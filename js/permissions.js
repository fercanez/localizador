(function () {
  "use strict";

  function can(permission) {
    if (typeof window.mxliCan === "function") {
      return window.mxliCan(permission);
    }
    var perms = (window.MXLI_USER && window.MXLI_USER.permissions) || [];
    return perms.indexOf(permission) !== -1;
  }

  function isAdminUser() {
    var role = String((window.MXLI_USER && window.MXLI_USER.role) || "").toLowerCase();
    return role === "admin";
  }

  function isValuadorUser() {
    var role = String((window.MXLI_USER && window.MXLI_USER.role) || "").toLowerCase();
    var label = String((window.MXLI_USER && window.MXLI_USER.role_label) || "").toLowerCase();
    if (role === "perito_valuador" || role.indexOf("valuad") !== -1) return true;
    return label.indexOf("valuad") !== -1;
  }

  function canLayer(layerKey) {
    if (layerKey === "fallas_geologicas") {
      return isAdminUser() || isValuadorUser();
    }
    var layers = (window.MXLI_USER && window.MXLI_USER.allowed_layers) || ["*"];
    if (layers.indexOf("*") !== -1) return true;
    return layers.indexOf(layerKey) !== -1;
  }

  function hide(el) {
    if (!el) return;
    el.style.display = "none";
    el.setAttribute("aria-hidden", "true");
    el.classList.add("is-permission-hidden");
  }

  function applyPermissions() {
    if (!can("map.upload")) {
      hide(document.getElementById("visible-uploadBtn"));
      hide(document.getElementById("shp-upload"));
    }

    if (
      !can("map.inspect") &&
      !can("map.inspect.catastro") &&
      !can("map.inspect.ecologica") &&
      !can("inspections.manage") &&
      !can("inspections.catastro.manage") &&
      !can("inspections.ecologica.manage") &&
      !can("users.manage")
    ) {
      hide(document.getElementById("inspectPhotoBtn"));
    }

    if (!can("map.print")) {
      hide(document.getElementById("printMapBtn"));
      var printMode = document.getElementById("printMode-dialog");
      if (printMode) hide(printMode);
    }

    if (!can("map.locate")) {
      hide(document.getElementById("locateBtn"));
    }

    if (!can("map.measure")) {
      hide(document.getElementById("editToolsDisplayBtn"));
      hide(document.getElementById("editToolsContainer"));
    }

    if (!can("map.search")) {
      hide(document.getElementById("buscadorPredioBox"));
      hide(document.getElementById("map-top-toolbar-search"));
    }

    if (!can("users.manage")) {
      document.querySelectorAll('a[href="admin/usuarios.php"]').forEach(hide);
    }

    if (!can("map.inspect") && !can("inspections.manage")) {
      document.querySelectorAll('a[href="admin/inspecciones.php"]').forEach(hide);
    }

    if (!can("map.inspect.catastro") && !can("inspections.catastro.manage")) {
      document.querySelectorAll('a[href="admin/inspecciones-catastro.php"]').forEach(hide);
    }

    if (!can("map.inspect.ecologica") && !can("inspections.ecologica.manage")) {
      document.querySelectorAll('a[href="admin/inspecciones-ecologica.php"]').forEach(hide);
    }

    if (!can("map.planos")) {
      hide(document.getElementById("consultaPlanosBtn"));
      hide(document.getElementById("consultaPlanosHeaderBtn"));
      hide(document.getElementById("consultaDeslindesHeaderBtn"));
    }

    document.querySelectorAll("[data-gs-layer]").forEach(function (card) {
      var key = card.getAttribute("data-gs-layer") || "";
      if (key && !canLayer(key)) {
        hide(card);
      }
    });
  }

  window.mxliCanLayer = canLayer;

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", applyPermissions);
  } else {
    applyPermissions();
  }

  setTimeout(applyPermissions, 600);
  setTimeout(applyPermissions, 1500);

  window.mxliApplyPermissions = applyPermissions;
})();
