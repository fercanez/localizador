<?php
declare(strict_types=1);

require_once dirname(__DIR__) . "/includes/init.php";
require_once dirname(__DIR__) . "/includes/auth.php";
require_once dirname(__DIR__) . "/includes/layer-catalog.php";

mxli_require_admin();
header("Content-Type: text/html; charset=UTF-8");
$me = mxli_current_user();
$toolDefs = mxli_tool_permission_defs();
$layerCatalog = mxli_layer_catalog();
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
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
      <p class="admin-user-line">Administrador: <strong><?= htmlspecialchars($me["username"], ENT_QUOTES, "UTF-8") ?></strong>
        <a class="admin-logout" href="../logout.php">Salir</a>
      </p>
    </div>
  </header>

  <nav class="admin-tabs" role="tablist">
    <button type="button" class="admin-tab is-active" data-tab="users">Usuarios</button>
    <button type="button" class="admin-tab" data-tab="roles">Roles y permisos</button>
    <button type="button" class="admin-tab" data-tab="bitacora">Bitácora padrón</button>
  </nav>

  <main class="admin-main">
    <div class="admin-tab-panel is-active" id="tab-users" data-panel="users">
      <aside class="admin-users-sidebar">
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
            <label class="admin-check">
              <input type="checkbox" name="can_view_padron" id="create-padron">
              <span>Ver nombre del contribuyente (padrón)
                <small>Independiente del rol; el usuario debe volver a iniciar sesión tras el cambio.</small>
              </span>
            </label>
            <label>Vence el (opcional)
              <input type="date" name="expires_at">
              <small>Vacío = sin vencimiento. Ese día aún puede entrar; al día siguiente ya no.</small>
            </label>
            <button type="submit" class="auth-btn">Crear usuario</button>
          </form>
          <p id="create-msg" class="admin-msg" hidden></p>
        </section>

        <section class="admin-panel" id="bulk-import-panel">
          <h2>Alta masiva</h2>
          <p class="admin-hint">
            Importe usuarios desde un archivo <strong>CSV</strong> o <strong>XLSX</strong>.
            Todas las filas recibirán el mismo rol seleccionado abajo.
          </p>
          <form id="user-import-form" class="admin-form">
            <label>Archivo (CSV o XLSX)
              <input type="file" name="file" id="import-file" accept=".csv,.xlsx,.txt,text/csv,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet" required>
            </label>
            <label>Rol para todos
              <select name="role" id="bulk-role" required disabled>
                <option value="" disabled selected>Cargando roles…</option>
              </select>
              <small>Después podrá editar el rol de cada usuario individualmente.</small>
            </label>
            <label>Vence el (opcional, para todos)
              <input type="date" name="expires_at" id="import-expires">
              <small>Vacío = sin vencimiento. Máx. 500 filas / 2&nbsp;MB.</small>
            </label>
            <div class="admin-form-actions">
              <button type="submit" class="auth-btn" id="import-submit">Importar</button>
              <a class="admin-secondary admin-link-btn" href="../api/admin/users-import.php?template=1" download="plantilla_usuarios.csv">Descargar plantilla CSV</a>
            </div>
          </form>
          <p id="import-msg" class="admin-msg" hidden></p>
          <div id="import-results" class="import-results" hidden>
            <p class="import-results__summary" id="import-summary"></p>
            <ul class="import-results__list" id="import-skipped-list"></ul>
          </div>
        </section>
      </aside>

      <section class="admin-panel admin-panel--wide">
        <div class="admin-panel__head">
          <h2>Usuarios registrados</h2>
          <div class="admin-users-toolbar">
            <button type="button" id="btn-export" class="admin-secondary">Exportar CSV</button>
            <button type="button" id="btn-refresh" class="admin-secondary">Actualizar</button>
          </div>
        </div>
        <div class="admin-users-filters" id="users-filters">
          <label class="admin-filter">
            <span>Buscar</span>
            <input type="search" id="filter-text" placeholder="Usuario, nombre o correo…" autocomplete="off">
          </label>
          <label class="admin-filter">
            <span>Rol</span>
            <select id="filter-role">
              <option value="">Todos</option>
            </select>
          </label>
          <label class="admin-filter">
            <span>Activo</span>
            <select id="filter-activo">
              <option value="">Todos</option>
              <option value="1">Sí</option>
              <option value="0">No</option>
            </select>
          </label>
          <label class="admin-filter">
            <span>Padrón</span>
            <select id="filter-padron">
              <option value="">Todos</option>
              <option value="1">Sí</option>
              <option value="0">No</option>
            </select>
          </label>
          <p class="admin-filter-count" id="filter-count" aria-live="polite"></p>
        </div>
        <div class="admin-table-wrap">
          <table class="admin-table" id="users-table">
            <thead>
              <tr>
                <th>Usuario</th>
                <th>Nombre</th>
                <th>Rol</th>
                <th>Padrón</th>
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
                <th>Descripci&oacute;n</th>
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

    <div class="admin-tab-panel" id="tab-bitacora" data-panel="bitacora" hidden>
      <section class="admin-panel admin-panel--wide">
        <div class="admin-panel__head">
          <h2>Bitácora de consultas al padrón</h2>
          <div class="admin-users-toolbar">
            <button type="button" id="bitacora-export" class="admin-secondary">Exportar CSV</button>
            <button type="button" id="bitacora-refresh" class="admin-secondary">Actualizar</button>
          </div>
        </div>
        <p class="admin-hint">
          Registro de quién consultó el padrón: usuario, clave catastral (o búsqueda por nombre), fecha y hora (hora de Mexicali).
        </p>
        <form class="admin-users-filters" id="bitacora-filters">
          <label class="admin-filter">
            <span>Usuario</span>
            <input type="search" id="bitacora-usuario" placeholder="Usuario o nombre…" autocomplete="off">
          </label>
          <label class="admin-filter">
            <span>Clave</span>
            <input type="search" id="bitacora-clave" placeholder="Ej. SP001001" autocomplete="off">
          </label>
          <label class="admin-filter">
            <span>Tipo</span>
            <select id="bitacora-tipo">
              <option value="">Todos</option>
              <option value="clave">Por clave</option>
              <option value="nombre">Por nombre</option>
            </select>
          </label>
          <label class="admin-filter">
            <span>Desde</span>
            <input type="date" id="bitacora-desde">
          </label>
          <label class="admin-filter">
            <span>Hasta</span>
            <input type="date" id="bitacora-hasta">
          </label>
          <div class="admin-filter admin-filter-actions">
            <button type="submit" class="auth-btn">Filtrar</button>
          </div>
          <p class="admin-filter-count" id="bitacora-count" aria-live="polite"></p>
        </form>
        <div class="admin-table-wrap">
          <table class="admin-table" id="bitacora-table">
            <thead>
              <tr>
                <th>Fecha y hora</th>
                <th>Usuario</th>
                <th>Nombre</th>
                <th>Clave / búsqueda</th>
                <th>Resultado</th>
                <th>IP</th>
              </tr>
            </thead>
            <tbody></tbody>
          </table>
        </div>
        <div class="admin-pager" id="bitacora-pager" hidden>
          <button type="button" class="admin-secondary" id="bitacora-prev">Anterior</button>
          <span id="bitacora-page-label"></span>
          <button type="button" class="admin-secondary" id="bitacora-next">Siguiente</button>
        </div>
        <p id="bitacora-msg" class="admin-msg" hidden></p>
      </section>
    </div>
  </main>

  <dialog id="edit-dialog" class="admin-dialog">
    <form id="user-edit-form" class="admin-form">
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
        <input type="password" name="password" id="edit-password" autocomplete="new-password" placeholder="Dejar vacío para no cambiar">
      </label>
      <label>Rol
        <select name="role" id="edit-role" required></select>
      </label>
      <label class="admin-check">
        <input type="checkbox" name="can_view_padron" id="edit-padron">
        <span>Ver nombre del contribuyente (padrón)
          <small>Override por usuario. También puede otorgarse por rol en «Roles y permisos».</small>
        </span>
      </label>
      <label>Vence el (opcional)
        <input type="date" name="expires_at" id="edit-expires">
        <small>Vacío = sin vencimiento</small>
      </label>
      <label class="admin-check">
        <input type="checkbox" name="is_active" id="edit-active">
        Usuario activo
      </label>
      <p id="edit-msg" class="admin-msg" hidden></p>
      <div class="admin-dialog-actions">
        <button type="button" id="edit-cancel" class="admin-secondary">Cancelar</button>
        <button type="submit" class="auth-btn">Guardar</button>
      </div>
    </form>
  </dialog>

  <dialog id="role-dialog" class="admin-dialog admin-dialog--wide">
    <form method="dialog" id="role-form" class="admin-form">
      <h3 id="role-dialog-title">Nuevo rol</h3>
      <input type="hidden" name="slug" id="role-slug">
      <input type="hidden" id="role-mode" value="create">
      <label>Nombre visible
        <input type="text" name="name" id="role-name" required minlength="2"
          placeholder="Ej. Gobierno Mpal" autocomplete="off">
        <small>Texto que ver&aacute;n los administradores. No use el identificador t&eacute;cnico aqu&iacute;.</small>
      </label>
      <label>Identificador (slug)
        <input type="text" id="role-slug-display" readonly tabindex="-1"
          placeholder="Se genera al guardar (ej. gobierno_mpal)">
        <small>Clave interna en <code>app_roles</code> / <code>app_users.role</code>. No se edita.</small>
      </label>
      <label>Descripci&oacute;n
        <input type="text" name="description" id="role-description" placeholder="Opcional">
      </label>

      <fieldset class="admin-fieldset">
        <legend>Herramientas</legend>
        <p class="admin-hint">Descarga e impresión de planos se marcan aparte. Si no están activas, el perito solo previsualiza con marca de agua.</p>
        <div class="admin-check-grid" id="role-tools">
          <?php foreach ($toolDefs as $perm => $label): ?>
            <?php
            $isSub = strpos($perm, "map.planos.") === 0 || strpos($perm, "map.expediente.") === 0;
            ?>
            <label class="admin-check<?= $isSub ? " admin-check--sub" : "" ?>">
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
        <button type="button" class="admin-secondary" id="role-cancel">Cancelar</button>
        <button type="button" class="auth-btn" id="role-save">Guardar rol</button>
      </div>
    </form>
  </dialog>

  <script src="../js/admin-users.js?v=104"></script>
  <script src="../js/admin-roles.js?v=103"></script>
  <script src="../js/admin-padron-bitacora.js?v=1"></script>
  <script src="../js/sessionGuard.js?v=99"></script>
</body>
</html>
