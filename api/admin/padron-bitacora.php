<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . "/includes/init.php";
require_once dirname(__DIR__, 2) . "/includes/auth.php";
require_once dirname(__DIR__, 2) . "/includes/padron-log.php";

header("Cache-Control: no-store, no-cache, must-revalidate");

if (!mxli_is_logged_in() || !mxli_can("users.manage")) {
    header("Content-Type: application/json; charset=UTF-8");
    mxli_json_response(["ok" => false, "error" => "Acceso denegado"], 403);
}

$method = $_SERVER["REQUEST_METHOD"] ?? "GET";
if ($method !== "GET") {
    header("Content-Type: application/json; charset=UTF-8");
    mxli_json_response(["ok" => false, "error" => "Método no permitido"], 405);
}

$export = strtolower(trim((string)($_GET["export"] ?? "")));
$filters = [
    "usuario" => (string)($_GET["usuario"] ?? $_GET["user"] ?? ""),
    "clave" => (string)($_GET["clave"] ?? ""),
    "tipo" => (string)($_GET["tipo"] ?? ""),
    "desde" => (string)($_GET["desde"] ?? ""),
    "hasta" => (string)($_GET["hasta"] ?? ""),
    "page" => (int)($_GET["page"] ?? 1),
    "limit" => $export === "csv" ? 200 : (int)($_GET["limit"] ?? 50),
];

try {
    if ($export === "csv") {
        $filters["page"] = 1;
        $filters["limit"] = 200;
        $all = [];
        $page = 1;
        $pages = 1;
        while ($page <= $pages && $page <= 50) {
            $filters["page"] = $page;
            $chunk = mxli_padron_log_list($filters);
            $pages = max(1, (int)$chunk["pages"]);
            foreach ($chunk["rows"] as $row) {
                $all[] = $row;
            }
            if (count($chunk["rows"]) === 0) {
                break;
            }
            $page++;
        }

        $filename = "bitacora_padron_" . date("Ymd_His") . ".csv";
        header("Content-Type: text/csv; charset=UTF-8");
        header("Content-Disposition: attachment; filename=\"{$filename}\"");
        echo "\xEF\xBB\xBF";
        $out = fopen("php://output", "w");
        if ($out === false) {
            header("Content-Type: application/json; charset=UTF-8");
            mxli_json_response(["ok" => false, "error" => "No se pudo generar el CSV"], 500);
        }
        fputcsv($out, [
            "fecha_hora",
            "usuario",
            "nombre",
            "tipo",
            "clave",
            "busqueda",
            "encontrado",
            "resultados",
            "ip",
        ], ",");
        foreach ($all as $row) {
            $found = $row["found"];
            $foundTxt = "";
            if ($found === true) {
                $foundTxt = "si";
            } elseif ($found === false) {
                $foundTxt = "no";
            }
            fputcsv($out, [
                (string)($row["consulted_at"] ?? ""),
                (string)($row["username"] ?? ""),
                (string)($row["full_name"] ?? ""),
                (string)($row["tipo"] ?? ""),
                (string)($row["clave"] ?? ""),
                (string)($row["query_text"] ?? ""),
                $foundTxt,
                $row["result_count"] !== null ? (string)$row["result_count"] : "",
                (string)($row["ip"] ?? ""),
            ], ",");
        }
        fclose($out);
        exit;
    }

    header("Content-Type: application/json; charset=UTF-8");
    $data = mxli_padron_log_list($filters);
    mxli_json_response([
        "ok" => true,
        "rows" => $data["rows"],
        "total" => $data["total"],
        "page" => $data["page"],
        "limit" => $data["limit"],
        "pages" => $data["pages"],
    ]);
} catch (Throwable $e) {
    header("Content-Type: application/json; charset=UTF-8");
    $payload = [
        "ok" => false,
        "error" => "No se pudo leer la bitácora de padrón.",
    ];
    if (mxli_is_admin()) {
        $payload["detail"] = $e->getMessage();
    }
    mxli_json_response($payload, 500);
}
