<?php
declare(strict_types=1);

require_once dirname(__DIR__) . "/includes/init.php";
require_once dirname(__DIR__) . "/includes/auth.php";
require_once dirname(__DIR__) . "/includes/inspections.php";

mxli_require_login();
if (!mxli_can("map.inspect") && !mxli_can_inspection_manage()) {
    mxli_require_permission("map.inspect");
}

$me = mxli_current_user();
$canManageAll = mxli_can_inspection_manage();
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Inspecciones fotográficas | Catastro Mexicali</title>
  <link rel="stylesheet" href="../css/auth-app.css">
  <link rel="stylesheet" href="../css/admin-users.css">
  <link rel="stylesheet" href="../css/admin-inspecciones.css">
</head>
<body class="admin-body">
  <header class="admin-header">
    <div>
      <a class="admin-back" href="../index.php">← Volver al mapa</a>
      <h1>Inspecciones fotográficas</h1>
      <p><?= $canManageAll ? "Gestión de todos los registros" : "Mis registros de campo" ?> — <strong><?= htmlspecialchars($me["username"] ?? "", ENT_QUOTES, "UTF-8") ?></strong></p>
    </div>
    <a class="admin-logout" href="../logout.php">Cerrar sesión</a>
  </header>

  <main class="admin-main admin-main--single">
    <section class="admin-panel admin-panel--wide">
      <div class="admin-panel__head">
        <h2>Registros</h2>
        <div class="inspect-admin-filters">
          <input type="text" id="filter-clave" placeholder="Filtrar por clave…" maxlength="32">
          <button type="button" id="btn-refresh-inspect" class="admin-secondary">Actualizar</button>
        </div>
      </div>
      <div id="inspect-grid" class="inspect-grid"></div>
      <p id="inspect-list-msg" class="admin-msg" hidden></p>
    </section>
  </main>

  <dialog id="inspect-edit-dialog" class="admin-dialog">
    <form method="dialog" id="inspect-edit-form" class="admin-form">
      <h3>Editar inspección</h3>
      <input type="hidden" id="edit-inspect-id">
      <img id="edit-inspect-preview" class="inspect-edit-preview" alt="Foto">
      <label>Clave catastral
        <input type="text" id="edit-inspect-clave" maxlength="32" required>
      </label>
      <label>Notas
        <textarea id="edit-inspect-notes" rows="3"></textarea>
      </label>
      <div class="admin-dialog-actions">
        <button type="submit" value="cancel" class="admin-secondary">Cancelar</button>
        <button type="submit" value="save" class="auth-btn">Guardar</button>
      </div>
    </form>
  </dialog>

  <script src="../js/admin-inspecciones.js"></script>
</body>
</html>
