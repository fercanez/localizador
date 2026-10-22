<?php
declare(strict_types=1);

/**
 * Exporta inspecciones como KMZ (KML + fotografías) o KML.
 *
 * GET:
 *   area=urbano|catastro   (requerido)
 *   format=kmz|kml         (default kmz)
 *   ids=1,2,3              (opcional; si vacío exporta el área filtrable)
 *   clave=                 (opcional)
 *   inspector=             (opcional, id de usuario)
 *   tipo=                  (opcional, un tipo urbano)
 *   with_coords=1          (default 1: solo puntos con lat/lng)
 *   id=123                 (exporta un solo registro)
 */

require_once dirname(__DIR__) . "/includes/init.php";
require_once dirname(__DIR__) . "/includes/auth.php";
require_once dirname(__DIR__) . "/includes/inspections.php";

if (!mxli_is_logged_in()) {
    http_response_code(401);
    header("Content-Type: text/plain; charset=UTF-8");
    echo "Debe iniciar sesión";
    exit;
}

$user = mxli_current_user();
if ($user === null) {
    http_response_code(401);
    header("Content-Type: text/plain; charset=UTF-8");
    echo "Sesión inválida";
    exit;
}

// Acepta filtros por GET o POST (POST evita límites de URL con muchos IDs)
$req = array_merge($_GET, $_POST);

$areaParam = strtolower(trim((string)($req["area"] ?? "")));
if ($areaParam === "" && isset($req["id"])) {
    // área se resolverá desde el registro
    $areaParam = "";
} elseif ($areaParam === "") {
    http_response_code(400);
    header("Content-Type: text/plain; charset=UTF-8");
    echo "Parámetro area requerido (urbano|catastro)";
    exit;
}

$format = strtolower(trim((string)($req["format"] ?? "kmz")));
if ($format !== "kml" && $format !== "kmz") {
    $format = "kmz";
}

$allowedAreas = mxli_inspection_areas_for_user($user);
$withCoords = !isset($req["with_coords"]) || (string)$req["with_coords"] !== "0";

try {
    $pdo = mxli_db();
    mxli_ensure_inspections_table($pdo);

    $params = [];
    $whereParts = [];

    $singleId = (int)($req["id"] ?? 0);
    if ($singleId > 0) {
        $whereParts[] = "p.id = :id";
        $params["id"] = $singleId;
    } else {
        $area = mxli_normalize_inspection_area($areaParam);
        if (!in_array($area, $allowedAreas, true)) {
            http_response_code(403);
            header("Content-Type: text/plain; charset=UTF-8");
            echo "Sin permiso para esta área";
            exit;
        }
        $whereParts[] = "p.inspection_area = :area";
        $params["area"] = $area;

        if (!mxli_can_manage_inspection_area($area, $user)) {
            $whereParts[] = "p.created_by = :uid";
            $params["uid"] = (int)$user["id"];
        }

        $idsRaw = trim((string)($req["ids"] ?? ""));
        if ($idsRaw !== "") {
            $ids = [];
            foreach (explode(",", $idsRaw) as $piece) {
                $n = (int)trim($piece);
                if ($n > 0) {
                    $ids[$n] = $n;
                }
            }
            $ids = array_values($ids);
            if ($ids === []) {
                http_response_code(400);
                header("Content-Type: text/plain; charset=UTF-8");
                echo "IDs inválidos";
                exit;
            }
            if (count($ids) > 2000) {
                $ids = array_slice($ids, 0, 2000);
            }
            $placeholders = [];
            foreach ($ids as $i => $idVal) {
                $key = "id" . $i;
                $placeholders[] = ":" . $key;
                $params[$key] = $idVal;
            }
            $whereParts[] = "p.id IN (" . implode(", ", $placeholders) . ")";
        }

        $clave = mxli_normalize_inspection_clave($req["clave"] ?? "");
        if ($clave !== "") {
            $whereParts[] = "p.clavecatas ILIKE :clave";
            $params["clave"] = $clave . "%";
        }

        $inspector = (int)($req["inspector"] ?? 0);
        if ($inspector > 0 && mxli_can_manage_inspection_area($area, $user)) {
            $whereParts[] = "p.created_by = :inspector";
            $params["inspector"] = $inspector;
        }

        $tipo = mxli_normalize_inspection_types($req["tipo"] ?? "");
        if ($tipo !== "" && $area === "urbano") {
            $tipoKey = mxli_inspection_types_list($tipo)[0] ?? "";
            if ($tipoKey !== "") {
                $whereParts[] = "(',' || COALESCE(p.inspection_types, '') || ',') ILIKE :tipo";
                $params["tipo"] = "%," . $tipoKey . ",%";
            }
        }
    }

    if ($withCoords) {
        $whereParts[] = "p.lat IS NOT NULL AND p.lng IS NOT NULL";
    }

    $where = implode(" AND ", $whereParts);
    $sql =
        "SELECT p.*, u.username AS created_by_name
         FROM app_inspection_photos p
         LEFT JOIN app_users u ON u.id = p.created_by
         WHERE {$where}
         ORDER BY p.captured_at DESC
         LIMIT 2000";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    $items = [];
    foreach ($rows as $row) {
        if (!mxli_can_access_inspection_row($row, $user)) {
            continue;
        }
        $items[] = $row;
    }

    if ($items === []) {
        http_response_code(404);
        header("Content-Type: text/plain; charset=UTF-8");
        echo "No hay inspecciones con coordenadas para exportar.";
        exit;
    }

    // Resolver área del lote (para nombre de archivo)
    $exportArea = mxli_normalize_inspection_area($items[0]["inspection_area"] ?? ($areaParam ?: "urbano"));
    $areaLabels = mxli_inspection_area_labels();
    $areaLabel = $areaLabels[$exportArea] ?? $exportArea;
    $folderName = "Inspecciones Control Urbano";
    if ($exportArea === "catastro") {
        $folderName = "Inspecciones Catastro";
    } elseif ($exportArea === "ecologica") {
        $folderName = "Inspecciones Patrulla Ecológica";
    }

    $photoEntries = []; // relative path inside archive => abs path OR binary string
    $iconBinaries = []; // icons/circle_9b2f55.png => png bytes
    $styleXml = [];
    $placemarks = [];
    $shape = "circle";
    $shapeLabel = "círculo";
    $remoteIcon = "http://maps.google.com/mapfiles/kml/shapes/placemark_circle.png";
    $canMakePng = function_exists("imagecreatetruecolor") && function_exists("imagepng");
    if ($exportArea === "catastro") {
        $shape = "triangle";
        $shapeLabel = "triángulo";
        $remoteIcon = "http://maps.google.com/mapfiles/kml/shapes/triangle.png";
    } elseif ($exportArea === "ecologica") {
        $shape = "star";
        $shapeLabel = "estrella";
        $remoteIcon = "http://maps.google.com/mapfiles/kml/shapes/star.png";
    }

    foreach ($items as $row) {
        $public = mxli_public_inspection_row($row, $user);
        $lat = $public["lat"];
        $lng = $public["lng"];
        if ($lat === null || $lng === null) {
            continue;
        }

        $id = (int)$public["id"];
        $clave = strtoupper((string)$public["clavecatas"]);
        $name = $clave !== "" ? $clave : ("INS-" . $id);
        $inspector = strtoupper((string)$public["created_by_name"]);
        $notes = strtoupper((string)$public["notes"]);
        $typesLabel = (string)($public["types_label"] ?? "");
        $captured = (string)($public["captured_at"] ?? "");
        $areaRowLabel = (string)($public["area_label"] ?? $areaLabel);
        $markerColor = mxli_inspection_color_for_user(
            isset($public["created_by"]) ? (int)$public["created_by"] : null,
            (string)($public["created_by_name"] ?? "")
        );
        $colorKey = strtolower(ltrim($markerColor, "#"));
        $styleId = ($exportArea === "catastro" ? "tri_" : "cir_") . $colorKey;

        if (!isset($styleXml[$styleId])) {
            $iconHref = $remoteIcon;
            if ($canMakePng) {
                $iconRel = "icons/" . ($exportArea === "catastro" ? "tri_" : "cir_") . $colorKey . ".png";
                if (!isset($iconBinaries[$iconRel])) {
                    $iconBinaries[$iconRel] = mxli_inspection_marker_png($markerColor, $shape);
                }
                // En KMZ usamos ruta relativa; en KML puro usamos remoto + color
                if ($format === "kmz") {
                    $iconHref = $iconRel;
                }
            }

            $hotspotY = $exportArea === "catastro" ? "0.12" : "0.5";
            $useEmbeddedIcon = $format === "kmz" && $canMakePng && str_starts_with($iconHref, "icons/");
            $colorXml = $useEmbeddedIcon
                ? ""
                : "      <color>" . mxli_kml_color_from_hex($markerColor, "ff") . "</color>\n";
            $styleXml[$styleId] =
                "  <Style id=\"" . htmlspecialchars($styleId, ENT_QUOTES | ENT_XML1, "UTF-8") . "\">\n" .
                "    <IconStyle>\n" .
                $colorXml .
                "      <scale>1.15</scale>\n" .
                "      <Icon><href>" . htmlspecialchars($iconHref, ENT_QUOTES | ENT_XML1, "UTF-8") . "</href></Icon>\n" .
                "      <hotSpot x=\"0.5\" y=\"" . $hotspotY . "\" xunits=\"fraction\" yunits=\"fraction\"/>\n" .
                "    </IconStyle>\n" .
                "    <LabelStyle><scale>0.85</scale></LabelStyle>\n" .
                "  </Style>";
        }

        $imgRel = "";
        $photoPathRel = (string)($row["photo_path"] ?? "");
        if ($photoPathRel !== "") {
            try {
                $abs = mxli_inspection_photo_abs_path($photoPathRel);
            } catch (Throwable $e) {
                $abs = "";
            }
            if ($abs !== "" && is_file($abs)) {
                $ext = strtolower(pathinfo($abs, PATHINFO_EXTENSION) ?: "jpg");
                if (!preg_match('/^(jpe?g|png|gif|webp)$/', $ext)) {
                    $ext = "jpg";
                }
                $imgRel = "files/insp_" . $id . "." . $ext;
                $photoEntries[$imgRel] = $abs;
            }
        }

        $descRows = [
            ["Clave", $clave !== "" ? $clave : "—"],
            ["Área", strtoupper($areaRowLabel)],
            ["Símbolo", strtoupper($shapeLabel)],
            ["Fecha", $captured !== "" ? $captured : "—"],
            ["Inspector", $inspector !== "" ? $inspector : "—"],
        ];
        if ($exportArea !== "catastro") {
            $descRows[] = ["Tipos", $typesLabel !== "" ? strtoupper($typesLabel) : "—"];
        }
        $descRows[] = ["Notas", $notes !== "" ? $notes : "—"];
        $descRows[] = ["Latitud", number_format((float)$lat, 6, ".", "")];
        $descRows[] = ["Longitud", number_format((float)$lng, 6, ".", "")];

        $tableHtml = '<table border="1" cellpadding="4" cellspacing="0" style="border-collapse:collapse;font-family:Arial,sans-serif;font-size:12px;">';
        foreach ($descRows as $pair) {
            $tableHtml .=
                "<tr><td><b>" .
                htmlspecialchars($pair[0], ENT_QUOTES | ENT_XML1, "UTF-8") .
                "</b></td><td>" .
                htmlspecialchars($pair[1], ENT_QUOTES | ENT_XML1, "UTF-8") .
                "</td></tr>";
        }
        $tableHtml .= "</table>";

        if ($imgRel !== "" && $format === "kmz") {
            $tableHtml .=
                '<div style="margin-top:8px"><img src="' .
                htmlspecialchars($imgRel, ENT_QUOTES | ENT_XML1, "UTF-8") .
                '" width="420" alt="Foto inspección"/></div>';
        } elseif ($imgRel === "" && $format === "kmz") {
            $tableHtml .= '<p><i>Sin fotografía</i></p>';
        }

        $extended = "";
        $dataPairs = [
            "id" => (string)$id,
            "clavecatas" => $clave,
            "area" => $exportArea,
            "area_label" => $areaRowLabel,
            "symbol" => $shapeLabel,
            "marker_color" => $markerColor,
            "captured_at" => $captured,
            "inspector" => $inspector,
            "types" => $typesLabel,
            "notes" => $notes,
            "lat" => number_format((float)$lat, 8, ".", ""),
            "lng" => number_format((float)$lng, 8, ".", ""),
        ];
        foreach ($dataPairs as $k => $v) {
            $extended .=
                "<Data name=\"" .
                htmlspecialchars($k, ENT_QUOTES | ENT_XML1, "UTF-8") .
                "\"><value>" .
                htmlspecialchars((string)$v, ENT_QUOTES | ENT_XML1, "UTF-8") .
                "</value></Data>";
        }

        $placemarks[] =
            "<Placemark>\n" .
            "  <name>" . htmlspecialchars($name, ENT_QUOTES | ENT_XML1, "UTF-8") . "</name>\n" .
            "  <styleUrl>#" . htmlspecialchars($styleId, ENT_QUOTES | ENT_XML1, "UTF-8") . "</styleUrl>\n" .
            "  <description><![CDATA[" . $tableHtml . "]]></description>\n" .
            "  <ExtendedData>" . $extended . "</ExtendedData>\n" .
            "  <Point><coordinates>" .
            number_format((float)$lng, 8, ".", "") .
            "," .
            number_format((float)$lat, 8, ".", "") .
            ",0</coordinates></Point>\n" .
            "</Placemark>";
    }

    if ($placemarks === []) {
        http_response_code(404);
        header("Content-Type: text/plain; charset=UTF-8");
        echo "No hay puntos con coordenadas válidas.";
        exit;
    }

    $stamp = date("Y-m-d");
    $baseName =
        "inspecciones_" .
        $exportArea .
        ($singleId > 0 ? "_id" . $singleId : "") .
        "_" .
        $stamp;

    $kml =
        '<?xml version="1.0" encoding="UTF-8"?>' . "\n" .
        '<kml xmlns="http://www.opengis.net/kml/2.2">' . "\n" .
        "<Document>\n" .
        "  <name>" . htmlspecialchars($folderName . " " . $stamp, ENT_QUOTES | ENT_XML1, "UTF-8") . "</name>\n" .
        "  <description>" .
        htmlspecialchars(
            "Exportación de inspecciones — " . $areaLabel .
            " (" . count($placemarks) . " puntos, " . $shapeLabel . " por inspector)",
            ENT_QUOTES | ENT_XML1,
            "UTF-8"
        ) .
        "</description>\n" .
        implode("\n", $styleXml) . "\n" .
        "  <Folder>\n" .
        "    <name>" . htmlspecialchars($folderName, ENT_QUOTES | ENT_XML1, "UTF-8") . "</name>\n" .
        implode("\n", $placemarks) . "\n" .
        "  </Folder>\n" .
        "</Document>\n</kml>\n";

    if ($format === "kml" || !class_exists("ZipArchive")) {
        header("Content-Type: application/vnd.google-earth.kml+xml; charset=UTF-8");
        header('Content-Disposition: attachment; filename="' . $baseName . '.kml"');
        header("Cache-Control: no-store, no-cache, must-revalidate");
        echo $kml;
        exit;
    }

    $tmp = tempnam(sys_get_temp_dir(), "mxli_kmz_");
    if ($tmp === false) {
        throw new RuntimeException("No se pudo crear archivo temporal");
    }
    $kmzPath = $tmp . ".kmz";
    @unlink($tmp);

    $zip = new ZipArchive();
    if ($zip->open($kmzPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        throw new RuntimeException("No se pudo crear el KMZ");
    }
    $zip->addFromString("doc.kml", $kml);
    foreach ($iconBinaries as $rel => $bytes) {
        $zip->addFromString($rel, $bytes);
    }
    foreach ($photoEntries as $rel => $abs) {
        $zip->addFile($abs, $rel);
    }
    $zip->close();

    $size = filesize($kmzPath);
    header("Content-Type: application/vnd.google-earth.kmz");
    header('Content-Disposition: attachment; filename="' . $baseName . '.kmz"');
    header("Cache-Control: no-store, no-cache, must-revalidate");
    if ($size !== false) {
        header("Content-Length: " . (string)$size);
    }
    readfile($kmzPath);
    @unlink($kmzPath);
    exit;
} catch (Throwable $e) {
    http_response_code(500);
    header("Content-Type: text/plain; charset=UTF-8");
    echo "Error al exportar: " . $e->getMessage();
    exit;
}
