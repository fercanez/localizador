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

if (session_status() === PHP_SESSION_ACTIVE) {
    session_write_close();
}

$raw = file_get_contents("php://input") ?: "";
$body = json_decode($raw, true);
if (!is_array($body)) {
    $body = $_POST;
}

$claves = $body["claves"] ?? $body["claves_catastrales"] ?? [];
if (!is_array($claves)) {
    mxli_json_response(["ok" => false, "error" => "Indique una lista de claves catastrales."], 400);
}

try {
    $resultados = mxli_predial_consultar_varias($claves, 80);
    mxli_json_response([
        "ok" => true,
        "resultados" => $resultados,
    ]);
} catch (Throwable $e) {
    $payload = [
        "ok" => false,
        "error" => "No se pudo consultar el portal de predial.",
    ];
    if (function_exists("mxli_is_admin") && mxli_is_admin()) {
        $payload["detail"] = $e->getMessage();
    }
    mxli_json_response($payload, 502);
}
