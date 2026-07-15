const menuBtn = document.querySelector(".menu-btn");

if (menuBtn) {
  const quickMenuBtnLabel = document.querySelector("#label-quickMenuBtn");

  if (quickMenuBtnLabel) {
    quickMenuBtnLabel.addEventListener("click", function (event) {
      event.preventDefault();
      menuBtn.classList.toggle("show");
    });
  }
}
