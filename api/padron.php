<?php
declare(strict_types=1);

header("Content-Type: application/json; charset=UTF-8");
header("Cache-Control: no-store, no-cache, must-revalidate");

require_once dirname(__DIR__) . "/includes/init.php";
require_once dirname(__DIR__) . "/includes/auth.php";
require_once dirname(__DIR__) . "/includes/padron.php";

if (!mxli_is_logged_in()) {
    mxli_json_response(["ok" => false, "error" => "Debe iniciar sesión."], 401);
}

if (!mxli_can("map.padron")) {
    mxli_json_response(["ok" => false, "error" => "Sin permiso para consultar el padrón."], 403);
}

$clave = mxli_normalize_clave_catastral((string)($_GET["clave"] ?? ""));
$nombre = trim((string)($_GET["nombre"] ?? ""));

try {
    if ($clave !== "") {
        $row = mxli_padron_lookup_by_clave($clave);
        $payload = [
            "ok" => true,
            "found" => $row !== null,
            "padron" => $row,
        ];
        // Admin: mapa de columnas para diagnosticar campos vacíos.
        if (function_exists("mxli_is_admin") && mxli_is_admin()) {
            try {
                $pdo = mxli_geonode_db();
                $payload["debug_map"] = mxli_padron_field_map($pdo);
                $payload["debug_columns"] = mxli_padron_column_names($pdo);
            } catch (Throwable $ignored) {
                // no bloquear la respuesta útil
            }
        }
        mxli_json_response($payload);
    }

    if ($nombre !== "") {
        $rows = mxli_padron_search_by_nombre($nombre);
        mxli_json_response([
            "ok" => true,
            "resultados" => $rows,
        ]);
    }

    mxli_json_response(["ok" => false, "error" => "Indique clave o nombre."], 400);
} catch (Throwable $e) {
    $payload = [
        "ok" => false,
        "error" => "No se pudo consultar padron2026.",
    ];
    // Solo admin: detalle para diagnosticar DSN / permisos / columnas.
    if (function_exists("mxli_is_admin") && mxli_is_admin()) {
        $payload["detail"] = $e->getMessage();
    }
    mxli_json_response($payload, 502);
}
