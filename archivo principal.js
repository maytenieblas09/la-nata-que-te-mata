document.getElementById("loginForm").addEventListener("submit", async (e) => {
    e.preventDefault();

    const correo = document.getElementById("email").value.trim();
    const password = document.getElementById("password").value;

    try {
        const respuesta = await fetch("acceso.php", {
            method: "POST",
            headers: {
                "Content-Type": "application/json"
            },
            body: JSON.stringify({ correo, password })
        });

        const resultado = await respuesta.json();

        if (resultado.ok) {
            window.location.href = "index.html";
        } else {
            alert(resultado.mensaje || "No se pudo iniciar sesión.");
        }
    } catch {
        alert("No se pudo conectar con el servidor.");
    }
});