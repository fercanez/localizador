<?php
declare(strict_types=1);

/**
 * Helpers de importación masiva del padrón (CSV/XLSX → public.padron2026).
 * Mapeo alineado con data/load_padron_sep2026.sql.
 */

require_once __DIR__ . "/padron.php";

/** Columnas destino en public.padron2026 (orden de carga). */
function mxli_padron_import_target_columns(): array
{
    return [
        "clavecatas",
        "nombre_completo",
        "razon_social",
        "delegacion",
        "colonia",
        "calle",
        "numof",
        "numint",
        "letra",
        "zonah",
        "valor",
        "sup_regitrada",
        "sup_fisica",
        "condominio",
        "adeudo2026",
        "adeudototal",
        "superficie_construccion",
        "tasaid",
        "uso_predial",
        "porcentaje",
    ];
}

/**
 * Alias de encabezado CSV/Excel → columna destino.
 * Acepta estilo Excel (CLAVECATASTRAL) y snake_case ya mapeado (clavecatas).
 *
 * @return array<string, list<string>>
 */
function mxli_padron_import_header_aliases(): array
{
    return [
        "clavecatas" => [
            "clavecatas", "clavecata", "clavecatastral", "clave_catastral", "clave",
        ],
        "nombre_completo" => [
            "nombre_completo", "nombrecompleto", "nombredespliegue", "nombre_despliegue",
            "nombrecontribuyente", "nombre_contribuyente", "propietario", "titular", "nombre",
        ],
        "razon_social" => ["razon_social", "razonsocial"],
        "delegacion" => ["delegacion", "delegación"],
        "colonia" => ["colonia"],
        "calle" => ["calle"],
        "numof" => [
            "numof", "numerooficial", "numero_oficial", "numoficial", "numero",
        ],
        "numint" => [
            "numint", "numinterior", "numerointerior", "numero_interior",
            "num_interior", "interior",
        ],
        "letra" => ["letra"],
        "zonah" => ["zonah", "zona", "zonahomo", "zona_homo", "zonahomogenea"],
        "valor" => [
            "valor", "valorm2", "valor_m2", "valor2026", "valorm2",
        ],
        "sup_regitrada" => [
            "sup_regitrada", "sup_registrada", "supregistrada", "supregitrada",
            "superficiedocumental", "superficie_documental", "superficiedoc",
            "superficie_doc", "supdocumental", "sup_documental", "sdoc", "sup",
        ],
        "sup_fisica" => [
            "sup_fisica", "superficiefisica", "superficie_fisica", "supfisica",
        ],
        "condominio" => ["condominio"],
        "adeudo2026" => ["adeudo2026", "adeudo_2026"],
        "adeudototal" => ["adeudototal", "adeudo_total"],
        "superficie_construccion" => [
            "superficie_construccion", "superficieconstruccion", "sup_construccion",
            "sup_const", "supconst",
        ],
        "tasaid" => ["tasaid", "tasa_id", "id_tasa"],
        "uso_predial" => [
            "uso_predial", "usopredial", "uso_pred", "descripcion_uso",
            "descripcion_tasa", "descripcion", "descripción", "descripcio",
            "tipouso", "uso_suelo", "uso",
        ],
        "porcentaje" => ["porcentaje", "porcentaje_tasa", "tasa_millar"],
    ];
}

function mxli_padron_import_normalize_header(string $raw): string
{
    $h = $raw;
    $h = preg_replace('/^\xEF\xBB\xBF/', "", $h) ?? $h;
    $h = preg_replace('/^[\x{FEFF}\x{200B}\x{200C}\x{200D}\x{2060}]+/u', "", $h) ?? $h;
    $h = str_replace(["\xC2\xA0", "\xE2\x80\xAF", "\t"], " ", $h);
    $h = trim($h);
    if (function_exists("mb_strtolower")) {
        $h = mb_strtolower($h, "UTF-8");
    } else {
        $h = strtolower($h);
    }

    if (class_exists("Normalizer")) {
        $n = Normalizer::normalize($h, Normalizer::FORM_KD);
        if (is_string($n) && $n !== "") {
            $h = $n;
        }
    }
    $h = preg_replace('/\p{Mn}+/u', "", $h) ?? $h;
    $h = strtr($h, [
        "á" => "a", "à" => "a", "ä" => "a", "â" => "a", "ã" => "a",
        "é" => "e", "è" => "e", "ë" => "e", "ê" => "e",
        "í" => "i", "ì" => "i", "ï" => "i", "î" => "i",
        "ó" => "o", "ò" => "o", "ö" => "o", "ô" => "o", "õ" => "o",
        "ú" => "u", "ù" => "u", "ü" => "u", "û" => "u",
        "ñ" => "n", "ç" => "c",
    ]);

    $h = preg_replace('/[\s\-]+/', "_", $h) ?? $h;
    $h = preg_replace('/_+/', "_", $h) ?? $h;
    $h = preg_replace('/[^a-z0-9_]/', "", $h) ?? $h;
    return trim($h, "_");
}

/**
 * Encoding → UTF-8 (misma lógica que users-import.php).
 */
function mxli_padron_import_to_utf8(string $raw): string
{
    if ($raw === "") {
        return "";
    }
    if (strncmp($raw, "\xEF\xBB\xBF", 3) === 0) {
        return substr($raw, 3);
    }
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
    if (function_exists("mb_check_encoding") && mb_check_encoding($raw, "UTF-8")) {
        return $raw;
    }
    if (!function_exists("mb_check_encoding")
        && (preg_match('//u', $raw) === 1 || preg_match('/^[\x00-\x7F]*$/', $raw) === 1)
    ) {
        return $raw;
    }
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
 * Detecta si un buffer parece Windows-1252 / Latin-1 (no UTF-8 válido con bytes altos).
 */
function mxli_padron_import_detect_encoding(string $sample): string
{
    if (strncmp($sample, "\xEF\xBB\xBF", 3) === 0) {
        return "UTF-8";
    }
    if (strncmp($sample, "\xFF\xFE", 2) === 0 || strncmp($sample, "\xFE\xFF", 2) === 0) {
        return "UTF-16";
    }
    if (function_exists("mb_check_encoding") && mb_check_encoding($sample, "UTF-8")) {
        // Heurística: muchos bytes 0x80–0x9F suelen ser Win-1252 mal leídos como UTF-8
        // Si es UTF-8 válido, confiar.
        return "UTF-8";
    }
    return "Windows-1252";
}

/**
 * @param list<string> $headers
 * @return array{map: array<string, int>, missing: list<string>}|null
 *         map: targetCol => sourceIndex. null si falta clavecatas.
 */
function mxli_padron_import_map_headers(array $headers): ?array
{
    $aliases = mxli_padron_import_header_aliases();
    $byNorm = [];
    foreach ($headers as $idx => $raw) {
        $norm = mxli_padron_import_normalize_header((string)$raw);
        if ($norm === "") {
            continue;
        }
        if (!isset($byNorm[$norm])) {
            $byNorm[$norm] = (int)$idx;
        }
    }

    $map = [];
    foreach ($aliases as $target => $aliasList) {
        foreach ($aliasList as $alias) {
            $want = mxli_padron_import_normalize_header($alias);
            if ($want !== "" && isset($byNorm[$want])) {
                $map[$target] = $byNorm[$want];
                break;
            }
        }
    }

    if (!isset($map["clavecatas"])) {
        return null;
    }

    $missing = [];
    foreach (mxli_padron_import_target_columns() as $col) {
        if (!isset($map[$col])) {
            $missing[] = $col;
        }
    }

    return ["map" => $map, "missing" => $missing];
}

/**
 * Limpia celdas del CSV catastral: NULL, asteriscos y miles con coma (1,890.00).
 * No toca textos (nombres, claves, interiores tipo 4B / INT).
 */
function mxli_padron_import_cell_clean(string $value): string
{
    $v = trim($value);
    $v = trim($v, " \t\n\r\0\x0B*");
    if ($v === "" || strcasecmp($v, "NULL") === 0) {
        return "";
    }
    $compact = str_replace([" ", "\xC2\xA0"], "", $v);
    $compact = trim($compact, "*");
    if (preg_match('/^-?\d{1,3}(?:,\d{3})+(?:\.\d+)?$/', $compact) === 1
        || preg_match('/^-?\d+(?:\.\d+)?$/', $compact) === 1
    ) {
        return str_replace(",", "", $compact);
    }
    return $v;
}

/**
 * Nombre real en padron2026 para una columna destino del loader.
 *
 * @param array<string, string> $liveByNorm lower(col) => nombre original
 */
function mxli_padron_import_resolve_live_column(string $target, array $liveByNorm): ?string
{
    $want = strtolower($target);
    if (isset($liveByNorm[$want])) {
        return $liveByNorm[$want];
    }
    $fallbacks = [
        "valor" => ["valor/m2", "valorm2", "valor_m2", "valor2026"],
        "sup_regitrada" => ["sup_registrada", "sup_documental"],
        "superficie_construccion" => ["sup_const", "sup_construccion"],
        "porcentaje" => ["tasa_millar", "porcentaje_tasa"],
        "uso_predial" => ["descripcion_uso", "descripcion"],
    ];
    foreach ($fallbacks[$want] ?? [] as $alias) {
        if (isset($liveByNorm[strtolower($alias)])) {
            return $liveByNorm[strtolower($alias)];
        }
    }
    return null;
}

/** Identificadores PG de carga (permite valor/m2). */
function mxli_padron_import_quote_ident(string $name): string
{
    if (!preg_match('/^[A-Za-z_][A-Za-z0-9_\/]*$/', $name)) {
        throw new InvalidArgumentException("Identificador inválido");
    }
    return '"' . str_replace('"', '""', $name) . '"';
}

/**
 * Tipos PG de padron2026: lower(column_name) => data_type.
 *
 * @return array<string, string>
 */
function mxli_padron_import_live_column_types(PDO $pdo): array
{
    $parts = mxli_padron_table_parts();
    $stmt = $pdo->prepare(
        "SELECT column_name, data_type
         FROM information_schema.columns
         WHERE lower(table_schema) = lower(:schema)
           AND lower(table_name) = lower(:table)"
    );
    $stmt->execute(["schema" => $parts["schema"], "table" => $parts["table"]]);
    $out = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $name = strtolower((string)($row["column_name"] ?? ""));
        if ($name !== "") {
            $out[$name] = strtolower((string)($row["data_type"] ?? ""));
        }
    }
    return $out;
}

function mxli_padron_import_is_numeric_pg_type(string $dataType): bool
{
    $t = strtolower(trim($dataType));
    return in_array($t, [
        "numeric",
        "decimal",
        "integer",
        "bigint",
        "smallint",
        "real",
        "double precision",
        "money",
        "int",
        "int2",
        "int4",
        "int8",
        "float4",
        "float8",
    ], true);
}

/**
 * Columnas destino que suelen ser numeric si information_schema no responde.
 *
 * @return list<string>
 */
function mxli_padron_import_numeric_fallback_names(): array
{
    return [
        "numof",
        "numoficial",
        "numerooficial",
        "numint",
        "valor",
        "valorm2",
        "valor/m2",
        "valor_m2",
        "valor2026",
        "sup_regitrada",
        "sup_registrada",
        "sup_fisica",
        "adeudo2026",
        "adeudototal",
        "superficie_construccion",
        "sup_const",
        "porcentaje",
        "tasa_millar",
    ];
}

function mxli_padron_import_needs_numeric_cast(
    string $stgCol,
    string $liveCol,
    string $liveType
): bool {
    if (mxli_padron_import_is_numeric_pg_type($liveType)) {
        return true;
    }
    if ($liveType !== "") {
        return false;
    }
    $fallback = mxli_padron_import_numeric_fallback_names();
    return in_array(strtolower($stgCol), $fallback, true)
        || in_array(strtolower($liveCol), $fallback, true);
}

/**
 * SELECT expr desde staging TEXT. Numéricos: NULLIF + replace + ::numeric.
 * "S/N", "_", vacío y no-números → NULL (evita fallo de cast).
 */
function mxli_padron_import_select_cast_expr(string $stgCol, bool $asNumeric): string
{
    $q = '"' . str_replace('"', '""', $stgCol) . '"';
    if (!$asNumeric) {
        return "NULLIF(btrim({$q}::text), '')";
    }
    $cleaned = "replace(replace(btrim({$q}::text), ',', ''), ' ', '')";
    return "CASE WHEN {$cleaned} ~ '^-?[0-9]+([.][0-9]+)?$' "
        . "THEN NULLIF({$cleaned}, '')::numeric ELSE NULL END";
}

/**
 * Escapa un campo CSV (UTF-8).
 */
function mxli_padron_import_csv_escape(string $value): string
{
    if ($value === "") {
        return "";
    }
    if (strpbrk($value, ",\"\r\n") !== false) {
        return '"' . str_replace('"', '""', $value) . '"';
    }
    return $value;
}

/**
 * Asegura tabla de metadatos en la BD de la app (no geonode).
 * Debe usarse siempre con mxli_db() → localizacion_app (o la BD de la app).
 */
function mxli_padron_import_ensure_meta(PDO $appPdo): void
{
    $appPdo->exec(
        "CREATE TABLE IF NOT EXISTS app_padron_import_meta (
            id SMALLINT PRIMARY KEY DEFAULT 1 CHECK (id = 1),
            imported_at TIMESTAMPTZ NULL,
            imported_by VARCHAR(80) NULL,
            filename TEXT NULL,
            rows_before BIGINT NULL,
            rows_after BIGINT NULL,
            claves_nuevas BIGINT NULL,
            claves_cambiadas BIGINT NULL,
            claves_eliminadas BIGINT NULL,
            claves_iguales BIGINT NULL,
            backup_table TEXT NULL,
            note TEXT NULL
         )"
    );
    // Migración suave si existía el esquema anterior
    $appPdo->exec("ALTER TABLE app_padron_import_meta ADD COLUMN IF NOT EXISTS imported_at TIMESTAMPTZ NULL");
    $appPdo->exec("ALTER TABLE app_padron_import_meta ADD COLUMN IF NOT EXISTS imported_by VARCHAR(80) NULL");
    $appPdo->exec("ALTER TABLE app_padron_import_meta ADD COLUMN IF NOT EXISTS filename TEXT NULL");
    $appPdo->exec("ALTER TABLE app_padron_import_meta ADD COLUMN IF NOT EXISTS rows_before BIGINT NULL");
    $appPdo->exec("ALTER TABLE app_padron_import_meta ADD COLUMN IF NOT EXISTS rows_after BIGINT NULL");
    $appPdo->exec("ALTER TABLE app_padron_import_meta ADD COLUMN IF NOT EXISTS claves_nuevas BIGINT NULL");
    $appPdo->exec("ALTER TABLE app_padron_import_meta ADD COLUMN IF NOT EXISTS claves_cambiadas BIGINT NULL");
    $appPdo->exec("ALTER TABLE app_padron_import_meta ADD COLUMN IF NOT EXISTS claves_eliminadas BIGINT NULL");
    $appPdo->exec("ALTER TABLE app_padron_import_meta ADD COLUMN IF NOT EXISTS claves_iguales BIGINT NULL");
    $appPdo->exec("ALTER TABLE app_padron_import_meta ADD COLUMN IF NOT EXISTS backup_table TEXT NULL");
    $appPdo->exec("ALTER TABLE app_padron_import_meta ADD COLUMN IF NOT EXISTS note TEXT NULL");
    // Fila singleton (imported_at NULL = aún no hubo carga desde el panel)
    $appPdo->exec(
        "INSERT INTO app_padron_import_meta (id) VALUES (1)
         ON CONFLICT (id) DO NOTHING"
    );
}

/**
 * @return array{
 *   imported_at:?string, imported_by:?string, filename:?string,
 *   rows_before:?int, rows_after:?int,
 *   claves_nuevas:?int, claves_cambiadas:?int, claves_eliminadas:?int, claves_iguales:?int,
 *   backup_table:?string
 * }|null
 */
function mxli_padron_import_read_meta(PDO $appPdo): ?array
{
    mxli_padron_import_ensure_meta($appPdo);
    $stmt = $appPdo->query(
        "SELECT imported_at, imported_by, filename,
                rows_before, rows_after,
                claves_nuevas, claves_cambiadas, claves_eliminadas, claves_iguales,
                backup_table
         FROM app_padron_import_meta WHERE id = 1"
    );
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!is_array($row)) {
        return null;
    }
    $intOrNull = static function (mixed $v): ?int {
        return $v === null || $v === "" ? null : (int)$v;
    };
    $importedAt = isset($row["imported_at"]) && $row["imported_at"] !== null && $row["imported_at"] !== ""
        ? (string)$row["imported_at"]
        : null;
    if ($importedAt === null) {
        return null;
    }
    return [
        "imported_at" => $importedAt,
        "imported_by" => isset($row["imported_by"]) ? (string)$row["imported_by"] : null,
        "filename" => isset($row["filename"]) ? (string)$row["filename"] : null,
        "rows_before" => $intOrNull($row["rows_before"] ?? null),
        "rows_after" => $intOrNull($row["rows_after"] ?? null),
        "claves_nuevas" => $intOrNull($row["claves_nuevas"] ?? null),
        "claves_cambiadas" => $intOrNull($row["claves_cambiadas"] ?? null),
        "claves_eliminadas" => $intOrNull($row["claves_eliminadas"] ?? null),
        "claves_iguales" => $intOrNull($row["claves_iguales"] ?? null),
        "backup_table" => isset($row["backup_table"]) ? (string)$row["backup_table"] : null,
    ];
}

/**
 * @param array{
 *   rows_before:int, rows_after:int,
 *   claves_nuevas:int, claves_cambiadas:int, claves_eliminadas:int, claves_iguales:int,
 *   backup_table?:?string, note?:string
 * } $stats
 */
function mxli_padron_import_write_meta(
    PDO $appPdo,
    string $username,
    string $filename,
    array $stats
): void {
    mxli_padron_import_ensure_meta($appPdo);
    $stmt = $appPdo->prepare(
        "INSERT INTO app_padron_import_meta (
            id, imported_at, imported_by, filename,
            rows_before, rows_after,
            claves_nuevas, claves_cambiadas, claves_eliminadas, claves_iguales,
            backup_table, note
         ) VALUES (
            1, NOW(), :uby, :fn,
            :rb, :ra, :cn, :cc, :ce, :ci, :bk, :note
         )
         ON CONFLICT (id) DO UPDATE SET
           imported_at = EXCLUDED.imported_at,
           imported_by = EXCLUDED.imported_by,
           filename = EXCLUDED.filename,
           rows_before = EXCLUDED.rows_before,
           rows_after = EXCLUDED.rows_after,
           claves_nuevas = EXCLUDED.claves_nuevas,
           claves_cambiadas = EXCLUDED.claves_cambiadas,
           claves_eliminadas = EXCLUDED.claves_eliminadas,
           claves_iguales = EXCLUDED.claves_iguales,
           backup_table = EXCLUDED.backup_table,
           note = EXCLUDED.note"
    );
    $stmt->execute([
        "uby" => $username,
        "fn" => $filename,
        "rb" => (int)$stats["rows_before"],
        "ra" => (int)$stats["rows_after"],
        "cn" => (int)$stats["claves_nuevas"],
        "cc" => (int)$stats["claves_cambiadas"],
        "ce" => (int)$stats["claves_eliminadas"],
        "ci" => (int)$stats["claves_iguales"],
        "bk" => $stats["backup_table"] ?? null,
        "note" => (string)($stats["note"] ?? ""),
    ]);
}

/**
 * Campos usados para detectar “clave cambiada” (mismo clavecatas, distinto contenido).
 *
 * @return list<string>
 */
function mxli_padron_import_diff_fields(): array
{
    return [
        "nombre_completo",
        "razon_social",
        "colonia",
        "calle",
        "numof",
        "numint",
        "letra",
        "uso_predial",
        "sup_regitrada",
        "delegacion",
        "zonah",
    ];
}

/**
 * Conteos y permisos actuales sobre padron2026.
 * El conteo se obtiene primero; fallos de privilege/columnas no lo anulan.
 *
 * @return array{row_count:int, can_insert:bool, can_truncate:bool, can_create:bool, columns:list<string>}
 */
function mxli_padron_import_status(PDO $geoPdo): array
{
    $qualified = mxli_padron_qualified();
    $count = (int)$geoPdo->query("SELECT count(*) FROM {$qualified}")->fetchColumn();

    $cols = [];
    try {
        $cols = mxli_padron_column_names($geoPdo);
    } catch (Throwable $e) {
        // El conteo ya es válido aunque information_schema falle.
    }

    $canInsert = true;
    $canTruncate = true;
    $canCreate = false;
    try {
        $chk = $geoPdo->query(
            "SELECT
               has_table_privilege(current_user, 'public.padron2026', 'INSERT') AS can_insert,
               has_table_privilege(current_user, 'public.padron2026', 'TRUNCATE') AS can_truncate,
               has_schema_privilege(current_user, 'public', 'CREATE') AS can_create"
        )->fetch(PDO::FETCH_ASSOC);
        if (is_array($chk)) {
            $canInsert = filter_var($chk["can_insert"] ?? false, FILTER_VALIDATE_BOOLEAN);
            $canTruncate = filter_var($chk["can_truncate"] ?? false, FILTER_VALIDATE_BOOLEAN);
            $canCreate = filter_var($chk["can_create"] ?? false, FILTER_VALIDATE_BOOLEAN);
        }
    } catch (Throwable $e) {
        // Si falla el check, se intentará en la carga.
    }

    return [
        "row_count" => $count,
        "can_insert" => $canInsert,
        "can_truncate" => $canTruncate,
        "can_create" => $canCreate,
        "columns" => $cols,
    ];
}

/**
 * Conserva la última fila de cada clavecatas en staging (ctid más alto = última del CSV).
 *
 * @return array{rows_before:int, rows_after:int, duplicates_removed:int}
 */
function mxli_padron_import_dedup_staging(PDO $pdo, string $stgName): array
{
    if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $stgName)) {
        throw new InvalidArgumentException("Tabla staging inválida");
    }

    $before = (int)$pdo->query("SELECT count(*) FROM {$stgName}")->fetchColumn();
    $pdo->exec(
        "DELETE FROM {$stgName} a
         USING {$stgName} b
         WHERE NULLIF(btrim(a.clavecatas), '') IS NOT NULL
           AND btrim(a.clavecatas) = btrim(b.clavecatas)
           AND a.ctid < b.ctid"
    );
    $after = (int)$pdo->query("SELECT count(*) FROM {$stgName}")->fetchColumn();

    return [
        "rows_before" => $before,
        "rows_after" => $after,
        "duplicates_removed" => max(0, $before - $after),
    ];
}
