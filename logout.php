<?php
declare(strict_types=1);

require_once __DIR__ . "/includes/init.php";
require_once __DIR__ . "/includes/auth.php";

mxli_logout();

header("Location: login.php");
exit;
