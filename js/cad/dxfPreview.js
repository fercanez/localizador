(function (global) {
  "use strict";

  var MAX_CANVAS = 2400;

  function decodeBytes(bytes) {
    try {
      var utf8 = new TextDecoder("utf-8").decode(bytes);
      if (utf8.indexOf("\uFFFD") === -1) return utf8;
    } catch (e) {}
    return new TextDecoder("latin1").decode(bytes);
  }

  function parsePairs(text) {
    var raw = String(text || "").replace(/\r\n/g, "\n").replace(/\r/g, "\n").split("\n");
    var pairs = [];
    for (var i = 0; i < raw.length - 1; i += 2) {
      var code = parseInt(String(raw[i]).trim(), 10);
      if (isNaN(code)) {
        i -= 1;
        continue;
      }
      pairs.push({ c: code, v: raw[i + 1] });
    }
    return pairs;
  }

  function num(v) {
    var n = parseFloat(String(v).replace(",", "."));
    return isFinite(n) ? n : 0;
  }

  function collectEntities(pairs, start, endCodes) {
    var entities = [];
    var i = start;
    var current = null;
    function flush() {
      if (current) entities.push(current);
      current = null;
    }
    for (; i < pairs.length; i++) {
      var p = pairs[i];
      if (p.c === 0) {
        var type = String(p.v || "").trim().toUpperCase();
        if (endCodes[type]) {
          flush();
          return { entities: entities, index: i };
        }
        flush();
        current = { type: type, pts: [], bulge: [], closed: false, text: "", rot: 0, r: 0, sa: 0, ea: 0, sx: 1, sy: 1, block: "", layer: "" };
        continue;
      }
      if (!current) continue;
      if (p.c === 8) current.layer = String(p.v || "").trim();
      else if (p.c === 2) current.block = String(p.v || "").trim();
      else if (p.c === 1 || p.c === 3) current.text += String(p.v || "");
      else if (p.c === 10) current.pts.push({ x: num(p.v), y: 0 });
      else if (p.c === 20 && current.pts.length) current.pts[current.pts.length - 1].y = num(p.v);
      else if (p.c === 11) current.pts.push({ x: num(p.v), y: 0, end: true });
      else if (p.c === 21 && current.pts.length) current.pts[current.pts.length - 1].y = num(p.v);
      else if (p.c === 40) current.r = num(p.v);
      else if (p.c === 41) current.sx = num(p.v) || 1;
      else if (p.c === 42) {
        if (current.type === "LWPOLYLINE" || current.type === "VERTEX") current.bulge.push(num(p.v));
        else current.sy = num(p.v) || 1;
      }
      else if (p.c === 50) current.rot = num(p.v);
      else if (p.c === 51) current.ea = num(p.v);
      else if (p.c === 70) current.closed = (num(p.v) & 1) === 1;
    }
    flush();
    return { entities: entities, index: pairs.length };
  }

  function mergePolylines(entities) {
    var out = [];
    var current = null;
    for (var i = 0; i < entities.length; i++) {
      var e = entities[i];
      if (e.type === "POLYLINE") {
        current = e;
        current.pts = current.pts || [];
        out.push(current);
      } else if (e.type === "VERTEX" && current) {
        if (e.pts && e.pts[0]) current.pts.push(e.pts[0]);
        if (e.bulge && e.bulge.length) current.bulge.push(e.bulge[0]);
      } else if (e.type === "SEQEND") {
        current = null;
      } else {
        current = null;
        out.push(e);
      }
    }
    return out;
  }

  function parseDxf(text) {
    var pairs = parsePairs(text);
    var header = { minx: null, miny: null, maxx: null, maxy: null };
    var blocks = {};
    var entities = [];
    var i = 0;
    while (i < pairs.length) {
      var p = pairs[i];
      if (p.c === 0 && String(p.v).trim().toUpperCase() === "SECTION") {
        var name = "";
        if (pairs[i + 1] && pairs[i + 1].c === 2) name = String(pairs[i + 1].v || "").trim().toUpperCase();
        i += 2;
        if (name === "HEADER") {
          for (; i < pairs.length; i++) {
            if (pairs[i].c === 0 && String(pairs[i].v).trim().toUpperCase() === "ENDSEC") break;
            if (pairs[i].c === 9) {
              var key = String(pairs[i].v || "").trim().toUpperCase();
              if (key === "$EXTMIN" && pairs[i + 1] && pairs[i + 2]) {
                header.minx = num(pairs[i + 1].v);
                header.miny = num(pairs[i + 2].v);
              }
              if (key === "$EXTMAX" && pairs[i + 1] && pairs[i + 2]) {
                header.maxx = num(pairs[i + 1].v);
                header.maxy = num(pairs[i + 2].v);
              }
            }
          }
        } else if (name === "BLOCKS") {
          for (; i < pairs.length; i++) {
            if (pairs[i].c === 0 && String(pairs[i].v).trim().toUpperCase() === "ENDSEC") break;
            if (pairs[i].c === 0 && String(pairs[i].v).trim().toUpperCase() === "BLOCK") {
              var bname = "";
              var j = i + 1;
              for (; j < pairs.length && pairs[j].c !== 0; j++) {
                if (pairs[j].c === 2) bname = String(pairs[j].v || "").trim();
              }
              var collected = collectEntities(pairs, j, { ENDBLK: 1, ENDSEC: 1 });
              if (bname) blocks[bname] = mergePolylines(collected.entities);
              i = collected.index;
            }
          }
        } else if (name === "ENTITIES") {
          var ent = collectEntities(pairs, i, { ENDSEC: 1 });
          entities = mergePolylines(ent.entities);
          i = ent.index;
        } else {
          for (; i < pairs.length; i++) {
            if (pairs[i].c === 0 && String(pairs[i].v).trim().toUpperCase() === "ENDSEC") break;
          }
        }
      }
      i++;
    }
    return { header: header, blocks: blocks, entities: entities };
  }

  function expandEntities(doc, list, ox, oy, sx, sy, rotDeg, acc) {
    var rad = (rotDeg || 0) * Math.PI / 180;
    var cos = Math.cos(rad);
    var sin = Math.sin(rad);
    function xf(pt) {
      var x = (pt.x || 0) * sx;
      var y = (pt.y || 0) * sy;
      return { x: ox + x * cos - y * sin, y: oy + x * sin + y * cos };
    }
    for (var i = 0; i < list.length; i++) {
      var e = list[i];
      if (e.type === "INSERT" && e.block && doc.blocks[e.block]) {
        var ip = e.pts[0] || { x: 0, y: 0 };
        var p = xf(ip);
        expandEntities(doc, doc.blocks[e.block], p.x, p.y, (e.sx || 1) * sx, (e.sy || 1) * sy, (e.rot || 0) + (rotDeg || 0), acc);
        continue;
      }
      var copy = {
        type: e.type,
        pts: (e.pts || []).map(xf),
        bulge: e.bulge || [],
        closed: !!e.closed,
        text: e.text || "",
        rot: (e.rot || 0) + (rotDeg || 0),
        r: (e.r || 0) * Math.max(Math.abs(sx), Math.abs(sy)),
        sa: e.sa || 0,
        ea: e.ea || 0,
        layer: e.layer || "",
      };
      acc.push(copy);
    }
  }

  function bboxOf(entities, header) {
    var minx = Infinity, miny = Infinity, maxx = -Infinity, maxy = -Infinity;
    function add(x, y) {
      if (!isFinite(x) || !isFinite(y)) return;
      if (x < minx) minx = x;
      if (y < miny) miny = y;
      if (x > maxx) maxx = x;
      if (y > maxy) maxy = y;
    }
    for (var i = 0; i < entities.length; i++) {
      var e = entities[i];
      for (var p = 0; p < (e.pts || []).length; p++) add(e.pts[p].x, e.pts[p].y);
      if ((e.type === "CIRCLE" || e.type === "ARC") && e.pts[0]) {
        add(e.pts[0].x - e.r, e.pts[0].y - e.r);
        add(e.pts[0].x + e.r, e.pts[0].y + e.r);
      }
    }
    if (!isFinite(minx) && header.minx != null && header.maxx != null) {
      minx = header.minx; miny = header.miny; maxx = header.maxx; maxy = header.maxy;
    }
    if (!isFinite(minx) || maxx - minx < 1e-6 || maxy - miny < 1e-6) {
      minx = 0; miny = 0; maxx = 100; maxy = 80;
    }
    var pad = Math.max(maxx - minx, maxy - miny) * 0.04;
    return { minx: minx - pad, miny: miny - pad, maxx: maxx + pad, maxy: maxy + pad };
  }

  function drawArcBulge(ctx, x1, y1, x2, y2, bulge, mapX, mapY) {
    if (!bulge) {
      ctx.lineTo(mapX(x2), mapY(y2));
      return;
    }
    var theta = 4 * Math.atan(bulge);
    var dx = x2 - x1;
    var dy = y2 - y1;
    var c = 1 / Math.tan(theta / 2);
    if (!isFinite(c)) {
      ctx.lineTo(mapX(x2), mapY(y2));
      return;
    }
    var cx = 0.5 * ((x1 + x2) - dy * c);
    var cy = 0.5 * ((y1 + y2) + dx * c);
    var r = Math.sqrt((x1 - cx) * (x1 - cx) + (y1 - cy) * (y1 - cy));
    var a1 = Math.atan2(y1 - cy, x1 - cx);
    var a2 = Math.atan2(y2 - cy, x2 - cx);
    var radius = Math.abs(r) * (typeof ctx._mxliScale === "number" ? ctx._mxliScale : 1);
    if (!isFinite(cx) || !isFinite(cy) || !isFinite(radius) || radius < 0.2) {
      ctx.lineTo(mapX(x2), mapY(y2));
      return;
    }
    ctx.arc(mapX(cx), mapY(cy), radius, -a1, -a2, bulge <= 0);
  }

  function drawEntities(ctx, entities, mapX, mapY, scale) {
    ctx._mxliScale = scale;
    ctx.lineWidth = Math.max(0.7, 1);
    ctx.strokeStyle = "#111";
    ctx.fillStyle = "#111";
    ctx.font = Math.max(10, Math.round(11)) + "px sans-serif";
    for (var i = 0; i < entities.length; i++) {
      var e = entities[i];
      var t = e.type;
      if (t === "LINE" && e.pts.length >= 2) {
        ctx.beginPath();
        ctx.moveTo(mapX(e.pts[0].x), mapY(e.pts[0].y));
        ctx.lineTo(mapX(e.pts[1].x), mapY(e.pts[1].y));
        ctx.stroke();
      } else if ((t === "LWPOLYLINE" || t === "POLYLINE") && e.pts.length) {
        ctx.beginPath();
        ctx.moveTo(mapX(e.pts[0].x), mapY(e.pts[0].y));
        for (var k = 1; k < e.pts.length; k++) {
          var b = e.bulge[k - 1] || 0;
          if (b) drawArcBulge(ctx, e.pts[k - 1].x, e.pts[k - 1].y, e.pts[k].x, e.pts[k].y, b, mapX, mapY);
          else ctx.lineTo(mapX(e.pts[k].x), mapY(e.pts[k].y));
        }
        if (e.closed) {
          var lastB = e.bulge[e.pts.length - 1] || 0;
          if (lastB) drawArcBulge(ctx, e.pts[e.pts.length - 1].x, e.pts[e.pts.length - 1].y, e.pts[0].x, e.pts[0].y, lastB, mapX, mapY);
          else ctx.closePath();
        }
        ctx.stroke();
      } else if (t === "CIRCLE" && e.pts[0] && e.r) {
        ctx.beginPath();
        ctx.arc(mapX(e.pts[0].x), mapY(e.pts[0].y), Math.abs(e.r) * scale, 0, Math.PI * 2);
        ctx.stroke();
      } else if (t === "ARC" && e.pts[0] && e.r) {
        var sa = (e.rot || 0) * Math.PI / 180;
        var ea = (e.ea || 0) * Math.PI / 180;
        ctx.beginPath();
        ctx.arc(mapX(e.pts[0].x), mapY(e.pts[0].y), Math.abs(e.r) * scale, -sa, -ea, false);
        ctx.stroke();
      } else if ((t === "TEXT" || t === "MTEXT") && e.pts[0] && e.text) {
        var label = String(e.text).replace(/\{\\.*?\}|\}|\\P/g, " ").replace(/\s+/g, " ").trim();
        if (!label) continue;
        ctx.save();
        ctx.translate(mapX(e.pts[0].x), mapY(e.pts[0].y));
        ctx.scale(1, -1);
        ctx.rotate(-((e.rot || 0) * Math.PI / 180));
        var fs = Math.max(8, (e.r || 0) * scale * 0.85);
        ctx.font = fs + "px sans-serif";
        ctx.fillText(label, 0, 0);
        ctx.restore();
      } else if (t === "POINT" && e.pts[0]) {
        ctx.beginPath();
        ctx.arc(mapX(e.pts[0].x), mapY(e.pts[0].y), 1.5, 0, Math.PI * 2);
        ctx.fill();
      } else if (t === "SPLINE" && e.pts.length > 1) {
        ctx.beginPath();
        ctx.moveTo(mapX(e.pts[0].x), mapY(e.pts[0].y));
        for (var s = 1; s < e.pts.length; s++) ctx.lineTo(mapX(e.pts[s].x), mapY(e.pts[s].y));
        ctx.stroke();
      }
    }
  }

  function renderToCanvas(bytes) {
    var text = decodeBytes(bytes);
    if (!/SECTION/i.test(text.slice(0, 400)) && text.indexOf("0\nSECTION") === -1 && text.indexOf("0\r\nSECTION") === -1) {
      throw new Error("El archivo no parece un DXF válido");
    }
    var doc = parseDxf(text);
    var flat = [];
    expandEntities(doc, doc.entities, 0, 0, 1, 1, 0, flat);
    if (!flat.length) throw new Error("El DXF no tiene geometría para mostrar");
    var box = bboxOf(flat, doc.header);
    var bw = Math.max(1, box.maxx - box.minx);
    var bh = Math.max(1, box.maxy - box.miny);
    var scale = Math.min(MAX_CANVAS / bw, MAX_CANVAS / bh, 8);
    var w = Math.max(320, Math.round(bw * scale));
    var h = Math.max(240, Math.round(bh * scale));
    var canvas = document.createElement("canvas");
    canvas.width = w;
    canvas.height = h;
    var ctx = canvas.getContext("2d");
    ctx.fillStyle = "#ffffff";
    ctx.fillRect(0, 0, w, h);
    function mapX(x) { return (x - box.minx) * scale; }
    function mapY(y) { return h - (y - box.miny) * scale; }
    ctx.lineJoin = "round";
    ctx.lineCap = "round";
    drawEntities(ctx, flat, mapX, mapY, scale);
    return canvas;
  }

  function readU32(bytes, off) {
    return (bytes[off] | (bytes[off + 1] << 8) | (bytes[off + 2] << 16) | (bytes[off + 3] << 24)) >>> 0;
  }

  function writeU32(out, off, value) {
    out[off] = value & 255;
    out[off + 1] = (value >> 8) & 255;
    out[off + 2] = (value >> 16) & 255;
    out[off + 3] = (value >> 24) & 255;
  }

  function ensureBmp(data) {
    if (data.length > 2 && data[0] === 0x42 && data[1] === 0x4d) return data;
    var headerSize = readU32(data, 0);
    if (headerSize !== 40 && headerSize !== 12) return data;
    var bpp = data[14] | (data[15] << 8);
    var palette = bpp > 0 && bpp <= 8 ? (1 << bpp) * 4 : 0;
    var fileSize = 14 + data.length;
    var offBits = 14 + headerSize + palette;
    var out = new Uint8Array(fileSize);
    out[0] = 0x42;
    out[1] = 0x4d;
    writeU32(out, 2, fileSize);
    writeU32(out, 10, offBits);
    out.set(data, 14);
    return out;
  }

  function blobToCanvas(data, mime) {
    return new Promise(function (resolve, reject) {
      var blob = new Blob([data], { type: mime });
      var url = URL.createObjectURL(blob);
      var img = new Image();
      img.onload = function () {
        if (img.naturalWidth < 8 || img.naturalHeight < 8) {
          URL.revokeObjectURL(url);
          reject(new Error("Miniatura vacía"));
          return;
        }
        var canvas = document.createElement("canvas");
        canvas.width = img.naturalWidth;
        canvas.height = img.naturalHeight;
        var ctx = canvas.getContext("2d");
        ctx.fillStyle = "#ffffff";
        ctx.fillRect(0, 0, canvas.width, canvas.height);
        ctx.drawImage(img, 0, 0);
        URL.revokeObjectURL(url);
        resolve(canvas);
      };
      img.onerror = function () {
        URL.revokeObjectURL(url);
        reject(new Error("Miniatura DWG ilegible"));
      };
      img.src = url;
    });
  }

  function extractFromHeader(bytes) {
    if (bytes.length < 0x30) return null;
    var sig = new TextDecoder("latin1").decode(bytes.subarray(0, 6));
    if (sig.indexOf("AC") !== 0) return null;
    var seek = readU32(bytes, 0x0d);
    if (seek < 0x15 || seek + 40 > bytes.length) return null;
    var p = seek + 16;
    var count = readU32(bytes, p + 4);
    if (count < 1 || count > 16) return null;
    p += 8;
    var images = [];
    for (var i = 0; i < count && p + 9 <= bytes.length; i++) {
      var type = bytes[p];
      var start = readU32(bytes, p + 1);
      var size = readU32(bytes, p + 5);
      p += 9;
      images.push({ type: type, start: start, size: size });
    }
    images.sort(function (a, b) {
      function rank(t) {
        if (t === 6) return 0;
        if (t === 3) return 1;
        if (t === 2) return 2;
        return 9;
      }
      return rank(a.type) - rank(b.type);
    });
    for (var j = 0; j < images.length; j++) {
      var im = images[j];
      if (!im.size || im.size > 8 * 1024 * 1024) continue;
      if (im.start + im.size > bytes.length) continue;
      var slice = bytes.subarray(im.start, im.start + im.size);
      if (im.type === 6 || (slice[0] === 0x89 && slice[1] === 0x50)) {
        return { mime: "image/png", data: slice };
      }
      if (im.type === 2 || (slice[0] === 0x42 && slice[1] === 0x4d) || readU32(slice, 0) === 40) {
        return { mime: "image/bmp", data: ensureBmp(slice) };
      }
    }
    return null;
  }

  function findEmbeddedPng(bytes) {
    var limit = Math.min(bytes.length - 24, 4 * 1024 * 1024);
    for (var i = 0; i < limit; i++) {
      if (bytes[i] !== 0x89 || bytes[i + 1] !== 0x50 || bytes[i + 2] !== 0x4e || bytes[i + 3] !== 0x47) continue;
      if (bytes[i + 4] !== 0x0d || bytes[i + 5] !== 0x0a) continue;
      for (var j = i + 16; j < Math.min(bytes.length - 8, i + 2 * 1024 * 1024); j++) {
        if (bytes[j] === 0x49 && bytes[j + 1] === 0x45 && bytes[j + 2] === 0x4e && bytes[j + 3] === 0x44) {
          return bytes.subarray(i, j + 8);
        }
      }
    }
    return null;
  }

  function extractDwgPreview(bytes) {
    var found = extractFromHeader(bytes);
    if (found) return found;
    var png = findEmbeddedPng(bytes);
    if (png) return { mime: "image/png", data: png };
    return null;
  }

  global.MxliDxfPreview = {
    looksLikeDxf: function (bytes) {
      var head = new TextDecoder("latin1").decode(bytes.slice(0, 120)).replace(/^\uFEFF/, "");
      if (/^AC10\d{2}/.test(head) || /^AC1\.\d/.test(head)) return false;
      return /0\s+SECTION/.test(head) || /^SECTION/im.test(head);
    },
    renderToCanvas: renderToCanvas,
  };

  global.MxliDwgThumb = {
    extract: extractDwgPreview,
    toCanvas: function (bytes) {
      var found = extractDwgPreview(bytes);
      if (!found) return Promise.reject(new Error("El DWG no trae miniatura"));
      return blobToCanvas(found.data, found.mime);
    },
  };
})(window);
