<?php
declare(strict_types=1);

header("Content-Type: application/json; charset=UTF-8");
header("Cache-Control: no-store, no-cache, must-revalidate");

$layer = preg_replace("/[^a-zA-Z0-9_:-]/", "", (string)($_GET["layer"] ?? ""));

if ($layer === "") {
    http_response_code(400);
    echo json_encode(["error" => "Parametro layer requerido."]);
    exit;
}

$metadata = require dirname(__DIR__) . "/includes/layer-metadata.php";
$layerMeta = $metadata[$layer] ?? [];

echo json_encode([
    "layer" => $layer,
    "title" => $layerMeta["title"] ?? $layer,
    "rules" => $layerMeta["legend_rules"] ?? [],
    "note" => $layerMeta["legend_note"] ?? "",
]);
