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
            "key" => "red_unificada_de_mexicali",
            "title" => "Red Unificada",
            "id" => "red_unificada_chkbox",
            "var" => "red_unificada",
            "workspace" => "geonode",
            "datastore" => "geonode",
            "color" => "#b91c1c",
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
        [
            "key" => "fallas_geologicas",
            "title" => "Fallas geológicas",
            "id" => "fallas_geologicas_chkbox",
            "var" => "fallas_geologicas",
            "workspace" => "geonode",
            "datastore" => "geonode",
            "color" => "#7a1f1f",
        ],
    ];
}

function mxli_layer_keys(): array
{
    return array_column(mxli_layer_catalog(), "key");
}

function mxli_user_is_perito_deslindador(?array $user = null): bool
{
    if ($user === null && function_exists("mxli_current_user")) {
        $user = mxli_current_user();
    }
    if (!$user) {
        return false;
    }
    $role = mb_strtolower((string)($user["role"] ?? ""), "UTF-8");
    $label = mb_strtolower((string)($user["role_label"] ?? ""), "UTF-8");
    if ($role === "perito_deslindador" || str_contains($role, "deslind")) {
        return true;
    }
    return str_contains($label, "perito") && str_contains($label, "deslind");
}

function mxli_user_is_perito_valuador(?array $user = null): bool
{
    if ($user === null && function_exists("mxli_current_user")) {
        $user = mxli_current_user();
    }
    if (!$user) {
        return false;
    }
    $role = mb_strtolower((string)($user["role"] ?? ""), "UTF-8");
    $label = mb_strtolower((string)($user["role_label"] ?? ""), "UTF-8");
    if ($role === "perito_valuador" || str_contains($role, "valuad")) {
        return true;
    }
    return str_contains($label, "valuad");
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
        "map.inspect.ecologica" => "Inspección fotográfica Patrulla Ecológica",
        "inspections.ecologica.manage" => "Gestionar inspecciones Patrulla Ecológica",
        "users.manage" => "Administrar usuarios y roles",
    ];
}

function mxli_default_role_seeds(): array
{
    $allLayers = ["*"];
    $allTools = array_values(array_filter(
        array_keys(mxli_tool_permission_defs()),
        static function ($perm) {
            return $perm !== "map.inspect.ecologica" && $perm !== "inspections.ecologica.manage";
        }
    ));
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
    $patrullaTools = [
        "map.view",
        "map.search",
        "map.locate",
        "map.inspect.ecologica",
        "inspections.ecologica.manage",
    ];
    $patrullaLayers = ["predios_mexicali_2025", "colonias"];
    $deslindadorTools = [
        "map.view",
        "map.search",
        "map.info",
        "map.planos",
        "map.locate",
        "map.print",
        "map.measure",
    ];
    $deslindadorLayers = ["predios_mexicali_2025", "colonias", "red_unificada_de_mexicali"];
    $valuadorTools = [
        "map.view",
        "map.search",
        "map.info",
        "map.print",
        "map.locate",
        "map.measure",
    ];
    $valuadorLayers = [
        "predios_mexicali_2025",
        "colonias",
        "zonas_homogeneas_2017_2026_prop",
        "fallas_geologicas",
    ];

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
            "slug" => "patrulla_ecologica",
            "name" => "Patrulla Ecológica",
            "description" => "Captura y consulta fotográfica de la patrulla ecológica. No ve inspecciones de otras áreas.",
            "is_system" => true,
            "permissions" => $patrullaTools,
            "allowed_layers" => $patrullaLayers,
        ],
        [
            "slug" => "perito_deslindador",
            "name" => "Perito deslindador",
            "description" => "Consulta de predios y planos catastrales. Descarga e impresión se asignan aparte en el rol.",
            "is_system" => true,
            "permissions" => $deslindadorTools,
            "allowed_layers" => $deslindadorLayers,
        ],
        [
            "slug" => "perito_valuador",
            "name" => "Perito valuador",
            "description" => "Consulta de predios, zonas homogéneas y fallas geológicas. La capa de fallas no se comparte con otros roles.",
            "is_system" => true,
            "permissions" => $valuadorTools,
            "allowed_layers" => $valuadorLayers,
        ],
    ];
}

/**
 * Agrega Red Unificada a los roles de perito deslindador que ya existen.
 * No quita capas que el administrador haya dejado en el rol.
 */
function mxli_grant_red_unificada_layer(?PDO $pdo = null): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;

    $key = "red_unificada_de_mexicali";
    try {
        if (!function_exists("mxli_roles_table_exists") || !mxli_roles_table_exists()) {
            return;
        }
        $pdo = $pdo ?? mxli_db();
        $rows = $pdo->query("SELECT slug, name, allowed_layers FROM app_roles")->fetchAll();
        $upd = $pdo->prepare(
            "UPDATE app_roles
             SET allowed_layers = CAST(:layers AS jsonb), updated_at = NOW()
             WHERE slug = :slug"
        );
        foreach ($rows as $row) {
            $slug = (string)$row["slug"];
            $name = mb_strtolower((string)($row["name"] ?? ""), "UTF-8");
            $isDeslindador = $slug === "perito_deslindador"
                || str_contains($slug, "deslind")
                || str_contains($name, "deslind");
            if (!$isDeslindador) {
                continue;
            }
            $layers = mxli_decode_json_list($row["allowed_layers"] ?? []);
            if (in_array("*", $layers, true) || in_array($key, $layers, true)) {
                continue;
            }
            $layers[] = $key;
            $upd->execute([
                "layers" => json_encode(array_values($layers), JSON_UNESCAPED_UNICODE),
                "slug" => $slug,
            ]);
            if (function_exists("mxli_clear_role_cache")) {
                mxli_clear_role_cache($slug);
            }
        }
    } catch (Throwable $e) {
        // No bloquear el mapa si el alta de la capa en el rol falla.
    }
}

/**
 * Crea el rol Perito valuador si no hay ninguno cuyo slug o nombre contenga "valuad",
 * y agrega fallas_geologicas solo a esos roles. No la pone en consulta, campo ni admin
 * (el administrador ya ve todo con "*").
 */
function mxli_grant_fallas_geologicas_layer(?PDO $pdo = null): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;

    $key = "fallas_geologicas";
    try {
        if (!function_exists("mxli_roles_table_exists") || !mxli_roles_table_exists()) {
            return;
        }
        $pdo = $pdo ?? mxli_db();
        $rows = $pdo->query("SELECT slug, name, allowed_layers FROM app_roles")->fetchAll();
        $found = false;
        $upd = $pdo->prepare(
            "UPDATE app_roles
             SET allowed_layers = CAST(:layers AS jsonb), updated_at = NOW()
             WHERE slug = :slug"
        );
        foreach ($rows as $row) {
            $slug = (string)$row["slug"];
            $slugL = mb_strtolower($slug, "UTF-8");
            $name = mb_strtolower((string)($row["name"] ?? ""), "UTF-8");
            $isValuador = $slugL === "perito_valuador"
                || str_contains($slugL, "valuad")
                || str_contains($name, "valuad");
            if (!$isValuador || $slugL === "admin") {
                continue;
            }
            $found = true;
            $layers = mxli_decode_json_list($row["allowed_layers"] ?? []);
            if (in_array("*", $layers, true) || in_array($key, $layers, true)) {
                continue;
            }
            $layers[] = $key;
            $upd->execute([
                "layers" => json_encode(array_values($layers), JSON_UNESCAPED_UNICODE),
                "slug" => $slug,
            ]);
            if (function_exists("mxli_clear_role_cache")) {
                mxli_clear_role_cache($slug);
            }
        }

        if ($found) {
            return;
        }

        $seed = null;
        foreach (mxli_default_role_seeds() as $row) {
            if (($row["slug"] ?? "") === "perito_valuador") {
                $seed = $row;
                break;
            }
        }
        if ($seed === null) {
            return;
        }
        $ins = $pdo->prepare(
            "INSERT INTO app_roles (slug, name, description, is_system, permissions, allowed_layers)
             VALUES (:slug, :name, :description, TRUE, CAST(:permissions AS jsonb), CAST(:layers AS jsonb))
             ON CONFLICT (slug) DO NOTHING"
        );
        $ins->execute([
            "slug" => $seed["slug"],
            "name" => $seed["name"],
            "description" => $seed["description"],
            "permissions" => json_encode(array_values($seed["permissions"]), JSON_UNESCAPED_UNICODE),
            "layers" => json_encode(array_values($seed["allowed_layers"]), JSON_UNESCAPED_UNICODE),
        ]);
        if (function_exists("mxli_clear_role_cache")) {
            mxli_clear_role_cache("perito_valuador");
        }
    } catch (Throwable $e) {
        // No bloquear el mapa si el alta de la capa falla.
    }
}

/** Crea el rol Patrulla Ecológica si todavía no existe. No lo agrega al administrador. */
function mxli_grant_patrulla_ecologica_role(?PDO $pdo = null): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;

    try {
        if (!function_exists("mxli_roles_table_exists") || !mxli_roles_table_exists()) {
            return;
        }
        $pdo = $pdo ?? mxli_db();
        $seed = null;
        foreach (mxli_default_role_seeds() as $row) {
            if (($row["slug"] ?? "") === "patrulla_ecologica") {
                $seed = $row;
                break;
            }
        }
        if ($seed === null) {
            return;
        }
        $ins = $pdo->prepare(
            "INSERT INTO app_roles (slug, name, description, is_system, permissions, allowed_layers)
             VALUES (:slug, :name, :description, TRUE, CAST(:permissions AS jsonb), CAST(:layers AS jsonb))
             ON CONFLICT (slug) DO NOTHING"
        );
        $ins->execute([
            "slug" => $seed["slug"],
            "name" => $seed["name"],
            "description" => $seed["description"],
            "permissions" => json_encode(array_values($seed["permissions"]), JSON_UNESCAPED_UNICODE),
            "layers" => json_encode(array_values($seed["allowed_layers"]), JSON_UNESCAPED_UNICODE),
        ]);
        if (function_exists("mxli_clear_role_cache")) {
            mxli_clear_role_cache("patrulla_ecologica");
        }
    } catch (Throwable $e) {
        // No bloquear el mapa si el alta del rol falla.
    }
}
