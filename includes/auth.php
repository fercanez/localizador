<?php
declare(strict_types=1);

require_once __DIR__ . "/db.php";
require_once __DIR__ . "/layer-catalog.php";

/** Roles hardcodeados de respaldo si app_roles aún no existe */
function mxli_builtin_role_slugs(): array
{
    return ["admin", "consulta", "campo"];
}

function mxli_role_permissions_fallback(): array
{
    $seeds = [];
    foreach (mxli_default_role_seeds() as $seed) {
        $seeds[$seed["slug"]] = $seed["permissions"];
    }
    return $seeds;
}

function mxli_decode_json_list(mixed $value): array
{
    if (is_array($value)) {
        return array_values(array_map("strval", $value));
    }
    if (is_string($value) && $value !== "") {
        $decoded = json_decode($value, true);
        if (is_array($decoded)) {
            return array_values(array_map("strval", $decoded));
        }
    }
    return [];
}

function mxli_roles_table_exists(): bool
{
    static $exists = null;
    if ($exists !== null) {
        return $exists;
    }
    try {
        mxli_db()->query("SELECT 1 FROM app_roles LIMIT 1");
        $exists = true;
    } catch (Throwable $e) {
        $exists = false;
    }
    return $exists;
}

function mxli_clear_role_cache(?string $slug = null): void
{
    mxli_fetch_role("", $slug === null ? "*" : $slug);
}

/**
 * @return array{slug:string,name:string,description:string,is_system:bool,permissions:array,allowed_layers:array}|null
 */
function mxli_fetch_role(string $slug, ?string $clearSlug = null): ?array
{
    static $cache = [];
    if ($clearSlug !== null) {
        if ($clearSlug === "" || $clearSlug === "*") {
            $cache = [];
        } else {
            unset($cache[mxli_normalize_role_slug($clearSlug)]);
        }
        return null;
    }

    $slug = mxli_normalize_role_slug($slug);
    if (array_key_exists($slug, $cache)) {
        return $cache[$slug];
    }

    if (!mxli_roles_table_exists()) {
        foreach (mxli_default_role_seeds() as $seed) {
            if ($seed["slug"] === $slug) {
                $cache[$slug] = [
                    "slug" => $seed["slug"],
                    "name" => $seed["name"],
                    "description" => $seed["description"],
                    "is_system" => (bool)$seed["is_system"],
                    "permissions" => array_values($seed["permissions"]),
                    "allowed_layers" => array_values($seed["allowed_layers"]),
                ];
                return $cache[$slug];
            }
        }
        $cache[$slug] = null;
        return null;
    }

    try {
        $stmt = mxli_db()->prepare(
            "SELECT slug, name, description, is_system, permissions, allowed_layers
             FROM app_roles WHERE slug = :slug LIMIT 1"
        );
        $stmt->execute(["slug" => $slug]);
        $row = $stmt->fetch();
        if (!$row) {
            $cache[$slug] = null;
            return null;
        }
        $cache[$slug] = [
            "slug" => (string)$row["slug"],
            "name" => (string)$row["name"],
            "description" => (string)($row["description"] ?? ""),
            "is_system" => mxli_to_bool($row["is_system"] ?? false),
            "permissions" => mxli_decode_json_list($row["permissions"] ?? []),
            "allowed_layers" => mxli_decode_json_list($row["allowed_layers"] ?? ["*"]),
        ];
        return $cache[$slug];
    } catch (Throwable $e) {
        // No cachear fallos transitorios de BD como "rol inexistente"
        return null;
    }
}

/** @return list<array> */
function mxli_list_roles(): array
{
    if (!mxli_roles_table_exists()) {
        $out = [];
        foreach (mxli_default_role_seeds() as $seed) {
            $out[] = [
                "slug" => $seed["slug"],
                "name" => $seed["name"],
                "description" => $seed["description"],
                "is_system" => (bool)$seed["is_system"],
                "permissions" => array_values($seed["permissions"]),
                "allowed_layers" => array_values($seed["allowed_layers"]),
            ];
        }
        return $out;
    }

    $stmt = mxli_db()->query(
        "SELECT slug, name, description, is_system, permissions, allowed_layers
         FROM app_roles
         ORDER BY is_system DESC, name ASC"
    );
    $rows = $stmt->fetchAll();
    $out = [];
    foreach ($rows as $row) {
        $out[] = [
            "slug" => (string)$row["slug"],
            "name" => (string)$row["name"],
            "description" => (string)($row["description"] ?? ""),
            "is_system" => mxli_to_bool($row["is_system"] ?? false),
            "permissions" => mxli_decode_json_list($row["permissions"] ?? []),
            "allowed_layers" => mxli_decode_json_list($row["allowed_layers"] ?? ["*"]),
        ];
    }
    return $out;
}

function mxli_role_labels(): array
{
    $labels = [];
    foreach (mxli_list_roles() as $role) {
        $labels[$role["slug"]] = $role["name"];
    }
    if ($labels === []) {
        $labels = [
            "admin" => "Administrador",
            "consulta" => "Consulta",
            "campo" => "Campo",
        ];
    }
    return $labels;
}

function mxli_valid_roles(): array
{
    $slugs = array_column(mxli_list_roles(), "slug");
    return $slugs !== [] ? $slugs : mxli_builtin_role_slugs();
}

function mxli_normalize_role_slug(string $role): string
{
    $role = strtolower(trim($role));
    // Alinear con slugify: espacios/acentos residuales → guion bajo (no eliminar).
    // Antes "Gobierno Mpal" → "gobiernompal" y no coincidía con slug "gobierno_mpal".
    $role = preg_replace('/[^a-z0-9_\-]+/', '_', $role) ?? "";
    $role = trim($role, "_-");
    $role = preg_replace('/_+/', '_', $role) ?? "";
    if ($role === "user") {
        return "consulta";
    }
    return $role !== "" ? $role : "consulta";
}

/**
 * Resuelve entrada de admin (slug o nombre visible) al slug canónico en app_roles.
 * Null si no existe. Usar en create/update de usuarios (no degradar a consulta).
 */
function mxli_resolve_role_slug(string $roleOrName): ?string
{
    $raw = trim($roleOrName);
    if ($raw === "") {
        return null;
    }

    $candidates = [];
    $normalized = mxli_normalize_role_slug($raw);
    if ($normalized !== "") {
        $candidates[] = $normalized;
    }
    $slugified = mxli_slugify_role_name($raw);
    if ($slugified !== "" && !in_array($slugified, $candidates, true)) {
        $candidates[] = $slugified;
    }

    foreach ($candidates as $cand) {
        if (mxli_fetch_role($cand) !== null) {
            return $cand;
        }
    }

    foreach (mxli_list_roles() as $role) {
        if (strcasecmp((string)$role["slug"], $raw) === 0) {
            return (string)$role["slug"];
        }
        if (strcasecmp((string)$role["name"], $raw) === 0) {
            return (string)$role["slug"];
        }
        if ($slugified !== "" && mxli_slugify_role_name((string)$role["name"]) === $slugified) {
            return (string)$role["slug"];
        }
    }

    return null;
}

function mxli_normalize_role(string $role): string
{
    $resolved = mxli_resolve_role_slug($role);
    if ($resolved !== null) {
        return $resolved;
    }
    // Fallback solo para sesiones / datos legados ilegibles
    return "consulta";
}

function mxli_role_label(string $role): string
{
    $resolved = mxli_resolve_role_slug($role) ?? mxli_normalize_role_slug($role);
    $fetched = mxli_fetch_role($resolved);
    if ($fetched) {
        return (string)$fetched["name"];
    }
    $labels = mxli_role_labels();
    return $labels[$resolved] ?? $resolved;
}

function mxli_to_bool(mixed $value): bool
{
    if (is_bool($value)) {
        return $value;
    }
    if (is_int($value) || is_float($value)) {
        return (int)$value === 1;
    }

    $text = strtolower(trim((string)$value));
    return in_array($text, ["1", "t", "true", "yes", "on"], true);
}

function mxli_normalize_expires_at(mixed $value): ?string
{
    if ($value === null) {
        return null;
    }
    $text = trim((string)$value);
    if ($text === "" || strtolower($text) === "null") {
        return null;
    }

    if (preg_match('/^(\d{4}-\d{2}-\d{2})/', $text, $m)) {
        $date = $m[1];
        $dt = DateTimeImmutable::createFromFormat("Y-m-d", $date);
        if ($dt && $dt->format("Y-m-d") === $date) {
            return $date;
        }
    }

    return null;
}

function mxli_user_is_expired(array $row): bool
{
    $expires = mxli_normalize_expires_at($row["expires_at"] ?? null);
    if ($expires === null) {
        return false;
    }
    return $expires < date("Y-m-d");
}

function mxli_permissions_for_role(string $role): array
{
    $role = mxli_normalize_role_slug($role);
    $fetched = mxli_fetch_role($role);
    if ($fetched) {
        $perms = $fetched["permissions"];
        if ($role === "admin" && !in_array("users.manage", $perms, true)) {
            $perms[] = "users.manage";
        }
        if ($role === "admin") {
            foreach (array_keys(mxli_tool_permission_defs()) as $p) {
                if (!in_array($p, $perms, true)) {
                    $perms[] = $p;
                }
            }
        }
        if (!in_array("map.view", $perms, true)) {
            array_unshift($perms, "map.view");
        }
        return array_values(array_unique($perms));
    }

    $matrix = mxli_role_permissions_fallback();
    return $matrix[$role] ?? $matrix["consulta"];
}

function mxli_layers_for_role(string $role): array
{
    $role = mxli_normalize_role_slug($role);
    if ($role === "admin") {
        return ["*"];
    }
    $fetched = mxli_fetch_role($role);
    if ($fetched) {
        $layers = $fetched["allowed_layers"];
        return $layers !== [] ? $layers : ["*"];
    }
    return ["*"];
}

/**
 * Asegura columna de override por usuario para ver padrón.
 */
function mxli_ensure_padron_permission_column(?PDO $pdo = null): void
{
    static $done = false;
    if ($done) {
        return;
    }
    try {
        $pdo = $pdo ?? mxli_db();
        $pdo->exec(
            "ALTER TABLE app_users
             ADD COLUMN IF NOT EXISTS can_view_padron BOOLEAN NOT NULL DEFAULT FALSE"
        );
        $done = true;
    } catch (Throwable $e) {
        // La migración puede ejecutarse aparte; no bloquear el resto del auth.
    }
}

/**
 * Asegura columnas de sesión única / idle / IP.
 */
function mxli_ensure_session_token_column(?PDO $pdo = null): void
{
    static $done = false;
    if ($done) {
        return;
    }
    try {
        $pdo = $pdo ?? mxli_db();
        $pdo->exec(
            "ALTER TABLE app_users
             ADD COLUMN IF NOT EXISTS session_token VARCHAR(64) NULL"
        );
        $pdo->exec(
            "ALTER TABLE app_users
             ADD COLUMN IF NOT EXISTS session_last_activity TIMESTAMPTZ NULL"
        );
        $pdo->exec(
            "ALTER TABLE app_users
             ADD COLUMN IF NOT EXISTS session_ip VARCHAR(45) NULL"
        );
        $done = true;
    } catch (Throwable $e) {
        // La migración puede ejecutarse aparte; no bloquear el resto del auth.
    }
}

/**
 * Segundos de inactividad tras los cuales la sesión caduca (default 300 = 5 min).
 * Acepta legacy session_idle_hours si no hay session_idle_seconds.
 */
function mxli_session_idle_seconds(): int
{
    global $MXLI_CONFIG;
    if (isset($MXLI_CONFIG["session_idle_seconds"])) {
        $sec = (int)$MXLI_CONFIG["session_idle_seconds"];
        return $sec > 0 ? $sec : 300;
    }
    if (isset($MXLI_CONFIG["session_idle_hours"])) {
        $hours = (int)$MXLI_CONFIG["session_idle_hours"];
        return $hours > 0 ? ($hours * 3600) : 300;
    }
    return 300;
}

/** @deprecated Use mxli_session_idle_seconds() */
function mxli_session_idle_hours(): int
{
    return max(1, (int)ceil(mxli_session_idle_seconds() / 3600));
}

function mxli_session_bind_ip(): bool
{
    global $MXLI_CONFIG;
    if (!array_key_exists("session_bind_ip", $MXLI_CONFIG ?? [])) {
        return true;
    }
    $raw = $MXLI_CONFIG["session_bind_ip"];
    if (is_bool($raw)) {
        return $raw;
    }
    $s = strtolower(trim((string)$raw));
    return !in_array($s, ["0", "false", "no", "off", ""], true);
}

function mxli_client_ip(): string
{
    $ip = (string)($_SERVER["REMOTE_ADDR"] ?? "");
    return substr(trim($ip), 0, 45);
}

/**
 * Política de sesión única:
 * - lock (default): rechaza segundo login si ya hay token activo en BD
 * - invalidate: nuevo login invalida la sesión anterior
 *
 * Excepción: rol admin siempre puede entrar (reemplaza token).
 * Auto-expire: si session_last_activity (o last_login_at) es más antiguo
 * que session_idle_seconds, se permite login.
 */
function mxli_session_policy(): string
{
    $policy = strtolower(trim((string)mxli_config("session_policy", "lock")));
    return $policy === "invalidate" ? "invalidate" : "lock";
}

/**
 * True si la sesión remota está caducada por inactividad (o no hay fecha).
 */
function mxli_session_token_is_stale(array $row): bool
{
    $last = $row["session_last_activity"] ?? null;
    if ($last === null || trim((string)$last) === "") {
        $last = $row["last_login_at"] ?? null;
    }
    if ($last === null || trim((string)$last) === "") {
        return true;
    }
    $ts = strtotime((string)$last);
    if ($ts === false) {
        return true;
    }
    return (time() - $ts) >= mxli_session_idle_seconds();
}

function mxli_generate_session_token(): string
{
    return bin2hex(random_bytes(32));
}

/**
 * Motivo de fallo de auth en esta petición (p. ej. session_replaced).
 */
function mxli_auth_fail_reason(): ?string
{
    $reason = $GLOBALS["mxli_auth_fail_reason"] ?? null;
    return is_string($reason) && $reason !== "" ? $reason : null;
}

function mxli_set_auth_fail_reason(?string $reason): void
{
    if ($reason === null || $reason === "") {
        unset($GLOBALS["mxli_auth_fail_reason"]);
        return;
    }
    $GLOBALS["mxli_auth_fail_reason"] = $reason;
}

/**
 * Destruye la sesión PHP sin tocar session_token en BD
 * (p. ej. sesión reemplazada por otro dispositivo).
 */
function mxli_destroy_php_session(): void
{
    $_SESSION = [];

    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            "",
            time() - 42000,
            $params["path"],
            $params["domain"],
            (bool)$params["secure"],
            (bool)$params["httponly"]
        );
    }

    setcookie("email", "", time() - 42000, "/");

    if (session_status() === PHP_SESSION_ACTIVE) {
        session_destroy();
    }
}

/**
 * Valida que $_SESSION['session_token'] coincida con app_users.session_token.
 * Una sola consulta por request (cache estático).
 */
function mxli_session_token_matches_db(): bool
{
    static $checked = false;
    static $ok = false;

    if ($checked) {
        return $ok;
    }
    $checked = true;

    $userId = (int)($_SESSION["user"]["id"] ?? 0);
    $token = (string)($_SESSION["session_token"] ?? "");
    if ($userId <= 0 || $token === "") {
        // Sesión PHP antigua (sin token): forzar re-login silencioso
        mxli_destroy_php_session();
        $ok = false;
        return false;
    }

    try {
        mxli_ensure_session_token_column();
        $stmt = mxli_db()->prepare(
            "SELECT session_token FROM app_users WHERE id = :id LIMIT 1"
        );
        $stmt->execute(["id" => $userId]);
        $dbToken = $stmt->fetchColumn();
        if ($dbToken === false || (string)$dbToken !== $token) {
            mxli_set_auth_fail_reason("session_replaced");
            mxli_destroy_php_session();
            $ok = false;
            return false;
        }
        $ok = true;
        return true;
    } catch (Throwable $e) {
        // Si la BD no responde, no expulsar a todos por un fallo transitorio.
        $ok = true;
        return true;
    }
}

/**
 * Emite sesión única: nuevo token en BD + $_SESSION.
 * Invalida cualquier sesión previa del mismo usuario (política invalidate).
 */
function mxli_start_user_session(array $row): array
{
    mxli_ensure_session_token_column();
    $token = mxli_generate_session_token();
    $ip = mxli_client_ip();
    $upd = mxli_db()->prepare(
        "UPDATE app_users
         SET session_token = :token,
             session_last_activity = NOW(),
             session_ip = :ip,
             last_login_at = NOW(),
             updated_at = NOW()
         WHERE id = :id"
    );
    $upd->execute([
        "token" => $token,
        "ip" => $ip !== "" ? $ip : null,
        "id" => (int)$row["id"],
    ]);
    $row["session_token"] = $token;
    $row["last_login_at"] = date("c");
    $row["session_last_activity"] = date("c");
    $row["session_ip"] = $ip;

    mxli_set_session_user($row);
    $_SESSION["session_token"] = $token;
    $_SESSION["session_ip"] = $ip;
    $_SESSION["last_activity"] = time();
    mxli_set_auth_fail_reason(null);

    return $row;
}

/**
 * Si session_policy=lock y ya hay token activo, no permite segundo login
 * (excepto admin, o token caducado por idle timeout).
 * @return string|null mensaje de error en español, o null si puede continuar
 */
function mxli_session_lock_error(array $row): ?string
{
    if (mxli_session_policy() !== "lock") {
        return null;
    }

    $role = mxli_normalize_role((string)($row["role"] ?? "consulta"));
    // Administrador siempre puede entrar (invalida token anterior al iniciar sesión).
    if ($role === "admin") {
        return null;
    }

    $existing = trim((string)($row["session_token"] ?? ""));
    if ($existing === "") {
        return null;
    }

    // Sesión abandonada (sin logout): permitir tras idle desde session_last_activity.
    if (mxli_session_token_is_stale($row)) {
        return null;
    }

    return "Ya hay una sesión activa para este usuario. Cierre la otra sesión o contacte al administrador.";
}

/**
 * True si el usuario tiene session_token en BD (sesión remota activa).
 */
function mxli_user_has_active_session(array $row): bool
{
    if (array_key_exists("has_active_session", $row)) {
        return mxli_to_bool($row["has_active_session"]);
    }
    return trim((string)($row["session_token"] ?? "")) !== "";
}

/**
 * Limpia session_token de un usuario (cierre forzado por admin / desbloqueo).
 */
function mxli_clear_user_session_token(int $userId, ?PDO $pdo = null): bool
{
    if ($userId <= 0) {
        return false;
    }
    $pdo = $pdo ?? mxli_db();
    mxli_ensure_session_token_column($pdo);
    $stmt = $pdo->prepare(
        "UPDATE app_users
         SET session_token = NULL,
             session_last_activity = NULL,
             session_ip = NULL,
             updated_at = NOW()
         WHERE id = :id"
    );
    $stmt->execute(["id" => $userId]);
    return $stmt->rowCount() > 0;
}

/**
 * Actualiza marca de actividad en PHP session y (opcionalmente) BD.
 */
function mxli_touch_session_activity(bool $updateDb = true): void
{
    $_SESSION["last_activity"] = time();
    if (!$updateDb) {
        return;
    }
    $userId = (int)($_SESSION["user"]["id"] ?? 0);
    $token = (string)($_SESSION["session_token"] ?? "");
    if ($userId <= 0 || $token === "") {
        return;
    }
    try {
        mxli_ensure_session_token_column();
        $stmt = mxli_db()->prepare(
            "UPDATE app_users
             SET session_last_activity = NOW(), updated_at = NOW()
             WHERE id = :id AND session_token = :token"
        );
        $stmt->execute(["id" => $userId, "token" => $token]);
    } catch (Throwable $e) {
        // No bloquear la petición por fallo de touch.
    }
}

/**
 * Valida idle + IP de la sesión PHP actual.
 * @return bool true si la sesión sigue válida
 */
function mxli_validate_session_constraints(bool $touchActivity = false): bool
{
    $idleSec = mxli_session_idle_seconds();
    $last = (int)($_SESSION["last_activity"] ?? 0);
    if ($last <= 0) {
        // Sesiones antiguas sin marca: inicializar y continuar una vez.
        $_SESSION["last_activity"] = time();
        $last = (int)$_SESSION["last_activity"];
    }
    if ((time() - $last) >= $idleSec) {
        mxli_set_auth_fail_reason("session_idle");
        mxli_logout();
        return false;
    }

    if (mxli_session_bind_ip()) {
        $bound = (string)($_SESSION["session_ip"] ?? "");
        $current = mxli_client_ip();
        if ($bound === "") {
            $_SESSION["session_ip"] = $current;
        } elseif ($current !== "" && $bound !== $current) {
            mxli_set_auth_fail_reason("session_ip");
            mxli_logout();
            return false;
        }
    }

    if ($touchActivity) {
        mxli_touch_session_activity(true);
    }

    return true;
}

/**
 * Heartbeat de sesión: comprueba token/idle/IP y opcionalmente refresca actividad.
 *
 * @return array{ok:bool, reason?:string, idle_seconds?:int, remaining_seconds?:int}
 */
function mxli_session_heartbeat(bool $userActivity = false): array
{
    if (empty($_SESSION["user"]) || !is_array($_SESSION["user"])) {
        return ["ok" => false, "reason" => "unauthenticated"];
    }

    if (!mxli_session_token_matches_db()) {
        $reason = mxli_auth_fail_reason() ?: "session_replaced";
        return ["ok" => false, "reason" => $reason];
    }

    // Siempre refrescar "visto" en BD para liberar lock tras desconexión.
    // La actividad de usuario solo mueve el reloj de idle en PHP session.
    if ($userActivity) {
        if (!mxli_validate_session_constraints(true)) {
            return [
                "ok" => false,
                "reason" => mxli_auth_fail_reason() ?: "session_idle",
                "idle_seconds" => mxli_session_idle_seconds(),
            ];
        }
    } else {
        if (!mxli_validate_session_constraints(false)) {
            return [
                "ok" => false,
                "reason" => mxli_auth_fail_reason() ?: "session_idle",
                "idle_seconds" => mxli_session_idle_seconds(),
            ];
        }
        // Conexión viva sin actividad de usuario: actualizar solo BD last_seen
        // para el desbloqueo de sesión única, sin extender idle PHP.
        $userId = (int)($_SESSION["user"]["id"] ?? 0);
        $token = (string)($_SESSION["session_token"] ?? "");
        if ($userId > 0 && $token !== "") {
            try {
                mxli_ensure_session_token_column();
                $stmt = mxli_db()->prepare(
                    "UPDATE app_users
                     SET session_last_activity = NOW(), updated_at = NOW()
                     WHERE id = :id AND session_token = :token"
                );
                $stmt->execute(["id" => $userId, "token" => $token]);
            } catch (Throwable $e) {
            }
        }
    }

    $last = (int)($_SESSION["last_activity"] ?? time());
    $idle = mxli_session_idle_seconds();
    $remaining = max(0, $idle - (time() - $last));

    return [
        "ok" => true,
        "idle_seconds" => $idle,
        "remaining_seconds" => $remaining,
    ];
}

function mxli_user_permissions(?array $user = null): array
{
    $user = $user ?? mxli_current_user();
    if ($user === null) {
        return [];
    }
    $perms = mxli_permissions_for_role((string)($user["role"] ?? "consulta"));
    if (mxli_to_bool($user["can_view_padron"] ?? false) && !in_array("map.padron", $perms, true)) {
        $perms[] = "map.padron";
    }
    return array_values($perms);
}

function mxli_allowed_layers(?array $user = null): array
{
    $user = $user ?? mxli_current_user();
    if ($user === null) {
        return [];
    }
    if (!empty($user["allowed_layers"]) && is_array($user["allowed_layers"])) {
        return array_values(array_map("strval", $user["allowed_layers"]));
    }
    return mxli_layers_for_role((string)($user["role"] ?? "consulta"));
}

function mxli_can(string $permission, ?array $user = null): bool
{
    return in_array($permission, mxli_user_permissions($user), true);
}

function mxli_can_layer(string $layerKey, ?array $user = null): bool
{
    $layerKey = trim($layerKey);
    if ($layerKey === "") {
        return false;
    }
    // Fallas geológicas no entra en el comodín "*". Consulta y Campo lo usan,
    // y solo administrador y perito valuador deben ver esta capa.
    if ($layerKey === "fallas_geologicas") {
        $user = $user ?? (function_exists("mxli_current_user") ? mxli_current_user() : null);
        if ($user === null) {
            return false;
        }
        $role = mb_strtolower((string)($user["role"] ?? ""), "UTF-8");
        if ($role === "admin") {
            return true;
        }
        return function_exists("mxli_user_is_perito_valuador") && mxli_user_is_perito_valuador($user);
    }
    $allowed = mxli_allowed_layers($user);
    if (in_array("*", $allowed, true)) {
        return true;
    }
    return in_array($layerKey, $allowed, true);
}

function mxli_public_user(array $row): array
{
    $role = mxli_normalize_role((string)$row["role"]);
    $expiresAt = mxli_normalize_expires_at($row["expires_at"] ?? null);
    $user = [
        "id" => (int)$row["id"],
        "username" => (string)$row["username"],
        "full_name" => (string)($row["full_name"] ?? ""),
        "email" => (string)($row["email"] ?? ""),
        "role" => $role,
        "role_label" => mxli_role_label($role),
        "can_view_padron" => mxli_to_bool($row["can_view_padron"] ?? false),
        "allowed_layers" => mxli_layers_for_role($role),
        "is_active" => mxli_to_bool($row["is_active"] ?? false),
        "expires_at" => $expiresAt,
        "is_expired" => mxli_user_is_expired($row),
        "created_at" => $row["created_at"] ?? null,
        "updated_at" => $row["updated_at"] ?? null,
        "last_login_at" => $row["last_login_at"] ?? null,
        "has_active_session" => mxli_user_has_active_session($row),
    ];
    $user["permissions"] = mxli_user_permissions($user);
    return $user;
}

function mxli_session_payload(array $row): array
{
    $role = mxli_normalize_role((string)$row["role"]);
    $payload = [
        "id" => (int)$row["id"],
        "username" => (string)$row["username"],
        "full_name" => (string)($row["full_name"] ?? ""),
        "email" => (string)($row["email"] ?? ""),
        "role" => $role,
        "role_label" => mxli_role_label($role),
        "can_view_padron" => mxli_to_bool($row["can_view_padron"] ?? false),
        "allowed_layers" => mxli_layers_for_role($role),
        "expires_at" => mxli_normalize_expires_at($row["expires_at"] ?? null),
    ];
    $payload["permissions"] = mxli_user_permissions($payload);
    return $payload;
}

function mxli_set_session_user(array $row): void
{
    $_SESSION["user"] = mxli_session_payload($row);
    $_SESSION["email"] = (string)$row["username"];
}

function mxli_current_user(): ?array
{
    if (empty($_SESSION["user"]) || !is_array($_SESSION["user"])) {
        return null;
    }

    if (!mxli_session_token_matches_db()) {
        return null;
    }

    // Idle / IP: caducar sesión aunque el cliente no haga logout.
    // touchActivity=true: cualquier petición autenticada cuenta como uso
    // (el heartbeat sin activity no pasa por aquí con touch; usa mxli_session_heartbeat).
    static $constraintsChecked = false;
    if (!$constraintsChecked) {
        $constraintsChecked = true;
        $isHeartbeat = false;
        $script = (string)($_SERVER["SCRIPT_NAME"] ?? "");
        if (str_ends_with($script, "/api/auth/heartbeat.php")) {
            $isHeartbeat = true;
        }
        if ($isHeartbeat) {
            // Heartbeat gestiona touch/activity por su cuenta.
            if (!mxli_validate_session_constraints(false)) {
                return null;
            }
        } else {
            if (!mxli_validate_session_constraints(true)) {
                return null;
            }
        }
    }

    $user = $_SESSION["user"];
    $user["role"] = mxli_normalize_role((string)($user["role"] ?? "consulta"));
    $user["role_label"] = mxli_role_label($user["role"]);
    $user["can_view_padron"] = mxli_to_bool($user["can_view_padron"] ?? false);
    $user["permissions"] = mxli_user_permissions($user);
    $user["allowed_layers"] = mxli_layers_for_role($user["role"]);
    $user["expires_at"] = mxli_normalize_expires_at($user["expires_at"] ?? null);
    $_SESSION["user"]["role"] = $user["role"];
    $_SESSION["user"]["role_label"] = $user["role_label"];
    $_SESSION["user"]["can_view_padron"] = $user["can_view_padron"];
    $_SESSION["user"]["permissions"] = $user["permissions"];
    $_SESSION["user"]["allowed_layers"] = $user["allowed_layers"];
    $_SESSION["user"]["expires_at"] = $user["expires_at"];
    return $user;
}

function mxli_is_logged_in(): bool
{
    return mxli_current_user() !== null;
}

function mxli_user_role(): string
{
    $user = mxli_current_user();
    return $user ? mxli_normalize_role((string)($user["role"] ?? "consulta")) : "";
}

function mxli_is_admin(): bool
{
    return mxli_user_role() === "admin" || mxli_can("users.manage");
}

function mxli_require_login(): void
{
    $user = mxli_current_user();
    if ($user !== null) {
        $expires = mxli_normalize_expires_at($user["expires_at"] ?? null);
        if ($expires !== null && $expires < date("Y-m-d")) {
            mxli_logout();
            header("Location: login.php?expired=1");
            exit;
        }
        return;
    }

    $fail = mxli_auth_fail_reason();
    if ($fail === "session_replaced") {
        header("Location: login.php?replaced=1");
        exit;
    }
    if ($fail === "session_idle") {
        header("Location: login.php?idle=1");
        exit;
    }
    if ($fail === "session_ip") {
        header("Location: login.php?disconnect=1");
        exit;
    }

    $next = $_SERVER["REQUEST_URI"] ?? "index.php";
    header("Location: login.php?next=" . rawurlencode($next));
    exit;
}

function mxli_require_admin(): void
{
    mxli_require_permission("users.manage");
}

function mxli_require_permission(string $permission): void
{
    mxli_require_login();
    if (mxli_can($permission)) {
        return;
    }

    http_response_code(403);
    echo "Acceso denegado. No tiene permiso: " . htmlspecialchars($permission, ENT_QUOTES, "UTF-8");
    exit;
}

function mxli_slugify_role_name(string $name): string
{
    $slug = strtolower(trim($name));
    $slug = iconv("UTF-8", "ASCII//TRANSLIT//IGNORE", $slug) ?: $slug;
    $slug = preg_replace('/[^a-z0-9]+/', "_", $slug) ?? "";
    $slug = trim($slug, "_");
    if ($slug === "") {
        $slug = "rol_" . substr(bin2hex(random_bytes(3)), 0, 6);
    }
    return substr($slug, 0, 80);
}

function mxli_authenticate(string $username, string $password): ?array
{
    $username = trim($username);
    if ($username === "" || $password === "") {
        return null;
    }

    mxli_ensure_padron_permission_column();
    mxli_ensure_session_token_column();

    $stmt = mxli_db()->prepare(
        "SELECT * FROM app_users WHERE username = :u LIMIT 1"
    );
    $stmt->execute(["u" => $username]);
    $row = $stmt->fetch();

    if (!$row || !mxli_to_bool($row["is_active"] ?? false)) {
        return null;
    }

    if (!password_verify($password, (string)$row["password_hash"])) {
        return null;
    }

    if (mxli_user_is_expired($row)) {
        $row["__expired"] = true;
        return $row;
    }

    // last_login_at + session_token se actualizan en mxli_start_user_session()
    return $row;
}

function mxli_logout(): void
{
    $userId = (int)($_SESSION["user"]["id"] ?? 0);
    $token = (string)($_SESSION["session_token"] ?? "");

    // Solo limpia el token si esta sesión sigue siendo la vigente
    if ($userId > 0 && $token !== "") {
        try {
            mxli_ensure_session_token_column();
            $stmt = mxli_db()->prepare(
                "UPDATE app_users
                 SET session_token = NULL,
                     session_last_activity = NULL,
                     session_ip = NULL,
                     updated_at = NOW()
                 WHERE id = :id AND session_token = :token"
            );
            $stmt->execute([
                "id" => $userId,
                "token" => $token,
            ]);
        } catch (Throwable $e) {
            // Continuar con destrucción de sesión PHP
        }
    }

    mxli_destroy_php_session();
}

function mxli_json_response(array $payload, int $status = 200): void
{
    http_response_code($status);
    header("Content-Type: application/json; charset=UTF-8");
    header("Cache-Control: no-store, no-cache, must-revalidate");
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}
