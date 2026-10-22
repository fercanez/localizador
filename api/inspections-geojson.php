<?php
declare(strict_types=1);

require_once dirname(__DIR__) . "/includes/init.php";
require_once dirname(__DIR__) . "/includes/auth.php";
require_once dirname(__DIR__) . "/includes/inspections.php";

header("Content-Type: application/geo+json; charset=UTF-8");
header("Cache-Control: no-store, no-cache, must-revalidate");

if (!mxli_is_logged_in()) {
    http_response_code(401);
    echo json_encode(["type" => "FeatureCollection", "features" => []]);
    exit;
}

if (!mxli_can_view_inspections_map_layer()) {
    http_response_code(403);
    echo json_encode(["type" => "FeatureCollection", "features" => []]);
    exit;
}

try {
    $pdo = mxli_db();
    mxli_ensure_inspections_table($pdo);
    $user = mxli_current_user();
    if ($user === null) {
        http_response_code(401);
        echo json_encode(["type" => "FeatureCollection", "features" => []]);
        exit;
    }

    $allowedAreas = mxli_inspection_areas_for_user($user);
    if ($allowedAreas === []) {
        http_response_code(403);
        echo json_encode(["type" => "FeatureCollection", "features" => []]);
        exit;
    }

    $params = [];
    $where = "p.lat IS NOT NULL AND p.lng IS NOT NULL";

    $areaParam = strtolower(trim((string)($_GET["area"] ?? "")));
    if ($areaParam !== "") {
        $area = mxli_normalize_inspection_area($areaParam);
        if (!in_array($area, $allowedAreas, true)) {
            http_response_code(403);
            echo json_encode(["type" => "FeatureCollection", "features" => []]);
            exit;
        }
        $where .= " AND p.inspection_area = :area";
        $params["area"] = $area;
        $filterAreas = [$area];
    } else {
        $placeholders = [];
        foreach ($allowedAreas as $i => $a) {
            $key = "area" . $i;
            $placeholders[] = ":" . $key;
            $params[$key] = $a;
        }
        $where .= " AND p.inspection_area IN (" . implode(", ", $placeholders) . ")";
        $filterAreas = $allowedAreas;
    }

    $ownParts = [];
    foreach ($filterAreas as $areaName) {
        $areaSql = $areaName === "catastro" || $areaName === "ecologica" || $areaName === "urbano"
            ? $areaName
            : "urbano";
        if (mxli_can_manage_inspection_area($areaSql, $user)) {
            $ownParts[] = "p.inspection_area = '" . $areaSql . "'";
            continue;
        }
        $param = "uid_" . $areaSql;
        $ownParts[] = "(p.inspection_area = '" . $areaSql . "' AND p.created_by = :" . $param . ")";
        $params[$param] = (int)$user["id"];
    }
    if ($ownParts !== []) {
        $where .= " AND (" . implode(" OR ", $ownParts) . ")";
    }

    $sql =
        "SELECT p.*, u.username AS created_by_name
         FROM app_inspection_photos p
         LEFT JOIN app_users u ON u.id = p.created_by
         WHERE {$where}
         ORDER BY p.captured_at DESC
         LIMIT 500";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    $features = [];
    foreach ($rows as $row) {
        $item = mxli_public_inspection_row($row, $user);
        $lat = (float)$row["lat"];
        $lng = (float)$row["lng"];
        if (!is_finite($lat) || !is_finite($lng)) {
            continue;
        }

        $features[] = [
            "type" => "Feature",
            "geometry" => [
                "type" => "Point",
                "coordinates" => [$lng, $lat],
            ],
            "properties" => array_merge($item, [
                "layer_type" => "inspeccion_foto",
            ]),
        ];
    }

    echo json_encode([
        "type" => "FeatureCollection",
        "features" => $features,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(["type" => "FeatureCollection", "features" => []]);
}