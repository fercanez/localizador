<?php
include "includes/init.php";
require_once __DIR__ . "/includes/auth.php";
mxli_require_login();
$mxliUser = mxli_current_user();
?>

<!DOCTYPE html>
<html lang="es">
<?php include "includes/head.php"; ?>

<style>
html, body {
  margin: 0;
  padding: 0;
}

.header-catastro-mxli {
  position: fixed;
  top: 0;
  left: 0;
  width: 100%;
  height: 86px;
  z-index: 100000;
  background: linear-gradient(135deg, #6f2f3d 0%, #7d3445 55%, #5e2633 100%);
  border-top: 4px solid #b00020;
  border-bottom: 3px solid #d6aa2f;
  box-shadow: 0 3px 10px rgba(0,0,0,.35);
  display: flex;
  align-items: center;
  padding: 0 34px;
  box-sizing: border-box;
}

.header-catastro-mxli img {
  width: auto;
  height: 64px;
  max-width: 48px;
  object-fit: contain;
  object-position: center;
  background: white;
  border-radius: 8px;
  padding: 4px 6px;
  margin-right: 14px;
  flex-shrink: 0;
}

.header-catastro-texto {
  color: white;
  font-family: Arial, sans-serif;
  line-height: 1.05;
}

.header-catastro-texto .dependencia {
  font-size: 14px;
  font-weight: 700;
  letter-spacing: .5px;
}

.header-catastro-texto .titulo {
  font-size: 27px;
  font-weight: 900;
  letter-spacing: 1px;
  margin-top: 4px;
}

.header-catastro-texto .ciudad {
  font-size: 14px;
  font-weight: 700;
  margin-top: 7px;
}

.contenedorPrincipal,
.main,
.layoutPublicMap,
.mapMenu,
.listado-capas,
.map3d iframe,
.sidePanel-content {
  height: calc(100vh - 86px) !important;
}

.contenedorPrincipal,
.main,
.layoutPublicMap,
.mapMenu {
  top: 86px !important;
}

#map2d {
  position: fixed !important;
  top: 86px !important;
  left: 0 !important;
  right: 0 !important;
  bottom: 0 !important;
  width: 100% !important;
  height: auto !important;
}

.leaflet-container {
  width: 100% !important;
}

.mapMenu,
.sidePanel,
.sidePanel-content {
  top: 86px !important;
  height: calc(100vh - 86px) !important;
  z-index: 9000 !important;
}

.leaflet-control-container {
  position: relative;
  z-index: 99999 !important;
}

.leaflet-top,
.leaflet-bottom {
  z-index: 99999 !important;
}

.leaflet-top.leaflet-left {
  top: 95px !important;
}

.header-catastro-mxli {
  justify-content: space-between;
}

.header-user-box {
  display: flex;
  align-items: center;
  gap: 0.65rem;
  color: #fff;
  font-family: Arial, sans-serif;
  font-size: 0.85rem;
}

.header-user-box a {
  color: #fff;
  text-decoration: none;
  background: rgba(255,255,255,0.14);
  border: 1px solid rgba(255,255,255,0.35);
  border-radius: 8px;
  padding: 0.35rem 0.65rem;
  font-weight: 700;
}

.header-user-box a:hover {
  background: rgba(255,255,255,0.24);
}

.header-user-role {
  opacity: 0.9;
  font-size: 0.75rem;
  font-weight: 700;
  letter-spacing: 0.02em;
  padding: 0.2rem 0.5rem;
  border-radius: 999px;
  background: rgba(255,255,255,0.12);
  border: 1px solid rgba(214,170,47,0.45);
  text-transform: uppercase;
}

@media (max-width: 760px) {
  .header-catastro-mxli {
    height: 72px;
    padding: 0 12px;
  }

  .header-catastro-mxli img {
    height: 52px;
    max-width: 40px;
    padding: 3px 5px;
    margin-right: 10px;
  }

  .header-catastro-texto .titulo { font-size: 16px; }
  .header-catastro-texto .dependencia,
  .header-catastro-texto .ciudad { font-size: 10px; }
  .header-user-name { display: none; }
  .header-user-role { display: none; }
  .header-user-box a {
    padding: 0.28rem 0.5rem;
    font-size: 0.75rem;
  }

  .contenedorPrincipal,
  .main,
  .layoutPublicMap,
  .mapMenu,
  .listado-capas,
  .map3d iframe,
  .sidePanel-content {
    height: calc(100vh - 72px) !important;
  }

  .contenedorPrincipal,
  .main,
  .layoutPublicMap,
  .mapMenu,
  #map2d,
  .mapMenu,
  .sidePanel,
  .sidePanel-content {
    top: 72px !important;
  }

  #map2d {
    top: 72px !important;
  }

  .mapMenu,
  .sidePanel,
  .sidePanel-content {
    height: calc(100vh - 72px) !important;
  }
}
</style>

<body class="body">

<header class="header-catastro-mxli">
  <div style="display:flex;align-items:center;">
    <img src="escudo-mexicali.png" alt="Escudo de Mexicali">
    <div class="header-catastro-texto">
      <div class="dependencia">DIRECCIÓN DE ADMINISTRACIÓN URBANA</div>
      <div class="titulo">JEFATURA DE CATASTRO</div>
      <div class="ciudad">Mexicali, Baja California</div>
    </div>
  </div>
  <div class="header-user-box">
    <span class="header-user-name"><?= htmlspecialchars($mxliUser["username"] ?? "", ENT_QUOTES, "UTF-8") ?></span>
    <span class="header-user-role"><?= htmlspecialchars($mxliUser["role_label"] ?? mxli_role_label($mxliUser["role"] ?? "consulta"), ENT_QUOTES, "UTF-8") ?></span>
    <?php if (mxli_can("users.manage")): ?>
      <a href="admin/usuarios.php">Usuarios</a>
    <?php endif; ?>
    <a href="logout.php">Salir</a>
  </div>
</header>

<div class="contenedorPrincipal">
  <?php //include "includes/header.php"; ?>
  <main class="main">
    <?php include "includes/layoutPublicMapa.php"; ?>
  </main>
  <?php //include "includes/footer.php"; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>

<script src="./resources/locate-control/src/L.Control.Locate.js" charset="utf-8"></script>
<script src="./resources/leaflet-bing-layer.js"></script>
<?php if (mxli_config("google_maps_key") !== ""): ?>
<script src="https://maps.googleapis.com/maps/api/js?key=<?= htmlspecialchars(mxli_config("google_maps_key"), ENT_QUOTES, "UTF-8") ?>"></script>
<script src="https://unpkg.com/leaflet.gridlayer.googlemutant@latest/dist/Leaflet.GoogleMutant.js"></script>
<?php endif; ?>

<script>
window.MXLI_MAP_KEYS = <?= json_encode([
  "bing" => mxli_config("bing_maps_key"),
  "geoserver" => mxli_config("geoserver_url"),
], JSON_UNESCAPED_SLASHES) ?>;
window.MXLI_USER = <?= json_encode($mxliUser, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
window.mxliCan = function (permission) {
  var perms = (window.MXLI_USER && window.MXLI_USER.permissions) || [];
  return perms.indexOf(permission) !== -1;
};
window.mxliCanLayer = function (layerKey) {
  var layers = (window.MXLI_USER && window.MXLI_USER.allowed_layers) || ["*"];
  if (layers.indexOf("*") !== -1) return true;
  return layers.indexOf(layerKey) !== -1;
};
</script>

<script src="js/listaCapas.js?v=77"></script>
<script src="js/leaflet.js?v=76"></script>
<script src="js/layerControls.js?v=72"></script>
<script src="js/wmsLayerPanel.js?v=72"></script>
<script src="js/simbologiaPanel.js?v=72"></script>
<script src="js/buscadorPredios.js?v=83"></script>
<script src="js/mapTopToolbar.js?v=72"></script>
<script src="js/permissions.js?v=73"></script>

<script>
<?php include "js/menuBtn.js"; ?>

function toggleSection(evt, section) {
  var i, sections, btns, status;
  status = document.getElementById(section).className.includes("open");
  sections = document.getElementsByClassName("sideElement");

  for (i = 0; i < sections.length; i++) {
    sections[i].classList.remove("open");
  }

  btns = document.getElementsByClassName("quickMenu-link");
  for (i = 0; i < btns.length; i++) {
    btns[i].classList.remove("active");
  }

  document.getElementById(section).classList.toggle("open");

  if (status) {
    document.getElementById(section).classList.toggle("open");
  }

  evt.currentTarget.classList.add("active");
}

function hideSideElements() {
  const act = document.querySelector(".quickMenu");
  const opn = document.querySelector(".open");
  const btns = document.getElementsByClassName("quickMenu-link");

  for (i = 0; i < btns.length; i++) {
    btns[i].classList.remove("active");
  }

  if (act) act.classList.toggle("notVisible");
  if (opn) opn.classList.toggle("open");
}

<?php include "js/mapbox.js"; ?>
<?php include "js/toggleModal.js"; ?>
<?php include "js/sortableList.js"; ?>

setTimeout(function () {
  if (typeof map2d !== "undefined") {
    map2d.invalidateSize();
  }
}, 800);
</script>

</body>
</html>
