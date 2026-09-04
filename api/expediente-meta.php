<?php
declare(strict_types=1);

header("Content-Type: application/json; charset=UTF-8");
header("Cache-Control: no-store, no-cache, must-revalidate");

require_once dirname(__DIR__) . "/includes/init.php";
require_once dirname(__DIR__) . "/includes/auth.php";
require_once dirname(__DIR__) . "/includes/expediente.php";

if (!mxli_is_logged_in()) {
    mxli_json_response(["ok" => false, "error" => "Debe iniciar sesión"], 401);
}

$action = (string)($_GET["action"] ?? $_POST["action"] ?? "");
if ($action === "toggle_wm") {
    if (!mxli_can("map.expediente.nowatermark")) {
        mxli_json_response(["ok" => false, "error" => "Sin permiso para quitar marca de agua"], 403);
    }
    $raw = file_get_contents("php://input") ?: "";
    $body = json_decode($raw, true);
    if (!is_array($body)) {
        $body = $_POST;
    }
    $off = !empty($body["off"]);
    $_SESSION["mxli_expediente_wm_off"] = $off;
    mxli_json_response(["ok" => true, "flags" => mxli_expediente_flags()]);
}

$clave = mxli_normalize_expediente_clave((string)($_GET["clave"] ?? ""));
$flags = mxli_expediente_flags();
if (!$flags["puedeVer"]) {
    mxli_json_response(["ok" => false, "error" => "Sin permiso para ver expedientes"], 403);
}

$user = mxli_current_user();
mxli_json_response([
    "ok" => true,
    "clave" => $clave,
    "external_url" => $clave !== "" ? mxli_expediente_external_url($clave) : "",
    "pdf_url" => $clave !== "" ? ("api/expediente-pdf.php?clave=" . rawurlencode($clave)) : "",
    "flags" => $flags,
    "watermark_text" => "CATASTRO MEXICALI · "
        . strtoupper((string)($user["username"] ?? "USUARIO"))
        . " · "
        . date("d/m/Y H:i"),
]);
