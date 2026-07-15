<div class="mapMenu">
    <div class="mapMenu-item">
        <a href="#" class="mapMenu-link" onclick="open2Map()" id="mapMenu-btn">
            <i class="icon-Mapa mapMenu-icon"></i>
        </a>
        <nav class="mapMenu-nav">
            <ul>                
            </ul>
        </nav>
    </div>
    <?php include "includes/sidePanels/acordeonCapas.php" ?>
    <?php include "includes/sidePanels/capasActivas.php" ?>
</div>

<script>
    function toggleLayers(evt, section) {
        var i, sections, btns, status;

        status = document.getElementById(section).className.includes("map-open");
        // console.log(status)
        sections = document.getElementsByClassName("map-sideElement");

        for (i = 0; i < sections.length; i++) {
            sections[i].classList.remove("map-open");
        }

        btns = document.getElementsByClassName("mapkMenu-link");
        for (i = 0; i < btns.length; i++) {
            btns[i].classList.remove("active");
        }
        document.getElementById(section).classList.toggle("map-open");

        if (status) {
            document.getElementById(section).classList.toggle("map-open");
        }
        // evt.currentTarget.classList.toggle("active");
    }


    function openMap(evt, mapName) {
        if (mapName !== 'map2d') {
            document.getElementById("capas").classList.remove('map-open');
        }

        if (mapName == 'map25d') {
            let mapBtns = document.getElementsByClassName('mapMenu-link')
            Array.from(mapBtns).forEach(el => el.classList.remove('active'))
            document.getElementById('btn-25d').classList.add('active')
        }

        if (mapName == 'map3d') {
            let mapBtns = document.getElementsByClassName('mapMenu-link')
            Array.from(mapBtns).forEach(el => el.classList.remove('active'))
            document.getElementById('btn-3d').classList.add('active')
        }

        // Declare all variables
        var i, mapdiv, maplinks;

        // Get all elements with class="mapdiv" and hide them
        mapdiv = document.getElementsByClassName("mapdiv");
        for (i = 0; i < mapdiv.length; i++) {
            mapdiv[i].style.display = "none";
        }

        // Get all elements with class="maplinks" and remove the class "active"
        maplinks = document.getElementsByClassName("maplinks");
        for (i = 0; i < maplinks.length; i++) {
            maplinks[i].className = maplinks[i].classList.toggle("map-toggle");
        }

        // Show the current tab, and add an "active" class to the button that opened the tab
        document.getElementById(mapName).style.display = "block";
        evt.currentTarget.classList.add('map-toggle');
    }

    let map2d_status = 'block';

    function open2Map() {
        map2d_status = document.getElementById("map2d").style.display;
        if (map2d_status == '' || map2d_status == 'none') {
            openMap(event, 'map2d')
            let mapBtns = document.getElementsByClassName('mapMenu-link')
            Array.from(mapBtns).forEach(el => el.classList.remove('active'))
            document.getElementById('btn-2d').classList.add('active')
        } else {
            toggleLayers(event, 'capas')
        }
    }

    function openActiveLayers() {
        map2d_status = document.getElementById("map2d").style.display;
        if (map2d_status == '' || map2d_status == 'none') {
            openMap(event, 'map2d')
        } else {
            toggleLayers(event, 'capas')
            toggleLayers(event, 'capasActivas')
        }
    }

    // $(function() {
    //     $('.plus-minus-toggle').on('click', function() {
    //         $(this).toggleClass('collapsed');
    //     });
    // });

    const labelsLvl1 = document.querySelectorAll('.cd-accordion__label--level1')

    Array.from(labelsLvl1).forEach((el)=>{
        el.addEventListener('click', () => {
            el.children[0].classList.toggle('collapsed')
        })
})


</script>