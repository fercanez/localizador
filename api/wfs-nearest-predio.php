<?php
declare(strict_types=1);

header("Content-Type: application/json; charset=UTF-8");
header("Cache-Control: no-store, no-cache, must-revalidate");

require_once dirname(__DIR__) . "/includes/init.php";
require_once dirname(__DIR__) . "/includes/auth.php";
require_once dirname(__DIR__) . "/includes/predio-lookup.php";

if (!mxli_is_logged_in()) {
    http_response_code(401);
    echo json_encode(["ok" => false, "error" => "Debe iniciar sesión"]);
    exit;
}

if (!mxli_can("map.inspect") && !mxli_can("map.search")) {
    http_response_code(403);
    echo json_encode(["ok" => false, "error" => "Sin permiso"]);
    exit;
}

if (!mxli_can_layer("predios_mexicali_2025")) {
    http_response_code(403);
    echo json_encode(["ok" => false, "error" => "Sin acceso a capa de predios"]);
    exit;
}

$clave = mxli_normalize_inspection_clave($_GET["clave"] ?? "");

try {
    if ($clave !== "") {
        $hit = mxli_predio_by_clave($clave);
        if ($hit === null) {
            echo json_encode(["ok" => false, "error" => "No se encontró el predio de esa clave"]);
            exit;
        }
        echo json_encode(["ok" => true] + $hit, JSON_UNESCAPED_UNICODE);
        exit;
    }

    $lat = (float)($_GET["lat"] ?? 0);
    $lng = (float)($_GET["lng"] ?? 0);
    if ($lat < -90 || $lat > 90 || $lng < -180 || $lng > 180 || ($lat === 0.0 && $lng === 0.0)) {
        http_response_code(400);
        echo json_encode(["ok" => false, "error" => "Coordenadas inválidas"]);
        exit;
    }

    $hit = mxli_predio_nearest($lat, $lng);
    if ($hit === null) {
        echo json_encode(["ok" => false, "error" => "No se encontró predio cercano"]);
        exit;
    }

    echo json_encode(["ok" => true] + $hit, JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(["ok" => false, "error" => "Error al buscar predio"]);
}
