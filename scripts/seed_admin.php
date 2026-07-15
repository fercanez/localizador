<?php
declare(strict_types=1);

/**
 * Crea (o restablece) el usuario admin.
 * Uso en el servidor:
 *   php scripts/seed_admin.php
 *   php scripts/seed_admin.php admin MiPasswordSegura
 */

require_once dirname(__DIR__) . "/includes/config.php";
require_once dirname(__DIR__) . "/includes/db.php";

$username = $argv[1] ?? "admin";
$password = $argv[2] ?? "AdminMxli2026!";
$fullName = $argv[3] ?? "Administrador";

try {
    $pdo = mxli_db();
    $hash = password_hash($password, PASSWORD_DEFAULT);

    $stmt = $pdo->prepare(
        "INSERT INTO app_users (username, password_hash, full_name, role, is_active)
         VALUES (:username, :hash, :full_name, 'admin', TRUE)
         ON CONFLICT (username) DO UPDATE
           SET password_hash = EXCLUDED.password_hash,
               full_name = EXCLUDED.full_name,
               role = 'admin',
               is_active = TRUE,
               updated_at = NOW()
         RETURNING id, username, role"
    );
    $stmt->execute([
        "username" => $username,
        "hash" => $hash,
        "full_name" => $fullName,
    ]);
    $row = $stmt->fetch();

    echo "OK usuario admin listo\n";
    echo "  id: " . $row["id"] . "\n";
    echo "  usuario: " . $row["username"] . "\n";
    echo "  rol: " . $row["role"] . "\n";
    echo "  password: " . $password . "\n";
    echo "Cambie la contraseña después del primer acceso.\n";
} catch (Throwable $e) {
    fwrite(STDERR, "ERROR: " . $e->getMessage() . "\n");
    exit(1);
}
