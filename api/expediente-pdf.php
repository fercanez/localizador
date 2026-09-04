<?php
declare(strict_types=1);

require_once dirname(__DIR__) . "/includes/init.php";
require_once dirname(__DIR__) . "/includes/auth.php";
require_once dirname(__DIR__) . "/includes/expediente.php";

header("Cache-Control: no-store, no-cache, must-revalidate");

if (!mxli_is_logged_in()) {
    http_response_code(401);
    header("Content-Type: text/plain; charset=UTF-8");
    echo "Debe iniciar sesión";
    exit;
}

if (!mxli_can("map.expediente")) {
    http_response_code(403);
    header("Content-Type: text/plain; charset=UTF-8");
    echo "Sin permiso para ver expedientes";
    exit;
}

$clave = mxli_normalize_expediente_clave((string)($_GET["clave"] ?? ""));
if ($clave === "") {
    http_response_code(400);
    header("Content-Type: text/plain; charset=UTF-8");
    echo "Clave inválida";
    exit;
}

$pdf = mxli_expediente_fetch_pdf($clave);
if ($pdf === null) {
    http_response_code(404);
    header("Content-Type: text/plain; charset=UTF-8");
    echo "No se encontró documentación para la clave " . $clave;
    exit;
}

$download = isset($_GET["download"]) && (string)$_GET["download"] === "1";
if ($download && !mxli_can("map.expediente.download")) {
    http_response_code(403);
    header("Content-Type: text/plain; charset=UTF-8");
    echo "Sin permiso para descargar";
    exit;
}

header("Content-Type: application/pdf");
header("Content-Length: " . (string)strlen($pdf));
header(
    ($download ? "Content-Disposition: attachment" : "Content-Disposition: inline")
    . '; filename="expediente-' . $clave . '.pdf"'
);
echo $pdf;
