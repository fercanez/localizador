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
  let claveFiscalActual = "";
  let resultadosAdeudoGen = 0;
  const COTA_COLOR = "#c62828";
  const PREDIO_COLOR = "#0057ff";
  const PREDIO_FILL = "#00a2ff";
  const FISCAL_OK_COLOR = "#15803d";
  const FISCAL_OK_FILL = "#16a34a";
  const FISCAL_DEBT_COLOR = "#c62828";
  const FISCAL_DEBT_FILL = "#dc2626";
  const FISCAL_ASEO_COLOR = "#c2410c";
  const FISCAL_ASEO_FILL = "#ea580c";

  var initTries = 0;
  var INIT_MAX = 40;

  function getMap() {
    if (window.map2d) return window.map2d;
    try {
      if (typeof map2d !== "undefined" && map2d) return map2d;
    } catch (e) {}
    return null;
  }

  function init() {
    if (typeof window.mxliCan === "function" && !window.mxliCan("map.search")) {
      return;
    }
    if (typeof window.mxliCanLayer === "function" && !window.mxliCanLayer("predios_mexicali_2025")) {
      return;
    }
    var map = getMap();
    if (typeof L === "undefined" || !map) {
      if (initTries++ < INIT_MAX) setTimeout(init, 400);
      return;
    }

    insertarEstilos();
    predioMedidasLayer = L.layerGroup().addTo(map);
    crearBuscadorPredio();

    window.addEventListener("mxli-predial-adeudo", function (ev) {
      var data = ev && ev.detail;
      if (!data) return;
      var est =
        typeof window.mxliPredialEstatus === "function"
          ? window.mxliPredialEstatus(data)
          : "";
      pintarPredioFiscal(est, data.clave_catastral);
    });

    map.on("zoomend", function () {
      if (ultimoRingCotas && ultimoRingCotas.length && medidasVisibles) {
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
        width:220px;
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
        flex-direction:column;
        align-items:flex-start;
        gap:2px;
        padding:8px 10px;
        border-radius:8px;
        border:0;
        background:#fff;
        cursor:pointer;
        font-size:13px;
        font-weight:800;
        color:#1f1f1f;
      }

      .buscador-predio-results__item-main {
        font-weight:800;
      }

      .buscador-predio-results__item-sub {
        font-size:11px;
        font-weight:600;
        color:#6a5560;
      }

      .buscador-predio-results__item:hover {
        background:#f7f0f2;
      }

      .buscador-predio-results__fiscal {
        font-size:10px;
        font-weight:800;
        letter-spacing:.02em;
        margin-top:2px;
        color:#6a5560;
      }

      .buscador-predio-results__item--ok {
        background:#e8f8ec;
      }

      .buscador-predio-results__item--ok:hover {
        background:#d4f0db;
      }

      .buscador-predio-results__item--debt {
        background:#fde8e8;
      }

      .buscador-predio-results__item--debt:hover {
        background:#f8d0d0;
      }

      .buscador-predio-results__item--aseo {
        background:#ffedd5;
      }

      .buscador-predio-results__item--aseo:hover {
        background:#fed7aa;
      }

      .buscador-predio-results__item-main--ok,
      .buscador-predio-results__item--ok .buscador-predio-results__fiscal {
        color:#145a32;
      }

      .buscador-predio-results__item-main--debt,
      .buscador-predio-results__item--debt .buscador-predio-results__fiscal {
        color:#8b1a1a;
      }

      .buscador-predio-results__item-main--aseo,
      .buscador-predio-results__item--aseo .buscador-predio-results__fiscal {
        color:#9a3412;
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

    const canPadron =
      typeof window.mxliCanSearchByOwnerName === "function"
        ? window.mxliCanSearchByOwnerName()
        : typeof window.mxliCanViewPadron === "function"
          ? window.mxliCanViewPadron()
          : typeof window.mxliCan === "function" && window.mxliCan("map.padron");
    const label = canPadron
      ? "Buscar predio, dirección o propietario:"
      : "Buscar predio o dirección:";
    const placeholder = canPadron
      ? "CLAVE, CALLE Y NÚMERO O NOMBRE"
      : "CLAVE O CALLE Y NÚMERO";

    mount.innerHTML = `
      <div id="buscadorPredioBox" class="buscador-predio-box" style="position:relative">
        <label for="buscarPredioClave" class="buscador-predio-label">${label}</label>
        <input id="buscarPredioClave" class="buscador-predio-input" type="text" placeholder="${placeholder}" />
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
    const hayPredio = !!(ultimoRingCotas && ultimoRingCotas.length);
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
      if (ultimoRingCotas && ultimoRingCotas.length) {
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
      // Conservar espacio final mientras se escribe (calle / nombre)
      this.value = normalizarBusqueda(this.value, true);
      const value = this.value;
      if (t) clearTimeout(t);
      t = setTimeout(function () {
        const q = String(value || "").trim();
        if (!q) {
          ocultarResultados();
          return;
        }
        if (pareceDireccion(q)) {
          buscarPadronPorDireccion(q);
        } else if (pareceNombre(q) && canViewPadron()) {
          buscarPadronPorNombre(q);
        } else if (esPrefijoListado(limpiarClave(q))) {
          buscarListadoPorPrefijo(limpiarClave(q));
        } else {
          ocultarResultados();
        }
      }, value.trim().length <= 2 ? 400 : 220);
    });

    btn.addEventListener("click", function () {
      ejecutarBusqueda(input.value);
    });

    input.addEventListener("keydown", function (e) {
      if (e.key !== "Enter") return;
      e.preventDefault();
      ejecutarBusqueda(input.value);
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

  function canViewPadron() {
    if (typeof window.mxliCanSearchByOwnerName === "function") {
      return window.mxliCanSearchByOwnerName();
    }
    if (typeof window.mxliCanViewPadron === "function") {
      return window.mxliCanViewPadron();
    }
    if (window.MXLI_USER && window.MXLI_USER.can_view_padron) return true;
    return (
      typeof window.mxliCan === "function" &&
      (window.mxliCan("map.padron") || window.mxliCan("users.manage"))
    );
  }

  function pareceNombre(valor) {
    const v = String(valor || "").trim();
    if (v.length < 3) return false;
    if (/\d/.test(v)) return false;
    if (pareceDireccion(v)) return false;
    return /[A-Za-zÁÉÍÓÚÑáéíóúñ]/.test(v);
  }

  function pareceDireccion(valor) {
    const v = String(valor || "").trim();
    if (v.length < 3) return false;
    // Prefijos típicos de vialidad / colonia
    if (
      /^(CALLE|AV\.?|AVENIDA|BLVD\.?|BOULEVARD|PRIV\.?|PRIVADA|ANDADOR|CERRADA|CIRCUITO|CALZ\.?|CALZADA|CARR\.?|CARRETERA|PROL\.?|PROLONGACION|PROLONGACIÓN|COL\.?|COLONIA)\b/i.test(
        v
      )
    ) {
      return true;
    }
    // Calle + número oficial: "REFORMA 120", "JUAREZ 45A"
    if (/[A-Za-zÁÉÍÓÚÑáéíóúñ]/.test(v) && /\d/.test(v)) {
      // No confundir claves tipo ST312014 (sin espacios)
      const compact = v.replace(/\s+/g, "");
      if (!/\s/.test(v) && /^[A-Z0-9]{4,20}$/i.test(compact)) {
        return false;
      }
      return true;
    }
    return false;
  }

  function normalizarBusqueda(valor, alEscribir) {
    let v = String(valor || "").toUpperCase();
    const trailingSpace = alEscribir && /\s$/.test(v);
    const trimmed = v.trim();

    // Clave catastral compacta (sin espacios): ST312014
    const compact = trimmed.replace(/\s+/g, "");
    const pareceClaveCompacta =
      trimmed.length > 0 &&
      !/\s/.test(trimmed) &&
      /^[A-Z0-9]+$/.test(compact) &&
      !pareceDireccion(trimmed) &&
      !pareceNombre(trimmed);

    if (pareceClaveCompacta) {
      return compact;
    }

    // Dirección / nombre / texto con espacios: conservar espacios
    v = v.replace(/\s+/g, " ");
    if (alEscribir) {
      v = v.replace(/^\s+/, "");
      if (trailingSpace && !/\s$/.test(v)) v += " ";
      return v;
    }
    return v.trim();
  }

  function ejecutarBusqueda(valorOriginal) {
    const crudo = normalizarBusqueda(valorOriginal, false);
    if (pareceDireccion(crudo)) {
      buscarPadronPorDireccion(crudo);
      return;
    }
    if (pareceNombre(crudo)) {
      if (!canViewPadron()) {
        window.alert(
          "Para buscar por nombre de propietario se requiere permiso de padrón.\nPuede buscar por clave o por dirección (calle y número)."
        );
        return;
      }
      buscarPadronPorNombre(crudo);
      return;
    }
    const value = limpiarClave(crudo);
    if (esPrefijoListado(value)) {
      buscarListadoPorPrefijo(value);
      return;
    }
    buscarPredioPorClave(value);
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
    resultadosAdeudoGen += 1;
  }

  function renderResultados(prefix, claves, meta) {
    const box = document.getElementById("buscadorPredioResults");
    const list = document.getElementById("buscadorPredioResultsList");
    const title = document.getElementById("buscadorPredioResultsTitle");
    if (!box || !list || !title) return;

    list.innerHTML = "";
    const total = (claves || []).length;
    const esPadron =
      String(prefix || "").indexOf("Propietario") === 0 ||
      String(prefix || "").indexOf("Dirección") === 0 ||
      (total > 0 && claves[0] && typeof claves[0] === "object");
    const etiqueta = esPadron ? prefix : etiquetaPrefijoBusqueda(prefix);
    let titulo = etiqueta + ": " + total + (esPadron ? " coincidencia(s)" : " predio(s)");
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
      if (clave && typeof clave === "object") {
        const claveTxt = clave.clave || "";
        btn.setAttribute("data-clave", claveTxt);
        const domicilio =
          clave.domicilio ||
          [clave.calle, clave.numero_oficial, clave.letra]
            .filter(Boolean)
            .join(" ")
            .trim();
        const colonia = clave.colonia || "";
        const propietario = canViewPadron() ? clave.propietario || "" : "";

        const main = document.createElement("span");
        main.className = "buscador-predio-results__item-main";
        main.textContent = claveTxt || "Sin clave";

        const sub = document.createElement("span");
        sub.className = "buscador-predio-results__item-sub";
        const bits = [];
        if (domicilio) bits.push(domicilio);
        if (colonia) bits.push(colonia);
        if (propietario) bits.push(propietario);
        sub.textContent = bits.join(" · ");

        btn.appendChild(main);
        if (bits.length) btn.appendChild(sub);

        btn.addEventListener("click", function () {
          ocultarResultados();
          const input = document.getElementById("buscarPredioClave");
          if (input) input.value = claveTxt;
          buscarPredioPorClave(claveTxt);
        });
      } else {
        btn.textContent = clave;
        btn.setAttribute("data-clave", String(clave || ""));
        btn.addEventListener("click", function () {
          ocultarResultados();
          const input = document.getElementById("buscarPredioClave");
          if (input) input.value = clave;
          buscarPredioPorClave(clave);
        });
      }
      li.appendChild(btn);
      list.appendChild(li);
    });

    box.hidden = total === 0;
    if (esPadron) {
      consultarAdeudosDeResultados(claves || []);
    }
  }

  function consultarAdeudosDeResultados(claves) {
    const gen = ++resultadosAdeudoGen;
    const list = document.getElementById("buscadorPredioResultsList");
    if (!list || typeof window.mxliConsultarPredialAdeudo !== "function") return;
    if (!claves.length || claves.length > 40) return;

    const botones = {};
    Array.prototype.forEach.call(list.querySelectorAll(".buscador-predio-results__item"), function (btn) {
      const clave = String(btn.getAttribute("data-clave") || "").toUpperCase();
      if (clave) botones[clave] = btn;
      if (typeof window.mxliPintarResultadoPredial === "function") {
        window.mxliPintarResultadoPredial(btn, { estatus_consulta: "pendiente" });
      }
    });

    Object.keys(botones).forEach(function (clave) {
      window.mxliConsultarPredialAdeudo(clave).then(function (data) {
        if (gen !== resultadosAdeudoGen) return;
        const btn = botones[clave];
        if (btn && typeof window.mxliPintarResultadoPredial === "function") {
          window.mxliPintarResultadoPredial(btn, data);
        }
      });
    });
  }

  function pintarPredioFiscal(estatus, clave) {
    const key = String(clave || "").trim().toUpperCase();
    if (key && claveFiscalActual && key !== claveFiscalActual) return;
    let color = PREDIO_COLOR;
    let fill = PREDIO_FILL;
    if (estatus === "con_adeudo") {
      color = FISCAL_DEBT_COLOR;
      fill = FISCAL_DEBT_FILL;
    } else if (estatus === "adeudo_aseo") {
      color = FISCAL_ASEO_COLOR;
      fill = FISCAL_ASEO_FILL;
    } else if (estatus === "sin_adeudo") {
      color = FISCAL_OK_COLOR;
      fill = FISCAL_OK_FILL;
    }
    if (predioRellenoLayer && predioRellenoLayer.setStyle) {
      predioRellenoLayer.setStyle({
        color: color,
        fillColor: fill,
        fillOpacity: estatus === "con_adeudo" || estatus === "sin_adeudo" || estatus === "adeudo_aseo" ? 0.18 : 0.10,
      });
    }
    if (predioBuscadoLayer && predioBuscadoLayer.setStyle) {
      predioBuscadoLayer.setStyle({
        color: color,
        fill: false,
        fillOpacity: 0,
        weight: 4,
        dashArray: "7,5",
      });
    }
  }

  async function buscarPadronPorDireccion(direccion) {
    if (typeof window.mxliCan === "function" && !window.mxliCan("map.search")) {
      window.alert("Su rol no tiene permiso para buscar predios.");
      return;
    }
    const q = String(direccion || "").trim();
    if (q.length < 3) {
      window.alert("Capture calle y, si es posible, el número oficial.");
      return;
    }

    const box = document.getElementById("buscadorPredioResults");
    const title = document.getElementById("buscadorPredioResultsTitle");
    if (box) box.hidden = false;
    if (title) title.textContent = "Buscando dirección…";

    try {
      const res = await fetch(
        "api/padron-address.php?direccion=" +
          encodeURIComponent(q) +
          "&limit=40",
        { credentials: "same-origin", cache: "no-store" }
      );
      const data = await res.json();
      if (!res.ok || !data || data.ok === false) {
        throw new Error((data && (data.detail || data.error)) || "Error al buscar dirección.");
      }
      const rows = data.resultados || [];
      renderResultados("Dirección " + q, rows, null);
      if (!rows.length && title) {
        title.textContent = "Sin coincidencias para «" + q + "»";
        if (box) box.hidden = false;
      }
    } catch (err) {
      renderResultados(q, [], null);
      if (title) title.textContent = String(err && err.message ? err.message : "Error");
    }
  }

  async function buscarPadronPorNombre(nombre) {
    if (!canViewPadron()) {
      window.alert("Su rol no tiene permiso para buscar por propietario.");
      return;
    }
    const box = document.getElementById("buscadorPredioResults");
    const title = document.getElementById("buscadorPredioResultsTitle");
    if (box) box.hidden = false;
    if (title) title.textContent = "Buscando propietario…";

    try {
      const res = await fetch(
        "api/padron.php?nombre=" + encodeURIComponent(nombre),
        { credentials: "same-origin", cache: "no-store" }
      );
      const data = await res.json();
      if (!res.ok || !data || data.ok === false) {
        throw new Error((data && (data.detail || data.error)) || "Error al buscar propietario.");
      }
      const rows = data.resultados || [];
      renderResultados("Propietario " + nombre, rows, null);
    } catch (err) {
      renderResultados(nombre, [], null);
      if (title) title.textContent = String(err && err.message ? err.message : "Error");
    }
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

    const geom = feature.geometry;
    if (!geom || !geom.type || !geom.coordinates) {
      alert("El predio no tiene geometría para medir.");
      return;
    }

    const rings = obtenerAnillosExteriores(geom)
      .map(function (ring) {
        return limpiarRingCadastral(ring);
      })
      .filter(function (ring) {
        return ring && ring.length >= 4;
      });

    if (!rings.length) {
      alert("El predio no tiene un polígono válido para medir.");
      return;
    }

    const latlngs = rings.map(ringToLatLngs);
    const fillStyle = {
      color: PREDIO_COLOR,
      weight: 0,
      fillColor: PREDIO_FILL,
      fillOpacity: 0.1,
      interactive: false,
    };
    const lineStyle = {
      color: PREDIO_COLOR,
      weight: 4,
      opacity: 1,
      fill: false,
      fillOpacity: 0,
      dashArray: "7,5",
      interactive: false,
    };

    predioRellenoLayer = L.featureGroup();
    predioBuscadoLayer = L.featureGroup();
    latlngs.forEach(function (ll) {
      L.polygon(ll, fillStyle).addTo(predioRellenoLayer);
      L.polygon(ll, lineStyle).addTo(predioBuscadoLayer);
    });
    predioRellenoLayer.addTo(map2d);
    predioBuscadoLayer.addTo(map2d);

    const bounds = predioBuscadoLayer.getBounds();
    ultimoRingCotas = rings;
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
    if (typeof window.encenderCapaPrediosMapa === "function") {
      window.encenderCapaPrediosMapa();
      return;
    }
    if (typeof window.mxliCanLayer === "function" && !window.mxliCanLayer("predios_mexicali_2025")) {
      return;
    }
    if (typeof predios === "undefined") return;

    const chk = document.getElementById("predios_chkbox");
    const yaVisible =
      (typeof group !== "undefined" && group.hasLayer && group.hasLayer(predios)) ||
      (map2d && map2d.hasLayer && map2d.hasLayer(predios));

    if (yaVisible) {
      if (typeof actualizarCapaActivaConsulta === "function") {
        actualizarCapaActivaConsulta();
      } else {
        window.capaActiva = predios;
      }
      return;
    }

    if (chk && typeof toggleLayer === "function") {
      chk.checked = true;
      toggleLayer("predios_chkbox", predios);
      return;
    }

    if (typeof group !== "undefined" && group.addLayer) group.addLayer(predios);
    else predios.addTo(map2d);
    if (chk) chk.checked = true;
    if (typeof actualizarCapaActivaConsulta === "function") {
      actualizarCapaActivaConsulta();
    } else {
      window.capaActiva = predios;
    }
  }

  function limpiarResultadoAnterior() {
    if (predioBuscadoLayer) map2d.removeLayer(predioBuscadoLayer);
    if (predioRellenoLayer) map2d.removeLayer(predioRellenoLayer);
    if (predioMedidasLayer) predioMedidasLayer.clearLayers();

    predioBuscadoLayer = null;
    predioRellenoLayer = null;
    ultimoRingCotas = null;
    claveFiscalActual = "";
    map2d.closePopup();
    actualizarBotonMedidas();
  }

  function abrirPopupPredio(feature, latlng) {
    const p = feature.properties || {};
    const clave = p.clavecatas || "";
    claveFiscalActual = String(clave || "").trim().toUpperCase();
    const homoclave = clave.substring(0, 2);
    const manzana = p.manzana || clave.substring(2, 5);
    const lote = p.lote || clave.substring(5, 8);
    const superficieRaw = p.superficie || 0;
    const superficie = Number(superficieRaw).toLocaleString("es-MX", {
      minimumFractionDigits: 2,
      maximumFractionDigits: 2,
    });

    let bodyHtml;
    if (canViewPadron() && typeof mxliPredioPadronBodyHtml === "function") {
      bodyHtml = mxliPredioPadronBodyHtml(clave, superficieRaw);
    } else {
      const claveU = String(clave || "").trim().toUpperCase();
      const expedienteHtml =
        typeof window.mxliExpedienteLinkHtml === "function"
          ? window.mxliExpedienteLinkHtml(claveU)
          : typeof window.mxliCan === "function" && window.mxliCan("map.expediente")
            ? '<a href="#" class="mxli-expediente-link" data-expediente-clave="' +
              claveU +
              '" role="button"><span class="mxli-expediente-link__text">Ver Expediente</span></a>'
            : "";
      const badgeHtml =
        typeof window.mxliPredialBadgeHtml === "function"
          ? window.mxliPredialBadgeHtml(null, true)
          : "";
      bodyHtml =
        '<div data-mxli-predial="' + claveU + '">' +
        '<div class="mxli-predial-clave-line"><b>Clave Catastral:</b> ' +
        clave +
        "</div>" +
        '<div class="mxli-predial-badge-host">' +
        badgeHtml +
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
        expedienteHtml +
        "</div>";
    }

    // Usar nodo DOM (no string): así popup.update() no reinstala placeholders "…".
    const wrap = document.createElement("div");
    wrap.className = "mxli-feature-popup";
    wrap.innerHTML =
      '<div class="mxli-feature-popup__head">Información del predio</div>' +
      '<div class="mxli-feature-popup__body">' +
      bodyHtml +
      "</div>";
    const popupOpts =
      typeof mxliPopupOptions === "function"
        ? mxliPopupOptions()
        : window.matchMedia("(max-width: 900px)").matches
          ? {
              maxWidth: 320,
              minWidth: 260,
              maxHeight: 320,
              autoPanPadding: [48, 72],
              className: "mxli-predio-leaflet-popup",
            }
          : {
              maxWidth: 420,
              maxHeight: 440,
              minWidth: 300,
              className: "mxli-predio-leaflet-popup",
            };
    L.popup(popupOpts).setLatLng(latlng).setContent(wrap).openOn(map2d);
    if (canViewPadron() && typeof mxliFillPadronBlock === "function") {
      // Rellenar tras montar el popup en el mapa (mismo nodo vivo).
      setTimeout(function () {
        mxliFillPadronBlock(wrap, clave);
      }, 0);
    } else if (typeof window.mxliFillPredialAdeudo === "function") {
      setTimeout(function () {
        window.mxliFillPredialAdeudo(wrap, clave);
      }, 0);
    }
  }
  function asLngLat(p) {
    return [Number(p[0]), Number(p[1])];
  }

  function abrirRing(ring) {
    const r = (ring || []).map(asLngLat);
    if (r.length >= 2) {
      const a = r[0];
      const b = r[r.length - 1];
      if (a[0] === b[0] && a[1] === b[1]) r.pop();
    }
    return r;
  }

  function cerrarRing(ring) {
    const r = abrirRing(ring);
    if (!r.length) return r;
    r.push([r[0][0], r[0][1]]);
    return r;
  }

  function obtenerAnillosExteriores(geom) {
    if (!geom || !geom.type) return [];
    if (geom.type === "Polygon") {
      return geom.coordinates && geom.coordinates[0] ? [cerrarRing(geom.coordinates[0])] : [];
    }
    if (geom.type === "MultiPolygon") {
      return (geom.coordinates || [])
        .map(function (poly) {
          return poly && poly[0] ? cerrarRing(poly[0]) : null;
        })
        .filter(Boolean);
    }
    return [];
  }

  function ringToLatLngs(ring) {
    return abrirRing(ring).map(function (p) {
      return [p[1], p[0]];
    });
  }

  /**
   * Conserva el contorno catastral. Solo fusiona duplicados y puntos
   * colineales en un único paso (sin iterar, para no recortar esquinas).
   */
  function limpiarRingCadastral(ring) {
    let puntos = abrirRing(ring);
    puntos = eliminarPuntosCortos(puntos, 0.08);
    puntos = eliminarColinealesUnaPasada(puntos, 0.25);
    if (puntos.length < 3) puntos = abrirRing(ring);
    return cerrarRing(puntos);
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

    if (res.length >= 3 && distanciaCoord(res[0], res[res.length - 1]) < minMetros) {
      res.pop();
    }

    return res.length >= 3 ? res : points;
  }

  function eliminarColinealesUnaPasada(points, maxDesvioMetros) {
    if (points.length <= 3) return points.slice();

    const res = [];
    for (let i = 0; i < points.length; i++) {
      const prev = points[(i - 1 + points.length) % points.length];
      const curr = points[i];
      const next = points[(i + 1) % points.length];
      if (distanciaPuntoSegmentoMetros(curr, prev, next) >= maxDesvioMetros) {
        res.push(curr);
      }
    }
    return res.length >= 3 ? res : points;
  }

  function ringsDeCotas(ringsOrRing) {
    if (!ringsOrRing || !ringsOrRing.length) return [];
    if (typeof ringsOrRing[0][0] === "number") return [ringsOrRing];
    return ringsOrRing;
  }

  function ringAPuntosCapa(ring) {
    const pts = abrirRing(ring);
    return pts.map(function (p) {
      return map2d.latLngToLayerPoint(L.latLng(p[1], p[0]));
    });
  }

  function puntoEnRingLayer(pt, layerPts) {
    let inside = false;
    for (let i = 0, j = layerPts.length - 1; i < layerPts.length; j = i++) {
      const xi = layerPts[i].x;
      const yi = layerPts[i].y;
      const xj = layerPts[j].x;
      const yj = layerPts[j].y;
      const cruce =
        yi > pt.y !== yj > pt.y &&
        pt.x < ((xj - xi) * (pt.y - yi)) / ((yj - yi) || 1e-9) + xi;
      if (cruce) inside = !inside;
    }
    return inside;
  }

  function dibujarCotasProfesionales(ringsOrRing) {
    if (!predioMedidasLayer || !medidasVisibles) return;
    predioMedidasLayer.clearLayers();
    if (!map2d.hasLayer(predioMedidasLayer)) {
      predioMedidasLayer.addTo(map2d);
    }

    const rings = ringsDeCotas(ringsOrRing);
    const minLado = 0.2;
    let nVertice = 0;

    rings.forEach(function (ring) {
      const pts = abrirRing(ring);
      if (pts.length < 3) return;
      const layerPts = ringAPuntosCapa(pts);

      for (let i = 0; i < pts.length; i++) {
        const A = L.latLng(pts[i][1], pts[i][0]);
        const B = L.latLng(pts[(i + 1) % pts.length][1], pts[(i + 1) % pts.length][0]);
        const dist = A.distanceTo(B);
        if (dist >= minLado) {
          dibujarLineaCota(A, B, layerPts, dist);
        }
      }

      for (let i = 0; i < pts.length; i++) {
        nVertice += 1;
        const A = L.latLng(pts[i][1], pts[i][0]);
        dibujarVertice(A, "P" + nVertice, layerPts);
      }
    });

    predioMedidasLayer.eachLayer(function (layer) {
      if (layer.bringToFront) layer.bringToFront();
    });
  }

  function normalExterna(pa, pb, layerPts) {
    const dx = pb.x - pa.x;
    const dy = pb.y - pa.y;
    const len = Math.hypot(dx, dy) || 1;
    let nx = -dy / len;
    let ny = dx / len;
    const mid = L.point((pa.x + pb.x) / 2, (pa.y + pb.y) / 2);
    const prueba = L.point(mid.x + nx * 8, mid.y + ny * 8);
    if (puntoEnRingLayer(prueba, layerPts)) {
      nx *= -1;
      ny *= -1;
    }
    return { nx: nx, ny: ny, mid: mid, dx: dx, dy: dy };
  }

  function dibujarLineaCota(A, B, layerPts, distancia) {
    const pa = map2d.latLngToLayerPoint(A);
    const pb = map2d.latLngToLayerPoint(B);
    const n = normalExterna(pa, pb, layerPts);
    const offset = 22;

    const A2 = map2d.layerPointToLatLng(L.point(pa.x + n.nx * offset, pa.y + n.ny * offset));
    const B2 = map2d.layerPointToLatLng(L.point(pb.x + n.nx * offset, pb.y + n.ny * offset));

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

    const mid2 = map2d.layerPointToLatLng(
      L.point(n.mid.x + n.nx * offset, n.mid.y + n.ny * offset)
    );

    const angulo = normalizarAngulo((Math.atan2(n.dy, n.dx) * 180) / Math.PI);

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

  function dibujarVertice(A, texto, layerPts) {
    const p = map2d.latLngToLayerPoint(A);
    const dirs = [
      [1, 0],
      [-1, 0],
      [0, 1],
      [0, -1],
      [1, 1],
      [-1, 1],
      [1, -1],
      [-1, -1],
    ];
    let dx = 1;
    let dy = -1;
    for (let k = 0; k < dirs.length; k++) {
      const test = L.point(p.x + dirs[k][0] * 12, p.y + dirs[k][1] * 12);
      if (!puntoEnRingLayer(test, layerPts)) {
        dx = dirs[k][0];
        dy = dirs[k][1];
        break;
      }
    }
    const len = Math.hypot(dx, dy) || 1;
    const pos = map2d.layerPointToLatLng(
      L.point(p.x + (dx / len) * 16, p.y + (dy / len) * 16)
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
    pintarFiscal: pintarPredioFiscal,
  };
})();
