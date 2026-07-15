<?php
declare(strict_types=1);

/**
 * Crea app_roles y siembra admin/consulta/campo.
 * Uso:
 *   php scripts/migrate_custom_roles.php
 */

require_once dirname(__DIR__) . "/includes/config.php";
require_once dirname(__DIR__) . "/includes/db.php";
require_once dirname(__DIR__) . "/includes/layer-catalog.php";

try {
    $pdo = mxli_db();

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS app_roles (
            slug            VARCHAR(80)  PRIMARY KEY,
            name            VARCHAR(120) NOT NULL,
            description     VARCHAR(255) NOT NULL DEFAULT '',
            is_system       BOOLEAN      NOT NULL DEFAULT FALSE,
            permissions     JSONB        NOT NULL DEFAULT '[]'::jsonb,
            allowed_layers  JSONB        NOT NULL DEFAULT '[\"*\"]'::jsonb,
            created_at      TIMESTAMPTZ  NOT NULL DEFAULT NOW(),
            updated_at      TIMESTAMPTZ  NOT NULL DEFAULT NOW()
        )"
    );
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_app_roles_system ON app_roles (is_system)");
    $pdo->exec("ALTER TABLE app_users DROP CONSTRAINT IF EXISTS app_users_role_check");
    $pdo->exec("UPDATE app_users SET role = 'consulta' WHERE role = 'user' OR role IS NULL OR role = ''");

    $insert = $pdo->prepare(
        "INSERT INTO app_roles (slug, name, description, is_system, permissions, allowed_layers)
         VALUES (:slug, :name, :description, :is_system, CAST(:permissions AS jsonb), CAST(:layers AS jsonb))
         ON CONFLICT (slug) DO NOTHING"
    );

    foreach (mxli_default_role_seeds() as $seed) {
        $insert->execute([
            "slug" => $seed["slug"],
            "name" => $seed["name"],
            "description" => $seed["description"],
            "is_system" => $seed["is_system"] ? "true" : "false",
            "permissions" => json_encode(array_values($seed["permissions"]), JSON_UNESCAPED_UNICODE),
            "layers" => json_encode(array_values($seed["allowed_layers"]), JSON_UNESCAPED_UNICODE),
        ]);
        echo "  seed: {$seed["slug"]}\n";
    }

    // Admin siempre con acceso total
    $admin = mxli_default_role_seeds()[0];
    $updAdmin = $pdo->prepare(
        "UPDATE app_roles
         SET name = :name,
             description = :description,
             is_system = TRUE,
             permissions = CAST(:permissions AS jsonb),
             allowed_layers = CAST(:layers AS jsonb),
             updated_at = NOW()
         WHERE slug = 'admin'"
    );
    $updAdmin->execute([
        "name" => $admin["name"],
        "description" => $admin["description"],
        "permissions" => json_encode(array_values($admin["permissions"]), JSON_UNESCAPED_UNICODE),
        "layers" => json_encode(["*"], JSON_UNESCAPED_UNICODE),
    ]);

    $count = (int)$pdo->query("SELECT COUNT(*) FROM app_roles")->fetchColumn();
    echo "OK app_roles listo ({$count} roles)\n";
} catch (Throwable $e) {
    fwrite(STDERR, "ERROR: " . $e->getMessage() . "\n");
    exit(1);
}
