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
    const qrImg = document.getElementById('qrImg');

    const donData = document.getElementById('donData');
    const contactData = document.getElementById('contactData');
    const aliasText = document.getElementById('aliasText');
    const cvuText = document.getElementById('cvuText');
    const mpLink = document.getElementById('mpLink');
    const mpLinkRow = document.getElementById('mpLinkRow');
    const contactText = document.getElementById('contactText');
    const waLink = document.getElementById('waLink');
    const donMessage = document.getElementById('donMessage');
    const qrNoteDinero = document.getElementById('qrNoteDinero');
    const qrNotePresencial = document.getElementById('qrNotePresencial');

    if (!open || !modal) return;

    function clearSelection(){
      [btnPres, btnDin].forEach(b => {
        if (!b) return;
        b.classList.remove('selected');
        b.setAttribute('aria-pressed','false');
      });
      if (qrArea) qrArea.classList.add('hidden');
      if (donData) donData.classList.add('hidden');
      if (contactData) contactData.classList.add('hidden');
      if (qrImg) { qrImg.src = ''; qrImg.classList.add('hidden'); }
      if (qrNoteDinero) qrNoteDinero.classList.add('hidden');
      if (qrNotePresencial) qrNotePresencial.classList.add('hidden');
    }

    open.addEventListener('click', () => {
      clearSelection();
      modal.classList.remove('hidden');
    });
    if (close) close.addEventListener('click', () => { modal.classList.add('hidden'); });
    modal.addEventListener('click', (e) => { if (e.target === modal) modal.classList.add('hidden'); });

    function formatPhone(raw){
      if (!raw) return '-';
      const only = raw.replace(/\D/g,'');
      // caso típico: país 54 + area 11 + 8 digitos => 54 11 23619153
      if (only.length >= 11 && only.startsWith('54')){
        const rest = only.slice(2);
        const area = rest.slice(0,2);
        const local = rest.slice(2);
        const part1 = local.slice(0,4);
        const part2 = local.slice(4);
        return `+54 ${area} ${part1}${part2?'-'+part2:''}`;
      }
      // fallback con signo +
      if (only.length > 0) return '+' + only;
      return raw;
    }

    // normaliza una URL: si ya tiene http(s) la devuelve tal cual, si no añade 'https://'
    function normalizeUrl(url){
      if (!url) return '';
      url = String(url).trim();
      // si ya tiene esquema -> devolver
      if (/^https?:\/\//i.test(url)) return url;
      // evitar barras al inicio y añadir https://
      return 'https://' + url.replace(/^\/+/, '');
    }

    function generateQRFor(url){
      const full = normalizeUrl(url);
      if (!full) {
        if (qrImg) qrImg.classList.add('hidden');
        return;
      }
      if (qrImg){
        const qrUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=220x220&data=' + encodeURIComponent(full);
        qrImg.src = qrUrl;
        qrImg.classList.remove('hidden');
      }
      // canvas remain hidden / unused
    }

    function showDinero(){
      const { link_pago_mp = '', alias_mp = '', cvu_mp = '' } = (window.DETALLE_DATA || {});
      // visual
      if (btnPres) { btnPres.classList.remove('selected'); btnPres.setAttribute('aria-pressed','false'); }
      if (btnDin) { btnDin.classList.add('selected'); btnDin.setAttribute('aria-pressed','true'); }
      if (qrArea) qrArea.classList.remove('hidden');

      // mensaje
      if (donMessage) donMessage.textContent = 'Gracias por tu voluntad de donar. Escaneá el QR para realizar una donación mediante transferencia bancaria.';
      if (qrNoteDinero) qrNoteDinero.classList.remove('hidden');
      if (qrNotePresencial) qrNotePresencial.classList.add('hidden');

      // QR y link normalizados
      const fullLink = normalizeUrl(link_pago_mp);
      if (fullLink) {
        generateQRFor(fullLink);
        if (mpLink){
          mpLink.href = fullLink;
          mpLinkRow.classList.remove('hidden');
        }
      } else {
        if (qrImg) qrImg.classList.add('hidden');
        if (mpLinkRow) mpLinkRow.classList.add('hidden');
      }

      // mostrar Alias/CVU (solo cuando corresponde)
      if (donData) {
        if (aliasText) aliasText.textContent = alias_mp || '-';
        if (cvuText) cvuText.textContent = cvu_mp || '-';
        // donData visible solo si hay datos (alias/cvu o link)
        const hasData = alias_mp || cvu_mp || fullLink;
        if (hasData) donData.classList.remove('hidden'); else donData.classList.add('hidden');
      }
      // ocultar contacto
      if (contactData) contactData.classList.add('hidden');
    }

    function showPresencial(){
      const { whatsapp_link = '', telefono_contacto = '' } = (window.DETALLE_DATA || {});
      if (btnDin) { btnDin.classList.remove('selected'); btnDin.setAttribute('aria-pressed','false'); }
      if (btnPres) { btnPres.classList.add('selected'); btnPres.setAttribute('aria-pressed','true'); }
      if (qrArea) qrArea.classList.remove('hidden');

      // mensaje
      if (donMessage) donMessage.textContent = 'Gracias por tu voluntad de donar. Acercate al punto indicado en la dirección o coordiná con el creador.';
      if (qrNotePresencial) qrNotePresencial.classList.remove('hidden');
      if (qrNoteDinero) qrNoteDinero.classList.add('hidden');

      // QR -> whatsapp_link (normalizado)
      const fullWa = normalizeUrl(whatsapp_link);
      if (fullWa) {
        generateQRFor(fullWa);
        if (waLink) waLink.href = fullWa;
      } else {
        if (qrImg) qrImg.classList.add('hidden');
        if (waLink) waLink.href = '#';
      }

      // mostrar contacto (solo datos de contacto)
      if (contactData) {
        const formatted = formatPhone(telefono_contacto || '');
        if (contactText) contactText.textContent = formatted;
        contactData.classList.remove('hidden');
      }
      // ocultar datos de dinero completamente
      if (donData) donData.classList.add('hidden');
      if (mpLinkRow) mpLinkRow.classList.add('hidden');
    }

    if (btnDin) btnDin.addEventListener('click', showDinero);
    if (btnPres) btnPres.addEventListener('click', showPresencial);
  }
})();