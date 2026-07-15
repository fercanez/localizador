(function () {
  "use strict";

  if (typeof mapboxgl === "undefined") return;

  var map25dMap = null;

  // Agregar token de Mapbox aqui si se habilita la vista 2.5D.
  if (!mapboxgl.accessToken) {
    mapboxgl.accessToken = "";
  }

  window.initMap25d = function () {
    if (map25dMap || !mapboxgl.accessToken) return map25dMap;

    var container = document.getElementById("map25d");
    if (!container) return null;

    map25dMap = new mapboxgl.Map({
      container: "map25d",
      style: "mapbox://styles/mapbox/satellite-streets-v11",
      center: [-115.471843, 32.642617],
      zoom: 13,
    });

    return map25dMap;
  };

  if (typeof window.openMap === "function") {
    var originalOpenMap = window.openMap;

    window.openMap = function (evt, mapName) {
      originalOpenMap(evt, mapName);
      if (mapName === "map25d") window.initMap25d();
    };
  }
})();
