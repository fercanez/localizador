(function () {
  "use strict";

  function getLayerByVar(layerVar) {
    return window[layerVar];
  }

  function getCardByChkId(chkId) {
    return document.querySelector('.wms-layer-card[data-layer-id="' + chkId + '"]');
  }

  window.setWmsLayerOpacity = function (layerVar, value, labelId, chkId) {
    const opacity = Number(value) / 100;
    const layer = getLayerByVar(layerVar);
    const label = document.getElementById(labelId);
    const card = chkId
      ? getCardByChkId(chkId)
      : document.querySelector('.wms-layer-card[data-layer-var="' + layerVar + '"]');

    if (card) card.dataset.opacity = String(value);
    if (label) label.textContent = Math.round(Number(value)) + "%";

    if (layer && typeof layer.setOpacity === "function") {
      layer.setOpacity(opacity);
    }
  };

  window.moveWmsLayer = function (chkId, direction) {
    const card = getCardByChkId(chkId);
    const list = document.getElementById("wms-layer-list");
    if (!card || !list) return;

    if (direction === "up" && card.previousElementSibling) {
      list.insertBefore(card, card.previousElementSibling);
    } else if (direction === "down" && card.nextElementSibling) {
      list.insertBefore(card.nextElementSibling, card);
    }

    applyWmsLayerStack();
  };

  window.applyWmsLayerStack = function () {
    const list = document.getElementById("wms-layer-list");
    if (!list || typeof group === "undefined") return;

    const cards = Array.from(list.querySelectorAll(".wms-layer-card"));
    let z = 700;

    cards.forEach(function (card) {
      const checkbox = card.querySelector('input[type="checkbox"]');
      const layer = getLayerByVar(card.dataset.layerVar);

      if (!checkbox || !checkbox.checked || !layer) return;

      if (typeof layer.setZIndex === "function") {
        layer.setZIndex(z);
      }
      if (typeof layer.bringToFront === "function") {
        layer.bringToFront();
      }
      z += 10;
    });

    if (typeof group.bringToFront === "function") {
      group.bringToFront();
    }

    // Keep inspection points above WMS tiles (map + interaction)
    if (typeof window.inspecciones_fotos !== "undefined" && window.inspecciones_fotos) {
      if (typeof window.inspecciones_fotos.bringToFront === "function") {
        window.inspecciones_fotos.bringToFront();
      }
    }
    if (typeof window.mxliBringInspectionsToTop === "function") {
      // only raise map layers; avoid re-entrancy on DOM order ? call bring only
      try {
        if (window.inspecciones_fotos && typeof map2d !== "undefined" && map2d.hasLayer(window.inspecciones_fotos)) {
          window.inspecciones_fotos.bringToFront();
        }
      } catch (e) {}
    }
  };

  window.syncWmsLayerCardState = function (chkId, isActive) {
    const card = getCardByChkId(chkId);
    if (!card) return;

    card.classList.toggle("is-active", !!isActive);

    if (isActive) {
      const layerVar = card.dataset.layerVar;
      const opacity = Number(card.dataset.opacity || 100);
      setWmsLayerOpacity(
        layerVar,
        opacity,
        chkId + "_opacity_label",
        chkId
      );
    }
  };
})();
