(function () {
  "use strict";

  if (window.MxliInspeccionFotosInit) return;
  window.MxliInspeccionFotosInit = true;

  var API = "api/inspections.php";
  var inspectionLayer = null;
  var markersById = {};
  var loadTimer = null;

  function canInspect() {
    if (typeof window.mxliCan !== "function") return false;
    return (
      window.mxliCan("map.inspect") ||
      window.mxliCan("map.inspect.catastro") ||
      window.mxliCan("map.inspect.ecologica") ||
      window.mxliCan("inspections.manage") ||
      window.mxliCan("inspections.catastro.manage") ||
      window.mxliCan("inspections.ecologica.manage") ||
      window.mxliCan("users.manage")
    );
  }

  function canInspectUrbano() {
    return (
      typeof window.mxliCan === "function" &&
      (window.mxliCan("map.inspect") ||
        window.mxliCan("inspections.manage") ||
        window.mxliCan("users.manage"))
    );
  }

  function ensureInspectPhotoInput() {
    var input = document.getElementById("inspect-photo-input");
    if (!input) {
      input = document.createElement("input");
      input.type = "file";
      input.id = "inspect-photo-input";
      input.accept = "image/*";
      input.setAttribute("capture", "environment");
      input.hidden = true;
      document.body.appendChild(input);
    } else if (input.parentNode !== document.body) {
      document.body.appendChild(input);
    }
    return input;
  }

  function releaseInspectDialog() {
    var dlg = document.getElementById("inspect-photo-dialog");
    if (!dlg || !dlg.open) return;
    document.querySelectorAll("[inert]").forEach(function (el) {
      if (el !== dlg && !dlg.contains(el)) el.removeAttribute("inert");
    });
  }

  function prepareInspectionPhoto(file) {
    return new Promise(function (resolve, reject) {
      if (!file || !/^image\//.test(file.type || "") || file.type === "image/gif") {
        resolve(file);
        return;
      }
      var url = URL.createObjectURL(file);
      var img = new Image();
      img.onload = function () {
        URL.revokeObjectURL(url);
        var w = img.naturalWidth || img.width;
        var h = img.naturalHeight || img.height;
        if (!w || !h) {
          resolve(file);
          return;
        }
        var maxEdge = 1600;
        var scale = Math.min(1, maxEdge / Math.max(w, h));
        var canvas = document.createElement("canvas");
        canvas.width = Math.max(1, Math.round(w * scale));
        canvas.height = Math.max(1, Math.round(h * scale));
        var ctx = canvas.getContext("2d");
        if (!ctx) {
          resolve(file);
          return;
        }
        ctx.drawImage(img, 0, 0, canvas.width, canvas.height);
        canvas.toBlob(
          function (blob) {
            if (!blob) {
              resolve(file);
              return;
            }
            var name = String(file.name || "foto.jpg").replace(/\.\w+$/, "") + ".jpg";
            resolve(new File([blob], name, { type: "image/jpeg" }));
          },
          "image/jpeg",
          0.72
        );
      };
      img.onerror = function () {
        URL.revokeObjectURL(url);
        reject(new Error("foto"));
      };
      img.src = url;
    });
  }

  function canInspectCatastro() {
    return (
      typeof window.mxliCan === "function" &&
      (window.mxliCan("map.inspect.catastro") ||
        window.mxliCan("inspections.catastro.manage") ||
        window.mxliCan("users.manage"))
    );
  }

  function canInspectEcologica() {
    return (
      typeof window.mxliCan === "function" &&
      (window.mxliCan("map.inspect.ecologica") ||
        window.mxliCan("inspections.ecologica.manage"))
    );
  }

  function userInspectionAreas() {
    var areas = [];
    if (canInspectUrbano()) areas.push("urbano");
    if (canInspectCatastro()) areas.push("catastro");
    if (canInspectEcologica()) areas.push("ecologica");
    return areas;
  }

  function defaultInspectionArea() {
    var areas = userInspectionAreas();
    if (areas.length === 1) return areas[0];
    if (areas.indexOf("urbano") >= 0) return "urbano";
    return areas[0] || "urbano";
  }

  var captureArea = "urbano";

  function areaTitle(area) {
    if (area === "catastro") return "Inspección Catastro";
    if (area === "ecologica") return "Inspección Patrulla Ecológica";
    return "Inspección Control Urbano";
  }

  function mustChooseArea() {
    return userInspectionAreas().length > 1;
  }

  function syncAreaUI(dlg) {
    var root = dlg || document;
    var title = root.querySelector("#inspect-dialog-title");
    if (title) title.textContent = areaTitle(captureArea);
    var typeField = root.querySelector(".inspect-type-field");
    if (typeField) typeField.hidden = captureArea !== "urbano";
    var badge = root.querySelector("#inspect-area-badge");
    if (badge) {
      var shortLabel = "Control Urbano";
      if (captureArea === "catastro") shortLabel = "Catastro";
      if (captureArea === "ecologica") shortLabel = "Patrulla Ecológica";
      badge.textContent = shortLabel;
      badge.classList.toggle("is-catastro", captureArea === "catastro");
      badge.classList.toggle("is-ecologica", captureArea === "ecologica");
      badge.classList.toggle("is-urbano", captureArea === "urbano");
    }
    var backBtn = root.querySelector("#inspect-area-back");
    if (backBtn) backBtn.hidden = !mustChooseArea();

    var eco = captureArea === "ecologica";
    var captureForm = root.querySelector("#inspect-step-capture");
    var hint = captureForm ? captureForm.querySelector(".inspect-photo-form__hint") : null;
    if (hint) {
      hint.textContent = eco
        ? "La clave catastral es opcional. Ubique el punto con GPS o punteando en el mapa."
        : "Capture la clave catastral y la fotografía del predio.";
    }
    var claveLabel = root.querySelector("#inspect-clave-label-text");
    if (claveLabel) claveLabel.textContent = eco ? "Clave catastral (opcional)" : "Clave catastral";
    var claveInput = root.querySelector("#inspect-clave");
    if (claveInput) {
      claveInput.required = !eco;
      claveInput.placeholder = eco ? "Opcional" : "ST312014";
    }
    root.querySelectorAll(".inspect-coord-toggle--clave").forEach(function (btn) {
      btn.hidden = eco;
    });
    root.querySelectorAll(".inspect-coord-toggle--map").forEach(function (btn) {
      btn.hidden = !eco;
    });
    if (eco && coordSource !== "gps" && coordSource !== "map") coordSource = "gps";
    if (!eco && coordSource === "map") coordSource = "clave";
    setCoordSource(coordSource, false);
  }

  function showInspectStep(step) {
    var choose = $("inspect-step-choose");
    var capture = $("inspect-step-capture");
    if (choose) choose.hidden = step !== "choose";
    if (capture) capture.hidden = step !== "capture";
  }

  function canViewInspectionMarkers() {
    if (typeof window.mxliCan !== "function") return false;
    return (
      window.mxliCan("map.inspect") ||
      window.mxliCan("map.inspect.catastro") ||
      window.mxliCan("map.inspect.ecologica") ||
      window.mxliCan("inspections.manage") ||
      window.mxliCan("inspections.catastro.manage") ||
      window.mxliCan("inspections.ecologica.manage") ||
      window.mxliCan("users.manage")
    );
  }

  function $(id) {
    return document.getElementById(id);
  }

  function showMsg(el, text, ok) {
    if (!el) return;
    el.hidden = !text;
    el.textContent = text || "";
    el.classList.toggle("is-ok", !!ok);
    el.classList.toggle("is-error", !ok && !!text);
  }

  function claveFromSearch() {
    var input = $("buscarPredioClave");
    if (!input) return "";
    return String(input.value || "").trim().replace(/\s+/g, "").toUpperCase();
  }

  function getMap() {
    if (typeof map2d !== "undefined" && map2d) return map2d;
    if (window.map2d) return window.map2d;
    return null;
  }

  function escapeHtml(value) {
    return String(value == null ? "" : value)
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;");
  }

  function formatDate(v) {
    if (!v) return "—";
    try {
      return new Date(v).toLocaleString("es-MX");
    } catch (e) {
      return String(v);
    }
  }

  var TYPE_LABELS = {
    lic_construccion: "Lic. Construcción",
    imagen_urbana: "Imagen Urbana",
    uso_suelo: "Uso de Suelo",
    casetas_de_fraccs: "Casetas de Fraccs.",
    otro: "Otro",
  };

  function typeKeys() {
    return Object.keys(TYPE_LABELS);
  }

  function areaChooseHtml() {
    var areas = userInspectionAreas();
    var cards = [];
    if (areas.indexOf("urbano") >= 0) {
      cards.push(
        '<button type="button" class="inspect-area-choice inspect-area-choice--urbano" data-inspect-area="urbano">' +
          '<span class="inspect-area-choice__title">Control Urbano</span>' +
          '<span class="inspect-area-choice__desc">Inspección fotográfica con tipos (Lic. Construcción, Imagen Urbana, etc.)</span>' +
          "</button>"
      );
    }
    if (areas.indexOf("catastro") >= 0) {
      cards.push(
        '<button type="button" class="inspect-area-choice inspect-area-choice--catastro" data-inspect-area="catastro">' +
          '<span class="inspect-area-choice__title">Catastro</span>' +
          '<span class="inspect-area-choice__desc">Reporte fotográfico de Catastro (foto, GPS/centroide y notas)</span>' +
          "</button>"
      );
    }
    if (areas.indexOf("ecologica") >= 0) {
      cards.push(
        '<button type="button" class="inspect-area-choice inspect-area-choice--ecologica" data-inspect-area="ecologica">' +
          '<span class="inspect-area-choice__title">Patrulla Ecológica</span>' +
          '<span class="inspect-area-choice__desc">Foto con GPS o un punto en el mapa. La clave catastral es opcional y solo la ve Patrulla Ecológica.</span>' +
          "</button>"
      );
    }
    return (
      '<div id="inspect-step-choose" class="inspect-step inspect-step-choose" hidden>' +
      "<h3>Tipo de reporte</h3>" +
      '<p class="inspect-photo-form__hint">Elija el área de inspección <strong>antes</strong> de capturar la foto, anotar y guardar.</p>' +
      '<div class="inspect-area-choices">' +
      cards.join("") +
      "</div>" +
      '<div class="inspect-photo-actions">' +
      '<button type="button" id="inspect-choose-cancel" class="inspect-photo-btn inspect-photo-btn--ghost">Cancelar</button>' +
      "</div></div>"
    );
  }

  function typesHtml(selectedCsv, nameAttr) {
    var selected = {};
    var name = nameAttr || "inspection_types";
    String(selectedCsv || "")
      .split(",")
      .forEach(function (k) {
        k = String(k || "").trim();
        if (k) selected[k] = true;
      });
    return (
      '<div class="inspect-type-field">' +
      '<span class="inspect-type-field__label">Tipo de inspección (puede marcar varios)</span>' +
      '<div class="inspect-type-grid" id="inspect-type-toggles">' +
      typeKeys()
        .map(function (key) {
          var checked = selected[key] ? " checked" : "";
          return (
            '<label class="inspect-type-toggle">' +
            '<input type="checkbox" name="' +
            name +
            '" value="' +
            key +
            '"' +
            checked +
            ">" +
            '<span class="inspect-type-dot" aria-hidden="true"></span>' +
            '<span class="inspect-type-text">' +
            TYPE_LABELS[key] +
            "</span></label>"
          );
        })
        .join("") +
      "</div></div>"
    );
  }

  function selectedTypesCsv(root) {
    var box = root || document;
    var keys = [];
    box.querySelectorAll('input[type="checkbox"][name="inspection_types"]:checked').forEach(function (inp) {
      var k = inp.value;
      if (k && keys.indexOf(k) < 0) keys.push(k);
    });
    return keys.join(",");
  }


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
    dlg.addEventListener("click", function (e) {
      if (e.target === dlg) closeLb();
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

  window.mxliOpenInspectionLightbox = openMapLightbox;


  var FALLBACK_COLORS = [
    "#9b2f55", "#1a5fd0", "#2f7d4b", "#e67e22", "#8e44ad", "#16a085",
    "#c0392b", "#2980b9", "#d35400", "#27ae60", "#6c3483", "#117a65"
  ];
  var localColorMap = {};

  function colorForInspector(item) {
    if (typeof window.mxliInspectColorForUser === "function") {
      return window.mxliInspectColorForUser(item || {});
    }
    var userId = item && item.created_by != null ? String(item.created_by) : "";
    var userName = item && item.created_by_name ? String(item.created_by_name).trim() : "";
    var key = userId || userName || "0";
    if (localColorMap[key]) return localColorMap[key];
    var hash = 0;
    for (var i = 0; i < key.length; i++) {
      hash = (hash * 31 + key.charCodeAt(i)) >>> 0;
    }
    var color = FALLBACK_COLORS[hash % FALLBACK_COLORS.length];
    localColorMap[key] = color;
    return color;
  }

  function isValidCoord(lat, lng) {
    return (
      typeof lat === "number" &&
      typeof lng === "number" &&
      isFinite(lat) &&
      isFinite(lng) &&
      lat >= -90 &&
      lat <= 90 &&
      lng >= -180 &&
      lng <= 180 &&
      !(lat === 0 && lng === 0)
    );
  }


  var PREDIO_API = "api/wfs-nearest-predio.php";
  var coordSource = "clave"; // clave | gps | map (map solo ecológica)
  var shownGps = { lat: null, lng: null };
  var mapPick = {
    active: false,
    suspendReset: false,
    placing: false,
    lat: null,
    lng: null,
    marker: null,
    down: null,
    onPointerDown: null,
    onPointerUp: null,
    onPointerCancel: null,
    onClick: null,
    onKey: null,
    panSuspended: null,
  };

  function getCoordSource() {
    var on = document.querySelector(".inspect-coord-toggle.is-on");
    if (on && on.getAttribute("data-coord-source")) {
      return on.getAttribute("data-coord-source");
    }
    return coordSource || "clave";
  }

  function setCoordSource(src, announce) {
    if (captureArea === "ecologica") {
      coordSource = src === "map" ? "map" : "gps";
    } else {
      coordSource = src === "gps" ? "gps" : "clave";
    }
    document.querySelectorAll(".inspect-coord-toggle").forEach(function (btn) {
      var on = btn.getAttribute("data-coord-source") === coordSource;
      btn.classList.toggle("is-on", on);
      btn.setAttribute("aria-pressed", on ? "true" : "false");
    });
    var hint = $("inspect-coord-hint");
    if (hint) {
      if (captureArea === "ecologica" && coordSource === "map") {
        hint.textContent = isValidCoord(mapPick.lat, mapPick.lng)
          ? "Punto tomado del plano. Puede puntear de nuevo para moverlo."
          : "Toque el plano para colocar el punto. Puede cancelar el punteo.";
      } else if (coordSource === "gps") {
        hint.textContent =
          captureArea === "ecologica"
            ? "Se usará la ubicación GPS del dispositivo."
            : "Se usará la ubicación GPS del dispositivo/mapa.";
      } else {
        hint.textContent = "Se usará el centro geométrico del predio (clave).";
      }
    }
    updateCoordReadout();
    if (announce) {
      showMsg($("inspect-msg"), hint ? hint.textContent : "", true);
    }
  }

  function updateCoordReadout() {
    var el = $("inspect-coord-readout");
    if (!el) return;
    if (captureArea !== "ecologica") {
      el.hidden = true;
      el.textContent = "";
      el.classList.remove("is-empty");
      return;
    }
    el.hidden = false;
    var source = coordSource === "map" ? "map" : "gps";
    var lat = source === "map" ? mapPick.lat : shownGps.lat;
    var lng = source === "map" ? mapPick.lng : shownGps.lng;
    if (!isValidCoord(lat, lng)) {
      el.classList.add("is-empty");
      el.textContent =
        source === "map" ? "Aún no hay punto en el plano." : "Aún no hay lectura GPS.";
      return;
    }
    el.classList.remove("is-empty");
    el.textContent =
      "Coordenadas: " +
      Number(lat).toFixed(6) +
      ", " +
      Number(lng).toFixed(6) +
      (source === "map" ? " (plano)" : " (GPS)");
  }

  function autoPickCoordSourceFromClave() {
    if (captureArea === "ecologica") return;
    var clave = String(($("inspect-clave") && $("inspect-clave").value) || "")
      .trim()
      .replace(/\s+/g, "");
    setCoordSource(clave.length >= 4 ? "clave" : "gps", false);
  }

  function coordSourceHtml() {
    return (
      '<div class="inspect-coord-field">' +
      '<span class="inspect-coord-field__label">Coordenadas</span>' +
      '<div class="inspect-coord-toggles" role="group" aria-label="Origen de coordenadas">' +
      '<button type="button" class="inspect-coord-toggle inspect-coord-toggle--clave is-on" data-coord-source="clave" aria-pressed="true">Centro del predio</button>' +
      '<button type="button" class="inspect-coord-toggle inspect-coord-toggle--gps" data-coord-source="gps" aria-pressed="false">GPS actual</button>' +
      '<button type="button" class="inspect-coord-toggle inspect-coord-toggle--map" data-coord-source="map" aria-pressed="false" hidden>Puntear en el mapa</button>' +
      "</div>" +
      '<p id="inspect-coord-hint" class="inspect-coord-hint">Se usará el centro geométrico del predio (clave).</p>' +
      '<p id="inspect-coord-readout" class="inspect-coord-readout" hidden></p>' +
      "</div>"
    );
  }

  function bindCoordToggles(root) {
    var box = root || document;
    box.querySelectorAll(".inspect-coord-toggle").forEach(function (btn) {
      btn.addEventListener("click", function (ev) {
        ev.preventDefault();
        var src = btn.getAttribute("data-coord-source") || "clave";
        if (captureArea === "ecologica" && src === "map") {
          setCoordSource("map", false);
          beginMapPick();
          return;
        }
        if (captureArea === "ecologica" && src === "gps") {
          finishMapPick(false);
          var gpsMap = getMap();
          if (mapPick.marker && gpsMap && gpsMap.hasLayer(mapPick.marker)) {
            gpsMap.removeLayer(mapPick.marker);
          }
          setCoordSource("gps", false);
          refreshEcoGps();
          return;
        }
        setCoordSource(src, true);
      });
    });
  }

  function refreshEcoGps() {
    if (captureArea !== "ecologica") return;
    var hint = $("inspect-coord-hint");
    if (hint) hint.textContent = "Obteniendo ubicación GPS…";
    shownGps.lat = null;
    shownGps.lng = null;
    updateCoordReadout();
    readDeviceGps().then(function (gps) {
      if (captureArea !== "ecologica" || getCoordSource() !== "gps") return;
      if (!gps) {
        shownGps.lat = null;
        shownGps.lng = null;
        if (hint) hint.textContent = "No se obtuvo GPS. Active la ubicación o puntee en el mapa.";
        showMsg($("inspect-msg"), "No se pudo obtener GPS. Puede puntear en el mapa.", false);
        updateCoordReadout();
        return;
      }
      shownGps.lat = gps.lat;
      shownGps.lng = gps.lng;
      if (hint) hint.textContent = "Se usará la ubicación GPS del dispositivo.";
      showMsg($("inspect-msg"), "", true);
      updateCoordReadout();
    });
  }

  function showDraftMarker(lat, lng) {
    var map = getMap();
    if (!map || typeof L === "undefined") return;
    if (!mapPick.marker) {
      mapPick.marker = L.marker([lat, lng], {
        icon: L.divIcon({
          className: "mxli-inspect-pick-marker",
          html: '<span class="mxli-inspect-pick-marker__dot"></span>',
          iconSize: [18, 18],
          iconAnchor: [9, 9],
        }),
        interactive: false,
        keyboard: false,
        zIndexOffset: 1000,
      });
    }
    mapPick.marker.setLatLng([lat, lng]);
    if (!map.hasLayer(mapPick.marker)) mapPick.marker.addTo(map);
  }

  function clearDraftMarker() {
    var map = getMap();
    if (mapPick.marker && map && map.hasLayer(mapPick.marker)) {
      map.removeLayer(mapPick.marker);
    }
    mapPick.marker = null;
    mapPick.lat = null;
    mapPick.lng = null;
  }

  function ensurePickBanner() {
    var banner = document.getElementById("inspect-map-pick-banner");
    if (banner) return banner;
    banner = document.createElement("div");
    banner.id = "inspect-map-pick-banner";
    banner.className = "inspect-map-pick-banner";
    banner.hidden = true;
    banner.innerHTML =
      "<p>Toque el plano para ubicar la inspección de Patrulla Ecológica.</p>" +
      '<button type="button" id="inspect-map-pick-cancel">Cancelar punteo</button>';
    document.body.appendChild(banner);
    var cancel = document.getElementById("inspect-map-pick-cancel");
    if (cancel) {
      cancel.addEventListener("click", function (ev) {
        ev.preventDefault();
        ev.stopPropagation();
        cancelMapPick();
      });
    }
    return banner;
  }

  function mapPickHitsUi(target) {
    if (!target || !target.closest) return false;
    return !!target.closest(
      ".leaflet-control, .leaflet-popup, .buscador-predio-flotante, .map-top-toolbar, .inspect-map-pick-banner, button, a, input, select, textarea, label"
    );
  }

  function unbindMapPick() {
    if (mapPick.onPointerDown) {
      document.removeEventListener("pointerdown", mapPick.onPointerDown, true);
      mapPick.onPointerDown = null;
    }
    if (mapPick.onPointerUp) {
      document.removeEventListener("pointerup", mapPick.onPointerUp, true);
      mapPick.onPointerUp = null;
    }
    if (mapPick.onPointerCancel) {
      document.removeEventListener("pointercancel", mapPick.onPointerCancel, true);
      mapPick.onPointerCancel = null;
    }
    if (mapPick.onClick) {
      document.removeEventListener("click", mapPick.onClick, true);
      mapPick.onClick = null;
    }
    mapPick.down = null;
  }

  function panButton() {
    return document.getElementById("panMapBtn");
  }

  function draggingEnabled(map) {
    try {
      return !!(map && map.dragging && typeof map.dragging.enabled === "function" && map.dragging.enabled());
    } catch (err) {
      return false;
    }
  }

  function suspendMapPan(map) {
    if (!map || mapPick.panSuspended) return;
    var panBtn = panButton();
    mapPick.panSuspended = {
      dragging: draggingEnabled(map),
      panActive: !!(panBtn && panBtn.classList.contains("btnIsActive")),
    };
    if (map.dragging) map.dragging.disable();
    var container = map.getContainer();
    if (container) {
      container.classList.remove("leaflet-grab", "leaflet-dragging");
      if (typeof L !== "undefined" && L.DomUtil) {
        L.DomUtil.removeClass(container, "leaflet-grab");
        L.DomUtil.removeClass(container, "leaflet-dragging");
      }
    }
    if (panBtn) panBtn.classList.remove("btnIsActive");
  }

  function restoreMapPan(map) {
    var saved = mapPick.panSuspended;
    mapPick.panSuspended = null;
    var container = map && map.getContainer ? map.getContainer() : null;
    if (container) {
      container.classList.remove("mxli-inspect-pick-cursor");
      container.style.cursor = "";
    }
    if (!saved) return;
    var panBtn = panButton();
    if (panBtn) panBtn.classList.toggle("btnIsActive", !!saved.panActive);
    if (map && map.dragging) {
      if (saved.dragging) map.dragging.enable();
      else map.dragging.disable();
    }
  }

  function swallowFollowingClick() {
    var pending = true;
    function onClick(ev) {
      if (!pending) return;
      pending = false;
      document.removeEventListener("click", onClick, true);
      ev.preventDefault();
      ev.stopPropagation();
    }
    document.addEventListener("click", onClick, true);
    setTimeout(function () {
      pending = false;
      document.removeEventListener("click", onClick, true);
    }, 700);
  }

  function finishMapPick(reopen) {
    mapPick.active = false;
    window.mxliInspectMapPickActive = false;
    mapPick.down = null;
    unbindMapPick();
    if (mapPick.onKey) {
      document.removeEventListener("keydown", mapPick.onKey, true);
      mapPick.onKey = null;
    }
    var map = getMap();
    restoreMapPan(map);
    var banner = document.getElementById("inspect-map-pick-banner");
    if (banner) banner.hidden = true;
    if (reopen) {
      if (isValidCoord(mapPick.lat, mapPick.lng)) showDraftMarker(mapPick.lat, mapPick.lng);
      setCoordSource("map", false);
      reopenCaptureDialog();
    }
  }

  function cancelMapPick() {
    finishMapPick(true);
    var hint = $("inspect-coord-hint");
    if (hint) {
      hint.textContent = isValidCoord(mapPick.lat, mapPick.lng)
        ? "Punteo cancelado. Se conserva el punto anterior."
        : "Punteo cancelado. Pulse «Puntear en el mapa» para elegir un punto.";
    }
  }

  function pickEventOnMap(ev, map) {
    if (!ev || !map || !map.getContainer) return false;
    var container = map.getContainer();
    if (!container || !ev.target || !container.contains(ev.target)) return false;
    return !mapPickHitsUi(ev.target);
  }

  function placeFromEvent(ev, swallowClick) {
    if (!mapPick.active || mapPick.placing) return;
    var map = getMap();
    if (!map) return;
    var ll = null;
    try {
      ll = map.mouseEventToLatLng(ev);
    } catch (err) {
      ll = null;
    }
    if (!ll || !isValidCoord(ll.lat, ll.lng)) return;
    mapPick.placing = true;
    mapPick.lat = ll.lat;
    mapPick.lng = ll.lng;
    showDraftMarker(ll.lat, ll.lng);
    if (swallowClick) swallowFollowingClick();
    finishMapPick(true);
    mapPick.placing = false;
  }

  function beginMapPick() {
    var map = getMap();
    if (!map || !map.getContainer()) {
      showMsg($("inspect-msg"), "El mapa no está listo para puntear.", false);
      return;
    }
    var dlg = $("inspect-photo-dialog");
    var dialogOpen = !!(dlg && (dlg.open || dlg.hasAttribute("open")));
    mapPick.active = true;
    mapPick.placing = false;
    window.mxliInspectMapPickActive = true;
    mapPick.suspendReset = dialogOpen;
    if (dialogOpen) {
      try {
        if (typeof dlg.close === "function") dlg.close();
        else dlg.removeAttribute("open");
      } catch (e) {
        dlg.removeAttribute("open");
        mapPick.suspendReset = false;
      }
    }

    suspendMapPan(map);
    var container = map.getContainer();
    container.classList.add("mxli-inspect-pick-cursor");
    container.style.cursor = "crosshair";
    var banner = ensurePickBanner();
    banner.hidden = false;

    unbindMapPick();
    mapPick.onPointerDown = function (ev) {
      if (!mapPick.active) return;
      if (ev.button !== undefined && ev.button !== 0) return;
      if (!pickEventOnMap(ev, map)) return;
      mapPick.down = { x: ev.clientX, y: ev.clientY };
      ev.preventDefault();
      ev.stopPropagation();
    };
    mapPick.onPointerUp = function (ev) {
      if (!mapPick.active) return;
      if (!mapPick.down) return;
      if (ev.button !== undefined && ev.button !== 0) {
        mapPick.down = null;
        return;
      }
      var down = mapPick.down;
      mapPick.down = null;
      if (!pickEventOnMap(ev, map)) return;
      var dx = ev.clientX - down.x;
      var dy = ev.clientY - down.y;
      if (dx * dx + dy * dy > 576) return;
      ev.preventDefault();
      ev.stopPropagation();
      placeFromEvent(ev, true);
    };
    mapPick.onPointerCancel = function () {
      mapPick.down = null;
    };
    mapPick.onClick = function (ev) {
      if (!mapPick.active) return;
      if (!pickEventOnMap(ev, map)) return;
      ev.preventDefault();
      ev.stopPropagation();
      placeFromEvent(ev, false);
    };
    document.addEventListener("pointerdown", mapPick.onPointerDown, true);
    document.addEventListener("pointerup", mapPick.onPointerUp, true);
    document.addEventListener("pointercancel", mapPick.onPointerCancel, true);
    document.addEventListener("click", mapPick.onClick, true);

    if (mapPick.onKey) document.removeEventListener("keydown", mapPick.onKey, true);
    mapPick.onKey = function (ev) {
      if (!mapPick.active || ev.key !== "Escape") return;
      ev.preventDefault();
      ev.stopPropagation();
      cancelMapPick();
    };
    document.addEventListener("keydown", mapPick.onKey, true);
  }

  function reopenCaptureDialog() {
    var dlg = $("inspect-photo-dialog");
    if (!dlg) return;
    showInspectStep("capture");
    syncAreaUI(dlg);
    if (typeof dlg.showModal === "function") dlg.showModal();
    else dlg.setAttribute("open", "");
    updateCoordReadout();
  }

  function fetchClaveCentroid(clave) {
    return fetch(PREDIO_API + "?clave=" + encodeURIComponent(clave), {
      credentials: "same-origin",
    })
      .then(function (res) {
        return res.json().then(function (data) {
          return { res: res, data: data };
        });
      })
      .then(function (payload) {
        if (!payload.res.ok || !payload.data || !payload.data.ok) {
          throw new Error(
            (payload.data && payload.data.error) || "No se encontró centro del predio"
          );
        }
        var lat = Number(payload.data.lat);
        var lng = Number(payload.data.lng);
        if (!isValidCoord(lat, lng)) {
          throw new Error("Centro del predio inválido");
        }
        return { lat: lat, lng: lng, source: "clave" };
      });
  }

  function readDeviceGps() {
    return new Promise(function (resolve) {
      // Prefer recent map locate result (2 minutes)
      if (
        window.mxliLastGps &&
        window.mxliLastGpsAt &&
        Date.now() - window.mxliLastGpsAt < 120000 &&
        isValidCoord(window.mxliLastGps.lat, window.mxliLastGps.lng)
      ) {
        resolve({
          lat: window.mxliLastGps.lat,
          lng: window.mxliLastGps.lng,
          source: "gps",
        });
        return;
      }
      if (!navigator.geolocation) {
        resolve(null);
        return;
      }
      navigator.geolocation.getCurrentPosition(
        function (pos) {
          var lat = pos.coords.latitude;
          var lng = pos.coords.longitude;
          if (!isValidCoord(lat, lng)) {
            resolve(null);
            return;
          }
          window.mxliLastGps = { lat: lat, lng: lng };
          window.mxliLastGpsAt = Date.now();
          resolve({ lat: lat, lng: lng, source: "gps" });
        },
        function () {
          resolve(null);
        },
        { enableHighAccuracy: true, timeout: 8000, maximumAge: 60000 }
      );
    });
  }

  function resolveSaveCoords(clave, source) {
    source = source === "gps" ? "gps" : "clave";
    if (source === "gps") {
      return readDeviceGps().then(function (gps) {
        if (!gps) {
          throw new Error(
            "No se pudo obtener GPS. Active ubicación o use Centro del predio."
          );
        }
        return gps;
      });
    }
    return fetchClaveCentroid(clave).catch(function (err) {
      // Fallback to GPS if centroid fails
      return readDeviceGps().then(function (gps) {
        if (gps) {
          gps.source = "gps";
          gps.fallbackFrom = "clave";
          return gps;
        }
        throw err;
      });
    });
  }

  function resolveEcoCoords() {
    var source = getCoordSource() === "map" ? "map" : "gps";
    if (source === "map") {
      if (!isValidCoord(mapPick.lat, mapPick.lng)) {
        return Promise.reject(new Error("Pantee un punto en el mapa antes de guardar."));
      }
      return Promise.resolve({ lat: mapPick.lat, lng: mapPick.lng, source: "map" });
    }
    if (!isValidCoord(shownGps.lat, shownGps.lng)) {
      return Promise.reject(
        new Error("No hay ubicación GPS. Obtenga el GPS o puntee en el mapa.")
      );
    }
    return Promise.resolve({ lat: shownGps.lat, lng: shownGps.lng, source: "gps" });
  }


  function ensureInspectionLayer() {
    var map = getMap();
    if (!map || typeof L === "undefined") return null;
    if (inspectionLayer) return inspectionLayer;

    inspectionLayer = L.layerGroup();
    inspectionLayer.addTo(map);

    try {
      if (typeof layerControl !== "undefined" && layerControl && layerControl.addOverlay) {
        layerControl.addOverlay(inspectionLayer, "Inspecciones GPS");
      }
    } catch (e) {
      // sin control de capas: la capa queda en el mapa
    }

    return inspectionLayer;
  }

  function markerPopupHtml(item) {
    var src = item.photo_url || "";
    var photo = src
      ? '<button type="button" class="mxli-inspect-popup__thumb-btn" data-photo-src="' +
        escapeHtml(src) +
        '" data-photo-clave="' +
        escapeHtml(item.clavecatas || "") +
        '"><img class="mxli-inspect-popup__photo" src="' +
        escapeHtml(src) +
        '" alt="Foto"></button>'
      : "";
    var types = item.types_label || "";
    var area = item.inspection_area === "catastro" || item.inspection_area === "ecologica"
      ? item.inspection_area
      : "urbano";
    var claveText = String(item.clavecatas || "").trim();
    claveText = claveText ? claveText.toUpperCase() : "—";
    var typesRow =
      area === "urbano"
        ? '<tr><td class="mxli-padron-label">Tipos</td><td class="mxli-padron-value"><b>' +
          escapeHtml(String(types || "—").toUpperCase()) +
          "</b></td></tr>"
        : "";
    var gpsRow = "";
    if (area === "ecologica") {
      var gpsText = "—";
      if (isValidCoord(Number(item.lat), Number(item.lng))) {
        gpsText = Number(item.lat).toFixed(5) + ", " + Number(item.lng).toFixed(5);
      }
      gpsRow =
        '<tr><td class="mxli-padron-label">GPS</td><td class="mxli-padron-value"><b>' +
        escapeHtml(gpsText) +
        "</b></td></tr>";
    }
    return (
      '<div class="mxli-feature-popup mxli-inspect-popup">' +
      '<div class="mxli-feature-popup__head">Inspección</div>' +
      '<div class="mxli-feature-popup__body mxli-inspect-popup__body">' +
      photo +
      '<table class="mxli-padron-table mxli-inspect-ficha" role="presentation"><tbody>' +
      '<tr><td class="mxli-padron-label">Clave</td><td class="mxli-padron-value"><b>' +
      escapeHtml(claveText) +
      "</b></td></tr>" +
      gpsRow +
      '<tr><td class="mxli-padron-label">Fecha</td><td class="mxli-padron-value"><b>' +
      escapeHtml(String(formatDate(item.captured_at)).toUpperCase()) +
      "</b></td></tr>" +
      '<tr><td class="mxli-padron-label">Inspector</td><td class="mxli-padron-value"><b>' +
      escapeHtml(String(item.created_by_name || "—").toUpperCase()) +
      "</b></td></tr>" +
      typesRow +
      '<tr><td class="mxli-padron-label">Notas</td><td class="mxli-padron-value"><b>' +
      escapeHtml(String(item.notes || "—").toUpperCase()) +
      "</b></td></tr>" +
      "</tbody></table></div></div>"
    );
  }


  function upsertMarker(item) {
    var layer = ensureInspectionLayer();
    if (!layer || !item) return;

    var id = String(item.id);
    var lat = item.lat != null ? Number(item.lat) : NaN;
    var lng = item.lng != null ? Number(item.lng) : NaN;
    if (!isValidCoord(lat, lng)) {
      if (markersById[id]) {
        layer.removeLayer(markersById[id]);
        delete markersById[id];
      }
      return;
    }

    if (markersById[id]) {
      markersById[id].setLatLng([lat, lng]);
      markersById[id].setPopupContent(markerPopupHtml(item));
      var fillUp = colorForInspector(item);
      if (markersById[id].setStyle) {
        markersById[id].setStyle({
          color: "#ffffff",
          fillColor: fillUp,
          fillOpacity: 0.85,
          opacity: 0.95,
        });
      }
      return;
    }

    var fill = colorForInspector(item);
    var marker = L.circleMarker([lat, lng], {
      radius: 7,
      weight: 2,
      color: "#ffffff",
      fillColor: fill,
      fillOpacity: 0.85,
      opacity: 0.95,
      className: "mxli-inspect-marker",
    });
    marker.bindPopup(markerPopupHtml(item), { minWidth: 280, maxWidth: 360, className: "mxli-inspect-leaflet-popup" });
    marker.on("popupopen", function () {
      var root = marker.getPopup() && marker.getPopup().getElement();
      if (!root) return;
      root.querySelectorAll("[data-photo-src]").forEach(function (el) {
        el.addEventListener("click", function (ev) {
          ev.preventDefault();
          ev.stopPropagation();
          openMapLightbox(el.getAttribute("data-photo-src"), el.getAttribute("data-photo-clave") || "");
        });
      });
    });
    marker.addTo(layer);
    markersById[id] = marker;
  }

  function loadInspectionMarkers() {
    if (!canViewInspectionMarkers()) return;
    if (!ensureInspectionLayer()) {
      setTimeout(loadInspectionMarkers, 400);
      return;
    }

    var areas = userInspectionAreas();
    var areaQ = areas.length === 1 ? "&area=" + encodeURIComponent(areas[0]) : "";
    // Listing API requires area; if both, load sequentially via capa refresh instead
    if (areas.length !== 1) {
      if (typeof window.mxliRefreshInspeccionesCapa === "function") {
        window.mxliRefreshInspeccionesCapa();
      }
      return;
    }
    fetch(API + "?with_coords=1&limit=2000" + areaQ)
      .then(function (res) {
        return res.json().then(function (data) {
          if (!res.ok || !data || data.ok === false) {
            throw new Error((data && data.error) || "No se pudieron cargar inspecciones");
          }
          return data.items || [];
        });
      })
      .then(function (items) {
        var seen = {};
        items.forEach(function (item) {
          seen[String(item.id)] = true;
          upsertMarker(item);
        });
        Object.keys(markersById).forEach(function (id) {
          if (!seen[id] && inspectionLayer) {
            inspectionLayer.removeLayer(markersById[id]);
            delete markersById[id];
          }
        });
      })
      .catch(function () {
        // Silencioso en mapa; el admin lista errores aparte.
      });
  }

  function scheduleLoadMarkers() {
    if (loadTimer) clearTimeout(loadTimer);
    loadTimer = setTimeout(loadInspectionMarkers, 200);
  }

  function ensureDialog() {
    if ($("inspect-photo-dialog")) return;

    var dlg = document.createElement("dialog");
    dlg.id = "inspect-photo-dialog";
    dlg.className = "inspect-photo-dialog";
    dlg.innerHTML =
      areaChooseHtml() +
      '<form method="post" id="inspect-step-capture" class="inspect-photo-form inspect-step" hidden novalidate>' +
      '<div class="inspect-area-banner">' +
      '<span class="inspect-area-badge is-urbano" id="inspect-area-badge">Control Urbano</span>' +
      '<button type="button" id="inspect-area-back" class="inspect-area-back" hidden>Cambiar tipo</button>' +
      "</div>" +
      '<h3 id="inspect-dialog-title">Inspección Control Urbano</h3>' +
      '<p class="inspect-photo-form__hint">Capture la clave catastral y la fotografía del predio.</p>' +
      '<label><span id="inspect-clave-label-text">Clave catastral</span><input type="text" id="inspect-clave" maxlength="32" required placeholder="ST312014"></label>' +
      coordSourceHtml() +
      '<label>Notas (opcional)<textarea id="inspect-notes" rows="2" placeholder="Observaciones"></textarea></label>' +
      typesHtml("") +
      '<div class="inspect-photo-preview-wrap">' +
      '<img id="inspect-preview" class="inspect-photo-preview" alt="Vista previa" hidden>' +
      '<button type="button" id="inspect-take-btn" class="inspect-photo-take">Tomar / seleccionar foto</button>' +
      "</div>" +
      '<p id="inspect-msg" class="inspect-photo-msg" hidden></p>' +
      '<div class="inspect-photo-actions">' +
      '<button type="button" id="inspect-cancel-btn" class="inspect-photo-btn inspect-photo-btn--ghost">Cancelar</button>' +
      '<button type="submit" value="save" class="inspect-photo-btn inspect-photo-btn--primary">Guardar registro</button>' +
      "</div>" +
      "</form>";
    document.body.appendChild(dlg);
    bindCoordToggles(dlg);
    captureArea = defaultInspectionArea();
    syncAreaUI(dlg);

    function closeInspectDialog() {
      var d = $("inspect-photo-dialog") || dlg;
      if (!d) return;
      try {
        if (typeof d.close === "function") d.close();
        else d.removeAttribute("open");
      } catch (e) {
        d.removeAttribute("open");
      }
    }

    function resetCaptureFields() {
      var fileInput = $("inspect-photo-input");
      var preview = $("inspect-preview");
      if (fileInput) fileInput.value = "";
      if (preview) {
        preview.hidden = true;
        preview.removeAttribute("src");
      }
      selectedFile = null;
      var notes = $("inspect-notes");
      if (notes) notes.value = "";
      dlg.querySelectorAll('input[type="checkbox"][name="inspection_types"]').forEach(function (inp) {
        inp.checked = false;
      });
      finishMapPick(false);
      clearDraftMarker();
      shownGps.lat = null;
      shownGps.lng = null;
      showMsg($("inspect-msg"), "", true);
    }

    function goToCapture(area) {
      captureArea = area === "catastro" || area === "ecologica" ? area : "urbano";
      if (captureArea === "ecologica") {
        coordSource = "gps";
        shownGps.lat = null;
        shownGps.lng = null;
        clearDraftMarker();
      } else if (coordSource === "map") {
        coordSource = "clave";
      }
      syncAreaUI(dlg);
      showInspectStep("capture");
      var clave = $("inspect-clave");
      if (clave && !clave.value) clave.value = claveFromSearch();
      if (captureArea === "ecologica") refreshEcoGps();
      else autoPickCoordSourceFromClave();
      if (clave) {
        setTimeout(function () {
          clave.focus();
        }, 50);
      }
    }

    dlg.querySelectorAll("[data-inspect-area]").forEach(function (btn) {
      btn.addEventListener("click", function () {
        goToCapture(btn.getAttribute("data-inspect-area"));
      });
    });

    var areaBack = $("inspect-area-back");
    if (areaBack) {
      areaBack.addEventListener("click", function () {
        resetCaptureFields();
        showInspectStep("choose");
      });
    }

    var chooseCancel = $("inspect-choose-cancel");
    if (chooseCancel) {
      chooseCancel.addEventListener("click", function (event) {
        event.preventDefault();
        closeInspectDialog();
      });
    }

    var cancelBtn = $("inspect-cancel-btn");
    if (cancelBtn) {
      cancelBtn.addEventListener("click", function (event) {
        event.preventDefault();
        event.stopPropagation();
        closeInspectDialog();
      });
    }

    dlg.addEventListener("cancel", function (event) {
      event.preventDefault();
      closeInspectDialog();
    });

    var takeBtn = $("inspect-take-btn");
    var fileInput = ensureInspectPhotoInput();
    var preview = $("inspect-preview");
    var selectedFile = null;
    var previewUrl = "";

    function clearPreview() {
      if (previewUrl) {
        URL.revokeObjectURL(previewUrl);
        previewUrl = "";
      }
      if (preview) {
        preview.hidden = true;
        preview.removeAttribute("src");
      }
    }

    takeBtn.addEventListener("click", function (ev) {
      ev.preventDefault();
      ev.stopPropagation();
      fileInput = ensureInspectPhotoInput();
      try {
        fileInput.value = "";
        fileInput.click();
      } catch (err) {
        showMsg($("inspect-msg"), "No se pudo abrir la cámara. Intente de nuevo.", false);
      }
    });

    fileInput.addEventListener("change", function () {
      var picked = fileInput.files && fileInput.files[0] ? fileInput.files[0] : null;
      if (!picked) {
        selectedFile = null;
        clearPreview();
        return;
      }
      showMsg($("inspect-msg"), "Procesando foto…", true);
      prepareInspectionPhoto(picked)
        .then(function (ready) {
          selectedFile = ready || picked;
          clearPreview();
          previewUrl = URL.createObjectURL(selectedFile);
          preview.src = previewUrl;
          preview.hidden = false;
          showMsg($("inspect-msg"), "", true);
          releaseInspectDialog();
        })
        .catch(function () {
          selectedFile = picked;
          clearPreview();
          previewUrl = URL.createObjectURL(picked);
          preview.src = previewUrl;
          preview.hidden = false;
          showMsg($("inspect-msg"), "", true);
          releaseInspectDialog();
        });
    });

    document.addEventListener("visibilitychange", function () {
      if (document.visibilityState === "visible") releaseInspectDialog();
    });
    window.addEventListener("pageshow", releaseInspectDialog);
    window.addEventListener("focus", releaseInspectDialog);

    dlg.addEventListener("close", function () {
      if (mapPick.suspendReset || mapPick.active) {
        mapPick.suspendReset = false;
        return;
      }
      selectedFile = null;
      if (fileInput) fileInput.value = "";
      clearPreview();
      finishMapPick(false);
      clearDraftMarker();
      shownGps.lat = null;
      shownGps.lng = null;
    });

    var claveInput = $("inspect-clave");
    if (claveInput) {
      claveInput.addEventListener("input", autoPickCoordSourceFromClave);
      claveInput.addEventListener("change", autoPickCoordSourceFromClave);
    }

    $("inspect-step-capture").addEventListener("submit", function (event) {
      event.preventDefault();
      if (!selectedFile) {
        showMsg($("inspect-msg"), "Seleccione o tome una fotografía.", false);
        return;
      }

      if (!captureArea || (captureArea !== "urbano" && captureArea !== "catastro" && captureArea !== "ecologica")) {
        showMsg($("inspect-msg"), "Elija primero el tipo de reporte (área de inspección).", false);
        if (mustChooseArea()) showInspectStep("choose");
        return;
      }

      var clave = String($("inspect-clave").value || "").trim().replace(/\s+/g, "").toUpperCase();
      if (captureArea === "ecologica") {
        if (clave !== "" && !/^[A-Z0-9]{4,32}$/.test(clave)) {
          showMsg($("inspect-msg"), "Si anota clave catastral, use de 4 a 32 letras o números.", false);
          return;
        }
      } else if (!/^[A-Z0-9]{4,32}$/.test(clave)) {
        showMsg($("inspect-msg"), "Capture una clave catastral válida.", false);
        return;
      }

      var typesCsv = selectedTypesCsv(dlg);
      if (captureArea === "urbano" && !typesCsv) {
        showMsg($("inspect-msg"), "Seleccione al menos un tipo de inspección.", false);
        return;
      }
      if (captureArea !== "urbano") {
        typesCsv = "";
      }

      var saveBtn = dlg.querySelector(".inspect-photo-btn--primary");
      var source = getCoordSource();
      var coordsPromise;
      if (captureArea === "ecologica") {
        source = source === "map" ? "map" : "gps";
        coordsPromise = resolveEcoCoords();
      } else {
        if (clave.length >= 4 && source !== "gps") {
          source = "clave";
          setCoordSource("clave", false);
        }
        if (clave.length < 4) {
          source = "gps";
          setCoordSource("gps", false);
        }
        coordsPromise = resolveSaveCoords(clave, source);
      }

      coordsPromise
        .then(function (coords) {
          showMsg($("inspect-msg"), "Guardando…", true);
          if (saveBtn) saveBtn.disabled = true;
          var fd = new FormData();
          fd.append("clavecatas", clave);
          fd.append(
            "notes",
            String($("inspect-notes").value || "")
              .trim()
              .toUpperCase()
          );
          fd.append("inspection_types", typesCsv);
          fd.append("inspection_area", captureArea);
          fd.append("photo", selectedFile);
          fd.append("coord_source", coords.source || source);
          if (coords.lat != null && coords.lng != null) {
            fd.append("lat", String(coords.lat));
            fd.append("lng", String(coords.lng));
          }

          var ctrl = new AbortController();
          var timer = setTimeout(function () {
            ctrl.abort();
          }, 45000);
          return fetch(API, { method: "POST", body: fd, signal: ctrl.signal }).then(function (res) {
            clearTimeout(timer);
            return res.json().then(function (data) {
              if (!res.ok || !data.ok) {
                throw new Error((data && data.error) || "Error al guardar");
              }
              return data;
            });
          });
        })
        .then(function (data) {
          dlg.close();
          if (data && data.item) {
            upsertMarker(data.item);
          } else {
            scheduleLoadMarkers();
          }
          if (typeof window.mxliRefreshInspeccionesCapa === "function") {
            window.mxliRefreshInspeccionesCapa(captureArea);
          }
          window.alert("Inspección registrada correctamente.");
        })
        .catch(function (err) {
          var msg = err && err.name === "AbortError"
            ? "La foto tardó demasiado. Intente otra vez."
            : (err && err.message) || "Error";
          showMsg($("inspect-msg"), msg, false);
        })
        .then(function () {
          if (saveBtn) saveBtn.disabled = false;
        });
    });
  }

  function openDialog() {
    if (!canInspect()) {
      window.alert("Su rol no tiene permiso de inspección fotográfica.");
      return;
    }
    ensureDialog();
    var dlg = $("inspect-photo-dialog");
    var areas = userInspectionAreas();
    if (!areas.length) {
      window.alert("Su rol no tiene un área de inspección asignada.");
      return;
    }

    $("inspect-clave").value = claveFromSearch();
    $("inspect-notes").value = "";
    dlg.querySelectorAll('input[type="checkbox"][name="inspection_types"]').forEach(function (inp) {
      inp.checked = false;
    });
    showMsg($("inspect-msg"), "", true);

    finishMapPick(false);
    clearDraftMarker();
    shownGps.lat = null;
    shownGps.lng = null;

    if (mustChooseArea()) {
      // Obliga a elegir área antes de foto / notas / guardar
      captureArea = "";
      showInspectStep("choose");
    } else {
      captureArea = areas[0];
      if (captureArea === "ecologica") coordSource = "gps";
      else if (coordSource === "map") coordSource = "clave";
      syncAreaUI(dlg);
      showInspectStep("capture");
      if (captureArea === "ecologica") refreshEcoGps();
      else autoPickCoordSourceFromClave();
    }

    if (typeof dlg.showModal === "function") dlg.showModal();
    else dlg.setAttribute("open", "");
  }

  function initCameraButton() {
    if (!canInspect()) return;

    var btn = $("inspectPhotoBtn");
    if (!btn) {
      setTimeout(initCameraButton, 400);
      return;
    }

    btn.addEventListener("click", openDialog);
    btn.setAttribute("title", "Inspección fotográfica");
    btn.setAttribute("role", "button");
    btn.setAttribute("tabindex", "0");
  }

  function init() {
    initCameraButton();
    if (canViewInspectionMarkers()) {
      // Prefer capa coloreada por inspector; fallback a marcadores propios
      function bootMarkers() {
        if (typeof window.mxliRefreshInspeccionesCapa === "function") {
          window.mxliRefreshInspeccionesCapa();
          return;
        }
        scheduleLoadMarkers();
      }
      bootMarkers();
      setTimeout(bootMarkers, 800);
      setTimeout(bootMarkers, 2000);
    }
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", init);
  } else {
    init();
  }

  window.MxliInspeccionFotos = {
    open: openDialog,
    reloadMarkers: loadInspectionMarkers,
  };
})();
