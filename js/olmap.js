var layerSource = new ol.source.TileWMS({
  url: "http://35.212.139.212:8080/geoserver/implan/wms",
  params: {
    VERSION: "1.3.0",
    LAYERS: "pducpt2010_USOS_DE_SUELO_PROPUESTOS_2009-2030",
    TILED: true,
    bbox: "488331.0%2C3573509.75%2C535258.5625%2C3603320.75", // GeoServer bbox or extent in EPGS: 5255 - read from url in GeoServer WMS
    CRS: "EPSG:2611",
    FORMAT: "image/png8",
  },
  serverType: "geoserver",
});

var layerLayer = new ol.layer.Tile({
  layer: "pducpt2010_USOS_DE_SUELO_PROPUESTOS_2009-2030",
  visible: false,
  preload: Infinity,
  source: layerSource,
});

var map = new ol.Map({
  target: "map",
  layers: [
    layerLayer,
    new ol.layer.Tile({
      source: new ol.source.OSM(),
    }),
  ],
  view: new ol.View({
    center: ol.proj.fromLonLat([37.41, 8.82]),
    zoom: 4,
  }),
});
