// perfil/perfil.js
document.addEventListener('DOMContentLoaded', function(){
  const avatarWrap = document.getElementById('avatarWrap');
  const fotoInput = document.getElementById('fotoInput');
  const fotoForm = document.getElementById('fotoForm');
  if (avatarWrap && fotoInput && fotoForm) {
    avatarWrap.addEventListener('click', () => fotoInput.click());
    fotoInput.addEventListener('change', () => {
      if (fotoInput.files && fotoInput.files.length) {
        fotoForm.submit();
      }
    });
  }
});