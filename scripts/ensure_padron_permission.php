<?php
declare(strict_types=1);
/**
 * Asegura map.padron en rol admin y en roles con users.manage.
 */
require_once dirname(__DIR__) . "/includes/init.php";
require_once dirname(__DIR__) . "/includes/auth.php";

$pdo = mxli_db();
$rows = $pdo->query("SELECT slug, permissions FROM app_roles")->fetchAll(PDO::FETCH_ASSOC);
$updated = 0;
foreach ($rows as $row) {
    $slug = (string)($row["slug"] ?? "");
    $perms = $row["permissions"];
    if (is_string($perms)) {
        $decoded = json_decode($perms, true);
        $perms = is_array($decoded) ? $decoded : [];
    }
    if (!is_array($perms)) {
        $perms = [];
    }

    $shouldHave =
        $slug === "admin" ||
        in_array("users.manage", $perms, true);

    if (!$shouldHave || in_array("map.padron", $perms, true)) {
        continue;
    }

    $perms[] = "map.padron";
    $perms = array_values(array_unique($perms));
    $stmt = $pdo->prepare("UPDATE app_roles SET permissions = CAST(:p AS jsonb) WHERE slug = :s");
    $stmt->execute([
        "p" => json_encode($perms, JSON_UNESCAPED_UNICODE),
        "s" => $slug,
    ]);
    $updated++;
    echo "updated role={$slug}\n";
}
echo "done updated={$updated}\n";
