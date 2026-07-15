<?php
declare(strict_types=1);

/**
 * Aplica migración de roles admin/consulta/campo.
 * Uso en el servidor:
 *   php scripts/migrate_roles.php
 */

require_once dirname(__DIR__) . "/includes/config.php";
require_once dirname(__DIR__) . "/includes/db.php";

try {
    $pdo = mxli_db();
    $pdo->exec("ALTER TABLE app_users DROP CONSTRAINT IF EXISTS app_users_role_check");
    $pdo->exec("UPDATE app_users SET role = 'consulta' WHERE role = 'user' OR role IS NULL OR role = ''");
    $pdo->exec("ALTER TABLE app_users ALTER COLUMN role SET DEFAULT 'consulta'");
    $pdo->exec(
        "ALTER TABLE app_users
         ADD CONSTRAINT app_users_role_check
         CHECK (role IN ('admin', 'consulta', 'campo'))"
    );

    $count = $pdo->query(
        "SELECT role, COUNT(*) AS n FROM app_users GROUP BY role ORDER BY role"
    )->fetchAll();

    echo "OK migración de roles aplicada\n";
    foreach ($count as $row) {
        echo "  " . $row["role"] . ": " . $row["n"] . "\n";
    }
} catch (Throwable $e) {
    fwrite(STDERR, "ERROR: " . $e->getMessage() . "\n");
    exit(1);
}
