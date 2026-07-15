<?php
declare(strict_types=1);

require_once __DIR__ . "/db.php";

function mxli_current_user(): ?array
{
    if (empty($_SESSION["user"]) || !is_array($_SESSION["user"])) {
        return null;
    }

    return $_SESSION["user"];
}

function mxli_is_logged_in(): bool
{
    return mxli_current_user() !== null;
}

function mxli_is_admin(): bool
{
    $user = mxli_current_user();
    return $user !== null && ($user["role"] ?? "") === "admin";
}

function mxli_require_login(): void
{
    if (mxli_is_logged_in()) {
        return;
    }

    $next = $_SERVER["REQUEST_URI"] ?? "index.php";
    header("Location: login.php?next=" . rawurlencode($next));
    exit;
}

function mxli_require_admin(): void
{
    mxli_require_login();
    if (!mxli_is_admin()) {
        http_response_code(403);
        echo "Acceso denegado. Se requiere rol administrador.";
        exit;
    }
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

function mxli_public_user(array $row): array
{
    return [
        "id" => (int)$row["id"],
        "username" => (string)$row["username"],
        "full_name" => (string)($row["full_name"] ?? ""),
        "email" => (string)($row["email"] ?? ""),
        "role" => (string)$row["role"],
        "is_active" => mxli_to_bool($row["is_active"] ?? false),
        "created_at" => $row["created_at"] ?? null,
        "updated_at" => $row["updated_at"] ?? null,
        "last_login_at" => $row["last_login_at"] ?? null,
    ];
}

function mxli_set_session_user(array $row): void
{
    $_SESSION["user"] = [
        "id" => (int)$row["id"],
        "username" => (string)$row["username"],
        "full_name" => (string)($row["full_name"] ?? ""),
        "email" => (string)($row["email"] ?? ""),
        "role" => (string)$row["role"],
    ];
    // Compatibilidad con logged_in() legacy
    $_SESSION["email"] = (string)$row["username"];
}

function mxli_authenticate(string $username, string $password): ?array
{
    $username = trim($username);
    if ($username === "" || $password === "") {
        return null;
    }

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
