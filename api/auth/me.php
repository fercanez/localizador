<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . "/includes/init.php";
require_once dirname(__DIR__, 2) . "/includes/auth.php";

$user = mxli_current_user();
if ($user === null) {
    mxli_json_response(["ok" => false, "authenticated" => false], 401);
}

mxli_json_response([
    "ok" => true,
    "authenticated" => true,
    "user" => $user,
    "permissions" => mxli_user_permissions($user),
    "allowed_layers" => mxli_allowed_layers($user),
    "roles" => mxli_role_labels(),
]);
