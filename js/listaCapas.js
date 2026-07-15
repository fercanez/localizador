var overlayMaps = {};

// WMS oficial GeoMexicali
const WMS_GEOMEXICALI = "https://www.geomexicali.info/geoserver/geonode/wms";

// -----------------------------
// PREDIOS
// -----------------------------
var predios = L.tileLayer.wms(WMS_GEOMEXICALI, {
  layers: "geonode:predios_mexicali_2025",
  title: "Predios",
  format: "image/png",
  transparent: true,
  version: "1.1.1",
  attribution: "MXLI",
  zIndex: 900,
  maxZoom: 22,
  maxNativeZoom: 22,
});

predios.id = "predios";
overlayMaps["predios"] = predios;

// -----------------------------
// PREDIOS BALDÍOS
// -----------------------------
var predios_baldios = L.tileLayer.wms(WMS_GEOMEXICALI, {
  layers: "geonode:predios_baldios",
  title: "Predios Baldíos",
  format: "image/png",
  transparent: true,
  version: "1.1.1",
  attribution: "MXLI",
  zIndex: 910,
  maxZoom: 22,
  maxNativeZoom: 22,
});

predios_baldios.id = "predios_baldios";
overlayMaps["predios_baldios"] = predios_baldios;
overlayMaps["Predios Baldíos"] = predios_baldios;
overlayMaps["geonode:predios_baldios"] = predios_baldios;

// -----------------------------
// LÍMITE MUNICIPAL
// Nota: en GeoServer está escrito como "mexciali"
// -----------------------------
var limite = L.tileLayer.wms(WMS_GEOMEXICALI, {
  layers: "geonode:limite_municipal_de_mexciali_oficial",
  title: "Limite Municipal Oficial de Mexicali",
  format: "image/png",
  transparent: true,
  version: "1.1.1",
  attribution: "MXLI",
  zIndex: 800,
});

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
var colonias = L.tileLayer.wms(WMS_GEOMEXICALI, {
  layers: "geonode:colonias",
  title: "Colonias",
  format: "image/png",
  transparent: true,
  version: "1.1.1",
  attribution: "MXLI",
  zIndex: 850,
});

colonias.id = "Colonias";
overlayMaps["Colonias"] = colonias;

// -----------------------------
// CÓDIGOS POSTALES
// -----------------------------
var cp = L.tileLayer.wms(WMS_GEOMEXICALI, {
  layers: "geonode:codigos_postales_2025",
  title: "Codigos Postales",
  format: "image/png",
  transparent: true,
  version: "1.1.1",
  attribution: "MXLI",
  zIndex: 870,
});

cp.id = "Codigos Postales";
overlayMaps["Codigos"] = cp;
overlayMaps["Codigos Postales"] = cp;

// -----------------------------
// ESTRUCTURA VIAL / ESQUEMA VIAL
// -----------------------------
var vialidades2040 = L.tileLayer.wms(WMS_GEOMEXICALI, {
  layers: "estructura_vial",
  title: "Estructura Vial",
  format: "image/png",
  transparent: true,
  version: "1.1.1",
  attribution: "MXLI",
  zIndex: 950,
});

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
var predios_con_uso = L.tileLayer.wms("https://www.geomexicali.info/gs/ows", {
  layers: "geonode:predios_con_uso",
  title: "Usos de Suelo Actual",
  format: "image/png",
  transparent: true,
  version: "1.1.1",
  attribution: "MXLI",
  zIndex: 905,
  maxZoom: 22,
  maxNativeZoom: 22,
});

predios_con_uso.id = "predios_con_uso";
overlayMaps["predios_con_uso"] = predios_con_uso;
overlayMaps["Usos de Suelo Actual"] = predios_con_uso;
overlayMaps["geonode:predios_con_uso"] = predios_con_uso;

// -----------------------------
// USOS DE SUELO 2040
// -----------------------------
var usosp = L.tileLayer.wms(WMS_GEOMEXICALI, {
  layers: "geonode:usos_prop_au40",
  title: "Usos Propuestos 2040",
  format: "image/png",
  transparent: true,
  version: "1.1.1",
  attribution: "MXLI",
  zIndex: 700,
});

usosp.id = "Usos Propuestos 2040";
overlayMaps["Usos Propuestos 2040"] = usosp;