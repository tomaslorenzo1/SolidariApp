// perfil/perfil.js
document.addEventListener('DOMContentLoaded', () => {
  const avatarWrap = document.getElementById('avatarWrap');
  const fotoInput = document.getElementById('fotoInput');
  const avatarImg = document.getElementById('avatarImg');

  // click en avatar abre selector
  avatarWrap.addEventListener('click', () => {
    fotoInput.click();
  });

  // al elegir archivo, mostrar preview y enviar el form
  fotoInput.addEventListener('change', () => {
    const f = fotoInput.files[0];
    if (!f) return;

    if (f.size > 2 * 1024 * 1024) {
      alert('El archivo es demasiado grande (máx 2MB).');
      fotoInput.value = '';
      return;
    }

    const reader = new FileReader();
    reader.onload = function(ev) {
      avatarImg.src = ev.target.result;
    };
    reader.readAsDataURL(f);

    setTimeout(() => {
      document.getElementById('fotoForm').submit();
    }, 400);
  });
});