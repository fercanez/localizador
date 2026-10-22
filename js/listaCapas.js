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
// RED UNIFICADA (vértices de control)
// -----------------------------
var red_unificada = L.tileLayer.wms(WMS_GEOMEXICALI, Object.assign({
  layers: "geonode:red_unificada_de_mexicali",
  title: "Red Unificada",
  format: "image/png",
  transparent: true,
  version: "1.1.1",
  attribution: "MXLI",
  zIndex: 980,
}, WMS_ZOOM));

red_unificada.id = "red_unificada_de_mexicali";
overlayMaps["red_unificada"] = red_unificada;
overlayMaps["Red Unificada"] = red_unificada;
overlayMaps["red_unificada_de_mexicali"] = red_unificada;
overlayMaps["geonode:red_unificada_de_mexicali"] = red_unificada;

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

// -----------------------------
// DRENES CONAGUA
// GeoNode sirve esta capa en /gs/ows (GetMap en /geoserver/geonode/wms responde 500)
// -----------------------------
var drenes_conagua = L.tileLayer.wms(WMS_GS_OWS, Object.assign({
  layers: "geonode:drenes_conagua",
  title: "Drenes CONAGUA",
  format: "image/png",
  transparent: true,
  version: "1.1.1",
  attribution: "MXLI",
  zIndex: 880,
}, WMS_ZOOM));

drenes_conagua.id = "drenes_conagua";
overlayMaps["drenes_conagua"] = drenes_conagua;
overlayMaps["Drenes CONAGUA"] = drenes_conagua;
overlayMaps["geonode:drenes_conagua"] = drenes_conagua;

// -----------------------------
// CANALES CONAGUA
// Mismo endpoint /gs/ows que drenes (no usar /geoserver/geonode/wms)
// -----------------------------
var canales_conagua = L.tileLayer.wms(WMS_GS_OWS, Object.assign({
  layers: "geonode:canales_conagua",
  title: "Canales CONAGUA",
  format: "image/png",
  transparent: true,
  version: "1.1.1",
  attribution: "MXLI",
  zIndex: 885,
}, WMS_ZOOM));

canales_conagua.id = "canales_conagua";
overlayMaps["canales_conagua"] = canales_conagua;
overlayMaps["Canales CONAGUA"] = canales_conagua;
overlayMaps["geonode:canales_conagua"] = canales_conagua;

// -----------------------------
// FALLAS GEOLÓGICAS
// Solo la ven administrador y perito valuador (el panel ya filtra el permiso).
// -----------------------------
var fallas_geologicas = L.tileLayer.wms(WMS_GEOMEXICALI, Object.assign({
  layers: "geonode:fallas_geologicas",
  title: "Fallas geológicas",
  format: "image/png",
  transparent: true,
  version: "1.1.1",
  attribution: "MXLI",
  zIndex: 930,
}, WMS_ZOOM));

fallas_geologicas.id = "fallas_geologicas";
overlayMaps["fallas_geologicas"] = fallas_geologicas;
overlayMaps["Fallas geológicas"] = fallas_geologicas;
overlayMaps["geonode:fallas_geologicas"] = fallas_geologicas;

// -----------------------------
// ADEUDO PREDIAL (capa temporal, se llena al acercar)
// -----------------------------
var predios_adeudo = L.geoJSON(null, {
  title: "Adeudo predial",
  interactive: true,
  style: {
    color: "#94a3b8",
    weight: 1.2,
    fillColor: "#cbd5e1",
    fillOpacity: 0.28,
  },
});
predios_adeudo.id = "predios_adeudo";
predios_adeudo.options.title = "Adeudo predial";
overlayMaps["Adeudo predial"] = predios_adeudo;
overlayMaps["predios_adeudo"] = predios_adeudo;
