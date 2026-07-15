<div class="layoutPublicMap">
  <?php include "mapMenu.php"; ?>
  <div id="map2d" class="publicMap mapdiv" style="display: block;">
    <div id="map-top-toolbar" class="map-top-toolbar" aria-label="Barra de herramientas del mapa">
      <button type="button" class="map-top-toolbar__handle" title="Arrastrar barra" aria-label="Arrastrar barra">⠿</button>
      <div id="map-top-toolbar-search" class="map-top-toolbar__search"></div>
      <div id="map-top-toolbar-actions" class="map-top-toolbar__actions"></div>
    </div>
    <div class="tools" id="tools">
      <span class="icon-Ubicacion_2 control-icon" id="locateBtn">
      </span>
      <span class="icon-Subir_1 control-icon" id="visible-uploadBtn">
      </span>
      <input type="file" id="shp-upload" style="display: none;">
      <span class="icon-Informacion control-icon" id="getFeatureInfoBtn">
      </span>
      <span class="icon-Mano btnIsActive control-icon" id="panMapBtn">
      </span>
      <span class="icon-lapizRregla control-icon" id="editToolsDisplayBtn">
      </span>
      <span
        class="icon-Impresora control-icon"
        id="printMapBtn"
        title="Imprimir mapa"
        role="button"
        tabindex="0"
      ></span>
    </div>
    <div id="editToolsContainer" class="displayEditTools">
    </div>
    <?php include "mapControls/baseLayerSelector.php"; ?>
  </div>
  <div id="map25d" class="publicMap mapboxgl-map mapdiv"></div>
  <div id="map3d" class="map3d mapdiv">
    <iframe width="100%" height="calc(100vh-8.5rem)" src="https://www.youtube-nocookie.com/embed/yfkXZ8ssJDc"
      title="YouTube video player" frameborder="0"
      allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
      allowfullscreen></iframe>
  </div>




  <dialog class="modal-container-v2 print-mode-dialog" id="printMode-dialog">
    <p class="subtituloModal">Elige el formato de impresión</p>
    <div class="print-mode-options">
      <button type="button" class="boton boton-secundario" data-print-mode="tabloide">Tabloide</button>
      <button type="button" class="boton boton-secundario" data-print-mode="horizontal">Carta Horizontal</button>
      <button type="button" class="boton boton-secundario" data-print-mode="vertical">Carta Vertical</button>
      <button type="button" class="boton boton-secundario" data-print-mode="custom">Selección</button>
    </div>
    <div class="modal-controls">
      <button type="button" class="boton boton-primario" id="printModeCancel">Cancelar</button>
    </div>
  </dialog>

  <dialog class="modal-container-v2" id="mapTitle-dialog">
    <p class="subtituloModal">Escribe un título para mostrar en el mapa.</p>
    <input type="text" placeholder="Mi mapa" id="mapTitle-input">
    <div class="modal-controls">
      <a href="#" class="boton boton-primario" id="printCancel">Cancelar</a>
      <a href="#" class="boton boton-secundario" id="printAcept">Aceptar</a>
    </div>
    <div>
      <small>*Nota: Antes de imprimir verifica que el tamaño de la hoja de impresión coincida con tu selección.</small>
    </div>
  </dialog>

  <?php if (logged_in()) {
    include "includes/quickMenu.php";
  } ?>
</div>