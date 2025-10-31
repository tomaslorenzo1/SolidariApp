const passwordInput = document.getElementById("password");
const toggleIcon = document.getElementById("toggleIcon");
let realPassword = "";
let lastTimeout = null;

// 👁️ Alternar mostrar/ocultar contraseña
toggleIcon.addEventListener("click", () => {
  if (passwordInput.type === "password") {
    passwordInput.type = "text";
    toggleIcon.src = "img/eye_open.png";
  } else {
    passwordInput.type = "password";
    toggleIcon.src = "img/eye_closed.png";
  }
});

// 🔒 Mostrar solo la última letra un instante
passwordInput.addEventListener("input", function (e) {
  const value = passwordInput.value;

  // Detectar si se borró algo
  if (value.length < realPassword.length) {
    realPassword = realPassword.slice(0, value.length);
  } else {
    // Se agregó una letra
    const newChar = value.charAt(value.length - 1);
    realPassword += newChar;
  }

  // Construir el texto enmascarado con la última letra visible
  let masked = "•".repeat(realPassword.length - 1) + realPassword.slice(-1);
  passwordInput.type = "text";
  passwordInput.value = masked;

  // Después de 0.4s ocultar también la última letra
  clearTimeout(lastTimeout);
  lastTimeout = setTimeout(() => {
    passwordInput.type = "password";
    passwordInput.value = realPassword;
  }, 400);
});

// Asegurar que al enviar se use la contraseña real
passwordInput.form.addEventListener("submit", () => {
  passwordInput.value = realPassword;
});