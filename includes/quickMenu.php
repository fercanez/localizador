<div class="quickMenu notVisible">
  <div class="quickMenu-item">
    <nav class="quickMenu-nav">
      <ul>
        <li>
          <a href="#" class="quickMenu-link" onclick="toggleSection(event, 'notificaciones')"><span
              class="material-symbols-outlined">
              notifications
            </span></a>
        </li>
        <li>
          <a href="#" class="quickMenu-link" onclick="toggleSection(event, 'perfil')" id="perfilLink"><span
              class="material-symbols-outlined">
              account_circle
            </span></a>
        </li>
        <li>
          <a href="#" class="quickMenu-link" onclick="toggleSection(event, 'tareas')"><span
              class="material-symbols-outlined"> task_alt </span></a>
        </li>
        <li>
          <a href="#" class="quickMenu-link" onclick="toggleSection(event, 'tramites')"><span
              class="material-symbols-outlined"> description </span></a>
        </li>
        <li>
          <a href="#" class="quickMenu-link" onclick="toggleSection(event, 'historial')"><span
              class="material-symbols-outlined"> history </span></a>
        </li>
        <li>
          <a href="#" class="quickMenu-link" onclick="toggleSection(event, 'ajustes')"><span
              class="material-symbols-outlined"> settings </span></a>
        </li>
      </ul>
    </nav>
  </div>

  <div class="quickMenu-item">
    <a href="<?php echo $root_directory ?>logout.php" class="quickMenu-link">
      <span class="material-symbols-outlined"> logout </span>
    </a>
  </div>
  <?php include "includes/sidePanels/notificaciones.php" ?>
  <?php include "includes/sidePanels/perfil.php" ?>
  <?php include "includes/sidePanels/tareas.php" ?>
  <?php include "includes/sidePanels/tramites.php" ?>
  <?php include "includes/sidePanels/historial.php" ?>
  <?php include "includes/sidePanels/ajustes.php" ?>
  <div class="sideElement">
    <h3>Historial</h3>
  </div>
</div>