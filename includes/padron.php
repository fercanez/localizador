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

    // SELECT * + remap: más tolerante a mayúsculas/truncados que un SELECT con AS fijo.
    $sql = "SELECT * FROM {$qualified}
            WHERE replace(upper(trim({$claveCol}::text)), ' ', '') = :clave
            LIMIT 1";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(["clave" => $clave]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!is_array($row)) {
        return null;
    }

    return mxli_padron_public_row($row, $map);
}

/**
 * @return list<array<string,string>>
 */
function mxli_padron_search_by_nombre(string $nombre, int $limit = 40): array
{
    $nombre = trim(preg_replace("/\s+/", " ", $nombre) ?? "");
    if (mb_strlen($nombre) < 3) {
        return [];
    }

    $pdo = mxli_geonode_db();
    $map = mxli_padron_field_map($pdo);
    if ($map["nombre"] === null) {
        return [];
    }

    $claveCol = mxli_padron_quote_ident($map["clave"]);
    $nombreCol = mxli_padron_quote_ident($map["nombre"]);
    $qualified = mxli_padron_qualified();
    $limit = min(80, max(1, $limit));

    $sql = "SELECT * FROM {$qualified}
            WHERE {$nombreCol}::text ILIKE :q
            ORDER BY {$nombreCol}
            LIMIT {$limit}";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(["q" => "%" . $nombre . "%"]);

    $out = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        if (is_array($row)) {
            $out[] = mxli_padron_public_row($row, $map);
        }
    }
    return $out;
}
