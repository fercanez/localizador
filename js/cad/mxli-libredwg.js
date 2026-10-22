import { LibreDwg, Dwg_File_Type, createModule } from "./dist/libredwg-web.js";

const pingUrl = new URL("../../api/cad-wasm.php?ping=1", import.meta.url).href;
const wasmB64Url = new URL("../../api/cad-wasm.php", import.meta.url).href;

function b64ToU8(b64) {
  var clean = String(b64 || "").replace(/\s+/g, "");
  var bin = atob(clean);
  var out = new Uint8Array(bin.length);
  for (var i = 0; i < bin.length; i++) out[i] = bin.charCodeAt(i);
  return out;
}

function loadWasmBinary() {
  return fetch(pingUrl, { credentials: "same-origin" })
    .then(function (res) {
      if (!res.ok) throw new Error("Motor CAD HTTP " + res.status);
      return res.json();
    })
    .then(function (info) {
      if (!info || !info.ok) {
        throw new Error("Falta libredwg-web.wasm en el servidor");
      }
      return fetch(wasmB64Url, { credentials: "same-origin" });
    })
    .then(function (res) {
      if (!res.ok) throw new Error("No se pudo descargar el motor CAD (" + res.status + ")");
      return res.text();
    })
    .then(function (text) {
      var bytes = b64ToU8(text);
      if (bytes.length < 1000) throw new Error("Motor CAD incompleto");
      if (!(bytes[0] === 0x00 && bytes[1] === 0x61 && bytes[2] === 0x73 && bytes[3] === 0x6d)) {
        throw new Error("Motor CAD corrupto");
      }
      return bytes;
    });
}

loadWasmBinary()
  .then(function (wasmBinary) {
    return createModule({ wasmBinary: wasmBinary });
  })
  .then(function (wasmInstance) {
    var lib = LibreDwg.createByWasmInstance(wasmInstance);
    window.MxliLibreDwg = { lib: lib, Dwg_File_Type: Dwg_File_Type };
    window.dispatchEvent(new Event("mxli-libredwg-ready"));
  })
  .catch(function (err) {
    window.MxliLibreDwg = { error: err || new Error("No se pudo iniciar LibreDWG") };
    window.dispatchEvent(new Event("mxli-libredwg-ready"));
  });
