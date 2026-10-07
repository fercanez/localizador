<?php
declare(strict_types=1);

require_once dirname(__DIR__) . "/includes/init.php";
require_once dirname(__DIR__) . "/includes/auth.php";
require_once dirname(__DIR__) . "/includes/layer-catalog.php";
require_once dirname(__DIR__) . "/includes/planos.php";

try {
    $pdo = mxli_db();
    if (function_exists("mxli_ensure_auth_tables")) {
        mxli_ensure_auth_tables($pdo);
    }
    mxli_planos_grant_roles($pdo);
    echo "OK permisos map.planos (visor, descarga, impresión) y rol perito_deslindador\n";
} catch (Throwable $e) {
    fwrite(STDERR, "ERROR: " . $e->getMessage() . "\n");
    exit(1);
}
