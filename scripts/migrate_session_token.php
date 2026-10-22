<?php
declare(strict_types=1);

/**
 * Columnas de sesión: token, last_activity, ip.
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
        "ALTER TABLE app_users
         ADD COLUMN IF NOT EXISTS session_last_activity TIMESTAMPTZ NULL"
    );
    $pdo->exec(
        "ALTER TABLE app_users
         ADD COLUMN IF NOT EXISTS session_ip VARCHAR(45) NULL"
    );
    $pdo->exec(
        "COMMENT ON COLUMN app_users.session_token IS
         'Token de sesión activa. Un solo valor vigente por usuario; NULL = sin sesión.'"
    );

    $n = (int)$pdo->query(
        "SELECT COUNT(*) FROM app_users WHERE session_token IS NOT NULL"
    )->fetchColumn();

    echo "OK columnas session_token / session_last_activity / session_ip listas\n";
    echo "  Usuarios con sesión activa (token no nulo): {$n}\n";
    echo "  Política por defecto: lock (rechaza 2.º login; admin siempre entra)\n";
    echo "  Idle: session_idle_seconds => 300 (5 min)\n";
    echo "  IP bind: session_bind_ip => true\n";
    echo "  Desbloqueo UI: Usuarios → Cerrar sesión remota\n";
} catch (Throwable $e) {
    fwrite(STDERR, "ERROR: " . $e->getMessage() . "\n");
    exit(1);
}
