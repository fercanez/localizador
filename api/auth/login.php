<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . "/includes/init.php";
require_once dirname(__DIR__, 2) . "/includes/auth.php";

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    mxli_json_response(["ok" => false, "error" => "Método no permitido"], 405);
}

$raw = file_get_contents("php://input") ?: "";
$data = json_decode($raw, true);
if (!is_array($data)) {
    $data = $_POST;
}

$username = trim((string)($data["username"] ?? $data["correo"] ?? ""));
$password = (string)($data["password"] ?? $data["contrasena"] ?? "");

try {
    $row = mxli_authenticate($username, $password);
} catch (Throwable $e) {
    mxli_json_response([
        "ok" => false,
        "error" => "Error de conexión a la base de datos. Verifique la configuración.",
    ], 500);
}

if ($row === null) {
    mxli_json_response(["ok" => false, "error" => "Usuario o contraseña incorrectos"], 401);
}

if (!empty($row["__expired"])) {
    $exp = mxli_normalize_expires_at($row["expires_at"] ?? null);
    mxli_json_response([
        "ok" => false,
        "error" => $exp
            ? "Su acceso venció el {$exp}. Contacte al administrador."
            : "Su acceso ha vencido. Contacte al administrador.",
        "expired" => true,
        "expires_at" => $exp,
    ], 403);
}

mxli_set_session_user($row);

mxli_json_response([
    "ok" => true,
    "user" => mxli_public_user($row),
]);
