// Control de capas corregido para evitar errores cuando el DOM o la lista de capas no existen.
// Mantiene compatibilidad con el visor original.
let activeLayers = [];
let capasActivas = {};
let capas = [];
let nActiva = 0;

function arrayRemove(arr, value) {
  return arr.filter(function (ele) {
    return ele !== value;
  });
}

function getLayerTitle(layer) {
  return (layer && layer.options && layer.options.title) || (layer && layer.id) || "Capa";
}

function removeLayerFromSortable(title) {
  const item = document.querySelector(`.sortableItem[data-layer-title="${CSS.escape(title)}"]`);
  if (item && item.parentElement) item.parentElement.removeChild(item);
}

function toggleLayer(chkBox, lyrName) {
  var checkBox = document.getElementById(chkBox);

  if (!checkBox || !lyrName) return;

  if (checkBox.checked == true) {
    group.addLayer(lyrName);

    capasActivas[lyrName.options.title] = {
      capa: lyrName,
      order: ++nActiva,
    };

    if (typeof syncWmsLayerCardState === "function") {
      syncWmsLayerCardState(chkBox, true);
    }

    addToList(lyrName.options.title, chkBox);

    if (typeof applyWmsLayerStack === "function") {
      applyWmsLayerStack();
    } else {
      group.bringToFront();
    }

    if (typeof refreshActiveSimbology === "function") {
      refreshActiveSimbology();
    }

    if (typeof actualizarCapaActivaConsulta === "function") {
      actualizarCapaActivaConsulta();
    }

  } else {
    group.removeLayer(lyrName);

    var item = document.getElementById(lyrName.options.title);
    if (item && item.parentElement) item.parentElement.remove();

    delete capasActivas[lyrName.options.title];

    if (typeof syncWmsLayerCardState === "function") {
      syncWmsLayerCardState(chkBox, false);
    }

    if (typeof applyWmsLayerStack === "function") {
      applyWmsLayerStack();
    } else {
      group.bringToFront();
    }

    if (typeof refreshActiveSimbology === "function") {
      refreshActiveSimbology();
    }

    if (typeof actualizarCapaActivaConsulta === "function") {
      actualizarCapaActivaConsulta();
    }
  }
}

// Zoom a partir del cual Predios se enciende solo (útil para ver lotes)
var PREDIOS_AUTO_ZOOM = 17;

function canUseLayerKey(layerKey) {
  if (typeof window.mxliCanLayer === "function" && !window.mxliCanLayer(layerKey)) {
    return false;
  }
  return true;
}

function groupHasLayer(layer) {
  try {
    if (typeof group !== "undefined" && group && typeof group.hasLayer === "function") {
      return group.hasLayer(layer);
    }
  } catch (e) {}
  try {
    if (typeof map2d !== "undefined" && map2d && typeof map2d.hasLayer === "function") {
      return map2d.hasLayer(layer);
    }
  } catch (e2) {}
  return false;
}

function encenderCapaPrediosMapa() {
  if (!canUseLayerKey("predios_mexicali_2025")) return;
  if (typeof predios === "undefined" || typeof toggleLayer !== "function") return;

  var chk = document.getElementById("predios_chkbox");
  if (!chk) return;

  if (chk.checked && groupHasLayer(predios)) {
    if (typeof actualizarCapaActivaConsulta === "function") {
      actualizarCapaActivaConsulta();
    }
    return;
  }

  if (!chk.checked) {
    chk.checked = true;
    toggleLayer("predios_chkbox", predios);
  } else if (!groupHasLayer(predios)) {
    toggleLayer("predios_chkbox", predios);
  }
}

window.encenderCapaPrediosMapa = encenderCapaPrediosMapa;
window.PREDIOS_AUTO_ZOOM = PREDIOS_AUTO_ZOOM;

function encenderCapaColoniasMapa() {
  if (!canUseLayerKey("colonias")) return false;
  if (typeof colonias === "undefined" || !colonias) return false;

  var g =
    typeof group !== "undefined" && group
      ? group
      : typeof window.group !== "undefined"
        ? window.group
        : null;
  if (!g || typeof g.addLayer !== "function") return false;

  var chkCol = document.getElementById("colonias_chkbox");
  if (chkCol) chkCol.checked = true;

  // El checkbox HTML puede venir checked sin haber disparado onchange:
  // hay que añadir la capa al mapa explícitamente.
  if (!groupHasLayer(colonias)) {
    try {
      if (chkCol && typeof toggleLayer === "function") {
        toggleLayer("colonias_chkbox", colonias);
      } else {
        g.addLayer(colonias);
      }
    } catch (e) {}
  }

  if (!groupHasLayer(colonias)) {
    try {
      g.addLayer(colonias);
    } catch (e2) {}
  }

  if (
    !groupHasLayer(colonias) &&
    typeof map2d !== "undefined" &&
    map2d &&
    typeof map2d.addLayer === "function"
  ) {
    try {
      map2d.addLayer(colonias);
    } catch (e3) {}
  }

  if (chkCol && typeof syncWmsLayerCardState === "function") {
    syncWmsLayerCardState("colonias_chkbox", true);
  }

  if (typeof applyWmsLayerStack === "function") {
    try {
      applyWmsLayerStack();
    } catch (e4) {}
  }

  return groupHasLayer(colonias);
}

window.encenderCapaColoniasMapa = encenderCapaColoniasMapa;

function esUsuarioPeritoDeslindador() {
  var user = window.MXLI_USER || {};
  var role = String(user.role || "").toLowerCase();
  var label = String(user.role_label || "").toLowerCase();
  if (role === "perito_deslindador" || role.indexOf("deslind") !== -1) return true;
  return label.indexOf("perito") !== -1 && label.indexOf("deslind") !== -1;
}

function encenderCapaRedUnificadaMapa() {
  if (!esUsuarioPeritoDeslindador()) return false;
  if (!canUseLayerKey("red_unificada_de_mexicali")) return false;
  if (typeof red_unificada === "undefined" || !red_unificada) return false;

  var g =
    typeof group !== "undefined" && group
      ? group
      : typeof window.group !== "undefined"
        ? window.group
        : null;
  if (!g || typeof g.addLayer !== "function") return false;

  var chk = document.getElementById("red_unificada_chkbox");
  if (chk) chk.checked = true;

  if (!groupHasLayer(red_unificada)) {
    try {
      if (chk && typeof toggleLayer === "function") {
        toggleLayer("red_unificada_chkbox", red_unificada);
      } else {
        g.addLayer(red_unificada);
      }
    } catch (e) {}
  }

  if (!groupHasLayer(red_unificada)) {
    try {
      g.addLayer(red_unificada);
    } catch (e2) {}
  }

  if (chk && typeof syncWmsLayerCardState === "function") {
    syncWmsLayerCardState("red_unificada_chkbox", true);
  }

  if (typeof applyWmsLayerStack === "function") {
    try {
      applyWmsLayerStack();
    } catch (e3) {}
  }

  return groupHasLayer(red_unificada);
}

window.encenderCapaRedUnificadaMapa = encenderCapaRedUnificadaMapa;

function activarCapasPorDefecto() {
  // Colonias ON; Predios OFF al inicio (consulta "i" sigue priorizando Predios)
  // El perito deslindador entra con Red Unificada encendida.
  encenderCapaColoniasMapa();
  encenderCapaRedUnificadaMapa();

  if (typeof predios !== "undefined") {
    var chkPred = document.getElementById("predios_chkbox");
    if (chkPred && chkPred.checked && !groupHasLayer(predios)) {
      // Checkbox marcado por error de UI: respetar OFF al arranque
      chkPred.checked = false;
    } else if (chkPred && chkPred.checked && groupHasLayer(predios)) {
      // Si alguien lo dejó ON en HTML, apagar en arranque
      chkPred.checked = false;
      toggleLayer("predios_chkbox", predios);
    } else if (chkPred && chkPred.checked) {
      chkPred.checked = false;
    }
  }

  if (typeof actualizarCapaActivaConsulta === "function") {
    actualizarCapaActivaConsulta();
  }
}

function bindPrediosAutoZoom() {
  if (typeof map2d === "undefined" || !map2d || map2d._mxliPrediosAutoZoomBound) return;
  map2d._mxliPrediosAutoZoomBound = true;

  map2d.on("zoomend", function () {
    if (map2d.getZoom() >= PREDIOS_AUTO_ZOOM) {
      encenderCapaPrediosMapa();
    }
  });

  if (map2d.getZoom() >= PREDIOS_AUTO_ZOOM) {
    encenderCapaPrediosMapa();
  }
}

(function initCapasStartup() {
  var tries = 0;
  var coloniasOk = false;
  var redOk = false;

  function ready() {
    return (
      typeof map2d !== "undefined" &&
      map2d &&
      (typeof group !== "undefined" || typeof window.group !== "undefined") &&
      typeof colonias !== "undefined"
    );
  }

  function go() {
    if (!ready()) {
      if (tries++ < 60) setTimeout(go, 150);
      return;
    }
    activarCapasPorDefecto();
    coloniasOk = groupHasLayer(colonias);
    redOk = typeof red_unificada !== "undefined" && groupHasLayer(red_unificada);
    bindPrediosAutoZoom();

    // Reintentos: a veces el panel WMS / permisos corre después del primer pass
    [400, 1200, 2500].forEach(function (ms) {
      setTimeout(function () {
        if (!coloniasOk || !groupHasLayer(colonias)) {
          coloniasOk = !!encenderCapaColoniasMapa();
        }
        if (esUsuarioPeritoDeslindador() && (!redOk || !groupHasLayer(red_unificada))) {
          redOk = !!encenderCapaRedUnificadaMapa();
        }
        if (typeof actualizarCapaActivaConsulta === "function") {
          actualizarCapaActivaConsulta();
        }
      }, ms);
    });
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", go);
  } else {
    go();
  }
  window.addEventListener("load", function () {
    if (!coloniasOk) go();
  });
})();