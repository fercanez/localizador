<div class="simbologia" id="simbologia">
  <div class="simbologia-content">
    <div class="descripcion">
      <h3 id="simbologiaCard-titulo">Simbología</h3>
      <hr>
      <div id="simbologia-leyendas" class="simbologia-leyendas">
        <p id="simbologia-empty" class="simbologia-empty">
          Active una o más capas para ver su simbología.
        </p>
      </div>
    </div>
    <div class="simbology-btn-container">
      <button id="btn-simbologia" onclick="toggleSimbologia()">
        <span id="simbologia-arrow" class="material-symbols-outlined">
          keyboard_arrow_right
        </span>
        <span id="btn">SIMBOLOGIA</span>
      </button>
    </div>
  </div>
</div>
<script>
  const toggleSimbologia = function () {
    const simbologia = document.getElementById("simbologia");
    simbologia.classList.toggle("visibilitado");
    if(simbologia.classList.contains("visibilitado")){
        document.getElementById("simbologia-arrow").style = "transform: rotate(180deg);"
        btn.innerText = "Cerrar";
    } else {
      btn.innerText = "Simbología";
      document.getElementById("simbologia-arrow").style = "transform: rotate(0deg);"
    }
  };
</script>
