function toggleModal(targetId) {
  // Encontrar todos las ventanas modales abiertas
  var openModals = document.getElementsByClassName("toggleModal");
  // Encontrar la ventana modal objetivo
  var target = document.getElementById(targetId);
  // Verificar si la ventana objetivo se encuentra abierta
  var status = target.classList.value.includes("toggleModal");

  // Cerrar todas las ventanas modales
  for (i = 0; i < openModals.length; i++) {
    openModals[i].classList.remove("toggleModal");
  }

  // Condicional para abrir ventana modal
  if (!status) {
    target.classList.add("toggleModal");
  }
}
