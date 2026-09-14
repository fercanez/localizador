<?php
declare(strict_types=1);

header("Content-Type: application/json; charset=UTF-8");
header("Cache-Control: no-store, no-cache, must-revalidate");

require_once dirname(__DIR__) . "/includes/init.php";
require_once dirname(__DIR__) . "/includes/auth.php";

if (!mxli_is_logged_in()) {
    mxli_json_response(["ok" => false, "error" => "Debe iniciar sesión."], 401);
}

if (
    !mxli_can("map.search")
    && !mxli_can_layer("predios_adeudo")
    && !mxli_can_layer("predios_mexicali_2025")
) {
    mxli_json_response(["ok" => false, "error" => "Sin permiso para consultar predios."], 403);
}

$raw = (string)($_GET["bbox"] ?? "");
$parts = array_map("trim", explode(",", $raw));
if (count($parts) !== 4) {
    mxli_json_response(["ok" => false, "error" => "Indique bbox=minLng,minLat,maxLng,maxLat."], 400);
}

$minLng = (float)$parts[0];
$minLat = (float)$parts[1];
$maxLng = (float)$parts[2];
$maxLat = (float)$parts[3];

if (
    is_nan($minLng) || is_nan($minLat) || is_nan($maxLng) || is_nan($maxLat)
    || is_infinite($minLng) || is_infinite($minLat) || is_infinite($maxLng) || is_infinite($maxLat)
    || $minLng < -180 || $maxLng > 180 || $minLat < -90 || $maxLat > 90
    || $minLng >= $maxLng || $minLat >= $maxLat
) {
    mxli_json_response(["ok" => false, "error" => "Bbox inválido."], 400);
}

$max = (int)($_GET["limit"] ?? 80);
if ($max < 1) {
    $max = 80;
}
if ($max > 400) {
    $max = 400;
}

$poly = sprintf(
    "POLYGON((%.8F %.8F, %.8F %.8F, %.8F %.8F, %.8F %.8F, %.8F %.8F))",
    $minLng,
    $minLat,
    $maxLng,
    $minLat,
    $maxLng,
    $maxLat,
    $minLng,
    $maxLat,
    $minLng,
    $minLat
);
$cql = "INTERSECTS(geometry, SRID=4326;" . $poly . ")";

$geoserverUrl = rtrim(mxli_config("geoserver_url", "https://www.geomexicali.info/geoserver/"), "/") . "/geonode/ows?" . http_build_query([
    "service" => "WFS",
    "version" => "1.0.0",
    "request" => "GetFeature",
    "typeName" => "geonode:predios_mexicali_2025",
    "outputFormat" => "application/json",
    "srsName" => "EPSG:4326",
    "CQL_FILTER" => $cql,
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
    mxli_json_response(["ok" => false, "error" => "No se pudo consultar GeoServer."], 502);
}

$statusLine = $http_response_header[0] ?? "";
if (!str_contains($statusLine, " 200 ")) {
    mxli_json_response(["ok" => false, "error" => "GeoServer respondió con error."], 502);
}

if (str_starts_with(ltrim($response), "<")) {
    $detail = "GeoServer devolvió XML en lugar de JSON.";
    if (preg_match("/<ServiceException[^>]*>(.*?)<\\/ServiceException>/s", $response, $m)) {
        $msg = trim(html_entity_decode(strip_tags($m[1])));
        if ($msg !== "") {
            $detail = $msg;
        }
    }
    mxli_json_response(["ok" => false, "error" => $detail], 502);
}

$json = json_decode($response, true);
if (!is_array($json)) {
    mxli_json_response(["ok" => false, "error" => "Respuesta inválida de GeoServer."], 502);
}

$viewW = max(0.00001, $maxLng - $minLng);
$viewH = max(0.00001, $maxLat - $minLat);

$features = [];
foreach (($json["features"] ?? []) as $feature) {
    if (!is_array($feature)) {
        continue;
    }
    $props = $feature["properties"] ?? [];
    $clave = strtoupper(preg_replace("/\s+/", "", (string)($props["clavecatas"] ?? "")) ?? "");
    if ($clave === "" || empty($feature["geometry"])) {
        continue;
    }
    if (!preg_match("/^[A-Z]{2}[A-Z0-9]{4,}$/", $clave)) {
        continue;
    }
    $gb = mxli_wfs_geometry_bbox($feature["geometry"]);
    if ($gb !== null) {
        $gw = $gb["maxLng"] - $gb["minLng"];
        $gh = $gb["maxLat"] - $gb["minLat"];
        // Polígonos enormes (colonias / basura) que solo tocan la vista.
        if ($gw > $viewW * 8 || $gh > $viewH * 8) {
            continue;
        }
    }
    $features[] = [
        "type" => "Feature",
        "properties" => [
            "clavecatas" => $clave,
        ],
        "geometry" => $feature["geometry"],
    ];
}

$totalMatched = (int)($json["numberMatched"] ?? $json["totalFeatures"] ?? count($features));
$returned = count($features);

mxli_json_response([
    "ok" => true,
    "type" => "FeatureCollection",
    "features" => $features,
    "total" => $returned,
    "total_matched" => $totalMatched,
    "truncated" => $totalMatched > $returned || $returned >= $max,
]);

/**
 * @param array<string, mixed> $geometry
 * @return array{minLng:float,minLat:float,maxLng:float,maxLat:float}|null
 */
function mxli_wfs_geometry_bbox(array $geometry): ?array
{
    $coords = $geometry["coordinates"] ?? null;
    if (!is_array($coords)) {
        return null;
    }
    $flat = [];
    mxli_wfs_collect_points($coords, $flat);
    if ($flat === []) {
        return null;
    }
    $minLng = $maxLng = $flat[0][0];
    $minLat = $maxLat = $flat[0][1];
    foreach ($flat as $pt) {
        $minLng = min($minLng, $pt[0]);
        $maxLng = max($maxLng, $pt[0]);
        $minLat = min($minLat, $pt[1]);
        $maxLat = max($maxLat, $pt[1]);
    }
    return [
        "minLng" => $minLng,
        "minLat" => $minLat,
        "maxLng" => $maxLng,
        "maxLat" => $maxLat,
    ];
}

/**
 * @param mixed $node
 * @param list<array{0:float,1:float}> $out
 */
function mxli_wfs_collect_points(mixed $node, array &$out): void
{
    if (!is_array($node) || $node === []) {
        return;
    }
    if (isset($node[0], $node[1]) && is_numeric($node[0]) && is_numeric($node[1]) && !is_array($node[0])) {
        $out[] = [(float)$node[0], (float)$node[1]];
        return;
    }
    foreach ($node as $child) {
        mxli_wfs_collect_points($child, $out);
        if (count($out) > 400) {
            return;
        }
    }
}
