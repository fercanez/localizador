(function () {
  "use strict";

  if (window.MxliInspeccionFotosInit) return;
  window.MxliInspeccionFotosInit = true;

  var API = "api/inspections.php";

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

  function claveFromSearch() {
    var input = $("buscarPredioClave");
    if (!input) return "";
    return String(input.value || "").trim().replace(/\s+/g, "").toUpperCase();
  }

  function ensureDialog() {
    if ($("inspect-photo-dialog")) return;

    var dlg = document.createElement("dialog");
    dlg.id = "inspect-photo-dialog";
    dlg.className = "inspect-photo-dialog";
    dlg.innerHTML =
      '<form method="dialog" id="inspect-photo-form" class="inspect-photo-form">' +
      '<h3>Inspección fotográfica</h3>' +
      '<p class="inspect-photo-form__hint">Capture la clave catastral y la fotografía del predio.</p>' +
      '<label>Clave catastral<input type="text" id="inspect-clave" maxlength="32" required placeholder="ST312014"></label>' +
      '<label>Notas (opcional)<textarea id="inspect-notes" rows="2" placeholder="Observaciones"></textarea></label>' +
      '<div class="inspect-photo-preview-wrap">' +
      '<img id="inspect-preview" class="inspect-photo-preview" alt="Vista previa" hidden>' +
      '<button type="button" id="inspect-take-btn" class="inspect-photo-take">Tomar / seleccionar foto</button>' +
      '<input type="file" id="inspect-photo-input" accept="image/*" capture="environment" hidden>' +
      "</div>" +
      '<p id="inspect-msg" class="inspect-photo-msg" hidden></p>' +
      '<div class="inspect-photo-actions">' +
      '<button type="submit" value="cancel" class="inspect-photo-btn inspect-photo-btn--ghost">Cancelar</button>' +
      '<button type="submit" value="save" class="inspect-photo-btn inspect-photo-btn--primary">Guardar registro</button>' +
      "</div>" +
      "</form>";
    document.body.appendChild(dlg);

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
      var submitter = event.submitter;
      if (submitter && submitter.value === "cancel") return;
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

      function upload(lat, lng) {
        var fd = new FormData();
        fd.append("clavecatas", clave);
        fd.append("notes", String($("inspect-notes").value || "").trim());
        fd.append("photo", selectedFile);
        if (lat != null && lng != null) {
          fd.append("lat", String(lat));
          fd.append("lng", String(lng));
        }

        fetch(API, { method: "POST", body: fd })
          .then(function (res) {
            return res.json().then(function (data) {
              if (!res.ok || !data.ok) {
                throw new Error((data && data.error) || "Error al guardar");
              }
              return data;
            });
          })
          .then(function () {
            dlg.close();
            window.alert("Inspección registrada correctamente.");
          })
          .catch(function (err) {
            showMsg($("inspect-msg"), err.message || "Error", false);
          });
      }

      if (navigator.geolocation) {
        navigator.geolocation.getCurrentPosition(
          function (pos) {
            upload(pos.coords.latitude, pos.coords.longitude);
          },
          function () {
            upload(null, null);
          },
          { enableHighAccuracy: true, timeout: 8000, maximumAge: 60000 }
        );
      } else {
        upload(null, null);
      }
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
    showMsg($("inspect-msg"), "", true);
    dlg.showModal();
  }

  function init() {
    if (!canInspect()) return;

    var btn = $("inspectPhotoBtn");
    if (!btn) {
      setTimeout(init, 400);
      return;
    }

    btn.addEventListener("click", openDialog);
    btn.setAttribute("title", "Inspección fotográfica");
    btn.setAttribute("role", "button");
    btn.setAttribute("tabindex", "0");
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", init);
  } else {
    init();
  }

  window.MxliInspeccionFotos = { open: openDialog };
})();
