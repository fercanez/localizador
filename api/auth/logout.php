<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . "/includes/init.php";
require_once dirname(__DIR__, 2) . "/includes/auth.php";

mxli_logout();

header("Location: ../../login.php");
exit;
