// detalle/detalle.js
// Carrusel simple, modal de donación y QR placeholder
(function(){
  document.addEventListener('DOMContentLoaded', () => {
    setupCarousel();
    setupDonationModal();
  });

  function setupCarousel(){
    const track = document.getElementById('cTrack');
    const prev = document.getElementById('cPrev');
    const next = document.getElementById('cNext');
    if (!track) return;

    const slides = Array.from(track.children);
    let idx = 0;
    let timer = null;

    function go(n){
      idx = (n + slides.length) % slides.length;
      const offset = -idx * 100;
      track.style.transform = `translateX(${offset}%)`;
    }
    function auto(){
      clearInterval(timer);
      timer = setInterval(() => go(idx + 1), 5000);
    }

    if (prev) prev.addEventListener('click', () => { go(idx - 1); auto(); });
    if (next) next.addEventListener('click', () => { go(idx + 1); auto(); });

    go(0);
    auto();
  }

  function setupDonationModal(){
    const open = document.getElementById('btnDonate');
    const modal = document.getElementById('donateModal');
    const close = document.getElementById('mClose');
    const btnPres = document.getElementById('donarPresencial');
    const btnDin = document.getElementById('donarDinero');
    const qrArea = document.getElementById('qrArea');
    const qrCanvas = document.getElementById('qrCanvas');

    if (!open || !modal) return;

    open.addEventListener('click', () => { modal.classList.remove('hidden'); });
    if (close) close.addEventListener('click', () => { modal.classList.add('hidden'); });
    modal.addEventListener('click', (e) => { if (e.target === modal) modal.classList.add('hidden'); });

    if (btnPres) btnPres.addEventListener('click', () => {
      alert('Gracias por tu voluntad de donar. Acercate al punto indicado en la dirección o coordiná con el creador.');
      modal.classList.add('hidden');
    });

    if (btnDin && qrArea) btnDin.addEventListener('click', () => {
      qrArea.classList.remove('hidden');
      const { link_pago_mp = '', alias_mp = '', cvu_mp = '' } = (window.DETALLE_DATA || {});
      const qrImg = document.getElementById('qrImg');
      const donData = document.getElementById('donData');
      const aliasText = document.getElementById('aliasText');
      const cvuText = document.getElementById('cvuText');
      const mpLink = document.getElementById('mpLink');
      const mpLinkRow = document.getElementById('mpLinkRow');

      // set alias/cvu text
      if (donData) {
        if (aliasText) aliasText.textContent = alias_mp || '-';
        if (cvuText) cvuText.textContent = cvu_mp || '-';
        donData.classList.remove('hidden');
      }

      // reset visuals
      if (qrImg) qrImg.classList.add('hidden');
      if (qrCanvas) qrCanvas.classList.add('hidden');

      // show QR by link if available
      if (link_pago_mp && /^https?:\/\//i.test(link_pago_mp)) {
        if (qrImg) {
          const url = 'https://api.qrserver.com/v1/create-qr-code/?size=220x220&data=' + encodeURIComponent(link_pago_mp);
          qrImg.src = url;
          qrImg.classList.remove('hidden');
        }
        if (mpLinkRow && mpLink) {
          mpLink.href = link_pago_mp;
          mpLinkRow.classList.remove('hidden');
        }
      } else {
        if (mpLinkRow) mpLinkRow.classList.add('hidden');
      }
    });
  }
})();