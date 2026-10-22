<div class="contenedorModal" id="modalTest">
    <div class="contenidoModal modalLogin">
        <span class="material-symbols-outlined cerrarModal" onclick="toggleModal('modalTest')">
            close
        </span>
        <h3>Inicia Sesión</h3>
        <form id="login-form" method="post" role="form">
            <div class="login-errors" id="login-errors"></div>
            <div class="loginForm">
                <label for="correo">Correo Electrónico:
                    <input type="text" name="correo" id="coreo" required class="input-field" autocomplete="username">
                </label>
                <label for="password">Contraseña:
                    <input type="password" name="password" id="password" required class="input-field"
                        autocomplete="current-password">
                </label>
                <a href="#" class="linkUnderline-1 olvidePassword-link">¿Olvidaste la contraseña?</a>
            </div>
            <input type="submit" class="boton boton-primario btn-entrar" value="Entrar">
        </form>
        <a href="#" onclick="toggleModal('modalRegistro')" class="linkUnderline-1 registrarse-link">Registrarse</a>
    </div>
</div>

<script>
var loginForm = document.getElementById("login-form");

function handleLoginForm(event) {
    event.preventDefault();
    let formData = new FormData(loginForm);
    const email = formData.get("correo");
    const password = formData.get("password");
    login(email, password);
}
loginForm.addEventListener("submit", handleLoginForm);

function login(email, password) {

    var objData = {
        correo: email,
        contrasena: password
    };
    $.ajax({
        url: 'php/loginForm.php',
        data: objData,
        type: 'POST',
        success: function(response) {
            console.log(response)
            if (response.substring(0, 2) !== "OK") {
                console.log(response)
                response = JSON.parse(response)
                console.log(response)
                const errorList = [];
                let errorsDiv = document.getElementById('login-errors')
                response.forEach(error => {
                    const listItem = document.createElement("p");
                    listItem.setAttribute("class", "error");

                    listItem.innerHTML = `
                                ${error}
                            `;

                    errorList.push(listItem);

                    errorsDiv.appendChild(listItem);

                    console.log(error)
                })
                alert('Error')
            } else {
                location.reload();
            }
        },
        error: function(xhr, status, error) {
            alert("ERROR: " + error);
        }
    });
}
</script>