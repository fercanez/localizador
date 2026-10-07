(function (window) {
  "use strict";

  var MIN_ZOOM = 17;
  var MAX_FEATURES = 400;
  var MAX_PAINT = 100;
  var BATCH_SIZE = 2;
  var MOVE_DEBOUNCE_MS = 700;
  var COLOR_OK = "#16a34a";
  var COLOR_OK_LINE = "#15803d";
  var COLOR_DEBT = "#dc2626";
  var COLOR_DEBT_LINE = "#b91c1c";
  var COLOR_ASEO = "#ea580c";
  var COLOR_ASEO_LINE = "#c2410c";
  var COLOR_WAIT = "#64748b";
  var COLOR_WAIT_LINE = "#334155";

  var gen = 0;
  var moveTimer = null;
  var lastBboxKey = "";
  var bound = false;

  function layer() {
    return window.predios_adeudo || null;
  }

  function map() {
    return window.map2d || null;
  }

  function chk() {
    return document.getElementById("predios_adeudo_chkbox");
  }

  function statusEl() {
    return document.getElementById("predios_adeudo_status");
  }

  function setStatus(text) {
    var el = statusEl();
    if (el) el.textContent = text || "";
  }

  function isOn() {
    var box = chk();
    return !!(box && box.checked);
  }

  function canUse() {
    if (typeof window.mxliCanLayer === "function") {
      return window.mxliCanLayer("predios_adeudo");
    }
    return true;
  }

  function styleFor(estatus) {
    if (estatus === "con_adeudo") {
      return {
        pane: "predialAdeudo",
        color: COLOR_DEBT_LINE,
        weight: 2.2,
        fillColor: COLOR_DEBT,
        fillOpacity: 0.55,
      };
    }
    if (estatus === "adeudo_aseo") {
      return {
        pane: "predialAdeudo",
        color: COLOR_ASEO_LINE,
        weight: 2.2,
        fillColor: COLOR_ASEO,
        fillOpacity: 0.55,
      };
    }
    if (estatus === "sin_adeudo") {
      return {
        pane: "predialAdeudo",
        color: COLOR_OK_LINE,
        weight: 2.2,
        fillColor: COLOR_OK,
        fillOpacity: 0.5,
      };
    }
    return {
      pane: "predialAdeudo",
      color: COLOR_WAIT_LINE,
      weight: 1.6,
      fillColor: COLOR_WAIT,
      fillOpacity: 0.28,
    };
  }

  function ensureOnMap() {
    var m = map();
    var lyr = layer();
    if (!m || !lyr) return;
    if (typeof m.hasLayer === "function" && !m.hasLayer(lyr)) {
      lyr.addTo(m);
    }
    if (typeof lyr.bringToFront === "function") {
      lyr.bringToFront();
    }
  }

  function ensureOffMap() {
    var m = map();
    var lyr = layer();
    if (!m || !lyr) return;
    if (typeof m.hasLayer === "function" && m.hasLayer(lyr)) {
      m.removeLayer(lyr);
    }
    try {
      if (typeof group !== "undefined" && group && group.hasLayer && group.hasLayer(lyr)) {
        group.removeLayer(lyr);
      }
    } catch (e) {}
  }

  function clearCapa() {
    var lyr = layer();
    if (lyr && typeof lyr.clearLayers === "function") {
      lyr.clearLayers();
    }
    lastBboxKey = "";
  }

  function currentBbox() {
    var m = map();
    if (!m || typeof m.getBounds !== "function") return null;
    var b = m.getBounds();
    var sw = b.getSouthWest();
    var ne = b.getNorthEast();
    return {
      minLng: sw.lng,
      minLat: sw.lat,
      maxLng: ne.lng,
      maxLat: ne.lat,
    };
  }

  function bboxKey(bb) {
    if (!bb) return "";
    return [bb.minLng, bb.minLat, bb.maxLng, bb.maxLat]
      .map(function (n) {
        return Number(n).toFixed(5);
      })
      .join(",");
  }

  function bboxQuery(bb) {
    return [bb.minLng, bb.minLat, bb.maxLng, bb.maxLat].join(",");
  }

  function featureInCanvas(feature, bounds) {
    try {
      var tmp = L.geoJSON(feature);
      var gb = tmp.getBounds();
      return bounds && gb && bounds.intersects(gb);
    } catch (e) {
      return false;
    }
  }

  function featureCenter(feature) {
    try {
      return L.geoJSON(feature).getBounds().getCenter();
    } catch (e) {
      return null;
    }
  }

  function nearestFromCenter(features, origin, limit) {
    if (!origin || !features || !features.length) return [];
    return features
      .map(function (f) {
        var c = featureCenter(f);
        return { f: f, d: c ? origin.distanceTo(c) : Infinity };
      })
      .sort(function (a, b) {
        return a.d - b.d;
      })
      .slice(0, limit)
      .map(function (x) {
        return x.f;
      });
  }

  function paintFeature(lyr, data) {
    var est =
      typeof window.mxliPredialEstatus === "function"
        ? window.mxliPredialEstatus(data)
        : data && data.estatus_consulta;
    if (lyr.feature && lyr.feature.properties) {
      lyr.feature.properties.estatus = est;
      lyr.feature.properties.total_a_pagar = data && data.total_a_pagar;
    }
    if (typeof lyr.setStyle === "function") {
      lyr.setStyle(styleFor(est));
    }
  }

  function paintClave(clave, data) {
    var lyr = layer();
    if (!lyr || typeof lyr.eachLayer !== "function") return;
    var key = String(clave || "").toUpperCase();
    lyr.eachLayer(function (poly) {
      var c = String(
        (poly.feature && poly.feature.properties && poly.feature.properties.clavecatas) || ""
      ).toUpperCase();
      if (c === key) paintFeature(poly, data);
    });
  }

  function bindFeature(feature, lyr) {
    var clave = String((feature.properties && feature.properties.clavecatas) || "").toUpperCase();
    lyr.on("click", function (ev) {
      if (ev && ev.originalEvent) {
        L.DomEvent.stopPropagation(ev.originalEvent);
      }
      if (clave && window.BuscarPredioMxli && typeof window.BuscarPredioMxli.buscar === "function") {
        window.BuscarPredioMxli.buscar(clave);
      }
    });
  }

  function consultarAdeudosProgresivo(claves, token, force) {
    if (!claves.length || typeof window.mxliConsultarPredialAdeudo !== "function") {
      return Promise.resolve({ debt: 0, ok: 0, gray: [] });
    }
    var done = 0;
    var debt = 0;
    var ok = 0;
    var aseo = 0;
    var gray = [];

    function runBatch(start) {
      if (token !== gen) return Promise.resolve();
      var batch = claves.slice(start, start + BATCH_SIZE);
      if (!batch.length) return Promise.resolve();
      return Promise.all(
        batch.map(function (clave) {
          return window
            .mxliConsultarPredialAdeudo(clave, force ? { force: true } : null)
            .then(function (data) {
              if (token !== gen) return;
              done += 1;
              var est =
                typeof window.mxliPredialEstatus === "function"
                  ? window.mxliPredialEstatus(data)
                  : "";
              if (est === "con_adeudo") debt += 1;
              else if (est === "adeudo_aseo") aseo += 1;
              else if (est === "sin_adeudo") ok += 1;
              else gray.push(clave);
              paintClave(clave, data);
              setStatus(
                "Adeudo portal: " +
                  done +
                  "/" +
                  claves.length +
                  "  ·  " +
                  ok +
                  " sin adeudo, " +
                  debt +
                  " predial, " +
                  aseo +
                  " aseo" +
                  (gray.length ? ", " + gray.length + " pendientes" : "")
              );
            });
        })
      ).then(function () {
        return runBatch(start + BATCH_SIZE);
      });
    }

    return runBatch(0).then(function () {
      return { debt: debt, ok: ok, gray: gray };
    });
  }

  function cargarVista() {
    if (!canUse() || !isOn()) {
      clearCapa();
      ensureOffMap();
      return;
    }
    var m = map();
    var lyr = layer();
    if (!m || !lyr) return;

    var zoom = m.getZoom();
    if (zoom < MIN_ZOOM) {
      clearCapa();
      setStatus("Acerque el mapa (zoom " + MIN_ZOOM + "+) para ver predios en verde/rojo.");
      return;
    }

    ensureOnMap();

    var bb = currentBbox();
    if (!bb) return;
    var key = bboxKey(bb);
    if (key === lastBboxKey) return;
    lastBboxKey = key;

    var token = ++gen;
    setStatus("Consultando predios en vista…");

    fetch(
      "api/wfs-bbox.php?bbox=" +
        encodeURIComponent(bboxQuery(bb)) +
        "&limit=" +
        encodeURIComponent(String(MAX_FEATURES)),
      { credentials: "same-origin", cache: "no-store" }
    )
      .then(function (res) {
        return res.json();
      })
      .then(function (data) {
        if (token !== gen) return;
        if (!data || data.ok === false) {
          setStatus((data && data.error) || "No se pudieron cargar predios.");
          return;
        }
        var bounds = m.getBounds();
        var inView = (data.features || []).filter(function (f) {
          return featureInCanvas(f, bounds);
        });
        var origin = m.getCenter();
        var totalInView = inView.length;
        var features = nearestFromCenter(inView, origin, MAX_PAINT);
        var clipped = totalInView > features.length;
        lyr.clearLayers();
        ensureOnMap();
        lyr.addData({
          type: "FeatureCollection",
          features: features,
        });
        lyr.eachLayer(function (poly) {
          poly.setStyle(styleFor("pendiente"));
          bindFeature(poly.feature, poly);
        });
        if (typeof lyr.bringToFront === "function") lyr.bringToFront();
        var seenClaves = {};
        var claves = [];
        features.forEach(function (f) {
          var c = String((f.properties && f.properties.clavecatas) || "").toUpperCase();
          if (!c || seenClaves[c]) return;
          seenClaves[c] = true;
          claves.push(c);
        });
        if (!claves.length) {
          setStatus("No hay predios en esta vista. Acerque un poco más.");
          return;
        }
        setStatus("Pintando " + claves.length + " predios desde el centro…");
        return consultarAdeudosProgresivo(claves, token).then(function (stats) {
          if (token !== gen) return;
          var extra = clipped
            ? " Solo los " + MAX_PAINT + " más cercanos al centro."
            : "";
          var gray = (stats && stats.gray) || [];
          if (gray.length) {
            setStatus("Reintentando " + gray.length + " predios en gris…");
            return consultarAdeudosProgresivo(gray, token, true).then(function (retry) {
              if (token !== gen) return;
              var stillGray = ((retry && retry.gray) || []).length;
              if (stillGray) {
                setStatus(
                  "Capa lista: " +
                    claves.length +
                    " predios." +
                    extra +
                    " " +
                    stillGray +
                    " siguen sin color (portal no respondió)."
                );
              } else {
                setStatus("Capa lista: " + claves.length + " predios." + extra);
              }
            });
          }
          setStatus("Capa lista: " + claves.length + " predios." + extra);
        });
      })
      .catch(function () {
        if (token !== gen) return;
        setStatus("No se pudo cargar la capa de adeudo.");
      });
  }

  function sync() {
    if (!isOn()) {
      gen += 1;
      clearCapa();
      ensureOffMap();
      setStatus("Capa apagada.");
      return;
    }
    lastBboxKey = "";
    cargarVista();
  }

  function onMove() {
    if (moveTimer) clearTimeout(moveTimer);
    moveTimer = setTimeout(function () {
      cargarVista();
    }, MOVE_DEBOUNCE_MS);
  }

  function bindMap() {
    var m = map();
    if (!m || bound) return;
    bound = true;
    if (!m.getPane("predialAdeudo")) {
      m.createPane("predialAdeudo");
      m.getPane("predialAdeudo").style.zIndex = 650;
    }
    var lyr = layer();
    if (lyr && lyr.options) {
      lyr.options.pane = "predialAdeudo";
    }
    m.on("zoomend", onMove);
    m.on("moveend", onMove);
    window.addEventListener("mxli-predial-adeudo", function (ev) {
      var data = ev && ev.detail;
      if (!data || !isOn()) return;
      paintClave(data.clave_catastral, data);
    });
  }

  function init() {
    if (!canUse()) {
      var card = document.getElementById("predios_adeudo_card");
      if (card) card.style.display = "none";
      return;
    }
    bindMap();
    var box = chk();
    if (box && !box._mxliPredialBound) {
      box._mxliPredialBound = true;
      box.addEventListener("change", function () {
        setTimeout(sync, 0);
      });
    }
    if (isOn()) {
      sync();
    } else {
      setStatus("Encienda la capa y acerque el mapa (zoom " + MIN_ZOOM + "+).");
    }
  }

  function boot() {
    if (!map() || !layer()) {
      setTimeout(boot, 250);
      return;
    }
    init();
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", boot);
  } else {
    boot();
  }

  window.MxliPredialCapa = {
    sync: sync,
    minZoom: MIN_ZOOM,
    maxPaint: MAX_PAINT,
  };
})(window);
