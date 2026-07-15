<?php
declare(strict_types=1);

header("Content-Type: application/json; charset=UTF-8");
header("Cache-Control: no-store, no-cache, must-revalidate");

require_once dirname(__DIR__) . "/includes/init.php";
require_once dirname(__DIR__) . "/includes/auth.php";

if (!mxli_is_logged_in()) {
    http_response_code(401);
    echo json_encode(["error" => "Debe iniciar sesión."]);
    exit;
}

if (!mxli_can("map.search") || !mxli_can_layer("predios_mexicali_2025")) {
    http_response_code(403);
    echo json_encode(["error" => "Sin permiso para consultar predios."]);
    exit;
}

$clave = strtoupper(preg_replace('/\s+/', '', (string)($_GET['clave'] ?? '')));

if ($clave === '') {
    http_response_code(400);
    echo json_encode(["error" => "Falta la clave catastral."]);
    exit;
}

if (!preg_match('/^[A-Z0-9]+$/', $clave)) {
    http_response_code(400);
    echo json_encode(["error" => "Clave catastral invalida."]);
    exit;
}

$geoserverUrl = rtrim(mxli_config("geoserver_url", "https://www.geomexicali.info/geoserver/"), "/") . "/geonode/ows?" . http_build_query([
    "service" => "WFS",
    "version" => "1.0.0",
    "request" => "GetFeature",
    "typeName" => "geonode:predios_mexicali_2025",
    "outputFormat" => "application/json",
    "srsName" => "EPSG:4326",
    "CQL_FILTER" => "clavecatas='" . str_replace("'", "''", $clave) . "'",
    "maxFeatures" => "1",
]);

$context = stream_context_create([
    "http" => [
        "method" => "GET",
        "timeout" => 20,
        "ignore_errors" => true,
        "header" => "Accept: application/json\r\n",
    ],
    "ssl" => [
        "verify_peer" => true,
        "verify_peer_name" => true,
    ],
]);

$response = @file_get_contents($geoserverUrl, false, $context);

if ($response === false) {
    http_response_code(502);
    echo json_encode(["error" => "No se pudo consultar GeoServer."]);
    exit;
}

$statusLine = $http_response_header[0] ?? "";
if (!str_contains($statusLine, " 200 ")) {
    http_response_code(502);
    echo json_encode(["error" => "GeoServer respondio con error.", "detail" => trim($response)]);
    exit;
}

if (str_starts_with(ltrim($response), "<")) {
    http_response_code(502);
    echo json_encode(["error" => "GeoServer devolvio XML en lugar de JSON."]);
    exit;
}

echo $response;
