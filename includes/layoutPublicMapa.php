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
      <span class="control-icon control-icon--camera" id="inspectPhotoBtn" title="Inspección fotográfica" role="button" tabindex="0" aria-label="Inspección fotográfica">
        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M9.5 5.5A2.5 2.5 0 0 1 12 3h0a2.5 2.5 0 0 1 2.5 2.5V4h3A2.5 2.5 0 0 1 20 6.5v11A2.5 2.5 0 0 1 17.5 20h-11A2.5 2.5 0 0 1 4 17.5v-11A2.5 2.5 0 0 1 6.5 4H9v1.5zm2.5-1a.5.5 0 0 0-.5.5V6H9.5a.5.5 0 0 0-.5.5v.5H6.5a.5.5 0 0 0-.5.5v11a.5.5 0 0 0 .5.5h11a.5.5 0 0 0 .5-.5v-11a.5.5 0 0 0-.5-.5H15V5a.5.5 0 0 0-.5-.5zM12 8.5a4 4 0 1 1 0 8 4 4 0 0 1 0-8zm0 1.5a2.5 2.5 0 1 0 0 5 2.5 2.5 0 0 0 0-5z"/></svg>
      </span>
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