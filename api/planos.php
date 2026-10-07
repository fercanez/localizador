<?php
declare(strict_types=1);

header("Content-Type: application/json; charset=UTF-8");
header("Cache-Control: no-store, no-cache, must-revalidate");

require_once dirname(__DIR__) . "/includes/init.php";
require_once dirname(__DIR__) . "/includes/auth.php";
require_once dirname(__DIR__) . "/includes/planos.php";

if (!mxli_is_logged_in()) {
    mxli_json_response(["ok" => false, "error" => "Debe iniciar sesión."], 401);
}

if (!mxli_can("map.planos")) {
    mxli_json_response(["ok" => false, "error" => "Su rol no tiene permiso para consultar planos."], 403);
}

mxli_planos_grant_roles();

$q = mxli_planos_normalize_homoclave((string)($_GET["q"] ?? $_GET["colonia"] ?? ""));
if ($q === "") {
    mxli_json_response(["ok" => false, "error" => "Indique la homoclave (2 a 4 caracteres)."], 400);
}
if (!mxli_planos_valid_homoclave($q)) {
    mxli_json_response(["ok" => false, "error" => "Homoclave inválida. Use 2 a 4 caracteres (ej. ST, SP)."], 400);
}

[$files, $source] = mxli_planos_search($q);
$colonia = mxli_planos_colonia_hint($q);

$items = [];
foreach ($files as $file) {
    $items[] = [
        "name" => $file["name"],
        "rel" => $file["rel"],
        "ext" => $file["ext"],
        "size" => $file["size"],
        "url" => "api/planos-file.php?f=" . rawurlencode($file["rel"]),
        "external_url" => mxli_planos_external_url($file["rel"]),
    ];
}

mxli_json_response([
    "ok" => true,
    "q" => $q,
    "colonia" => $colonia,
    "count" => count($items),
    "source" => $source,
    "flags" => mxli_planos_flags(),
    "items" => $items,
]);
