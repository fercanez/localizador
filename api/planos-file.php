<?php
declare(strict_types=1);

require_once dirname(__DIR__) . "/includes/init.php";
require_once dirname(__DIR__) . "/includes/auth.php";
require_once dirname(__DIR__) . "/includes/planos.php";

@ini_set("memory_limit", "256M");
@set_time_limit(90);

header("Cache-Control: no-store, no-cache, must-revalidate");

if (!mxli_is_logged_in()) {
    http_response_code(401);
    header("Content-Type: text/plain; charset=UTF-8");
    echo "Debe iniciar sesión";
    exit;
}

if (!mxli_can("map.planos")) {
    http_response_code(403);
    header("Content-Type: text/plain; charset=UTF-8");
    echo "Sin permiso para consultar planos";
    exit;
}

$rel = mxli_planos_safe_rel((string)($_GET["f"] ?? ""));
if ($rel === "") {
    http_response_code(400);
    header("Content-Type: text/plain; charset=UTF-8");
    echo "Archivo inválido";
    exit;
}

$download = isset($_GET["download"]) && (string)$_GET["download"] === "1";
if ($download && !mxli_can("map.planos.download")) {
    http_response_code(403);
    header("Content-Type: text/plain; charset=UTF-8");
    echo "Sin permiso para descargar";
    exit;
}

$file = mxli_planos_fetch_bytes($rel);
if ($file === null) {
    http_response_code(404);
    header("Content-Type: text/plain; charset=UTF-8");
    echo "No se encontró el plano";
    exit;
}

$ascii = preg_replace('/[^\x20-\x7E]/', "_", $file["name"]) ?: "plano." . $file["ext"];
header("Content-Type: " . $file["mime"]);
header("Content-Length: " . (string)strlen($file["body"]));
header(
    ($download ? "Content-Disposition: attachment" : "Content-Disposition: inline")
    . '; filename="' . $ascii . '"'
);
echo $file["body"];
