<div class="map-sideElement"  id="capas">
    <div class="map-sidePanel-header">
        <h3>CAPAS</h3>
    </div>
    <div class="sidePanel-content">
    <fieldset>
        <div class="layerControl">
            <label for="usosSuelo" class="switch-label">
                Usos de Suelo
            </label>
            <label class="switch" for="usosSuelo">
                <input type="checkbox" id="usosSuelo" name="usosSuelo" onclick="toggleLayer('usosSuelo', usos_de_suelo_propuestos)"/>
                <span class="slider round"></span>
            </label>
        </div>

        <div class="layerControl">
            <label for="esquemaVial" class="switch-label">
                Esquema Vial Propuesto
            </label>
            <label class="switch" for="esquemaVial">
                <input type="checkbox" id="esquemaVial" name="esquemaVial" onclick="toggleLayer('esquemaVial', esquema_vial_propuesto)"/>
                <span class="slider round"></span>
            </label>
        </div>

        <div class="layerControl">
            <label for="equipamiento" class="switch-label">
                Equipamiento
            </label>
            <label class="switch" for="equipamiento">
                <input type="checkbox" id="equipamiento" name="equipamiento" onclick="toggleLayer('equipamiento', equipamiento)"/>
                <span class="slider round"></span>
            </label>
        </div>

    </fieldset>
    </div>
</div>