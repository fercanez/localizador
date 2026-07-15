const mapMenuBtn = document.querySelector("#mapMenu-btn");

function openSideElement() {
  status = document.getElementById(section).className.includes("open");
}

function hideSideElements() {
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
  // act.classList.toggle("show");
}
