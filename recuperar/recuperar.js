// recuperar.js
// Maneja: mostrar/ocultar + mostrar última letra brevemente para dos campos de contraseña

(function () {
    // Config
    const revealMs = 400; // tiempo que se muestra la última letra
  
    function setupMaskedInput(inputId, toggleId) {
      const input = document.getElementById(inputId);
      const toggle = document.getElementById(toggleId);
      if (!input || !toggle) return null;
  
      let real = "";
      let lastTimeout = null;
      let showingFull = false;
  
      function setIcon(open) {
        // ajusta ruta si tus imágenes están en otra carpeta
        toggle.src = open ? 'img/eye_open.png' : 'img/eye_closed.png';
      }
  
      // Inicializar icono
      setIcon(false);
  
      // Click en ojo: toggle mostrar/ocultar
      toggle.addEventListener('click', (e) => {
        e.preventDefault();
        if (showingFull) {
          // pasar a oculto
          showingFull = false;
          input.type = 'password';
          // mostrar bullets (valor real se mantiene)
          input.value = '•'.repeat(real.length);
          setIcon(false);
        } else {
          // mostrar real
          showingFull = true;
          input.type = 'text';
          input.value = real;
          setIcon(true);
          if (lastTimeout) {
            clearTimeout(lastTimeout);
            lastTimeout = null;
          }
        }
        // mantener el foco en el input
        input.focus();
      });
  
      // Input handler: manejar adiciones, borrados y mostrar última letra
      input.addEventListener('input', (e) => {
        // Si estamos en modo "mostrar todo", actualizamos real y salimos
        if (showingFull) {
          real = input.value;
          return;
        }
  
        const current = input.value;
        // caso borrado
        if (current.length < real.length) {
          // actualizar real cortando
          real = real.slice(0, current.length);
        } else if (current.length > real.length) {
          // agregado (puede ser múltiples caracteres si se pegó)
          const added = current.slice(real.length);
          // añadimos lo que se escribió al real
          real += added;
        } else {
          // misma longitud (posible que el navegador haya convertido a bullets), no cambiamos real
        }
  
        // Construir máscara: bullets para todo menos la última letra
        const lastChar = real.slice(-1) || '';
        const masked = (real.length > 1 ? '•'.repeat(real.length - 1) : '') + lastChar;
  
        // Mostrar máscara (como texto) para que el usuario vea la última letra
        input.type = 'text';
        input.value = masked;
  
        // Ocultar la última letra después de revealMs
        if (lastTimeout) clearTimeout(lastTimeout);
        lastTimeout = setTimeout(() => {
          if (!showingFull) {
            input.type = 'password';
            input.value = real; // navegador mostrará bullets
          }
          lastTimeout = null;
        }, revealMs);
      });
  
      // al perder foco (opcional) garantizamos que el campo muestre bullets
      input.addEventListener('blur', () => {
        if (!showingFull) {
          // limpiar cualquier timeout pendiente
          if (lastTimeout) {
            clearTimeout(lastTimeout);
            lastTimeout = null;
          }
          input.type = 'password';
          input.value = real;
        }
      });
  
      // Si el usuario pega con Ctrl+V, el input event anterior ya cubre, pero podemos mejorar con paste
      input.addEventListener('paste', (e) => {
        // delay para que el valor pegado se actualice
        setTimeout(() => {
          if (showingFull) {
            real = input.value;
          } else {
            // forzamos el manejo como si hubiera escrito
            const current = input.value;
            if (current.length >= real.length) {
              const added = current.slice(real.length);
              real += added;
            }
            // mostrar última letra breve
            input.type = 'text';
            input.value = (real.length > 1 ? '•'.repeat(real.length - 1) : '') + (real.slice(-1) || '');
            if (lastTimeout) clearTimeout(lastTimeout);
            lastTimeout = setTimeout(() => {
              if (!showingFull) {
                input.type = 'password';
                input.value = real;
              }
              lastTimeout = null;
            }, revealMs);
          }
        }, 10);
      });
  
      return {
        getReal: () => real,
        setReal: (v) => { real = v; input.value = (showingFull ? v : '•'.repeat(v.length)); }
      };
    }
  
    // Vincular ambos campos
    const pwd1 = setupMaskedInput('password', 'togglePassword');
    const pwd2 = setupMaskedInput('confirm_password', 'toggleConfirm');
  
    // Al enviar el formulario, asegurarnos de enviar el valor real
    const form = document.getElementById('resetForm');
    if (form) {
      form.addEventListener('submit', (e) => {
        // Antes de enviar colocamos los valores reales en los inputs
        const real1 = pwd1 ? pwd1.getReal() : (document.getElementById('password').value || '');
        const real2 = pwd2 ? pwd2.getReal() : (document.getElementById('confirm_password').value || '');
  
        // Asignar temporalmente tipo text y valor real para que via POST llegue la contraseña correcta
        const i1 = document.getElementById('password');
        const i2 = document.getElementById('confirm_password');
        if (i1) { i1.type = 'text'; i1.value = real1; }
        if (i2) { i2.type = 'text'; i2.value = real2; }
  
        // pequeño timeout opcional para que el navegador actualice antes de enviar
        // (no necesario pero lo dejamos seguro)
        // no evitar el envío
      });
    }
  
  })();  