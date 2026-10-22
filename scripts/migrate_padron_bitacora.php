<?php
declare(strict_types=1);

/**
 * Tabla app_padron_consultas (bitácora de consultas al padrón).
 * Uso: php scripts/migrate_padron_bitacora.php
 */

require_once dirname(__DIR__) . "/includes/config.php";
require_once dirname(__DIR__) . "/includes/db.php";
require_once dirname(__DIR__) . "/includes/padron-log.php";

try {
    $pdo = mxli_db();
    mxli_padron_log_ensure($pdo);
    echo "OK app_padron_consultas\n";
} catch (Throwable $e) {
    fwrite(STDERR, "ERROR: " . $e->getMessage() . "\n");
    exit(1);
}
