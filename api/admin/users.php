<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . "/includes/init.php";
require_once dirname(__DIR__, 2) . "/includes/auth.php";

header("Cache-Control: no-store, no-cache, must-revalidate");

if (!mxli_is_logged_in() || !mxli_can("users.manage")) {
    header("Content-Type: application/json; charset=UTF-8");
    mxli_json_response(["ok" => false, "error" => "Acceso denegado"], 403);
}

$method = $_SERVER["REQUEST_METHOD"] ?? "GET";
$exportMode = strtolower(trim((string)($_GET["export"] ?? "")));
if (!($method === "GET" && $exportMode === "csv")) {
    header("Content-Type: application/json; charset=UTF-8");
}
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
    if (str_contains($msg, "can_view_padron") && str_contains($msg, "does not exist")) {
        return "Falta la columna de padrón. En el servidor ejecute: php scripts/migrate_padron_permission.php";
    }
    if (
        str_contains($msg, "22001")
        || str_contains($msg, "right truncated")
        || str_contains($msg, "value too long")
        || (str_contains($msg, "varying") && str_contains($msg, "too long"))
    ) {
        return "Columna role demasiado corta (VARCHAR). Ejecute SQL: ALTER TABLE app_users ALTER COLUMN role TYPE VARCHAR(80); o redeploy users.php";
    }
    if (str_contains($msg, "app_users_username_key") || str_contains($msg, "unique")) {
        return "El usuario ya existe";
    }
    return "Error en el servidor al administrar usuarios";
}

function mxli_is_role_column_schema_error(Throwable $e): bool
{
    $msg = $e->getMessage();
    return str_contains($msg, "22001")
        || str_contains($msg, "right truncated")
        || str_contains($msg, "value too long")
        || str_contains($msg, "app_users_role_check")
        || str_contains($msg, "check constraint")
        || (str_contains($msg, "role") && str_contains($msg, "violates"));
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

function mxli_ensure_padron_user_column(PDO $pdo): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $pdo->exec(
        "ALTER TABLE app_users
         ADD COLUMN IF NOT EXISTS can_view_padron BOOLEAN NOT NULL DEFAULT FALSE"
    );
    $done = true;
}

/**
 * Roles custom: quitar TODOS los CHECK sobre role y ampliar a VARCHAR(80).
 * $force=true ignora caché (reintento tras error 22001 / CHECK).
 */
function mxli_ensure_users_role_column(PDO $pdo, bool $force = false): void
{
    static $done = false;
    if ($done && !$force) {
        return;
    }

    // Nombre histórico del CHECK del schema original
    $pdo->exec("ALTER TABLE app_users DROP CONSTRAINT IF EXISTS app_users_role_check");

    // Por si el CHECK tiene otro nombre (p.ej. tras migrate_roles)
    $pdo->exec(
        "DO \$\$
         DECLARE r record;
         BEGIN
           FOR r IN
             SELECT c.conname
             FROM pg_constraint c
             JOIN pg_attribute a
               ON a.attrelid = c.conrelid
              AND a.attnum = ANY (c.conkey)
             WHERE c.conrelid = 'app_users'::regclass
               AND c.contype = 'c'
               AND a.attname = 'role'
           LOOP
             EXECUTE format('ALTER TABLE app_users DROP CONSTRAINT IF EXISTS %I', r.conname);
           END LOOP;
         END
         \$\$"
    );

    // schema original VARCHAR(20) o más corto; slugs custom necesitan más espacio
    $pdo->exec(
        "ALTER TABLE app_users
         ALTER COLUMN role TYPE VARCHAR(80)
         USING trim(both FROM role::text)"
    );
    $pdo->exec("ALTER TABLE app_users ALTER COLUMN role SET DEFAULT 'consulta'");
    $done = true;
}

/**
 * INSERT/UPDATE con un reintento si la columna role sigue corta o con CHECK.
 */
function mxli_users_exec_with_role_retry(PDO $pdo, PDOStatement $stmt, array $params): array
{
    try {
        $stmt->execute($params);
        $row = $stmt->fetch();
        return is_array($row) ? $row : [];
    } catch (PDOException $e) {
        if (!mxli_is_role_column_schema_error($e)) {
            throw $e;
        }
        mxli_ensure_users_role_column($pdo, true);
        $stmt->execute($params);
        $row = $stmt->fetch();
        return is_array($row) ? $row : [];
    }
}

$returning =
    "id, username, full_name, email, role, is_active, can_view_padron, expires_at, created_at, updated_at, last_login_at,
     (session_token IS NOT NULL AND btrim(session_token) <> '') AS has_active_session";

try {
    $pdo = mxli_db();
    mxli_ensure_expires_column($pdo);
    mxli_ensure_padron_user_column($pdo);
    mxli_ensure_users_role_column($pdo);
    mxli_ensure_session_token_column($pdo);

    if ($method === "GET") {
        $q = trim((string)($_GET["q"] ?? $_GET["texto"] ?? $_GET["search"] ?? ""));
        $roleFilter = trim((string)($_GET["rol"] ?? $_GET["role"] ?? ""));
        if ($roleFilter === "user") {
            $roleFilter = "consulta";
        }
        $activoRaw = strtolower(trim((string)($_GET["activo"] ?? "")));
        $padronRaw = strtolower(trim((string)($_GET["padron"] ?? $_GET["can_view_padron"] ?? "")));

        $sql = "SELECT {$returning} FROM app_users WHERE 1=1";
        $params = [];

        if ($q !== "") {
            $sql .= " AND (
                username ILIKE :q
                OR COALESCE(full_name, '') ILIKE :q
                OR COALESCE(email, '') ILIKE :q
            )";
            $params["q"] = "%" . $q . "%";
        }
        if ($roleFilter !== "") {
            if ($roleFilter === "consulta") {
                $sql .= " AND (role = :role OR role = 'user')";
            } else {
                $sql .= " AND role = :role";
            }
            $params["role"] = $roleFilter;
        }
        if ($activoRaw === "1" || $activoRaw === "si" || $activoRaw === "sí" || $activoRaw === "true" || $activoRaw === "yes") {
            $sql .= " AND is_active = TRUE";
        } elseif ($activoRaw === "0" || $activoRaw === "no" || $activoRaw === "false") {
            $sql .= " AND is_active = FALSE";
        }
        if ($padronRaw === "1" || $padronRaw === "si" || $padronRaw === "sí" || $padronRaw === "true" || $padronRaw === "yes") {
            $sql .= " AND can_view_padron = TRUE";
        } elseif ($padronRaw === "0" || $padronRaw === "no" || $padronRaw === "false") {
            $sql .= " AND can_view_padron = FALSE";
        }

        $sql .= " ORDER BY role DESC, username ASC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll();
        $users = array_map("mxli_public_user", $rows);

        $export = strtolower(trim((string)($_GET["export"] ?? "")));
        if ($export === "csv") {
            $filename = "usuarios_" . date("Ymd_His") . ".csv";
            header("Content-Type: text/csv; charset=UTF-8");
            header("Content-Disposition: attachment; filename=\"{$filename}\"");
            header("Cache-Control: no-store, no-cache, must-revalidate");
            echo "\xEF\xBB\xBF";
            $out = fopen("php://output", "w");
            if ($out === false) {
                mxli_json_response(["ok" => false, "error" => "No se pudo generar el CSV"], 500);
            }
            fputcsv($out, [
                "usuario",
                "nombre_completo",
                "correo",
                "rol",
                "can_view_padron",
                "activo",
                "vence",
                "ultimo_acceso",
            ], ",");
            foreach ($users as $u) {
                fputcsv($out, [
                    (string)($u["username"] ?? ""),
                    (string)($u["full_name"] ?? ""),
                    (string)($u["email"] ?? ""),
                    (string)($u["role"] ?? ""),
                    !empty($u["can_view_padron"]) ? "1" : "0",
                    !empty($u["is_active"]) ? "1" : "0",
                    (string)($u["expires_at"] ?? ""),
                    (string)($u["last_login_at"] ?? ""),
                ], ",");
            }
            fclose($out);
            exit;
        }

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
        $roleInput = trim((string)($body["role"] ?? "consulta"));
        $role = mxli_resolve_role_slug($roleInput !== "" ? $roleInput : "consulta");
        $isActive = array_key_exists("is_active", $body) ? (bool)$body["is_active"] : true;
        $canViewPadron = array_key_exists("can_view_padron", $body)
            ? (bool)$body["can_view_padron"]
            : false;
        $expiresAt = array_key_exists("expires_at", $body)
            ? mxli_normalize_expires_at($body["expires_at"])
            : null;

        if ($username === "" || strlen($username) < 3) {
            mxli_json_response(["ok" => false, "error" => "El usuario debe tener al menos 3 caracteres"], 400);
        }
        if (strlen($password) < 6) {
            mxli_json_response(["ok" => false, "error" => "La contraseña debe tener al menos 6 caracteres"], 400);
        }
        if ($role === null) {
            mxli_json_response(["ok" => false, "error" => "Rol inválido: " . $roleInput], 400);
        }

        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare(
            "INSERT INTO app_users (username, password_hash, full_name, email, role, is_active, can_view_padron, expires_at)
             VALUES (:username, :hash, :full_name, :email, :role, " . mxli_sql_bool($isActive) . ", " . mxli_sql_bool($canViewPadron) . ", :expires_at)
             RETURNING {$returning}"
        );

        try {
            $row = mxli_users_exec_with_role_retry($pdo, $stmt, [
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

        mxli_json_response(["ok" => true, "user" => mxli_public_user($row)], 201);
    }

    if ($method === "PUT" || $method === "PATCH") {
        $id = (int)($body["id"] ?? 0);
        if ($id <= 0) {
            mxli_json_response(["ok" => false, "error" => "ID inválido"], 400);
        }

        // Cierre forzado de sesión remota (desbloquear usuario atrapado por session_policy=lock)
        if (!empty($body["clear_session"])) {
            $stmt = $pdo->prepare("SELECT id FROM app_users WHERE id = :id");
            $stmt->execute(["id" => $id]);
            if (!$stmt->fetch()) {
                mxli_json_response(["ok" => false, "error" => "Usuario no encontrado"], 404);
            }
            mxli_clear_user_session_token($id, $pdo);
            $stmt = $pdo->prepare("SELECT {$returning} FROM app_users WHERE id = :id");
            $stmt->execute(["id" => $id]);
            $row = $stmt->fetch();
            mxli_json_response([
                "ok" => true,
                "cleared" => true,
                "message" => "Sesión remota cerrada. El usuario ya puede iniciar sesión.",
                "user" => mxli_public_user(is_array($row) ? $row : ["id" => $id]),
            ]);
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
        if (array_key_exists("role", $body)) {
            $roleInput = trim((string)$body["role"]);
            $role = mxli_resolve_role_slug($roleInput);
            if ($role === null) {
                mxli_json_response(["ok" => false, "error" => "Rol inválido: " . $roleInput], 400);
            }
        } else {
            // Al activar/desactivar u otros PATCH parciales: no degradar rol custom a consulta
            $role = mxli_resolve_role_slug((string)$current["role"]);
            if ($role === null) {
                $role = trim((string)$current["role"]);
                if ($role === "" || strtolower($role) === "user") {
                    $role = "consulta";
                }
            }
        }
        $isActive = array_key_exists("is_active", $body)
            ? (bool)$body["is_active"]
            : mxli_to_bool($current["is_active"] ?? true);
        $canViewPadron = array_key_exists("can_view_padron", $body)
            ? (bool)$body["can_view_padron"]
            : mxli_to_bool($current["can_view_padron"] ?? false);
        $expiresAt = array_key_exists("expires_at", $body)
            ? mxli_normalize_expires_at($body["expires_at"])
            : mxli_normalize_expires_at($current["expires_at"] ?? null);

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
                    can_view_padron = " . mxli_sql_bool($canViewPadron) . ",
                    expires_at = :expires_at,
                    updated_at = NOW()";

        if (!$isActive) {
            $sql .= ", session_token = NULL, session_last_activity = NULL, session_ip = NULL";
        }

        if (!empty($body["password"])) {
            if (strlen((string)$body["password"]) < 6) {
                mxli_json_response(["ok" => false, "error" => "La contraseña debe tener al menos 6 caracteres"], 400);
            }
            $sql .= ", password_hash = :hash";
            $params["hash"] = password_hash((string)$body["password"], PASSWORD_DEFAULT);
        }

        $sql .= " WHERE id = :id RETURNING {$returning}";

        try {
            // Forzar migración role antes del UPDATE (evita 22001 si ALTER previo falló/no corrió)
            mxli_ensure_users_role_column($pdo, true);
            $upd = $pdo->prepare($sql);
            $row = mxli_users_exec_with_role_retry($pdo, $upd, $params);
        } catch (PDOException $e) {
            mxli_json_response([
                "ok" => false,
                "error" => mxli_users_db_error_message($e),
                "detail" => $e->getMessage(),
            ], 500);
        }

        if ($me && (int)$me["id"] === $id && is_array($row)) {
            mxli_set_session_user($row);
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
             SET is_active = FALSE, session_token = NULL, session_last_activity = NULL, session_ip = NULL, updated_at = NOW()
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
