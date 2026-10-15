<?php
declare(strict_types=1);

require_once dirname(__DIR__) . "/includes/init.php";
require_once dirname(__DIR__) . "/includes/auth.php";
require_once dirname(__DIR__) . "/includes/layer-catalog.php";
require_once dirname(__DIR__) . "/includes/deslindes.php";

try {
    $pdo = mxli_db();
    if (function_exists("mxli_ensure_auth_tables")) {
        mxli_ensure_auth_tables($pdo);
    }
    $n = mxli_deslindes_grant_roles($pdo);
    echo "OK permiso map.deslindes agregado a {$n} rol(es) de perito deslindador\n";
} catch (Throwable $e) {
    fwrite(STDERR, "ERROR: " . $e->getMessage() . "\n");
    exit(1);
}
