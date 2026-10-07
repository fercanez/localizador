<?php
declare(strict_types=1);

/**
 * Catálogo canónico de capas (keys ACL). Las virtuales no son WMS de GeoServer.
 */
function mxli_layer_catalog(): array
{
    return [
        [
            "key" => "predios_mexicali_2025",
            "title" => "Predios",
            "id" => "predios_chkbox",
            "var" => "predios",
            "workspace" => "geonode",
            "datastore" => "geonode",
            "color" => "#e67e22",
        ],
        [
            "key" => "predios_adeudo",
            "title" => "Adeudo predial (deudores)",
            "id" => "predios_adeudo_chkbox",
            "var" => "predios_adeudo",
            "workspace" => "",
            "datastore" => "",
            "color" => "#dc2626",
            "virtual" => true,
        ],
        [
            "key" => "predios_baldios",
            "title" => "Predios Baldíos",
            "id" => "predios_baldios_chkbox",
            "var" => "predios_baldios",
            "workspace" => "geonode",
            "datastore" => "geonode",
            "color" => "#d4a017",
        ],
        [
            "key" => "limite_municipal_de_mexciali_oficial",
            "title" => "Limite Municipal Oficial de Mexicali",
            "id" => "limite_chkbox",
            "var" => "limite_municipal_de_mexciali_oficial",
            "workspace" => "geonode",
            "datastore" => "geonode",
            "color" => "#27ae60",
        ],
        [
            "key" => "colonias",
            "title" => "Colonias",
            "id" => "colonias_chkbox",
            "var" => "colonias",
            "workspace" => "geonode",
            "datastore" => "geonode",
            "color" => "#8e44ad",
        ],
        [
            "key" => "predios_con_uso",
            "title" => "Usos de Suelo Actual",
            "id" => "predios_con_uso_chkbox",
            "var" => "predios_con_uso",
            "workspace" => "geonode",
            "datastore" => "geonode",
            "color" => "#e74c3c",
        ],
        [
            "key" => "usos_prop_au40",
            "title" => "Usos de Suelo 2040",
            "id" => "usosp_chkbox",
            "var" => "usosp",
            "workspace" => "geonode",
            "datastore" => "geonode",
            "color" => "#c0392b",
        ],
        [
            "key" => "codigos_postales_2025",
            "title" => "Codigos Postales Mexicali",
            "id" => "cp_chkbox1",
            "var" => "cp",
            "workspace" => "geonode",
            "datastore" => "geonode",
            "color" => "#2980b9",
        ],
        [
            "key" => "estructura_vial",
            "title" => "Esquema Vial Propuesto 2040",
            "id" => "vialidades2040_chkbox1",
            "var" => "estructura_vial",
            "workspace" => "geonode",
            "datastore" => "geonode",
            "color" => "#16a085",
        ],
        [
            "key" => "zonas_homogeneas_2017_2026_prop",
            "title" => "Zonas Homogéneas 2017-2026",
            "id" => "zonas_homogeneas_chkbox",
            "var" => "zonas_homogeneas",
            "workspace" => "geonode",
            "datastore" => "geonode",
            "color" => "#a05a2c",
        ],
        [
            "key" => "avaluos_rusticos_mexicali",
            "title" => "Avalúos Rústicos Mexicali",
            "id" => "avaluos_rusticos_chkbox",
            "var" => "avaluos_rusticos",
            "workspace" => "geonode",
            "datastore" => "geonode",
            "color" => "#6d4c41",
        ],
        [
            "key" => "drenes_conagua",
            "title" => "Drenes CONAGUA",
            "id" => "drenes_conagua_chkbox",
            "var" => "drenes_conagua",
            "workspace" => "geonode",
            "datastore" => "geonode",
            "color" => "#1a7f9e",
        ],
        [
            "key" => "canales_conagua",
            "title" => "Canales CONAGUA",
            "id" => "canales_conagua_chkbox",
            "var" => "canales_conagua",
            "workspace" => "geonode",
            "datastore" => "geonode",
            "color" => "#1565c0",
        ],
    ];
}

function mxli_layer_keys(): array
{
    return array_column(mxli_layer_catalog(), "key");
}

function mxli_tool_permission_defs(): array
{
    return [
        "map.view" => "Ver mapa",
        "map.search" => "Buscar predios",
        "map.info" => "Consultar información (clic)",
        "map.padron" => "Ver nombre del contribuyente (padrón)",
        "map.expediente" => "Ver expediente digital (documentación)",
        "map.expediente.download" => "Descargar expediente",
        "map.expediente.print" => "Imprimir expediente",
        "map.expediente.nowatermark" => "Ver expediente sin marca de agua (o quitarla)",
        "map.planos" => "Consultar planos (PDF / DWG) por homoclave",
        "map.planos.download" => "Descargar planos",
        "map.planos.print" => "Imprimir planos",
        "map.planos.nowatermark" => "Ver planos sin marca de agua (o quitarla)",
        "map.print" => "Imprimir mapa",
        "map.locate" => "Geolocalización GPS",
        "map.measure" => "Medir / dibujar",
        "map.upload" => "Subir SHP / KML / KMZ",
        "map.inspect" => "Inspección fotográfica Control Urbano",
        "inspections.manage" => "Gestionar inspecciones Control Urbano",
        "map.inspect.catastro" => "Inspección fotográfica Catastro",
        "inspections.catastro.manage" => "Gestionar inspecciones Catastro",
        "users.manage" => "Administrar usuarios y roles",
    ];
}

function mxli_default_role_seeds(): array
{
    $allLayers = ["*"];
    $allTools = array_keys(mxli_tool_permission_defs());
    $consultaTools = [
        "map.view",
        "map.search",
        "map.info",
        "map.print",
        "map.locate",
        "map.measure",
    ];
    $campoTools = array_merge($consultaTools, ["map.upload"]);
    $inspectorTools = [
        "map.view",
        "map.search",
        "map.locate",
        "map.inspect",
    ];
    $inspectorLayers = ["predios_mexicali_2025", "colonias"];
    $inspectorCatastroTools = [
        "map.view",
        "map.search",
        "map.info",
        "map.locate",
        "map.inspect.catastro",
    ];
    $inspectorCatastroLayers = ["predios_mexicali_2025", "colonias"];
    $deslindadorTools = [
        "map.view",
        "map.search",
        "map.info",
        "map.planos",
        "map.planos.download",
        "map.planos.print",
        "map.locate",
        "map.print",
        "map.measure",
    ];
    $deslindadorLayers = ["predios_mexicali_2025", "colonias"];

    return [
        [
            "slug" => "admin",
            "name" => "Administrador",
            "description" => "Acceso total al sistema",
            "is_system" => true,
            "permissions" => $allTools,
            "allowed_layers" => $allLayers,
        ],
        [
            "slug" => "consulta",
            "name" => "Consulta",
            "description" => "Mapa, búsqueda e impresión",
            "is_system" => true,
            "permissions" => $consultaTools,
            "allowed_layers" => $allLayers,
        ],
        [
            "slug" => "campo",
            "name" => "Campo",
            "description" => "Consulta + subir archivos",
            "is_system" => true,
            "permissions" => $campoTools,
            "allowed_layers" => $allLayers,
        ],
        [
            "slug" => "inspector",
            "name" => "Inspector Control Urbano",
            "description" => "Captura fotográfica Control Urbano",
            "is_system" => true,
            "permissions" => $inspectorTools,
            "allowed_layers" => $inspectorLayers,
        ],
        [
            "slug" => "inspector_catastro",
            "name" => "Inspector Catastro",
            "description" => "Captura fotográfica de peritos de Catastro",
            "is_system" => true,
            "permissions" => $inspectorCatastroTools,
            "allowed_layers" => $inspectorCatastroLayers,
        ],
        [
            "slug" => "perito_deslindador",
            "name" => "Perito deslindador",
            "description" => "Consulta de predios y planos catastrales (PDF / DWG) por homoclave",
            "is_system" => true,
            "permissions" => $deslindadorTools,
            "allowed_layers" => $deslindadorLayers,
        ],
    ];
}
