<?php
declare(strict_types=1);

require_once __DIR__ . "/inspections.php";

function mxli_haversine_m(float $lat1, float $lng1, float $lat2, float $lng2): float
{
    $r = 6371000.0;
    $p1 = deg2rad($lat1);
    $p2 = deg2rad($lat2);
    $dLat = deg2rad($lat2 - $lat1);
    $dLng = deg2rad($lng2 - $lng1);
    $a = sin($dLat / 2) ** 2 + cos($p1) * cos($p2) * sin($dLng / 2) ** 2;
    return $r * 2 * atan2(sqrt($a), sqrt(1 - $a));
}

function mxli_ring_centroid(array $ring): ?array
{
    if (count($ring) < 3) {
        return null;
    }
    $n = count($ring);
    if ($ring[0] === $ring[$n - 1]) {
        $n--;
    }
    if ($n <= 0) {
        return null;
    }
    $sumLng = 0.0;
    $sumLat = 0.0;
    for ($i = 0; $i < $n; $i++) {
        $sumLng += (float)$ring[$i][0];
        $sumLat += (float)$ring[$i][1];
    }
    return ["lng" => $sumLng / $n, "lat" => $sumLat / $n];
}

function mxli_geometry_centroid(?array $geometry): ?array
{
    if ($geometry === null || empty($geometry["type"]) || empty($geometry["coordinates"])) {
        return null;
    }

    $type = (string)$geometry["type"];
    $coords = $geometry["coordinates"];

    if ($type === "Point") {
        return ["lng" => (float)$coords[0], "lat" => (float)$coords[1]];
    }

    if ($type === "Polygon") {
        return mxli_ring_centroid($coords[0] ?? []);
    }

    if ($type === "MultiPolygon") {
        $best = null;
        $bestArea = -1.0;
        foreach ($coords as $poly) {
            $ring = $poly[0] ?? [];
            $c = mxli_ring_centroid($ring);
            if ($c === null) {
                continue;
            }
            $area = abs(count($ring));
            if ($area > $bestArea) {
                $bestArea = $area;
                $best = $c;
            }
        }
        return $best;
    }

    return null;
}

function mxli_wfs_fetch_features(string $cqlFilter, int $maxFeatures = 15): array
{
    $geoserverUrl = rtrim(mxli_config("geoserver_url", "https://www.geomexicali.info/geoserver/"), "/")
        . "/geonode/ows?" . http_build_query([
            "service" => "WFS",
            "version" => "1.0.0",
            "request" => "GetFeature",
            "typeName" => "geonode:predios_mexicali_2025",
            "outputFormat" => "application/json",
            "srsName" => "EPSG:4326",
            "CQL_FILTER" => $cqlFilter,
            "maxFeatures" => (string)$maxFeatures,
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
    if ($response === false || str_starts_with(ltrim($response), "<")) {
        return [];
    }

    $data = json_decode($response, true);
    if (!is_array($data) || !is_array($data["features"] ?? null)) {
        return [];
    }

    return $data["features"];
}

function mxli_feature_to_predio(array $feature, ?float $fromLat = null, ?float $fromLng = null): ?array
{
    $props = $feature["properties"] ?? [];
    $clave = mxli_normalize_inspection_clave($props["clavecatas"] ?? "");
    if ($clave === "") {
        return null;
    }

    $centroid = mxli_geometry_centroid($feature["geometry"] ?? null);
    if ($centroid === null) {
        return null;
    }

    $row = [
        "clavecatas" => $clave,
        "lat" => $centroid["lat"],
        "lng" => $centroid["lng"],
        "source" => "clave",
        "distance_m" => null,
    ];

    if ($fromLat !== null && $fromLng !== null) {
        $row["distance_m"] = round(mxli_haversine_m($fromLat, $fromLng, $centroid["lat"], $centroid["lng"]), 1);
    }

    return $row;
}

function mxli_pick_nearest_feature(array $features, float $lat, float $lng): ?array
{
    $best = null;
    $bestDist = INF;

    foreach ($features as $feature) {
        if (!is_array($feature)) {
            continue;
        }
        $row = mxli_feature_to_predio($feature, $lat, $lng);
        if ($row === null) {
            continue;
        }
        $dist = (float)($row["distance_m"] ?? INF);
        if ($dist < $bestDist) {
            $bestDist = $dist;
            $best = $row;
        }
    }

    return $best;
}

function mxli_predio_by_clave(string $clave): ?array
{
    $clave = mxli_normalize_inspection_clave($clave);
    if ($clave === "" || !preg_match('/^[A-Z0-9]{4,32}$/', $clave)) {
        return null;
    }

    $escaped = str_replace("'", "''", $clave);
    $features = mxli_wfs_fetch_features("clavecatas='" . $escaped . "'", 1);
    $feature = $features[0] ?? null;
    if (!is_array($feature)) {
        return null;
    }

    $row = mxli_feature_to_predio($feature);
    if ($row !== null) {
        $row["source"] = "clave";
    }
    return $row;
}

function mxli_predio_nearest(float $lat, float $lng): ?array
{
    $lngEsc = number_format($lng, 8, ".", "");
    $latEsc = number_format($lat, 8, ".", "");

    $inside = mxli_wfs_fetch_features(
        "INTERSECTS(the_geom, POINT({$lngEsc} {$latEsc}))",
        5
    );
    if ($inside !== []) {
        $hit = mxli_pick_nearest_feature($inside, $lat, $lng);
        if ($hit !== null) {
            $hit["source"] = "intersects";
            return $hit;
        }
    }

    $nearby = mxli_wfs_fetch_features(
        "DWITHIN(the_geom, POINT({$lngEsc} {$latEsc}), 150, meters)",
        25
    );
    $hit = mxli_pick_nearest_feature($nearby, $lat, $lng);
    if ($hit !== null) {
        $hit["source"] = "nearest";
    }
    return $hit;
}
