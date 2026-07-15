var overlayMaps = {};

// WMS oficial GeoMexicali
const WMS_GEOMEXICALI = "https://www.geomexicali.info/geoserver/geonode/wms";
const WMS_GS_OWS = "https://www.geomexicali.info/gs/ows";

// El mapa llega a zoom 20–22; sin esto Leaflet deja de pedir tiles tras el 18
const WMS_ZOOM = { maxZoom: 22, maxNativeZoom: 22 };

// -----------------------------
// PREDIOS
// -----------------------------
var predios = L.tileLayer.wms(WMS_GEOMEXICALI, Object.assign({
  layers: "geonode:predios_mexicali_2025",
  title: "Predios",
  format: "image/png",
  transparent: true,
  version: "1.1.1",
  attribution: "MXLI",
  zIndex: 900,
}, WMS_ZOOM));

predios.id = "predios";
overlayMaps["predios"] = predios;

// -----------------------------
// PREDIOS BALDÍOS
// -----------------------------
var predios_baldios = L.tileLayer.wms(WMS_GEOMEXICALI, Object.assign({
  layers: "geonode:predios_baldios",
  title: "Predios Baldíos",
  format: "image/png",
  transparent: true,
  version: "1.1.1",
  attribution: "MXLI",
  zIndex: 910,
}, WMS_ZOOM));

predios_baldios.id = "predios_baldios";
overlayMaps["predios_baldios"] = predios_baldios;
overlayMaps["Predios Baldíos"] = predios_baldios;
overlayMaps["geonode:predios_baldios"] = predios_baldios;

// -----------------------------
// LÍMITE MUNICIPAL
// Nota: en GeoServer está escrito como "mexciali"
// -----------------------------
var limite = L.tileLayer.wms(WMS_GEOMEXICALI, Object.assign({
  layers: "geonode:limite_municipal_de_mexciali_oficial",
  title: "Limite Municipal Oficial de Mexicali",
  format: "image/png",
  transparent: true,
  version: "1.1.1",
  attribution: "MXLI",
  zIndex: 800,
}, WMS_ZOOM));

limite.id = "limite_municipal_de_mexciali_oficial";

overlayMaps["limite_municipal_de_mexciali_oficial"] = limite;
overlayMaps["limite_municipal_de_mexicali_oficial"] = limite;
overlayMaps["geonode:limite_municipal_de_mexciali_oficial"] = limite;
overlayMaps["geonode:limite_municipal_de_mexicali_oficial"] = limite;

var limite_municipal_de_mexciali_oficial = limite;
var limite_municipal_de_mexicali_oficial = limite;

// -----------------------------
// COLONIAS
// -----------------------------
var colonias = L.tileLayer.wms(WMS_GEOMEXICALI, Object.assign({
  layers: "geonode:colonias",
  title: "Colonias",
  format: "image/png",
  transparent: true,
  version: "1.1.1",
  attribution: "MXLI",
  zIndex: 850,
}, WMS_ZOOM));

colonias.id = "Colonias";
overlayMaps["Colonias"] = colonias;
overlayMaps["colonias"] = colonias;
overlayMaps["geonode:colonias"] = colonias;

// -----------------------------
// CÓDIGOS POSTALES
// -----------------------------
var cp = L.tileLayer.wms(WMS_GEOMEXICALI, Object.assign({
  layers: "geonode:codigos_postales_2025",
  title: "Codigos Postales",
  format: "image/png",
  transparent: true,
  version: "1.1.1",
  attribution: "MXLI",
  zIndex: 870,
}, WMS_ZOOM));

cp.id = "Codigos Postales";
overlayMaps["Codigos"] = cp;
overlayMaps["Codigos Postales"] = cp;
overlayMaps["codigos_postales_2025"] = cp;
overlayMaps["geonode:codigos_postales_2025"] = cp;

// -----------------------------
// ESTRUCTURA VIAL / ESQUEMA VIAL
// -----------------------------
var vialidades2040 = L.tileLayer.wms(WMS_GEOMEXICALI, Object.assign({
  layers: "estructura_vial",
  title: "Estructura Vial",
  format: "image/png",
  transparent: true,
  version: "1.1.1",
  attribution: "MXLI",
  zIndex: 950,
}, WMS_ZOOM));

vialidades2040.id = "Esquema Vial Propuesto 2040";

overlayMaps["Esquema Vial Propuesto 2040"] = vialidades2040;
overlayMaps["Esquema Vial Propuesto"] = vialidades2040;
overlayMaps["Estructura Vial"] = vialidades2040;
overlayMaps["estructura_vial"] = vialidades2040;
overlayMaps["geonode:estructura_vial"] = vialidades2040;
overlayMaps["Infraestructura ciclista actual"] = vialidades2040;

var estructura_vial = vialidades2040;
var esquema_vial_propuesto_2040 = vialidades2040;

// -----------------------------
// USOS DE SUELO ACTUAL
// GeoNode sirve esta capa en /gs/ows (no en /geoserver/geonode/wms)
// -----------------------------
var predios_con_uso = L.tileLayer.wms(WMS_GS_OWS, Object.assign({
  layers: "geonode:predios_con_uso",
  title: "Usos de Suelo Actual",
  format: "image/png",
  transparent: true,
  version: "1.1.1",
  attribution: "MXLI",
  zIndex: 905,
}, WMS_ZOOM));

predios_con_uso.id = "predios_con_uso";
overlayMaps["predios_con_uso"] = predios_con_uso;
overlayMaps["Usos de Suelo Actual"] = predios_con_uso;
overlayMaps["geonode:predios_con_uso"] = predios_con_uso;

// -----------------------------
// USOS DE SUELO 2040
// -----------------------------
var usosp = L.tileLayer.wms(WMS_GEOMEXICALI, Object.assign({
  layers: "geonode:usos_prop_au40",
  title: "Usos Propuestos 2040",
  format: "image/png",
  transparent: true,
  version: "1.1.1",
  attribution: "MXLI",
  zIndex: 700,
}, WMS_ZOOM));

usosp.id = "Usos Propuestos 2040";
overlayMaps["Usos Propuestos 2040"] = usosp;
overlayMaps["usos_prop_au40"] = usosp;
overlayMaps["geonode:usos_prop_au40"] = usosp;

// -----------------------------
// ZONAS HOMOGÉNEAS 2017-2026
// -----------------------------
var zonas_homogeneas = L.tileLayer.wms(WMS_GS_OWS, Object.assign({
  layers: "geonode:zonas_homogeneas_2017_2026_prop",
  title: "Zonas Homogéneas 2017-2026",
  format: "image/png",
  transparent: true,
  version: "1.1.1",
  attribution: "MXLI",
  zIndex: 720,
}, WMS_ZOOM));

zonas_homogeneas.id = "zonas_homogeneas_2017_2026_prop";
overlayMaps["zonas_homogeneas"] = zonas_homogeneas;
overlayMaps["zonas_homogeneas_2017_2026_prop"] = zonas_homogeneas;
overlayMaps["Zonas Homogéneas 2017-2026"] = zonas_homogeneas;
overlayMaps["geonode:zonas_homogeneas_2017_2026_prop"] = zonas_homogeneas;
