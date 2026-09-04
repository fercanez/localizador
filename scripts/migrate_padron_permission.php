<?php
declare(strict_types=1);

/**
 * Permiso map.padron + override por usuario (can_view_padron).
 * Uso: php scripts/migrate_padron_permission.php
 */

require_once dirname(__DIR__) . "/includes/config.php";
require_once dirname(__DIR__) . "/includes/db.php";
require_once dirname(__DIR__) . "/includes/layer-catalog.php";
require_once dirname(__DIR__) . "/includes/auth.php";

try {
    $pdo = mxli_db();

    $pdo->exec(
        "ALTER TABLE app_users
         ADD COLUMN IF NOT EXISTS can_view_padron BOOLEAN NOT NULL DEFAULT FALSE"
    );
    echo "OK columna app_users.can_view_padron\n";

    // Asegurar que el rol admin tenga map.padron (y el resto de tools actuales)
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
        echo "  admin: permisos actualizados (incluye map.padron)\n";
    }

    // Si admin no existía en app_roles, sembrar desde defaults
    $exists = $pdo->query("SELECT 1 FROM app_roles WHERE slug = 'admin' LIMIT 1")->fetchColumn();
    if (!$exists) {
        echo "  aviso: no hay rol admin en app_roles; ejecute también migrate_custom_roles.php\n";
    }

    echo "OK migración padrón/permisos lista\n";
    echo "Para habilitar a un usuario concreto:\n";
    echo "  UPDATE app_users SET can_view_padron = TRUE WHERE username = 'usuario';\n";
    echo "O en Admin → Usuarios (checkbox) / Roles y permisos (herramienta «Ver nombre del contribuyente»).\n";
} catch (Throwable $e) {
    fwrite(STDERR, "ERROR: " . $e->getMessage() . "\n");
    exit(1);
}
