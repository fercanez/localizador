<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . "/includes/init.php";
require_once dirname(__DIR__, 2) . "/includes/auth.php";

header("Content-Type: application/json; charset=UTF-8");
header("Cache-Control: no-store, no-cache, must-revalidate");

if (!mxli_is_logged_in() || !mxli_can("users.manage")) {
    mxli_json_response(["ok" => false, "error" => "Acceso denegado"], 403);
}

$method = $_SERVER["REQUEST_METHOD"] ?? "GET";
$raw = file_get_contents("php://input") ?: "";
$body = json_decode($raw, true);
if (!is_array($body)) {
    $body = [];
}

function mxli_sql_bool(bool $value): string
{
    return $value ? "TRUE" : "FALSE";
}

function mxli_users_db_error_message(Throwable $e): string
{
    $msg = $e->getMessage();
    if (
        str_contains($msg, "app_users_role_check")
        || str_contains($msg, "check constraint")
        || (str_contains($msg, "role") && str_contains($msg, "violates"))
    ) {
        return "Ejecute en el servidor: php scripts/migrate_custom_roles.php (y migrate_roles.php / migrate_user_expiry.php si faltan)";
    }
    if (str_contains($msg, "expires_at") && str_contains($msg, "does not exist")) {
        return "Falta la columna de vencimiento. En el servidor ejecute: php scripts/migrate_user_expiry.php";
    }
    if (str_contains($msg, "app_users_username_key") || str_contains($msg, "unique")) {
        return "El usuario ya existe";
    }
    return "Error en el servidor al administrar usuarios";
}

function mxli_ensure_expires_column(PDO $pdo): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $pdo->exec("ALTER TABLE app_users ADD COLUMN IF NOT EXISTS expires_at DATE NULL");
    $done = true;
}

$returning =
    "id, username, full_name, email, role, is_active, expires_at, created_at, updated_at, last_login_at";

try {
    $pdo = mxli_db();
    mxli_ensure_expires_column($pdo);

    if ($method === "GET") {
        $stmt = $pdo->query(
            "SELECT {$returning}
             FROM app_users
             ORDER BY role DESC, username ASC"
        );
        $rows = $stmt->fetchAll();
        $users = array_map("mxli_public_user", $rows);
        mxli_json_response([
            "ok" => true,
            "users" => $users,
            "roles" => array_map(static function ($r) {
                return [
                    "slug" => $r["slug"],
                    "name" => $r["name"],
                    "description" => $r["description"] ?? "",
                ];
            }, mxli_list_roles()),
        ]);
    }

    if ($method === "POST") {
        $username = trim((string)($body["username"] ?? ""));
        $password = (string)($body["password"] ?? "");
        $fullName = trim((string)($body["full_name"] ?? ""));
        $email = trim((string)($body["email"] ?? ""));
        $role = mxli_normalize_role((string)($body["role"] ?? "consulta"));
        $isActive = array_key_exists("is_active", $body) ? (bool)$body["is_active"] : true;
        $expiresAt = array_key_exists("expires_at", $body)
            ? mxli_normalize_expires_at($body["expires_at"])
            : null;

        if ($username === "" || strlen($username) < 3) {
            mxli_json_response(["ok" => false, "error" => "El usuario debe tener al menos 3 caracteres"], 400);
        }
        if (strlen($password) < 6) {
            mxli_json_response(["ok" => false, "error" => "La contraseña debe tener al menos 6 caracteres"], 400);
        }
        if (mxli_fetch_role($role) === null) {
            mxli_json_response(["ok" => false, "error" => "Rol inválido"], 400);
        }

        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare(
            "INSERT INTO app_users (username, password_hash, full_name, email, role, is_active, expires_at)
             VALUES (:username, :hash, :full_name, :email, :role, " . mxli_sql_bool($isActive) . ", :expires_at)
             RETURNING {$returning}"
        );

        try {
            $stmt->execute([
                "username" => $username,
                "hash" => $hash,
                "full_name" => $fullName,
                "email" => $email,
                "role" => $role,
                "expires_at" => $expiresAt,
            ]);
        } catch (PDOException $e) {
            mxli_json_response([
                "ok" => false,
                "error" => mxli_users_db_error_message($e),
                "detail" => $e->getMessage(),
            ], str_contains($e->getMessage(), "unique") ? 409 : 500);
        }

        $row = $stmt->fetch();
        mxli_json_response(["ok" => true, "user" => mxli_public_user($row)], 201);
    }

    if ($method === "PUT" || $method === "PATCH") {
        $id = (int)($body["id"] ?? 0);
        if ($id <= 0) {
            mxli_json_response(["ok" => false, "error" => "ID inválido"], 400);
        }

        $stmt = $pdo->prepare("SELECT * FROM app_users WHERE id = :id");
        $stmt->execute(["id" => $id]);
        $current = $stmt->fetch();
        if (!$current) {
            mxli_json_response(["ok" => false, "error" => "Usuario no encontrado"], 404);
        }

        $fullName = array_key_exists("full_name", $body)
            ? trim((string)$body["full_name"])
            : (string)$current["full_name"];
        $email = array_key_exists("email", $body)
            ? trim((string)$body["email"])
            : (string)$current["email"];
        $role = array_key_exists("role", $body)
            ? mxli_normalize_role((string)$body["role"])
            : mxli_normalize_role((string)$current["role"]);
        $isActive = array_key_exists("is_active", $body)
            ? (bool)$body["is_active"]
            : mxli_to_bool($current["is_active"] ?? true);
        $expiresAt = array_key_exists("expires_at", $body)
            ? mxli_normalize_expires_at($body["expires_at"])
            : mxli_normalize_expires_at($current["expires_at"] ?? null);

        if (mxli_fetch_role($role) === null) {
            mxli_json_response(["ok" => false, "error" => "Rol inválido"], 400);
        }

        $me = mxli_current_user();
        if ($me && (int)$me["id"] === $id) {
            if ($role !== "admin") {
                mxli_json_response(["ok" => false, "error" => "No puede quitarse el rol de administrador"], 400);
            }
            if (!$isActive) {
                mxli_json_response(["ok" => false, "error" => "No puede desactivarse a sí mismo"], 400);
            }
        }

        $params = [
            "id" => $id,
            "full_name" => $fullName,
            "email" => $email,
            "role" => $role,
            "expires_at" => $expiresAt,
        ];

        $sql = "UPDATE app_users
                SET full_name = :full_name,
                    email = :email,
                    role = :role,
                    is_active = " . mxli_sql_bool($isActive) . ",
                    expires_at = :expires_at,
                    updated_at = NOW()";

        if (!empty($body["password"])) {
            if (strlen((string)$body["password"]) < 6) {
                mxli_json_response(["ok" => false, "error" => "La contraseña debe tener al menos 6 caracteres"], 400);
            }
            $sql .= ", password_hash = :hash";
            $params["hash"] = password_hash((string)$body["password"], PASSWORD_DEFAULT);
        }

        $sql .= " WHERE id = :id RETURNING {$returning}";

        try {
            $upd = $pdo->prepare($sql);
            $upd->execute($params);
            $row = $upd->fetch();
        } catch (PDOException $e) {
            mxli_json_response([
                "ok" => false,
                "error" => mxli_users_db_error_message($e),
                "detail" => $e->getMessage(),
            ], 500);
        }

        mxli_json_response(["ok" => true, "user" => mxli_public_user($row)]);
    }

    if ($method === "DELETE") {
        $id = (int)($_GET["id"] ?? $body["id"] ?? 0);
        if ($id <= 0) {
            mxli_json_response(["ok" => false, "error" => "ID inválido"], 400);
        }

        $me = mxli_current_user();
        if ($me && (int)$me["id"] === $id) {
            mxli_json_response(["ok" => false, "error" => "No puede eliminar su propio usuario"], 400);
        }

        $stmt = $pdo->prepare(
            "UPDATE app_users
             SET is_active = FALSE, updated_at = NOW()
             WHERE id = :id
             RETURNING {$returning}"
        );
        $stmt->execute(["id" => $id]);
        $row = $stmt->fetch();
        if (!$row) {
            mxli_json_response(["ok" => false, "error" => "Usuario no encontrado"], 404);
        }

        mxli_json_response(["ok" => true, "user" => mxli_public_user($row)]);
    }

    mxli_json_response(["ok" => false, "error" => "Método no permitido"], 405);
} catch (Throwable $e) {
    mxli_json_response([
        "ok" => false,
        "error" => mxli_users_db_error_message($e),
        "detail" => $e->getMessage(),
    ], 500);
}
