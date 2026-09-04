<?php
declare(strict_types=1);

/**
 * Columna inspection_area + rol inspector_catastro + perms en admin.
 * Uso: php scripts/migrate_inspector_catastro.php
 */

require_once dirname(__DIR__) . "/includes/config.php";
require_once dirname(__DIR__) . "/includes/db.php";
require_once dirname(__DIR__) . "/includes/layer-catalog.php";
require_once dirname(__DIR__) . "/includes/inspections.php";
require_once dirname(__DIR__) . "/includes/auth.php";

try {
    $pdo = mxli_db();
    if (function_exists("mxli_ensure_auth_tables")) {
        mxli_ensure_auth_tables($pdo);
    } elseif (function_exists("mxli_ensure_roles_table")) {
        mxli_ensure_roles_table($pdo);
    }

    mxli_ensure_inspections_table($pdo);
    echo "OK inspection_area column / table\n";

    $seeds = mxli_default_role_seeds();
    $bySlug = [];
    foreach ($seeds as $seed) {
        $bySlug[$seed["slug"]] = $seed;
    }

    if (!isset($bySlug["inspector_catastro"])) {
        throw new RuntimeException("Falta seed inspector_catastro en layer-catalog");
    }

    $upsert = $pdo->prepare(
        "INSERT INTO app_roles (slug, name, description, is_system, permissions, allowed_layers)
         VALUES (:slug, :name, :description, TRUE, CAST(:permissions AS jsonb), CAST(:layers AS jsonb))
         ON CONFLICT (slug) DO UPDATE SET
           name = EXCLUDED.name,
           description = EXCLUDED.description,
           permissions = EXCLUDED.permissions,
           allowed_layers = EXCLUDED.allowed_layers,
           updated_at = NOW()"
    );

    $cat = $bySlug["inspector_catastro"];
    $upsert->execute([
        "slug" => $cat["slug"],
        "name" => $cat["name"],
        "description" => $cat["description"],
        "permissions" => json_encode(array_values($cat["permissions"]), JSON_UNESCAPED_UNICODE),
        "layers" => json_encode(array_values($cat["allowed_layers"]), JSON_UNESCAPED_UNICODE),
    ]);
    echo "OK upsert role inspector_catastro\n";

    // Refresh urbano inspector name/description from seed if present
    if (isset($bySlug["inspector"])) {
        $urb = $bySlug["inspector"];
        $upsert->execute([
            "slug" => $urb["slug"],
            "name" => $urb["name"],
            "description" => $urb["description"],
            "permissions" => json_encode(array_values($urb["permissions"]), JSON_UNESCAPED_UNICODE),
            "layers" => json_encode(array_values($urb["allowed_layers"]), JSON_UNESCAPED_UNICODE),
        ]);
        echo "OK upsert role inspector (Control Urbano)\n";
    }

    $needed = [
        "map.inspect",
        "map.inspect.catastro",
        "inspections.manage",
        "inspections.catastro.manage",
    ];
    foreach (array_keys(mxli_tool_permission_defs()) as $p) {
        if (!in_array($p, $needed, true)) {
            $needed[] = $p;
        }
    }

    $stmt = $pdo->query("SELECT slug, permissions FROM app_roles WHERE slug = 'admin' LIMIT 1");
    $row = $stmt->fetch();
    if (!$row) {
        echo "WARN: no hay rol admin\n";
    } else {
        $perms = json_decode((string)$row["permissions"], true);
        if (!is_array($perms)) {
            $perms = [];
        }
        $before = $perms;
        foreach ($needed as $p) {
            if (!in_array($p, $perms, true)) {
                $perms[] = $p;
            }
        }
        if ($perms !== $before) {
            $upd = $pdo->prepare(
                "UPDATE app_roles SET permissions = CAST(:p AS jsonb), updated_at = NOW() WHERE slug = 'admin'"
            );
            $upd->execute([
                "p" => json_encode(array_values($perms), JSON_UNESCAPED_UNICODE),
            ]);
            echo "OK admin permisos actualizados (catastro + tools)\n";
        } else {
            echo "OK admin ya tenía los permisos\n";
        }
    }

    echo "DONE migrate_inspector_catastro\n";
} catch (Throwable $e) {
    fwrite(STDERR, "ERROR: " . $e->getMessage() . "\n");
    exit(1);
}