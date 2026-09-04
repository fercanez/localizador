<?php
declare(strict_types=1);

require_once dirname(__DIR__) . "/includes/init.php";
require_once dirname(__DIR__) . "/includes/auth.php";
require_once dirname(__DIR__) . "/includes/layer-catalog.php";

$pdo = mxli_db();
if (function_exists("mxli_ensure_auth_tables")) {
    mxli_ensure_auth_tables($pdo);
} elseif (function_exists("mxli_ensure_roles_table")) {
    mxli_ensure_roles_table($pdo);
}

$needed = [
    "map.expediente",
    "map.expediente.download",
    "map.expediente.print",
    "map.expediente.nowatermark",
];

$stmt = $pdo->query("SELECT slug, permissions FROM app_roles WHERE slug = 'admin' LIMIT 1");
$row = $stmt->fetch();
if (!$row) {
    echo "No hay rol admin\n";
    exit(1);
}

$perms = json_decode((string)$row["permissions"], true);
if (!is_array($perms)) {
    $perms = [];
}
$before = $perms;
foreach ($needed as $p) {
    if (!in_array($p, $perms, true)) {
        $perms[] = $p;
    }
}
foreach (array_keys(mxli_tool_permission_defs()) as $p) {
    if (!in_array($p, $perms, true)) {
        $perms[] = $p;
    }
}

if ($perms !== $before) {
    $upd = $pdo->prepare("UPDATE app_roles SET permissions = :p, updated_at = NOW() WHERE slug = :slug");
    $upd->execute([
        "p" => json_encode(array_values($perms), JSON_UNESCAPED_UNICODE),
        "slug" => (string)$row["slug"],
    ]);
    echo "OK admin actualizado con permisos de expediente\n";
} else {
    echo "OK admin ya tenia los permisos\n";
}