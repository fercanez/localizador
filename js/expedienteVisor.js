(function () {
  "use strict";

  var cfg = window.MXLI_EXPEDIENTE || {};
  var pagesEl = document.getElementById("exp-pages");
  var scrollEl = document.getElementById("exp-scroll");
  var busy = document.getElementById("exp-busy");
  var pctEl = document.getElementById("exp-zoom-pct");
  var pdfDoc = null;
  var wmUrl = cfg.watermarkUrl || "img/marca_agua_expediente.png";
  var baseScale = 1.25;
  var viewScale = 1;
  var MIN_SCALE = 0.35;
  var MAX_SCALE = 8;
  var pageNatural = []; // {w,h} canvas pixel size per page wrap
  var pan = { on: false, x: 0, y: 0, sl: 0, st: 0, id: null };

  function setBusy(on, text) {
    if (!busy) return;
    busy.textContent = text || "Preparando…";
    busy.classList.toggle("show", !!on);
  }

  function addWatermarkLayer(wrap) {
    var wm = document.createElement("div");
    wm.className = "exp-wm";
    wm.style.backgroundImage = 'url("' + wmUrl + '")';
    wrap.appendChild(wm);
  }

  function updatePct() {
    if (pctEl) pctEl.textContent = Math.round(viewScale * 100) + "%";
  }

  function applyViewScale() {
    var wraps = pagesEl ? pagesEl.querySelectorAll(".exp-page") : [];
    for (var i = 0; i < wraps.length; i++) {
      var wrap = wraps[i];
      var inner = wrap.querySelector(".exp-page__inner") || wrap;
      var canvas = wrap.querySelector("canvas");
      if (!canvas) continue;
      var nat = pageNatural[i] || {
        w: canvas.width,
        h: canvas.height,
      };
      var w = Math.max(1, Math.round(nat.w * viewScale));
      var h = Math.max(1, Math.round(nat.h * viewScale));
      canvas.style.width = w + "px";
      canvas.style.height = h + "px";
      canvas.style.maxWidth = "";
      canvas.style.maxHeight = "";
      wrap.style.width = "";
      wrap.style.height = "";
      inner.style.width = w + "px";
      inner.style.height = h + "px";
    }
    updatePct();
  }

  function setViewScale(next) {
    viewScale = Math.min(MAX_SCALE, Math.max(MIN_SCALE, next));
    applyViewScale();
  }

  function zoomAtClient(clientX, clientY, nextScale) {
    if (!scrollEl) {
      setViewScale(nextScale);
      return;
    }
    var rect = scrollEl.getBoundingClientRect();
    var beforeX = scrollEl.scrollLeft + (clientX - rect.left);
    var beforeY = scrollEl.scrollTop + (clientY - rect.top);
    var prev = viewScale || 1;
    setViewScale(nextScale);
    var ratio = (viewScale || 1) / prev;
    if (!isFinite(ratio) || ratio <= 0) return;
    scrollEl.scrollLeft = beforeX * ratio - (clientX - rect.left);
    scrollEl.scrollTop = beforeY * ratio - (clientY - rect.top);
  }

  function zoomCentered(nextScale) {
    if (!scrollEl) {
      setViewScale(nextScale);
      return;
    }
    var rect = scrollEl.getBoundingClientRect();
    zoomAtClient(rect.left + scrollEl.clientWidth / 2, rect.top + scrollEl.clientHeight / 2, nextScale);
  }

  function bindPanAndWheel() {
    if (!scrollEl) return;
    scrollEl.addEventListener("pointerdown", function (e) {
      if (e.button != null && e.button !== 0) return;
      if (e.target && e.target.closest && e.target.closest("button, a, input, .exp-bar")) return;
      pan.on = true;
      pan.id = e.pointerId;
      pan.x = e.clientX;
      pan.y = e.clientY;
      pan.sl = scrollEl.scrollLeft;
      pan.st = scrollEl.scrollTop;
      scrollEl.classList.add("is-panning");
      try {
        scrollEl.setPointerCapture(e.pointerId);
      } catch (err) {}
      e.preventDefault();
    });
    scrollEl.addEventListener("pointermove", function (e) {
      if (!pan.on || (pan.id != null && e.pointerId !== pan.id)) return;
      scrollEl.scrollLeft = pan.sl - (e.clientX - pan.x);
      scrollEl.scrollTop = pan.st - (e.clientY - pan.y);
    });
    function endPan(e) {
      if (pan.id != null && e && e.pointerId != null && e.pointerId !== pan.id) return;
      pan.on = false;
      pan.id = null;
      scrollEl.classList.remove("is-panning");
    }
    scrollEl.addEventListener("pointerup", endPan);
    scrollEl.addEventListener("pointercancel", endPan);
    scrollEl.addEventListener("wheel", function (e) {
      if (!(e.ctrlKey || e.metaKey)) return;
      e.preventDefault();
      zoomAtClient(e.clientX, e.clientY, viewScale * (e.deltaY < 0 ? 1.12 : 1 / 1.12));
    }, { passive: false });
  }

  function fitWidth() {
    if (!scrollEl || !pageNatural.length) return;
    var avail = Math.max(120, scrollEl.clientWidth - 28);
    setViewScale(avail / pageNatural[0].w);
  }

  function fitHeight() {
    if (!scrollEl || !pageNatural.length) return;
    var avail = Math.max(120, scrollEl.clientHeight - 28);
    setViewScale(avail / pageNatural[0].h);
  }

  function renderPage(pageNum) {
    return pdfDoc.getPage(pageNum).then(function (page) {
      var viewport = page.getViewport({ scale: baseScale });
      var wrap = document.createElement("div");
      wrap.className = "exp-page";
      var inner = document.createElement("div");
      inner.className = "exp-page__inner";
      var canvas = document.createElement("canvas");
      var ctx = canvas.getContext("2d");
      canvas.width = viewport.width;
      canvas.height = viewport.height;
      pageNatural[pageNum - 1] = { w: viewport.width, h: viewport.height };
      inner.appendChild(canvas);
      addWatermarkLayer(inner);
      wrap.appendChild(inner);
      pagesEl.appendChild(wrap);
      return page.render({ canvasContext: ctx, viewport: viewport }).promise;
    });
  }

  function loadPdf() {
    if (!window.pdfjsLib) {
      pagesEl.innerHTML = '<p class="exp-msg">No se pudo cargar el visor PDF.</p>';
      return;
    }
    pdfjsLib.GlobalWorkerOptions.workerSrc =
      "https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js";

    setBusy(true, "Cargando expediente…");
    pdfjsLib
      .getDocument({ url: cfg.pdfUrl, withCredentials: true })
      .promise.then(function (doc) {
        pdfDoc = doc;
        pagesEl.innerHTML = "";
        pageNatural = [];
        var chain = Promise.resolve();
        for (var i = 1; i <= doc.numPages; i++) {
          (function (n) {
            chain = chain.then(function () {
              return renderPage(n);
            });
          })(i);
        }
        return chain;
      })
      .then(function () {
        setBusy(false);
        fitWidth();
      })
      .catch(function () {
        setBusy(false);
        pagesEl.innerHTML =
          '<p class="exp-msg">No se pudo cargar el expediente. Verifique la clave o intente más tarde.</p>';
      });
  }

  function loadImage(url) {
    return new Promise(function (resolve, reject) {
      var img = new Image();
      img.crossOrigin = "anonymous";
      img.onload = function () {
        resolve(img);
      };
      img.onerror = function () {
        reject(new Error("No se pudo cargar la marca de agua"));
      };
      img.src = url;
    });
  }

  function isWatermarkActive() {
    if (!cfg.canToggle && cfg.wm) return true;
    return document.body.classList.contains("wm-on");
  }

  function drawWatermarkOnCanvas(ctx, width, height, wmImg) {
    if (!wmImg) return;
    var tileW = Math.max(180, Math.round(width * 0.42));
    var ratio = wmImg.naturalHeight / Math.max(1, wmImg.naturalWidth);
    var tileH = Math.max(120, Math.round(tileW * ratio));
    ctx.save();
    ctx.globalAlpha = 0.28;
    ctx.globalCompositeOperation = "multiply";
    for (var y = -tileH * 0.2; y < height + tileH; y += tileH * 0.85) {
      for (var x = -tileW * 0.2; x < width + tileW; x += tileW * 0.85) {
        ctx.drawImage(wmImg, x, y, tileW, tileH);
      }
    }
    ctx.restore();
  }

  function downloadBlob(blob, filename) {
    var url = URL.createObjectURL(blob);
    var a = document.createElement("a");
    a.href = url;
    a.download = filename;
    document.body.appendChild(a);
    a.click();
    a.remove();
    setTimeout(function () {
      URL.revokeObjectURL(url);
    }, 1500);
  }

  function downloadOriginalPdf() {
    return fetch(cfg.pdfUrl + "&download=1", { credentials: "same-origin" })
      .then(function (res) {
        if (!res.ok) throw new Error("No se pudo descargar el expediente");
        return res.blob();
      })
      .then(function (blob) {
        downloadBlob(blob, "expediente-" + (cfg.clave || "doc") + ".pdf");
      });
  }

  function downloadWatermarkedPdf(wmImg) {
    if (!pdfDoc) return Promise.reject(new Error("PDF no cargado"));
    var JsPDF = (window.jspdf && window.jspdf.jsPDF) || window.jsPDF;
    if (!JsPDF) return Promise.reject(new Error("Falta el generador PDF"));

    var renderScale = 1.5;
    var chain = Promise.resolve();
    var pdf = null;

    function addPageFromCanvas(canvas, pageIndex) {
      var wPt = (canvas.width * 72) / 96;
      var hPt = (canvas.height * 72) / 96;
      // Use px as unit for simplicity matching canvas aspect
      var orient = canvas.width >= canvas.height ? "l" : "p";
      if (pageIndex === 0) {
        pdf = new JsPDF({
          orientation: orient,
          unit: "pt",
          format: [canvas.width * 0.75, canvas.height * 0.75],
          compress: true,
        });
      } else {
        pdf.addPage([canvas.width * 0.75, canvas.height * 0.75], orient);
      }
      var pageW = pdf.internal.pageSize.getWidth();
      var pageH = pdf.internal.pageSize.getHeight();
      var dataUrl = canvas.toDataURL("image/jpeg", 0.88);
      pdf.addImage(dataUrl, "JPEG", 0, 0, pageW, pageH);
    }

    for (var i = 1; i <= pdfDoc.numPages; i++) {
      (function (n) {
        chain = chain.then(function () {
          return pdfDoc.getPage(n).then(function (page) {
            var viewport = page.getViewport({ scale: renderScale });
            var canvas = document.createElement("canvas");
            canvas.width = viewport.width;
            canvas.height = viewport.height;
            var ctx = canvas.getContext("2d");
            return page
              .render({ canvasContext: ctx, viewport: viewport })
              .promise.then(function () {
                drawWatermarkOnCanvas(ctx, canvas.width, canvas.height, wmImg);
                addPageFromCanvas(canvas, n - 1);
              });
          });
        });
      })(i);
    }

    return chain.then(function () {
      var filename =
        "expediente-" + (cfg.clave || "doc") + "-marca-agua.pdf";
      pdf.save(filename);
    });
  }

  function bakeAndDownload() {
    if (!cfg.canDownload) return;
    setBusy(true, "Preparando descarga…");

    var task;
    if (!isWatermarkActive()) {
      task = downloadOriginalPdf();
    } else {
      task = loadImage(wmUrl)
        .catch(function () {
          return null;
        })
        .then(function (img) {
          return downloadWatermarkedPdf(img);
        });
    }

    task
      .catch(function (err) {
        window.alert((err && err.message) || "No se pudo descargar");
      })
      .then(function () {
        setBusy(false);
      });
  }

  function preparePrintLayout() {
    // Quitar tamaños fijos del zoom en pantalla: el CSS de impresión
    // escala cada página (contain) a una sola hoja del papel elegido.
    var wraps = pagesEl ? pagesEl.querySelectorAll(".exp-page") : [];
    for (var i = 0; i < wraps.length; i++) {
      var wrap = wraps[i];
      var inner = wrap.querySelector(".exp-page__inner");
      var canvas = wrap.querySelector("canvas");
      wrap.style.width = "";
      wrap.style.height = "";
      wrap.style.margin = "0";
      if (inner) {
        inner.style.width = "";
        inner.style.height = "";
      }
      if (canvas) {
        canvas.style.width = "auto";
        canvas.style.height = "auto";
        canvas.style.maxWidth = "100vw";
        canvas.style.maxHeight = "100vh";
      }
    }
    document.body.classList.add("exp-printing");
  }

  function restoreScreenLayout() {
    document.body.classList.remove("exp-printing");
    var wraps = pagesEl ? pagesEl.querySelectorAll(".exp-page") : [];
    for (var i = 0; i < wraps.length; i++) {
      wraps[i].style.margin = "";
    }
    applyViewScale();
  }

  function doPrint() {
    if (!cfg.canPrint) return;
    preparePrintLayout();
    setTimeout(function () {
      window.print();
    }, 50);
  }

  window.addEventListener("beforeprint", preparePrintLayout);
  window.addEventListener("afterprint", restoreScreenLayout);

  var zoomOut = document.getElementById("exp-zoom-out");
  var zoomIn = document.getElementById("exp-zoom-in");
  var zoomFitW = document.getElementById("exp-zoom-fitw");
  var zoomFitH = document.getElementById("exp-zoom-fith");

  if (zoomOut) {
    zoomOut.addEventListener("click", function () {
      zoomCentered(viewScale / 1.2);
    });
  }
  if (zoomIn) {
    zoomIn.addEventListener("click", function () {
      zoomCentered(viewScale * 1.2);
    });
  }
  if (zoomFitW) {
    zoomFitW.addEventListener("click", fitWidth);
  }
  if (zoomFitH) {
    zoomFitH.addEventListener("click", fitHeight);
  }

  var toggleBtn = document.getElementById("exp-toggle-wm");
  if (toggleBtn && cfg.canToggle) {
    toggleBtn.addEventListener("click", function () {
      var wantOff = document.body.classList.contains("wm-on");
      fetch("api/expediente-meta.php?action=toggle_wm", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        credentials: "same-origin",
        body: JSON.stringify({ off: wantOff }),
      })
        .then(function (res) {
          return res.json();
        })
        .then(function (data) {
          if (!data || !data.ok) return;
          var activa = !!(data.flags && data.flags.activa);
          document.body.classList.toggle("wm-on", activa);
          toggleBtn.textContent = activa
            ? "Quitar marca de agua"
            : "Poner marca de agua";
        });
    });
  }

  var dlBtn = document.getElementById("exp-download");
  if (dlBtn) {
    dlBtn.addEventListener("click", bakeAndDownload);
    if (!cfg.canDownload) dlBtn.disabled = true;
  }

  var printBtn = document.getElementById("exp-print");
  if (printBtn) {
    printBtn.addEventListener("click", doPrint);
    if (!cfg.canPrint) printBtn.disabled = true;
  }

  document.addEventListener("keydown", function (e) {
    var key = String(e.key || "").toLowerCase();
    if ((e.ctrlKey || e.metaKey) && (key === "p" || key === "s")) {
      if (key === "p") {
        if (!cfg.canPrint) {
          e.preventDefault();
          return;
        }
        e.preventDefault();
        doPrint();
        return;
      }
      if (key === "s" && !cfg.canDownload) {
        e.preventDefault();
      }
    }
    if (key === "+" || key === "=") zoomCentered(viewScale * 1.2);
    if (key === "-") zoomCentered(viewScale / 1.2);
  });

  bindPanAndWheel();
  loadPdf();
})();
