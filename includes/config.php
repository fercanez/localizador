<?php
declare(strict_types=1);

$MXLI_CONFIG = require __DIR__ . "/config.example.php";

$configLocal = __DIR__ . "/config.local.php";
if (is_file($configLocal)) {
    $local = require $configLocal;
    if (is_array($local)) {
        $MXLI_CONFIG = array_merge($MXLI_CONFIG, $local);
    }
}

function mxli_config(string $key, string $default = ""): string
{
    global $MXLI_CONFIG;
    $value = $MXLI_CONFIG[$key] ?? $default;
    return is_string($value) ? $value : $default;
}
