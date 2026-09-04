<?php
/**
 * Diagnóstico en el servidor:
 *   cd /var/www/html/localizacion
 *   php scripts/test_padron_lookup.php SP001001
 */
declare(strict_types=1);

require_once dirname(__DIR__) . "/includes/config.php";
require_once dirname(__DIR__) . "/includes/db.php";
require_once dirname(__DIR__) . "/includes/padron.php";

$clave = $argv[1] ?? "SP001001";

echo "DSN geonode: " . mxli_geonode_dsn() . "\n";
echo "Tabla: " . mxli_padron_qualified() . "\n";
echo "Clave: " . mxli_normalize_clave_catastral($clave) . "\n\n";

try {
    $pdo = mxli_geonode_db();
    echo "Conexión OK\n";
    $cols = mxli_padron_column_names($pdo);
    echo "Columnas (" . count($cols) . "): " . implode(", ", $cols) . "\n";
    $map = mxli_padron_field_map($pdo);
    echo "Mapa clave=" . ($map["clave"] ?? "?")
        . " nombre=" . ($map["nombre"] ?? "(ninguna)")
        . " colonia=" . ($map["colonia"] ?? "(ninguna)")
        . " sup_doc=" . ($map["superficie_documental"] ?? "(ninguna)")
        . " descripcion=" . ($map["descripcion"] ?? "(ninguna)")
        . "\n\n";

    // Fila cruda (claves PDO tal cual) para ver mayúsculas / truncados.
    $map = mxli_padron_field_map($pdo);
    $claveCol = mxli_padron_quote_ident($map["clave"]);
    $rawStmt = $pdo->prepare(
        "SELECT * FROM " . mxli_padron_qualified()
        . " WHERE replace(upper(trim({$claveCol}::text)), ' ', '') = :clave LIMIT 1"
    );
    $rawStmt->execute(["clave" => mxli_normalize_clave_catastral($clave)]);
    $raw = $rawStmt->fetch(PDO::FETCH_ASSOC);
    if (is_array($raw)) {
        echo "Claves PDO crudas: " . implode(", ", array_keys($raw)) . "\n";
        echo "Raw (recorte): " . json_encode($raw, JSON_UNESCAPED_UNICODE) . "\n\n";
    }

    $row = mxli_padron_lookup_by_clave($clave);
    if ($row === null) {
        echo "RESULTADO: no encontrado\n";
        $sample = $pdo->query("SELECT * FROM " . mxli_padron_qualified() . " LIMIT 2")->fetchAll(PDO::FETCH_ASSOC);
        echo "Muestra (2 filas):\n";
        foreach ($sample as $i => $r) {
            echo "  [" . $i . "] " . json_encode($r, JSON_UNESCAPED_UNICODE) . "\n";
        }
        exit(2);
    }
    echo "RESULTADO OK:\n";
    echo json_encode($row, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
    if (($row["propietario"] ?? "") === "" || ($row["descripcion"] ?? "") === "" || ($row["superficie_documental"] ?? "") === "") {
        echo "\nAVISO: algún campo popup quedó vacío. Revisa Mapa/Claves PDO arriba.\n";
        exit(3);
    }
} catch (Throwable $e) {
    fwrite(STDERR, "ERROR: " . $e->getMessage() . "\n");
    exit(1);
}
