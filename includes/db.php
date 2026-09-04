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

    $pdo = mxli_pdo_connect($dsn, $user, $pass);

    return $pdo;
}

function mxli_pdo_connect(string $dsn, string $user, string $pass): PDO
{
    return new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
}

function mxli_geonode_dsn(): string
{
    $explicit = mxli_config("geonode_data_dsn", "");
    if ($explicit !== "") {
        return $explicit;
    }

    $appDsn = mxli_config("db_dsn", "");
    if ($appDsn !== "" && preg_match("/dbname=[^;]+/", $appDsn)) {
        return preg_replace("/dbname=[^;]+/", "dbname=geonode_data", $appDsn, 1);
    }

    return "pgsql:host=127.0.0.1;port=5432;dbname=geonode_data";
}

/**
 * Conexión a geonode_data (padrón). Mismos usuario/clave que la app, salvo override.
 */
function mxli_geonode_db(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $dsn = mxli_geonode_dsn();
    $user = mxli_config("geonode_data_user", "") ?: mxli_config("db_user", "");
    $pass = mxli_config("geonode_data_pass", "");
    if ($pass === "") {
        $pass = mxli_config("db_pass", "");
    }

    if ($dsn === "" || $user === "") {
        throw new RuntimeException("No hay DSN/usuario para geonode_data.");
    }

    $pdo = mxli_pdo_connect($dsn, $user, $pass);

    return $pdo;
}
