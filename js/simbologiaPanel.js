(function () {
  "use strict";

  const legendCache = new Map();

  function getActiveLayerCards() {
    const cards = document.querySelectorAll("#wms-layer-list .wms-layer-card");
    const active = [];

    cards.forEach(function (card) {
      const checkbox = card.querySelector('input[type="checkbox"]');
      if (!checkbox || !checkbox.checked) return;

      active.push({
        layer: card.dataset.gsLayer || "",
        workspace: card.dataset.workspace || "geonode",
        datastore: card.dataset.datastore || "geonode",
        title:
          card.dataset.title ||
          (card.querySelector(".wms-layer-card__title") || {}).textContent ||
          "",
        legendWidth: Math.min(Number(card.dataset.legendWidth || 300), 240),
        legendHeight: Math.min(Number(card.dataset.legendHeight || 700), 280),
        isSelected: card.classList.contains("is-selected"),
      });
    });

    return active;
  }

  function updatePanelTitle(activeLayers) {
    const titleEl = document.getElementById("simbologiaCard-titulo");
    if (!titleEl) return;

    if (activeLayers.length === 0) {
      titleEl.textContent = "Simbología";
    } else if (activeLayers.length === 1) {
      titleEl.textContent = activeLayers[0].title;
    } else {
      titleEl.textContent = "Simbología (" + activeLayers.length + " capas)";
    }
  }

  function fetchLegendUrl(layer, workspace, width, height) {
    const legendWidth = width || 300;
    const legendHeight = height || 700;
    const cacheKey =
      workspace + ":" + layer + ":" + legendWidth + "x" + legendHeight;

    if (legendCache.has(cacheKey)) {
      return Promise.resolve(legendCache.get(cacheKey));
    }

    const params = new URLSearchParams({
      layer: layer,
      workspace: workspace,
      width: String(legendWidth),
      height: String(legendHeight),
    });

    return fetch("api/legend-graphic.php?" + params.toString())
      .then(function (response) {
        if (!response.ok) throw new Error("legend");
        return response.blob();
      })
      .then(function (blob) {
        if (!blob || blob.size < 40) throw new Error("legend-empty");
        const url = URL.createObjectURL(blob);
        legendCache.set(cacheKey, url);
        return url;
      });
  }

  function fetchLegendRules(layer) {
    const params = new URLSearchParams({ layer: layer });
    return fetch("api/legend-rules.php?" + params.toString())
      .then(function (response) {
        return response.json();
      })
      .catch(function () {
        return { rules: [], note: "" };
      });
  }

  function renderHtmlLegend(item, payload) {
    const rules = payload.rules || [];
    const note = payload.note || "";
    let htmlLegend = item.querySelector(".simbologia-item__html");

    if (!htmlLegend) {
      htmlLegend = document.createElement("div");
      htmlLegend.className = "simbologia-item__html";
      const img = item.querySelector(".simbologia-item__image");
      if (img && img.parentNode) {
        img.parentNode.insertBefore(htmlLegend, img.nextSibling);
      } else {
        item.appendChild(htmlLegend);
      }
    }

    if (rules.length === 0 && !note) {
      htmlLegend.innerHTML =
        '<p class="simbologia-item__error">No se pudo cargar la leyenda de esta capa.</p>';
      return;
    }

    let html = "";
    rules.forEach(function (rule) {
      const color = rule.fill || rule.color || "#cccccc";
      const stroke = rule.stroke || "#666666";
      const label = rule.label || "";
      html +=
        '<div class="simbologia-rule">' +
        '<span class="simbologia-rule__swatch" style="background:' +
        color +
        ";border-color:" +
        stroke +
        '"></span>' +
        '<span class="simbologia-rule__label">' +
        label +
        "</span>" +
        "</div>";
    });

    if (note) {
      html += '<p class="simbologia-item__note">' + note + "</p>";
    }

    htmlLegend.innerHTML = html;
  }

  function fetchLayerAbstract(layer, datastore, workspace) {
    const params = new URLSearchParams({
      layer: layer,
      datastore: datastore,
      workspace: workspace,
    });

    return fetch("api/layer-info.php?" + params.toString())
      .then(function (response) {
        return response.json();
      })
      .then(function (data) {
        if (data.error) return "";
        return data.abstract || "";
      })
      .catch(function () {
        return "";
      });
  }

  function showLegendError(item, message) {
    let errorEl = item.querySelector(".simbologia-item__error");
    if (!errorEl) {
      errorEl = document.createElement("p");
      errorEl.className = "simbologia-item__error";
      const img = item.querySelector(".simbologia-item__image");
      if (img && img.parentNode) {
        img.parentNode.insertBefore(errorEl, img.nextSibling);
      } else {
        item.appendChild(errorEl);
      }
    }
    errorEl.textContent = message;
  }

  function loadLegendImage(item, layerInfo) {
    const img = item.querySelector(".simbologia-item__image");
    const errorEl = item.querySelector(".simbologia-item__error");
    const htmlLegend = item.querySelector(".simbologia-item__html");
    if (errorEl) errorEl.remove();
    if (htmlLegend) htmlLegend.remove();
    if (img) {
      img.style.display = "";
      img.removeAttribute("src");
    }

    fetchLegendUrl(
      layerInfo.layer,
      layerInfo.workspace,
      layerInfo.legendWidth,
      layerInfo.legendHeight
    )
      .then(function (url) {
        if (img) img.src = url;
      })
      .catch(function () {
        return fetchLegendRules(layerInfo.layer).then(function (payload) {
          if (payload.rules && payload.rules.length > 0) {
            if (img) {
              img.style.display = "none";
              img.removeAttribute("src");
            }
            renderHtmlLegend(item, payload);
            return;
          }

          if (img) {
            img.style.display = "none";
            img.removeAttribute("src");
          }
          showLegendError(
            item,
            payload.note || "No se pudo cargar la leyenda de esta capa."
          );
        });
      });
  }

  function createLegendItem(layerInfo) {
    const item = document.createElement("div");
    item.className = "simbologia-item";
    item.dataset.layer = layerInfo.layer;

    if (layerInfo.isSelected) {
      item.classList.add("is-selected");
    }

    item.innerHTML =
      '<h4 class="simbologia-item__title"></h4>' +
      '<img class="simbologia-item__image" alt="">' +
      '<p class="simbologia-item__desc"></p>';

    item.querySelector(".simbologia-item__title").textContent = layerInfo.title;
    loadLegendImage(item, layerInfo);

    fetchLayerAbstract(
      layerInfo.layer,
      layerInfo.datastore,
      layerInfo.workspace
    ).then(function (abstract) {
      const desc = item.querySelector(".simbologia-item__desc");
      if (desc) desc.textContent = abstract;
    });

    return item;
  }

  window.refreshActiveSimbology = function () {
    const container = document.getElementById("simbologia-leyendas");
    const emptyMessage = document.getElementById("simbologia-empty");
    if (!container) return;

    const activeLayers = getActiveLayerCards();
    updatePanelTitle(activeLayers);

    if (emptyMessage) {
      emptyMessage.style.display = activeLayers.length === 0 ? "block" : "none";
    }

    container.querySelectorAll(".simbologia-item").forEach(function (item) {
      const stillActive = activeLayers.some(function (layerInfo) {
        return layerInfo.layer === item.dataset.layer;
      });
      if (!stillActive) item.remove();
    });

    activeLayers.forEach(function (layerInfo) {
      let item = container.querySelector(
        '.simbologia-item[data-layer="' + layerInfo.layer + '"]'
      );

      if (!item) {
        item = createLegendItem(layerInfo);
        container.appendChild(item);
      } else {
        item.classList.toggle("is-selected", layerInfo.isSelected);
        item.querySelector(".simbologia-item__title").textContent =
          layerInfo.title;

        const img = item.querySelector(".simbologia-item__image");
        if (img && !img.getAttribute("src")) {
          loadLegendImage(item, layerInfo);
        }
      }

      container.appendChild(item);
    });
  };

  window.getSimbology = function (layer, workspace) {
    fetchLegendUrl(layer, workspace || "geonode", 300, 700).catch(function () {});
  };

  window.setLayerInfo = function (layer, datastore, workspace, displayTitle) {
    fetchLayerAbstract(layer, datastore, workspace).catch(function () {});
  };

  window.seleccionarCapa = function (element, layer, datastore, workspace) {
    document.querySelectorAll(".wms-layer-card").forEach(function (card) {
      card.classList.remove("is-selected");
    });

    document.querySelectorAll(".simbologia-item").forEach(function (item) {
      item.classList.remove("is-selected");
    });

    const activeCard = element.closest(".wms-layer-card");
    if (activeCard) activeCard.classList.add("is-selected");

    const symItem = document.querySelector(
      '.simbologia-item[data-layer="' + layer + '"]'
    );
    if (symItem) symItem.classList.add("is-selected");

    window.capaSimbologiaSeleccionada = layer;
    refreshActiveSimbology();
  };

  document.addEventListener("DOMContentLoaded", function () {
    refreshActiveSimbology();
  });
})();
