<?php
declare(strict_types=1);

/**
 * Agrega columna expires_at a app_users.
 * Uso:
 *   php scripts/migrate_user_expiry.php
 */

require_once dirname(__DIR__) . "/includes/config.php";
require_once dirname(__DIR__) . "/includes/db.php";

try {
    $pdo = mxli_db();
    $pdo->exec("ALTER TABLE app_users ADD COLUMN IF NOT EXISTS expires_at DATE NULL");
    $pdo->exec(
        "COMMENT ON COLUMN app_users.expires_at IS
         'Último día de acceso (inclusive). NULL = sin vencimiento.'"
    );
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_app_users_expires_at ON app_users (expires_at)");

    $n = (int)$pdo->query("SELECT COUNT(*) FROM app_users WHERE expires_at IS NOT NULL")->fetchColumn();
    echo "OK columna expires_at lista\n";
    echo "  Usuarios con fecha de vencimiento: {$n}\n";
} catch (Throwable $e) {
    fwrite(STDERR, "ERROR: " . $e->getMessage() . "\n");
    exit(1);
}
