<?php

if (session_status() === PHP_SESSION_NONE) {
  session_start();
}

$page = basename($_SERVER["REQUEST_URI"]);
$uriSegments = explode("/", parse_url($_SERVER["REQUEST_URI"], PHP_URL_PATH));

$root_directory = "/";
require_once __DIR__ . "/config.php";
include "php_functions.php";
?>