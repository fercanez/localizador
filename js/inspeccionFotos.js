(function () {
  "use strict";

  if (window.MxliInspeccionFotosInit) return;
  window.MxliInspeccionFotosInit = true;

  var API = "api/inspections.php";
  var PREDIO_API = "api/wfs-nearest-predio.php";
  var claveUserEdited = false;
  var lastSuggestedClave = "";

  function canInspect() {
    return typeof window.mxliCan === "function" && window.mxliCan("map.inspect");
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

  function toUpperNotes(value) {
    return String(value || "").toUpperCase();
  }

  function normalizeClave(value) {
    return String(value || "").trim().replace(/\s+/g, "").toUpperCase();
  }

  function compressPhoto(file) {
    return new Promise(function (resolve, reject) {
      if (!file || !file.type || file.type.indexOf("image/") !== 0) {
        reject(new Error("Seleccione una imagen válida."));
        return;
      }
      var img = new Image();
      var url = URL.createObjectURL(file);
      img.onload = function () {
        URL.revokeObjectURL(url);
        var maxSide = 1920;
        var w = img.naturalWidth || img.width;
        var h = img.naturalHeight || img.height;
        var scale = Math.min(1, maxSide / Math.max(w, h));
        var canvas = document.createElement("canvas");
        canvas.width = Math.max(1, Math.round(w * scale));
        canvas.height = Math.max(1, Math.round(h * scale));
        var ctx = canvas.getContext("2d");
        ctx.drawImage(img, 0, 0, canvas.width, canvas.height);
        canvas.toBlob(
          function (blob) {
            if (!blob) {
              resolve(file);
              return;
            }
            try {
              resolve(new File([blob], "inspeccion.jpg", { type: "image/jpeg" }));
            } catch (e) {
              resolve(blob);
            }
          },
          "image/jpeg",
          0.82
        );
      };
      img.onerror = function () {
        URL.revokeObjectURL(url);
        resolve(file);
      };
      img.src = url;
    });
  }

  function claveFromSearch() {
    var input = $("buscarPredioClave");
    if (!input) return "";
    return normalizeClave(input.value);
  }

  function applySuggestedClave(clave, distanceM, source) {
    var input = $("inspect-clave");
    if (!input || claveUserEdited) return false;

    var current = normalizeClave(input.value);
    if (current !== "" && current !== lastSuggestedClave) {
      return false;
    }

    input.value = clave;
    lastSuggestedClave = clave;

    var hint = "Clave sugerida. El punto se ubicará en el centro del predio.";
    if (source === "intersects") {
      hint = "Clave sugerida (predio en su GPS). El punto se guardará en el centro del predio.";
    } else if (distanceM != null) {
      hint =
        "Clave sugerida: predio a ~" +
        Math.round(distanceM) +
        " m. El punto se guardará en el centro del predio.";
    }
    showMsg($("inspect-msg"), hint, true);
    return true;
  }

  function fetchPredio(params) {
    var qs = Object.keys(params)
      .map(function (key) {
        return encodeURIComponent(key) + "=" + encodeURIComponent(String(params[key]));
      })
      .join("&");
    return fetch(PREDIO_API + "?" + qs, { credentials: "same-origin" })
      .then(function (res) {
        return res.json().then(function (data) {
          if (!res.ok || !data.ok) {
            return null;
          }
          return data;
        });
      })
      .catch(function () {
        return null;
      });
  }

  function suggestClaveFromGps(options) {
    options = options || {};
    if (!navigator.geolocation) {
      return Promise.resolve(null);
    }

    return new Promise(function (resolve) {
      navigator.geolocation.getCurrentPosition(
        function (pos) {
          fetchPredio({ lat: pos.coords.latitude, lng: pos.coords.longitude }).then(function (data) {
            if (data && data.clavecatas) {
              applySuggestedClave(data.clavecatas, data.distance_m, data.source);
            } else if (options.showNoMatch && !claveUserEdited) {
              showMsg(
                $("inspect-msg"),
                "No hay predio cercano. Puede teclear la clave o dejarla vacía para usar el GPS.",
                false
              );
            }
            resolve(data);
          });
        },
        function () {
          if (options.showNoMatch) {
            showMsg(
              $("inspect-msg"),
              "No se pudo obtener GPS. Capture la clave para ubicar el predio, o active la ubicación.",
              false
            );
          }
          resolve(null);
        },
        { enableHighAccuracy: true, timeout: 10000, maximumAge: 15000 }
      );
    });
  }

  function getCurrentPosition() {
    return new Promise(function (resolve) {
      if (!navigator.geolocation) {
        resolve(null);
        return;
      }
      navigator.geolocation.getCurrentPosition(
        function (pos) {
          resolve({
            lat: pos.coords.latitude,
            lng: pos.coords.longitude,
          });
        },
        function () {
          resolve(null);
        },
        { enableHighAccuracy: true, timeout: 10000, maximumAge: 60000 }
      );
    });
  }

  function resolveSaveCoords(clave) {
    if (clave.length >= 4) {
      showMsg($("inspect-msg"), "Ubicando centroide del predio…", true);
      return fetchPredio({ clave: clave }).then(function (data) {
        if (data && data.lat != null && data.lng != null) {
          return {
            lat: data.lat,
            lng: data.lng,
            source: "centroid",
          };
        }
        showMsg($("inspect-msg"), "Clave no encontrada; se usará el GPS del dispositivo…", true);
        return getCurrentPosition().then(function (coords) {
          if (!coords) return null;
          coords.source = "gps";
          return coords;
        });
      });
    }

    showMsg($("inspect-msg"), "Obteniendo ubicación GPS…", true);
    return getCurrentPosition().then(function (coords) {
      if (!coords) return null;
      coords.source = "gps";
      return coords;
    });
  }

  function ensureDialog() {
    if ($("inspect-photo-dialog")) return;

    var dlg = document.createElement("dialog");
    dlg.id = "inspect-photo-dialog";
    dlg.className = "inspect-photo-dialog";
    dlg.innerHTML =
      '<form method="dialog" id="inspect-photo-form" class="inspect-photo-form">' +
      "<h3>Inspección fotográfica</h3>" +
      '<p class="inspect-photo-form__hint">Si captura o acepta una clave, el punto se coloca en el centro del predio. Si la deja vacía, se usa el GPS. Las notas se guardan en MAYÚSCULAS.</p>' +
      '<label>Clave catastral (opcional)<input type="text" id="inspect-clave" maxlength="32" placeholder="ST312014 o vacía para usar GPS" autocomplete="off" style="text-transform:uppercase"></label>' +
      '<label>Notas (opcional)<textarea id="inspect-notes" rows="2" placeholder="OBSERVACIONES" style="text-transform:uppercase"></textarea></label>' +
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

    var takeBtn = $("inspect-take-btn");
    var fileInput = $("inspect-photo-input");
    var preview = $("inspect-preview");
    var claveInput = $("inspect-clave");
    var notesInput = $("inspect-notes");
    var selectedFile = null;

    function closeInspectDialog() {
      if (typeof dlg.close === "function") {
        dlg.close();
      } else {
        dlg.removeAttribute("open");
      }
    }

    $("inspect-cancel-btn").addEventListener("click", function (event) {
      event.preventDefault();
      closeInspectDialog();
    });

    dlg.addEventListener("cancel", function () {
      closeInspectDialog();
    });

    claveInput.addEventListener("input", function () {
      claveUserEdited = true;
      claveInput.value = normalizeClave(claveInput.value);
    });

    notesInput.addEventListener("input", function () {
      notesInput.value = toUpperNotes(notesInput.value);
    });

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
      claveUserEdited = false;
      lastSuggestedClave = "";
    });

    $("inspect-photo-form").addEventListener("submit", function (event) {
      event.preventDefault();
      if (!selectedFile) {
        showMsg($("inspect-msg"), "Seleccione o tome una fotografía.", false);
        return;
      }

      var clave = normalizeClave($("inspect-clave").value);
      if (clave !== "" && clave.length < 4) {
        showMsg($("inspect-msg"), "Capture una clave catastral válida o déjela vacía para usar GPS.", false);
        $("inspect-clave").focus();
        return;
      }

      var notes = toUpperNotes($("inspect-notes").value || "").trim();

      resolveSaveCoords(clave)
        .then(function (coords) {
          if (!coords) {
            throw new Error("No hay ubicación: capture una clave válida o active el GPS.");
          }

          showMsg($("inspect-msg"), "Preparando foto…", true);
          return compressPhoto(selectedFile).then(function (photoFile) {
            var fd = new FormData();
            fd.append("clavecatas", clave);
            fd.append("notes", notes);
            fd.append("photo", photoFile, photoFile.name || "inspeccion.jpg");
            fd.append("lat", String(coords.lat));
            fd.append("lng", String(coords.lng));

            showMsg(
              $("inspect-msg"),
              coords.source === "centroid" ? "Guardando en el centro del predio…" : "Guardando con GPS…",
              true
            );
            return fetch(API, { method: "POST", body: fd }).then(function (res) {
              return res.json().then(function (data) {
                if (!res.ok || !data.ok) {
                  throw new Error((data && data.error) || "Error al guardar");
                }
                return data;
              });
            });
          });
        })
        .then(function () {
          dlg.close();
          window.alert("Inspección registrada correctamente.");
          if (typeof window.mxliRefreshInspeccionesCapa === "function") {
            window.mxliRefreshInspeccionesCapa();
          }
        })
        .catch(function (err) {
          showMsg($("inspect-msg"), err.message || "Error", false);
        });
    });
  }

  function openDialog(event) {
    if (event) {
      event.preventDefault();
      event.stopPropagation();
    }
    if (!canInspect()) {
      window.alert("Su rol no tiene permiso de inspección fotográfica.");
      return;
    }
    ensureDialog();
    var dlg = $("inspect-photo-dialog");
    if (!dlg) return;

    claveUserEdited = false;
    lastSuggestedClave = "";

    var searchClave = claveFromSearch();
    $("inspect-clave").value = searchClave;
    if (searchClave) {
      claveUserEdited = true;
    }

    $("inspect-notes").value = "";
    showMsg($("inspect-msg"), "Obteniendo GPS para sugerir clave…", true);

    if (typeof dlg.showModal === "function") {
      dlg.showModal();
    } else {
      dlg.setAttribute("open", "");
    }

    if (!searchClave) {
      suggestClaveFromGps({ showNoMatch: true });
    } else {
      showMsg(
        $("inspect-msg"),
        "Clave del buscador. El punto se guardará en el centro del predio.",
        true
      );
    }
  }

  function bindButton(btn) {
    if (!btn || btn.dataset.inspectBound === "1") return;
    btn.dataset.inspectBound = "1";
    btn.addEventListener("click", openDialog);
    btn.addEventListener("keydown", function (event) {
      if (event.key === "Enter" || event.key === " ") {
        openDialog(event);
      }
    });
    btn.setAttribute("title", "Inspección fotográfica");
    btn.setAttribute("role", "button");
    btn.setAttribute("tabindex", "0");
  }

  function init() {
    if (!canInspect()) return;

    var btn = $("inspectPhotoBtn");
    if (!btn) {
      setTimeout(init, 400);
      return;
    }

    bindButton(btn);
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", init);
  } else {
    init();
  }

  setTimeout(init, 800);
  setTimeout(init, 1600);

  window.MxliInspeccionFotos = { open: openDialog };
})();
