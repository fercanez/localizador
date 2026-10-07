<?php
declare(strict_types=1);

/**
 * Heartbeat de sesión (cada ~25s desde el cliente).
 * Valida token, idle e IP. Si activity=true, renueva el reloj de inactividad.
 */
require_once dirname(__DIR__, 2) . "/includes/init.php";
require_once dirname(__DIR__, 2) . "/includes/auth.php";

if ($_SERVER["REQUEST_METHOD"] !== "GET" && $_SERVER["REQUEST_METHOD"] !== "POST") {
    mxli_json_response(["ok" => false, "error" => "Método no permitido"], 405);
}

$userActivity = false;
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $raw = file_get_contents("php://input") ?: "";
    $data = json_decode($raw, true);
    if (!is_array($data)) {
        $data = $_POST;
    }
    $userActivity = !empty($data["activity"]);
} else {
    $userActivity = isset($_GET["activity"]) && (string)$_GET["activity"] !== "0";
}

$result = mxli_session_heartbeat($userActivity);
if (!$result["ok"]) {
    $reason = (string)($result["reason"] ?? "unauthenticated");
    $payload = [
        "ok" => false,
        "authenticated" => false,
        "reason" => $reason,
    ];
    if ($reason === "session_replaced") {
        $payload["error"] = "Su sesión fue cerrada porque se inició en otro dispositivo.";
        $payload["session_replaced"] = true;
    } elseif ($reason === "session_idle") {
        $payload["error"] = "Su sesión se cerró por inactividad.";
        $payload["session_idle"] = true;
    } elseif ($reason === "session_ip") {
        $payload["error"] = "Su sesión se cerró porque cambió la conexión (IP).";
        $payload["session_ip"] = true;
    } else {
        $payload["error"] = "Sesión no válida.";
    }
    mxli_json_response($payload, 401);
}

$user = mxli_current_user();
mxli_json_response([
    "ok" => true,
    "authenticated" => true,
    "idle_seconds" => $result["idle_seconds"] ?? mxli_session_idle_seconds(),
    "remaining_seconds" => $result["remaining_seconds"] ?? null,
    "user_id" => $user ? (int)$user["id"] : null,
]);
