<?php
/**
 * Entrega el motor LibreDWG en Base64 (text/plain) para evitar que Apache/WAF
 * bloqueen el binario .wasm (HTTP 500).
 */
@ini_set("memory_limit", "256M");
@ini_set("zlib.output_compression", "0");
@set_time_limit(120);

$wasm = dirname(__DIR__) . "/js/cad/wasm/libredwg-web.wasm";
$exists = is_file($wasm);
$size = $exists ? (int)filesize($wasm) : 0;

if (isset($_GET["ping"])) {
    header("Content-Type: application/json; charset=UTF-8");
    header("Cache-Control: no-store");
    echo json_encode([
        "ok" => $exists && $size > 1000,
        "size" => $size,
    ]);
    exit;
}

if (!$exists || $size < 1000) {
    http_response_code(404);
    header("Content-Type: text/plain; charset=UTF-8");
    echo "Falta el motor CAD (js/cad/wasm/libredwg-web.wasm).";
    exit;
}

header("Content-Type: text/plain; charset=US-ASCII");
header("Cache-Control: public, max-age=604800");
header("X-Mxli-Cad: b64");

$fh = fopen($wasm, "rb");
if ($fh === false) {
    http_response_code(500);
    header("Content-Type: text/plain; charset=UTF-8");
    echo "No se pudo leer el motor CAD.";
    exit;
}

while (!feof($fh)) {
    $chunk = fread($fh, 5700);
    if ($chunk === false || $chunk === "") {
        break;
    }
    echo base64_encode($chunk);
}
fclose($fh);
