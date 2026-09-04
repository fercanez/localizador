(function () {
  "use strict";

  if (window.MxliInspeccionesCapaInit) return;
  window.MxliInspeccionesCapaInit = true;

  var GEOJSON_API = "api/inspections-geojson.php";
  var markers = [];
  var loaded = false;
  var userColorMap = {};
  var mapClickBound = false;

  var USER_COLORS = [
    "#9b2f55",
    "#1a5fd0",
    "#2f7d4b",
    "#e67e22",
    "#8e44ad",
    "#16a085",
    "#c0392b",
    "#2980b9",
    "#d35400",
    "#27ae60",
    "#6c3483",
    "#117a65",
    "#b7950b",
    "#5d6d7e",
    "#922b21",
  ];

  var POPUP_OPTS = {
    minWidth: 280,
    maxWidth: 360,
    maxHeight: 380,
    className: "mxli-inspect-leaflet-popup",
    autoPan: true,
    autoPanPadding: [40, 80],
    closeButton: true,
  };

  function colorForUser(props) {
    var userId = props.created_by != null ? String(props.created_by) : "";
    var userName = String(props.created_by_name || "").trim();
    var key = userId || userName || "0";
    if (userColorMap[key]) return userColorMap[key];
    var hash = 0;
    for (var i = 0; i < key.length; i++) {
      hash = (hash * 31 + key.charCodeAt(i)) >>> 0;
    }
    var color = USER_COLORS[hash % USER_COLORS.length];
    userColorMap[key] = color;
    return color;
  }

  function resetUserColors() {
    userColorMap = {};
  }

  window.mxliInspectColorForUser = colorForUser;
  window.mxliInspectUserColors = USER_COLORS;

  function canViewLayer() {
    if (typeof window.mxliCan !== "function") return false;
    return (
      window.mxliCan("map.inspect") ||
      window.mxliCan("inspections.manage") ||
      window.mxliCan("users.manage")
    );
  }

  function escapeHtml(v) {
    return String(v == null ? "" : v)
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/"/g, "&quot;");
  }

  function formatDate(v) {
    if (!v) return "—";
    try {
      return new Date(v).toLocaleString("es-MX");
    } catch (e) {
      return String(v);
    }
  }

  function photoSrc(url) {
    var u = String(url || "");
    if (!u) return "";
    return u;
  }

  function ensureInspeccionesPane() {
    if (typeof map2d === "undefined" || !map2d || !map2d.createPane) {
      return "markerPane";
    }
    if (!map2d.getPane("inspeccionesPane")) {
      var pane = map2d.createPane("inspeccionesPane");
      pane.style.zIndex = 650;
      pane.style.pointerEvents = "auto";
    } else {
      map2d.getPane("inspeccionesPane").style.zIndex = 650;
      map2d.getPane("inspeccionesPane").style.pointerEvents = "auto";
    }
    return "inspeccionesPane";
  }

  var inspecciones_fotos = L.layerGroup([], {
    title: "Inspecciones fotográficas",
  });
  inspecciones_fotos.id = "inspecciones_fotos";
  window.inspecciones_fotos = inspecciones_fotos;

  function attachPaneToLayer() {
    try {
      inspecciones_fotos.options.pane = ensureInspeccionesPane();
    } catch (e) {}
  }
  attachPaneToLayer();
  setTimeout(attachPaneToLayer, 500);
  setTimeout(attachPaneToLayer, 1500);

  function clearMarkers() {
    markers = [];
    inspecciones_fotos.clearLayers();
  }

  function renderUserLegend(items) {
    var legend = document.getElementById("inspect-users-legend");
    if (!legend) return;

    var byUser = {};
    (items || []).forEach(function (props) {
      var userId = props.created_by != null ? String(props.created_by) : "";
      var userName = String(props.created_by_name || "Sin usuario").trim() || "Sin usuario";
      var key = userId || userName;
      if (!byUser[key]) {
        byUser[key] = { name: userName, color: colorForUser(props) };
      }
    });

    var users = Object.keys(byUser).map(function (k) {
      return byUser[k];
    });

    if (users.length < 1) {
      legend.hidden = true;
      legend.innerHTML = "";
      return;
    }

    legend.hidden = false;
    legend.innerHTML =
      '<p class="inspect-users-legend__title">Color por inspector</p>' +
      users
        .sort(function (a, b) {
          return a.name.localeCompare(b.name, "es");
        })
        .map(function (u) {
          return (
            '<span class="inspect-users-legend__item">' +
            '<span class="inspect-users-legend__dot" style="background:' +
            escapeHtml(u.color) +
            '"></span>' +
            escapeHtml(u.name) +
            "</span>"
          );
        })
        .join("");
  }

  /** Safe string HTML — never throws; missing fields → "—" */
  function buildInspectPopupHtml(props) {
    props = props || {};
    var src = "";
    var types = "";
    var notes = "";
    var clave = "—";
    var fecha = "—";
    var inspector = "—";
    var dotColor = "#9b2f55";
    try {
      src = photoSrc(props.photo_url);
      types = props.types_label != null ? String(props.types_label) : "";
      notes = props.notes != null ? String(props.notes) : "";
      clave = String(props.clavecatas || "—").toUpperCase();
      fecha = String(formatDate(props.captured_at)).toUpperCase();
      inspector = String(props.created_by_name || "—").toUpperCase();
      dotColor = props.marker_color || colorForUser(props);
    } catch (err) {
      console.warn("buildInspectPopupHtml:", err);
    }

    var photoHtml = src
      ? '<button type="button" class="mxli-inspect-popup__thumb-btn" data-photo-src="' +
        escapeHtml(src) +
        '" data-photo-clave="' +
        escapeHtml(props.clavecatas || "") +
        '"><img class="mxli-inspect-popup__photo" src="' +
        escapeHtml(src) +
        '" alt="Foto ' +
        escapeHtml(props.clavecatas || "") +
        '"></button>'
      : "";

    return (
      '<div class="mxli-feature-popup mxli-inspect-popup">' +
      '<div class="mxli-feature-popup__head">Inspección</div>' +
      '<div class="mxli-feature-popup__body mxli-inspect-popup__body">' +
      photoHtml +
      '<table class="mxli-padron-table mxli-inspect-ficha" role="presentation"><tbody>' +
      '<tr><td class="mxli-padron-label">Clave</td><td class="mxli-padron-value"><b>' +
      escapeHtml(clave) +
      "</b></td></tr>" +
      '<tr><td class="mxli-padron-label">Fecha</td><td class="mxli-padron-value"><b>' +
      escapeHtml(fecha) +
      "</b></td></tr>" +
      '<tr><td class="mxli-padron-label">Inspector</td><td class="mxli-padron-value"><b><span class="mxli-inspect-popup__dot" style="background:' +
      escapeHtml(dotColor) +
      '"></span> ' +
      escapeHtml(inspector) +
      "</b></td></tr>" +
      '<tr><td class="mxli-padron-label">Tipos</td><td class="mxli-padron-value"><b>' +
      escapeHtml((types || "—").toUpperCase()) +
      "</b></td></tr>" +
      '<tr><td class="mxli-padron-label">Notas</td><td class="mxli-padron-value"><b>' +
      escapeHtml((notes || "—").toUpperCase()) +
      "</b></td></tr>" +
      "</tbody></table></div></div>"
    );
  }

  function wirePopupPhotoClicks(root) {
    if (!root || !root.querySelectorAll) return;
    root.querySelectorAll("[data-photo-src]").forEach(function (el) {
      el.addEventListener("click", function (ev) {
        ev.preventDefault();
        ev.stopPropagation();
        openMapLightbox(
          el.getAttribute("data-photo-src"),
          el.getAttribute("data-photo-clave") || ""
        );
      });
    });
  }

  function openInspectionMarkerPopup(marker) {
    if (!marker || !marker.mxliInspection) return;
    if (typeof map2d === "undefined" || !map2d) return;
    var props = marker.mxliInspection;
    props.isInspection = true;
    props.layer_type = props.layer_type || "inspeccion_foto";
    props.sourceLayer = "inspecciones_fotos";
    var html = buildInspectPopupHtml(props);
    if (!marker.getPopup()) {
      marker.bindPopup(html, POPUP_OPTS);
    } else {
      marker.setPopupContent(html);
    }
    marker.openPopup();
    try {
      var popEl = marker.getPopup() && marker.getPopup().getElement();
      if (popEl) wirePopupPhotoClicks(popEl);
    } catch (e) {}
  }

  function addFeature(feature) {
    if (!feature || !feature.geometry || feature.geometry.type !== "Point") return;
    var coords = feature.geometry.coordinates || [];
    var lng = Number(coords[0]);
    var lat = Number(coords[1]);
    if (!isFinite(lat) || !isFinite(lng)) return;

    var props = feature.properties || {};
    var color = colorForUser(props);
    props.marker_color = color;
    props.isInspection = true;
    props.layer_type = props.layer_type || "inspeccion_foto";

    var paneName = "markerPane";
    try {
      paneName = ensureInspeccionesPane();
    } catch (e) {}

    // CircleMarker: reliable hit area (radius >= 9), SVG default renderer, custom pane
    var marker = L.circleMarker([lat, lng], {
      radius: 10,
      weight: 2,
      color: "#ffffff",
      fillColor: color,
      fillOpacity: 0.92,
      opacity: 1,
      interactive: true,
      bubblingMouseEvents: true,
      pane: paneName,
      className: "mxli-inspect-circle",
    });
    marker.mxliInspection = props;
    marker.feature = props;

    var popupHtml = buildInspectPopupHtml(props);
    marker.bindPopup(popupHtml, POPUP_OPTS);

    marker.on("popupopen", function () {
      try {
        var el = marker.getPopup() && marker.getPopup().getElement();
        if (el) wirePopupPhotoClicks(el);
      } catch (e) {}
    });

    marker.on("click", function (e) {
      if (e && L && L.DomEvent) {
        L.DomEvent.stopPropagation(e);
        if (e.originalEvent) L.DomEvent.stopPropagation(e.originalEvent);
      }
      openInspectionMarkerPopup(marker);
    });

    markers.push(marker);
    inspecciones_fotos.addLayer(marker);
    if (typeof marker.bringToFront === "function") {
      marker.bringToFront();
    }
  }

  function loadGeoJson(force) {
    if (!canViewLayer()) {
      return Promise.resolve([]);
    }
    if (loaded && !force) {
      return Promise.resolve(
        markers.map(function (m) {
          return m.mxliInspection;
        })
      );
    }

    return fetch(GEOJSON_API, { credentials: "same-origin" })
      .then(function (res) {
        return res.json();
      })
      .then(function (data) {
        clearMarkers();
        resetUserColors();
        var items = [];
        (data.features || []).forEach(function (feature) {
          addFeature(feature);
          if (feature.properties) items.push(feature.properties);
        });
        renderUserLegend(items);
        loaded = true;
        bringInspectionsToTop();
        bindMapClickFallback();
        return markers.map(function (m) {
          return m.mxliInspection;
        });
      })
      .catch(function (err) {
        console.error("Inspecciones capa:", err);
        return [];
      });
  }

  function isLayerOnMap() {
    return typeof map2d !== "undefined" && map2d && map2d.hasLayer(inspecciones_fotos);
  }

  window.isInspeccionesCapaActive = function () {
    return isLayerOnMap();
  };

  function bringInspectionsToTop() {
    var list = document.getElementById("wms-layer-list");
    var card = document.querySelector(
      '.wms-layer-card[data-layer-id="inspecciones_fotos_chkbox"]'
    );

    // Keep card FIRST in Capas UI (do not append to bottom)
    if (list && card && list.firstChild !== card) {
      list.insertBefore(card, list.firstChild);
    }

    if (typeof applyWmsLayerStack === "function") {
      applyWmsLayerStack();
    }

    try {
      ensureInspeccionesPane();
    } catch (e) {}

    if (isLayerOnMap()) {
      if (typeof inspecciones_fotos.bringToFront === "function") {
        inspecciones_fotos.bringToFront();
      }
      markers.forEach(function (m) {
        if (m && typeof m.bringToFront === "function") m.bringToFront();
      });
    }
  }

  window.mxliBringInspectionsToTop = bringInspectionsToTop;

  function findNearestInspection(latlng) {
    if (!isLayerOnMap() || !latlng || typeof map2d === "undefined") return null;
    var clickPoint = map2d.latLngToContainerPoint(latlng);
    var best = null;
    var bestMarker = null;
    var bestDist = Infinity;
    var tolerance = window.matchMedia("(max-width: 900px)").matches ? 32 : 24;

    markers.forEach(function (marker) {
      var p = map2d.latLngToContainerPoint(marker.getLatLng());
      var dist = Math.hypot(p.x - clickPoint.x, p.y - clickPoint.y);
      if (dist <= tolerance && dist < bestDist) {
        bestDist = dist;
        best = marker.mxliInspection;
        bestMarker = marker;
      }
    });

    return bestMarker ? { props: best, marker: bestMarker } : null;
  }

  window.mxliFindInspectionFeature = function (latlng) {
    var hit = findNearestInspection(latlng);
    return hit ? hit.props : null;
  };

  /** Fallback when WMS tiles steal marker clicks: map click still fires */
  function onInspectMapClick(e) {
    if (!isLayerOnMap() || !e || !e.latlng) return;
    // If info tool is active, getFeatureInfo already handles inspections — skip duplicate
    var infoBtn = document.querySelector("#getFeatureInfoBtn");
    if (infoBtn && infoBtn.classList.contains("btnIsActive")) return;

    var hit = findNearestInspection(e.latlng);
    if (!hit || !hit.marker) return;
    openInspectionMarkerPopup(hit.marker);
  }

  function bindMapClickFallback() {
    if (typeof map2d === "undefined" || !map2d || mapClickBound) return;
    map2d.on("click", onInspectMapClick);
    mapClickBound = true;
  }

  window.mxliRebindInspectMapClick = function () {
    if (typeof map2d === "undefined" || !map2d) return;
    map2d.off("click", onInspectMapClick);
    mapClickBound = false;
    if (isLayerOnMap()) {
      bindMapClickFallback();
    }
  };

  window.isInspectionFeatureInfo = function (info) {
    if (!info) return false;
    if (info.isInspection) return true;
    if (info.sourceLayer === "inspecciones_fotos") return true;
    var props = info.properties || info;
    return !!(props && props.layer_type === "inspeccion_foto");
  };

  function ensureMapLightbox() {
    if (document.getElementById("inspect-lightbox")) return;
    var dlg = document.createElement("dialog");
    dlg.id = "inspect-lightbox";
    dlg.className = "inspect-lightbox";
    dlg.setAttribute("aria-label", "Foto ampliada");
    dlg.innerHTML =
      '<button type="button" id="inspect-lightbox-close" class="inspect-lightbox__close" aria-label="Cerrar">×</button>' +
      '<img id="inspect-lightbox-img" alt="Foto ampliada">' +
      '<p id="inspect-lightbox-caption" class="inspect-lightbox__caption"></p>';
    document.body.appendChild(dlg);
    function closeLb() {
      if (typeof dlg.close === "function") dlg.close();
      else dlg.removeAttribute("open");
    }
    var closeBtn = document.getElementById("inspect-lightbox-close");
    if (closeBtn) closeBtn.addEventListener("click", closeLb);
    dlg.addEventListener("click", function (ev) {
      if (ev.target === dlg) closeLb();
    });
  }

  function openMapLightbox(src, clave) {
    ensureMapLightbox();
    var box = document.getElementById("inspect-lightbox");
    var img = document.getElementById("inspect-lightbox-img");
    var cap = document.getElementById("inspect-lightbox-caption");
    if (!box || !img) return;
    img.src = src;
    img.alt = "Foto " + (clave || "");
    if (cap) cap.textContent = String(clave || "").toUpperCase();
    if (typeof box.showModal === "function") box.showModal();
    else box.setAttribute("open", "");
  }

  window.createInspectionPopup = function (info) {
    var props = (info && (info.properties || info)) || {};
    var wrap = document.createElement("div");
    wrap.innerHTML = buildInspectPopupHtml(props);
    var div = wrap.firstChild || wrap;
    wirePopupPhotoClicks(div);
    return div;
  };

  function onLayerToggle() {
    var chk = document.getElementById("inspecciones_fotos_chkbox");
    if (!chk) return;
    if (chk.checked) {
      loadGeoJson(false).then(function () {
        bringInspectionsToTop();
        bindMapClickFallback();
      });
    } else {
      clearMarkers();
      resetUserColors();
      renderUserLegend([]);
      loaded = false;
    }
  }

  function autoActivateLayer() {
    if (!canViewLayer()) return false;

    var chk = document.getElementById("inspecciones_fotos_chkbox");
    if (!chk || typeof toggleLayer !== "function" || !window.inspecciones_fotos) {
      return false;
    }

    if (!chk.checked) {
      chk.checked = true;
      toggleLayer("inspecciones_fotos_chkbox", inspecciones_fotos);
    }

    bringInspectionsToTop();
    bindMapClickFallback();

    return loadGeoJson(false).then(function () {
      bringInspectionsToTop();
    });
  }

  function init() {
    if (!canViewLayer()) return;

    attachPaneToLayer();

    var chk = document.getElementById("inspecciones_fotos_chkbox");
    if (chk) {
      chk.addEventListener("change", onLayerToggle);
    }

    if (typeof map2d !== "undefined" && map2d) {
      map2d.on("overlayadd", function (e) {
        if (e.layer === inspecciones_fotos && !loaded) {
          loadGeoJson(false).then(function () {
            bringInspectionsToTop();
            bindMapClickFallback();
          });
        }
        if (e.layer === inspecciones_fotos) {
          bringInspectionsToTop();
          bindMapClickFallback();
        }
      });
    }

    var attempts = 0;
    function tryAutoActivate() {
      if (!canViewLayer()) return;
      attempts += 1;
      var ok =
        document.getElementById("inspecciones_fotos_chkbox") &&
        typeof toggleLayer === "function" &&
        typeof group !== "undefined" &&
        typeof map2d !== "undefined";

      if (ok) {
        autoActivateLayer();
        return;
      }
      if (attempts < 20) {
        setTimeout(tryAutoActivate, 350);
      }
    }

    setTimeout(tryAutoActivate, 600);
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", init);
  } else {
    init();
  }
})();
