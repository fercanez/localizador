<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . "/includes/init.php";
require_once dirname(__DIR__, 2) . "/includes/auth.php";

header("Content-Type: application/json; charset=UTF-8");
header("Cache-Control: no-store, no-cache, must-revalidate");

if (!mxli_is_logged_in() || !mxli_is_admin()) {
    mxli_json_response(["ok" => false, "error" => "Acceso denegado"], 403);
}

$method = $_SERVER["REQUEST_METHOD"] ?? "GET";
$raw = file_get_contents("php://input") ?: "";
$body = json_decode($raw, true);
if (!is_array($body)) {
    $body = [];
}

try {
    $pdo = mxli_db();

    if ($method === "GET") {
        $stmt = $pdo->query(
            "SELECT id, username, full_name, email, role, is_active, created_at, updated_at, last_login_at
             FROM app_users
             ORDER BY role DESC, username ASC"
        );
        $rows = $stmt->fetchAll();
        $users = array_map("mxli_public_user", $rows);
        mxli_json_response(["ok" => true, "users" => $users]);
    }

    if ($method === "POST") {
        $username = trim((string)($body["username"] ?? ""));
        $password = (string)($body["password"] ?? "");
        $fullName = trim((string)($body["full_name"] ?? ""));
        $email = trim((string)($body["email"] ?? ""));
        $role = (string)($body["role"] ?? "user");
        $isActive = array_key_exists("is_active", $body) ? (bool)$body["is_active"] : true;

        if ($username === "" || strlen($username) < 3) {
            mxli_json_response(["ok" => false, "error" => "El usuario debe tener al menos 3 caracteres"], 400);
        }
        if (strlen($password) < 6) {
            mxli_json_response(["ok" => false, "error" => "La contraseña debe tener al menos 6 caracteres"], 400);
        }
        if (!in_array($role, ["admin", "user"], true)) {
            mxli_json_response(["ok" => false, "error" => "Rol inválido"], 400);
        }

        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare(
            "INSERT INTO app_users (username, password_hash, full_name, email, role, is_active)
             VALUES (:username, :hash, :full_name, :email, :role, :active)
             RETURNING id, username, full_name, email, role, is_active, created_at, updated_at, last_login_at"
        );

        try {
            $stmt->execute([
                "username" => $username,
                "hash" => $hash,
                "full_name" => $fullName,
                "email" => $email,
                "role" => $role,
                "active" => $isActive,
            ]);
        } catch (PDOException $e) {
            if (str_contains($e->getMessage(), "app_users_username_key") || str_contains($e->getMessage(), "unique")) {
                mxli_json_response(["ok" => false, "error" => "El usuario ya existe"], 409);
            }
            throw $e;
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
            ? (string)$body["role"]
            : (string)$current["role"];
        $isActive = array_key_exists("is_active", $body)
            ? (bool)$body["is_active"]
            : (bool)$current["is_active"];

        if (!in_array($role, ["admin", "user"], true)) {
            mxli_json_response(["ok" => false, "error" => "Rol inválido"], 400);
        }

        // Evitar que el admin se quite el rol o se desactive a sí mismo
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
            "active" => $isActive,
        ];

        $sql = "UPDATE app_users
                SET full_name = :full_name,
                    email = :email,
                    role = :role,
                    is_active = :active,
                    updated_at = NOW()";

        if (!empty($body["password"])) {
            if (strlen((string)$body["password"]) < 6) {
                mxli_json_response(["ok" => false, "error" => "La contraseña debe tener al menos 6 caracteres"], 400);
            }
            $sql .= ", password_hash = :hash";
            $params["hash"] = password_hash((string)$body["password"], PASSWORD_DEFAULT);
        }

        $sql .= " WHERE id = :id
                  RETURNING id, username, full_name, email, role, is_active, created_at, updated_at, last_login_at";

        $upd = $pdo->prepare($sql);
        $upd->execute($params);
        $row = $upd->fetch();

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

        // Soft delete / desactivar
        $stmt = $pdo->prepare(
            "UPDATE app_users
             SET is_active = FALSE, updated_at = NOW()
             WHERE id = :id
             RETURNING id, username, full_name, email, role, is_active, created_at, updated_at, last_login_at"
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
        "error" => "Error en el servidor al administrar usuarios",
        "detail" => $e->getMessage(),
    ], 500);
}
