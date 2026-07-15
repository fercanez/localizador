<?php
declare(strict_types=1);

require_once dirname(__DIR__) . "/includes/init.php";
require_once dirname(__DIR__) . "/includes/auth.php";

mxli_require_admin();
$me = mxli_current_user();
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Usuarios | Catastro Mexicali</title>
  <link rel="stylesheet" href="../css/auth-app.css">
  <link rel="stylesheet" href="../css/admin-users.css">
</head>
<body class="admin-body">
  <header class="admin-header">
    <div>
      <a class="admin-back" href="../index.php">← Volver al mapa</a>
      <h1>Asignación de usuarios</h1>
      <p>Administrador: <strong><?= htmlspecialchars($me["username"], ENT_QUOTES, "UTF-8") ?></strong></p>
    </div>
    <a class="admin-logout" href="../logout.php">Cerrar sesión</a>
  </header>

  <main class="admin-main">
    <section class="admin-panel">
      <h2>Nuevo usuario</h2>
      <form id="user-create-form" class="admin-form">
        <label>Usuario
          <input type="text" name="username" required minlength="3">
        </label>
        <label>Nombre completo
          <input type="text" name="full_name">
        </label>
        <label>Correo
          <input type="email" name="email">
        </label>
        <label>Contraseña
          <input type="password" name="password" required minlength="6">
        </label>
        <label>Rol
          <select name="role">
            <option value="user">Usuario</option>
            <option value="admin">Administrador</option>
          </select>
        </label>
        <button type="submit" class="auth-btn">Crear usuario</button>
      </form>
      <p id="create-msg" class="admin-msg" hidden></p>
    </section>

    <section class="admin-panel admin-panel--wide">
      <div class="admin-panel__head">
        <h2>Usuarios registrados</h2>
        <button type="button" id="btn-refresh" class="admin-secondary">Actualizar</button>
      </div>
      <div class="admin-table-wrap">
        <table class="admin-table" id="users-table">
          <thead>
            <tr>
              <th>Usuario</th>
              <th>Nombre</th>
              <th>Rol</th>
              <th>Activo</th>
              <th>Último acceso</th>
              <th>Acciones</th>
            </tr>
          </thead>
          <tbody></tbody>
        </table>
      </div>
      <p id="list-msg" class="admin-msg" hidden></p>
    </section>
  </main>

  <dialog id="edit-dialog" class="admin-dialog">
    <form method="dialog" id="user-edit-form" class="admin-form">
      <h3>Editar usuario</h3>
      <input type="hidden" name="id" id="edit-id">
      <p id="edit-username" class="admin-edit-user"></p>
      <label>Nombre completo
        <input type="text" name="full_name" id="edit-full-name">
      </label>
      <label>Correo
        <input type="email" name="email" id="edit-email">
      </label>
      <label>Nueva contraseña (opcional)
        <input type="password" name="password" id="edit-password" minlength="6" placeholder="Dejar vacío para no cambiar">
      </label>
      <label>Rol
        <select name="role" id="edit-role">
          <option value="user">Usuario</option>
          <option value="admin">Administrador</option>
        </select>
      </label>
      <label class="admin-check">
        <input type="checkbox" name="is_active" id="edit-active">
        Usuario activo
      </label>
      <div class="admin-dialog-actions">
        <button type="submit" value="cancel" class="admin-secondary">Cancelar</button>
        <button type="submit" value="save" class="auth-btn">Guardar</button>
      </div>
    </form>
  </dialog>

  <script src="../js/admin-users.js"></script>
</body>
</html>
