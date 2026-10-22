<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . "/includes/init.php";
require_once dirname(__DIR__, 2) . "/includes/auth.php";

mxli_logout();

$accept = (string)($_SERVER["HTTP_ACCEPT"] ?? "");
$wantsJson = str_contains($accept, "application/json")
    || strcasecmp((string)($_SERVER["HTTP_X_REQUESTED_WITH"] ?? ""), "XMLHttpRequest") === 0
    || ($_SERVER["REQUEST_METHOD"] ?? "") === "POST";

if ($wantsJson) {
    mxli_json_response(["ok" => true, "authenticated" => false]);
}

header("Location: ../../login.php");
exit;
