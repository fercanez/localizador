<?php
declare(strict_types=1);

/**
 * Tabla de inspecciones fotográficas + rol inspector.
 * Uso: php scripts/migrate_inspection_photos.php
 */

require_once dirname(__DIR__) . "/includes/config.php";
require_once dirname(__DIR__) . "/includes/db.php";
require_once dirname(__DIR__) . "/includes/layer-catalog.php";
require_once dirname(__DIR__) . "/includes/inspections.php";

try {
    $pdo = mxli_db();
    mxli_ensure_inspections_table($pdo);
    echo "OK app_inspection_photos\n";

    // Asegurar permisos nuevos en roles del sistema
    require_once dirname(__DIR__) . "/includes/auth.php";

    $inspectorTools = [
        "map.view",
        "map.search",
        "map.locate",
        "map.inspect",
    ];
    $inspectorLayers = ["predios_mexicali_2025", "colonias"];

    $ins = $pdo->prepare(
        "INSERT INTO app_roles (slug, name, description, is_system, permissions, allowed_layers)
         VALUES (:slug, :name, :description, TRUE, CAST(:permissions AS jsonb), CAST(:layers AS jsonb))
         ON CONFLICT (slug) DO NOTHING"
    );
    $ins->execute([
        "slug" => "inspector",
        "name" => "Inspector",
        "description" => "Captura fotográfica de predios en campo",
        "permissions" => json_encode($inspectorTools, JSON_UNESCAPED_UNICODE),
        "layers" => json_encode($inspectorLayers, JSON_UNESCAPED_UNICODE),
    ]);
    echo "  seed: inspector (si no existía)\n";

    foreach (mxli_default_role_seeds() as $seed) {
        if ($seed["slug"] !== "admin") {
            continue;
        }
        $upd = $pdo->prepare(
            "UPDATE app_roles
             SET permissions = CAST(:permissions AS jsonb),
                 allowed_layers = CAST(:layers AS jsonb),
                 updated_at = NOW()
             WHERE slug = 'admin'"
        );
        $upd->execute([
            "permissions" => json_encode(array_values($seed["permissions"]), JSON_UNESCAPED_UNICODE),
            "layers" => json_encode(["*"], JSON_UNESCAPED_UNICODE),
        ]);
        echo "  admin: permisos actualizados\n";
    }
} catch (Throwable $e) {
    fwrite(STDERR, "ERROR: " . $e->getMessage() . "\n");
    exit(1);
}
