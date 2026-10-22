<?php
/**
 * Visor de predio por clave (?id=CLAVE).
 * Compatible con PHP 5.6 (XAMPP).
 */
header("Content-Type: text/html; charset=UTF-8");

$host = "10.101.11.205";
$database = "catastro";
$user = "canez";
$pass = "012170";
$wmsUrl = "http://10.101.11.205:8080/geoserver/wms";
$paso1Url = "../paso1.php";

$claveRaw = isset($_GET["id"]) ? $_GET["id"] : "";
$clave = strtoupper(preg_replace("/\s+/", "", $claveRaw));
$error = "";
$lon = null;
$lat = null;
$geojson = null;

if ($clave === "" || !preg_match('/^[A-Z0-9_-]{4,32}$/', $clave)) {
    $error = "Clave catastral invalida.";
} else {
    $conn = @pg_connect("host=" . $host . " port=5432 dbname=" . $database . " user=" . $user . " password=" . $pass . " connect_timeout=5");
    if ($conn === false) {
        $error = "No se pudo conectar a la base de datos.";
    } else {
        @pg_query($conn, "SET statement_timeout = '8000'");
        $sql = "SELECT
            ST_X(ST_Transform(ST_PointOnSurface(geom), 4326)) AS lon,
            ST_Y(ST_Transform(ST_PointOnSurface(geom), 4326)) AS lat,
            ST_AsGeoJSON(
              ST_Transform(
                ST_SimplifyPreserveTopology(
                  geom,
                  CASE WHEN ST_SRID(geom) IN (4326, 4269, 4674) THEN 0.00004 ELSE 2.0 END
                ),
                4326
              ),
              6
            ) AS geojson
          FROM predios
          WHERE clavecatas = $1
          LIMIT 1";
        $rs = @pg_query_params($conn, $sql, array($clave));
        if ($rs === false) {
            $error = "Error al consultar el predio: " . pg_last_error($conn);
        } else {
            $row = pg_fetch_assoc($rs);
            if (!$row || !isset($row["geojson"]) || $row["geojson"] === null || $row["geojson"] === "") {
                $error = "No se encontro el predio " . $clave . ".";
            } else {
                $lon = (float)$row["lon"];
                $lat = (float)$row["lat"];
                $decoded = json_decode($row["geojson"], true);
                if (is_array($decoded)) {
                    $geojson = $decoded;
                } else {
                    $error = "La geometria del predio no es valida.";
                }
            }
        }
        pg_close($conn);
    }
}

$claveJs = json_encode($clave);
$lonJs = json_encode($lon);
$latJs = json_encode($lat);
$geoJs = json_encode($geojson);
$wmsJs = json_encode($wmsUrl);
$paso1Js = json_encode($paso1Url);
$errorJs = json_encode($error);
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta http-equiv="X-UA-Compatible" content="IE=edge">
  <title>Localizacion de Predio</title>
  <style>
    html, body { height: 100%; margin: 0; padding: 0; font-family: Arial, sans-serif; }
    #map {
      position: absolute;
      top: 0; right: 0; bottom: 0; left: 0;
      min-height: 420px;
    }
    .ol-popup {
      position: absolute;
      background: #fff;
      filter: drop-shadow(0 1px 4px rgba(0,0,0,.25));
      padding: 14px 16px;
      border-radius: 10px;
      border: 1px solid #ccc;
      bottom: 12px;
      left: -50px;
      min-width: 220px;
      font-size: 13px;
    }
    .ol-popup-closer {
      text-decoration: none;
      position: absolute;
      top: 2px;
      right: 8px;
      color: #444;
    }
    .ol-popup-closer:after { content: "X"; }
    .status-bar {
      position: absolute;
      z-index: 2;
      left: 10px;
      bottom: 10px;
      background: rgba(255,255,255,.92);
      border: 1px solid #ccc;
      border-radius: 6px;
      padding: 6px 10px;
      font-size: 12px;
    }
    .status-bar.error { color: #8b1538; font-weight: 700; }
    .btn-link {
      display: inline-block;
      margin-top: 8px;
      padding: 6px 10px;
      background: #7d3445;
      color: #fff;
      border: 0;
      border-radius: 6px;
      cursor: pointer;
    }
    #myposition { display: none; }
  </style>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/ol@6.15.1/ol.css">
  <link rel="stylesheet" href="https://unpkg.com/ol-layerswitcher@3.8.3/dist/ol-layerswitcher.css">
</head>
<body>
  <div id="map" class="map"></div>
  <div id="popup" class="ol-popup">
    <a href="#" id="popup-closer" class="ol-popup-closer"></a>
    <div id="popup-content"></div>
  </div>
  <div id="status" class="status-bar">Cargando predio...</div>
  <div id="myposition"></div>

  <script type="text/javascript" src="https://code.jquery.com/jquery-1.11.0.min.js"></script>
  <script src="v6.5.0/build/ol.js"></script>
  <script>
    if (typeof ol === "undefined") {
      document.write('<script src="https://cdn.jsdelivr.net/npm/ol@6.15.1/dist/ol.js"><\/script>');
    }
  </script>
  <script src="https://unpkg.com/ol-layerswitcher@3.8.3"></script>
  <script>
    (function () {
      var CLAVE = <?php echo $claveJs; ?>;
      var LON = <?php echo $lonJs; ?>;
      var LAT = <?php echo $latJs; ?>;
      var GEOJSON = <?php echo $geoJs; ?>;
      var WMS_URL = <?php echo $wmsJs; ?>;
      var PASO1 = <?php echo $paso1Js; ?>;
      var PAGE_ERROR = <?php echo $errorJs; ?>;

      var statusEl = document.getElementById("status");
      var popup = document.getElementById("popup");
      var content = document.getElementById("popup-content");
      var closer = document.getElementById("popup-closer");

      function setStatus(msg, isError) {
        statusEl.innerHTML = msg;
        statusEl.className = isError ? "status-bar error" : "status-bar";
      }

      function escapeHtml(value) {
        return String(value == null ? "" : value)
          .replace(/&/g, "&amp;")
          .replace(/</g, "&lt;")
          .replace(/>/g, "&gt;")
          .replace(/"/g, "&quot;");
      }

      if (PAGE_ERROR) {
        setStatus(PAGE_ERROR, true);
        return;
      }

      var view = new ol.View({
        center: ol.proj.fromLonLat([LON, LAT]),
        zoom: 18,
        maxZoom: 21
      });

      var osm = new ol.layer.Tile({
        title: "Open Street Map",
        type: "base",
        visible: false,
        source: new ol.source.OSM()
      });

      var esri = new ol.layer.Tile({
        title: "ESRI Satellite",
        type: "base",
        visible: false,
        source: new ol.source.XYZ({
          url: "https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}",
          attributions: "Tiles © Esri"
        })
      });

      var googleLayerSatellite = new ol.layer.Tile({
        type: "base",
        title: "Google Satellite",
        visible: false,
        source: new ol.source.XYZ({
          url: "https://mt1.google.com/vt/lyrs=s&hl=es&x={x}&y={y}&z={z}",
          maxZoom: 21
        })
      });

      var googleLayerHybrid = new ol.layer.Tile({
        type: "base",
        title: "Google Satellite & Roads",
        visible: true,
        source: new ol.source.XYZ({
          url: "https://mt1.google.com/vt/lyrs=y&hl=es&x={x}&y={y}&z={z}",
          maxZoom: 21
        })
      });

      var prediosSource = new ol.source.TileWMS({
        url: WMS_URL,
        params: {
          LAYERS: "catastro:predios",
          TILED: true,
          FORMAT: "image/png",
          VERSION: "1.1.1"
        },
        serverType: "geoserver",
        transition: 0
      });

      var predios = new ol.layer.Tile({
        title: "Predios",
        source: prediosSource,
        minZoom: 16
      });

      var colonias = new ol.layer.Tile({
        title: "Colonias",
        visible: false,
        minZoom: 12,
        source: new ol.source.TileWMS({
          url: WMS_URL,
          params: {
            LAYERS: "catastro:colonias",
            TILED: true,
            FORMAT: "image/png"
          },
          serverType: "geoserver",
          transition: 0
        })
      });

      var valuados = new ol.layer.Tile({
        title: "Predios valuados",
        visible: true,
        minZoom: 13,
        source: new ol.source.TileWMS({
          url: WMS_URL,
          params: {
            LAYERS: "catastro:AVALUOS RUSTICOS 2024",
            TILED: true,
            FORMAT: "image/png"
          },
          serverType: "geoserver",
          transition: 0
        })
      });

      var fallas = new ol.layer.Tile({
        title: "Fallas",
        visible: true,
        source: new ol.source.TileWMS({
          url: WMS_URL,
          params: {
            LAYERS: "Mexicali:fallas_geologicas",
            TILED: true,
            FORMAT: "image/png",
            TRANSPARENT: true
          },
          serverType: "geoserver",
          transition: 0
        })
      });

      var consultadoStyle = new ol.style.Style({
        fill: new ol.style.Fill({ color: "rgba(25,12,210,0.22)" }),
        stroke: new ol.style.Stroke({
          color: "rgb(25,12,210)",
          width: 4,
          lineDash: [7, 7]
        })
      });

      var format = new ol.format.GeoJSON();
      var feature = format.readFeature({
        type: "Feature",
        geometry: GEOJSON,
        properties: { clavecatas: CLAVE }
      }, {
        dataProjection: "EPSG:4326",
        featureProjection: "EPSG:3857"
      });

      var predioConsultado = new ol.layer.Vector({
        title: "Predio consultado",
        visible: true,
        source: new ol.source.Vector({ features: [feature] }),
        style: consultadoStyle,
        zIndex: 20
      });

      var highlight = new ol.layer.Vector({
        title: "Seleccion",
        source: new ol.source.Vector(),
        zIndex: 19
      });

      var overlay = new ol.Overlay({
        element: popup,
        autoPan: true
      });

      var map = new ol.Map({
        target: "map",
        view: view,
        layers: [
          new ol.layer.Group({
            title: "Mapas base",
            layers: [osm, esri, googleLayerSatellite, googleLayerHybrid]
          }),
          new ol.layer.Group({
            title: "Capas",
            layers: [colonias, valuados, predios, fallas, highlight, predioConsultado]
          })
        ],
        overlays: [overlay],
        controls: ol.control.defaults({ attribution: false })
      });

      if (typeof ol.control.LayerSwitcher === "function") {
        map.addControl(new ol.control.LayerSwitcher({ tipLabel: "Arbol de capas" }));
      }

      var extent = feature.getGeometry().getExtent();
      if (extent && isFinite(extent[0])) {
        map.getView().fit(extent, {
          size: map.getSize(),
          padding: [48, 48, 48, 48],
          maxZoom: 19,
          duration: 0
        });
      }

      predioConsultado.setVisible(true);
      fallas.setVisible(true);
      valuados.setVisible(true);
      setStatus("Predio " + CLAVE, false);

      closer.onclick = function () {
        overlay.setPosition(undefined);
        closer.blur();
        highlight.getSource().clear();
        return false;
      };

      map.on("singleclick", function (e) {
        if (map.getView().getZoom() < 16) {
          setStatus("Acerque el mapa (zoom 16+) para consultar predios.", false);
          return;
        }

        var loc = e.coordinate;
        var url = prediosSource.getFeatureInfoUrl(
          loc,
          map.getView().getResolution(),
          map.getView().getProjection(),
          { INFO_FORMAT: "application/json", FEATURE_COUNT: 5 }
        );
        if (!url) {
          return;
        }

        setStatus("Consultando predio...", false);
        $.ajax({
          url: url,
          success: function (data) {
            var features = format.readFeatures(data);
            highlight.getSource().clear();
            if (!features.length) {
              content.innerHTML = "No se encontraron datos que mostrar...";
              overlay.setPosition(loc);
              setStatus("Predio " + CLAVE, false);
              return;
            }
            highlight.getSource().addFeatures(features);
            var html = "";
            var i;
            for (i = 0; i < features.length; i++) {
              var f = features[i];
              var cve = f.get("clavecatas") || f.get("cve_cat") || "";
              html +=
                "<div><b>Cve. Catastral:</b> " + escapeHtml(cve) +
                "<br><b>Delegacion:</b> " + escapeHtml(f.get("delegacion") || "") +
                "<br><button type='button' class='btn-link' data-clave='" + escapeHtml(cve) +
                "'>Ver expediente</button></div>";
            }
            content.innerHTML = html;
            overlay.setPosition(loc);
            setStatus("Predio " + CLAVE, false);
          },
          error: function () {
            highlight.getSource().clear();
            content.innerHTML = "No fue posible localizar informacion.";
            overlay.setPosition(loc);
            setStatus("No se pudo consultar el WMS.", true);
          }
        });
      });

      content.onclick = function (ev) {
        var t = ev.target || ev.srcElement;
        var claveBtn = "";
        while (t && t !== content) {
          if (t.getAttribute && t.getAttribute("data-clave")) {
            claveBtn = t.getAttribute("data-clave");
            break;
          }
          t = t.parentNode;
        }
        if (claveBtn) {
          location.href = PASO1 + "?claveCat=" + encodeURIComponent(claveBtn);
        }
      };
    })();
  </script>
</body>
</html>
