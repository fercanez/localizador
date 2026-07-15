<?php
declare(strict_types=1);

/**
 * Genera una leyenda SVG a partir de reglas locales.
 *
 * @param array<int, array{color?:string, fill?:string, stroke?:string, label:string}> $rules
 */
function mxli_build_legend_svg(array $rules, ?string $note = null, int $width = 300): string
{
    $width = max(180, min(900, $width));
    $rowHeight = 22;
    $padding = 12;
    $swatchWidth = 28;
    $startY = $padding + 8;
    $height = $padding * 2 + count($rules) * $rowHeight + ($note ? 42 : 0);

    $svg = '<?xml version="1.0" encoding="UTF-8"?>';
    $svg .= '<svg xmlns="http://www.w3.org/2000/svg" width="' . $width . '" height="' . $height . '" viewBox="0 0 ' . $width . ' ' . $height . '">';
    $svg .= '<rect width="100%" height="100%" fill="#e9e9e9"/>';

    $y = $startY;
    foreach ($rules as $rule) {
        $fill = htmlspecialchars((string)($rule["fill"] ?? $rule["color"] ?? "#cccccc"), ENT_QUOTES, "UTF-8");
        $stroke = htmlspecialchars((string)($rule["stroke"] ?? "#666666"), ENT_QUOTES, "UTF-8");
        $label = htmlspecialchars((string)($rule["label"] ?? ""), ENT_QUOTES, "UTF-8");

        $svg .= '<rect x="' . $padding . '" y="' . ($y - 12) . '" width="' . $swatchWidth . '" height="14" fill="' . $fill . '" stroke="' . $stroke . '" stroke-width="1"/>';
        $svg .= '<text x="' . ($padding + $swatchWidth + 8) . '" y="' . $y . '" font-family="Arial, sans-serif" font-size="11" fill="#333">' . $label . '</text>';
        $y += $rowHeight;
    }

    if ($note) {
        $noteY = $y + 8;
        $noteText = htmlspecialchars($note, ENT_QUOTES, "UTF-8");
        $svg .= '<text x="' . $padding . '" y="' . $noteY . '" font-family="Arial, sans-serif" font-size="10" fill="#666">' . $noteText . '</text>';
    }

    $svg .= '</svg>';

    return $svg;
}

/**
 * @return array{body:string,status:int,content_type:string}
 */
function mxli_fetch_remote(string $url, int $timeout = 45): array
{
    if (function_exists("curl_init")) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_CONNECTTIMEOUT => 15,
            CURLOPT_HTTPHEADER => ["Accept: image/png, image/svg+xml, */*"],
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ]);
        $body = curl_exec($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $contentType = (string)curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
        curl_close($ch);

        return [
            "body" => is_string($body) ? $body : "",
            "status" => $status,
            "content_type" => $contentType,
        ];
    }

    $context = stream_context_create([
        "http" => [
            "method" => "GET",
            "timeout" => $timeout,
            "ignore_errors" => true,
            "header" => "Accept: image/png, image/svg+xml, */*\r\n",
        ],
        "ssl" => [
            "verify_peer" => true,
            "verify_peer_name" => true,
        ],
    ]);

    $body = @file_get_contents($url, false, $context);
    $statusLine = $http_response_header[0] ?? "";
    preg_match('/\s(\d{3})\s/', $statusLine, $matches);

    return [
        "body" => is_string($body) ? $body : "",
        "status" => (int)($matches[1] ?? 0),
        "content_type" => "",
    ];
}

function mxli_is_png(string $body): bool
{
    return str_starts_with($body, "\x89PNG") && strlen($body) > 80;
}

function mxli_is_svg(string $body): bool
{
    $trimmed = ltrim($body);
    return str_contains($trimmed, "<svg") && str_contains($trimmed, "</svg>");
}

function mxli_is_error_payload(string $body): bool
{
    $trimmed = ltrim($body);
    return $trimmed === "" || str_starts_with($trimmed, "<") || str_starts_with($trimmed, "{");
}

/**
 * @return array{body:string, content_type:string}|null
 */
function mxli_fetch_geoserver_legend(string $geoserverBase, string $workspace, string $layer, int $width, int $height): ?array
{
    $variants = [
        [
            "SERVICE" => "WMS",
            "REQUEST" => "GetLegendGraphic",
            "VERSION" => "1.0.0",
            "FORMAT" => "image/png",
            "WIDTH" => $width,
            "HEIGHT" => $height,
            "LEGEND_OPTIONS" => "bgColor:0xe9e9e9;forceLabels:on;wrap:true;wrap_limit:" . $width . ";fontSize:11",
            "LAYER" => $workspace . ":" . $layer,
        ],
        [
            "REQUEST" => "GetLegendGraphic",
            "VERSION" => "1.0.0",
            "FORMAT" => "image/png",
            "WIDTH" => min(220, $width),
            "HEIGHT" => min(500, $height),
            "LAYER" => $workspace . ":" . $layer,
        ],
    ];

    foreach ($variants as $query) {
        $url = $geoserverBase . "/" . rawurlencode($workspace) . "/wms?" . http_build_query($query);
        $result = mxli_fetch_remote($url);

        if ($result["status"] === 200 && mxli_is_png($result["body"])) {
            return ["body" => $result["body"], "content_type" => "image/png"];
        }
    }

    return null;
}

/**
 * @return array{body:string, content_type:string}|null
 */
function mxli_fetch_geoserver_getmap_preview(
    string $geoserverBase,
    string $workspace,
    string $layer,
    int $width,
    int $height,
    string $bbox
): ?array {
    $query = [
        "SERVICE" => "WMS",
        "REQUEST" => "GetMap",
        "VERSION" => "1.1.1",
        "LAYERS" => $workspace . ":" . $layer,
        "STYLES" => "",
        "FORMAT" => "image/png",
        "TRANSPARENT" => "true",
        "WIDTH" => $width,
        "HEIGHT" => $height,
        "SRS" => "EPSG:4326",
        "BBOX" => $bbox,
    ];

    $url = $geoserverBase . "/" . rawurlencode($workspace) . "/wms?" . http_build_query($query);
    $result = mxli_fetch_remote($url);

    if ($result["status"] === 200 && mxli_is_png($result["body"])) {
        return ["body" => $result["body"], "content_type" => "image/png"];
    }

    return null;
}
