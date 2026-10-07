<?php
declare(strict_types=1);

require_once dirname(__DIR__) . "/includes/init.php";
require_once dirname(__DIR__) . "/includes/auth.php";
require_once dirname(__DIR__) . "/includes/inspections.php";

mxli_require_login();

$areas = mxli_inspection_areas_for_user();
if (!in_array("catastro", $areas, true)) {
    if (in_array("urbano", $areas, true)) {
        header("Location: inspecciones.php");
        exit;
    }
    mxli_require_permission("map.inspect.catastro");
}

$me = mxli_current_user();
$canManageAll = mxli_can_manage_inspection_area("catastro");
$typeLabels = mxli_inspection_type_labels();
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Inspecciones Catastro | Catastro Mexicali</title>
  <link rel="stylesheet" href="../css/auth-app.css">
  <link rel="stylesheet" href="../css/admin-users.css">
  <link rel="stylesheet" href="../css/admin-inspecciones.css?v=92">
  <link rel="stylesheet" href="../css/inspeccion-fotos.css?v=92">
</head>
<body class="admin-body" data-area="catastro">
  <header class="admin-header">
    <div>
      <a class="admin-back" href="../index.php">← Volver al mapa</a>
      <h1>Inspecciones Catastro</h1>
      <p class="admin-user-line"><?= $canManageAll ? "Gestión de todos los registros" : "Mis registros de campo" ?> — <strong><?= htmlspecialchars($me["username"] ?? "", ENT_QUOTES, "UTF-8") ?></strong>
        <a class="admin-logout" href="../logout.php">Salir</a>
      </p>
    </div>
  </header>

  <main class="admin-main admin-main--single">
    <section class="admin-panel admin-panel--wide">
      <div class="admin-panel__head">
        <h2>Registros</h2>
        <div class="inspect-admin-filters">
          <input type="text" id="filter-clave" placeholder="Filtrar por clave…" maxlength="32">
          <?php if ($canManageAll): ?>
          <select id="filter-inspector">
            <option value="">Todos los inspectores</option>
          </select>
          <?php endif; ?>
          <button type="button" id="btn-refresh-inspect" class="admin-secondary">Actualizar</button>
          <button type="button" id="btn-export-csv" class="admin-secondary">Exportar Excel (CSV)</button>
          <button type="button" id="btn-export-kmz" class="admin-secondary" title="Puntos con ficha y fotografía (Google Earth)">Exportar KMZ</button>
          <button type="button" id="btn-export-kml" class="admin-secondary" title="Puntos con ficha (sin fotos embebidas)">Exportar KML</button>
        </div>
      </div>
      <div class="inspect-table-wrap">
        <table class="inspect-table" id="inspect-table">
          <thead>
            <tr>
              <th>#</th>
              <th>Foto</th>
              <th>Clave</th>
              <th>Fecha</th>
              <th>Inspector</th>
              <th>Notas</th>
              <th>GPS</th>
              <th>Acciones</th>
            </tr>
          </thead>
          <tbody id="inspect-grid"></tbody>
        </table>
      </div>
      <p id="inspect-list-msg" class="admin-msg" hidden></p>
    </section>
  </main>

  <dialog id="inspect-edit-dialog" class="admin-dialog">
    <form method="dialog" id="inspect-edit-form" class="admin-form">
      <h3>Editar inspección</h3>
      <input type="hidden" id="edit-inspect-id">
      <img id="edit-inspect-preview" class="inspect-edit-preview" alt="Foto" title="Ver foto ampliada">
      <label>Clave
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

  <dialog id="inspect-lightbox" class="inspect-lightbox" aria-label="Foto ampliada">
    <button type="button" id="inspect-lightbox-close" class="inspect-lightbox__close" aria-label="Cerrar">×</button>
    <img id="inspect-lightbox-img" alt="Foto ampliada">
    <p id="inspect-lightbox-caption" class="inspect-lightbox__caption"></p>
  </dialog>

  <script>
    window.MXLI_INSPECT_CAN_MANAGE = <?= $canManageAll ? "true" : "false" ?>;
    window.MXLI_INSPECT_TYPE_LABELS = <?= json_encode($typeLabels, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
  </script>
  <script src="../js/admin-inspecciones.js?v=92"></script>
  <script src="../js/sessionGuard.js?v=99"></script>
</body>
</html>