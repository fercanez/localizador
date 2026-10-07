(function () {
  "use strict";

  var STORAGE_KEY = "mxli_map_view_state_v2";
  // Vista de inicio del localizador (igual que leaflet.js)
  var HOME_CENTER = [32.642617, -115.471843];
  var HOME_ZOOM = 13;

  function collectLayerState() {
    var layers = [];
    document.querySelectorAll(".wms-layer-card").forEach(function (card, index) {
      var chk = card.querySelector('input[type="checkbox"]');
      if (!chk || !chk.id) return;
      layers.push({
        id: chk.id,
        checked: !!chk.checked,
        opacity: Number(card.dataset.opacity || 100),
        order: index,
      });
    });
    return layers;
  }

  function saveMapState() {
    try {
      if (typeof map2d === "undefined" || !map2d) return;
      var c = map2d.getCenter();
      var state = {
        lat: c.lat,
        lng: c.lng,
        zoom: map2d.getZoom(),
        layers: collectLayerState(),
        ts: Date.now(),
      };
      sessionStorage.setItem(STORAGE_KEY, JSON.stringify(state));
    } catch (e) {}
  }

  function setHomeView() {
    if (typeof map2d === "undefined" || !map2d) return;
    try {
      map2d.setView(HOME_CENTER, HOME_ZOOM, { animate: false });
    } catch (e) {}
  }

  function setLayerChecked(chkId, layerVar, wantOn) {
    var chk = document.getElementById(chkId);
    if (!chk) return;
    var layer = typeof window[layerVar] !== "undefined" ? window[layerVar] : null;
    if (!layer) return;

    if (wantOn) {
      if (
        typeof window.mxliCanLayer === "function" &&
        layerVar === "predios" &&
        !window.mxliCanLayer("predios_mexicali_2025")
      ) {
        return;
      }
      if (
        typeof window.mxliCanLayer === "function" &&
        layerVar === "colonias" &&
        !window.mxliCanLayer("colonias")
      ) {
        return;
      }
    }

    var isOn =
      typeof map2d !== "undefined" && map2d && typeof map2d.hasLayer === "function"
        ? map2d.hasLayer(layer)
        : !!chk.checked;

    if (wantOn === isOn && chk.checked === wantOn) return;

    chk.checked = !!wantOn;
    if (typeof toggleLayer === "function") {
      try {
        toggleLayer(chkId, layer);
      } catch (e) {}
    } else if (wantOn) {
      if (typeof group !== "undefined" && group.addLayer) group.addLayer(layer);
      else if (map2d) layer.addTo(map2d);
    } else if (map2d && map2d.hasLayer(layer)) {
      map2d.removeLayer(layer);
    }
  }

  /** Arranque: Predios ON (consulta) + Colonias ON (solo visible, no identify). */
  function applyStartupDefaults() {
    setHomeView();

    setLayerChecked("predios_chkbox", "predios", true);
    setLayerChecked("colonias_chkbox", "colonias", true);

    if (typeof applyWmsLayerStack === "function") {
      try {
        applyWmsLayerStack();
      } catch (e) {}
    }

    if (typeof window.mxliSetIdentifyLayer === "function") {
      window.mxliSetIdentifyLayer("predios_mexicali_2025");
    }
    if (typeof actualizarCapaActivaConsulta === "function") {
      actualizarCapaActivaConsulta();
    }
    if (typeof window.mxliActivarConsultaPrediosPorDefecto === "function") {
      window.mxliActivarConsultaPrediosPorDefecto();
    }
  }

  /**
   * Restaura solo capas/opacidad (no vista). Usado al volver de flujos internos.
   * La vista de inicio NO se restaura desde la última consulta.
   */
  function restoreMapState(opts) {
    opts = opts || {};
    if (!opts.forceView) {
      // Por diseño: no recuperar lat/lng/zoom de la última búsqueda
      return;
    }
    var raw;
    try {
      raw = sessionStorage.getItem(STORAGE_KEY);
    } catch (e) {
      return;
    }
    if (!raw) return;
    var state;
    try {
      state = JSON.parse(raw);
    } catch (e2) {
      return;
    }
    if (!state || !state.lat || !state.lng) return;
    if (state.ts && Date.now() - state.ts > 12 * 60 * 60 * 1000) return;
    try {
      map2d.setView([state.lat, state.lng], state.zoom || map2d.getZoom(), {
        animate: false,
      });
    } catch (e3) {}
  }

  window.mxliSaveMapState = saveMapState;
  window.mxliRestoreMapState = restoreMapState;
  window.mxliSetHomeView = setHomeView;
  window.mxliApplyStartupDefaults = applyStartupDefaults;

  document.addEventListener("change", function (e) {
    var t = e.target;
    if (!t) return;
    if (
      t.matches &&
      (t.matches(".wms-layer-card input[type=checkbox]") ||
        t.matches(".wms-layer-card input[type=range]"))
    ) {
      saveMapState();
    }
  });

  if (typeof map2d !== "undefined" && map2d && map2d.on) {
    map2d.on("moveend", saveMapState);
    map2d.on("zoomend", saveMapState);
  } else {
    setTimeout(function () {
      if (typeof map2d !== "undefined" && map2d && map2d.on) {
        map2d.on("moveend", saveMapState);
        map2d.on("zoomend", saveMapState);
      }
    }, 800);
  }

  window.addEventListener("pagehide", saveMapState);
  window.addEventListener("beforeunload", saveMapState);

  function boot() {
    // Limpiar restauración antigua de vista (evita quedar en la última consulta)
    try {
      sessionStorage.removeItem("mxli_map_view_state_v1");
      sessionStorage.removeItem(STORAGE_KEY);
    } catch (e) {}

    var tries = 0;
    function tick() {
      tries += 1;
      var ready =
        typeof map2d !== "undefined" &&
        map2d &&
        typeof group !== "undefined" &&
        group &&
        typeof toggleLayer === "function" &&
        typeof window.colonias !== "undefined" &&
        typeof window.predios !== "undefined" &&
        document.getElementById("colonias_chkbox") &&
        document.getElementById("predios_chkbox");
      if (ready) {
        applyStartupDefaults();
        return;
      }
      if (tries < 25) setTimeout(tick, 200);
    }
    setTimeout(tick, 300);
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", boot);
  } else {
    boot();
  }
})();
