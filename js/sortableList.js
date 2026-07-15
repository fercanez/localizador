const draggable_list = document.getElementById("sortable");
const listItems = [];

function safeCssId(value) {
  return String(value || "")
    .normalize("NFD")
    .replace(/[\u0300-\u036f]/g, "")
    .replace(/[^a-zA-Z0-9_-]/g, "_");
}

function createList() {
  if (!draggable_list || typeof capas === "undefined") return;

  [...capas]
    .map((a) => ({ value: a, sort: Math.random() }))
    .sort((a, b) => a.sort - b.sort)
    .map((a) => a.value)
    .forEach((capa) => {
      const listItem = document.createElement("div");
      listItem.setAttribute("class", "sortableItem");
      listItem.dataset.layerTitle = capa;

      listItem.innerHTML = `
        <span class="icon-Eliminar"></span>
        <a href="#" onclick="return false;">Quitar</a>
        <p class="nombre-capa">${capa}</p>
        <input type="range" min="0" max="100" step="10" onchange="opacidadCapa(event)" data-layer-title="${capa}" class="rangeOpacityInput" value="100">
      `;

      listItems.push(listItem);
      draggable_list.appendChild(listItem);
    });
}

createList();

function desactivarCapa(inputId) {
  const el = document.getElementById(inputId);
  if (el) el.click();
}

function addToList(capa, inputId) {
  if (!draggable_list) return;

  const existente = document.querySelector(`.sortableItem[data-layer-title="${CSS.escape(capa)}"]`);
  if (existente) return;

  const listItem = document.createElement("div");
  listItem.setAttribute("class", "sortableItem");
  listItem.dataset.layerTitle = capa;
  listItem.innerHTML = `
      <span class="icon-MoverCapas sort"></span>
      <a href="#" onclick="desactivarCapa('${inputId}'); return false;" class="remove">
        <span class="icon-Eliminar"></span>
      </a>
      <p class="nombre-capa layerName">${capa}</p>
      <input type="range" min="0" max="100" step="10" onchange="opacidadCapa(event)" data-layer-title="${capa}" class="rangeOpacityInput" value="100">
    `;

  draggable_list.prepend(listItem);
}

function add(capa, inputId) {
  if (!draggable_list) return;
  const listItem = document.createElement("div");
  listItem.setAttribute("class", "sortableItem");
  listItem.dataset.layerTitle = capa;
  listItem.innerHTML = `
      <span class="material-symbols-outlined sort">unfold_more</span>
      <a href="#" onclick="desactivarCapa('${inputId}'); return false;" class="remove">
        <span class="material-symbols-outlined">block</span>
      </a>
      <p class="nombre-capa layerName">${capa}</p>
    `;

  draggable_list.prepend(listItem);
}

if (typeof dragula === "function" && draggable_list) {
  var drake = dragula([draggable_list]);
  drake.on("drop", function () {
    if (typeof reorganizar === "function") reorganizar();
  });
}

function opacidadCapa(event) {
  if (!event || !event.target) return;
  const title = event.target.dataset.layerTitle;
  const opacity = Number(event.target.value) / 100;

  if (typeof group === "undefined" || !group || typeof group.eachLayer !== "function") return;

  group.eachLayer(function (layer) {
    const layerTitle = (layer && layer.options && layer.options.title) || layer.id;
    if (layerTitle === title || layer.id === title) {
      if (typeof layer.setOpacity === "function") layer.setOpacity(opacity);
    }
  });
}
