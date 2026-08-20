<?php
declare(strict_types=1);

require_once dirname(__DIR__) . "/includes/init.php";
require_once dirname(__DIR__) . "/includes/auth.php";
require_once dirname(__DIR__) . "/includes/inspections.php";

$id = (int)($_GET["id"] ?? 0);
if ($id <= 0) {
    http_response_code(400);
    exit("ID inválido");
}

if (!mxli_is_logged_in()) {
    http_response_code(401);
    exit("No autorizado");
}

try {
    $pdo = mxli_db();
    mxli_ensure_inspections_table($pdo);

    $stmt = $pdo->prepare("SELECT * FROM app_inspection_photos WHERE id = :id");
    $stmt->execute(["id" => $id]);
    $row = $stmt->fetch();
    if (!$row) {
        http_response_code(404);
        exit("No encontrado");
    }

    if (!mxli_can_access_inspection_row($row)) {
        http_response_code(403);
        exit("Acceso denegado");
    }

    $path = mxli_inspection_photo_abs_path((string)$row["photo_path"]);
    if (!is_file($path)) {
        http_response_code(404);
        exit("Archivo no encontrado");
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($path) ?: "image/jpeg";
    $ext = match ($mime) {
        "image/png" => "png",
        "image/webp" => "webp",
        default => "jpg",
    };
    $clave = preg_replace('/[^A-Z0-9_-]/i', "", (string)($row["clavecatas"] ?? "inspeccion")) ?: "inspeccion";
    $filename = "inspeccion-" . $clave . "-" . $id . "." . $ext;

    header("Content-Type: " . $mime);
    header("Cache-Control: private, max-age=3600");
    if (!empty($_GET["download"])) {
        header('Content-Disposition: attachment; filename="' . $filename . '"');
    } else {
        header('Content-Disposition: inline; filename="' . $filename . '"');
    }
    readfile($path);
} catch (Throwable $e) {
    http_response_code(500);
    exit("Error");
}
