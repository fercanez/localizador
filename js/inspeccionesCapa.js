(function () {
  "use strict";

  if (window.MxliInspeccionesCapaInit) return;
  window.MxliInspeccionesCapaInit = true;

  var GEOJSON_API = "api/inspections-geojson.php";
  var markers = [];
  var loaded = false;
  var userColorMap = {};

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

  function colorForUser(props) {
    var userId = props.created_by != null ? String(props.created_by) : "";
    var userName = String(props.created_by_name || "").trim();
    var key = userId || userName || "0";

    if (userColorMap[key]) {
      return userColorMap[key];
    }

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
      return v;
    }
  }

  function photoSrc(url) {
    var u = String(url || "");
    if (!u) return "";
    if (u.charAt(0) === "/" || /^https?:/i.test(u)) return u;
    return u;
  }

  var inspecciones_fotos = L.layerGroup([], {
    title: "Inspecciones fotográficas",
    pane: "overlayPane",
  });
  inspecciones_fotos.id = "inspecciones_fotos";
  window.inspecciones_fotos = inspecciones_fotos;

  function clearMarkers() {
    markers = [];
    inspecciones_fotos.clearLayers();
  }

  function markerIcon(color) {
    var c = color || "#9b2f55";
    return L.divIcon({
      className: "mxli-inspect-marker-wrap",
      html:
        '<span class="mxli-inspect-marker" style="background:' +
        c +
        ";box-shadow:0 0 0 2px #fff, 0 0 0 4px " +
        c +
        '66, 0 2px 6px rgba(0,0,0,0.35)" aria-hidden="true"></span>',
      iconSize: [18, 18],
      iconAnchor: [9, 9],
    });
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
        byUser[key] = {
          name: userName,
          color: colorForUser(props),
        };
      }
    });

    var users = Object.keys(byUser).map(function (k) {
      return byUser[k];
    });

    if (users.length <= 1) {
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

  function addFeature(feature) {
    if (!feature || !feature.geometry || feature.geometry.type !== "Point") return;
    var coords = feature.geometry.coordinates || [];
    var lng = Number(coords[0]);
    var lat = Number(coords[1]);
    if (!isFinite(lat) || !isFinite(lng)) return;

    var props = feature.properties || {};
    var color = colorForUser(props);
    props.marker_color = color;
    var marker = L.marker([lat, lng], {
      icon: markerIcon(color),
      title: (props.clavecatas || "Inspección") + " · " + (props.created_by_name || ""),
      interactive: false,
    });
    marker.mxliInspection = props;
    marker.feature = props;
    markers.push(marker);
    inspecciones_fotos.addLayer(marker);
  }

  function loadGeoJson(force) {
    if (!canViewLayer()) {
      return Promise.resolve([]);
    }
    if (loaded && !force) {
      return Promise.resolve(markers.map(function (m) {
        return m.mxliInspection;
      }));
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
    return typeof map2d !== "undefined" && map2d.hasLayer(inspecciones_fotos);
  }

  window.isInspeccionesCapaActive = function () {
    return isLayerOnMap();
  };

  function bringInspectionsToTop() {
    var list = document.getElementById("wms-layer-list");
    var card = document.querySelector(
      '.wms-layer-card[data-layer-id="inspecciones_fotos_chkbox"]'
    );

    if (list && card) {
      list.appendChild(card);
    }

    if (typeof applyWmsLayerStack === "function") {
      applyWmsLayerStack();
    }

    if (isLayerOnMap()) {
      if (typeof inspecciones_fotos.bringToFront === "function") {
        inspecciones_fotos.bringToFront();
      }
      if (typeof group !== "undefined" && typeof group.bringToFront === "function") {
        group.bringToFront();
      }
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

    return loadGeoJson(false).then(function () {
      bringInspectionsToTop();
    });
  }

  window.mxliAutoActivateInspeccionesCapa = autoActivateLayer;

  window.mxliRefreshInspeccionesCapa = function () {
    if (!canViewLayer()) return Promise.resolve();
    loaded = false;
    return loadGeoJson(true).then(function () {
      if (isLayerOnMap()) {
        bringInspectionsToTop();
      }
    });
  };

  window.mxliFindInspectionFeature = function (latlng) {
    if (!isLayerOnMap() || !latlng || typeof map2d === "undefined") return null;

    var clickPoint = map2d.latLngToContainerPoint(latlng);
    var best = null;
    var bestDist = Infinity;
    var tolerance = window.matchMedia("(max-width: 900px)").matches ? 28 : 22;

    markers.forEach(function (marker) {
      var p = map2d.latLngToContainerPoint(marker.getLatLng());
      var dist = Math.hypot(p.x - clickPoint.x, p.y - clickPoint.y);
      if (dist <= tolerance && dist < bestDist) {
        bestDist = dist;
        best = marker.mxliInspection;
      }
    });

    return best;
  };

  window.isInspectionFeatureInfo = function (info) {
    if (!info) return false;
    if (info.isInspection) return true;
    if (info.sourceLayer === "inspecciones_fotos") return true;
    var props = info.properties || info;
    return !!(props && props.layer_type === "inspeccion_foto");
  };

  window.createInspectionPopup = function (info) {
    var props = info.properties || info || {};
    var src = photoSrc(props.photo_url);
    var div = document.createElement("div");
    div.className = "mxli-feature-popup mxli-inspect-popup";

    var photoHtml = src
      ? '<img class="mxli-inspect-popup__photo" src="' +
        escapeHtml(src) +
        '" alt="Foto ' +
        escapeHtml(props.clavecatas || "") +
        '">'
      : "";

    var inspector = escapeHtml(props.created_by_name || "—");
    var dotColor = props.marker_color || colorForUser(props);
    var inspectorLine =
      '<span class="mxli-inspect-popup__inspector">' +
      '<span class="mxli-inspect-popup__dot" style="background:' +
      escapeHtml(dotColor) +
      '"></span>' +
      inspector +
      "</span>";

    div.innerHTML =
      '<div class="mxli-feature-popup__head">Inspección fotográfica</div>' +
      '<div class="mxli-feature-popup__body mxli-inspect-popup__body">' +
      photoHtml +
      "<div><b>Clave:</b> " +
      escapeHtml(props.clavecatas || "—") +
      "</div>" +
      "<div><b>Fecha:</b> " +
      escapeHtml(formatDate(props.captured_at)) +
      "</div>" +
      "<div><b>Inspector:</b> " +
      inspectorLine +
      "</div>" +
      "<div><b>Notas:</b> " +
      escapeHtml(props.notes || "—") +
      "</div>" +
      (src
        ? '<a class="mxli-inspect-popup__link" href="admin/inspecciones.php">Ver en módulo</a>'
        : "") +
      "</div>";

    return div;
  };

  function onLayerToggle() {
    var chk = document.getElementById("inspecciones_fotos_chkbox");
    if (!chk) return;
    if (chk.checked) {
      loadGeoJson(false).then(function () {
        bringInspectionsToTop();
      });
    } else {
      clearMarkers();
      resetUserColors();
      renderUserLegend([]);
      loaded = false;
    }
  }

  function init() {
    if (!canViewLayer()) return;

    var chk = document.getElementById("inspecciones_fotos_chkbox");
    if (chk) {
      chk.addEventListener("change", onLayerToggle);
    }

    if (typeof group !== "undefined") {
      group.on("layeradd", function (e) {
        if (e.layer === inspecciones_fotos && !loaded) {
          loadGeoJson(false).then(function () {
            bringInspectionsToTop();
          });
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
