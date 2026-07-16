<?php
declare(strict_types=1);

header("Content-Type: application/json; charset=UTF-8");
header("Cache-Control: no-store, no-cache, must-revalidate");

require_once dirname(__DIR__) . "/includes/init.php";
require_once dirname(__DIR__) . "/includes/auth.php";

if (!mxli_is_logged_in()) {
    http_response_code(401);
    echo json_encode(["ok" => false, "error" => "Debe iniciar sesión."]);
    exit;
}

if (!mxli_can("map.search") || !mxli_can_layer("predios_mexicali_2025")) {
    http_response_code(403);
    echo json_encode(["ok" => false, "error" => "Sin permiso para consultar predios."]);
    exit;
}

$prefix = strtoupper(preg_replace('/\s+/', '', (string)($_GET["prefix"] ?? "")));

if ($prefix === "") {
    http_response_code(400);
    echo json_encode(["ok" => false, "error" => "Falta el prefijo de búsqueda."]);
    exit;
}

// Homoclave (ST), manzana (ST312) u otro prefijo parcial
if (!preg_match('/^[A-Z0-9]{2,10}$/', $prefix)) {
    http_response_code(400);
    echo json_encode(["ok" => false, "error" => "Prefijo inválido (mínimo 2 caracteres)."]);
    exit;
}

$max = (int)($_GET["limit"] ?? 0);
if ($max < 1) {
    // Prefijos cortos (homoclave) pueden devolver muchos predios
    $max = strlen($prefix) <= 2 ? 250 : (strlen($prefix) <= 4 ? 180 : 120);
}
if ($max > 250) {
    $max = 250;
}

$geoserverUrl = rtrim(mxli_config("geoserver_url", "https://www.geomexicali.info/geoserver/"), "/") . "/geonode/ows?" . http_build_query([
    "service" => "WFS",
    "version" => "1.0.0",
    "request" => "GetFeature",
    "typeName" => "geonode:predios_mexicali_2025",
    "outputFormat" => "application/json",
    // Solo necesitamos atributos
    "propertyName" => "clavecatas",
    "CQL_FILTER" => "clavecatas ILIKE '" . str_replace("'", "''", $prefix) . "%'",
    "maxFeatures" => (string)$max,
]);

$context = stream_context_create([
    "http" => [
        "method" => "GET",
        "timeout" => 25,
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
    echo json_encode(["ok" => false, "error" => "No se pudo consultar GeoServer."]);
    exit;
}

$statusLine = $http_response_header[0] ?? "";
if (!str_contains($statusLine, " 200 ")) {
    http_response_code(502);
    echo json_encode(["ok" => false, "error" => "GeoServer respondió con error.", "detail" => trim($response)]);
    exit;
}

if (str_starts_with(ltrim($response), "<")) {
    http_response_code(502);
    echo json_encode(["ok" => false, "error" => "GeoServer devolvió XML en lugar de JSON."]);
    exit;
}

$json = json_decode($response, true);
if (!is_array($json)) {
    http_response_code(502);
    echo json_encode(["ok" => false, "error" => "Respuesta inválida de GeoServer."]);
    exit;
}

$claves = [];
foreach (($json["features"] ?? []) as $feature) {
    $props = $feature["properties"] ?? null;
    $clave = is_array($props) ? (string)($props["clavecatas"] ?? "") : "";
    if ($clave !== "") {
        $claves[$clave] = true;
    }
}

$list = array_keys($claves);
sort($list, SORT_STRING);

$totalMatched = (int)($json["numberMatched"] ?? $json["totalFeatures"] ?? count($list));
$returned = count($list);
$truncated = $totalMatched > $returned;

echo json_encode([
    "ok" => true,
    "prefix" => $prefix,
    "total" => $returned,
    "total_matched" => $totalMatched,
    "truncated" => $truncated,
    "claves" => $list,
], JSON_UNESCAPED_UNICODE);

