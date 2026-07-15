const menuBtn = document.querySelector(".menu-btn");
const label = document
  .querySelector("#label-quickMenuBtn")
  .addEventListener("click", (event) => {
    event.preventDefault();
    menuBtn.classList.toggle("show");
  });
// let menuOpen = false;
// menuBtn.addEventListener("click", () => {
//   if (!menuOpen) {
//     menuBtn.classList.remove("open");
//     menuOpen = true;
//   } else {
//     menuBtn.classList.remove("open");
//     menuOpen = false;
//   }
// });

// label.addEventListener("click", () => menuBtn.classList.toggle("open"));
