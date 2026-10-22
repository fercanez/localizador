<?php
declare(strict_types=1);

require_once __DIR__ . "/db.php";

/**
 * @return array{schema:string,table:string}
 */
function mxli_padron_table_parts(): array
{
    $raw = mxli_config("padron_table", "public.padron2026");
    $raw = trim($raw) !== "" ? trim($raw) : "public.padron2026";
    if (str_contains($raw, ".")) {
        [$schema, $table] = explode(".", $raw, 2);
    } else {
        $schema = "public";
        $table = $raw;
    }

    $schema = preg_replace("/[^A-Za-z0-9_]/", "", $schema) ?? "public";
    $table = preg_replace("/[^A-Za-z0-9_]/", "", $table) ?? "padron2026";
    if ($schema === "") {
        $schema = "public";
    }
    if ($table === "") {
        $table = "padron2026";
    }

    return ["schema" => $schema, "table" => $table];
}

/**
 * Quote a PostgreSQL identifier (preserves mixed/upper case from information_schema).
 */
function mxli_padron_quote_ident(string $name): string
{
    if (!preg_match("/^[A-Za-z_][A-Za-z0-9_]*$/", $name)) {
        throw new InvalidArgumentException("Identificador inválido");
    }
    return '"' . str_replace('"', '""', $name) . '"';
}

function mxli_padron_qualified(): string
{
    $parts = mxli_padron_table_parts();
    return mxli_padron_quote_ident($parts["schema"]) . "." . mxli_padron_quote_ident($parts["table"]);
}

/**
 * Normalize column/key names for matching: NOMBRE_DESPLIEGUE / "Nombre Despliegue" → nombredespliegue.
 */
function mxli_padron_norm_key(string $name): string
{
    return strtolower(preg_replace("/[^A-Za-z0-9]/", "", $name) ?? "");
}

/**
 * Original column names as stored in Postgres (case preserved).
 *
 * @return list<string>
 */
function mxli_padron_column_names(PDO $pdo): array
{
    static $cache = null;
    if (is_array($cache)) {
        return $cache;
    }

    $parts = mxli_padron_table_parts();
    // information_schema stores unquoted names lowercased; quoted mixed-case as created.
    $stmt = $pdo->prepare(
        "SELECT column_name
         FROM information_schema.columns
         WHERE lower(table_schema) = lower(:schema) AND lower(table_name) = lower(:table)
         ORDER BY ordinal_position"
    );
    $stmt->execute(["schema" => $parts["schema"], "table" => $parts["table"]]);
    $cache = [];
    foreach ($stmt->fetchAll() as $row) {
        $name = (string)($row["column_name"] ?? "");
        if ($name !== "") {
            $cache[] = $name;
        }
    }

    if ($cache === []) {
        throw new RuntimeException("No se encontró la tabla " . mxli_padron_qualified() . " en geonode_data.");
    }

    return $cache;
}

/**
 * @param list<string> $columns Original PG column names
 * @param list<string> $aliases Desired names (any case)
 * @return string|null Original column name
 */
function mxli_padron_pick_column(array $columns, array $aliases): ?string
{
    $byNorm = [];
    foreach ($columns as $col) {
        $norm = mxli_padron_norm_key($col);
        if ($norm !== "" && !isset($byNorm[$norm])) {
            $byNorm[$norm] = $col;
        }
    }

    foreach ($aliases as $alias) {
        $key = mxli_padron_norm_key($alias);
        if ($key !== "" && isset($byNorm[$key])) {
            return $byNorm[$key];
        }
    }

    // Prefijo / truncado (p.ej. shapefile 10 chars: NOMBREDESP, DESCRIPCIO).
    foreach ($aliases as $alias) {
        $want = mxli_padron_norm_key($alias);
        if (strlen($want) < 8) {
            continue;
        }
        $best = null;
        $bestLen = 0;
        foreach ($byNorm as $norm => $orig) {
            if (strlen($norm) < 8) {
                continue;
            }
            $match =
                str_starts_with($norm, $want)
                || str_starts_with($want, $norm);
            if (!$match) {
                continue;
            }
            // Preferir la columna más específica (nombre más largo).
            if (strlen($norm) > $bestLen) {
                $best = $orig;
                $bestLen = strlen($norm);
            }
        }
        if ($best !== null) {
            return $best;
        }
    }

    return null;
}

/**
 * Aliases for each logical padron field (Excel + variantes GeoNode/shapefile).
 *
 * @return array<string, list<string>>
 */
function mxli_padron_field_aliases(): array
{
    return [
        // Esquema real catastro (padron2026): clavecatas, nombre_completo, colonia,
        // calle, numof, numint, letra, delegacion, razon_social, sup_registrada.
        "clave" => [
            "clavecatas",
            "clavecata",
            "clavecatastral",
            "clave_catastral",
            "clave",
        ],
        "nombre" => [
            "nombre_completo",
            "nombrecompleto",
            "nombredespliegue",
            "nombre_despliegue",
            "nombredesp",
            "nombredesplie",
            "nombrecontribuyente",
            "nombre_contribuyente",
            "contribuyente",
            "propietario",
            "titular",
            "nombre",
        ],
        "razon_social" => [
            "razon_social",
            "razonsocial",
        ],
        "delegacion" => ["delegacion", "delegación"],
        "colonia" => ["colonia"],
        "calle" => ["calle"],
        "numero" => [
            "numof",
            "numerooficial",
            "numero_oficial",
            "numoficial",
            "numero",
        ],
        "interior" => [
            "numint",
            "numerointerior",
            "numero_interior",
            "interior",
        ],
        "letra" => ["letra"],
        "zona" => [
            "zonah",
            "zona",
        ],
        // padron2026 usa uso_predial; otros esquemas pueden traer descripcion/tasa.
        "descripcion" => [
            "uso_predial",
            "usopredial",
            "uso_pred",
            "descripcion",
            "descripción",
            "descripcio",
            "tasa",
            "tipouso",
            "uso_suelo",
            "uso",
        ],
        "superficie_documental" => [
            "sup_registrada",
            "sup_regitrada", // typo observado en datos
            "supregistrada",
            "supregitrada",
            "superficiedocumental",
            "superficie_documental",
            "superficiedoc",
            "supdocumental",
            "sup_documental",
            "areadocumental",
            "sdoc",
            "sup",
        ],
    ];
}

/**
 * @return array{clave:string,nombre:?string,razon_social:?string,delegacion:?string,colonia:?string,calle:?string,numero:?string,interior:?string,letra:?string,zona:?string,descripcion:?string,superficie_documental:?string} Column map; public row also exposes uso_predial.
 */
function mxli_padron_field_map(PDO $pdo): array
{
    $cols = mxli_padron_column_names($pdo);
    $aliases = mxli_padron_field_aliases();

    $clave = mxli_padron_pick_column($cols, $aliases["clave"]);
    if ($clave === null) {
        throw new RuntimeException(
            "padron2026 no tiene columna de clave catastral. Columnas: " . implode(", ", $cols)
        );
    }

    return [
        "clave" => $clave,
        "nombre" => mxli_padron_pick_column($cols, $aliases["nombre"]),
        "razon_social" => mxli_padron_pick_column($cols, $aliases["razon_social"]),
        "delegacion" => mxli_padron_pick_column($cols, $aliases["delegacion"]),
        "colonia" => mxli_padron_pick_column($cols, $aliases["colonia"]),
        "calle" => mxli_padron_pick_column($cols, $aliases["calle"]),
        "numero" => mxli_padron_pick_column($cols, $aliases["numero"]),
        "interior" => mxli_padron_pick_column($cols, $aliases["interior"]),
        "letra" => mxli_padron_pick_column($cols, $aliases["letra"]),
        "zona" => mxli_padron_pick_column($cols, $aliases["zona"]),
        "descripcion" => mxli_padron_pick_column($cols, $aliases["descripcion"]),
        "superficie_documental" => mxli_padron_pick_column($cols, $aliases["superficie_documental"]),
    ];
}

function mxli_normalize_clave_catastral(string $clave): string
{
    return strtoupper(preg_replace("/\s+/", "", $clave) ?? "");
}

/**
 * Scalar → trimmed string (handles numeric PG types from PDO).
 */
function mxli_padron_stringify(mixed $value): string
{
    if ($value === null || is_bool($value)) {
        return "";
    }
    if (is_float($value) || is_int($value)) {
        // Evitar notación científica / trailing .0 innecesarios en enteros.
        if (is_float($value) && floor($value) === $value) {
            return (string)(int)$value;
        }
        return trim((string)$value);
    }
    $s = trim((string)$value);
    if ($s === "" || strcasecmp($s, "NULL") === 0) {
        return "";
    }
    return $s;
}

/**
 * Index a PDO row by normalized key (skips numeric FETCH_BOTH indexes).
 *
 * @return array<string, mixed>
 */
function mxli_padron_row_index(array $row): array
{
    $out = [];
    foreach ($row as $key => $value) {
        if (is_int($key)) {
            continue;
        }
        $norm = mxli_padron_norm_key((string)$key);
        if ($norm === "") {
            continue;
        }
        // Preferir el primer valor no vacío si hay colisión.
        if (!array_key_exists($norm, $out) || mxli_padron_stringify($out[$norm]) === "") {
            $out[$norm] = $value;
        }
    }
    return $out;
}

/**
 * Case-/punctuation-insensitive get from a PDO row.
 */
function mxli_padron_row_value(array $row, ?string $col): string
{
    if ($col === null || $col === "") {
        return "";
    }
    if (array_key_exists($col, $row)) {
        return mxli_padron_stringify($row[$col]);
    }
    $idx = mxli_padron_row_index($row);
    $want = mxli_padron_norm_key($col);
    if ($want !== "" && array_key_exists($want, $idx)) {
        return mxli_padron_stringify($idx[$want]);
    }
    return "";
}

/**
 * Try several aliases against the row itself (not only the schema map).
 * Prefer non-empty values; supports truncated column names.
 *
 * @param list<string> $aliases
 */
function mxli_padron_row_first(array $row, array $aliases): string
{
    $idx = mxli_padron_row_index($row);

    foreach ($aliases as $alias) {
        $want = mxli_padron_norm_key($alias);
        if ($want !== "" && array_key_exists($want, $idx)) {
            $val = mxli_padron_stringify($idx[$want]);
            if ($val !== "") {
                return $val;
            }
        }
    }

    foreach ($aliases as $alias) {
        $want = mxli_padron_norm_key($alias);
        if (strlen($want) < 8) {
            continue;
        }
        $bestVal = "";
        $bestLen = 0;
        foreach ($idx as $norm => $raw) {
            if (strlen($norm) < 8) {
                continue;
            }
            if (!(str_starts_with($norm, $want) || str_starts_with($want, $norm))) {
                continue;
            }
            $val = mxli_padron_stringify($raw);
            if ($val === "") {
                continue;
            }
            if (strlen($norm) > $bestLen) {
                $bestVal = $val;
                $bestLen = strlen($norm);
            }
        }
        if ($bestVal !== "") {
            return $bestVal;
        }
    }

    // Último recurso: alias exacto aunque esté vacío (para no inventar datos).
    foreach ($aliases as $alias) {
        $want = mxli_padron_norm_key($alias);
        if ($want !== "" && array_key_exists($want, $idx)) {
            return mxli_padron_stringify($idx[$want]);
        }
    }

    return "";
}

/**
 * @return array<string,string>
 */
function mxli_padron_public_row(array $row, array $map): array
{
    $aliases = mxli_padron_field_aliases();

    $getMapped = static function (string $key) use ($row, $map, $aliases): string {
        $fromMap = mxli_padron_row_value($row, $map[$key] ?? null);
        if ($fromMap !== "") {
            return $fromMap;
        }
        return mxli_padron_row_first($row, $aliases[$key] ?? []);
    };

    $numero = trim($getMapped("numero") . " " . $getMapped("letra"));
    $interior = $getMapped("interior");
    $calle = $getMapped("calle");
    $domicilio = trim($calle . ($numero !== "" ? " " . $numero : "") . ($interior !== "" ? " Int. " . $interior : ""));

    // Preferir nombre_completo; si vacío, razon_social.
    $nombreCompleto = $getMapped("nombre");
    $razonSocial = mxli_padron_row_value($row, $map["razon_social"] ?? null);
    if ($razonSocial === "") {
        $razonSocial = mxli_padron_row_first($row, $aliases["razon_social"] ?? []);
    }
    $propietario = $nombreCompleto !== "" ? $nombreCompleto : $razonSocial;
    $usoPredial = $getMapped("descripcion");
    $supDoc = $getMapped("superficie_documental");

    return [
        "clave" => mxli_normalize_clave_catastral($getMapped("clave")),
        "propietario" => $propietario,
        "nombre_completo" => $nombreCompleto,
        // Alias explícitos por si algún cliente lee la clave Excel.
        "nombredespliegue" => $propietario,
        "razon_social" => $razonSocial,
        "delegacion" => $getMapped("delegacion"),
        "colonia" => $getMapped("colonia"),
        "calle" => $calle,
        "numero_oficial" => $getMapped("numero"),
        "numero_interior" => $interior,
        "letra" => $getMapped("letra"),
        "zona" => $getMapped("zona"),
        "uso_predial" => $usoPredial,
        "descripcion" => $usoPredial,
        "superficie_documental" => $supDoc,
        "superficiedocumental" => $supDoc,
        "domicilio" => $domicilio,
    ];
}

function mxli_padron_lookup_by_clave(string $clave): ?array
{
    $clave = mxli_normalize_clave_catastral($clave);
    if ($clave === "" || !preg_match("/^[A-Z0-9]+$/", $clave)) {
        return null;
    }

    $pdo = mxli_geonode_db();
    $map = mxli_padron_field_map($pdo);
    $claveCol = mxli_padron_quote_ident($map["clave"]);
    $qualified = mxli_padron_qualified();
    $normExpr = "replace(replace(upper(trim({$claveCol}::text)), ' ', ''), '-', '')";

    // SELECT * + remap: más tolerante a mayúsculas/espacios/guiones.
    $sql = "SELECT * FROM {$qualified}
            WHERE {$normExpr} = :clave
            LIMIT 1";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(["clave" => $clave]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!is_array($row)) {
        // Fallback: prefijo exacto (por si el padrón trae sufijos)
        $sql2 = "SELECT * FROM {$qualified}
                 WHERE {$normExpr} LIKE :clave_like
                 ORDER BY length({$normExpr}) ASC
                 LIMIT 1";
        $stmt2 = $pdo->prepare($sql2);
        $stmt2->execute(["clave_like" => $clave . "%"]);
        $row = $stmt2->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row)) {
            return null;
        }
    }

    return mxli_padron_public_row($row, $map);
}

/**
 * Longitud de string compatible sin mbstring.
 */
function mxli_padron_strlen(string $s): int
{
    return function_exists("mb_strlen") ? (int)mb_strlen($s, "UTF-8") : strlen($s);
}

/**
 * Mayúsculas compatible sin mbstring.
 */
function mxli_padron_upper(string $s): string
{
    if (function_exists("mb_strtoupper")) {
        return mb_strtoupper($s, "UTF-8");
    }
    return strtoupper($s);
}

/**
 * Fila pública para búsquedas (omite propietario si $includeOwner = false).
 *
 * @return array<string,string>
 */
function mxli_padron_public_row_search(array $row, array $map, bool $includeOwner = false): array
{
    $full = mxli_padron_public_row($row, $map);
    $out = [
        "clave" => (string)($full["clave"] ?? ""),
        "calle" => (string)($full["calle"] ?? ""),
        "numero_oficial" => (string)($full["numero_oficial"] ?? ""),
        "letra" => (string)($full["letra"] ?? ""),
        "numero_interior" => (string)($full["numero_interior"] ?? ""),
        "colonia" => (string)($full["colonia"] ?? ""),
        "domicilio" => (string)($full["domicilio"] ?? ""),
        "delegacion" => (string)($full["delegacion"] ?? ""),
    ];
    if ($includeOwner) {
        $out["propietario"] = (string)($full["propietario"] ?? "");
        $out["nombre_completo"] = (string)($full["nombre_completo"] ?? "");
        $out["nombredespliegue"] = (string)($full["nombredespliegue"] ?? "");
        $out["razon_social"] = (string)($full["razon_social"] ?? "");
    }
    return $out;
}

/**
 * @return list<array<string,string>>
 */
function mxli_padron_search_by_nombre(string $nombre, int $limit = 40): array
{
    $nombre = trim(preg_replace("/\s+/u", " ", $nombre) ?? "");
    if (mxli_padron_strlen($nombre) < 3) {
        return [];
    }

    $pdo = mxli_geonode_db();
    $map = mxli_padron_field_map($pdo);
    if ($map["nombre"] === null && ($map["razon_social"] ?? null) === null) {
        return [];
    }

    $qualified = mxli_padron_qualified();
    $limit = min(80, max(1, $limit));

    // Tokens: "GARCIA LOPEZ" exige ambas partes (orden flexible)
    $tokens = [];
    foreach (preg_split("/\s+/u", mxli_padron_upper($nombre)) ?: [] as $t) {
        $t = trim((string)$t);
        if (mxli_padron_strlen($t) >= 2) {
            $tokens[$t] = $t;
        }
    }
    $tokens = array_values($tokens);
    if ($tokens === []) {
        return [];
    }

    $nameCols = [];
    if (!empty($map["nombre"])) {
        $nameCols[] = mxli_padron_quote_ident($map["nombre"]) . "::text";
    }
    if (!empty($map["razon_social"])) {
        $nameCols[] = mxli_padron_quote_ident($map["razon_social"]) . "::text";
    }
    if ($nameCols === []) {
        return [];
    }

    $haystack = "upper(trim(concat_ws(' ', " . implode(", ", $nameCols) . ")))";

    $whereParts = [];
    $params = [];
    foreach ($tokens as $i => $tok) {
        $key = "n" . $i;
        $whereParts[] = "{$haystack} LIKE :" . $key;
        $params[$key] = "%" . mxli_padron_upper($tok) . "%";
    }
    $where = implode(" AND ", $whereParts);

    $orderCol = !empty($map["nombre"])
        ? mxli_padron_quote_ident($map["nombre"])
        : mxli_padron_quote_ident($map["razon_social"]);

    // Ranking: coincidencia más corta / al inicio primero
    $first = $params["n0"] ?? ("%");
    $sql = "SELECT * FROM {$qualified}
            WHERE {$where}
            ORDER BY
              CASE WHEN {$haystack} LIKE :rank_prefix THEN 0 ELSE 1 END,
              length(trim({$orderCol}::text)) ASC,
              {$orderCol}
            LIMIT {$limit}";
    $params["rank_prefix"] = ltrim($first, "%");
    if (!str_ends_with((string)$params["rank_prefix"], "%")) {
        $params["rank_prefix"] .= "%";
    }
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    $out = [];
    $seen = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        if (!is_array($row)) {
            continue;
        }
        $item = mxli_padron_public_row($row, $map);
        $clave = (string)($item["clave"] ?? "");
        if ($clave === "" || isset($seen[$clave])) {
            continue;
        }
        $seen[$clave] = true;
        $out[] = $item;
    }
    return $out;
}

/**
 * Prefijos / ruido vial que no deben exigir coincidencia exacta.
 *
 * @return list<string>
 */
function mxli_padron_calle_stopwords(): array
{
    return [
        "GRAL", "GENERAL", "AV", "AVENIDA", "CALLE", "C", "BLVD", "BOULEVARD",
        "PRIV", "PRIVADA", "ANDADOR", "CERRADA", "CIRCUITO", "CALZ", "CALZADA",
        "CARR", "CARRETERA", "PROL", "PROLONGACION", "PROLONGACIÓN",
        "DR", "DRA", "ING", "LIC", "PROF",
        "DEL", "DE", "LA", "LAS", "LOS", "EL", "Y", "AL",
        "SAN", "SANTA", "STO", "STA", "COLONIA", "COL",
    ];
}

/**
 * Tokens significativos de un nombre de calle (sin puntuación ni prefijos).
 *
 * @return list<string>
 */
function mxli_padron_calle_tokens(string $calle): array
{
    $s = mxli_padron_upper($calle);
    $s = preg_replace("/[^\p{L}\p{N}\s]/u", " ", $s) ?? $s;
    $s = trim(preg_replace("/\s+/u", " ", $s) ?? "");
    if ($s === "") {
        return [];
    }

    $stop = array_fill_keys(mxli_padron_calle_stopwords(), true);
    $tokens = [];
    foreach (preg_split("/\s+/u", $s) ?: [] as $t) {
        $t = trim((string)$t);
        if ($t === "" || isset($stop[$t])) {
            continue;
        }
        if (mxli_padron_strlen($t) < 2) {
            continue;
        }
        if (!isset($tokens[$t])) {
            $tokens[$t] = $t;
        }
    }
    return array_values($tokens);
}

/**
 * Parsea "CALLE REFORMA 123", "REFORMA #120", "AV HIDALGO 45-A".
 *
 * @return array{calle:string,numero:string,tokens:list<string>}
 */
function mxli_padron_parse_direccion(string $query): array
{
    $q = trim(preg_replace("/\s+/u", " ", mxli_padron_upper($query)) ?? "");
    $q = str_replace(["№", "Nº", "N°"], "#", $q);
    if ($q === "") {
        return ["calle" => "", "numero" => "", "tokens" => []];
    }

    $calle = $q;
    $numero = "";

    if (preg_match('/^(.+?)\s+(?:#|NO\.?|NUM\.?|NÚM\.?|NUMERO|NÚMERO)\s*([0-9]+[A-Z]?)$/u', $q, $m)) {
        $calle = trim($m[1]);
        $numero = trim($m[2]);
    } elseif (preg_match('/^(.+?)\s+([0-9]+[A-Z]?)$/u', $q, $m)) {
        $cand = trim($m[1]);
        if (mxli_padron_strlen($cand) >= 3) {
            $calle = $cand;
            $numero = trim($m[2]);
        }
    }

    $tokens = mxli_padron_calle_tokens($calle);
    if ($tokens === []) {
        $raw = preg_replace("/[^\p{L}\p{N}\s]/u", " ", mxli_padron_upper($calle)) ?? $calle;
        foreach (preg_split("/\s+/u", trim($raw)) ?: [] as $t) {
            $t = trim((string)$t);
            if (mxli_padron_strlen($t) >= 3) {
                $tokens[] = $t;
            }
        }
    }

    return ["calle" => $calle, "numero" => $numero, "tokens" => $tokens];
}

/**
 * Búsqueda por calle + número oficial (y/o colonia).
 * Tolera prefijos (GRAL./AV./CALLE) y puntuación.
 *
 * @return list<array<string,string>>
 */
function mxli_padron_search_by_direccion(string $query, int $limit = 40, bool $includeOwner = false): array
{
    $parsed = mxli_padron_parse_direccion($query);
    $calle = trim($parsed["calle"]);
    $numero = trim($parsed["numero"]);
    $tokens = $parsed["tokens"] ?? [];

    if ($tokens === [] && mxli_padron_strlen($calle) < 3) {
        return [];
    }
    if ($tokens === [] && $calle !== "") {
        $tokens = mxli_padron_calle_tokens($calle);
    }
    if ($tokens === []) {
        return [];
    }

    $pdo = mxli_geonode_db();
    $map = mxli_padron_field_map($pdo);
    if ($map["calle"] === null && $map["colonia"] === null) {
        return [];
    }

    $qualified = mxli_padron_qualified();
    $limit = min(80, max(1, $limit));

    $calleNorm = null;
    if ($map["calle"] !== null) {
        $calleCol = mxli_padron_quote_ident($map["calle"]);
        $calleNorm = "regexp_replace(upper(trim({$calleCol}::text)), '[^A-Z0-9ÁÉÍÓÚÑÜ ]', ' ', 'g')";
    }
    $coloniaNorm = null;
    if ($map["colonia"] !== null) {
        $coloniaCol = mxli_padron_quote_ident($map["colonia"]);
        $coloniaNorm = "regexp_replace(upper(trim({$coloniaCol}::text)), '[^A-Z0-9ÁÉÍÓÚÑÜ ]', ' ', 'g')";
    }

    $whereParts = [];
    $params = [];
    foreach ($tokens as $i => $tok) {
        $key = "t" . $i;
        $ors = [];
        if ($calleNorm !== null) {
            $ors[] = "{$calleNorm} LIKE :" . $key;
        }
        if ($coloniaNorm !== null) {
            $ors[] = "{$coloniaNorm} LIKE :" . $key . "c";
            $params[$key . "c"] = "%" . mxli_padron_upper($tok) . "%";
        }
        if ($ors === []) {
            continue;
        }
        $whereParts[] = "(" . implode(" OR ", $ors) . ")";
        $params[$key] = "%" . mxli_padron_upper($tok) . "%";
    }

    if ($numero !== "" && $map["numero"] !== null) {
        $numCol = mxli_padron_quote_ident($map["numero"]);
        $whereParts[] = "regexp_replace({$numCol}::text, '[^0-9A-Za-z]', '', 'g') ILIKE :numero";
        $params["numero"] = $numero . "%";
    }

    if ($whereParts === []) {
        return [];
    }

    $where = implode(" AND ", $whereParts);

    $order = "1";
    if ($map["calle"] !== null) {
        $calleCol = mxli_padron_quote_ident($map["calle"]);
        $order = "length(trim({$calleCol}::text)) ASC, {$calleCol}";
        if ($numero !== "" && $map["numero"] !== null) {
            $numCol = mxli_padron_quote_ident($map["numero"]);
            $order = "{$numCol}, length(trim({$calleCol}::text)) ASC, {$calleCol}";
        }
    }

    $sql = "SELECT * FROM {$qualified}
            WHERE {$where}
            ORDER BY {$order}
            LIMIT {$limit}";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    $out = [];
    $seen = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        if (!is_array($row)) {
            continue;
        }
        $item = mxli_padron_public_row_search($row, $map, $includeOwner);
        $clave = (string)($item["clave"] ?? "");
        if ($clave === "" || isset($seen[$clave])) {
            continue;
        }
        $seen[$clave] = true;
        $out[] = $item;
    }
    return $out;
}
