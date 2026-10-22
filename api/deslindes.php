<?php
declare(strict_types=1);

header("Content-Type: application/json; charset=UTF-8");
header("Cache-Control: no-store, no-cache, must-revalidate");

require_once dirname(__DIR__) . "/includes/init.php";
require_once dirname(__DIR__) . "/includes/auth.php";
require_once dirname(__DIR__) . "/includes/deslindes.php";

if (!mxli_is_logged_in()) {
    mxli_json_response(["ok" => false, "error" => "Debe iniciar sesión."], 401);
}

if (!mxli_can("map.planos")) {
    mxli_json_response(["ok" => false, "error" => "Su rol no tiene permiso para consultar deslindes."], 403);
}

if (session_status() === PHP_SESSION_ACTIVE) {
    session_write_close();
}

@set_time_limit(90);

$folio = mxli_deslindes_normalize_folio((string)($_GET["folio"] ?? $_GET["tramite"] ?? $_GET["q"] ?? ""));
if ($folio === "") {
    mxli_json_response(["ok" => false, "error" => "Indique el No. de Trámite."], 400);
}
if (!mxli_deslindes_valid_folio($folio)) {
    mxli_json_response(["ok" => false, "error" => "El No. de Trámite debe ser numérico (folio). Ejemplo: 1881356"], 400);
}

try {
    $data = mxli_deslindes_consultar($folio);
    mxli_json_response($data);
} catch (InvalidArgumentException $e) {
    mxli_json_response(["ok" => false, "error" => $e->getMessage()], 400);
} catch (Throwable $e) {
    $payload = [
        "ok" => false,
        "error" => $e->getMessage() !== ""
            ? $e->getMessage()
            : "No se pudo consultar el portal de deslindes.",
    ];
    if (mxli_can("users.manage")) {
        $payload["detail"] = $e->getMessage();
    }
    mxli_json_response($payload, 502);
}
