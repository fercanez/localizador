<?php
require_once dirname(__DIR__) . "/inspections.php";

$layerMetadata = require dirname(__DIR__) . "/layer-metadata.php";
$inspectAreas = mxli_inspection_areas_for_user();
$showInspeccionesUrbano = in_array("urbano", $inspectAreas, true);
$showInspeccionesCatastro = in_array("catastro", $inspectAreas, true);
$showInspeccionesEcologica = in_array("ecologica", $inspectAreas, true);
$showInspeccionesCapa = $showInspeccionesUrbano || $showInspeccionesCatastro || $showInspeccionesEcologica;

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
        "id" => "red_unificada_chkbox",
        "var" => "red_unificada",
        "layer" => "red_unificada_de_mexicali",
        "workspace" => "geonode",
        "datastore" => "geonode",
        "title" => "Red Unificada",
        "color" => "#b91c1c",
        "default_on" => function_exists("mxli_user_is_perito_deslindador") && mxli_user_is_perito_deslindador(),
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
        "default_on" => true,
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
    [
        "id" => "avaluos_rusticos_chkbox",
        "var" => "avaluos_rusticos",
        "layer" => "avaluos_rusticos_mexicali",
        "workspace" => "geonode",
        "datastore" => "geonode",
        "title" => "Avalúos Rústicos Mexicali",
        "color" => "#6d4c41",
    ],
    [
        "id" => "drenes_conagua_chkbox",
        "var" => "drenes_conagua",
        "layer" => "drenes_conagua",
        "workspace" => "geonode",
        "datastore" => "geonode",
        "title" => "Drenes CONAGUA",
        "color" => "#1a7f9e",
    ],
    [
        "id" => "canales_conagua_chkbox",
        "var" => "canales_conagua",
        "layer" => "canales_conagua",
        "workspace" => "geonode",
        "datastore" => "geonode",
        "title" => "Canales CONAGUA",
        "color" => "#1565c0",
    ],
    [
        "id" => "fallas_geologicas_chkbox",
        "var" => "fallas_geologicas",
        "layer" => "fallas_geologicas",
        "workspace" => "geonode",
        "datastore" => "geonode",
        "title" => "Fallas geológicas",
        "color" => "#7a1f1f",
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

if (function_exists("mxli_user_is_perito_deslindador") && mxli_user_is_perito_deslindador()) {
    $redPrimero = [];
    $restoCapas = [];
    foreach ($wmsCapasGeoservidor as $capa) {
        if (($capa["layer"] ?? "") === "red_unificada_de_mexicali") {
            $redPrimero[] = $capa;
        } else {
            $restoCapas[] = $capa;
        }
    }
    $wmsCapasGeoservidor = array_merge($redPrimero, $restoCapas);
}
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
                            <?php if ($showInspeccionesUrbano): ?>
                                <div class="wms-layer-card wms-layer-card--inspect wms-layer-card--inspect-urbano"
                                    data-layer-id="inspecciones_fotos_chkbox"
                                    data-layer-var="inspecciones_fotos"
                                    data-title="Inspecciones fotográficas"
                                    data-opacity="100">
                                    <div class="wms-layer-card__header">
                                        <label class="wms-layer-card__check" for="inspecciones_fotos_chkbox">
                                            <input type="checkbox"
                                                id="inspecciones_fotos_chkbox"
                                                name="inspecciones_fotos_chkbox"
                                                onchange="toggleLayer('inspecciones_fotos_chkbox', inspecciones_fotos)">
                                        </label>
                                        <span class="wms-layer-card__shape mxli-inspect-shape mxli-inspect-shape--circle"
                                            style="background-color: #9b2f55"
                                            aria-hidden="true"></span>
                                        <span class="wms-layer-card__title">Inspecciones fotográficas</span>
                                    </div>
                                    <p class="wms-layer-card__hint">Control Urbano: círculos con color por inspector. Toque un punto o use la herramienta <strong>i</strong> para ver la ficha.</p>
                                    <div id="inspect-users-legend-urbano" class="inspect-users-legend" hidden></div>
                                </div>
                            <?php endif; ?>

                            <?php if ($showInspeccionesCatastro): ?>
                                <div class="wms-layer-card wms-layer-card--inspect wms-layer-card--inspect-catastro"
                                    data-layer-id="inspecciones_catastro_chkbox"
                                    data-layer-var="inspecciones_catastro"
                                    data-title="Inspecciones Catastro"
                                    data-opacity="100">
                                    <div class="wms-layer-card__header">
                                        <label class="wms-layer-card__check" for="inspecciones_catastro_chkbox">
                                            <input type="checkbox"
                                                id="inspecciones_catastro_chkbox"
                                                name="inspecciones_catastro_chkbox"
                                                onchange="toggleLayer('inspecciones_catastro_chkbox', inspecciones_catastro)">
                                        </label>
                                        <span class="wms-layer-card__shape mxli-inspect-shape mxli-inspect-shape--tri"
                                            style="color: #1a5fd0"
                                            aria-hidden="true">
                                            <svg viewBox="0 0 24 24" focusable="false"><polygon points="12,3 22,21 2,21" fill="currentColor" stroke="#ffffff" stroke-width="2" stroke-linejoin="round"/></svg>
                                        </span>
                                        <span class="wms-layer-card__title">Inspecciones Catastro</span>
                                    </div>
                                    <p class="wms-layer-card__hint">Catastro: triángulos con color por inspector. Toque un punto o use la herramienta <strong>i</strong> para ver la ficha.</p>
                                    <div id="inspect-users-legend-catastro" class="inspect-users-legend" hidden></div>
                                </div>
                            <?php endif; ?>

                            <?php if ($showInspeccionesEcologica): ?>
                                <div class="wms-layer-card wms-layer-card--inspect wms-layer-card--inspect-ecologica"
                                    data-layer-id="inspecciones_ecologica_chkbox"
                                    data-layer-var="inspecciones_ecologica"
                                    data-title="Inspecciones Patrulla Ecológica"
                                    data-opacity="100">
                                    <div class="wms-layer-card__header">
                                        <label class="wms-layer-card__check" for="inspecciones_ecologica_chkbox">
                                            <input type="checkbox"
                                                id="inspecciones_ecologica_chkbox"
                                                name="inspecciones_ecologica_chkbox"
                                                onchange="toggleLayer('inspecciones_ecologica_chkbox', inspecciones_ecologica)">
                                        </label>
                                        <span class="wms-layer-card__shape mxli-inspect-shape mxli-inspect-shape--star"
                                            style="display:inline-block;width:18px;height:18px;background:#1e7a46;clip-path:polygon(50% 0%, 61% 35%, 98% 35%, 68% 57%, 79% 91%, 50% 70%, 21% 91%, 32% 57%, 2% 35%, 39% 35%);-webkit-clip-path:polygon(50% 0%, 61% 35%, 98% 35%, 68% 57%, 79% 91%, 50% 70%, 21% 91%, 32% 57%, 2% 35%, 39% 35%);"
                                            aria-hidden="true"></span>
                                        <span class="wms-layer-card__title">Inspecciones Patrulla Ecológica</span>
                                    </div>
                                    <p class="wms-layer-card__hint">Patrulla Ecológica: estrellas con color por inspector. Solo la ve este rol. Toque un punto o use la herramienta <strong>i</strong> para ver la ficha.</p>
                                    <div id="inspect-users-legend-ecologica" class="inspect-users-legend" hidden></div>
                                </div>
                            <?php endif; ?>

                            <?php if (function_exists("mxli_can_layer") && mxli_can_layer("predios_adeudo")): ?>
                                <div class="wms-layer-card wms-layer-card--adeudo"
                                    id="predios_adeudo_card"
                                    data-layer-id="predios_adeudo_chkbox"
                                    data-layer-var="predios_adeudo"
                                    data-gs-layer="predios_mexicali_2025"
                                    data-title="Adeudo predial"
                                    data-opacity="100">
                                    <div class="wms-layer-card__header">
                                        <label class="wms-layer-card__check" for="predios_adeudo_chkbox">
                                            <input type="checkbox"
                                                id="predios_adeudo_chkbox"
                                                name="predios_adeudo_chkbox"
                                                onchange="if (window.MxliPredialCapa) MxliPredialCapa.sync();">
                                        </label>
                                        <span class="wms-layer-card__color mxli-adeudo-swatch" aria-hidden="true"></span>
                                        <span class="wms-layer-card__title">Adeudo predial</span>
                                    </div>
                                    <p class="wms-layer-card__hint">
                                        Capa temporal: verde sin adeudo, naranja aseo público, rojo adeudo predial
                                        (portal del Ayuntamiento). Pinta hasta 100 predios desde el centro del mapa hacia afuera
                                        (zoom 17+) y se limpia al alejar.
                                    </p>
                                    <div class="mxli-adeudo-legend" aria-hidden="true">
                                        <span class="mxli-adeudo-legend__item mxli-adeudo-legend__item--ok">Sin adeudo</span>
                                        <span class="mxli-adeudo-legend__item mxli-adeudo-legend__item--aseo">Aseo público</span>
                                        <span class="mxli-adeudo-legend__item mxli-adeudo-legend__item--debt">Adeudo predial</span>
                                    </div>
                                    <p id="predios_adeudo_status" class="wms-layer-card__hint mxli-adeudo-status">
                                        Encienda la capa y acerque el mapa (zoom 17+).
                                    </p>
                                </div>
                            <?php endif; ?>


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
                                    data-opacity="100"
                                    <?= !empty($capa["default_on"]) ? 'data-default-on="1"' : "" ?>>
                                    <div class="wms-layer-card__header">
                                        <label class="wms-layer-card__check" for="<?= htmlspecialchars($capa["id"], ENT_QUOTES, "UTF-8") ?>">
                                            <input type="checkbox"
                                                id="<?= htmlspecialchars($capa["id"], ENT_QUOTES, "UTF-8") ?>"
                                                name="<?= htmlspecialchars($capa["id"], ENT_QUOTES, "UTF-8") ?>"
                                                <?= !empty($capa["default_on"]) ? "checked" : "" ?>
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
