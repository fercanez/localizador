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

if (!mxli_can("map.deslindes")) {
    mxli_json_response(["ok" => false, "error" => "Sin permiso para consultar trámites de deslinde."], 403);
}

$isAdmin = mxli_is_admin();

// La consulta al portal puede tardar; no bloquear otras peticiones de la sesión.
if (session_status() === PHP_SESSION_ACTIVE) {
    session_write_close();
}

@set_time_limit(120);

$tramite = mxli_deslindes_normalize_tramite((string)($_GET["tramite"] ?? $_POST["tramite"] ?? ""));
if ($tramite === "") {
    mxli_json_response(["ok" => false, "error" => "Indique el No. de Trámite."], 400);
}

$refresh = ($_GET["refresh"] ?? "") === "1";

try {
    $data = mxli_deslindes_consultar($tramite, !$refresh);
    mxli_json_response(["ok" => true] + $data);
} catch (MxliDeslindesError $e) {
    $status = [
        "bad_request" => 400,
        "not_configured" => 503,
        "portal_down" => 503,
    ][$e->kind] ?? 502;
    $payload = ["ok" => false, "code" => $e->kind, "error" => $e->getMessage()];
    if ($isAdmin && $e->debugPath !== null) {
        $payload["debug"] = $e->debugPath;
    }
    if ($isAdmin && $e->kind === "form_unknown") {
        try {
            $jar = mxli_deslindes_jar_load();
            $page = mxli_deslindes_open_page($jar);
            $payload["form"] = mxli_deslindes_describe_form($page["body"]);
        } catch (Throwable $ignored) {
        }
    }
    mxli_json_response($payload, $status);
} catch (Throwable $e) {
    $payload = ["ok" => false, "code" => "error", "error" => "No se pudo consultar el portal de deslindes."];
    if ($isAdmin) {
        $payload["detail"] = $e->getMessage();
    }
    mxli_json_response($payload, 502);
}
