// =====================================================
// BUSCADOR FLOTANTE DE PREDIO + COTAS TIPO CATASTRO
// =====================================================

(function () {
  "use strict";

  if (window.BuscadorPrediosMxliInicializado) return;
  window.BuscadorPrediosMxliInicializado = true;

  const WFS_PREDIOS_URL = "api/wfs.php";
  const WFS_LIST_URL = "api/wfs-list.php";

  let predioBuscadoLayer = null;
  let predioRellenoLayer = null;
  let predioMedidasLayer = null;
  let ultimoRingCotas = null;
  let medidasVisibles = true;
  const COTA_COLOR = "#c62828";
  const PREDIO_COLOR = "#0057ff";

  function init() {
    if (typeof window.mxliCan === "function" && !window.mxliCan("map.search")) {
      return;
    }
    if (typeof window.mxliCanLayer === "function" && !window.mxliCanLayer("predios_mexicali_2025")) {
      return;
    }
    if (typeof L === "undefined" || typeof map2d === "undefined") {
      setTimeout(init, 500);
      return;
    }

    insertarEstilos();
    predioMedidasLayer = L.layerGroup().addTo(map2d);
    crearBuscadorPredio();

    map2d.on("zoomend", function () {
      if (ultimoRingCotas && medidasVisibles) {
        dibujarCotasProfesionales(ultimoRingCotas);
      }
    });
  }

  function insertarEstilos() {
    if (document.getElementById("buscador-predios-mxli-css")) return;

    const style = document.createElement("style");
    style.id = "buscador-predios-mxli-css";
    style.innerHTML = `
      .buscador-predio-flotante {
        margin: 0 !important;
      }

      .buscador-predio-box {
        display:flex;
        gap:6px;
        align-items:center;
        font-family:Arial,sans-serif;
      }

      .buscador-predio-min,
      .buscador-predio-open {
        width:38px;
        height:38px;
        border:0;
        border-radius:8px;
        background:#9b2f55;
        color:#fff;
        font-weight:bold;
        font-size:18px;
        cursor:pointer;
        box-shadow:0 2px 8px rgba(0,0,0,.28);
      }

      .buscador-predio-input {
        width:165px;
        height:34px;
        border:1px solid #aaa;
        border-radius:6px;
        padding:0 9px;
        font-size:13px;
        text-transform:uppercase;
        outline:none;
      }

      .buscador-predio-input:focus {
        border-color:#9b2f55;
        box-shadow:0 0 0 2px rgba(155,47,85,.18);
      }

      .buscador-predio-btn {
        height:36px;
        border:0;
        border-radius:6px;
        padding:0 13px;
        cursor:pointer;
        background:#9b2f55;
        color:#fff;
        font-weight:bold;
      }

      .buscador-predio-btn-medidas {
        background:#fff;
        color:#c62828;
        border:1px solid #c62828;
        display:none;
      }

      .buscador-predio-btn-medidas.is-on {
        background:#c62828;
        color:#fff;
      }

      .buscador-predio-open {
        display:none;
      }

      .buscador-predio-results {
        position:absolute;
        left:0;
        right:0;
        top:42px;
        background:#fff;
        border:1px solid rgba(0,0,0,.18);
        border-radius:10px;
        box-shadow:0 10px 24px rgba(0,0,0,.18);
        max-height:220px;
        overflow:auto;
        padding:6px;
        z-index:999999;
      }

      .buscador-predio-results[hidden] { display:none; }

      .buscador-predio-results__title {
        font-size:12px;
        font-weight:800;
        color:#5a2f3d;
        padding:6px 8px;
      }

      .buscador-predio-results__list {
        list-style:none;
        padding:0;
        margin:0;
      }

      .buscador-predio-results__item {
        width:100%;
        text-align:left;
        display:flex;
        justify-content:space-between;
        gap:10px;
        padding:8px 10px;
        border-radius:8px;
        border:0;
        background:#fff;
        cursor:pointer;
        font-size:13px;
        font-weight:800;
        color:#1f1f1f;
      }

      .buscador-predio-results__item:hover {
        background:#f7f0f2;
      }

      .predio-medida-label,
      .predio-vertice-label {
        background:transparent;
        border:0;
      }

      .predio-medida-label-inner {
        color:#c62828;
        font-size:12px;
        font-weight:900;
        white-space:nowrap;
        text-shadow:
          -1px -1px 0 #fff,
           1px -1px 0 #fff,
          -1px  1px 0 #fff,
           1px  1px 0 #fff,
           0px  0px 4px #fff;
      }

      .predio-vertice-label-inner {
        color:#c62828;
        font-size:12px;
        font-weight:900;
        white-space:nowrap;
        text-shadow:
          -1px -1px 0 #fff,
           1px -1px 0 #fff,
          -1px  1px 0 #fff,
           1px  1px 0 #fff,
           0px  0px 4px #fff;
      }
    `;
    document.head.appendChild(style);
  }

  function crearBuscadorPredio() {
    const mount = document.getElementById("map-top-toolbar-search");
    if (!mount) {
      setTimeout(crearBuscadorPredio, 300);
      return;
    }

    if (mount.dataset.ready === "1") return;
    mount.dataset.ready = "1";

    mount.innerHTML = `
      <div id="buscadorPredioBox" class="buscador-predio-box" style="position:relative">
        <label for="buscarPredioClave" class="buscador-predio-label">Buscar Clave Catastral:</label>
        <input id="buscarPredioClave" class="buscador-predio-input" type="text" placeholder="CLAVE CATASTRAL" />
        <button id="btnBuscarPredioClave" class="buscador-predio-btn" type="button">Buscar</button>
        <button id="btnToggleMedidasPredio" class="buscador-predio-btn buscador-predio-btn-medidas is-on" type="button" title="Mostrar u ocultar medidas">Medidas</button>
        <div id="buscadorPredioResults" class="buscador-predio-results" hidden>
          <div id="buscadorPredioResultsTitle" class="buscador-predio-results__title"></div>
          <ul id="buscadorPredioResultsList" class="buscador-predio-results__list"></ul>
        </div>
      </div>
    `;

    wireBuscadorEvents();

    if (typeof initMapTopToolbar === "function") {
      initMapTopToolbar();
    }
  }

  function actualizarBotonMedidas() {
    const btn = document.getElementById("btnToggleMedidasPredio");
    if (!btn) return;
    const hayPredio = !!ultimoRingCotas;
    btn.style.display = hayPredio ? "inline-flex" : "none";
    btn.classList.toggle("is-on", medidasVisibles);
    btn.textContent = medidasVisibles ? "Medidas ON" : "Medidas OFF";
    btn.setAttribute("aria-pressed", medidasVisibles ? "true" : "false");
  }

  function setMedidasVisibles(visible) {
    medidasVisibles = !!visible;
    if (!predioMedidasLayer) {
      actualizarBotonMedidas();
      return;
    }
    if (medidasVisibles) {
      if (!map2d.hasLayer(predioMedidasLayer)) {
        predioMedidasLayer.addTo(map2d);
      }
      if (ultimoRingCotas) {
        dibujarCotasProfesionales(ultimoRingCotas);
      }
    } else {
      predioMedidasLayer.clearLayers();
      if (map2d.hasLayer(predioMedidasLayer)) {
        map2d.removeLayer(predioMedidasLayer);
      }
    }
    actualizarBotonMedidas();
  }

  function wireBuscadorEvents() {
    const input = document.getElementById("buscarPredioClave");
    const btn = document.getElementById("btnBuscarPredioClave");
    const btnMedidas = document.getElementById("btnToggleMedidasPredio");
    const results = document.getElementById("buscadorPredioResults");

    if (!input || !btn) return;

    if (btnMedidas) {
      btnMedidas.addEventListener("click", function () {
        setMedidasVisibles(!medidasVisibles);
      });
    }

    let t = null;
    input.addEventListener("input", function () {
      this.value = limpiarClave(this.value);
      const value = this.value;
      if (t) clearTimeout(t);
      t = setTimeout(function () {
        if (esPrefijoListado(value)) {
          buscarListadoPorPrefijo(value);
        } else {
          ocultarResultados();
        }
      }, value.length <= 2 ? 400 : 220);
    });

    btn.addEventListener("click", function () {
      const value = limpiarClave(input.value);
      if (esPrefijoListado(value)) {
        buscarListadoPorPrefijo(value);
        return;
      }
      buscarPredioPorClave(value);
    });

    input.addEventListener("keydown", function (e) {
      if (e.key !== "Enter") return;
      e.preventDefault();
      const value = limpiarClave(input.value);
      if (esPrefijoListado(value)) {
        buscarListadoPorPrefijo(value);
        return;
      }
      buscarPredioPorClave(value);
    });

    // Cerrar resultados al hacer clic fuera
    document.addEventListener("click", function (evt) {
      if (!results) return;
      const target = evt.target;
      if (!(target instanceof Node)) return;
      if (results.contains(target)) return;
      if (input.contains(target)) return;
      ocultarResultados();
    });
  }

  function esPrefijoListado(valor) {
    const len = String(valor || "").length;
    return len >= 2 && len < 8;
  }

  function etiquetaPrefijoBusqueda(prefix) {
    if (prefix.length === 2) return "Homoclave " + prefix;
    if (prefix.length >= 5 && prefix.length <= 7) return "Manzana " + prefix;
    return "Prefijo " + prefix;
  }

  function limiteListadoPorPrefijo(prefix) {
    if (prefix.length <= 2) return 250;
    if (prefix.length <= 4) return 180;
    return 120;
  }

  function escaparCQL(valor) {
    return String(valor).replace(/'/g, "''");
  }

  async function buscarPredioPorClave(claveOriginal) {
    if (typeof window.mxliCan === "function" && !window.mxliCan("map.search")) {
      window.alert("Su rol no tiene permiso para buscar predios.");
      return;
    }
    if (typeof window.mxliCanLayer === "function" && !window.mxliCanLayer("predios_mexicali_2025")) {
      window.alert("Su rol no tiene permiso para consultar la capa de Predios.");
      return;
    }
    const clave = limpiarClave(claveOriginal);

    if (!clave) {
      alert("Capture una clave catastral.");
      return;
    }

    try {
      const res = await fetch(WFS_PREDIOS_URL + "?clave=" + encodeURIComponent(clave));
      const texto = await res.text();

      if (!res.ok) {
        let mensaje = "Error al buscar el predio.";
        try {
          const err = JSON.parse(texto);
          if (err.error) mensaje = err.error;
        } catch (parseError) {}
        throw new Error(mensaje);
      }
      if (texto.trim().startsWith("<")) throw new Error(texto);

      const data = JSON.parse(texto);

      if (!data.features || data.features.length === 0) {
        alert("No se encontró predio con esa clave.");
        return;
      }

      mostrarPredioEncontrado(data.features[0]);
    } catch (error) {
      console.error("Error buscando predio:", error);
      alert(error.message || "Error al buscar el predio.");
    }
  }

  function ocultarResultados() {
    const box = document.getElementById("buscadorPredioResults");
    const list = document.getElementById("buscadorPredioResultsList");
    const title = document.getElementById("buscadorPredioResultsTitle");
    if (list) list.innerHTML = "";
    if (title) title.textContent = "";
    if (box) box.hidden = true;
  }

  function renderResultados(prefix, claves, meta) {
    const box = document.getElementById("buscadorPredioResults");
    const list = document.getElementById("buscadorPredioResultsList");
    const title = document.getElementById("buscadorPredioResultsTitle");
    if (!box || !list || !title) return;

    list.innerHTML = "";
    const total = (claves || []).length;
    const etiqueta = etiquetaPrefijoBusqueda(prefix);
    let titulo = etiqueta + ": " + total + " predio(s)";
    if (meta && meta.truncated) {
      titulo +=
        " (mostrando " +
        total +
        " de " +
        (meta.total_matched || "muchos") +
        ". Refine la búsqueda)";
    }
    title.textContent = titulo;

    (claves || []).forEach(function (clave) {
      const li = document.createElement("li");
      const btn = document.createElement("button");
      btn.type = "button";
      btn.className = "buscador-predio-results__item";
      btn.textContent = clave;
      btn.addEventListener("click", function () {
        ocultarResultados();
        const input = document.getElementById("buscarPredioClave");
        if (input) input.value = clave;
        buscarPredioPorClave(clave);
      });
      li.appendChild(btn);
      list.appendChild(li);
    });

    box.hidden = total === 0;
  }

  async function buscarListadoPorPrefijo(prefix) {
    if (typeof window.mxliCan === "function" && !window.mxliCan("map.search")) {
      return;
    }
    if (typeof window.mxliCanLayer === "function" && !window.mxliCanLayer("predios_mexicali_2025")) {
      return;
    }

    const box = document.getElementById("buscadorPredioResults");
    const title = document.getElementById("buscadorPredioResultsTitle");
    if (box) box.hidden = false;
    if (title) title.textContent = "Buscando…";

    const limit = limiteListadoPorPrefijo(prefix);

    try {
      const res = await fetch(
        WFS_LIST_URL +
          "?prefix=" +
          encodeURIComponent(prefix) +
          "&limit=" +
          encodeURIComponent(String(limit))
      );
      const data = await res.json();
      if (!res.ok || !data || data.ok === false) {
        throw new Error((data && data.error) || "Error al listar predios.");
      }
      renderResultados(prefix, data.claves || [], {
        truncated: !!data.truncated,
        total_matched: data.total_matched,
      });
    } catch (err) {
      renderResultados(prefix, [], null);
      if (title) title.textContent = String(err && err.message ? err.message : "Error");
    }
  }

  function limpiarClave(valor) {
    return String(valor || "").trim().replace(/\s+/g, "").toUpperCase();
  }

  function mostrarPredioEncontrado(feature) {
    encenderCapaPredios();
    limpiarResultadoAnterior();

    const ring = obtenerRingPrincipal(feature.geometry);
    const ringContorno = limpiarRingCadastral(ring);
    const ringFinal = ringParaCotas(ringContorno);

    const latlngs = ringContorno.map(p => [p[1], p[0]]);

    predioRellenoLayer = L.polygon(latlngs, {
      color: PREDIO_COLOR,
      weight: 0,
      fillColor: "#00a2ff",
      fillOpacity: 0.10,
      interactive: false,
    }).addTo(map2d);

    predioBuscadoLayer = L.polyline(latlngs, {
      color: PREDIO_COLOR,
      weight: 4,
      opacity: 1,
      dashArray: "7,5",
    }).addTo(map2d);

    const bounds = predioBuscadoLayer.getBounds();
    ultimoRingCotas = ringFinal;
    medidasVisibles = true;

    map2d.fitBounds(bounds, {
      padding: [130, 130],
      maxZoom: 19,
    });

    setTimeout(function () {
      setMedidasVisibles(true);
      abrirPopupPredio(feature, bounds.getCenter());
    }, 450);
  }

  function encenderCapaPredios() {
    if (typeof window.mxliCanLayer === "function" && !window.mxliCanLayer("predios_mexicali_2025")) {
      return;
    }
    if (typeof predios !== "undefined" && !map2d.hasLayer(predios)) {
      if (typeof group !== "undefined" && group.addLayer) group.addLayer(predios);
      else predios.addTo(map2d);

      const chk = document.getElementById("predios_chkbox");
      if (chk) chk.checked = true;

      if (typeof actualizarCapaActivaConsulta === "function") {
        actualizarCapaActivaConsulta();
      } else {
        window.capaActiva = predios;
      }
    }
  }

  function limpiarResultadoAnterior() {
    if (predioBuscadoLayer) map2d.removeLayer(predioBuscadoLayer);
    if (predioRellenoLayer) map2d.removeLayer(predioRellenoLayer);
    if (predioMedidasLayer) predioMedidasLayer.clearLayers();

    predioBuscadoLayer = null;
    predioRellenoLayer = null;
    ultimoRingCotas = null;
    map2d.closePopup();
    actualizarBotonMedidas();
  }

  function abrirPopupPredio(feature, latlng) {
    const p = feature.properties || {};
    const clave = p.clavecatas || "";
    const homoclave = clave.substring(0, 2);
    const manzana = p.manzana || clave.substring(2, 5);
    const lote = p.lote || clave.substring(5, 8);
    const superficie = Number(p.superficie || 0).toLocaleString("es-MX", {
      minimumFractionDigits: 2,
      maximumFractionDigits: 2,
    });

    const html =
      '<div class="mxli-feature-popup">' +
      '<div class="mxli-feature-popup__head">Información del predio</div>' +
      '<div class="mxli-feature-popup__body">' +
      "<div><b>Clave Catastral:</b> " +
      clave +
      "</div>" +
      "<div><b>Homoclave:</b> " +
      homoclave +
      "</div>" +
      "<div><b>Manzana:</b> " +
      manzana +
      "</div>" +
      "<div><b>Lote:</b> " +
      lote +
      "</div>" +
      "<div><b>Sup. Cart:</b> " +
      superficie +
      " m²</div>" +
      "</div></div>";
    const popupOpts =
      typeof mxliPopupOptions === "function"
        ? mxliPopupOptions()
        : window.matchMedia("(max-width: 900px)").matches
          ? { maxWidth: 260, minWidth: 160, maxHeight: 160, autoPanPadding: [48, 72] }
          : { maxHeight: 350, minWidth: 230 };
    L.popup(popupOpts).setLatLng(latlng).setContent(html).openOn(map2d);
  }
  function obtenerRingPrincipal(geom) {
    if (geom.type === "Polygon") return cerrarRing(geom.coordinates[0]);
    if (geom.type === "MultiPolygon") return cerrarRing(geom.coordinates[0][0]);
    throw new Error("Geometría no soportada");
  }

  function cerrarRing(ring) {
    const r = ring.slice();
    const a = r[0];
    const b = r[r.length - 1];

    if (a[0] !== b[0] || a[1] !== b[1]) r.push([a[0], a[1]]);
    return r;
  }

  function limpiarRingCadastral(ring) {
    let puntos = ring.slice(0, -1);
    puntos = eliminarPuntosCortos(puntos, 0.15);
    puntos = eliminarColineales(puntos, 1.5);
    if (puntos.length < 3) puntos = ring.slice(0, -1);
    puntos.push([puntos[0][0], puntos[0][1]]);
    return puntos;
  }

  function ringParaCotas(ringContorno) {
    let puntos = ringContorno.slice(0, -1);
    const hull = convexHullMetros(puntos);

    // Rectángulo simple (4 esquinas, pocos vértices): cotas en las 4 esquinas exteriores
    if (hull.length === 4 && puntos.length <= 12) {
      return cerrarRing(hull);
    }

    // Polígono complejo: medir todos los lados del contorno hasta cerrar
    return ringContorno;
  }

  /** Plano local: UTM (metros) o lon/lat convertido a metros aproximados */
  function planimetriaXY(p) {
    if (Math.abs(p[0]) > 180 || Math.abs(p[1]) > 90) {
      return { x: p[0], y: p[1] };
    }
    return { x: lngToMeters(p[0]), y: latToMeters(p[1]) };
  }

  /** Contorno convexo (Graham) en metros planos */
  function convexHullMetros(points) {
    if (points.length < 3) return points.slice();

    const pts = points
      .map(function (p, idx) {
        const xy = planimetriaXY(p);
        return { p: p, idx: idx, x: xy.x, y: xy.y };
      })
      .sort(function (a, b) {
        return a.y - b.y || a.x - b.x;
      });

    const start = pts[0];
    const sorted = pts.slice(1).sort(function (a, b) {
      const angA = Math.atan2(a.y - start.y, a.x - start.x);
      const angB = Math.atan2(b.y - start.y, b.x - start.x);
      return angA - angB;
    });

    const stack = [start, sorted[0]];

    function cross(o, a, b) {
      return (a.x - o.x) * (b.y - o.y) - (a.y - o.y) * (b.x - o.x);
    }

    for (let i = 1; i < sorted.length; i++) {
      const p = sorted[i];
      while (stack.length > 1 && cross(stack[stack.length - 2], stack[stack.length - 1], p) <= 0) {
        stack.pop();
      }
      stack.push(p);
    }

    return stack.map(function (s) {
      return s.p;
    });
  }

  function eliminarPuntosCortos(points, minMetros) {
    const res = [];

    for (let i = 0; i < points.length; i++) {
      const prev = res[res.length - 1];
      const curr = points[i];

      if (!prev || distanciaCoord(prev, curr) >= minMetros) {
        res.push(curr);
      }
    }

    // Cierre del anillo: no dejar primer/último demasiado cerca
    if (res.length >= 3 && distanciaCoord(res[0], res[res.length - 1]) < minMetros) {
      res.pop();
    }

    return res.length >= 3 ? res : points;
  }

  function eliminarColineales(points, anguloMinimo) {
    if (points.length <= 3) return points;

    const res = [];

    for (let i = 0; i < points.length; i++) {
      const prev = points[(i - 1 + points.length) % points.length];
      const curr = points[i];
      const next = points[(i + 1) % points.length];

      if (cambioAngulo(prev, curr, next) >= anguloMinimo) {
        res.push(curr);
      }
    }

    return res.length >= 3 ? res : points;
  }

  function douglasPeuckerMetros(points, tolerance) {
    if (points.length <= 3) return points;

    let maxDist = 0;
    let index = 0;
    const first = points[0];
    const last = points[points.length - 1];

    for (let i = 1; i < points.length - 1; i++) {
      const d = distanciaPuntoSegmentoMetros(points[i], first, last);
      if (d > maxDist) {
        maxDist = d;
        index = i;
      }
    }

    if (maxDist > tolerance) {
      const left = douglasPeuckerMetros(points.slice(0, index + 1), tolerance);
      const right = douglasPeuckerMetros(points.slice(index), tolerance);
      return left.slice(0, -1).concat(right);
    }

    return [first, last];
  }

  function cambioAngulo(a, b, c) {
    const p1 = map2d.latLngToLayerPoint([a[1], a[0]]);
    const p2 = map2d.latLngToLayerPoint([b[1], b[0]]);
    const p3 = map2d.latLngToLayerPoint([c[1], c[0]]);

    const v1 = { x: p1.x - p2.x, y: p1.y - p2.y };
    const v2 = { x: p3.x - p2.x, y: p3.y - p2.y };

    const dot = v1.x * v2.x + v1.y * v2.y;
    const m1 = Math.hypot(v1.x, v1.y);
    const m2 = Math.hypot(v2.x, v2.y);

    if (!m1 || !m2) return 0;

    let cos = dot / (m1 * m2);
    cos = Math.max(-1, Math.min(1, cos));

    const ang = Math.acos(cos) * 180 / Math.PI;
    return Math.abs(180 - ang);
  }

  function dibujarCotasProfesionales(ring) {
    if (!predioMedidasLayer || !medidasVisibles) return;
    predioMedidasLayer.clearLayers();
    if (!map2d.hasLayer(predioMedidasLayer)) {
      predioMedidasLayer.addTo(map2d);
    }

    const centro = centroide(ring);
    const pts = ring.slice(0, -1);
    const minLado = 0.2;

    for (let i = 0; i < pts.length; i++) {
      const A = L.latLng(pts[i][1], pts[i][0]);
      const B = L.latLng(pts[(i + 1) % pts.length][1], pts[(i + 1) % pts.length][0]);
      const dist = A.distanceTo(B);
      if (dist >= minLado) {
        dibujarLineaCota(A, B, centro, dist);
      }
    }

    for (let i = 0; i < pts.length; i++) {
      const A = L.latLng(pts[i][1], pts[i][0]);
      dibujarVertice(A, "P" + (i + 1), centro);
    }

    predioMedidasLayer.eachLayer(function (layer) {
      if (layer.bringToFront) layer.bringToFront();
    });
  }

  function dibujarLineaCota(A, B, centro, distancia) {
    const pa = map2d.latLngToLayerPoint(A);
    const pb = map2d.latLngToLayerPoint(B);
    const pc = map2d.latLngToLayerPoint(centro);

    const dx = pb.x - pa.x;
    const dy = pb.y - pa.y;
    const len = Math.hypot(dx, dy) || 1;

    let nx = -dy / len;
    let ny = dx / len;

    const mid = L.point((pa.x + pb.x) / 2, (pa.y + pb.y) / 2);
    const haciaCentro = L.point(pc.x - mid.x, pc.y - mid.y);

    if (nx * haciaCentro.x + ny * haciaCentro.y > 0) {
      nx *= -1;
      ny *= -1;
    }

    const offset = 22;

    const A2 = map2d.layerPointToLatLng(L.point(pa.x + nx * offset, pa.y + ny * offset));
    const B2 = map2d.layerPointToLatLng(L.point(pb.x + nx * offset, pb.y + ny * offset));

    // Patillas desde el vértice real hasta la línea de cota
    L.polyline([A, A2], {
      color: COTA_COLOR,
      weight: 1.5,
      opacity: 0.9,
      interactive: false,
    }).addTo(predioMedidasLayer);
    L.polyline([B, B2], {
      color: COTA_COLOR,
      weight: 1.5,
      opacity: 0.9,
      interactive: false,
    }).addTo(predioMedidasLayer);

    L.polyline([A2, B2], {
      color: COTA_COLOR,
      weight: 2.2,
      opacity: 1,
      interactive: false,
    }).addTo(predioMedidasLayer);

    const mid2 = map2d.layerPointToLatLng(L.point(mid.x + nx * offset, mid.y + ny * offset));

    const angulo = normalizarAngulo(Math.atan2(dy, dx) * 180 / Math.PI);

    L.marker(mid2, {
      interactive: false,
      zIndexOffset: 9999,
      icon: L.divIcon({
        className: "predio-medida-label",
        html: `<div class="predio-medida-label-inner" style="transform:rotate(${angulo}deg);">${distancia.toLocaleString("es-MX", {
          minimumFractionDigits: 2,
          maximumFractionDigits: 2,
        })} m</div>`,
      }),
    }).addTo(predioMedidasLayer);
  }

  function dibujarVertice(A, texto, centro) {
    const p = map2d.latLngToLayerPoint(A);
    const c = map2d.latLngToLayerPoint(centro);

    const dx = p.x - c.x;
    const dy = p.y - c.y;
    const len = Math.hypot(dx, dy) || 1;

    const pos = map2d.layerPointToLatLng(
      L.point(p.x + (dx / len) * 15, p.y + (dy / len) * 15)
    );

    L.circleMarker(A, {
      radius: 5,
      color: COTA_COLOR,
      weight: 2,
      fillColor: "#fff",
      fillOpacity: 1,
      interactive: false,
    }).addTo(predioMedidasLayer);

    L.marker(pos, {
      interactive: false,
      zIndexOffset: 9999,
      icon: L.divIcon({
        className: "predio-vertice-label",
        html: `<div class="predio-vertice-label-inner">${texto}</div>`,
      }),
    }).addTo(predioMedidasLayer);
  }

  function normalizarAngulo(a) {
    if (a > 90) a -= 180;
    if (a < -90) a += 180;
    return a;
  }

  function centroide(ring) {
    let lat = 0;
    let lng = 0;
    const n = ring.length - 1;

    for (let i = 0; i < n; i++) {
      lng += ring[i][0];
      lat += ring[i][1];
    }

    return L.latLng(lat / n, lng / n);
  }

  function distanciaCoord(a, b) {
    return L.latLng(a[1], a[0]).distanceTo(L.latLng(b[1], b[0]));
  }

  function distanciaPuntoSegmentoMetros(p, a, b) {
    const px = lngToMeters(p[0]);
    const py = latToMeters(p[1]);
    const ax = lngToMeters(a[0]);
    const ay = latToMeters(a[1]);
    const bx = lngToMeters(b[0]);
    const by = latToMeters(b[1]);

    const dx = bx - ax;
    const dy = by - ay;

    if (dx === 0 && dy === 0) return Math.hypot(px - ax, py - ay);

    let t = ((px - ax) * dx + (py - ay) * dy) / (dx * dx + dy * dy);
    t = Math.max(0, Math.min(1, t));

    return Math.hypot(px - (ax + t * dx), py - (ay + t * dy));
  }

  function lngToMeters(lng) {
    return lng * 111320 * Math.cos(32.64 * Math.PI / 180);
  }

  function latToMeters(lat) {
    return lat * 110540;
  }

  setTimeout(init, 800);

  window.BuscarPredioMxli = {
    buscar: buscarPredioPorClave,
    limpiar: limpiarResultadoAnterior,
    toggleMedidas: setMedidasVisibles,
  };
})();
