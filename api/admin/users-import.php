<?php
declare(strict_types=1);

/**
 * Importación masiva de usuarios (CSV / XLSX).
 * Siempre responde JSON en POST (nunca HTML de warnings/fatals de PHP).
 */

@ini_set("display_errors", "0");
@ini_set("html_errors", "0");
@ini_set("log_errors", "1");
error_reporting(E_ALL);
ob_start();

const MXLI_IMPORT_MAX_BYTES = 2 * 1024 * 1024; // 2 MB
const MXLI_IMPORT_MAX_ROWS = 500;

/**
 * Descarta cualquier salida accidental (warnings, BOM, espacios) y responde JSON.
 */
function mxli_import_json(array $payload, int $status = 200): void
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

/**
 * Convierte fatales no capturables (memoria, etc.) en JSON en lugar de HTML <br/><b>.
 */
register_shutdown_function(static function (): void {
    $err = error_get_last();
    if ($err === null) {
        return;
    }
    $fatalTypes = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR];
    if (!in_array((int)($err["type"] ?? 0), $fatalTypes, true)) {
        return;
    }
    if (!function_exists("mxli_import_json")) {
        return;
    }
    mxli_import_json([
        "ok" => false,
        "error" => "Error fatal en el servidor al importar",
        "detail" => (string)($err["message"] ?? "fatal"),
    ], 500);
});

function mxli_import_mb_strlen(string $s): int
{
    return function_exists("mb_strlen") ? mb_strlen($s, "UTF-8") : strlen($s);
}

function mxli_import_mb_strtolower(string $s): string
{
    return function_exists("mb_strtolower") ? mb_strtolower($s, "UTF-8") : strtolower($s);
}

try {
    $root = dirname(__DIR__, 2);
    $init = $root . DIRECTORY_SEPARATOR . "includes" . DIRECTORY_SEPARATOR . "init.php";
    $auth = $root . DIRECTORY_SEPARATOR . "includes" . DIRECTORY_SEPARATOR . "auth.php";
    if (!is_file($init) || !is_file($auth)) {
        mxli_import_json([
            "ok" => false,
            "error" => "Rutas de includes incorrectas en el servidor",
            "detail" => "init/auth no encontrados bajo " . $root,
        ], 500);
    }
    require_once $init;
    require_once $auth;
    // init/config pueden reactivar display_errors; forzar de nuevo.
    @ini_set("display_errors", "0");
    @ini_set("html_errors", "0");
} catch (Throwable $e) {
    mxli_import_json([
        "ok" => false,
        "error" => "Error al cargar dependencias",
        "detail" => $e->getMessage(),
    ], 500);
}

if (!function_exists("mxli_is_logged_in")
    || !function_exists("mxli_can")
    || !function_exists("mxli_resolve_role_slug")
    || !function_exists("mxli_db")
) {
    mxli_import_json([
        "ok" => false,
        "error" => "Helpers de autenticación no disponibles (auth.php desactualizado)",
    ], 500);
}

header("Cache-Control: no-store, no-cache, must-revalidate");

if (!mxli_is_logged_in() || !mxli_can("users.manage")) {
    mxli_import_json(["ok" => false, "error" => "Acceso denegado"], 403);
}

function mxli_import_sql_bool(bool $value): string
{
    return $value ? "TRUE" : "FALSE";
}

/**
 * Normaliza encabezado: BOM, acentos (NFKD), minúsculas, espacios → _.
 */
function mxli_import_normalize_header(string $raw): string
{
    $h = $raw;
    // BOM UTF-8 / UTF-16 leftovers al inicio de celda
    $h = preg_replace('/^\xEF\xBB\xBF/', "", $h) ?? $h;
    $h = preg_replace('/^[\x{FEFF}\x{200B}\x{200C}\x{200D}\x{2060}]+/u', "", $h) ?? $h;
    // NBSP y espacios raros → espacio normal
    $h = str_replace(["\xC2\xA0", "\xE2\x80\xAF", "\t"], " ", $h);
    $h = trim($h);
    $h = mxli_import_mb_strtolower($h);

    // NFKD: descompone acentos (á→a+combining) y luego quita marcas
    if (class_exists("Normalizer")) {
        $n = Normalizer::normalize($h, Normalizer::FORM_KD);
        if (is_string($n) && $n !== "") {
            $h = $n;
        }
    }
    // Marcas diacríticas (combining) tras NFKD o si no hay intl
    $h = preg_replace('/\p{Mn}+/u', "", $h) ?? $h;

    // Mapa explícito por si intl/Normalizer no está o falló
    $h = strtr($h, [
        "á" => "a", "à" => "a", "ä" => "a", "â" => "a", "ã" => "a",
        "é" => "e", "è" => "e", "ë" => "e", "ê" => "e",
        "í" => "i", "ì" => "i", "ï" => "i", "î" => "i",
        "ó" => "o", "ò" => "o", "ö" => "o", "ô" => "o", "õ" => "o",
        "ú" => "u", "ù" => "u", "ü" => "u", "û" => "u",
        "ñ" => "n", "ç" => "c",
        "Á" => "a", "É" => "e", "Í" => "i", "Ó" => "o", "Ú" => "u", "Ñ" => "n",
    ]);

    $h = preg_replace('/[\s\-]+/', "_", $h) ?? $h;
    $h = preg_replace('/_+/', "_", $h) ?? $h;
    $h = preg_replace('/[^a-z0-9_]/', "", $h) ?? $h;
    return trim($h, "_");
}

/**
 * Detecta Latin-1 / Windows-1252 (y UTF-16) y convierte a UTF-8 antes de parsear.
 * Sin esto, la ñ (0xF1 ANSI) se pierde al normalizar y "contraseña" → "contrasea".
 */
function mxli_import_to_utf8(string $raw): string
{
    if ($raw === "") {
        return "";
    }

    // UTF-8 BOM
    if (strncmp($raw, "\xEF\xBB\xBF", 3) === 0) {
        $raw = substr($raw, 3);
        return $raw;
    }

    // UTF-16 LE / BE
    if (strncmp($raw, "\xFF\xFE", 2) === 0 || strncmp($raw, "\xFE\xFF", 2) === 0) {
        if (function_exists("mb_convert_encoding")) {
            $converted = @mb_convert_encoding($raw, "UTF-8", "UTF-16");
            if (is_string($converted) && $converted !== "") {
                return $converted;
            }
        }
        if (function_exists("iconv")) {
            $converted = @iconv("UTF-16", "UTF-8//IGNORE", $raw);
            if (is_string($converted) && $converted !== "") {
                return $converted;
            }
        }
    }

    // Ya es UTF-8 válido (ASCII puro también pasa)
    if (function_exists("mb_check_encoding") && mb_check_encoding($raw, "UTF-8")) {
        return $raw;
    }
    // PHP sin mbstring: heurística — secuencias UTF-8 bien formadas o solo ASCII
    if (!function_exists("mb_check_encoding")
        && (preg_match('//u', $raw) === 1 || preg_match('/^[\x00-\x7F]*$/', $raw) === 1)
    ) {
        return $raw;
    }

    // Excel/Windows en MX suele guardar CSV como Windows-1252 (ANSI)
    if (function_exists("mb_convert_encoding")) {
        $converted = @mb_convert_encoding($raw, "UTF-8", "Windows-1252");
        if (is_string($converted) && $converted !== "") {
            return $converted;
        }
        $converted = @mb_convert_encoding($raw, "UTF-8", "ISO-8859-1");
        if (is_string($converted) && $converted !== "") {
            return $converted;
        }
    }
    if (function_exists("iconv")) {
        $converted = @iconv("Windows-1252", "UTF-8//IGNORE", $raw);
        if (is_string($converted) && $converted !== "") {
            return $converted;
        }
        $converted = @iconv("ISO-8859-1", "UTF-8//IGNORE", $raw);
        if (is_string($converted) && $converted !== "") {
            return $converted;
        }
    }

    // Fallback Latin-1 byte→UTF-8 (equiv. utf8_encode)
    $out = "";
    $len = strlen($raw);
    for ($i = 0; $i < $len; $i++) {
        $c = ord($raw[$i]);
        if ($c < 0x80) {
            $out .= $raw[$i];
        } else {
            $out .= chr(0xC0 | ($c >> 6)) . chr(0x80 | ($c & 0x3F));
        }
    }
    return $out;
}

/**
 * @return list<string> encabezados crudos (trim) para depuración en JSON
 */
function mxli_import_headers_debug(array $headers): array
{
    $out = [];
    foreach ($headers as $raw) {
        $s = trim((string)$raw);
        $s = preg_replace('/^\xEF\xBB\xBF/', "", $s) ?? $s;
        $out[] = $s;
    }
    // Ignorar columnas vacías al final (Excel deja comas sobrantes)
    while ($out !== [] && end($out) === "") {
        array_pop($out);
    }
    return $out;
}

/**
 * ¿La fila está vacía (sin celdas con texto)?
 */
function mxli_import_row_is_empty(array $row): bool
{
    foreach ($row as $cell) {
        if (trim((string)$cell) !== "") {
            return false;
        }
    }
    return true;
}

/**
 * @return array{username:?int,password:?int,full_name:?int,email:?int}|null
 */
function mxli_import_map_headers(array $headers): ?array
{
    $map = [
        "username" => null,
        "password" => null,
        "full_name" => null,
        "email" => null,
    ];

    foreach ($headers as $idx => $raw) {
        $h = mxli_import_normalize_header((string)$raw);
        if ($h === "") {
            continue;
        }
        if (in_array($h, ["usuario", "username", "user", "login", "usuarios"], true)) {
            $map["username"] = (int)$idx;
        } elseif (in_array($h, [
            "contrasena", "password", "passwd", "clave", "pass",
            "contrasenas", "pwd",
        ], true)) {
            $map["password"] = (int)$idx;
        } elseif (in_array($h, [
            "nombre_completo", "full_name", "nombre", "name", "nombrecompleto", "fullname",
        ], true)) {
            $map["full_name"] = (int)$idx;
        } elseif (in_array($h, [
            "correo", "correo_electronico", "email", "e_mail", "mail",
        ], true)) {
            $map["email"] = (int)$idx;
        }
    }

    if ($map["username"] === null || $map["password"] === null) {
        return null;
    }
    return $map;
}

/**
 * Toma la primera fila no vacía como encabezados; si no mapea y la siguiente sí, salta la vacía/basura.
 *
 * @param list<list<string>> $grid
 * @return array{0: list<string>, 1: array{username:int,password:int,full_name:?int,email:?int}, 2: int}|null
 *         [headerRow, colMap, headerLineNo] o null si no hay encabezados válidos
 */
function mxli_import_find_header_map(array &$grid): ?array
{
    $maxProbe = min(5, count($grid));
    for ($i = 0; $i < $maxProbe; $i++) {
        $candidate = $grid[$i];
        if (mxli_import_row_is_empty($candidate)) {
            continue;
        }
        $colMap = mxli_import_map_headers($candidate);
        if ($colMap !== null) {
            // Descartar filas anteriores a la de encabezados (vacías o basura)
            $grid = array_slice($grid, $i + 1);
            return [$candidate, $colMap, $i + 1];
        }
    }
    return null;
}

function mxli_import_cell(array $row, ?int $idx): string
{
    if ($idx === null || !array_key_exists($idx, $row)) {
        return "";
    }
    return trim((string)$row[$idx]);
}

function mxli_import_light_email_ok(string $email): bool
{
    if ($email === "") {
        return true;
    }
    return (bool)preg_match('/^[^\s@]+@[^\s@]+\.[^\s@]+$/', $email);
}

/**
 * @return list<list<string>>
 */
function mxli_import_parse_csv(string $path): array
{
    $raw = @file_get_contents($path);
    if ($raw === false) {
        throw new RuntimeException("No se pudo leer el archivo CSV");
    }
    // ANSI/Windows-1252 → UTF-8 antes de fgetcsv y de normalizar encabezados
    $raw = mxli_import_to_utf8($raw);

    $fh = fopen("php://temp", "r+b");
    if ($fh === false) {
        throw new RuntimeException("No se pudo abrir buffer temporal para CSV");
    }
    fwrite($fh, $raw);
    rewind($fh);

    $rows = [];
    // escape "\\" es compatible PHP 7.4–8.3; en 8.4+ sigue válido.
    while (($data = fgetcsv($fh, 0, ",", '"', "\\")) !== false) {
        if ($data === [null] || $data === false) {
            continue;
        }
        $rows[] = array_map(static function ($v) {
            return is_string($v) ? $v : (string)$v;
        }, $data);
    }
    fclose($fh);
    return $rows;
}

/**
 * Quita namespaces del XML OpenXML para que SimpleXML lea hijos con ->si / ->row.
 * Los XLSX de Excel suelen declarar xmlns por defecto y sin esto sharedStrings queda vacío.
 */
function mxli_import_strip_xml_ns(string $xml): string
{
    $xml = preg_replace('/\sxmlns(:\w+)?="[^"]*"/', "", $xml) ?? $xml;
    $xml = preg_replace('/([<\/])[a-zA-Z0-9]+:/', '$1', $xml) ?? $xml;
    return $xml;
}

/**
 * Texto de un nodo sharedString <si> (t simple o rich text <r><t>).
 */
function mxli_import_si_text(SimpleXMLElement $si): string
{
    if (isset($si->t)) {
        return (string)$si->t;
    }
    $parts = [];
    if (isset($si->r)) {
        foreach ($si->r as $r) {
            $parts[] = (string)($r->t ?? "");
        }
    }
    // Fallback: cualquier <t> anidado
    if ($parts === []) {
        foreach ($si->xpath(".//t") ?: [] as $t) {
            $parts[] = (string)$t;
        }
    }
    return implode("", $parts);
}

/**
 * Índice de columna A→0, B→1, … AA→26 a partir de ref tipo "B12".
 */
function mxli_import_col_index(string $ref): int
{
    $colLetters = preg_replace("/\d+/", "", $ref) ?? "";
    $colLetters = strtoupper($colLetters);
    $colIdx = 0;
    $len = strlen($colLetters);
    for ($i = 0; $i < $len; $i++) {
        $ch = $colLetters[$i];
        if ($ch < "A" || $ch > "Z") {
            continue;
        }
        $colIdx = $colIdx * 26 + (ord($ch) - 64);
    }
    return max(0, $colIdx - 1);
}

/**
 * Lector mínimo XLSX (primera hoja) vía ZipArchive / OpenXML.
 * Maneja shared strings, namespaces y celdas inlineStr.
 * @return list<list<string>>
 */
function mxli_import_parse_xlsx(string $path): array
{
    if (!class_exists("ZipArchive")) {
        throw new RuntimeException(
            "PHP ZipArchive no está disponible. Guarde el archivo como CSV UTF-8 e intente de nuevo."
        );
    }
    if (!function_exists("simplexml_load_string")) {
        throw new RuntimeException(
            "PHP SimpleXML no está disponible. Guarde el archivo como CSV UTF-8 e intente de nuevo."
        );
    }

    $zip = new ZipArchive();
    if ($zip->open($path) !== true) {
        // OLE/.xls antiguo no es ZIP: mensaje claro
        $fh = @fopen($path, "rb");
        $magic = $fh ? (string)fread($fh, 8) : "";
        if ($fh) {
            fclose($fh);
        }
        if (strncmp($magic, "\xD0\xCF\x11\xE0", 4) === 0) {
            throw new RuntimeException(
                "El archivo parece Excel antiguo (.xls). Guárdelo como .xlsx o CSV UTF-8 e intente de nuevo."
            );
        }
        throw new RuntimeException(
            "No se pudo abrir el archivo XLSX (¿está dañado o es .xls renombrado?). Guárdelo como CSV UTF-8."
        );
    }

    $shared = [];
    $ssXml = $zip->getFromName("xl/sharedStrings.xml");
    if ($ssXml !== false && $ssXml !== "") {
        $sx = @simplexml_load_string(mxli_import_strip_xml_ns($ssXml));
        if ($sx !== false) {
            foreach ($sx->si as $si) {
                $shared[] = mxli_import_si_text($si);
            }
        }
    }

    $sheetPath = "xl/worksheets/sheet1.xml";
    $wbXml = $zip->getFromName("xl/workbook.xml");
    $relsXml = $zip->getFromName("xl/_rels/workbook.xml.rels");
    if ($wbXml !== false && $relsXml !== false) {
        $wb = @simplexml_load_string(mxli_import_strip_xml_ns($wbXml));
        $rels = @simplexml_load_string(mxli_import_strip_xml_ns($relsXml));
        if ($wb !== false && $rels !== false) {
            $firstSheet = null;
            if (isset($wb->sheets->sheet[0])) {
                $firstSheet = $wb->sheets->sheet[0];
            }
            if ($firstSheet !== null) {
                $rid = (string)($firstSheet["id"] ?? "");
                if ($rid === "") {
                    foreach ($firstSheet->attributes() ?: [] as $an => $av) {
                        if (strcasecmp((string)$an, "id") === 0
                            || substr((string)$an, -3) === ":id"
                        ) {
                            $rid = (string)$av;
                            break;
                        }
                    }
                }
                if ($rid !== "" && isset($rels->Relationship)) {
                    foreach ($rels->Relationship as $rel) {
                        if ((string)$rel["Id"] === $rid) {
                            $target = (string)$rel["Target"];
                            if (isset($target[0]) && $target[0] === "/") {
                                $sheetPath = ltrim($target, "/");
                            } else {
                                $sheetPath = "xl/" . ltrim($target, "/");
                            }
                            $sheetPath = preg_replace('#^xl/xl/#', "xl/", $sheetPath) ?? $sheetPath;
                            break;
                        }
                    }
                }
            }
        }
    }

    $sheetXml = $zip->getFromName($sheetPath);
    // Fallback: primera worksheet del zip
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
        throw new RuntimeException("No se encontró la primera hoja del XLSX");
    }

    $sheet = @simplexml_load_string(mxli_import_strip_xml_ns($sheetXml));
    if ($sheet === false) {
        throw new RuntimeException("XLSX inválido o dañado");
    }

    $rowsOut = [];
    if (!isset($sheet->sheetData) || !isset($sheet->sheetData->row)) {
        return [];
    }

    foreach ($sheet->sheetData->row as $row) {
        $cells = [];
        $maxCol = -1;
        if (!isset($row->c)) {
            $rowsOut[] = [];
            continue;
        }
        foreach ($row->c as $c) {
            $ref = (string)($c["r"] ?? "");
            $colIdx = $ref !== "" ? mxli_import_col_index($ref) : ($maxCol + 1);

            $type = (string)($c["t"] ?? "");
            $val = "";
            if ($type === "s") {
                $si = (int)(string)($c->v ?? "-1");
                $val = $shared[$si] ?? "";
            } elseif ($type === "inlineStr") {
                if (isset($c->is)) {
                    $val = mxli_import_si_text($c->is);
                }
            } elseif ($type === "str" || $type === "e") {
                $val = (string)($c->v ?? "");
            } else {
                // Número o fecha serial; o texto sin t= si el valor va en v
                $val = (string)($c->v ?? "");
                if ($val === "" && isset($c->is)) {
                    $val = mxli_import_si_text($c->is);
                }
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
 * Igual que users.php: quitar CHECKs de role y ampliar a VARCHAR(80).
 */
function mxli_import_ensure_schema(PDO $pdo, bool $force = false): void
{
    static $done = false;
    if ($done && !$force) {
        return;
    }

    $pdo->exec("ALTER TABLE app_users ADD COLUMN IF NOT EXISTS expires_at DATE NULL");
    $pdo->exec(
        "ALTER TABLE app_users
         ADD COLUMN IF NOT EXISTS can_view_padron BOOLEAN NOT NULL DEFAULT FALSE"
    );

    $pdo->exec("ALTER TABLE app_users DROP CONSTRAINT IF EXISTS app_users_role_check");

    $pdo->exec(
        "DO \$\$
         DECLARE r record;
         BEGIN
           FOR r IN
             SELECT c.conname
             FROM pg_constraint c
             JOIN pg_attribute a
               ON a.attrelid = c.conrelid
              AND a.attnum = ANY (c.conkey)
             WHERE c.conrelid = 'app_users'::regclass
               AND c.contype = 'c'
               AND a.attname = 'role'
           LOOP
             EXECUTE format('ALTER TABLE app_users DROP CONSTRAINT IF EXISTS %I', r.conname);
           END LOOP;
         END
         \$\$"
    );

    $pdo->exec(
        "ALTER TABLE app_users
         ALTER COLUMN role TYPE VARCHAR(80)
         USING trim(both FROM role::text)"
    );
    $pdo->exec("ALTER TABLE app_users ALTER COLUMN role SET DEFAULT 'consulta'");
    $done = true;
}

function mxli_import_str_contains(string $haystack, string $needle): bool
{
    return $needle === "" || strpos($haystack, $needle) !== false;
}

function mxli_import_is_role_schema_error(Throwable $e): bool
{
    $msg = $e->getMessage();
    return mxli_import_str_contains($msg, "22001")
        || mxli_import_str_contains($msg, "right truncated")
        || mxli_import_str_contains($msg, "value too long")
        || mxli_import_str_contains($msg, "app_users_role_check")
        || mxli_import_str_contains($msg, "check constraint")
        || (mxli_import_str_contains($msg, "role") && mxli_import_str_contains($msg, "violates"));
}

try {
    // --- Plantilla CSV ---
    if (($_SERVER["REQUEST_METHOD"] ?? "GET") === "GET"
        && (isset($_GET["template"]) || ($_GET["action"] ?? "") === "template")
    ) {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        header("Content-Type: text/csv; charset=UTF-8");
        header("Content-Disposition: attachment; filename=\"plantilla_usuarios.csv\"");
        echo "\xEF\xBB\xBF";
        echo "usuario,contraseña,nombre_completo,correo\n";
        echo "ej_usuario,ClaveSegura1,Nombre Ejemplo,ejemplo@dominio.mx\n";
        exit;
    }

    if (($_SERVER["REQUEST_METHOD"] ?? "") !== "POST") {
        mxli_import_json(["ok" => false, "error" => "Método no permitido"], 405);
    }

    $roleInput = trim((string)($_POST["role"] ?? ""));
    // Acepta slug o nombre; Notificadores_recaudacion → notificadores_recaudacion
    $role = $roleInput !== "" ? mxli_resolve_role_slug($roleInput) : null;
    if ($role === null && $roleInput !== "" && function_exists("mxli_normalize_role_slug")) {
        $normalized = mxli_normalize_role_slug($roleInput);
        if ($normalized !== "" && function_exists("mxli_fetch_role") && mxli_fetch_role($normalized) !== null) {
            $role = $normalized;
        }
    }
    if ($role === null) {
        mxli_import_json([
            "ok" => false,
            "error" => "Seleccione un rol válido",
            "detail" => $roleInput !== ""
                ? "No se encontró el rol «{$roleInput}» (pruebe el slug con guiones bajos, p.ej. notificadores_recaudacion)"
                : "Rol vacío",
        ], 400);
    }

    $expiresAt = array_key_exists("expires_at", $_POST)
        ? mxli_normalize_expires_at($_POST["expires_at"])
        : null;

    $roleMeta = function_exists("mxli_fetch_role") ? mxli_fetch_role($role) : null;
    $rolePerms = is_array($roleMeta)
        ? ($roleMeta["permissions"] ?? [])
        : (function_exists("mxli_permissions_for_role") ? mxli_permissions_for_role($role) : []);
    $canViewPadron = in_array("map.padron", $rolePerms, true);

    if (!isset($_FILES["file"]) || !is_array($_FILES["file"])) {
        mxli_import_json(["ok" => false, "error" => "Adjunte un archivo CSV o XLSX"], 400);
    }

    $file = $_FILES["file"];
    $err = (int)($file["error"] ?? UPLOAD_ERR_NO_FILE);
    if ($err === UPLOAD_ERR_INI_SIZE || $err === UPLOAD_ERR_FORM_SIZE) {
        mxli_import_json(["ok" => false, "error" => "El archivo supera el límite permitido (máx. 2 MB)"], 400);
    }
    if ($err !== UPLOAD_ERR_OK) {
        mxli_import_json(["ok" => false, "error" => "Error al subir el archivo (código {$err})"], 400);
    }

    $size = (int)($file["size"] ?? 0);
    if ($size <= 0) {
        mxli_import_json(["ok" => false, "error" => "El archivo está vacío"], 400);
    }
    if ($size > MXLI_IMPORT_MAX_BYTES) {
        mxli_import_json(["ok" => false, "error" => "El archivo supera 2 MB"], 400);
    }

    $tmp = (string)($file["tmp_name"] ?? "");
    $origName = (string)($file["name"] ?? "import.csv");
    $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
    if ($ext === "xls") {
        mxli_import_json([
            "ok" => false,
            "error" => "Formato Excel antiguo (.xls) no soportado. En Excel: Archivo → Guardar como → Libro de Excel (.xlsx) o CSV UTF-8.",
        ], 400);
    }
    if (!in_array($ext, ["csv", "xlsx", "txt"], true)) {
        mxli_import_json([
            "ok" => false,
            "error" => "Formato no soportado. Use CSV UTF-8 o XLSX (Excel → Guardar como).",
        ], 400);
    }

    // Detectar .xls OLE disfrazado de .xlsx/.csv
    if ($ext === "xlsx" || $ext === "csv" || $ext === "txt") {
        $fhMagic = @fopen($tmp, "rb");
        $magic = $fhMagic ? (string)fread($fhMagic, 8) : "";
        if ($fhMagic) {
            fclose($fhMagic);
        }
        if (strncmp($magic, "\xD0\xCF\x11\xE0", 4) === 0) {
            mxli_import_json([
                "ok" => false,
                "error" => "El archivo es Excel antiguo (.xls), no .xlsx/.csv. Guárdelo como .xlsx o CSV UTF-8 e intente de nuevo.",
            ], 400);
        }
    }

    try {
        $grid = $ext === "xlsx" ? mxli_import_parse_xlsx($tmp) : mxli_import_parse_csv($tmp);
    } catch (Throwable $e) {
        mxli_import_json(["ok" => false, "error" => $e->getMessage()], 400);
    }

    if ($grid === []) {
        mxli_import_json(["ok" => false, "error" => "El archivo no tiene filas"], 400);
    }

    // Primera fila no vacía como encabezados; si fila 1 vacía y fila 2 tiene headers, se salta
    $probeHeaders = [];
    foreach ($grid as $probeRow) {
        if (!mxli_import_row_is_empty($probeRow)) {
            $probeHeaders = mxli_import_headers_debug($probeRow);
            break;
        }
    }

    $found = mxli_import_find_header_map($grid);
    if ($found === null) {
        mxli_import_json([
            "ok" => false,
            "error" => "Encabezados inválidos. Se requieren columnas: usuario (o username) y contraseña (o password / contrasena / clave / pass). Opcionales: nombre_completo / full_name / nombre, correo / email.",
            "headers_detected" => $probeHeaders,
            "headers_normalized" => array_map("mxli_import_normalize_header", $probeHeaders),
            "hint" => "Solución rápida: Excel → Guardar como → CSV UTF-8 (delimitado por comas) con columnas usuario,contraseña,nombre_completo,correo",
        ], 400);
    }

    /** @var list<string> $headerRow */
    /** @var array{username:int,password:int,full_name:?int,email:?int} $colMap */
    [$headerRow, $colMap, $headerLineNo] = $found;

    $dataRows = array_slice($grid, 0, MXLI_IMPORT_MAX_ROWS);
    $truncatedHint = count($grid) > MXLI_IMPORT_MAX_ROWS
        ? "Solo se procesaron las primeras " . MXLI_IMPORT_MAX_ROWS . " filas de datos."
        : null;

    $created = 0;
    $skipped = [];
    $seenInFile = [];

    $pdo = mxli_db();
    mxli_import_ensure_schema($pdo);

    $existingStmt = $pdo->query("SELECT lower(username) AS u FROM app_users");
    $existing = [];
    foreach ($existingStmt->fetchAll() as $er) {
        $existing[(string)$er["u"]] = true;
    }

    $insertSql =
        "INSERT INTO app_users (username, password_hash, full_name, email, role, is_active, can_view_padron, expires_at)
         VALUES (:username, :hash, :full_name, :email, :role, " . mxli_import_sql_bool(true) . ", "
        . mxli_import_sql_bool($canViewPadron) . ", :expires_at)
         RETURNING id, username";

    $insert = $pdo->prepare($insertSql);

    $lineNo = $headerLineNo; // número de fila del encabezado en el archivo
    foreach ($dataRows as $row) {
        $lineNo++;
        $username = mxli_import_cell($row, $colMap["username"]);
        $password = mxli_import_cell($row, $colMap["password"]);
        $fullName = mxli_import_cell($row, $colMap["full_name"]);
        $email = mxli_import_cell($row, $colMap["email"]);

        $allEmpty = $username === "" && $password === "" && $fullName === "" && $email === "";
        if ($allEmpty) {
            $skipped[] = ["line" => $lineNo, "username" => "", "reason" => "Fila vacía"];
            continue;
        }

        if ($username === "" || mxli_import_mb_strlen($username) < 3) {
            $skipped[] = [
                "line" => $lineNo,
                "username" => $username,
                "reason" => "Usuario vacío o con menos de 3 caracteres",
            ];
            continue;
        }

        if ($password === "" || strlen($password) < 6) {
            $skipped[] = [
                "line" => $lineNo,
                "username" => $username,
                "reason" => "Contraseña vacía o con menos de 6 caracteres",
            ];
            continue;
        }

        if (!mxli_import_light_email_ok($email)) {
            $skipped[] = [
                "line" => $lineNo,
                "username" => $username,
                "reason" => "Correo electrónico con formato inválido",
            ];
            continue;
        }

        $key = mxli_import_mb_strtolower($username);
        if (isset($seenInFile[$key])) {
            $skipped[] = [
                "line" => $lineNo,
                "username" => $username,
                "reason" => "Usuario duplicado en el archivo (línea " . $seenInFile[$key] . ")",
            ];
            continue;
        }
        if (isset($existing[$key])) {
            $skipped[] = [
                "line" => $lineNo,
                "username" => $username,
                "reason" => "Usuario ya existe en el sistema",
            ];
            continue;
        }

        $seenInFile[$key] = $lineNo;
        $hash = password_hash($password, PASSWORD_DEFAULT);
        if ($hash === false || $hash === "") {
            $skipped[] = [
                "line" => $lineNo,
                "username" => $username,
                "reason" => "No se pudo generar el hash de contraseña",
            ];
            continue;
        }

        $params = [
            "username" => $username,
            "hash" => $hash,
            "full_name" => $fullName,
            "email" => $email,
            "role" => $role,
            "expires_at" => $expiresAt,
        ];

        try {
            try {
                $insert->execute($params);
                $insert->fetch();
            } catch (PDOException $e) {
                if (!mxli_import_is_role_schema_error($e)) {
                    throw $e;
                }
                mxli_import_ensure_schema($pdo, true);
                $insert->execute($params);
                $insert->fetch();
            }
            $created++;
            $existing[$key] = true;
        } catch (PDOException $e) {
            $msg = $e->getMessage();
            $reason = (mxli_import_str_contains($msg, "unique")
                || mxli_import_str_contains($msg, "app_users_username_key"))
                ? "Usuario ya existe en el sistema"
                : (mxli_import_is_role_schema_error($e)
                    ? "Columna role/CHECK: ejecute migrate_custom_roles.php o redeploy users.php"
                    : "Error al insertar");
            $skipped[] = [
                "line" => $lineNo,
                "username" => $username,
                "reason" => $reason,
            ];
        }
    }

    mxli_import_json([
        "ok" => true,
        "created" => $created,
        "skipped_count" => count($skipped),
        "skipped" => $skipped,
        "role" => $role,
        "can_view_padron" => $canViewPadron,
        "truncated" => $truncatedHint,
        "message" => "Importación finalizada: {$created} creados, " . count($skipped) . " omitidos.",
    ]);
} catch (Throwable $e) {
    mxli_import_json([
        "ok" => false,
        "error" => "Error en el servidor al importar usuarios",
        "detail" => $e->getMessage(),
    ], 500);
}
