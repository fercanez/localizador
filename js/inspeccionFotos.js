(function () {
  "use strict";

  if (window.MxliInspeccionFotosInit) return;
  window.MxliInspeccionFotosInit = true;

  var API = "api/inspections.php";
  var inspectionLayer = null;
  var markersById = {};
  var loadTimer = null;

  function canInspect() {
    return typeof window.mxliCan === "function" && window.mxliCan("map.inspect");
  }

  function canViewInspectionMarkers() {
    if (typeof window.mxliCan !== "function") return false;
    return window.mxliCan("map.inspect") || window.mxliCan("inspections.manage");
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
    otro: "Otro",
  };

  function typeKeys() {
    return Object.keys(TYPE_LABELS);
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
  var coordSource = "clave"; // clave | gps

  function getCoordSource() {
    var on = document.querySelector(".inspect-coord-toggle.is-on");
    if (on && on.getAttribute("data-coord-source")) {
      return on.getAttribute("data-coord-source");
    }
    return coordSource || "clave";
  }

  function setCoordSource(src, announce) {
    coordSource = src === "gps" ? "gps" : "clave";
    document.querySelectorAll(".inspect-coord-toggle").forEach(function (btn) {
      var on = btn.getAttribute("data-coord-source") === coordSource;
      btn.classList.toggle("is-on", on);
      btn.setAttribute("aria-pressed", on ? "true" : "false");
    });
    var hint = $("inspect-coord-hint");
    if (hint) {
      if (coordSource === "gps") {
        hint.textContent = "Se usará la ubicación GPS del dispositivo/mapa.";
      } else {
        hint.textContent = "Se usará el centro geométrico del predio (clave).";
      }
    }
    if (announce) {
      showMsg($("inspect-msg"), hint ? hint.textContent : "", true);
    }
  }

  function autoPickCoordSourceFromClave() {
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
      '<button type="button" class="inspect-coord-toggle is-on" data-coord-source="clave" aria-pressed="true">Centro del predio</button>' +
      '<button type="button" class="inspect-coord-toggle" data-coord-source="gps" aria-pressed="false">GPS actual</button>' +
      "</div>" +
      '<p id="inspect-coord-hint" class="inspect-coord-hint">Se usará el centro geométrico del predio (clave).</p>' +
      "</div>"
    );
  }

  function bindCoordToggles(root) {
    var box = root || document;
    box.querySelectorAll(".inspect-coord-toggle").forEach(function (btn) {
      btn.addEventListener("click", function (ev) {
        ev.preventDefault();
        setCoordSource(btn.getAttribute("data-coord-source") || "clave", true);
      });
    });
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
    return (
      '<div class="mxli-feature-popup mxli-inspect-popup">' +
      '<div class="mxli-feature-popup__head">Inspección</div>' +
      '<div class="mxli-feature-popup__body mxli-inspect-popup__body">' +
      photo +
      '<table class="mxli-padron-table mxli-inspect-ficha" role="presentation"><tbody>' +
      '<tr><td class="mxli-padron-label">Clave</td><td class="mxli-padron-value"><b>' +
      escapeHtml(String(item.clavecatas || "—").toUpperCase()) +
      "</b></td></tr>" +
      '<tr><td class="mxli-padron-label">Fecha</td><td class="mxli-padron-value"><b>' +
      escapeHtml(String(formatDate(item.captured_at)).toUpperCase()) +
      "</b></td></tr>" +
      '<tr><td class="mxli-padron-label">Inspector</td><td class="mxli-padron-value"><b>' +
      escapeHtml(String(item.created_by_name || "—").toUpperCase()) +
      "</b></td></tr>" +
      '<tr><td class="mxli-padron-label">Tipos</td><td class="mxli-padron-value"><b>' +
      escapeHtml(String(types || "—").toUpperCase()) +
      "</b></td></tr>" +
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

    fetch(API + "?with_coords=1&limit=2000")
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
      '<form method="post" id="inspect-photo-form" class="inspect-photo-form" novalidate>' +
      '<h3>Inspección fotográfica</h3>' +
      '<p class="inspect-photo-form__hint">Capture la clave catastral y la fotografía del predio.</p>' +
      '<label>Clave catastral<input type="text" id="inspect-clave" maxlength="32" required placeholder="ST312014"></label>' +
      coordSourceHtml() +
      '<label>Notas (opcional)<textarea id="inspect-notes" rows="2" placeholder="Observaciones"></textarea></label>' +
      typesHtml("") +
      '<div class="inspect-photo-preview-wrap">' +
      '<img id="inspect-preview" class="inspect-photo-preview" alt="Vista previa" hidden>' +
      '<button type="button" id="inspect-take-btn" class="inspect-photo-take">Tomar / seleccionar foto</button>' +
      '<input type="file" id="inspect-photo-input" accept="image/*" capture="environment" hidden>' +
      "</div>" +
      '<p id="inspect-msg" class="inspect-photo-msg" hidden></p>' +
      '<div class="inspect-photo-actions">' +
      '<button type="button" id="inspect-cancel-btn" class="inspect-photo-btn inspect-photo-btn--ghost">Cancelar</button>' +
      '<button type="submit" value="save" class="inspect-photo-btn inspect-photo-btn--primary">Guardar registro</button>' +
      "</div>" +
      "</form>";
    document.body.appendChild(dlg);
    bindCoordToggles(dlg);
    var claveInput = $("inspect-clave");
    if (claveInput) {
      claveInput.addEventListener("input", autoPickCoordSourceFromClave);
      claveInput.addEventListener("change", autoPickCoordSourceFromClave);
    }

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

    var cancelBtn = $("inspect-cancel-btn");
    if (cancelBtn) {
      cancelBtn.addEventListener("click", function (event) {
        event.preventDefault();
        event.stopPropagation();
        closeInspectDialog();
      });
    }

    dlg.addEventListener("cancel", function (event) {
      // Escape key — allow default close
      event.preventDefault();
      closeInspectDialog();
    });

    var takeBtn = $("inspect-take-btn");
    var fileInput = $("inspect-photo-input");
    var preview = $("inspect-preview");
    var selectedFile = null;

    takeBtn.addEventListener("click", function () {
      fileInput.click();
    });

    fileInput.addEventListener("change", function () {
      selectedFile = fileInput.files && fileInput.files[0] ? fileInput.files[0] : null;
      if (!selectedFile) {
        preview.hidden = true;
        preview.removeAttribute("src");
        return;
      }
      preview.src = URL.createObjectURL(selectedFile);
      preview.hidden = false;
    });

    dlg.addEventListener("close", function () {
      selectedFile = null;
      fileInput.value = "";
      preview.hidden = true;
      preview.removeAttribute("src");
    });

    $("inspect-photo-form").addEventListener("submit", function (event) {
      event.preventDefault();
      if (!selectedFile) {
        showMsg($("inspect-msg"), "Seleccione o tome una fotografía.", false);
        return;
      }

      var clave = String($("inspect-clave").value || "").trim().replace(/\s+/g, "").toUpperCase();
      if (clave.length < 4) {
        showMsg($("inspect-msg"), "Capture una clave catastral válida.", false);
        return;
      }

      showMsg($("inspect-msg"), "Guardando…", true);

      var typesCsv = selectedTypesCsv(dlg);
      if (!typesCsv) {
        showMsg($("inspect-msg"), "Seleccione al menos un tipo de inspección.", false);
        return;
      }

      var source = getCoordSource();
      if (clave.length >= 4 && source !== "gps") {
        source = "clave";
        setCoordSource("clave", false);
      }
      if (clave.length < 4) {
        source = "gps";
        setCoordSource("gps", false);
      }

      resolveSaveCoords(clave, source)
        .then(function (coords) {
          var fd = new FormData();
          fd.append("clavecatas", clave);
          fd.append(
            "notes",
            String($("inspect-notes").value || "")
              .trim()
              .toUpperCase()
          );
          fd.append("inspection_types", typesCsv);
          fd.append("photo", selectedFile);
          fd.append("coord_source", coords.source || source);
          if (coords.lat != null && coords.lng != null) {
            fd.append("lat", String(coords.lat));
            fd.append("lng", String(coords.lng));
          }

          return fetch(API, { method: "POST", body: fd }).then(function (res) {
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
            window.mxliRefreshInspeccionesCapa();
          }
          window.alert("Inspección registrada correctamente.");
        })
        .catch(function (err) {
          showMsg($("inspect-msg"), err.message || "Error", false);
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
    $("inspect-clave").value = claveFromSearch();
    $("inspect-notes").value = "";
    var dlgEl = $("inspect-photo-dialog");
    if (dlgEl) {
      dlgEl.querySelectorAll('input[type="checkbox"][name="inspection_types"]').forEach(function (inp) {
        inp.checked = false;
      });
    }
    showMsg($("inspect-msg"), "", true);
    dlg.showModal();
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
