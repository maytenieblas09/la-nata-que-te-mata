(function () {
  const overlay = document.getElementById('acceso-inicial-login');
  const form = document.getElementById('formulario-acceso-login');
  const correoInput = document.getElementById('correo-acceso-login');
  const passInput = document.getElementById('contrasena-acceso-login');
  const mensaje = document.getElementById('mensaje-acceso-login');
  const btnIniciar = document.getElementById('modo-iniciar-login');
  const btnRegistrar = document.getElementById('modo-registrar-login');
  const btnEnviar = document.getElementById('boton-enviar-login');
  const ayuda = document.getElementById('texto-ayuda-login');

  let modo = 'login';

  function setModo(nuevoModo) {
    modo = nuevoModo;
    mensaje.textContent = '';
    if (modo === 'login') {
      btnIniciar.style.background = '#c99542';
      btnIniciar.style.color = '#321a11';
      btnRegistrar.style.background = '#542d1c';
      btnRegistrar.style.color = '#fff';
      btnEnviar.textContent = 'Ingresar';
      ayuda.textContent = 'Ingresa tus datos para acceder a nuestra página.';
      passInput.autocomplete = 'current-password';
    } else {
      btnIniciar.style.background = '#542d1c';
      btnIniciar.style.color = '#fff';
      btnRegistrar.style.background = '#c99542';
      btnRegistrar.style.color = '#321a11';
      btnEnviar.textContent = 'Crear cuenta';
      ayuda.textContent = 'Regístrate con tu correo para crear una cuenta.';
      passInput.autocomplete = 'new-password';
    }
  }

  btnIniciar.addEventListener('click', () => setModo('login'));
  btnRegistrar.addEventListener('click', () => setModo('registro'));

  function ocultarOverlay() { overlay.style.display = 'none'; }
  function mostrarOverlay() { overlay.style.display = 'flex'; }

  async function revisarSesion() {
    try {
      const r = await fetch('auth.php?action=status', { credentials: 'same-origin' });
      const data = await r.json();
      if (data.ok && data.autenticado) {
        ocultarOverlay();
      } else {
        mostrarOverlay();
      }
    } catch (_) {
      mostrarOverlay();
    }
  }

  form.addEventListener('submit', async (e) => {
    e.preventDefault();
    mensaje.textContent = '';

    const correo = correoInput.value.trim();
    const contrasena = passInput.value;

    if (!correo || !contrasena) {
      mensaje.textContent = 'Escribe tu correo y contraseña.';
      return;
    }

    btnEnviar.disabled = true;
    const accion = modo === 'registro' ? 'registro' : 'login';

    try {
      const resp = await fetch('auth.php?action=' + accion, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        credentials: 'same-origin',
        body: JSON.stringify({ correo, contrasena })
      });
      const data = await resp.json();

      if (data.ok) {
        ocultarOverlay();
      } else {
        mensaje.textContent = data.mensaje || 'No se pudo completar la solicitud.';
      }
    } catch (_) {
      mensaje.textContent = 'No se pudo conectar con el servidor.';
    } finally {
      btnEnviar.disabled = false;
    }
  });

  revisarSesion();
})();
