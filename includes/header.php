<header class="header">
  <img src=<?php echo "{$root_directory}resources/logos/logo-ayuntamientoXXIV-gris.svg"; ?> height="55px"
    alt="Logo del Municipio de Tijuana"></img>
  <div class="navigation">
    <?php include "includes/nav.php"; ?>
    <div class="avatar">
      <a href="#" <?php echo logged_in()
        ? "class=accountBtn"
        : "onclick=\"toggleModal('modalTest')\""; ?>>
        <img src="/resources/svg/ICO_Usuario_2C.svg" alt="Avatar del usuario" height="40px">
      </a>
    </div>
    <?php if (logged_in()) {
      include "includes/sandwichAnimated.php";
    } ?>
  </div>
</header>