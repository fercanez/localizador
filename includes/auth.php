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

/**
 * @return array{slug:string,name:string,description:string,is_system:bool,permissions:array,allowed_layers:array}|null
 */
function mxli_fetch_role(string $slug): ?array
{
    static $cache = [];
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

    $upd = mxli_db()->prepare(
        "UPDATE app_users SET last_login_at = NOW(), updated_at = NOW() WHERE id = :id"
    );
    $upd->execute(["id" => $row["id"]]);

    return $row;
}

function mxli_logout(): void
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
    session_destroy();
}

function mxli_json_response(array $payload, int $status = 200): void
{
    http_response_code($status);
    header("Content-Type: application/json; charset=UTF-8");
    header("Cache-Control: no-store, no-cache, must-revalidate");
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}
