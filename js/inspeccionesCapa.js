(function () {
  "use strict";

  if (window.MxliInspeccionesCapaInit) return;
  window.MxliInspeccionesCapaInit = true;

  var GEOJSON_API = "api/inspections-geojson.php";
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

  /** @type {Record<string, {id:string, area:string, title:string, chkId:string, legendId:string, layer: L.LayerGroup, markers: any[], loaded: boolean, userColorMap: Record<string,string>}>} */
  var LAYERS = {};

  function colorForUser(props, state) {
    var map = (state && state.userColorMap) || {};
    var userId = props.created_by != null ? String(props.created_by) : "";
    var userName = String(props.created_by_name || "").trim();
    var key = userId || userName || "0";
    if (map[key]) return map[key];
    var hash = 0;
    for (var i = 0; i < key.length; i++) {
      hash = (hash * 31 + key.charCodeAt(i)) >>> 0;
    }
    var color = USER_COLORS[hash % USER_COLORS.length];
    map[key] = color;
    if (state) state.userColorMap = map;
    return color;
  }

  window.mxliInspectColorForUser = function (props) {
    var area = (props && props.inspection_area) === "catastro" ? "catastro" : "urbano";
    var state = LAYERS[area];
    return colorForUser(props || {}, state || { userColorMap: {} });
  };
  window.mxliInspectUserColors = USER_COLORS;

  function canViewLayer() {
    return userInspectionAreas().length > 0;
  }

  function userInspectionAreas() {
    if (typeof window.mxliCan !== "function") return [];
    var areas = [];
    if (
      window.mxliCan("map.inspect") ||
      window.mxliCan("inspections.manage") ||
      window.mxliCan("users.manage")
    ) {
      areas.push("urbano");
    }
    if (
      window.mxliCan("map.inspect.catastro") ||
      window.mxliCan("inspections.catastro.manage") ||
      window.mxliCan("users.manage")
    ) {
      areas.push("catastro");
    }
    return areas;
  }

  function canViewArea(area) {
    return userInspectionAreas().indexOf(area) >= 0;
  }

  function geojsonUrl(area) {
    return GEOJSON_API + "?area=" + encodeURIComponent(area);
  }

  function adminBoardHref(area) {
    return area === "catastro"
      ? "admin/inspecciones-catastro.php"
      : "admin/inspecciones.php";
  }

  function layerIdForArea(area) {
    return area === "catastro" ? "inspecciones_catastro" : "inspecciones_fotos";
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
    return String(url || "");
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

  function makeLayerGroup(id, title) {
    var group = L.layerGroup([], { title: title });
    group.id = id;
    try {
      group.options.pane = ensureInspeccionesPane();
    } catch (e) {}
    return group;
  }

  function defineLayer(area, title, chkId, legendId) {
    var id = layerIdForArea(area);
    var group = makeLayerGroup(id, title);
    window[id] = group;
    LAYERS[area] = {
      id: id,
      area: area,
      title: title,
      chkId: chkId,
      legendId: legendId,
      layer: group,
      markers: [],
      loaded: false,
      userColorMap: {},
    };
    return LAYERS[area];
  }

  defineLayer(
    "urbano",
    "Inspecciones fotográficas",
    "inspecciones_fotos_chkbox",
    "inspect-users-legend-urbano"
  );
  defineLayer(
    "catastro",
    "Inspecciones Catastro",
    "inspecciones_catastro_chkbox",
    "inspect-users-legend-catastro"
  );

  function attachPaneToLayers() {
    Object.keys(LAYERS).forEach(function (area) {
      try {
        LAYERS[area].layer.options.pane = ensureInspeccionesPane();
      } catch (e) {}
    });
  }
  attachPaneToLayers();
  setTimeout(attachPaneToLayers, 500);
  setTimeout(attachPaneToLayers, 1500);

  function shapeSwatchHtml(color, area, extraClass) {
    var cls = extraClass ? " " + extraClass : "";
    if (area === "catastro") {
      return (
        '<span class="mxli-inspect-shape mxli-inspect-shape--tri' +
        cls +
        '" style="color:' +
        escapeHtml(color) +
        '" aria-hidden="true">' +
        '<svg viewBox="0 0 24 24" focusable="false">' +
        '<polygon points="12,3 22,21 2,21" fill="currentColor" stroke="#ffffff" stroke-width="2" stroke-linejoin="round"/>' +
        "</svg></span>"
      );
    }
    return (
      '<span class="mxli-inspect-shape mxli-inspect-shape--circle' +
      cls +
      '" style="background-color:' +
      escapeHtml(color) +
      '" aria-hidden="true"></span>'
    );
  }

  function triangleDivIcon(color) {
    return L.divIcon({
      className: "mxli-inspect-marker-wrap",
      html: shapeSwatchHtml(color, "catastro", "mxli-inspect-shape--map"),
      iconSize: [26, 26],
      iconAnchor: [13, 18],
      popupAnchor: [0, -14],
    });
  }

  function clearLayerMarkers(state) {
    state.markers = [];
    state.layer.clearLayers();
    state.userColorMap = {};
    state.loaded = false;
  }

  function renderUserLegend(state, items) {
    var legend = document.getElementById(state.legendId);
    if (!legend) return;

    var byUser = {};
    (items || []).forEach(function (props) {
      var userId = props.created_by != null ? String(props.created_by) : "";
      var userName = String(props.created_by_name || "Sin usuario").trim() || "Sin usuario";
      var key = userId || userName;
      if (!byUser[key]) {
        byUser[key] = { name: userName, color: colorForUser(props, state) };
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
            shapeSwatchHtml(u.color, state.area, "mxli-inspect-shape--legend") +
            escapeHtml(u.name) +
            "</span>"
          );
        })
        .join("");
  }

  function buildInspectPopupHtml(props) {
    props = props || {};
    var src = "";
    var types = "";
    var notes = "";
    var clave = "—";
    var fecha = "—";
    var inspector = "—";
    var area = (props.inspection_area || "urbano") === "catastro" ? "catastro" : "urbano";
    var state = LAYERS[area];
    var dotColor = "#9b2f55";
    try {
      src = photoSrc(props.photo_url);
      types = props.types_label != null ? String(props.types_label) : "";
      notes = props.notes != null ? String(props.notes) : "";
      clave = String(props.clavecatas || "—").toUpperCase();
      fecha = String(formatDate(props.captured_at)).toUpperCase();
      inspector = String(props.created_by_name || "—").toUpperCase();
      dotColor = props.marker_color || colorForUser(props, state);
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

    var typesRow =
      area === "catastro"
        ? ""
        : '<tr><td class="mxli-padron-label">Tipos</td><td class="mxli-padron-value"><b>' +
          escapeHtml((types || "—").toUpperCase()) +
          "</b></td></tr>";

    return (
      '<div class="mxli-feature-popup mxli-inspect-popup">' +
      '<div class="mxli-feature-popup__head">' +
      escapeHtml(area === "catastro" ? "Inspección Catastro" : "Inspección fotográfica") +
      "</div>" +
      '<div class="mxli-feature-popup__body mxli-inspect-popup__body">' +
      photoHtml +
      '<table class="mxli-padron-table mxli-inspect-ficha" role="presentation"><tbody>' +
      '<tr><td class="mxli-padron-label">Clave</td><td class="mxli-padron-value"><b>' +
      escapeHtml(clave) +
      "</b></td></tr>" +
      '<tr><td class="mxli-padron-label">Fecha</td><td class="mxli-padron-value"><b>' +
      escapeHtml(fecha) +
      "</b></td></tr>" +
      '<tr><td class="mxli-padron-label">Inspector</td><td class="mxli-padron-value"><b>' +
      shapeSwatchHtml(dotColor, area, "mxli-inspect-shape--popup") +
      " " +
      escapeHtml(inspector) +
      "</b></td></tr>" +
      typesRow +
      '<tr><td class="mxli-padron-label">Notas</td><td class="mxli-padron-value"><b>' +
      escapeHtml((notes || "—").toUpperCase()) +
      "</b></td></tr>" +
      '<tr><td class="mxli-padron-label">Área</td><td class="mxli-padron-value"><b>' +
      escapeHtml(String(props.area_label || (area === "catastro" ? "Catastro" : "Control Urbano")).toUpperCase()) +
      "</b></td></tr>" +
      '<tr><td colspan="2" class="mxli-padron-value"><a href="' +
      escapeHtml(adminBoardHref(area)) +
      '">Ver en administración</a></td></tr>' +
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
    var area = (props.inspection_area || "urbano") === "catastro" ? "catastro" : "urbano";
    props.sourceLayer = layerIdForArea(area);
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

  function addFeature(state, feature) {
    if (!feature || !feature.geometry || feature.geometry.type !== "Point") return;
    var coords = feature.geometry.coordinates || [];
    var lng = Number(coords[0]);
    var lat = Number(coords[1]);
    if (!isFinite(lat) || !isFinite(lng)) return;

    var props = feature.properties || {};
    props.inspection_area = state.area;
    var color = colorForUser(props, state);
    props.marker_color = color;
    props.isInspection = true;
    props.layer_type = "inspeccion_foto";
    props.sourceLayer = state.id;

    var paneName = "markerPane";
    try {
      paneName = ensureInspeccionesPane();
    } catch (e) {}

    var marker;
    if (state.area === "catastro") {
      marker = L.marker([lat, lng], {
        icon: triangleDivIcon(color),
        interactive: true,
        bubblingMouseEvents: true,
        keyboard: true,
        pane: paneName,
        riseOnHover: true,
        zIndexOffset: 20,
      });
    } else {
      marker = L.circleMarker([lat, lng], {
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
    }
    marker.mxliInspection = props;
    marker.feature = props;
    marker.mxliInspectArea = state.area;

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

    state.markers.push(marker);
    state.layer.addLayer(marker);
    if (typeof marker.bringToFront === "function") {
      marker.bringToFront();
    }
  }

  function loadGeoJson(area, force) {
    var state = LAYERS[area];
    if (!state || !canViewArea(area)) {
      return Promise.resolve([]);
    }
    if (state.loaded && !force) {
      return Promise.resolve(
        state.markers.map(function (m) {
          return m.mxliInspection;
        })
      );
    }

    return fetch(geojsonUrl(area), { credentials: "same-origin" })
      .then(function (res) {
        return res.json();
      })
      .then(function (data) {
        clearLayerMarkers(state);
        var items = [];
        (data.features || []).forEach(function (feature) {
          var props = feature.properties || {};
          var featArea =
            (props.inspection_area || area) === "catastro" ? "catastro" : "urbano";
          if (featArea !== area) return;
          if (!canViewArea(featArea)) return;
          addFeature(state, feature);
          items.push(props);
        });
        state.loaded = true;
        renderUserLegend(state, items);
        bringInspectionsToTop();
        bindMapClickFallback();
        return state.markers.map(function (m) {
          return m.mxliInspection;
        });
      })
      .catch(function (err) {
        console.error("Inspecciones capa (" + area + "):", err);
        return [];
      });
  }

  function isAreaLayerOnMap(area) {
    var state = LAYERS[area];
    return (
      !!state &&
      typeof map2d !== "undefined" &&
      map2d &&
      map2d.hasLayer(state.layer)
    );
  }

  function anyInspectLayerOnMap() {
    return isAreaLayerOnMap("urbano") || isAreaLayerOnMap("catastro");
  }

  window.isInspeccionesCapaActive = function () {
    return anyInspectLayerOnMap();
  };

  function bringInspectionsToTop() {
    var list = document.getElementById("wms-layer-list");
    var order = ["inspecciones_fotos_chkbox", "inspecciones_catastro_chkbox"];
    // Keep inspection cards at the top of Capas (urbano first, then catastro)
    for (var i = order.length - 1; i >= 0; i--) {
      var card = document.querySelector(
        '.wms-layer-card[data-layer-id="' + order[i] + '"]'
      );
      if (list && card) {
        list.insertBefore(card, list.firstChild);
      }
    }

    if (typeof applyWmsLayerStack === "function") {
      applyWmsLayerStack();
    }

    try {
      ensureInspeccionesPane();
    } catch (e) {}

    ["urbano", "catastro"].forEach(function (area) {
      var state = LAYERS[area];
      if (!state || !isAreaLayerOnMap(area)) return;
      if (typeof state.layer.bringToFront === "function") {
        state.layer.bringToFront();
      }
    });
  }

  window.mxliBringInspectionsToTop = bringInspectionsToTop;

  function allActiveMarkers() {
    var list = [];
    ["urbano", "catastro"].forEach(function (area) {
      if (!isAreaLayerOnMap(area)) return;
      var state = LAYERS[area];
      if (!state) return;
      state.markers.forEach(function (m) {
        list.push(m);
      });
    });
    return list;
  }

  function findNearestInspection(latlng) {
    if (!anyInspectLayerOnMap() || !latlng || typeof map2d === "undefined") return null;
    var clickPoint = map2d.latLngToContainerPoint(latlng);
    var best = null;
    var bestMarker = null;
    var bestDist = Infinity;
    var tolerance = window.matchMedia("(max-width: 900px)").matches ? 36 : 28;

    allActiveMarkers().forEach(function (marker) {
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

  function onInspectMapClick(e) {
    if (!anyInspectLayerOnMap() || !e || !e.latlng) return;
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
    if (anyInspectLayerOnMap()) {
      bindMapClickFallback();
    }
  };

  window.isInspectionFeatureInfo = function (info) {
    if (!info) return false;
    if (info.isInspection) return true;
    if (
      info.sourceLayer === "inspecciones_fotos" ||
      info.sourceLayer === "inspecciones_catastro"
    ) {
      return true;
    }
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

  window.mxliRefreshInspeccionesCapa = function (area) {
    var areas = area ? [area] : userInspectionAreas();
    return Promise.all(
      areas.map(function (a) {
        if (!LAYERS[a] || !canViewArea(a)) return Promise.resolve([]);
        LAYERS[a].loaded = false;
        if (!isAreaLayerOnMap(a)) {
          return autoActivateLayer(a);
        }
        return loadGeoJson(a, true);
      })
    );
  };

  window.createInspectionPopup = function (info) {
    var props = (info && (info.properties || info)) || {};
    var wrap = document.createElement("div");
    wrap.innerHTML = buildInspectPopupHtml(props);
    var div = wrap.firstChild || wrap;
    wirePopupPhotoClicks(div);
    return div;
  };

  function onLayerToggle(area) {
    var state = LAYERS[area];
    if (!state) return;
    var chk = document.getElementById(state.chkId);
    if (!chk) return;
    if (chk.checked) {
      loadGeoJson(area, false).then(function () {
        bringInspectionsToTop();
        bindMapClickFallback();
      });
    } else {
      clearLayerMarkers(state);
      renderUserLegend(state, []);
    }
  }

  function autoActivateLayer(area) {
    if (!canViewArea(area)) return Promise.resolve(false);
    var state = LAYERS[area];
    if (!state) return Promise.resolve(false);

    var chk = document.getElementById(state.chkId);
    if (!chk || typeof toggleLayer !== "function" || !window[state.id]) {
      return Promise.resolve(false);
    }

    if (!chk.checked) {
      chk.checked = true;
      toggleLayer(state.chkId, state.layer);
    }

    bringInspectionsToTop();
    bindMapClickFallback();

    return loadGeoJson(area, false).then(function () {
      bringInspectionsToTop();
      return true;
    });
  }

  function autoActivateAll() {
    var areas = userInspectionAreas();
    var chain = Promise.resolve();
    areas.forEach(function (area) {
      chain = chain.then(function () {
        return autoActivateLayer(area);
      });
    });
    return chain;
  }

  function init() {
    if (!canViewLayer()) return;

    attachPaneToLayers();

    Object.keys(LAYERS).forEach(function (area) {
      if (!canViewArea(area)) return;
      var state = LAYERS[area];
      var chk = document.getElementById(state.chkId);
      if (chk) {
        chk.addEventListener("change", function () {
          onLayerToggle(area);
        });
      }
    });

    if (typeof map2d !== "undefined" && map2d) {
      map2d.on("overlayadd", function (e) {
        Object.keys(LAYERS).forEach(function (area) {
          var state = LAYERS[area];
          if (e.layer !== state.layer) return;
          if (!state.loaded) {
            loadGeoJson(area, false).then(function () {
              bringInspectionsToTop();
              bindMapClickFallback();
            });
          } else {
            bringInspectionsToTop();
            bindMapClickFallback();
          }
        });
      });
    }

    var attempts = 0;
    function tryAutoActivate() {
      if (!canViewLayer()) return;
      attempts += 1;
      var areas = userInspectionAreas();
      var ready =
        typeof toggleLayer === "function" &&
        typeof group !== "undefined" &&
        typeof map2d !== "undefined" &&
        areas.every(function (area) {
          return !!document.getElementById(LAYERS[area].chkId) && !!window[LAYERS[area].id];
        });

      if (ready) {
        autoActivateAll();
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
