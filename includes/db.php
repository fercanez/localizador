<?php
declare(strict_types=1);

require_once __DIR__ . "/config.php";

/**
 * Conexión PDO a PostgreSQL (singleton).
 */
function mxli_db(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $dsn = mxli_config("db_dsn", "");
    $user = mxli_config("db_user", "");
    $pass = mxli_config("db_pass", "");

    if ($dsn === "" || $user === "") {
        throw new RuntimeException(
            "Base de datos no configurada. Defina db_dsn, db_user y db_pass en includes/config.local.php"
        );
    }

    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);

    return $pdo;
}
