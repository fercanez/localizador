<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . "/includes/init.php";
require_once dirname(__DIR__, 2) . "/includes/auth.php";

$user = mxli_current_user();
if ($user === null) {
    $payload = ["ok" => false, "authenticated" => false];
    $fail = mxli_auth_fail_reason();
    if ($fail === "session_replaced") {
        $payload["error"] = "Su sesión fue cerrada porque se inició en otro dispositivo.";
        $payload["session_replaced"] = true;
        $payload["reason"] = "session_replaced";
    } elseif ($fail === "session_idle") {
        $payload["error"] = "Su sesión se cerró por inactividad.";
        $payload["session_idle"] = true;
        $payload["reason"] = "session_idle";
    } elseif ($fail === "session_ip") {
        $payload["error"] = "Su sesión se cerró porque cambió la conexión (IP).";
        $payload["session_ip"] = true;
        $payload["reason"] = "session_ip";
    }
    mxli_json_response($payload, 401);
}

mxli_json_response([
    "ok" => true,
    "authenticated" => true,
    "user" => $user,
    "permissions" => mxli_user_permissions($user),
    "allowed_layers" => mxli_allowed_layers($user),
    "roles" => mxli_role_labels(),
]);
