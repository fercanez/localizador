<?php
declare(strict_types=1);

require_once dirname(__DIR__) . "/includes/init.php";
require_once dirname(__DIR__) . "/includes/auth.php";
require_once dirname(__DIR__) . "/includes/layer-catalog.php";

mxli_require_admin();
$me = mxli_current_user();
$toolDefs = mxli_tool_permission_defs();
$layerCatalog = mxli_layer_catalog();
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Usuarios y roles | Catastro Mexicali</title>
  <link rel="stylesheet" href="../css/auth-app.css">
  <link rel="stylesheet" href="../css/admin-users.css">
</head>
<body class="admin-body">
  <header class="admin-header">
    <div>
      <a class="admin-back" href="../index.php">← Volver al mapa</a>
      <h1>Usuarios y roles</h1>
      <p>Administrador: <strong><?= htmlspecialchars($me["username"], ENT_QUOTES, "UTF-8") ?></strong></p>
    </div>
    <a class="admin-logout" href="../logout.php">Cerrar sesión</a>
  </header>

  <nav class="admin-tabs" role="tablist">
    <button type="button" class="admin-tab is-active" data-tab="users">Usuarios</button>
    <button type="button" class="admin-tab" data-tab="roles">Roles y permisos</button>
  </nav>

  <main class="admin-main">
    <div class="admin-tab-panel is-active" id="tab-users" data-panel="users">
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
            <select name="role" id="create-role"></select>
          </label>
          <label>Vence el (opcional)
            <input type="date" name="expires_at">
            <small>Vacío = sin vencimiento. Ese día aún puede entrar; al día siguiente ya no.</small>
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
                <th>Vence</th>
                <th>Último acceso</th>
                <th>Acciones</th>
              </tr>
            </thead>
            <tbody></tbody>
          </table>
        </div>
        <p id="list-msg" class="admin-msg" hidden></p>
      </section>
    </div>

    <div class="admin-tab-panel" id="tab-roles" data-panel="roles" hidden>
      <section class="admin-panel admin-panel--wide">
        <div class="admin-panel__head">
          <h2>Roles</h2>
          <button type="button" id="btn-new-role" class="auth-btn">Nuevo rol</button>
        </div>
        <div class="admin-table-wrap">
          <table class="admin-table" id="roles-table">
            <thead>
              <tr>
                <th>Rol</th>
                <th>Descripción</th>
                <th>Permisos</th>
                <th>Capas</th>
                <th>Acciones</th>
              </tr>
            </thead>
            <tbody></tbody>
          </table>
        </div>
        <p id="roles-msg" class="admin-msg" hidden></p>
      </section>
    </div>
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
        <select name="role" id="edit-role"></select>
      </label>
      <label>Vence el (opcional)
        <input type="date" name="expires_at" id="edit-expires">
        <small>Vacío = sin vencimiento</small>
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

  <dialog id="role-dialog" class="admin-dialog admin-dialog--wide">
    <form method="dialog" id="role-form" class="admin-form">
      <h3 id="role-dialog-title">Nuevo rol</h3>
      <input type="hidden" name="slug" id="role-slug">
      <input type="hidden" id="role-mode" value="create">
      <label>Nombre
        <input type="text" name="name" id="role-name" required minlength="2">
      </label>
      <label>Descripción
        <input type="text" name="description" id="role-description" placeholder="Opcional">
      </label>

      <fieldset class="admin-fieldset">
        <legend>Herramientas</legend>
        <div class="admin-check-grid" id="role-tools">
          <?php foreach ($toolDefs as $perm => $label): ?>
            <label class="admin-check">
              <input type="checkbox"
                class="role-tool"
                value="<?= htmlspecialchars($perm, ENT_QUOTES, "UTF-8") ?>"
                data-perm="<?= htmlspecialchars($perm, ENT_QUOTES, "UTF-8") ?>"
                <?= $perm === "map.view" ? "checked disabled" : "" ?>
                <?= $perm === "users.manage" ? "data-admin-only=\"1\"" : "" ?>>
              <?= htmlspecialchars($label, ENT_QUOTES, "UTF-8") ?>
            </label>
          <?php endforeach; ?>
        </div>
      </fieldset>

      <fieldset class="admin-fieldset">
        <legend>Capas visibles</legend>
        <label class="admin-check">
          <input type="checkbox" id="role-layers-all">
          Todas las capas
        </label>
        <div class="admin-check-grid" id="role-layers">
          <?php foreach ($layerCatalog as $layer): ?>
            <label class="admin-check">
              <input type="checkbox"
                class="role-layer"
                value="<?= htmlspecialchars($layer["key"], ENT_QUOTES, "UTF-8") ?>">
              <?= htmlspecialchars($layer["title"], ENT_QUOTES, "UTF-8") ?>
            </label>
          <?php endforeach; ?>
        </div>
      </fieldset>

      <div class="admin-dialog-actions">
        <button type="submit" value="cancel" class="admin-secondary">Cancelar</button>
        <button type="submit" value="save" class="auth-btn">Guardar rol</button>
      </div>
    </form>
  </dialog>

  <script src="../js/admin-users.js"></script>
  <script src="../js/admin-roles.js"></script>
</body>
</html>
