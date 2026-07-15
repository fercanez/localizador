// Utilidad segura para evitar que el visor se detenga si falta algún botón en el HTML.
function safeOn(element, eventName, handler) {
  if (element) {
    element.addEventListener(eventName, handler);
  }
}

// Inicialización del mapa
let map2d = L.map("map2d", { zoomControl: false }).setView(
  [32.642617, -115.471843],
  13
);

// Capa para carga de KML
var vector = {};

// Capa para carga de SHP (.zip)
var customLayer = L.geoJson(null, {
  style: function (feature) {
    return { color: "#f00" };
  },
});
var customLayer;

// Capa para carga de KMZ
var kmz = L.kmzLayer().addTo(map2d);
kmz.id = "KMZ Propio";

var kml = L.kmzLayer().addTo(map2d);
kml.id = "KMZ Propio";

// Agregar escala al mapa
L.control.scale().addTo(map2d);

// Definicion de capas base

var osm = L.tileLayer("https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png", {
  maxZoom: 20,
  attribution:
    '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
});
var googleSatellite = L.tileLayer(
  "https://mt1.google.com/vt/lyrs=s&hl=pl&x={x}&y={y}&z={z}",
  {
    attribution: "Google",
    maxZoom: 22,
    maxNativeZoom: 20,
  }
).addTo(map2d);

var googleRoadMap = L.tileLayer(
  "https://mt1.google.com/vt/lyrs=m&x={x}&y={y}&z={z}",
  {
    attribution: "Google",
    maxZoom: 22,
    maxNativeZoom: 20,
  }
);

var googleTerrain = L.tileLayer(
  "https://mt1.google.com/vt/lyrs=p&x={x}&y={y}&z={z}",
  {
    attribution: "Google",
    maxZoom: 22,
    maxNativeZoom: 20,
  }
);

var googleTrafficRoads = L.tileLayer(
  "https://mt1.google.com/vt/lyrs=r,traffic|vm:1&hl=en&opts=r&x={x}&y={y}&z={z}",
  {
    attribution: "Google",
    maxZoom: 22,
    maxNativeZoom: 20,
  }
);

var googleTrafficSatellite = L.tileLayer(
  "https://mt1.google.com/vt/lyrs=s,traffic|vm:1&hl=en&opts=r&x={x}&y={y}&z={z}",
  {
    attribution: "Google",
    maxZoom: 22,
    maxNativeZoom: 20,
  }
);

var googleTrafficTerrain = L.tileLayer(
  "https://mt1.google.com/vt?lyrs=p,traffic|seconds_into_week:-1&x={x}&y={y}&z={z}",
  {
    attribution: "Google",
    maxZoom: 22,
    maxNativeZoom: 20,
  }
);

var googleBikeSatellite = L.tileLayer(
  "https://mt1.google.com/vt?lyrs=s,bike&x={x}&y={y}&z={z}",
  {
    attribution: "Google",
    maxZoom: 22,
    maxNativeZoom: 20,
  }
);

var googleBikeRoads = L.tileLayer(
  "https://mt1.google.com/vt?lyrs=r,bike&x={x}&y={y}&z={z}",
  {
    attribution: "Google",
    maxZoom: 22,
    maxNativeZoom: 20,
  }
);

var googleBikeTerrain = L.tileLayer(
  "https://mt1.google.com/vt?lyrs=p,bike&x={x}&y={y}&z={z}",
  {
    attribution: "Google",
    maxZoom: 22,
    maxNativeZoom: 20,
  }
);

var bingAerial = null;
if (window.MXLI_MAP_KEYS && MXLI_MAP_KEYS.bing && typeof L.tileLayer.bing === "function") {
  bingAerial = L.tileLayer.bing(MXLI_MAP_KEYS.bing);
}
var cartoDark = L.tileLayer(
  "https://a.basemaps.cartocdn.com/dark_all/{z}/{x}/{y}.png"
);
var cartoPositron = L.tileLayer(
  "https://cartodb-basemaps-a.global.ssl.fastly.net/light_all/{z}/{x}/{y}.png"
);
var ESRI_satellite = L.tileLayer(
  "https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}",
  {
    crs: L.CRS.EPSG3857,
  }
);
var yandex = L.tileLayer(
  "https://core-sat.maps.yandex.net/tiles?l=sat&v=3.1020.0&x={x}&y={y}&z={z}&scale=1&lang=en_US",
  {
    crs: L.CRS.EPSG3395,
  }
);

var emptyLayer = L.tileLayer("/portal/capas/dest2019gw/{z}/{x}/{y}.png", {
  minZoom: 4,
  maxZoom: 16,
  opacity: 1,
});
var ESRI_topo = L.tileLayer(
  "https://server.arcgisonline.com/ArcGIS/rest/services/World_Topo_Map/MapServer/tile/{z}/{y}/{x}"
);
var Stamen_Watercolor = L.tileLayer(
  "https://stamen-tiles-{s}.a.ssl.fastly.net/watercolor/{z}/{x}/{y}.{ext}",
  {
    attribution:
      'Map tiles by <a href="http://stamen.com">Stamen Design</a>, <a href="http://creativecommons.org/licenses/by/3.0">CC BY 3.0</a> &mdash; Map data &copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
    subdomains: "abcd",
    minZoom: 1,
    maxZoom: 16,
    ext: "jpg",
  }
);
var wmsINEGI = L.tileLayer.wms(
  "https://gaiamapas.inegi.org.mx/mdmCache/service/wms?&service?",
  {
    layers: "MapaBaseTopograficov61_consombreado",
  }
);

function blank() {
  var layer = new L.Layer();
  layer.onAdd = layer.onRemove = function () {};
  return layer;
}

var baseMaps = {
  "Open Street Maps": osm,
  "Google Calles": googleRoadMap,
  "Google Satélite": googleSatellite,
  "Google Relieve": googleTerrain,
  "Google Tráfico-Calles": googleTrafficRoads,
  "Google Tráfico-Satelite": googleTrafficSatellite,
  "Google Tráfico-Relieve": googleTrafficTerrain,
  "Google Bicicleta-Satelite": googleBikeSatellite,
  "Google Bicicleta-Calles": googleBikeRoads,
  "Google Bicicleta-Relieve": googleBikeTerrain,
};
if (bingAerial) {
  baseMaps["Bing Satélite"] = bingAerial;
}
Object.assign(baseMaps, {
  "Yandex Satélite": yandex,
  "ESRI Satélite": ESRI_satellite,
  "ESRI Topo": ESRI_topo,
  INEGI: wmsINEGI,
  "Carto Dark": cartoDark,
  "Carto Positron": cartoPositron,
  "Stamen Acuarela": Stamen_Watercolor,
  "En blanco": blank(),
});

var group = L.featureGroup([]).addTo(map2d);

var overlays = {
  Grupos: group,
};

var layerControl = L.control.layers(baseMaps).addTo(map2d);

var activeBaseLayer = osm;
var center, zoom;

let radios = Array.from(document.getElementsByName("leaflet-base-layers_114"));

map2d.on("baselayerchange", function (e) {
  activeBaseLayer = e.layer;

  center = map2d.getCenter();
  zoom = map2d.getZoom();

  if (e.name === "Yandex Satélite") {
    map2d.options.crs = L.CRS.EPSG3395;
  } else {
    map2d.options.crs = L.CRS.EPSG3857;
  }

  map2d._resetView(center, zoom);

  if (map2d.hasLayer(group)) {
    activeBaseLayer.bringToBack();
    group.bringToFront();
  }

  radios.forEach((radio, index) => {
    if (radio.checked) {
      if (index > 9) {
        var opcionesActiva = Array.from(document.querySelectorAll(".isActive"));
        opcionesActiva.forEach((opcionesActiva) => {
          opcionesActiva.classList.remove("isActive");
        });
      }
    }
  });

  // reorganizar();
});

L.Control.NewTools = L.Control.extend({
  onAdd: function (map) {
    const $tools = L.DomUtil.get("tools");
    return $tools;
  },

  onRemove: function (map) {
    // Nothing to do here
  },
});

L.Control.BaseLayerTools = L.Control.extend({
  onAdd: function (map) {
    const $toolsBaseLayers = L.DomUtil.get("tools-baseLayers");
    return $toolsBaseLayers;
  },

  onRemove: function (map) {
    // Nothing to do here
  },
});

L.control.newtools = function (opts) {
  return new L.Control.NewTools(opts);
};

// L.control.baselayertools = function (opts) {
//   return new L.Control.BaseLayerTools(opts);
// };

L.control.newtools({ position: "topright" }).addTo(map2d);
// L.control.baselayertools({ position: "bottomright" }).addTo(map2d);

L.control
  .locate({
    strings: {
      title: "Geolocalizar mi ubicación",
      popup: "Estás a {distance} {unit} de este punto",
      outsideMapBoundsMsg: "Parece que estás fuera del área del mapa",
    },
    locateOptions: {
      enableHighAccuracy: true,
      setView: true,
      maxZoom: 18,
    },
    position: "bottomright",
    onLocationError: function (err) {
      var msg = "No se pudo obtener tu ubicación.";
      if (err && err.code === 1) {
        msg =
          "Permiso de ubicación denegado.\n\nEn el celular: Ajustes → Apps → Catastro Mexicali → Permisos → Ubicación → Permitir.";
      } else if (err && err.code === 2) {
        msg = "No se pudo determinar la ubicación. Activa el GPS e intenta de nuevo.";
      } else if (err && err.code === 3) {
        msg = "Tiempo de espera agotado al obtener la ubicación.";
      }
      window.alert(msg);
    },
  })
  .addTo(map2d);

let $locateBtn = document.getElementById("locateBtn");
let $realLocateBtn = document.querySelector(".leaflet-bar-part-single");

safeOn($locateBtn, "click", () => {
  if ($realLocateBtn) $realLocateBtn.click();
  $locateBtn.classList.toggle("locationIsActive");
});

var zoomOptions = {
  zoomInText: "+",
  zoomOutText: "-",
  position: "topright",
};

// Creating zoom control
var zoom = L.control.zoom(zoomOptions);

zoom.addTo(map2d);

var style = {
  color: "red",
  opacity: 1.0,
  fillOpacity: 1.0,
  weight: 2,
  clickable: false,
};

$file_input = document.getElementById("shp-upload");

$capasPropias = document.querySelector(".capas-propias");

function addCustomToList(capa) {
  const listItem = document.createElement("div");
  listItem.setAttribute("class", "sortableItem");
  listItem.innerHTML = `
      <a href="#" onclick="removeCustomLayer('${capa}')" class="remove" id='${capa}'/>
        <span class="material-symbols-outlined">block</span>
      </a>
      <p class="nombre-capa layerName">${capa}</p>
    `;

  const sortable = document.getElementById("sortable");
  const target = sortable || $capasPropias;
  if (target) target.appendChild(listItem);
}

function removeCustomLayer(layerName) {
  if (layerName === "SHP Propio") {
    group.removeLayer(vector);
  } else if (layerName === "KML Propio") {
    group.removeLayer(kml);
    kml = L.kmzLayer().addTo(map2d);
    kml.id = "KMZ Propio";
  } else if (layerName === "KMZ Propio") {
    group.removeLayer(kmz);
    kmz = L.kmzLayer().addTo(map2d);
    kmz.id = "KMZ Propio";
  } else if (layerName === "GeomanGroup") {
    group.removeLayer(geomanGroup);
  }
  const customItem = document.getElementById(layerName);
  if (customItem && customItem.parentElement) customItem.parentElement.remove();
  delete capasActivas[layerName];
  group.bringToFront();
}

safeOn($file_input, "change", function fileValidation(event) {
  let files = event.target.files;

  var filePath = $file_input.value;
  var fileExt = filePath.split(".").pop();

  // Allowing file type
  var allowedExtensions = /(\.zip|\.kml|\.kmz)$/i;

  if (!allowedExtensions.exec(filePath)) {
    alert("Tipo de archivo no valido. Use .zip, .kml o .kmz");
    $file_input.value = "";
    return false;
  }

  if (fileExt === "zip") {
    function handleZipFile(file) {
      var reader = new FileReader();
      reader.onload = function () {
        if (reader.readyState != 2 || reader.error) {
          return;
        } else {
          convertToLayer(reader.result);
        }
      };
      reader.readAsArrayBuffer(file);
    }

    function convertToLayer(buffer) {
      shp(buffer).then(function (geojson) {
        //More info: https://github.com/calvinmetcalf/shapefile-js
        vector = L.shapefile(geojson).addTo(map2d);
        vector.id = "SHP Propio";
      });
    }

    handleZipFile(files[0]);

    setTimeout(() => {
      map2d.fitBounds(vector.getBounds());
      group.addLayer(vector),
        (capasActivas["SHP Propio"] = {
          capa: vector,
          order: ++nActiva,
        }),
        addCustomToList("SHP Propio", vector),
        group.bringToFront();
    }, "1000");
  }
  if (fileExt === "kml") {
    kml.load(URL.createObjectURL(files[0]));
    setTimeout(() => {
      map2d.fitBounds(kml.getBounds());
      group.addLayer(kml),
        (capasActivas["KML Propio"] = {
          capa: kml,
          order: ++nActiva,
        }),
        addCustomToList("KML Propio", kml),
        group.bringToFront();
    }, "500");
  }

  if (fileExt === "kmz") {
    kmz.load(URL.createObjectURL(files[0]));
    setTimeout(() => {
      map2d.fitBounds(kmz.getBounds());
      group.addLayer(kmz),
        (capasActivas["KMZ Propio"] = {
          capa: kmz,
          order: ++nActiva,
        }),
        addCustomToList("KMZ Propio", kmz),
        group.bringToFront();
    }, "2000");
  }
});

let $visibleUploadBtn = document.getElementById("visible-uploadBtn");

safeOn($visibleUploadBtn, "click", () => {
  if (typeof window.mxliCan === "function" && !window.mxliCan("map.upload")) {
    window.alert("Su rol no tiene permiso para subir archivos.");
    return;
  }
  const input = document.getElementById("shp-upload");
  if (input) input.click();
});

// const WMSurl = "https://geoserver.implantijuana.gob.mx/geoserver/implan/wms";

const CAPAS_CONSULTABLES_WMS = [
  "geonode:predios_mexicali_2025",
  "geonode:predios_baldios",
  "geonode:predios_con_uso",
  "geonode:colonias",
  "geonode:usos_prop_au40",
  "geonode:zonas_homogeneas_2017_2026_prop",
];

function esCapaConsultable(capa) {
  if (
    !(
      capa &&
      capa.wmsParams &&
      CAPAS_CONSULTABLES_WMS.includes(capa.wmsParams.layers)
    )
  ) {
    return false;
  }
  if (typeof window.mxliCan === "function" && !window.mxliCan("map.info")) {
    return false;
  }
  if (typeof window.mxliCanLayer === "function") {
    var full = String(capa.wmsParams.layers || "");
    var key = full.indexOf(":") >= 0 ? full.split(":").pop() : full;
    if (!window.mxliCanLayer(key)) return false;
  }
  return true;
}

function getCapasConsultablesActivas() {
  const activas = [];

  if (typeof group === "undefined") return activas;

  group.eachLayer(function (layer) {
    if (esCapaConsultable(layer)) {
      activas.push(layer);
    }
  });

  return activas.sort(function (a, b) {
    return (b.options.zIndex || 0) - (a.options.zIndex || 0);
  });
}

function actualizarCapaActivaConsulta() {
  const activas = getCapasConsultablesActivas();
  window.capaActiva = activas.length > 0 ? activas[0] : null;
}

window.esCapaConsultable = esCapaConsultable;
window.getCapasConsultablesActivas = getCapasConsultablesActivas;
window.actualizarCapaActivaConsulta = actualizarCapaActivaConsulta;

function pickBestFeature(features) {
  if (!features || !features.length) return null;

  const predio = features.find(function (feature) {
    return feature.properties && feature.properties.clavecatas;
  });
  if (predio) return predio;

  const colonia = features.find(function (feature) {
    const props = feature.properties || {};
    return props.nombre || props.colonia || props.NOMBRE || props.COLONIA;
  });
  if (colonia) return colonia;

  return features[0] || null;
}

function wrapFeatureInfo(feature, sourceLayer) {
  if (!feature) return null;
  return {
    properties: feature.properties || {},
    geometry: feature.geometry || null,
    sourceLayer: sourceLayer || "",
  };
}

async function fetchFeatureInfoFromApi(capaLayers, version, point, size) {
  const params = new URLSearchParams({
    layers: capaLayers,
    version: version || "1.1.1",
    bbox: map2d.getBounds().toBBoxString(),
    width: String(size.x),
    height: String(size.y),
    x: String(Math.round(point.x)),
    y: String(Math.round(point.y)),
  });

  const res = await fetch("api/wms-feature-info.php?" + params.toString());
  let data = null;

  try {
    data = await res.json();
  } catch (parseError) {
    throw new Error("Respuesta invalida del servidor al consultar la capa.");
  }

  if (!res.ok) {
    throw new Error((data && data.error) || "Error al consultar la capa.");
  }

  if (!data || !Array.isArray(data.features)) {
    return { type: "FeatureCollection", features: [] };
  }

  return data;
}

async function getFeatureInfo(evt) {
  const capas = getCapasConsultablesActivas();
  if (capas.length === 0) {
    alert(
      "Active Predios, Predios Baldíos, Usos de Suelo Actual, Colonias o Usos de Suelo 2040 para consultar informacion."
    );
    return null;
  }

  const point = map2d.latLngToContainerPoint(evt.latlng, map2d.getZoom());
  const size = map2d.getSize();
  const version = capas[0].wmsParams.version || "1.1.1";

  try {
    // Consultar de arriba hacia abajo para respetar la capa visible superior.
    for (let i = 0; i < capas.length; i++) {
      const capa = capas[i];
      const data = await fetchFeatureInfoFromApi(
        capa.wmsParams.layers,
        capa.wmsParams.version || version,
        point,
        size
      );
      const feature = pickBestFeature(data.features);
      if (feature) {
        return wrapFeatureInfo(feature, capa.wmsParams.layers);
      }
    }

    alert("No se encontro informacion en este punto.");
    return null;
  } catch (error) {
    console.error("GetFeatureInfo:", error);
    alert(
      error && error.message
        ? error.message
        : "Error al consultar la informacion del mapa."
    );
    return null;
  }
}

function ringAreaSqMeters(ring) {
  if (!ring || ring.length < 3) return 0;

  const R = 6378137;
  let area = 0;
  const coords = ring[0][0] === ring[ring.length - 1][0] &&
    ring[0][1] === ring[ring.length - 1][1]
      ? ring
      : ring.concat([ring[0]]);

  for (let i = 0; i < coords.length - 1; i++) {
    const lon1 = (coords[i][0] * Math.PI) / 180;
    const lat1 = (coords[i][1] * Math.PI) / 180;
    const lon2 = (coords[i + 1][0] * Math.PI) / 180;
    const lat2 = (coords[i + 1][1] * Math.PI) / 180;
    area += (lon2 - lon1) * (2 + Math.sin(lat1) + Math.sin(lat2));
  }

  return Math.abs((area * R * R) / 2);
}

function geometryAreaSqMeters(geometry) {
  if (!geometry || !geometry.type || !geometry.coordinates) return 0;

  if (geometry.type === "Polygon") {
    return ringAreaSqMeters(geometry.coordinates[0] || []);
  }

  if (geometry.type === "MultiPolygon") {
    return geometry.coordinates.reduce(function (sum, polygon) {
      return sum + ringAreaSqMeters((polygon && polygon[0]) || []);
    }, 0);
  }

  return 0;
}

function formatAreaM2(value) {
  return Number(value || 0).toLocaleString("es-MX", {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  });
}

function isUsoPredioFeatureInfo(info) {
  if (!info) return false;
  const source = info.sourceLayer || "";
  if (
    source.indexOf("predios_baldios") !== -1 ||
    source.indexOf("predios_con_uso") !== -1
  ) {
    return true;
  }
  const props = info.properties || info;
  return !!(
    props &&
    props.clavecatas &&
    (Object.prototype.hasOwnProperty.call(props, "uso_generi") ||
      Object.prototype.hasOwnProperty.call(props, "descripcio"))
  );
}

function usoPredioPopupTitle(info) {
  const source = (info && info.sourceLayer) || "";
  if (source.indexOf("predios_con_uso") !== -1) {
    return "Información de uso de suelo actual";
  }
  if (source.indexOf("predios_baldios") !== -1) {
    return "Información del baldío";
  }
  return "Información del predio";
}

function escapeHtml(value) {
  return String(value == null ? "" : value)
    .replace(/&/g, "&amp;")
    .replace(/</g, "&lt;")
    .replace(/>/g, "&gt;");
}

function createUsoPredioPopup(info) {
  const props = info.properties || {};
  const clave = props.clavecatas || "";
  const descripcion = props.descripcio || "";
  const usoGenerico = props.uso_generi || "";
  let superficie = Number(props.superficie || props.Superficie || 0);

  if (!superficie || !isFinite(superficie)) {
    superficie = geometryAreaSqMeters(info.geometry);
  }

  const div = document.createElement("div");
  div.className = "mxli-feature-popup";
  div.innerHTML =
    '<div class="mxli-feature-popup__head">' +
    escapeHtml(usoPredioPopupTitle(info)) +
    "</div>" +
    '<div class="mxli-feature-popup__body">' +
    "<div><b>Clave Catastral:</b> " +
    escapeHtml(clave) +
    "</div>" +
    "<div><b>Descripción:</b> " +
    escapeHtml(descripcion) +
    "</div>" +
    "<div><b>Superficie:</b> " +
    formatAreaM2(superficie) +
    " m²</div>" +
    "<div><b>Uso genérico:</b> " +
    escapeHtml(usoGenerico) +
    "</div>" +
    "</div>";

  return div;
}

function getFeatureInfoUrl(evt, capa = window.capaActiva) {
  // Construct a GetFeatureInfo request URL given a point
  var point = map2d.latLngToContainerPoint(evt, map2d.getZoom()),
    size = map2d.getSize(),
    params = {
      request: "GetFeatureInfo",
      service: "WMS",
      srs: "EPSG:4326",
      styles: capa.wmsParams.styles,
      transparent: capa.wmsParams.transparent,
      version: capa.wmsParams.version,
      format: capa.wmsParams.format,
      bbox: map2d.getBounds().toBBoxString(),
      height: size.y,
      width: size.x,
      layers: capa.wmsParams.layers,
      query_layers: capa.wmsParams.layers,
      info_format: "application/json",
    };

  params[params.version === "1.3.0" ? "i" : "x"] = Math.round(point.x);
  params[params.version === "1.3.0" ? "j" : "y"] = Math.round(point.y);

  return capa._url + L.Util.getParamString(params, capa._url, true);
}

function createDataTable(data) {
  const props = data.properties || data;
  const clave = props.clavecatas || "";
  const homoclave = clave.substring(0, 2);
  const manzana = props.manzana || clave.substring(2, 5);
  const lote = props.lote || clave.substring(5, 8);
  const superficie = formatAreaM2(props.superficie || 0);

  const div = document.createElement("div");
  div.className = "mxli-feature-popup";
  div.innerHTML = `
    <div class="mxli-feature-popup__head">Información del predio</div>
    <div class="mxli-feature-popup__body">
      <div><b>Clave Catastral:</b> ${clave}</div>
      <div><b>Homoclave:</b> ${homoclave}</div>
      <div><b>Manzana:</b> ${manzana}</div>
      <div><b>Lote:</b> ${lote}</div>
      <div><b>Sup. Cart:</b> ${superficie} m²</div>
    </div>
  `;

  return div;
}

const FEATURE_LABELS = {
  clavecatas: "Clave Catastral",
  nombre: "Nombre",
  colonia: "Colonia",
  NOMBRE: "Nombre",
  COLONIA: "Colonia",
  uso: "Uso de suelo",
  descripcion: "Descripción",
  descripcio: "Descripción",
  uso_generi: "Uso genérico",
  superficie: "Superficie",
  zona: "Zona",
  secsub: "Secsub",
};

const FEATURE_SKIP_KEYS = new Set([
  "geom",
  "the_geom",
  "shape",
  "geometry",
  "boundedBy",
]);

const ZONA_HOMOGENEA_VALUE_FIELDS = [
  "valor2017",
  "valor2018",
  "valor2019",
  "valor2020",
  "valor2021",
  "valor2022",
  "valor2023",
  "valor2024",
  "valor2025",
  "valor2026",
];

// En GeoServer el valor 2026 viene como prop_2026 (no valor2026)
const ZONA_HOMOGENEA_FIELD_ALIASES = {
  valor2026: ["prop_2026", "valor_2026", "prop2026"],
  valor2022: ["valor_2022"],
};

function isZonaHomogeneaFeatureInfo(info) {
  if (!info) return false;
  const source = String(info.sourceLayer || "");
  if (source.indexOf("zonas_homogeneas_2017_2026_prop") !== -1) {
    return true;
  }
  const props = info.properties || info;
  return !!(
    props &&
    Object.prototype.hasOwnProperty.call(props, "zona") &&
    (Object.prototype.hasOwnProperty.call(props, "valor2025") ||
      Object.prototype.hasOwnProperty.call(props, "prop_2026") ||
      Object.prototype.hasOwnProperty.call(props, "valor2026"))
  );
}

function formatValorM2(value) {
  if (value === null || value === undefined || value === "") return "—";
  const num = Number(String(value).replace(/[^0-9.-]/g, ""));
  if (!isFinite(num)) {
    return "$" + escapeHtml(value) + "/m2";
  }
  return (
    "$" +
    num.toLocaleString("es-MX", {
      minimumFractionDigits: 2,
      maximumFractionDigits: 2,
    }) +
    "/m2"
  );
}

function pickZonaHomogeneaProp(props, field) {
  if (!props) return null;
  if (props[field] !== null && props[field] !== undefined && props[field] !== "") {
    return props[field];
  }
  const aliases = ZONA_HOMOGENEA_FIELD_ALIASES[field] || [];
  for (var i = 0; i < aliases.length; i++) {
    var key = aliases[i];
    if (props[key] !== null && props[key] !== undefined && props[key] !== "") {
      return props[key];
    }
  }
  return props[field];
}

function normalizeZonaLabel(zona) {
  var text = String(zona == null ? "" : zona).trim();
  // En la capa a veces llega "MXHAFPA = $ 4050" (el 2026 pegado); dejamos solo el código
  var m = text.match(/^(.+?)\s*=\s*\$\s*/);
  return m ? m[1].trim() : text;
}

function createZonaHomogeneaPopup(info) {
  const props = info.properties || {};
  const zona = normalizeZonaLabel(props.zona);
  const secsub = props.secsub != null ? props.secsub : "";
  const valor2026 = pickZonaHomogeneaProp(props, "valor2026");
  const valor2026Fmt = formatValorM2(valor2026);

  let zonaLine = "<div><b>Zona:</b> " + escapeHtml(zona);
  if (valor2026 !== null && valor2026 !== undefined && valor2026 !== "") {
    zonaLine +=
      '<span style="color:#1a5fd0;font-weight:700">=' +
      valor2026Fmt +
      "</span>";
  }
  zonaLine += "</div>";

  let body =
    zonaLine +
    "<div><b>Secsub:</b> " +
    escapeHtml(secsub) +
    "</div>";

  ZONA_HOMOGENEA_VALUE_FIELDS.forEach(function (field) {
    const year = field.replace("valor", "");
    body +=
      "<div><b>Valor " +
      year +
      ":</b> " +
      formatValorM2(pickZonaHomogeneaProp(props, field)) +
      "</div>";
  });

  const div = document.createElement("div");
  div.className = "mxli-feature-popup";
  div.innerHTML =
    '<div class="mxli-feature-popup__head">Zona homogénea</div>' +
    '<div class="mxli-feature-popup__body">' +
    body +
    "</div>";
  return div;
}

function createFeaturePopup(data) {
  if (isUsoPredioFeatureInfo(data)) {
    return createUsoPredioPopup(data);
  }

  if (isZonaHomogeneaFeatureInfo(data)) {
    return createZonaHomogeneaPopup(data);
  }

  const props = data.properties || data;
  if (props && props.clavecatas) {
    return createDataTable(data);
  }

  const div = document.createElement("div");
  let body = "";
  const entries = Object.entries(props || {});

  entries.forEach(function (entry) {
    const key = entry[0];
    const value = entry[1];
    if (FEATURE_SKIP_KEYS.has(key)) return;
    if (value === null || value === undefined || value === "") return;

    const label = FEATURE_LABELS[key] || key.replace(/_/g, " ");
    body +=
      "<div><b>" +
      label +
      ":</b> " +
      escapeHtml(value) +
      "</div>";
  });

  if (!body) {
    body = "<div>Sin atributos disponibles para este punto.</div>";
  }

  div.className = "mxli-feature-popup";
  div.innerHTML =
    '<div class="mxli-feature-popup__head">Información</div>' +
    '<div class="mxli-feature-popup__body">' +
    body +
    "</div>";

  return div;
}

function mxliPopupOptions() {
  const mobile = window.matchMedia("(max-width: 900px)").matches;
  return mobile
    ? { maxWidth: 260, minWidth: 160, maxHeight: 160, autoPanPadding: [48, 72] }
    : { maxWidth: 320, minWidth: 200, maxHeight: 350 };
}

var popup = L.popup(mxliPopupOptions());

window.capaActiva = window.capaActiva || null;

let $getFeatureInfoBtn = document.querySelector("#getFeatureInfoBtn");

let $panMapBtn = document.querySelector("#panMapBtn");

safeOn($panMapBtn, "click", () => {
  var state = document
    .querySelector("#getFeatureInfoBtn")
    .classList.contains("btnIsActive");
  if (state) {
    map2d.off("click");
    map2d.dragging.enable();
    L.DomUtil.removeClass(map2d._container, "crosshair-cursor-enabled");
    document.querySelector("#panMapBtn").classList.add("btnIsActive");
    document
      .querySelector("#getFeatureInfoBtn")
      .classList.remove("btnIsActive");
  }
});

function desactivarInformacion(state) {
  if (state) {
    map2d.off("click");
    map2d.dragging.enable();
    L.DomUtil.removeClass(map2d._container, "crosshair-cursor-enabled");
    document.querySelector("#panMapBtn").classList.add("btnIsActive");
  } else {
    L.DomUtil.addClass(map2d._container, "crosshair-cursor-enabled");
    document.querySelector("#panMapBtn").classList.remove("btnIsActive");
    map2d.dragging.disable();
    map2d.on("click", async (e) => {
      let featureInfo = await getFeatureInfo(e);
      if (featureInfo) {
        let table = createFeaturePopup(featureInfo);
        popup.setLatLng(e.latlng).setContent(table).openOn(map2d);
      }
    });
  }
}

safeOn($getFeatureInfoBtn, "click", (e) => {
  e.stopPropagation();
  if (getCapasConsultablesActivas().length === 0) {
    alert(
      "Active Predios, Predios Baldíos, Usos de Suelo Actual, Colonias o Usos de Suelo 2040 para consultar informacion."
    );
    return false;
  }

  var state = e.target.classList.contains("btnIsActive");
  e.target.classList.toggle("btnIsActive");
  desactivarInformacion(state);
});

function selectBaseLayer(baseLayer) {
  Array.from(document.querySelectorAll(".leaflet-control-layers-selector"))[
    baseLayer
  ].click();
}

let $optionSatellite = document.querySelector(".google-toggle");
let $optionTerrain = document.querySelector(".optionTerrain");
let $optionTraffic = document.querySelector(".optionTraffic");
let $optionBike = document.querySelector(".optionBike");
const InputsBaseMaps = Array.from(
  document.querySelectorAll(".leaflet-control-layers-list input")
);

$baseLayerControl = document.querySelector(".baseLayer-control");
$googleToggle = document.querySelector(".google-toggle");
$googleOptions = document.querySelector(".google-options");

safeOn($baseLayerControl, "mousemove", () => {
  map2d.doubleClickZoom.disable();
});

safeOn($baseLayerControl, "mouseover", () => {
  $baseLayerControl.style.maxWidth = "none";
  $googleOptions.classList.add("isVisible");
  $baseLayerControl.style.maxWidth = "none";
});
safeOn($baseLayerControl, "mouseleave", () => {
  $googleOptions.classList.remove("isVisible");
  // $baseLayerControl.style.overflow = "hidden";
  setTimeout(() => {
    map2d.doubleClickZoom.enable();
    $baseLayerControl.style.maxWidth = "5rem";
  }, 500); // Without the timeout the map will still zoom in on entity double-click
});

safeOn($optionTerrain, "click", (event) => {
  $googleToggle.classList.add("googleSatelite");
  terrainStatus = $optionTerrain.classList.contains("isActive");
  traficStatus = $optionTraffic.classList.contains("isActive");
  bikeStatus = $optionBike.classList.contains("isActive");
  if (traficStatus) {
    if (!terrainStatus) {
      InputsBaseMaps[6].click();
    } else {
      InputsBaseMaps[4].click();
    }
  } else {
    if (bikeStatus) {
      if (terrainStatus) {
        InputsBaseMaps[8].click();
      } else {
        InputsBaseMaps[9].click();
      }
    } else {
      if (terrainStatus) {
        InputsBaseMaps[1].click();
      } else {
        InputsBaseMaps[3].click();
      }
    }
  }

  $optionTerrain.classList.toggle("isActive");
});

safeOn($optionTraffic, "click", (event) => {
  if (!$googleToggle.classList.contains("googleSatelite")) {
    if ($optionTraffic.classList.contains("isActive")) {
      InputsBaseMaps[2].click();
    } else {
      InputsBaseMaps[5].click();
    }
  }

  if (
    $googleToggle.classList.contains("googleSatelite") &&
    !$optionTerrain.classList.contains("isActive")
  ) {
    if ($optionTraffic.classList.contains("isActive")) {
      InputsBaseMaps[1].click();
    } else {
      InputsBaseMaps[4].click();
    }
  }

  if ($optionTerrain.classList.contains("isActive")) {
    if ($optionTraffic.classList.contains("isActive")) {
      InputsBaseMaps[3].click();
    } else {
      InputsBaseMaps[6].click();
    }
  }

  if ($optionTerrain.classList.contains("isActive")) {
    if ($optionTraffic.classList.contains("isActive")) {
      InputsBaseMaps[3].click();
    } else {
      InputsBaseMaps[6].click();
    }
  }

  $optionBike.classList.remove("isActive");
  event.target.classList.toggle("isActive");
});

safeOn($optionBike, "click", (event) => {
  event.stopPropagation();
  $optionTraffic.classList.remove("isActive");
  event.target.classList.toggle("isActive");
});

function toggleGoogleMap(event) {
  $spanDescripcion = document.querySelector(".google-toggle span");

  if ($googleToggle.classList.contains("googleSatelite")) {
    $spanDescripcion.innerText = "Calles";
  } else {
    $spanDescripcion.innerText = "Sátelite";
  }

  if ($optionTraffic.classList.contains("isActive")) {
    if ($googleToggle.classList.contains("googleSatelite")) {
      InputsBaseMaps[5].click();
    } else {
      InputsBaseMaps[4].click();
    }
  } else {
    if ($googleToggle.classList.contains("googleSatelite")) {
      InputsBaseMaps[2].click();
    } else {
      InputsBaseMaps[1].click();
    }
  }

  if ($optionTerrain.classList.contains("isActive")) {
    $optionTerrain.classList.remove("isActive");
  }
  $googleToggle.classList.toggle("googleSatelite");

  if ($optionBike.classList.contains("isActive")) {
    if ($googleToggle.classList.contains("googleSatelite")) {
      InputsBaseMaps[8].click();
    } else {
      InputsBaseMaps[7].click();
    }
  }
}

safeOn($optionBike, "click", () => {
  if ($optionBike.classList.contains("isActive")) {
    if ($googleToggle.classList.contains("googleSatelite")) {
      if ($optionTerrain.classList.contains("isActive")) {
        InputsBaseMaps[9].click();
      } else {
        InputsBaseMaps[8].click();
      }
    } else {
      InputsBaseMaps[7].click();
    }
  } else {
    if ($googleToggle.classList.contains("googleSatelite")) {
      if ($optionTerrain.classList.contains("isActive")) {
        InputsBaseMaps[3].click();
      } else {
        InputsBaseMaps[1].click();
      }
    } else {
      InputsBaseMaps[2].click();
    }
  }
});

let $verMasDiv = document.querySelector(".moreOptions");
let $moreOptions = document.querySelector(".moreOptions2");

function closeMoreOptions() {
  $googleOptions.style.display = "inline-flex";
  $moreOptions.style.display = "none";
}

safeOn($verMasDiv, "click", () => {
  $googleOptions.style.display = "none";
  $baseLayerControl.style.overflow = "visible";
  $baseLayerControl.style.maxWidth = "none";
  $moreOptions.style.display = "block";

  $moreOptions.appendChild(
    document.querySelector(".leaflet-control-layers-list")
  );
  // $tools.style.paddingBottom = '5.75rem';
});

for (let i = 1; i <= 9; i++) {
  if (InputsBaseMaps[i] && InputsBaseMaps[i].parentElement) {
    InputsBaseMaps[i].parentElement.style.display = "none";
  }
}

let geomanGroup = L.layerGroup().addTo(map2d);

const validGeometries = ["Circle", "Line", "Rectangle", "Polygon"];
// let measurement = false;
// creates new actions
const actions = [
  // uses the default 'cancel' action
  "cancel",
  // creates a new action that has text, no click event
  {
    text: "Ocultar Medidas",
    onClick: () => {
      let geomanLayers = map2d.pm.getGeomanLayers();

      geomanLayers.forEach((layer) => {
        if (validGeometries.includes(layer.pm._shape)) {
          layer.hideMeasurements();
        }
      });
      map2d.pm.setGlobalOptions({
        templineStyle: {
          showMeasurements: false,
        },
        hintlineStyle: {
          showMeasurements: false,
        },
        pathOptions: {
          showMeasurements: false,
        },
      });
      measurement = false;
    },
  },
  // creates a new action with text and a click event
  {
    text: "Ver Medidas",
    onClick: () => {
      console.log("ver medidas");
      let geomanLayers = map2d.pm.getGeomanLayers();
      geomanLayers.forEach((layer) => {
        if (validGeometries.includes(layer.pm._shape)) {
          layer.showMeasurements();
        }
      });
      measurement = true;
      map2d.pm.setGlobalOptions({
        templineStyle: {
          showMeasurements: true,
        },
        hintlineStyle: {
          showMeasurements: true,
        },
        pathOptions: {
          showMeasurements: true,
        },
      });
    },
  },
];

map2d.pm.addControls({
  position: "topleft",
  drawCircleMarker: false,
  drawText: false,
  rotateMode: true,
});
map2d.pm.setLang("es");

map2d.pm.Toolbar.copyDrawControl("drawText", {
  name: "Texto",
  block: "draw",
  title: "Agregar texto",
});

// map2d.pm.Toolbar.copyDrawControl("rotateMode", {
//   name: "Rotar",
//   block: "edit",
//   title: "Rotar elemento",
// });

map2d.pm.Toolbar.createCustomControl({
  name: "Medir",
  block: "draw",
  title: "Medir",
  className: "icon-Regla",
  actions: actions,
});

map2d.pm.setGlobalOptions({ layerGroup: geomanGroup });

$editToolsContainer = document.querySelector("#editToolsContainer");
$drawTools = document.querySelector(".leaflet-pm-draw");
$editTools = document.querySelector(".leaflet-pm-edit");

if ($editToolsContainer && $drawTools) $editToolsContainer.appendChild($drawTools);
if ($editToolsContainer && $editTools) $editToolsContainer.appendChild($editTools);

$editToolsDisplayBtn = document.querySelector("#editToolsDisplayBtn");

safeOn($editToolsDisplayBtn, "click", () => {
  $editToolsDisplayBtn.classList.toggle("btnIsActive");
  $editToolsContainer.classList.toggle("displayEditTools");
});

// Agregar título rotar al boton rotar
let rotateTool = document.querySelector(".leaflet-pm-icon-rotate");
if (rotateTool && rotateTool.parentElement && rotateTool.parentElement.parentElement) {
  rotateTool.parentElement.parentElement.setAttribute("title", "Rotar polígonos");
}

// var printTopol = L.control
//   .browserPrint({ position: "topleft", title: "Print ..." })
//   .addTo(map2d);

// var customActionToPrint = function (context, mode) {
//   return function () {
//     window.alert("We are printing the MAP. Let's do Custom print here!");
//     context._printMode(mode);
//   };
// };

// var options = {
//   documentTitle: "Map printed using leaflet.browser.print plugin",
//   printLayer: googleRoadMap,
//   closePopupsOnPrint: false,
//   manualMode: false,
// };
// var browserPrint = L.browserPrint(map2d, options);

// L.control.browserPrint().addTo(map2d);

// var browserControl = L.control
//   .browserPrint({ position: "topleft", title: "Print ..." })
//   .addTo(map);

$btnPrintAcept = document.querySelector("#printAcept");
$btnPrintCancel = document.querySelector("#printCancel");
$mapTitleInput = document.querySelector("#mapTitle-input");

safeOn($btnPrintCancel, "click", cancelPrint);
safeOn($btnPrintAcept, "click", () => {
  printMap(printOption);
});

let printOption;

function cancelPrint() {
  document.querySelector("#mapTitle-dialog").close();
  // browserControl.cancel();
}

function setMapTitle(mode) {
  if (typeof window.mxliCan === "function" && !window.mxliCan("map.print")) {
    window.alert("Su rol no tiene permiso para imprimir.");
    return;
  }
  printOption = mode;
  const dialog = document.querySelector("#mapTitle-dialog");
  if (dialog && typeof dialog.showModal === "function") {
    dialog.showModal();
  }
}
window.setMapTitle = setMapTitle;

function buildPrintHeader(mapTitle) {
  const title = String(mapTitle || "").trim() || "TÍTULO DEL PLANO";
  const escudoUrl = new URL("escudo-mexicali.png", window.location.href).href;

  return {
    enabled: true,
    size: "26mm",
    overTheMap: false,
    text:
      '<table width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;width:100%;font-family:Arial,Helvetica,sans-serif;">' +
      '<tr>' +
      '<td bgcolor="#6f2f3d" style="' +
      "background-color:#6f2f3d;" +
      "border-bottom:3px solid #d6aa2f;padding:8px 14px;" +
      '">' +
      '<table width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;width:100%;"><tr>' +
      '<td style="vertical-align:middle;width:1%;white-space:nowrap;padding-right:12px;">' +
      '<table cellpadding="0" cellspacing="0" style="border-collapse:collapse;"><tr>' +
      '<td style="vertical-align:middle;padding-right:10px;">' +
      '<img src="' +
      escudoUrl +
      '" alt="Escudo de Mexicali" width="40" height="48" style="' +
      "display:block;height:48px;width:auto;max-width:40px;background:#ffffff;border-radius:6px;padding:3px 5px;" +
      '">' +
      "</td>" +
      '<td style="vertical-align:middle;color:#ffffff;line-height:1.1;">' +
      '<div style="font-size:10px;font-weight:700;letter-spacing:.4px;color:#ffffff;">DIRECCIÓN DE ADMINISTRACIÓN URBANA</div>' +
      '<div style="font-size:18px;font-weight:900;letter-spacing:.8px;margin-top:2px;color:#ffffff;">JEFATURA DE CATASTRO</div>' +
      '<div style="font-size:11px;font-weight:700;margin-top:3px;color:#ffffff;">Mexicali, Baja California</div>' +
      "</td></tr></table>" +
      "</td>" +
      '<td style="' +
      "vertical-align:middle;text-align:right;color:#ffffff;font-size:18px;font-weight:900;" +
      "letter-spacing:.6px;text-transform:uppercase;padding-left:16px;text-shadow:0 1px 2px rgba(0,0,0,.35);" +
      '">' +
      escapeHtml(title) +
      "</td>" +
      "</tr></table>" +
      "</td></tr></table>",
  };
}

function buildPrintFooter() {
  const fecha = new Date().toLocaleString("es-MX", {
    dateStyle: "medium",
    timeStyle: "short",
  });
  return {
    enabled: true,
    size: "9mm",
    overTheMap: false,
    text:
      '<table width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;width:100%;font-family:Arial,Helvetica,sans-serif;">' +
      '<tr><td bgcolor="#6f2f3d" style="' +
      "background-color:#6f2f3d;border-top:2px solid #d6aa2f;color:#ffffff;" +
      "font-size:10px;font-weight:600;padding:4px 12px;text-align:center;" +
      '">' +
      "localizacion.geomexicali.info · " +
      escapeHtml(fecha) +
      "</td></tr></table>",
  };
}

function ensureMxliPrintFrameStyle() {
  var style = document.getElementById("mxli-print-frame-css");
  if (style) return style;

  style = document.createElement("style");
  style.id = "mxli-print-frame-css";
  style.textContent =
    ".grid-print-container{" +
    "box-sizing:border-box!important;" +
    "border:8px solid #6f2f3d!important;" +
    "outline:2px solid #d6aa2f!important;" +
    "outline-offset:-10px!important;" +
    "background:#fff!important;" +
    "overflow:hidden!important;" +
    "}" +
    ".grid-print-container .print-header," +
    ".grid-print-container .print-footer{" +
    "margin:0!important;padding:0!important;width:100%!important;" +
    "}" +
    ".grid-print-container .print-header>*," +
    ".grid-print-container .print-footer>*{" +
    "margin:0!important;" +
    "}" +
    ".leaflet-browser-print--portrait," +
    ".leaflet-browser-print--landscape," +
    ".leaflet-browser-print--auto," +
    ".leaflet-browser-print--custom{" +
    "box-sizing:border-box!important;" +
    "}" +
    "@media print{" +
    "body{-webkit-print-color-adjust:exact!important;print-color-adjust:exact!important;}" +
    ".grid-print-container{" +
    "border:8px solid #6f2f3d!important;" +
    "outline:2px solid #d6aa2f!important;" +
    "outline-offset:-10px!important;" +
    "-webkit-print-color-adjust:exact!important;print-color-adjust:exact!important;" +
    "}" +
    "}";
  document.head.appendChild(style);
  return style;
}

function printMap(mode) {
  const mapTitle = (($mapTitleInput && $mapTitleInput.value) || "").trim();
  const header = buildPrintHeader(mapTitle);
  const footer = buildPrintFooter();
  ensureMxliPrintFrameStyle();

  const commonOptions = {
    documentTitle: mapTitle || "Mapa catastral Mexicali",
    closePopupsOnPrint: false,
    header: header,
    footer: footer,
    margin: { top: 6, right: 6, bottom: 6, left: 6 },
  };

  const browserPrint = L.browserPrint(map2d, {
    documentTitle: commonOptions.documentTitle,
  });

  switch (mode) {
    case "vertical":
      browserPrint.print(
        L.BrowserPrint.Mode.Portrait("Letter", {
          documentTitle: commonOptions.documentTitle,
          closePopupsOnPrint: false,
          header: header,
          footer: footer,
          margin: commonOptions.margin,
          scale: 1,
          pageSize: "Letter",
          orientation: "Portrait",
        })
      );
      break;
    case "horizontal":
      browserPrint.print(
        L.BrowserPrint.Mode.Landscape("Letter", {
          documentTitle: commonOptions.documentTitle,
          closePopupsOnPrint: false,
          header: header,
          footer: footer,
          margin: commonOptions.margin,
          scale: 1,
          pageSize: "Letter",
          orientation: "Landscape",
        })
      );
      break;
    case "custom":
      browserPrint.print(
        L.BrowserPrint.Mode.Custom("Letter", {
          documentTitle: commonOptions.documentTitle,
          closePopupsOnPrint: false,
          header: header,
          footer: footer,
          margin: commonOptions.margin,
          pageSize: "Letter",
          orientation: "Landscape",
        })
      );
      break;
    case "tabloide":
      browserPrint.print(
        L.BrowserPrint.Mode.Landscape("Tabloid", {
          documentTitle: commonOptions.documentTitle,
          closePopupsOnPrint: false,
          header: header,
          footer: footer,
          margin: commonOptions.margin,
          title: "Tabloide",
          pageSize: "Tabloid",
          orientation: "Landscape",
        })
      );
      break;
    default:
      console.warn("Modo de impresión desconocido:", mode);
  }

  const titleDialog = document.querySelector("#mapTitle-dialog");
  if (titleDialog && typeof titleDialog.close === "function") {
    titleDialog.close();
  }
  if ($mapTitleInput) {
    $mapTitleInput.value = "";
  }
}

function activar_carta_urbana() {
  document.querySelector("#pducpt2010_LIMITE_MUNICIPAL_chkbox").click();
  document.querySelector("#pducpt2010_USOS_DE_SUELO_PROPUESTOS_chkbox").click();
  document.querySelector("#pducpt2010_SUBSECTORES_chkbox").click();
  document.querySelector("#pducpt2010_DELEGACIONES_chkbox").click();
  document.querySelector("#pducpt2010_ESQUEMA_VIAL_ACTUAL_chkbox").click();
  document.querySelector("#pducpt2010_ESQUEMA_VIAL_PROPUESTO_chkbox").click();
}

/* Recuperar interacción del mapa después de cargar controles */
setTimeout(function () {
  if (typeof map2d !== "undefined") {
    map2d.dragging.enable();
    map2d.scrollWheelZoom.enable();
    map2d.doubleClickZoom.enable();
    map2d.boxZoom.enable();
    map2d.keyboard.enable();
    map2d.touchZoom.enable();
    map2d.invalidateSize();
  }
}, 1000);
