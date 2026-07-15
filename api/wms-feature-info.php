<?php
declare(strict_types=1);

header("Content-Type: application/json; charset=UTF-8");
header("Cache-Control: no-store, no-cache, must-revalidate");

require_once dirname(__DIR__) . "/includes/config.php";

$layers = preg_replace("/[^a-zA-Z0-9_:,-]/", "", (string)($_GET["layers"] ?? ""));
$version = (string)($_GET["version"] ?? "1.1.1");
$bbox = (string)($_GET["bbox"] ?? "");
$width = (int)($_GET["width"] ?? 0);
$height = (int)($_GET["height"] ?? 0);
$x = (int)($_GET["x"] ?? 0);
$y = (int)($_GET["y"] ?? 0);

if ($layers === "" || $bbox === "" || $width <= 0 || $height <= 0) {
    http_response_code(400);
    echo json_encode(["error" => "Parametros insuficientes para consultar el mapa."]);
    exit;
}

if (!in_array($version, ["1.1.0", "1.1.1", "1.3.0"], true)) {
    $version = "1.1.1";
}

$geoserverBase = rtrim(mxli_config("geoserver_url", "https://www.geomexicali.info/geoserver/"), "/");
$gsOws = rtrim(mxli_config("gs_ows_url", "https://www.geomexicali.info/gs/ows"), "/");

/**
 * Endpoints a probar: GeoNode OWS funciona para capas que no aparecen en /geonode/wms.
 */
function mxli_feature_info_endpoints(string $geoserverBase, string $gsOws): array
{
    $endpoints = [
        $gsOws,
        $geoserverBase . "/ows",
        $geoserverBase . "/geonode/wms",
    ];

    return array_values(array_unique($endpoints));
}

function mxli_build_feature_info_query(
    string $layers,
    string $version,
    string $bbox,
    int $width,
    int $height,
    int $x,
    int $y
): array {
    $query = [
        "service" => "WMS",
        "request" => "GetFeatureInfo",
        "srs" => "EPSG:4326",
        "styles" => "",
        "transparent" => "true",
        "format" => "image/png",
        "info_format" => "application/json",
        "layers" => $layers,
        "query_layers" => $layers,
        "version" => $version,
        "bbox" => $bbox,
        "width" => $width,
        "height" => $height,
        "feature_count" => "10",
    ];

    if ($version === "1.3.0") {
        $query["i"] = $x;
        $query["j"] = $y;
    } else {
        $query["x"] = $x;
        $query["y"] = $y;
    }

    return $query;
}

function mxli_fetch_feature_info(string $endpoint, array $query): ?array
{
    $separator = str_contains($endpoint, "?") ? "&" : "?";
    $url = $endpoint . $separator . http_build_query($query);

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

    $response = @file_get_contents($url, false, $context);
    if ($response === false) {
        return null;
    }

    $statusLine = $http_response_header[0] ?? "";
    if (!str_contains($statusLine, " 200 ")) {
        return null;
    }

    if (str_starts_with(ltrim($response), "<")) {
        return null;
    }

    $json = json_decode($response, true);
    if (!is_array($json)) {
        return null;
    }

    return $json;
}

function mxli_feature_count(?array $payload): int
{
    if (!is_array($payload) || !isset($payload["features"]) || !is_array($payload["features"])) {
        return 0;
    }

    return count($payload["features"]);
}

function mxli_query_feature_info(
    array $endpoints,
    string $layers,
    string $version,
    string $bbox,
    int $width,
    int $height,
    int $x,
    int $y
): ?array {
    $query = mxli_build_feature_info_query($layers, $version, $bbox, $width, $height, $x, $y);

    foreach ($endpoints as $endpoint) {
        $result = mxli_fetch_feature_info($endpoint, $query);
        if ($result !== null) {
            return $result;
        }
    }

    return null;
}

$endpoints = mxli_feature_info_endpoints($geoserverBase, $gsOws);
$combined = mxli_query_feature_info($endpoints, $layers, $version, $bbox, $width, $height, $x, $y);

if ($combined !== null && mxli_feature_count($combined) > 0) {
    echo json_encode($combined);
    exit;
}

$layerList = array_values(array_filter(array_map("trim", explode(",", $layers))));
$mergedFeatures = [];

foreach ($layerList as $singleLayer) {
    $singleResult = mxli_query_feature_info(
        $endpoints,
        $singleLayer,
        $version,
        $bbox,
        $width,
        $height,
        $x,
        $y
    );

    if ($singleResult === null || mxli_feature_count($singleResult) === 0) {
        continue;
    }

    foreach ($singleResult["features"] as $feature) {
        $mergedFeatures[] = $feature;
    }
}

if ($mergedFeatures !== []) {
    echo json_encode([
        "type" => "FeatureCollection",
        "features" => $mergedFeatures,
        "totalFeatures" => count($mergedFeatures),
    ]);
    exit;
}

echo json_encode([
    "type" => "FeatureCollection",
    "features" => [],
    "totalFeatures" => 0,
]);
