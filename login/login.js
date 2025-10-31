// login.js - show last char briefly + eye toggle + ensure real password submitted

const passwordInput = document.getElementById('password');
const toggleIcon = document.getElementById('toggleIcon');
const form = document.getElementById('loginForm');

let realPassword = "";
let lastTimeout = null;
let showingFull = false;

// Toggle eye icon (images must exist in login/ folder)
toggleIcon.addEventListener('click', () => {
  if (showingFull) {
    // pasar a oculto
    passwordInput.type = 'password';
    toggleIcon.src = 'img/eye_closed.png';
    showingFull = false;
    // cuando ocultamos mostramos bullets - el value real permanece en realPassword
    passwordInput.value = '•'.repeat(realPassword.length);
  } else {
    // mostrar la contraseña real completa
    showingFull = true;
    passwordInput.type = 'text';
    toggleIcon.src = 'img/eye_open.png';
    passwordInput.value = realPassword;
    clearTimeout(lastTimeout);
  }
});

// Input handler: mostramos SOLO la última letra por 400ms
passwordInput.addEventListener('input', (e) => {
  // Si estamos mostrando todo, actualizamos realPassword con lo escrito
  if (showingFull) {
    realPassword = passwordInput.value;
    return;
  }

  const current = passwordInput.value;

  // Si current está solo con bullets (por ejemplo al cargar), no hacemos nada especial
  // Comparamos longitudes para decidir si se borró o se agregó
  if (current.length < realPassword.length) {
    // borrado/backspace
    realPassword = realPassword.slice(0, current.length);
  } else if (current.length > realPassword.length) {
    // se agregaron caracteres (incluye pegar)
    // Extraemos la "nueva porción" desde current - puede contener la última letra visible
    // Para simplificar, tomamos lo agregado al final:
    const added = current.slice(realPassword.length);
    realPassword += added;
  } else {
    // misma longitud => nada a hacer
  }

  // Construimos máscara: bullets para todo menos la última letra visible
  const lastChar = realPassword.slice(-1) || '';
  const masked = (realPassword.length > 1 ? '•'.repeat(realPassword.length - 1) : '') + lastChar;

  // Mostramos la máscara (en modo texto) para que el usuario vea la última letra
  passwordInput.type = 'text';
  passwordInput.value = masked;

  // Ocultamos la última letra luego de 400ms y devolvemos el campo a type=password
  clearTimeout(lastTimeout);
  lastTimeout = setTimeout(() => {
    if (!showingFull) {
      passwordInput.type = 'password';
      // al asignar realPassword a input[type=password] el navegador lo mostrará como bullets
      passwordInput.value = realPassword;
    }
  }, 400);
});

// Antes de enviar el formulario, aseguramos que el campo contenga la contraseña real
form.addEventListener('submit', (e) => {
  // si está en modo oculto o con máscara, colocamos el valor real para que el servidor lo reciba
  passwordInput.type = 'text'; // aseguramos que el value se pueda escribir tal cual
  passwordInput.value = realPassword;
});