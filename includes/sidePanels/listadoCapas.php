<?php
$layerMetadata = require dirname(__DIR__) . "/layer-metadata.php";

$wmsCapasGeoservidor = [
    [
        "id" => "predios_chkbox",
        "var" => "predios",
        "layer" => "predios_mexicali_2025",
        "workspace" => "geonode",
        "datastore" => "geonode",
        "title" => "Predios",
        "color" => "#e67e22",
    ],
    [
        "id" => "predios_baldios_chkbox",
        "var" => "predios_baldios",
        "layer" => "predios_baldios",
        "workspace" => "geonode",
        "datastore" => "geonode",
        "title" => "Predios Baldíos",
        "color" => "#d4a017",
    ],
    [
        "id" => "limite_chkbox",
        "var" => "limite_municipal_de_mexciali_oficial",
        "layer" => "limite_municipal_de_mexciali_oficial",
        "workspace" => "geonode",
        "datastore" => "geonode",
        "title" => "Limite Municipal Oficial de Mexicali",
        "color" => "#27ae60",
    ],
    [
        "id" => "colonias_chkbox",
        "var" => "colonias",
        "layer" => "colonias",
        "workspace" => "geonode",
        "datastore" => "geonode",
        "title" => "Colonias",
        "color" => "#8e44ad",
    ],
    [
        "id" => "predios_con_uso_chkbox",
        "var" => "predios_con_uso",
        "layer" => "predios_con_uso",
        "workspace" => "geonode",
        "datastore" => "geonode",
        "title" => "Usos de Suelo Actual",
        "color" => "#e74c3c",
    ],
    [
        "id" => "usosp_chkbox",
        "var" => "usosp",
        "layer" => "usos_prop_au40",
        "workspace" => "geonode",
        "datastore" => "geonode",
        "title" => "Usos de Suelo 2040",
        "color" => "#c0392b",
    ],
    [
        "id" => "cp_chkbox1",
        "var" => "cp",
        "layer" => "codigos_postales_2025",
        "workspace" => "geonode",
        "datastore" => "geonode",
        "title" => "Codigos Postales Mexicali",
        "color" => "#2980b9",
    ],
    [
        "id" => "vialidades2040_chkbox1",
        "var" => "estructura_vial",
        "layer" => "estructura_vial",
        "workspace" => "geonode",
        "datastore" => "geonode",
        "title" => "Esquema Vial Propuesto 2040",
        "color" => "#16a085",
    ],
    [
        "id" => "zonas_homogeneas_chkbox",
        "var" => "zonas_homogeneas",
        "layer" => "zonas_homogeneas_2017_2026_prop",
        "workspace" => "geonode",
        "datastore" => "geonode",
        "title" => "Zonas Homogéneas 2017-2026",
        "color" => "#a05a2c",
    ],
];

foreach ($wmsCapasGeoservidor as &$capa) {
    $meta = $layerMetadata[$capa["layer"]] ?? [];
    $capa["legend_width"] = (int)($meta["legend_width"] ?? 300);
    $capa["legend_height"] = (int)($meta["legend_height"] ?? 700);
}
unset($capa);

if (!function_exists("mxli_can_layer")) {
    require_once dirname(__DIR__) . "/auth.php";
}

$wmsCapasGeoservidor = array_values(array_filter(
    $wmsCapasGeoservidor,
    static function (array $capa): bool {
        return mxli_can_layer((string)($capa["layer"] ?? ""));
    }
));
?>

<ul class="cd-accordion cd-accordion--animated margin-top-lg margin-bottom-lg">
    <li class="cd-accordion__item cd-accordion__item--has-children">
        <input class="cd-accordion__input" type="checkbox" name="group-1" id="group-1" checked>
        <label class="cd-accordion__label cd-accordion__label--level1 accordion-capas" for="group-1">
            CATASTRO DE MEXICALI
            <div class="plus-minus-toggle collapsed"></div>
        </label>

        <ul class="cd-accordion__sub cd-accordion__sub--l1">
            <li class="cd-accordion__item cd-accordion__item--has-children">
                <input class="cd-accordion__input" type="checkbox" name="sub-group-0" id="sub-group-0" checked>
                <label class="cd-accordion__label cd-accordion__label--icon-folder accordion-capas subgrupo1"
                    for="sub-group-0">Capas en el Geoservidor</label>

                <ul class="cd-accordion__sub cd-accordion__sub--l2">
                    <li class="wms-layer-panel-item">
                    <div class="lista-capas wms-layer-panel">
                        <h4 class="wms-layer-panel__heading">CAPAS WMS</h4>
                        <div id="wms-layer-list" class="wms-layer-list">
                            <?php foreach ($wmsCapasGeoservidor as $capa): ?>
                                <div class="wms-layer-card"
                                    data-layer-id="<?= htmlspecialchars($capa["id"], ENT_QUOTES, "UTF-8") ?>"
                                    data-layer-var="<?= htmlspecialchars($capa["var"], ENT_QUOTES, "UTF-8") ?>"
                                    data-gs-layer="<?= htmlspecialchars($capa["layer"], ENT_QUOTES, "UTF-8") ?>"
                                    data-workspace="<?= htmlspecialchars($capa["workspace"], ENT_QUOTES, "UTF-8") ?>"
                                    data-datastore="<?= htmlspecialchars($capa["datastore"], ENT_QUOTES, "UTF-8") ?>"
                                    data-title="<?= htmlspecialchars($capa["title"], ENT_QUOTES, "UTF-8") ?>"
                                    data-legend-width="<?= (int)$capa["legend_width"] ?>"
                                    data-legend-height="<?= (int)$capa["legend_height"] ?>"
                                    data-opacity="100">
                                    <div class="wms-layer-card__header">
                                        <label class="wms-layer-card__check" for="<?= htmlspecialchars($capa["id"], ENT_QUOTES, "UTF-8") ?>">
                                            <input type="checkbox"
                                                id="<?= htmlspecialchars($capa["id"], ENT_QUOTES, "UTF-8") ?>"
                                                name="<?= htmlspecialchars($capa["id"], ENT_QUOTES, "UTF-8") ?>"
                                                onchange="toggleLayer('<?= htmlspecialchars($capa["id"], ENT_QUOTES, "UTF-8") ?>', <?= htmlspecialchars($capa["var"], ENT_QUOTES, "UTF-8") ?>)">
                                        </label>
                                        <span class="wms-layer-card__color"
                                            style="background: <?= htmlspecialchars($capa["color"], ENT_QUOTES, "UTF-8") ?>"></span>
                                        <a href="#"
                                            onclick="seleccionarCapa(this, '<?= htmlspecialchars($capa["layer"], ENT_QUOTES, "UTF-8") ?>', '<?= htmlspecialchars($capa["datastore"], ENT_QUOTES, "UTF-8") ?>', '<?= htmlspecialchars($capa["workspace"], ENT_QUOTES, "UTF-8") ?>')"
                                            class="wms-layer-card__title test-link">
                                            <?= htmlspecialchars($capa["title"], ENT_QUOTES, "UTF-8") ?>
                                        </a>
                                        <span class="wms-layer-card__opacity-value"
                                            id="<?= htmlspecialchars($capa["id"], ENT_QUOTES, "UTF-8") ?>_opacity_label">100%</span>
                                    </div>

                                    <input type="range"
                                        class="wms-layer-card__slider"
                                        min="0"
                                        max="100"
                                        step="1"
                                        value="100"
                                        oninput="setWmsLayerOpacity('<?= htmlspecialchars($capa["var"], ENT_QUOTES, "UTF-8") ?>', this.value, '<?= htmlspecialchars($capa["id"], ENT_QUOTES, "UTF-8") ?>_opacity_label', '<?= htmlspecialchars($capa["id"], ENT_QUOTES, "UTF-8") ?>')">

                                    <div class="wms-layer-card__actions">
                                        <button type="button" class="wms-layer-card__btn"
                                            onclick="moveWmsLayer('<?= htmlspecialchars($capa["id"], ENT_QUOTES, "UTF-8") ?>', 'up')">
                                            ↑ Subir
                                        </button>
                                        <button type="button" class="wms-layer-card__btn"
                                            onclick="moveWmsLayer('<?= htmlspecialchars($capa["id"], ENT_QUOTES, "UTF-8") ?>', 'down')">
                                            ↓ Bajar
                                        </button>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    </li>
                </ul>
            </li>
        </ul>
    </li>
</ul>
