<?php
include "includes/init.php";
require_once __DIR__ . "/includes/auth.php";
require_once __DIR__ . "/includes/inspections.php";
require_once __DIR__ . "/includes/planos.php";
mxli_planos_grant_roles();
mxli_grant_red_unificada_layer();
mxli_grant_patrulla_ecologica_role();
mxli_grant_fallas_geologicas_layer();
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

/* height:auto + fixed colapsaba el mapa a 0px (área blanca sin error JS) */
#map2d {
  position: fixed !important;
  top: 86px !important;
  left: 0 !important;
  right: 0 !important;
  bottom: 0 !important;
  width: 100% !important;
  height: calc(100vh - 86px) !important;
  min-height: calc(100vh - 86px) !important;
  z-index: 10 !important;
  display: block !important;
  visibility: visible !important;
  opacity: 1 !important;
}

.leaflet-container {
  width: 100% !important;
  height: 100% !important;
  min-height: 100% !important;
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

.header-user-identity {
  display: inline-flex;
  align-items: center;
  gap: 0.45rem;
  margin-right: 0.15rem;
  padding-right: 0.55rem;
  border-right: 1px solid rgba(255,255,255,0.28);
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

.header-user-box a.header-logout {
  background: rgba(214,170,47,0.22);
  border-color: rgba(214,170,47,0.55);
}

.header-user-box a.header-logout:hover {
  background: rgba(214,170,47,0.38);
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
  .header-user-identity {
    border-right: none;
    padding-right: 0;
    margin-right: 0;
  }
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
    height: calc(100vh - 72px) !important;
    min-height: calc(100vh - 72px) !important;
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
    <div class="header-user-identity">
      <span class="header-user-name"><?= htmlspecialchars($mxliUser["username"] ?? "", ENT_QUOTES, "UTF-8") ?></span>
      <span class="header-user-role"><?= htmlspecialchars($mxliUser["role_label"] ?? mxli_role_label($mxliUser["role"] ?? "consulta"), ENT_QUOTES, "UTF-8") ?></span>
      <a class="header-logout" href="logout.php">Salir</a>
    </div>
    <?php if (mxli_can("users.manage")): ?>
      <a href="admin/usuarios.php">Usuarios</a>
    <?php endif; ?>
    <?php if (mxli_can("map.inspect") || mxli_can_inspection_manage()): ?>
      <a href="admin/inspecciones.php">Insp. Control Urbano</a>
    <?php endif; ?>
    <?php if (mxli_can("map.inspect.catastro") || mxli_can_inspection_manage_catastro()): ?>
      <a href="admin/inspecciones-catastro.php">Insp. Catastro</a>
    <?php endif; ?>
    <?php if (mxli_can("map.inspect.ecologica") || mxli_can_inspection_manage_ecologica()): ?>
      <a href="admin/inspecciones-ecologica.php">Insp. Patrulla</a>
    <?php endif; ?>
    <?php if (mxli_can("map.planos")): ?>
      <button type="button" class="header-planos-btn" id="consultaPlanosHeaderBtn">Planos</button>
      <button type="button" class="header-planos-btn" id="consultaDeslindesHeaderBtn">Deslindes</button>
    <?php endif; ?>
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
  if (layerKey === "fallas_geologicas") {
    var roleF = String((window.MXLI_USER && window.MXLI_USER.role) || "").toLowerCase();
    var labelF = String((window.MXLI_USER && window.MXLI_USER.role_label) || "").toLowerCase();
    if (roleF === "admin") return true;
    if (roleF === "perito_valuador" || roleF.indexOf("valuad") !== -1) return true;
    return labelF.indexOf("valuad") !== -1;
  }
  var layers = (window.MXLI_USER && window.MXLI_USER.allowed_layers) || ["*"];
  if (layers.indexOf("*") !== -1) return true;
  return layers.indexOf(layerKey) !== -1;
};
window.MXLI_RED_UNIFICADA_DEFAULT = <?= (static function (array $user): bool {
  $role = mb_strtolower((string)($user["role"] ?? ""), "UTF-8");
  $label = mb_strtolower((string)($user["role_label"] ?? ""), "UTF-8");
  if ($role === "perito_deslindador" || str_contains($role, "deslind")) {
      return true;
  }
  return str_contains($label, "perito") && str_contains($label, "deslind");
})($mxliUser) ? "true" : "false" ?>;
</script>

<script src="js/listaCapas.js?v=105"></script>
<script src="js/predialAdeudo.js?v=110"></script>
<script src="js/padronPredio.js?v=103"></script>
<script src="js/expedienteBtn.js?v=103"></script>
<script src="js/leaflet.js?v=106"></script>
<script src="js/layerControls.js?v=104"></script>
<script src="js/wmsLayerPanel.js?v=104"></script>
<script src="js/simbologiaPanel.js?v=103"></script>
<script src="js/buscadorPredios.js?v=110"></script>
<script src="js/predialCapa.js?v=110"></script>
<script src="js/inspeccionFotos.js?v=111"></script>
<script src="js/inspeccionesCapa.js?v=107"></script>
<script src="js/consultaPlanos.js?v=105"></script>
<script src="js/consultaDeslindes.js?v=3"></script>
<script src="js/mapTopToolbar.js?v=103"></script>
<script src="js/permissions.js?v=106"></script>
<script src="js/sessionGuard.js?v=102"></script>
<script>
(function encenderRedUnificadaAlEntrar() {
  if (!window.MXLI_RED_UNIFICADA_DEFAULT) return;

  function capaVisible() {
    try {
      if (typeof group !== "undefined" && group && typeof group.hasLayer === "function") {
        return group.hasLayer(red_unificada);
      }
    } catch (e) {}
    return false;
  }

  function encender() {
    if (typeof red_unificada === "undefined" || !red_unificada) return false;
    if (typeof window.mxliCanLayer === "function" && !window.mxliCanLayer("red_unificada_de_mexicali")) {
      return false;
    }
    var g = typeof group !== "undefined" && group ? group : null;
    if (!g || typeof g.addLayer !== "function") return false;

    var chk = document.getElementById("red_unificada_chkbox");
    if (chk) chk.checked = true;
    if (!capaVisible()) {
      if (chk && typeof toggleLayer === "function") {
        toggleLayer("red_unificada_chkbox", red_unificada);
      } else {
        g.addLayer(red_unificada);
      }
    }
    if (!capaVisible()) {
      try { g.addLayer(red_unificada); } catch (e2) {}
    }
    if (chk && typeof syncWmsLayerCardState === "function") {
      syncWmsLayerCardState("red_unificada_chkbox", true);
    }
    ponerRedPrimera();
    return capaVisible();
  }

  function ponerRedPrimera() {
    var list = document.getElementById("wms-layer-list");
    var card = document.querySelector('.wms-layer-card[data-layer-id="red_unificada_chkbox"]');
    if (list && card && card.parentNode === list && list.firstElementChild !== card) {
      list.insertBefore(card, list.firstElementChild);
    }
    if (typeof red_unificada !== "undefined" && red_unificada) {
      if (typeof red_unificada.setZIndex === "function") red_unificada.setZIndex(2200);
      if (typeof red_unificada.bringToFront === "function") red_unificada.bringToFront();
    }
  }

  if (typeof window.applyWmsLayerStack === "function" && !window.applyWmsLayerStack._mxliRedPrimera) {
    var apilarOriginal = window.applyWmsLayerStack;
    window.applyWmsLayerStack = function () {
      apilarOriginal();
      ponerRedPrimera();
    };
    window.applyWmsLayerStack._mxliRedPrimera = true;
  }

  var intentos = 0;
  function intentar() {
    if (encender() || intentos++ > 40) return;
    setTimeout(intentar, 250);
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", intentar);
  } else {
    intentar();
  }
  window.addEventListener("load", intentar);
})();
</script>

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

function mxliFixMapSize() {
  try {
    var m = window.map2d || (typeof map2d !== "undefined" ? map2d : null);
    if (m && m.invalidateSize) m.invalidateSize(true);
  } catch (e) {}
}
setTimeout(mxliFixMapSize, 200);
setTimeout(mxliFixMapSize, 800);
setTimeout(mxliFixMapSize, 2000);
window.addEventListener("load", mxliFixMapSize);
window.addEventListener("resize", mxliFixMapSize);
</script>

</body>
</html>
