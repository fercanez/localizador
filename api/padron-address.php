<?php
declare(strict_types=1);

/**
 * Búsqueda por dirección (calle / número oficial) desde padron2026.
 * Requiere map.search. Nunca expone propietario salvo map.padron.
 */

header("Content-Type: application/json; charset=UTF-8");
header("Cache-Control: no-store, no-cache, must-revalidate");

require_once dirname(__DIR__) . "/includes/init.php";
require_once dirname(__DIR__) . "/includes/auth.php";
require_once dirname(__DIR__) . "/includes/padron.php";

if (!mxli_is_logged_in()) {
    mxli_json_response(["ok" => false, "error" => "Debe iniciar sesión."], 401);
}

if (!mxli_can("map.search")) {
    mxli_json_response(["ok" => false, "error" => "Sin permiso para buscar predios."], 403);
}

$direccion = trim((string)($_GET["direccion"] ?? $_GET["q"] ?? ""));
if ($direccion === "") {
    mxli_json_response(["ok" => false, "error" => "Indique una dirección (calle y número)."], 400);
}

$includeOwner = mxli_can("map.padron");
$limit = min(80, max(1, (int)($_GET["limit"] ?? 40)));

try {
    $parsed = mxli_padron_parse_direccion($direccion);
    $rows = mxli_padron_search_by_direccion($direccion, $limit, $includeOwner);
    mxli_json_response([
        "ok" => true,
        "query" => $direccion,
        "parsed" => $parsed,
        "include_owner" => $includeOwner,
        "resultados" => $rows,
        "total" => count($rows),
    ]);
} catch (Throwable $e) {
    $payload = [
        "ok" => false,
        "error" => "No se pudo buscar por dirección.",
    ];
    if (function_exists("mxli_is_admin") && mxli_is_admin()) {
        $payload["detail"] = $e->getMessage();
    }
    mxli_json_response($payload, 502);
}
