(function () {
  "use strict";

  var cfg = window.MXLI_PLANO || {};
  var pagesEl = document.getElementById("exp-pages");
  var scrollEl = document.getElementById("exp-scroll");
  var busy = document.getElementById("exp-busy");
  var pctEl = document.getElementById("exp-zoom-pct");
  var pdfDoc = null;
  var cadViewer = null;
  var cadHost = null;
  var kind = String(cfg.kind || "pdf").toLowerCase();
  var wmUrl = cfg.watermarkUrl || "img/marca_agua_expediente.png";
  var baseScale = 1.25;
  var viewScale = 1;
  var MIN_SCALE = 0.35;
  var MAX_SCALE = 8;
  var pageNatural = [];
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
    if (cadViewer && typeof cadViewer.SetScale === "function") {
      updatePct();
      return;
    }
    var wraps = pagesEl ? pagesEl.querySelectorAll(".exp-page") : [];
    for (var i = 0; i < wraps.length; i++) {
      var wrap = wraps[i];
      var inner = wrap.querySelector(".exp-page__inner") || wrap;
      var canvas = wrap.querySelector("canvas");
      if (!canvas) continue;
      var nat = pageNatural[i] || { w: canvas.width, h: canvas.height };
      var w = Math.max(1, Math.round(nat.w * viewScale));
      var h = Math.max(1, Math.round(nat.h * viewScale));
      canvas.style.width = w + "px";
      canvas.style.height = h + "px";
      inner.style.width = w + "px";
      inner.style.height = h + "px";
    }
    updatePct();
  }

  function setViewScale(next) {
    viewScale = Math.min(MAX_SCALE, Math.max(MIN_SCALE, next));
    if (cadViewer) {
      try {
        if (typeof cadViewer.SetScale === "function") cadViewer.SetScale(viewScale);
        else if (cadHost) {
          cadHost.style.transform = "scale(" + viewScale + ")";
          cadHost.style.transformOrigin = "top left";
        }
      } catch (e) {}
      updatePct();
      return;
    }
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
    if (!scrollEl || !pageNatural.length) {
      if (cadViewer && typeof cadViewer.FitView === "function") {
        cadViewer.FitView();
        viewScale = 1;
        updatePct();
      }
      return;
    }
    var avail = Math.max(120, scrollEl.clientWidth - 28);
    setViewScale(avail / pageNatural[0].w);
  }

  function fitHeight() {
    if (!scrollEl || !pageNatural.length) {
      if (cadViewer && typeof cadViewer.FitView === "function") {
        cadViewer.FitView();
        viewScale = 1;
        updatePct();
      }
      return;
    }
    var avail = Math.max(120, scrollEl.clientHeight - 28);
    setViewScale(avail / pageNatural[0].h);
  }

  function renderPdfPage(pageNum) {
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
    setBusy(true, "Cargando plano…");
    pdfjsLib
      .getDocument({ url: cfg.fileUrl, withCredentials: true })
      .promise.then(function (doc) {
        pdfDoc = doc;
        pagesEl.innerHTML = "";
        pageNatural = [];
        var chain = Promise.resolve();
        for (var i = 1; i <= doc.numPages; i++) {
          (function (n) {
            chain = chain.then(function () {
              return renderPdfPage(n);
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
          '<p class="exp-msg">No se pudo cargar el PDF. Intente de nuevo o descárguelo.</p>';
      });
  }

  function loadRaster() {
    setBusy(true, "Cargando imagen…");
    var img = new Image();
    img.onload = function () {
      pagesEl.innerHTML = "";
      pageNatural = [{ w: img.naturalWidth, h: img.naturalHeight }];
      var wrap = document.createElement("div");
      wrap.className = "exp-page";
      var inner = document.createElement("div");
      inner.className = "exp-page__inner";
      var canvas = document.createElement("canvas");
      canvas.width = img.naturalWidth;
      canvas.height = img.naturalHeight;
      canvas.getContext("2d").drawImage(img, 0, 0);
      inner.appendChild(canvas);
      addWatermarkLayer(inner);
      wrap.appendChild(inner);
      pagesEl.appendChild(wrap);
      setBusy(false);
      fitWidth();
    };
    img.onerror = function () {
      setBusy(false);
      pagesEl.innerHTML = '<p class="exp-msg">No se pudo cargar la imagen del plano.</p>';
    };
    img.src = cfg.fileUrl;
  }

  function dwgLooksLikeDxf(bytes) {
    var sig = new TextDecoder("latin1").decode(bytes.slice(0, 8)).replace(/^\uFEFF/, "");
    if (/^AC10\d{2}/.test(sig) || /^AC1\.\d/.test(sig)) return false;
    if (window.MxliDxfPreview && typeof window.MxliDxfPreview.looksLikeDxf === "function") {
      return window.MxliDxfPreview.looksLikeDxf(bytes);
    }
    var head = new TextDecoder("latin1").decode(bytes.slice(0, 120));
    return /0\s+SECTION|SECTION/i.test(head);
  }

  function waitLibreDwg(timeoutMs) {
    if (window.MxliLibreDwg && window.MxliLibreDwg.lib) {
      return Promise.resolve(window.MxliLibreDwg);
    }
    if (window.MxliLibreDwg && window.MxliLibreDwg.error) {
      return Promise.reject(window.MxliLibreDwg.error);
    }
    return new Promise(function (resolve, reject) {
      var t = setTimeout(function () {
        reject(new Error("LibreDWG no cargó a tiempo"));
      }, timeoutMs || 90000);
      window.addEventListener(
        "mxli-libredwg-ready",
        function () {
          clearTimeout(t);
          if (window.MxliLibreDwg && window.MxliLibreDwg.lib) resolve(window.MxliLibreDwg);
          else reject((window.MxliLibreDwg && window.MxliLibreDwg.error) || new Error("LibreDWG no disponible"));
        },
        { once: true }
      );
    });
  }

  function showCanvasPage(canvas) {
    cadViewer = null;
    cadHost = null;
    pagesEl.innerHTML = "";
    pageNatural = [{ w: canvas.width, h: canvas.height }];
    var wrap = document.createElement("div");
    wrap.className = "exp-page";
    var inner = document.createElement("div");
    inner.className = "exp-page__inner";
    inner.appendChild(canvas);
    addWatermarkLayer(inner);
    wrap.appendChild(inner);
    pagesEl.appendChild(wrap);
    fitWidth();
  }

  function svgStringToCanvas(svgString) {
    return new Promise(function (resolve, reject) {
      var m = /viewBox\s*=\s*"([^"]+)"/i.exec(svgString);
      var parts = m ? m[1].trim().split(/[\s,]+/).map(Number) : [0, 0, 1600, 1200];
      var vbW = Math.abs(parts[2]) || 0;
      var vbH = Math.abs(parts[3]) || 0;
      if (vbW < 1e-6 || vbH < 1e-6) {
        reject(new Error("SVG vacío"));
        return;
      }
      var maxDim = 2400;
      var scale = Math.min(maxDim / vbW, maxDim / vbH, 6);
      var w = Math.max(400, Math.round(vbW * scale));
      var h = Math.max(300, Math.round(vbH * scale));
      var patched = svgString
        .replace(/width="100%"/i, 'width="' + w + '"')
        .replace(/height="100%"/i, 'height="' + h + '"');
      var blob = new Blob([patched], { type: "image/svg+xml;charset=utf-8" });
      var url = URL.createObjectURL(blob);
      var img = new Image();
      img.onload = function () {
        var canvas = document.createElement("canvas");
        canvas.width = w;
        canvas.height = h;
        var ctx = canvas.getContext("2d");
        ctx.fillStyle = "#ffffff";
        ctx.fillRect(0, 0, w, h);
        ctx.drawImage(img, 0, 0, w, h);
        URL.revokeObjectURL(url);
        resolve(canvas);
      };
      img.onerror = function () {
        URL.revokeObjectURL(url);
        reject(new Error("No se pudo rasterizar el plano CAD"));
      };
      img.src = url;
    });
  }

  function thumbnailToCanvas(thumb) {
    var data = thumb && (thumb.data || thumb);
    if (!data || !data.length) return Promise.reject(new Error("Sin miniatura"));
    var type = (thumb && thumb.type) || 0;
    var mime = "";
    if (data[0] === 0x89 && data[1] === 0x50) mime = "image/png";
    else if (data[0] === 0x42 && data[1] === 0x4d) mime = "image/bmp";
    else if (type === 6) mime = "image/png";
    else if (type === 2) mime = "image/bmp";
    if (!mime) return Promise.reject(new Error("Miniatura CAD no compatible"));
    var blob = new Blob([data], { type: mime });
    var url = URL.createObjectURL(blob);
    return new Promise(function (resolve, reject) {
      var img = new Image();
      img.onload = function () {
        var canvas = document.createElement("canvas");
        canvas.width = Math.max(1, img.naturalWidth);
        canvas.height = Math.max(1, img.naturalHeight);
        var ctx = canvas.getContext("2d");
        ctx.fillStyle = "#ffffff";
        ctx.fillRect(0, 0, canvas.width, canvas.height);
        ctx.drawImage(img, 0, 0);
        URL.revokeObjectURL(url);
        resolve(canvas);
      };
      img.onerror = function () {
        URL.revokeObjectURL(url);
        reject(new Error("No se pudo leer la miniatura CAD"));
      };
      img.src = url;
    });
  }

  function renderDxfCanvas(bytes) {
    if (!window.MxliDxfPreview || typeof window.MxliDxfPreview.renderToCanvas !== "function") {
      return Promise.reject(new Error("Visor DXF no disponible"));
    }
    try {
      return Promise.resolve(window.MxliDxfPreview.renderToCanvas(bytes));
    } catch (err) {
      return Promise.reject(err);
    }
  }

  function renderDwgCanvas(buffer) {
    return waitLibreDwg().then(function (pack) {
      var lib = pack.lib;
      var DwgType = pack.Dwg_File_Type || { DWG: 0 };
      var dwg = lib.dwg_read_data(new Uint8Array(buffer), DwgType.DWG != null ? DwgType.DWG : 0);
      if (!dwg) throw new Error("No se pudo leer el DWG");
      var svg = "";
      var thumb = null;
      try {
        var db = lib.convert(dwg);
        try {
          svg = lib.dwg_to_svg(db) || "";
        } catch (e1) {
          svg = "";
        }
        if (db && db.thumbnailImage) thumb = db.thumbnailImage;
      } catch (e2) {}
      if (!thumb) {
        try {
          thumb = lib.dwg_bmp(dwg);
        } catch (e3) {}
      }
      try {
        lib.dwg_free(dwg);
      } catch (e4) {}
      if (svg && /viewBox\s*=\s*"[^"]*[1-9]/i.test(svg)) {
        return svgStringToCanvas(svg).catch(function () {
          return thumbnailToCanvas(thumb);
        });
      }
      return thumbnailToCanvas(thumb);
    });
  }

  function cadErrorMessage(err) {
    var msg = (err && err.message) || String(err || "Error desconocido");
    msg = String(msg)
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;");
    return "No se pudo previsualizar el DWG/DXF. " + msg;
  }

  function loadCad() {
    setBusy(true, "Cargando plano CAD…");
    fetch(cfg.fileUrl, { credentials: "same-origin" })
      .then(function (res) {
        if (!res.ok) throw new Error("No se pudo obtener el plano CAD (" + res.status + ")");
        return res.arrayBuffer();
      })
      .then(function (buf) {
        var bytes = new Uint8Array(buf);
        if (bytes.length < 32) throw new Error("El archivo llegó vacío");
        if (kind === "dxf" || dwgLooksLikeDxf(bytes)) {
          return renderDxfCanvas(bytes);
        }
        var thumbPromise =
          window.MxliDwgThumb && typeof window.MxliDwgThumb.toCanvas === "function"
            ? window.MxliDwgThumb.toCanvas(bytes).catch(function () {
                return null;
              })
            : Promise.resolve(null);
        return thumbPromise.then(function (thumbCanvas) {
          if (thumbCanvas) {
            showCanvasPage(thumbCanvas);
            setBusy(true, "Mejorando vista del DWG…");
          }
          return renderDwgCanvas(buf)
            .then(function (vectorCanvas) {
              return vectorCanvas;
            })
            .catch(function (err) {
              if (thumbCanvas) return thumbCanvas;
              throw err;
            });
        });
      })
      .then(function (canvas) {
        if (canvas) showCanvasPage(canvas);
        setBusy(false);
      })
      .catch(function (err) {
        setBusy(false);
        if (typeof console !== "undefined" && console.error) console.error("CAD preview", err);
        pagesEl.innerHTML = '<p class="exp-msg">' + cadErrorMessage(err) + "</p>";
        addWatermarkLayer(pagesEl);
      });
  }

  function loadImageAsset(url) {
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

  function safeName(suffix) {
    var base = String(cfg.name || "plano").replace(/\.[^.]+$/, "");
    return base + (suffix || "");
  }

  function downloadOriginal() {
    return fetch(cfg.fileUrl + "&download=1", { credentials: "same-origin" })
      .then(function (res) {
        if (!res.ok) throw new Error("No se pudo descargar el plano");
        return res.blob();
      })
      .then(function (blob) {
        downloadBlob(blob, cfg.name || "plano");
      });
  }

  function canvasToPdfPage(pdf, canvas, pageIndex) {
    var orient = canvas.width >= canvas.height ? "l" : "p";
    var w = canvas.width * 0.75;
    var h = canvas.height * 0.75;
    if (pageIndex === 0) {
      pdf = new ((window.jspdf && window.jspdf.jsPDF) || window.jsPDF)({
        orientation: orient,
        unit: "pt",
        format: [w, h],
        compress: true,
      });
    } else {
      pdf.addPage([w, h], orient);
    }
    var pageW = pdf.internal.pageSize.getWidth();
    var pageH = pdf.internal.pageSize.getHeight();
    pdf.addImage(canvas.toDataURL("image/jpeg", 0.88), "JPEG", 0, 0, pageW, pageH);
    return pdf;
  }

  function downloadWatermarkedPdf(wmImg) {
    var JsPDF = (window.jspdf && window.jspdf.jsPDF) || window.jsPDF;
    if (!JsPDF) return Promise.reject(new Error("Falta el generador PDF"));

    if (kind === "cad") {
      var canvas =
        (pagesEl && pagesEl.querySelector(".exp-page canvas")) ||
        (cadHost && cadHost.querySelector("canvas")) ||
        (cadViewer && cadViewer.GetCanvas && cadViewer.GetCanvas());
      if (!canvas) return Promise.reject(new Error("No hay vista CAD para sellar"));
      var copy = document.createElement("canvas");
      copy.width = canvas.width;
      copy.height = canvas.height;
      var ctx = copy.getContext("2d");
      ctx.fillStyle = "#ffffff";
      ctx.fillRect(0, 0, copy.width, copy.height);
      ctx.drawImage(canvas, 0, 0);
      drawWatermarkOnCanvas(ctx, copy.width, copy.height, wmImg);
      var pdf = canvasToPdfPage(null, copy, 0);
      pdf.save(safeName("-marca-agua.pdf"));
      return Promise.resolve();
    }

    if (kind === "image" || (kind === "pdf" && pdfDoc)) {
      if (kind === "image") {
        var pageCanvas = pagesEl.querySelector("canvas");
        if (!pageCanvas) return Promise.reject(new Error("Imagen no cargada"));
        var baked = document.createElement("canvas");
        baked.width = pageCanvas.width;
        baked.height = pageCanvas.height;
        var bctx = baked.getContext("2d");
        bctx.drawImage(pageCanvas, 0, 0);
        drawWatermarkOnCanvas(bctx, baked.width, baked.height, wmImg);
        canvasToPdfPage(null, baked, 0).save(safeName("-marca-agua.pdf"));
        return Promise.resolve();
      }
    }

    if (!pdfDoc) return Promise.reject(new Error("PDF no cargado"));
    var renderScale = 1.5;
    var chain = Promise.resolve();
    var pdfOut = null;
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
                pdfOut = canvasToPdfPage(pdfOut, canvas, n - 1);
              });
          });
        });
      })(i);
    }
    return chain.then(function () {
      pdfOut.save(safeName("-marca-agua.pdf"));
    });
  }

  function bakeAndDownload() {
    if (!cfg.canDownload) return;
    setBusy(true, "Preparando descarga…");
    var task;
    if (!isWatermarkActive() || (kind === "cad" && !isWatermarkActive())) {
      task = downloadOriginal();
    } else if (!isWatermarkActive()) {
      task = downloadOriginal();
    } else {
      task = loadImageAsset(wmUrl)
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

  function snapshotCadForPrint() {
    if (pagesEl && pagesEl.querySelector(".exp-page canvas") && !cadViewer) return true;
    var canvas =
      (cadHost && cadHost.querySelector("canvas")) ||
      (cadViewer && cadViewer.GetCanvas && cadViewer.GetCanvas());
    if (!canvas) return false;
    pagesEl.innerHTML = "";
    var wrap = document.createElement("div");
    wrap.className = "exp-page";
    var inner = document.createElement("div");
    inner.className = "exp-page__inner";
    var copy = document.createElement("canvas");
    copy.width = canvas.width;
    copy.height = canvas.height;
    copy.getContext("2d").drawImage(canvas, 0, 0);
    pageNatural = [{ w: copy.width, h: copy.height }];
    inner.appendChild(copy);
    addWatermarkLayer(inner);
    wrap.appendChild(inner);
    pagesEl.appendChild(wrap);
    cadViewer = null;
    cadHost = null;
    return true;
  }

  function doPrint() {
    if (!cfg.canPrint) return;
    if (kind === "cad") {
      snapshotCadForPrint();
    }
    preparePrintLayout();
    setTimeout(function () {
      window.print();
    }, 80);
  }

  window.addEventListener("beforeprint", preparePrintLayout);
  window.addEventListener("afterprint", restoreScreenLayout);

  var zoomOut = document.getElementById("exp-zoom-out");
  var zoomIn = document.getElementById("exp-zoom-in");
  var zoomFitW = document.getElementById("exp-zoom-fitw");
  var zoomFitH = document.getElementById("exp-zoom-fith");
  if (zoomOut) zoomOut.addEventListener("click", function () { zoomCentered(viewScale / 1.2); });
  if (zoomIn) zoomIn.addEventListener("click", function () { zoomCentered(viewScale * 1.2); });
  if (zoomFitW) zoomFitW.addEventListener("click", fitWidth);
  if (zoomFitH) zoomFitH.addEventListener("click", fitHeight);

  var toggleBtn = document.getElementById("exp-toggle-wm");
  if (toggleBtn && cfg.canToggle) {
    toggleBtn.addEventListener("click", function () {
      var wantOff = document.body.classList.contains("wm-on");
      fetch("api/planos-meta.php?action=toggle_wm", {
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
          toggleBtn.textContent = activa ? "Quitar marca de agua" : "Poner marca de agua";
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
      if (key === "s" && !cfg.canDownload) e.preventDefault();
    }
    if (key === "+" || key === "=") zoomCentered(viewScale * 1.2);
    if (key === "-") zoomCentered(viewScale / 1.2);
  });

  bindPanAndWheel();

  if (kind === "image") loadRaster();
  else if (kind === "cad") loadCad();
  else loadPdf();
})();
