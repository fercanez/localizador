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
  let ultimosRingsCotas = null;
  let medidasVisibles = true;
  let claveFiscalActual = "";
  let resultadosAdeudoGen = 0;
  const COTA_COLOR = "#c62828";
  const VERTICE_MIN_M = 0.1;
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
      if (ultimosRingsCotas && medidasVisibles) {
        dibujarCotasProfesionales(ultimosRingsCotas);
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
      <form id="buscadorPredioBox" class="buscador-predio-box" style="position:relative" role="search" action="#" autocomplete="off" novalidate>
        <label for="buscarPredioClave" class="buscador-predio-label">${label}</label>
        <input id="buscarPredioClave" class="buscador-predio-input" type="text" placeholder="${placeholder}"
          enterkeyhint="search" autocomplete="off" autocorrect="off" autocapitalize="characters" spellcheck="false" />
        <button id="btnBuscarPredioClave" class="buscador-predio-btn buscador-predio-btn--buscar" type="submit" aria-label="Buscar" title="Buscar">
          <svg class="buscador-predio-btn__icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M10 3a7 7 0 0 1 5.6 11.2l4.6 4.6-1.4 1.4-4.6-4.6A7 7 0 1 1 10 3zm0 2a5 5 0 1 0 0 10 5 5 0 0 0 0-10z"/></svg>
          <span class="buscador-predio-btn__text">Buscar</span>
        </button>
        <button id="btnToggleMedidasPredio" class="buscador-predio-btn buscador-predio-btn-medidas is-on" type="button" title="Mostrar u ocultar medidas">Medidas</button>
        <div id="buscadorPredioResults" class="buscador-predio-results" hidden>
          <div id="buscadorPredioResultsTitle" class="buscador-predio-results__title"></div>
          <ul id="buscadorPredioResultsList" class="buscador-predio-results__list"></ul>
        </div>
      </form>
    `;

    wireBuscadorEvents();

    if (typeof initMapTopToolbar === "function") {
      initMapTopToolbar();
    }
  }

  function actualizarBotonMedidas() {
    const btn = document.getElementById("btnToggleMedidasPredio");
    if (!btn) return;
    const hayPredio = !!ultimosRingsCotas;
    btn.style.display = hayPredio ? "inline-flex" : "none";
    btn.classList.toggle("is-on", medidasVisibles);
    const texto = medidasVisibles ? "Medidas ON" : "Medidas OFF";
    btn.innerHTML =
      '<svg class="buscador-predio-btn__icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false">' +
      '<path d="M3 15.5 15.5 3 21 8.5 8.5 21zm2.8 0L8.5 18.2 18.2 8.5 15.5 5.8l-1.3 1.3 1.6 1.6-1 1-1.6-1.6-1.3 1.3 1.6 1.6-1 1-1.6-1.6-1.3 1.3 1.6 1.6-1 1-1.6-1.6z"/></svg>' +
      '<span class="buscador-predio-btn__text">' + texto + "</span>";
    btn.setAttribute("aria-label", texto);
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
      if (ultimosRingsCotas) {
        dibujarCotasProfesionales(ultimosRingsCotas);
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

    const form = document.getElementById("buscadorPredioBox");
    if (form && form.tagName === "FORM") {
      form.addEventListener("submit", function (e) {
        e.preventDefault();
        if (typeof input.blur === "function" && window.MXLI_DEVICE && window.MXLI_DEVICE.isTouch()) {
          input.blur();
        }
        ejecutarBusqueda(input.value);
      });
    } else {
      btn.addEventListener("click", function () {
        ejecutarBusqueda(input.value);
      });
      input.addEventListener("keydown", function (e) {
        if (e.key !== "Enter") return;
        e.preventDefault();
        ejecutarBusqueda(input.value);
      });
    }

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
      predioBuscadoLayer.setStyle({ color: color });
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

    const poligonos = obtenerPoligonos(feature.geometry);
    const ringsCotas = ringsParaCotas(poligonos);

    const latlngs = poligonos.map(function (rings) {
      return rings.map(function (ring) {
        return ring.map(p => [p[1], p[0]]);
      });
    });
    const contornos = [];
    latlngs.forEach(function (rings) {
      rings.forEach(function (ring) {
        contornos.push(ring);
      });
    });

    predioRellenoLayer = L.polygon(latlngs, {
      color: PREDIO_COLOR,
      weight: 0,
      fillColor: PREDIO_FILL,
      fillOpacity: 0.10,
      interactive: false,
    }).addTo(map2d);

    predioBuscadoLayer = L.polyline(contornos, {
      color: PREDIO_COLOR,
      weight: 4,
      opacity: 1,
      dashArray: "7,5",
    }).addTo(map2d);

    const bounds = predioBuscadoLayer.getBounds();
    ultimosRingsCotas = ringsCotas;
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
    ultimosRingsCotas = null;
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
  /** Partes del predio como [[anilloExterior, ...huecos], ...], anillos cerrados en [lng, lat]. */
  function obtenerPoligonos(geom) {
    let partes;
    if (geom.type === "Polygon") partes = [geom.coordinates];
    else if (geom.type === "MultiPolygon") partes = geom.coordinates;
    else throw new Error("Geometría no soportada");

    const poligonos = [];
    (partes || []).forEach(function (rings) {
      const cerrados = (rings || [])
        .filter(function (ring) {
          return ring && ring.length >= 3;
        })
        .map(cerrarRing);
      if (cerrados.length) poligonos.push(cerrados);
    });
    if (!poligonos.length) throw new Error("Geometría sin contorno");
    return poligonos;
  }

  function cerrarRing(ring) {
    const r = ring.slice();
    const a = r[0];
    const b = r[r.length - 1];

    if (a[0] !== b[0] || a[1] !== b[1]) r.push([a[0], a[1]]);
    return r;
  }

  /** Anillos a acotar: exterior y huecos de cada parte, con todos sus vértices en su orden. */
  function ringsParaCotas(poligonos) {
    const res = [];
    poligonos.forEach(function (rings) {
      rings.forEach(function (ring, idx) {
        res.push({ ring: ring, hueco: idx > 0, marcas: verticesMarcados(ring, VERTICE_MIN_M) });
      });
    });
    return res;
  }

  /** Vértices con marca P#: sin marca si quedan a menos de minMetros del anterior marcado. */
  function verticesMarcados(ring, minMetros) {
    const pts = ring.slice(0, -1);
    const marcas = [];
    let ultimo = null;
    for (let i = 0; i < pts.length; i++) {
      const ok = !ultimo || distanciaCoord(ultimo, pts[i]) >= minMetros;
      marcas.push(ok);
      if (ok) ultimo = pts[i];
    }
    for (let i = pts.length - 1; i > 0; i--) {
      if (!marcas[i]) continue;
      if (distanciaCoord(pts[i], pts[0]) < minMetros) marcas[i] = false;
      break;
    }
    return marcas;
  }

  function dibujarCotasProfesionales(rings) {
    if (!predioMedidasLayer || !medidasVisibles) return;
    predioMedidasLayer.clearLayers();
    if (!map2d.hasLayer(predioMedidasLayer)) {
      predioMedidasLayer.addTo(map2d);
    }

    const minLado = 0.2;
    let numero = 0;

    rings.forEach(function (item) {
      const pts = item.ring.slice(0, -1);
      const n = pts.length;
      if (n < 3) return;

      const latlngs = pts.map(function (p) {
        return L.latLng(p[1], p[0]);
      });
      const capa = latlngs.map(puntoCapa);
      // Cotas hacia fuera del terreno: fuera del exterior, dentro de los huecos.
      const signo = (areaFirmada(capa) >= 0 ? 1 : -1) * (item.hueco ? -1 : 1);
      const normales = [];
      const lados = [];
      for (let i = 0; i < n; i++) {
        const j = (i + 1) % n;
        normales.push(normalExterior(capa[i], capa[j], signo));
        lados.push(distanciaCoord(pts[i], pts[j]));
      }

      for (let i = 0; i < n; i++) {
        const j = (i + 1) % n;
        if (lados[i] >= minLado && normales[i]) {
          dibujarLineaCota(latlngs[i], latlngs[j], normales[i], lados[i]);
        }
      }

      // Dirección de la etiqueta P# según los lados vecinos significativos (ignora micro-lados).
      function normalVecina(i, paso) {
        for (let k = 0; k < n; k++) {
          const idx = (((i + paso * k) % n) + n) % n;
          if (lados[idx] >= VERTICE_MIN_M && normales[idx]) return normales[idx];
        }
        return null;
      }

      for (let i = 0; i < n; i++) {
        if (item.marcas && !item.marcas[i]) continue;
        numero += 1;
        dibujarVertice(latlngs[i], "P" + numero, direccionVertice(normalVecina(i - 1, -1), normalVecina(i, 1)));
      }
    });

    predioMedidasLayer.eachLayer(function (layer) {
      if (layer.bringToFront) layer.bringToFront();
    });
  }

  /** Punto de capa sin redondear (latLngToLayerPoint redondea a píxel entero). */
  function puntoCapa(latlng) {
    return map2d.project(latlng, map2d.getZoom()).subtract(map2d.getPixelOrigin());
  }

  /** Área con signo en píxeles; positiva = sentido horario en pantalla (y hacia abajo). */
  function areaFirmada(puntos) {
    let s = 0;
    for (let i = 0; i < puntos.length; i++) {
      const a = puntos[i];
      const b = puntos[(i + 1) % puntos.length];
      s += a.x * b.y - b.x * a.y;
    }
    return s / 2;
  }

  function normalExterior(pa, pb, signo) {
    const dx = pb.x - pa.x;
    const dy = pb.y - pa.y;
    const len = Math.hypot(dx, dy);
    if (!len) return null;
    return L.point((signo * dy) / len, (-signo * dx) / len);
  }

  function direccionVertice(nPrev, nNext) {
    if (!nPrev && !nNext) return L.point(0, -1);
    if (!nPrev || !nNext) return nPrev || nNext;
    const sx = nPrev.x + nNext.x;
    const sy = nPrev.y + nNext.y;
    const len = Math.hypot(sx, sy);
    if (len < 0.1) return nNext;
    return L.point(sx / len, sy / len);
  }

  function dibujarLineaCota(A, B, normal, distancia) {
    const pa = puntoCapa(A);
    const pb = puntoCapa(B);

    const dx = pb.x - pa.x;
    const dy = pb.y - pa.y;
    const nx = normal.x;
    const ny = normal.y;

    const mid = L.point((pa.x + pb.x) / 2, (pa.y + pb.y) / 2);

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

  function dibujarVertice(A, texto, dir) {
    const p = puntoCapa(A);

    const pos = map2d.layerPointToLatLng(
      L.point(p.x + dir.x * 15, p.y + dir.y * 15)
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

  /**
   * Distancia [lng, lat] en metros sobre la cuadrícula UTM WGS84 (zona 11 en Mexicali),
   * la misma base que el atributo `superficie` de la capa de predios.
   */
  function distanciaCoord(a, b) {
    const zona = Math.floor(((a[0] + b[0]) / 2 + 180) / 6) + 1;
    const pa = utmXY(a[0], a[1], zona);
    const pb = utmXY(b[0], b[1], zona);
    return Math.hypot(pb.x - pa.x, pb.y - pa.y);
  }

  function utmXY(lng, lat, zona) {
    const a = 6378137;
    const f = 1 / 298.257223563;
    const k0 = 0.9996;
    const e2 = f * (2 - f);
    const ep2 = e2 / (1 - e2);
    const phi = (lat * Math.PI) / 180;
    const dLam = ((lng - (zona * 6 - 183)) * Math.PI) / 180;
    const sin = Math.sin(phi);
    const cos = Math.cos(phi);
    const tan = Math.tan(phi);

    const N = a / Math.sqrt(1 - e2 * sin * sin);
    const T = tan * tan;
    const C = ep2 * cos * cos;
    const A = cos * dLam;
    const M =
      a *
      ((1 - e2 / 4 - (3 * e2 * e2) / 64 - (5 * e2 * e2 * e2) / 256) * phi -
        ((3 * e2) / 8 + (3 * e2 * e2) / 32 + (45 * e2 * e2 * e2) / 1024) * Math.sin(2 * phi) +
        ((15 * e2 * e2) / 256 + (45 * e2 * e2 * e2) / 1024) * Math.sin(4 * phi) -
        ((35 * e2 * e2 * e2) / 3072) * Math.sin(6 * phi));

    const x =
      k0 *
        N *
        (A +
          ((1 - T + C) * Math.pow(A, 3)) / 6 +
          ((5 - 18 * T + T * T + 72 * C - 58 * ep2) * Math.pow(A, 5)) / 120) +
      500000;
    const y =
      k0 *
      (M +
        N *
          tan *
          ((A * A) / 2 +
            ((5 - T + 9 * C + 4 * C * C) * Math.pow(A, 4)) / 24 +
            ((61 - 58 * T + T * T + 600 * C - 330 * ep2) * Math.pow(A, 6)) / 720));
    return { x: x, y: y };
  }

  setTimeout(init, 800);

  window.BuscarPredioMxli = {
    buscar: buscarPredioPorClave,
    limpiar: limpiarResultadoAnterior,
    toggleMedidas: setMedidasVisibles,
    pintarFiscal: pintarPredioFiscal,
  };
})();
