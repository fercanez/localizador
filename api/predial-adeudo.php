<?php
declare(strict_types=1);

header("Content-Type: application/json; charset=UTF-8");
header("Cache-Control: no-store, no-cache, must-revalidate");

require_once dirname(__DIR__) . "/includes/init.php";
require_once dirname(__DIR__) . "/includes/auth.php";
require_once dirname(__DIR__) . "/includes/predial.php";

if (!mxli_is_logged_in()) {
    mxli_json_response(["ok" => false, "error" => "Debe iniciar sesión."], 401);
}

if (!mxli_can("map.search") && !mxli_can("map.padron") && !mxli_can_layer("predios_adeudo")) {
    mxli_json_response(["ok" => false, "error" => "Sin permiso para consultar adeudos prediales."], 403);
}

// Liberar el lock de sesión para permitir consultas paralelas desde el buscador.
if (session_status() === PHP_SESSION_ACTIVE) {
    session_write_close();
}

@set_time_limit(90);

$clave = mxli_predial_normalize_clave((string)($_GET["clave"] ?? $_GET["clave_catastral"] ?? ""));
if (strlen($clave) < 6) {
    mxli_json_response(["ok" => false, "error" => "Indique una clave catastral."], 400);
}

try {
    $adeudo = mxli_predial_consultar($clave);
    mxli_json_response([
        "ok" => true,
        "adeudo" => $adeudo,
    ]);
} catch (Throwable $e) {
    $payload = [
        "ok" => false,
        "error" => "No se pudo consultar el portal de predial.",
        "adeudo" => [
            "clave_catastral" => $clave,
            "tiene_adeudo" => false,
            "estatus_consulta" => "error",
            "fuente" => "portal_mexicali",
            "mensaje" => $e->getMessage(),
        ],
    ];
    if (function_exists("mxli_is_admin") && mxli_is_admin()) {
        $payload["detail"] = $e->getMessage();
    }
    mxli_json_response($payload, 502);
}
