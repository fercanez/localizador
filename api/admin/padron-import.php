<?php
declare(strict_types=1);

/**
 * Importación / actualización de public.padron2026 desde CSV o XLSX.
 * GET  ?action=status|template
 * POST multipart: file, have_backup=1, make_backup=0|1
 */

@ini_set("display_errors", "0");
@ini_set("html_errors", "0");
@ini_set("log_errors", "1");
error_reporting(E_ALL);
ob_start();

@set_time_limit(0);
@ini_set("max_execution_time", "0");
@ini_set("memory_limit", "512M");

const MXLI_PADRON_IMPORT_MAX_BYTES = 120 * 1024 * 1024; // 120 MB
const MXLI_PADRON_IMPORT_XLSX_MAX_BYTES = 12 * 1024 * 1024; // XLSX completo en memoria
const MXLI_PADRON_IMPORT_BATCH = 800;

function mxli_padron_api_json(array $payload, int $status = 200): void
{
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    if (!headers_sent()) {
        http_response_code($status);
        header("Content-Type: application/json; charset=UTF-8");
        header("Cache-Control: no-store, no-cache, must-revalidate");
    }
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

register_shutdown_function(static function (): void {
    $err = error_get_last();
    if ($err === null) {
        return;
    }
    $fatalTypes = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR];
    if (!in_array((int)($err["type"] ?? 0), $fatalTypes, true)) {
        return;
    }
    if (!function_exists("mxli_padron_api_json")) {
        return;
    }
    mxli_padron_api_json([
        "ok" => false,
        "error" => "Error fatal al importar el padrón",
        "detail" => (string)($err["message"] ?? "fatal"),
    ], 500);
});

try {
    $root = dirname(__DIR__, 2);
    require_once $root . "/includes/init.php";
    require_once $root . "/includes/auth.php";
    require_once $root . "/includes/padron.php";
    require_once $root . "/includes/padron-import.php";
    @ini_set("display_errors", "0");
    @ini_set("html_errors", "0");
} catch (Throwable $e) {
    mxli_padron_api_json([
        "ok" => false,
        "error" => "Error al cargar dependencias",
        "detail" => $e->getMessage(),
    ], 500);
}

header("Cache-Control: no-store, no-cache, must-revalidate");

if (!mxli_is_logged_in() || !mxli_can("users.manage")) {
    mxli_padron_api_json(["ok" => false, "error" => "Acceso denegado"], 403);
}

$method = $_SERVER["REQUEST_METHOD"] ?? "GET";
$action = strtolower(trim((string)($_GET["action"] ?? "")));

// --- Plantilla CSV ---
if ($method === "GET" && ($action === "template" || isset($_GET["template"]))) {
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    header("Content-Type: text/csv; charset=UTF-8");
    header("Content-Disposition: attachment; filename=\"plantilla_padron2026.csv\"");
    echo "\xEF\xBB\xBF";
    $cols = mxli_padron_import_target_columns();
    echo implode(",", $cols) . "\n";
    // Fila de ejemplo (también acepta encabezados Excel)
    echo "SP001001,NOMBRE EJEMPLO,,CENTRO,COLONIA EJEMPLO,CALLE EJEMPLO,100,,,1,0,725,,,,,,HABITACIONAL,\n";
    exit;
}

// --- Estado actual (count siempre, aunque meta esté vacío o falle) ---
$wantStatus = $method === "GET" && (
    $action === "" || $action === "status" || isset($_GET["status"])
);
if ($wantStatus) {
    $rowCount = null;
    $countError = null;
    $status = [
        "row_count" => 0,
        "can_insert" => false,
        "can_truncate" => false,
        "can_create" => false,
        "columns" => [],
    ];
    $meta = null;
    $metaError = null;

    // 1) Conteo real de padron2026 (geonode) — independiente de meta
    try {
        $geo = mxli_geonode_db();
        try {
            $qualified = mxli_padron_qualified();
            $rowCount = (int)$geo->query("SELECT count(*) FROM {$qualified}")->fetchColumn();
        } catch (Throwable $e) {
            $countError = $e->getMessage();
        }
        try {
            $status = mxli_padron_import_status($geo);
            if ($rowCount === null) {
                $rowCount = (int)$status["row_count"];
            } else {
                $status["row_count"] = $rowCount;
            }
        } catch (Throwable $e) {
            if ($countError === null) {
                $countError = $e->getMessage();
            }
            if ($rowCount !== null) {
                $status["row_count"] = $rowCount;
            }
        }
    } catch (Throwable $e) {
        $countError = $e->getMessage();
    }

    // 2) Meta en BD de la app (localizacion_app vía mxli_db) — no bloquea el conteo
    try {
        $app = mxli_db();
        mxli_padron_import_ensure_meta($app);
        $meta = mxli_padron_import_read_meta($app);
    } catch (Throwable $e) {
        $metaError = $e->getMessage();
        $meta = null;
    }

    if ($rowCount === null && $countError !== null) {
        mxli_padron_api_json([
            "ok" => false,
            "error" => "No se pudo consultar el padrón",
            "detail" => $countError,
            "meta_error" => $metaError,
        ], 500);
    }

    $canInsert = (bool)$status["can_insert"];
    $canTruncate = (bool)$status["can_truncate"];
    mxli_padron_api_json([
        "ok" => true,
        "table" => mxli_padron_qualified(),
        "row_count" => (int)($rowCount ?? 0),
        "can_insert" => $canInsert,
        "can_truncate" => $canTruncate,
        "can_create" => (bool)$status["can_create"],
        "columns" => $status["columns"],
        "meta" => $meta,
        "meta_error" => $metaError,
        "count_error" => $countError,
        "limits" => [
            "max_bytes" => MXLI_PADRON_IMPORT_MAX_BYTES,
            "xlsx_max_bytes" => MXLI_PADRON_IMPORT_XLSX_MAX_BYTES,
        ],
        "hint" => (!$canInsert || !$canTruncate)
            ? "Faltan permisos de carga. Ejecute sql/grant_padron_loader.sql como postgres."
            : ($metaError
                ? "El padrón se puede contar, pero no se pudo leer/crear app_padron_import_meta: {$metaError}"
                : null),
    ]);
}

if ($method !== "POST") {
    mxli_padron_api_json(["ok" => false, "error" => "Método no permitido"], 405);
}

// --- POST import ---
$haveBackup = isset($_POST["have_backup"]) && in_array(
    strtolower((string)$_POST["have_backup"]),
    ["1", "true", "on", "yes", "si", "sí"],
    true
);
$makeBackup = isset($_POST["make_backup"]) && in_array(
    strtolower((string)$_POST["make_backup"]),
    ["1", "true", "on", "yes", "si", "sí"],
    true
);

if (!$haveBackup && !$makeBackup) {
    mxli_padron_api_json([
        "ok" => false,
        "error" => "Confirme que ya tiene respaldo, o marque «Crear respaldo en BD» antes de reemplazar el padrón.",
    ], 400);
}

if (!isset($_FILES["file"]) || !is_array($_FILES["file"])) {
    mxli_padron_api_json(["ok" => false, "error" => "Adjunte un archivo CSV o XLSX"], 400);
}

$file = $_FILES["file"];
$err = (int)($file["error"] ?? UPLOAD_ERR_NO_FILE);
if ($err === UPLOAD_ERR_INI_SIZE || $err === UPLOAD_ERR_FORM_SIZE) {
    mxli_padron_api_json([
        "ok" => false,
        "error" => "El archivo supera el límite de PHP (upload_max_filesize / post_max_size). Suba un CSV o aumente el límite a ≥100 MB.",
    ], 400);
}
if ($err !== UPLOAD_ERR_OK) {
    mxli_padron_api_json(["ok" => false, "error" => "Error al subir el archivo (código {$err})"], 400);
}

$size = (int)($file["size"] ?? 0);
if ($size <= 0) {
    mxli_padron_api_json(["ok" => false, "error" => "El archivo está vacío"], 400);
}
if ($size > MXLI_PADRON_IMPORT_MAX_BYTES) {
    mxli_padron_api_json(["ok" => false, "error" => "El archivo supera 120 MB"], 400);
}

$tmp = (string)($file["tmp_name"] ?? "");
$origName = (string)($file["name"] ?? "padron.csv");
$ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
if ($ext === "xls") {
    mxli_padron_api_json([
        "ok" => false,
        "error" => "Formato .xls no soportado. Guarde como .xlsx o, preferible, CSV UTF-8 / Windows-1252.",
    ], 400);
}
if (!in_array($ext, ["csv", "xlsx", "txt"], true)) {
    mxli_padron_api_json(["ok" => false, "error" => "Formato no soportado. Use CSV o XLSX."], 400);
}
if ($ext === "xlsx" && $size > MXLI_PADRON_IMPORT_XLSX_MAX_BYTES) {
    mxli_padron_api_json([
        "ok" => false,
        "error" => "XLSX demasiado grande para importar en memoria. Exporte a CSV (≈80 MB) e intente de nuevo.",
    ], 400);
}

$fhMagic = @fopen($tmp, "rb");
$magic = $fhMagic ? (string)fread($fhMagic, 8) : "";
if ($fhMagic) {
    fclose($fhMagic);
}
if (strncmp($magic, "\xD0\xCF\x11\xE0", 4) === 0) {
    mxli_padron_api_json([
        "ok" => false,
        "error" => "El archivo es Excel antiguo (.xls). Guárdelo como CSV o .xlsx.",
    ], 400);
}

$workDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . "mxli_padron_" . getmypid() . "_" . bin2hex(random_bytes(4));
if (!@mkdir($workDir, 0700, true) && !is_dir($workDir)) {
    mxli_padron_api_json(["ok" => false, "error" => "No se pudo crear directorio temporal"], 500);
}

$mappedCsv = $workDir . DIRECTORY_SEPARATOR . "padron_mapped.csv";
$cleanup = static function () use ($workDir): void {
    if (!is_dir($workDir)) {
        return;
    }
    foreach (glob($workDir . DIRECTORY_SEPARATOR . "*") ?: [] as $f) {
        @unlink($f);
    }
    @rmdir($workDir);
};

try {
    $uploadPath = $workDir . DIRECTORY_SEPARATOR . "upload." . $ext;
    if (!@move_uploaded_file($tmp, $uploadPath) && !@copy($tmp, $uploadPath)) {
        throw new RuntimeException("No se pudo guardar el archivo subido");
    }

    if ($ext === "xlsx") {
        $rowsWritten = mxli_padron_import_xlsx_to_mapped_csv($uploadPath, $mappedCsv);
    } else {
        $rowsWritten = mxli_padron_import_csv_to_mapped_csv($uploadPath, $mappedCsv);
    }

    if ($rowsWritten <= 0) {
        throw new RuntimeException("El archivo no tiene filas de datos");
    }

    $geo = mxli_geonode_db();
    $app = mxli_db();
    $status = mxli_padron_import_status($geo);
    if (!$status["can_insert"] || !$status["can_truncate"]) {
        throw new RuntimeException(
            "El usuario de BD no puede INSERT/TRUNCATE en padron2026. Ejecute sql/grant_padron_loader.sql como postgres."
        );
    }

    $allTargetCols = mxli_padron_import_target_columns();
    // Solo columnas que existen en la tabla viva (case-insensitive)
    $liveCols = mxli_padron_column_names($geo);
    $liveByNorm = [];
    foreach ($liveCols as $lc) {
        $liveByNorm[strtolower($lc)] = $lc;
    }
    $insertCols = []; // nombres reales en PG
    $stgCols = [];    // nombres en staging (= target snake)
    foreach ($allTargetCols as $want) {
        if (isset($liveByNorm[strtolower($want)])) {
            $insertCols[] = $liveByNorm[strtolower($want)];
            $stgCols[] = $want;
        }
    }
    // Fallbacks de typos históricos
    if (!in_array("sup_regitrada", $stgCols, true) && isset($liveByNorm["sup_registrada"])) {
        $insertCols[] = $liveByNorm["sup_registrada"];
        $stgCols[] = "sup_regitrada";
    }
    if ($stgCols === [] || !in_array("clavecatas", $stgCols, true)) {
        throw new RuntimeException(
            "La tabla padron2026 no tiene columnas compatibles (clavecatas…). Columnas: "
            . implode(", ", $liveCols)
        );
    }

    $stgName = "padron_load_stg";

    // Staging TEMP (no requiere CREATE ON SCHEMA)
    $geo->exec("DROP TABLE IF EXISTS {$stgName}");
    $defs = [];
    foreach ($allTargetCols as $c) {
        $defs[] = '"' . $c . '" text';
    }
    $geo->exec("CREATE TEMP TABLE {$stgName} (" . implode(", ", $defs) . ")");

    $loaded = mxli_padron_import_load_csv_into_table($geo, $stgName, $mappedCsv, $allTargetCols);
    if ($loaded <= 0) {
        throw new RuntimeException("No se cargaron filas en staging");
    }

    try {
        $geo->exec("CREATE INDEX ON {$stgName} (clavecatas)");
    } catch (Throwable $e) {
        // no crítico
    }

    $qualified = mxli_padron_qualified();
    $rowsBefore = (int)$geo->query("SELECT count(*) FROM {$qualified}")->fetchColumn();

    // Diff antes del swap (staging vs tabla viva)
    $diff = mxli_padron_import_compute_diff($geo, $stgName, $qualified, $liveByNorm);

    $backupTable = null;
    if ($makeBackup) {
        if (!$status["can_create"]) {
            throw new RuntimeException(
                "No hay permiso CREATE en schema public para respaldar. Marque «Ya tengo respaldo» o ejecute grant_padron_loader.sql."
            );
        }
        $backupTable = "padron2026_backup_" . date("Ymd_His");
        if (!preg_match('/^[a-z0-9_]+$/', $backupTable)) {
            throw new RuntimeException("Nombre de respaldo inválido");
        }
        $geo->exec("CREATE TABLE public.{$backupTable} AS TABLE {$qualified}");
    }

    $selectParts = [];
    $insertParts = [];
    foreach ($stgCols as $i => $stgCol) {
        $liveCol = $insertCols[$i];
        $insertParts[] = mxli_padron_quote_ident($liveCol);
        $selectParts[] = 'NULLIF(btrim("' . $stgCol . '"::text), \'\')';
    }
    $insertList = implode(", ", $insertParts);
    $selectList = implode(", ", $selectParts);

    $geo->beginTransaction();
    try {
        $geo->exec("TRUNCATE TABLE {$qualified}");
        $geo->exec(
            "INSERT INTO {$qualified} ({$insertList})
             SELECT {$selectList} FROM {$stgName}"
        );
        $geo->commit();
    } catch (Throwable $e) {
        if ($geo->inTransaction()) {
            $geo->rollBack();
        }
        throw $e;
    }

    // Índice útil si no existe
    try {
        $geo->exec(
            "CREATE INDEX IF NOT EXISTS idx_padron2026_clavecatas ON {$qualified} (clavecatas)"
        );
    } catch (Throwable $e) {
        // no crítico
    }

    $rowsAfter = (int)$geo->query("SELECT count(*) FROM {$qualified}")->fetchColumn();

    $stats = [
        "rows_before" => $rowsBefore,
        "rows_after" => $rowsAfter,
        "claves_nuevas" => (int)$diff["claves_nuevas"],
        "claves_cambiadas" => (int)$diff["claves_cambiadas"],
        "claves_eliminadas" => (int)$diff["claves_eliminadas"],
        "claves_iguales" => (int)$diff["claves_iguales"],
        "backup_table" => $backupTable,
        "note" => "import " . $origName,
    ];

    $me = mxli_current_user();
    $username = (string)($me["username"] ?? "admin");
    $meta = null;
    $metaError = null;
    try {
        mxli_padron_import_write_meta($app, $username, $origName, $stats);
        $meta = mxli_padron_import_read_meta($app);
    } catch (Throwable $e) {
        $metaError = $e->getMessage();
        // La carga ya se aplicó; devolver éxito con comparativo aunque meta falle.
    }

    $cleanup();

    mxli_padron_api_json([
        "ok" => true,
        "message" => "Padrón actualizado: {$rowsAfter} filas cargadas.",
        "row_count" => $rowsAfter,
        "rows_loaded" => $loaded,
        "rows_before" => $rowsBefore,
        "rows_after" => $rowsAfter,
        "claves_nuevas" => $stats["claves_nuevas"],
        "claves_cambiadas" => $stats["claves_cambiadas"],
        "claves_eliminadas" => $stats["claves_eliminadas"],
        "claves_iguales" => $stats["claves_iguales"],
        "backup_table" => $backupTable,
        "filename" => $origName,
        "imported_by" => $username,
        "meta" => $meta,
        "meta_error" => $metaError,
        "comparativo" => [
            "rows_before" => $rowsBefore,
            "rows_after" => $rowsAfter,
            "claves_nuevas" => $stats["claves_nuevas"],
            "claves_cambiadas" => $stats["claves_cambiadas"],
            "claves_eliminadas" => $stats["claves_eliminadas"],
            "claves_iguales" => $stats["claves_iguales"],
        ],
    ]);
} catch (Throwable $e) {
    $cleanup();
    $msg = $e->getMessage();
    $hint = null;
    if (stripos($msg, "permission") !== false || stripos($msg, "privilege") !== false) {
        $hint = "Ejecute sql/grant_padron_loader.sql como postgres en geonode_data.";
    }
    mxli_padron_api_json([
        "ok" => false,
        "error" => "Error al importar el padrón",
        "detail" => $msg,
        "hint" => $hint,
    ], 500);
}

/**
 * @param array<string, int> $colMap target => source index
 * @param list<string> $row
 * @return list<string>
 */
function mxli_padron_import_row_mapped(array $colMap, array $row): array
{
    $out = [];
    foreach (mxli_padron_import_target_columns() as $col) {
        $idx = $colMap[$col] ?? null;
        $val = "";
        if ($idx !== null && array_key_exists($idx, $row)) {
            $val = mxli_padron_import_cell_clean((string)$row[$idx]);
        }
        if ($col === "clavecatas") {
            $val = mxli_normalize_clave_catastral($val);
        }
        $out[] = $val;
    }
    return $out;
}

function mxli_padron_import_write_mapped_line($outFh, array $mappedCells): void
{
    $parts = [];
    foreach ($mappedCells as $cell) {
        $parts[] = mxli_padron_import_csv_escape($cell);
    }
    fwrite($outFh, implode(",", $parts) . "\n");
}

/**
 * Stream CSV → CSV mapeado UTF-8 con columnas destino.
 */
function mxli_padron_import_csv_to_mapped_csv(string $srcPath, string $destPath): int
{
    $sample = (string)file_get_contents($srcPath, false, null, 0, 65536);
    $enc = mxli_padron_import_detect_encoding($sample);

    $in = fopen($srcPath, "rb");
    if ($in === false) {
        throw new RuntimeException("No se pudo abrir el CSV");
    }
    $out = fopen($destPath, "wb");
    if ($out === false) {
        fclose($in);
        throw new RuntimeException("No se pudo crear CSV mapeado");
    }

    // Leer todo a un temp UTF-8 si hace falta conversión por encoding
    // Para archivos grandes: convertir línea a línea.
    $headerLine = fgets($in);
    if ($headerLine === false) {
        fclose($in);
        fclose($out);
        throw new RuntimeException("CSV vacío");
    }
    $headerLine = mxli_padron_import_decode_line($headerLine, $enc);
    $headers = str_getcsv($headerLine, ",", '"', "\\");
    if (!is_array($headers) || $headers === []) {
        fclose($in);
        fclose($out);
        throw new RuntimeException("No se leyeron encabezados");
    }

    $mapped = mxli_padron_import_map_headers($headers);
    if ($mapped === null) {
        fclose($in);
        fclose($out);
        throw new RuntimeException(
            "Encabezados inválidos: se requiere CLAVECATASTRAL / clavecatas (u alias). "
            . "Detectados: " . implode(", ", array_map("trim", $headers))
        );
    }
    $colMap = $mapped["map"];

    // Sin header: pgsqlCopyFromFile no soporta HEADER true
    $count = 0;
    while (($line = fgets($in)) !== false) {
        $line = mxli_padron_import_decode_line($line, $enc);
        if (trim($line) === "") {
            continue;
        }
        $row = str_getcsv($line, ",", '"', "\\");
        if (!is_array($row)) {
            continue;
        }
        $cells = mxli_padron_import_row_mapped($colMap, $row);
        // Saltar filas sin clave
        if (($cells[0] ?? "") === "") {
            continue;
        }
        mxli_padron_import_write_mapped_line($out, $cells);
        $count++;
    }
    fclose($in);
    fclose($out);
    return $count;
}

function mxli_padron_import_decode_line(string $line, string $enc): string
{
    $line = rtrim($line, "\r\n");
    if ($enc === "UTF-8") {
        // quitar BOM solo en primera línea si aparece
        $line = preg_replace('/^\xEF\xBB\xBF/', "", $line) ?? $line;
        return $line;
    }
    if ($enc === "UTF-16") {
        return mxli_padron_import_to_utf8($line);
    }
    // Windows-1252
    if (function_exists("mb_convert_encoding")) {
        $c = @mb_convert_encoding($line, "UTF-8", "Windows-1252");
        if (is_string($c)) {
            return $c;
        }
    }
    return mxli_padron_import_to_utf8($line);
}

/**
 * XLSX pequeño → CSV mapeado (reutiliza lógica mínima OpenXML).
 */
function mxli_padron_import_xlsx_to_mapped_csv(string $srcPath, string $destPath): int
{
    $grid = mxli_padron_import_parse_xlsx_lite($srcPath);
    if ($grid === []) {
        throw new RuntimeException("XLSX sin filas");
    }

    $headerIdx = null;
    $colMap = null;
    $maxProbe = min(5, count($grid));
    for ($i = 0; $i < $maxProbe; $i++) {
        $candidate = $grid[$i];
        $empty = true;
        foreach ($candidate as $c) {
            if (trim((string)$c) !== "") {
                $empty = false;
                break;
            }
        }
        if ($empty) {
            continue;
        }
        $mapped = mxli_padron_import_map_headers($candidate);
        if ($mapped !== null) {
            $headerIdx = $i;
            $colMap = $mapped["map"];
            break;
        }
    }
    if ($headerIdx === null || $colMap === null) {
        throw new RuntimeException("Encabezados XLSX inválidos (falta CLAVECATASTRAL / clavecatas)");
    }

    $out = fopen($destPath, "wb");
    if ($out === false) {
        throw new RuntimeException("No se pudo crear CSV mapeado");
    }
    // Sin header (compatible con pgsqlCopyFromFile)
    $count = 0;
    for ($i = $headerIdx + 1, $n = count($grid); $i < $n; $i++) {
        $cells = mxli_padron_import_row_mapped($colMap, $grid[$i]);
        if (($cells[0] ?? "") === "") {
            continue;
        }
        mxli_padron_import_write_mapped_line($out, $cells);
        $count++;
    }
    fclose($out);
    return $count;
}

/**
 * Lector XLSX mínimo (primera hoja).
 * @return list<list<string>>
 */
function mxli_padron_import_parse_xlsx_lite(string $path): array
{
    if (!class_exists("ZipArchive")) {
        throw new RuntimeException("PHP ZipArchive no disponible. Use CSV.");
    }
    $zip = new ZipArchive();
    if ($zip->open($path) !== true) {
        throw new RuntimeException("No se pudo abrir el XLSX");
    }

    $stripNs = static function (string $xml): string {
        $xml = preg_replace('/\sxmlns(:\w+)?="[^"]*"/', "", $xml) ?? $xml;
        $xml = preg_replace('/([<\/])[a-zA-Z0-9]+:/', '$1', $xml) ?? $xml;
        return $xml;
    };
    $siText = static function (SimpleXMLElement $si): string {
        if (isset($si->t)) {
            return (string)$si->t;
        }
        $parts = [];
        if (isset($si->r)) {
            foreach ($si->r as $r) {
                $parts[] = (string)($r->t ?? "");
            }
        }
        return implode("", $parts);
    };
    $colIndex = static function (string $ref): int {
        $letters = strtoupper(preg_replace("/\d+/", "", $ref) ?? "");
        $idx = 0;
        $len = strlen($letters);
        for ($i = 0; $i < $len; $i++) {
            $idx = $idx * 26 + (ord($letters[$i]) - 64);
        }
        return max(0, $idx - 1);
    };

    $shared = [];
    $ssXml = $zip->getFromName("xl/sharedStrings.xml");
    if ($ssXml !== false && $ssXml !== "") {
        $sx = @simplexml_load_string($stripNs($ssXml));
        if ($sx !== false) {
            foreach ($sx->si as $si) {
                $shared[] = $siText($si);
            }
        }
    }

    $sheetXml = $zip->getFromName("xl/worksheets/sheet1.xml");
    if ($sheetXml === false) {
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            if (is_string($name) && preg_match('#^xl/worksheets/[^/]+\.xml$#i', $name)) {
                $sheetXml = $zip->getFromIndex($i);
                break;
            }
        }
    }
    $zip->close();
    if ($sheetXml === false || $sheetXml === "") {
        throw new RuntimeException("No se encontró hoja en el XLSX");
    }
    $sheet = @simplexml_load_string($stripNs($sheetXml));
    if ($sheet === false || !isset($sheet->sheetData->row)) {
        return [];
    }

    $rowsOut = [];
    foreach ($sheet->sheetData->row as $row) {
        $cells = [];
        $maxCol = -1;
        if (!isset($row->c)) {
            $rowsOut[] = [];
            continue;
        }
        foreach ($row->c as $c) {
            $ref = (string)($c["r"] ?? "");
            $colIdx = $ref !== "" ? $colIndex($ref) : ($maxCol + 1);
            $type = (string)($c["t"] ?? "");
            $val = "";
            if ($type === "s") {
                $si = (int)(string)($c->v ?? "-1");
                $val = $shared[$si] ?? "";
            } elseif ($type === "inlineStr" && isset($c->is)) {
                $val = $siText($c->is);
            } else {
                $val = (string)($c->v ?? "");
            }
            $cells[$colIdx] = $val;
            $maxCol = max($maxCol, $colIdx);
        }
        $line = [];
        for ($i = 0; $i <= $maxCol; $i++) {
            $line[] = $cells[$i] ?? "";
        }
        $rowsOut[] = $line;
    }
    return $rowsOut;
}

/**
 * Carga CSV mapeado a tabla (COPY si está disponible; si no, batch INSERT).
 *
 * @param list<string> $cols
 */
function mxli_padron_import_load_csv_into_table(PDO $pdo, string $table, string $csvPath, array $cols): int
{
    $fields = implode(",", $cols);

    // Preferir COPY FROM STDIN vía pgsqlCopyFromFile
    if (method_exists($pdo, "pgsqlCopyFromFile")) {
        $ok = @$pdo->pgsqlCopyFromFile($table, $csvPath, ",", "", $fields);
        if ($ok) {
            return (int)$pdo->query("SELECT count(*) FROM {$table}")->fetchColumn();
        }
    }

    // Fallback: batch INSERT
    $fh = fopen($csvPath, "rb");
    if ($fh === false) {
        throw new RuntimeException("No se pudo leer CSV mapeado");
    }

    $placeholders = "(" . implode(",", array_fill(0, count($cols), "?")) . ")";
    $sql = "INSERT INTO {$table} (" . implode(",", array_map(static fn ($c) => '"' . $c . '"', $cols))
        . ") VALUES ";
    $batch = [];
    $loaded = 0;

    $flush = static function () use ($pdo, $sql, &$batch, $placeholders, &$loaded): void {
        if ($batch === []) {
            return;
        }
        $n = count($batch);
        $valuesSql = implode(",", array_fill(0, $n, $placeholders));
        $flat = [];
        foreach ($batch as $row) {
            foreach ($row as $cell) {
                $flat[] = $cell === "" ? null : $cell;
            }
        }
        $stmt = $pdo->prepare($sql . $valuesSql);
        $stmt->execute($flat);
        $loaded += $n;
        $batch = [];
    };

    while (($row = fgetcsv($fh, 0, ",", '"', "\\")) !== false) {
        if ($row === [null] || $row === false) {
            continue;
        }
        $cells = [];
        foreach ($cols as $i => $_) {
            $cells[] = isset($row[$i]) ? (string)$row[$i] : "";
        }
        if ($cells[0] === "") {
            continue;
        }
        $batch[] = $cells;
        if (count($batch) >= MXLI_PADRON_IMPORT_BATCH) {
            $flush();
        }
    }
    $flush();
    fclose($fh);
    return $loaded;
}

/**
 * Compara staging vs padron actual por clavecatas normalizada.
 *
 * @param array<string, string> $liveByNorm lower(col) => original name
 * @return array{claves_nuevas:int, claves_cambiadas:int, claves_eliminadas:int, claves_iguales:int}
 */
function mxli_padron_import_compute_diff(
    PDO $pdo,
    string $stg,
    string $qualified,
    array $liveByNorm = []
): array {
    $claveLive = $liveByNorm["clavecatas"] ?? ($liveByNorm["clavecata"] ?? "clavecatas");
    $claveLiveQ = mxli_padron_quote_ident($claveLive);

    $normOld = "replace(upper(trim(COALESCE(o.{$claveLiveQ}::text, ''))), ' ', '')";
    $normNew = "replace(upper(trim(COALESCE(n.\"clavecatas\"::text, ''))), ' ', '')";

    $changeParts = [];
    foreach (mxli_padron_import_diff_fields() as $f) {
        if ($f === "clavecatas") {
            continue;
        }
        // Solo comparar si la columna existe en la tabla viva
        $liveName = $liveByNorm[strtolower($f)] ?? null;
        if ($liveName === null && $f === "sup_regitrada") {
            $liveName = $liveByNorm["sup_registrada"] ?? null;
        }
        if ($liveName === null) {
            continue;
        }
        $oq = mxli_padron_quote_ident($liveName);
        $changeParts[] =
            "COALESCE(btrim(n.\"{$f}\"::text), '') IS DISTINCT FROM COALESCE(btrim(o.{$oq}::text), '')";
    }
    $changeExpr = $changeParts === [] ? "FALSE" : "(" . implode(" OR ", $changeParts) . ")";

    $sql = "
      WITH
      old_keys AS (
        SELECT DISTINCT {$normOld} AS k
        FROM {$qualified} o
        WHERE {$normOld} <> ''
      ),
      new_keys AS (
        SELECT DISTINCT {$normNew} AS k
        FROM {$stg} n
        WHERE {$normNew} <> ''
      ),
      paired AS (
        SELECT
          {$normNew} AS k,
          CASE WHEN {$changeExpr} THEN 1 ELSE 0 END AS changed
        FROM {$stg} n
        INNER JOIN {$qualified} o
          ON {$normNew} = {$normOld}
      )
      SELECT
        (SELECT count(*) FROM new_keys nk WHERE NOT EXISTS (SELECT 1 FROM old_keys ok WHERE ok.k = nk.k))::int AS claves_nuevas,
        (SELECT count(*) FROM old_keys ok WHERE NOT EXISTS (SELECT 1 FROM new_keys nk WHERE nk.k = ok.k))::int AS claves_eliminadas,
        (SELECT COALESCE(sum(changed), 0) FROM (
           SELECT k, MAX(changed) AS changed FROM paired GROUP BY k
         ) x)::int AS claves_cambiadas,
        (SELECT count(*) FROM (
           SELECT k, MAX(changed) AS changed FROM paired GROUP BY k
         ) x WHERE changed = 0)::int AS claves_iguales
    ";

    $row = $pdo->query($sql)->fetch(PDO::FETCH_ASSOC);
    if (!is_array($row)) {
        return [
            "claves_nuevas" => 0,
            "claves_cambiadas" => 0,
            "claves_eliminadas" => 0,
            "claves_iguales" => 0,
        ];
    }
    return [
        "claves_nuevas" => (int)($row["claves_nuevas"] ?? 0),
        "claves_cambiadas" => (int)($row["claves_cambiadas"] ?? 0),
        "claves_eliminadas" => (int)($row["claves_eliminadas"] ?? 0),
        "claves_iguales" => (int)($row["claves_iguales"] ?? 0),
    ];
}
