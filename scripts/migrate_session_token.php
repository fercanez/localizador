<?php
declare(strict_types=1);

/**
 * Agrega columna session_token a app_users (sesión única por usuario).
 * Uso:
 *   php scripts/migrate_session_token.php
 */

require_once dirname(__DIR__) . "/includes/config.php";
require_once dirname(__DIR__) . "/includes/db.php";

try {
    $pdo = mxli_db();
    $pdo->exec(
        "ALTER TABLE app_users
         ADD COLUMN IF NOT EXISTS session_token VARCHAR(64) NULL"
    );
    $pdo->exec(
        "COMMENT ON COLUMN app_users.session_token IS
         'Token de sesión activa. Un solo valor vigente por usuario; NULL = sin sesión.'"
    );

    $n = (int)$pdo->query(
        "SELECT COUNT(*) FROM app_users WHERE session_token IS NOT NULL"
    )->fetchColumn();

    echo "OK columna session_token lista\n";
    echo "  Usuarios con sesión activa (token no nulo): {$n}\n";
    echo "  Política por defecto: invalidate (nuevo login cierra la anterior)\n";
    echo "  Opcional lock: session_policy => \"lock\" en config.local.php\n";
} catch (Throwable $e) {
    fwrite(STDERR, "ERROR: " . $e->getMessage() . "\n");
    exit(1);
}
