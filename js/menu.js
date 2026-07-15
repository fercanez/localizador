// Toggle Seccion mi Cuenta Rapido (derecha)
const accountBtn = document.querySelector(".accountBtn");

accountBtn.addEventListener("click", (event) => {
  menuBtn.classList.toggle("show");
  const act = document.querySelector(".quickMenu");
  const opn = document.querySelector(".open");
  const btns = document.getElementsByClassName("quickMenu-link");
  for (i = 0; i < btns.length; i++) {
    btns[i].classList.remove("active");
  }
  act.classList.toggle("notVisible");
  if (opn) {
    opn.classList.toggle("open");
  }
  if (!act.classList.contains("notVisible")) {
    $("#perfilLink").click();
  }
});

function toggleSection(evt, section) {
  var i, sections, btns, status;

  status = document.getElementById(section).className.includes("open");
  sections = document.getElementsByClassName("sideElement");

  for (i = 0; i < sections.length; i++) {
    sections[i].classList.remove("open");
  }

  btns = document.getElementsByClassName("quickMenu-link");
  for (i = 0; i < btns.length; i++) {
    btns[i].classList.remove("active");
  }
  document.getElementById(section).classList.toggle("open");

  if (status) {
    document.getElementById(section).classList.toggle("open");
  }
  evt.currentTarget.classList.add("active");
}
