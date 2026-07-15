// Control de capas corregido para evitar errores cuando el DOM o la lista de capas no existen.
// Mantiene compatibilidad con el visor original.
let activeLayers = [];
let capasActivas = {};
let capas = [];
let nActiva = 0;

function arrayRemove(arr, value) {
  return arr.filter(function (ele) {
    return ele !== value;
  });
}

function getLayerTitle(layer) {
  return (layer && layer.options && layer.options.title) || (layer && layer.id) || "Capa";
}

function removeLayerFromSortable(title) {
  const item = document.querySelector(`.sortableItem[data-layer-title="${CSS.escape(title)}"]`);
  if (item && item.parentElement) item.parentElement.removeChild(item);
}

function toggleLayer(chkBox, lyrName) {
  var checkBox = document.getElementById(chkBox);

  if (!checkBox || !lyrName) return;

  if (checkBox.checked == true) {
    group.addLayer(lyrName);

    capasActivas[lyrName.options.title] = {
      capa: lyrName,
      order: ++nActiva,
    };

    if (typeof syncWmsLayerCardState === "function") {
      syncWmsLayerCardState(chkBox, true);
    }

    addToList(lyrName.options.title, chkBox);

    if (typeof applyWmsLayerStack === "function") {
      applyWmsLayerStack();
    } else {
      group.bringToFront();
    }

    if (typeof refreshActiveSimbology === "function") {
      refreshActiveSimbology();
    }

    if (typeof actualizarCapaActivaConsulta === "function") {
      actualizarCapaActivaConsulta();
    }

  } else {
    group.removeLayer(lyrName);

    var item = document.getElementById(lyrName.options.title);
    if (item && item.parentElement) item.parentElement.remove();

    delete capasActivas[lyrName.options.title];

    if (typeof syncWmsLayerCardState === "function") {
      syncWmsLayerCardState(chkBox, false);
    }

    if (typeof applyWmsLayerStack === "function") {
      applyWmsLayerStack();
    } else {
      group.bringToFront();
    }

    if (typeof refreshActiveSimbology === "function") {
      refreshActiveSimbology();
    }

    if (typeof actualizarCapaActivaConsulta === "function") {
      actualizarCapaActivaConsulta();
    }
  }
}