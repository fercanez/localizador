// Muestra solo una pestaña del formulario de registro
function openTab(evt, tabName, currentTab) {
  var i;
  var x = document.getElementsByClassName("registerTab");
  for (i = 0; i < x.length; i++) {
    x[i].style.display = "none";
  }
  tablinks = document.getElementsByClassName("form-stage");
  for (i = 0; i < x.length; i++) {
    tablinks[i].className = tablinks[i].className.replace(
      " form-stage-active",
      ""
    );
  }
  document.getElementById(tabName).style.display = "block";
  // Problem with secondary links not changing tab color
  // document.getElementById()
  switch (tabName) {
    case "d_personales":
      tab_destiny = "tab1";
      break;
    case "t_y_c":
      tab_destiny = "tab2";
      break;
    case "resumen":
      tab_destiny = "tab3";
      break;
    default:
      console.log(`Sorry, ${expr} is not defined.`);
  }
  document.getElementById(tab_destiny).classList.add("form-stage-active");
}

// Obtener valores para actualizar el resumen de los datos de registro
const nameInputs = document.querySelectorAll(".name-input");
const fnInputs = document.querySelectorAll(".fn-input");
const singleInputs = document.querySelectorAll(".single-input");

// Funciones para actualizar los valores del resumen
const updateName = function (e) {
  const nombre = document.getElementById("nombre");
  const ap_paterno = document.getElementById("ap_paterno");
  const ap_materno = document.getElementById("ap_materno");
  const nombre_full = `${nombre.value.trim()} ${ap_paterno.value.trim()} ${
    ap_materno.value
  }`;
  const noutput = document.getElementById("nombre-output");
  noutput.innerText = nombre_full;
};

const updateFechaNacimiento = function (e) {
  const day = document.getElementById("fn_day");
  const month = document.getElementById("fn_month");
  const year = document.getElementById("fn_year");
  const fn_full = `${day.value.trim()} / ${month.value.trim()} / ${year.value}`;
  const fnOutput = document.getElementById("fn-output");
  fnOutput.innerText = fn_full;
};

const updateSingleField = function (e) {
  console.log(e.srcElement.id);
  const inputElement = document.getElementById(e.srcElement.id);
  const outputElement = document.getElementById(`${e.srcElement.id}-output`);
  outputElement.innerText = inputElement.value;
};

// Agregar Event Listener a text inputs para el nombre
Array.from(nameInputs).forEach((el) => {
  el.addEventListener("change", updateName);
});

// Agregar Event Listener a text inputs para la fecha de nacimiento
Array.from(fnInputs).forEach((el) => {
  el.addEventListener("change", updateFechaNacimiento);
});

// Agregar Event Listener a todos los inputs sencillos
Array.from(singleInputs).forEach((el) => {
  el.addEventListener("change", updateSingleField);
});
