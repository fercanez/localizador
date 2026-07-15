<?php
declare(strict_types=1);

require_once dirname(__DIR__) . "/includes/config.php";
require_once dirname(__DIR__) . "/includes/legend-svg.php";

$layer = preg_replace("/[^a-zA-Z0-9_:-]/", "", (string)($_GET["layer"] ?? ""));
$workspace = preg_replace("/[^a-zA-Z0-9_-]/", "", (string)($_GET["workspace"] ?? "geonode"));
$width = max(120, min(900, (int)($_GET["width"] ?? 300)));
$height = max(150, min(2000, (int)($_GET["height"] ?? 700)));

if ($layer === "" || $workspace === "") {
    http_response_code(400);
    header("Content-Type: application/json; charset=UTF-8");
    echo json_encode(["error" => "Parametros de capa invalidos."]);
    exit;
}

$metadata = require dirname(__DIR__) . "/includes/layer-metadata.php";
$layerMeta = $metadata[$layer] ?? [];
$geoserverBase = rtrim(mxli_config("geoserver_url", "https://www.geomexicali.info/geoserver/"), "/");

$legend = mxli_fetch_geoserver_legend($geoserverBase, $workspace, $layer, $width, $height);

if ($legend === null) {
    $legendImage = (string)($layerMeta["legend_image"] ?? "");
    if ($legendImage !== "") {
        $imagePath = dirname(__DIR__) . "/" . ltrim($legendImage, "/");
        if (is_file($imagePath)) {
            $extension = strtolower(pathinfo($imagePath, PATHINFO_EXTENSION));
            $mime = match ($extension) {
                "svg" => "image/svg+xml",
                "jpg", "jpeg" => "image/jpeg",
                default => "image/png",
            };
            $legend = [
                "body" => (string)file_get_contents($imagePath),
                "content_type" => $mime,
            ];
        }
    }
}

if ($legend === null && !empty($layerMeta["legend_rules"]) && is_array($layerMeta["legend_rules"])) {
    $svg = mxli_build_legend_svg(
        $layerMeta["legend_rules"],
        $layerMeta["legend_note"] ?? null,
        $width
    );
    $legend = [
        "body" => $svg,
        "content_type" => "image/svg+xml",
    ];
}

if ($legend === null && !empty($layerMeta["legend_getmap_bbox"])) {
    $legend = mxli_fetch_geoserver_getmap_preview(
        $geoserverBase,
        $workspace,
        $layer,
        $width,
        $height,
        (string)$layerMeta["legend_getmap_bbox"]
    );
}

if ($legend === null) {
    http_response_code(502);
    header("Content-Type: application/json; charset=UTF-8");
    echo json_encode([
        "error" => "No se pudo generar la simbologia para esta capa.",
        "layer" => $layer,
    ]);
    exit;
}

header("Content-Type: " . $legend["content_type"]);
header("Cache-Control: public, max-age=3600");
echo $legend["body"];
