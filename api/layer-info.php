<?php
declare(strict_types=1);

header("Content-Type: application/json; charset=UTF-8");
header("Cache-Control: no-store, no-cache, must-revalidate");

$layer = preg_replace("/[^a-zA-Z0-9_-]/", "", (string)($_GET["layer"] ?? ""));
$fallbackTitle = trim((string)($_GET["title"] ?? ""));

if ($layer === "") {
    http_response_code(400);
    echo json_encode(["error" => "Parametro layer requerido."]);
    exit;
}

$metadata = require dirname(__DIR__) . "/includes/layer-metadata.php";

if (isset($metadata[$layer]) && is_array($metadata[$layer])) {
    echo json_encode([
        "title" => $metadata[$layer]["title"] ?? $fallbackTitle ?: $layer,
        "abstract" => $metadata[$layer]["abstract"] ?? "",
    ]);
    exit;
}

echo json_encode([
    "title" => $fallbackTitle !== "" ? $fallbackTitle : $layer,
    "abstract" => "",
]);
