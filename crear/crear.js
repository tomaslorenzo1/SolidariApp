// crear/crear.js
document.addEventListener('DOMContentLoaded', () => {
  // Tabs
  const tabCamp = document.getElementById('tabCamp');
  const tabCentro = document.getElementById('tabCentro');
  const formCamp = document.getElementById('formCampana');
  const formCent = document.getElementById('formCentro');

  tabCamp.addEventListener('click', () => {
    tabCamp.classList.add('active'); tabCentro.classList.remove('active');
    formCamp.classList.remove('hidden'); formCent.classList.add('hidden');
    tabCamp.setAttribute('aria-selected','true'); tabCentro.setAttribute('aria-selected','false');
    window.scrollTo({top:0, behavior:'smooth'});
  });
  tabCentro.addEventListener('click', () => {
    tabCentro.classList.add('active'); tabCamp.classList.remove('active');
    formCent.classList.remove('hidden'); formCamp.classList.add('hidden');
    tabCentro.setAttribute('aria-selected','true'); tabCamp.setAttribute('aria-selected','false');
    window.scrollTo({top:0, behavior:'smooth'});
  });

  /* ------------------ CATEGORIES CHIPS with suggestions ------------------ */
  function setupChips(inputId, suggestId, chipsContainerId, hiddenId) {
    const input = document.getElementById(inputId);
    const suggestWrap = document.getElementById(suggestId);
    const chipsWrap = document.getElementById(chipsContainerId);
    const hidden = document.getElementById(hiddenId);

    // lista base de sugerencias (puede crecer o venir desde DB)
    const predefined = ['Ropa','Alimentos','Muebles','Voluntariado','Útiles','Medicamentos','Higiene','Dinero','Ropa infantil','Calzado','Juguetes'];

    function renderChips() {
      chipsWrap.innerHTML = '';
      const values = (hidden.value || '').split(',').map(s => s.trim()).filter(Boolean);
      values.forEach((v, idx) => {
        const el = document.createElement('div');
        el.className = 'chip';
        el.innerHTML = `${v} <span class="remove" data-idx="${idx}" title="Eliminar">×</span>`;
        chipsWrap.appendChild(el);
      });
    }

    function updateSuggestions() {
      const q = input.value.trim().toLowerCase();
      suggestWrap.innerHTML = '';
      if (!q) { suggestWrap.classList.add('hidden'); return; }
      // coincidencias que comienzan con q primero, luego las que lo contienen
      const starts = predefined.filter(p => p.toLowerCase().startsWith(q));
      const contains = predefined.filter(p => !p.toLowerCase().startsWith(q) && p.toLowerCase().includes(q));
      const results = [...new Set([...starts, ...contains])].slice(0, 8);

      // además siempre mostraremos la "creación rápida" con exactamente lo que el usuario escribió
      const uniqueInput = input.value.trim();
      if (uniqueInput) {
        // si no coincide exactamente con una sugerencia, lo mostramos como primera opción "Crear: <texto>"
        const existsExact = predefined.some(p => p.toLowerCase() === uniqueInput.toLowerCase());
        if (!existsExact) {
          const div = document.createElement('div');
          div.className = 's-item';
          div.dataset.value = uniqueInput;
          div.innerHTML = `<span>Crear: <strong>${escapeHtml(uniqueInput)}</strong></span><small>usará exactamente esto</small>`;
          suggestWrap.appendChild(div);
        }
      }

      results.forEach(r => {
        const div = document.createElement('div');
        div.className = 's-item';
        div.dataset.value = r;
        div.innerHTML = `<span>${escapeHtml(r)}</span><small>sugerido</small>`;
        suggestWrap.appendChild(div);
      });

      if (suggestWrap.children.length) suggestWrap.classList.remove('hidden');
      else suggestWrap.classList.add('hidden');
    }

    // add by pressing Enter or comma
    input.addEventListener('keydown', (e) => {
      if (e.key === 'Enter' || e.key === ',') {
        e.preventDefault();
        addChip(input.value.trim());
      }
      if (e.key === 'Escape') {
        input.value = '';
        suggestWrap.classList.add('hidden');
      }
    });

    input.addEventListener('input', () => {
      updateSuggestions();
    });

    input.addEventListener('blur', () => {
      // dejar un pequeño delay para que el click en sugerencia funcione
      setTimeout(() => {
        suggestWrap.classList.add('hidden');
      }, 180);
    });

    suggestWrap.addEventListener('click', (e) => {
      const item = e.target.closest('.s-item');
      if (!item) return;
      const val = item.dataset.value;
      if (val) addChip(val.trim());
      input.value = '';
      suggestWrap.classList.add('hidden');
      input.focus();
    });

    function addChip(val) {
      if (!val) return;
      val = val.replace(/\s{2,}/g,' ').trim();
      const existing = (hidden.value || '').split(',').map(s=>s.trim()).filter(Boolean);
      // evitar duplicados (case-insensitive)
      if (existing.some(x => x.toLowerCase() === val.toLowerCase())) { input.value = ''; return; }
      existing.push(val);
      hidden.value = existing.join(',');
      input.value = '';
      renderChips();
    }

    chipsWrap.addEventListener('click', (e) => {
      if (e.target.classList.contains('remove')) {
        const idx = Number(e.target.dataset.idx);
        const arr = (hidden.value || '').split(',').map(s=>s.trim()).filter(Boolean);
        arr.splice(idx,1);
        hidden.value = arr.join(',');
        renderChips();
      }
    });

    // initial render (if any)
    renderChips();
  }

  setupChips('catInputCamp','catSuggestCamp','catChipsCamp','categorias_hidden_camp');
  setupChips('catInputCentro','catSuggestCentro','catChipsCentro','categorias_hidden_centro');

  /* ------------------ META slider sync (visual + editable) ------------------ */
  const metaRange = document.getElementById('metaRange');
  const metaText = document.getElementById('metaText');
  if (metaRange && metaText) {
    // inicial
    metaText.value = numberWithCommas(metaRange.value);

    metaRange.addEventListener('input', () => {
      metaText.value = numberWithCommas(metaRange.value);
      updateRangeBackground(metaRange);
    });

    metaText.addEventListener('input', () => {
      // permitir comas y números, quitar todo lo demás
      const raw = metaText.value.replace(/[^\d]/g, '');
      const n = raw === '' ? 0 : Math.min(parseInt(raw,10), Number(metaRange.max));
      metaRange.value = n;
      metaText.value = numberWithCommas(n);
      updateRangeBackground(metaRange);
    });
  }

  function numberWithCommas(x){
    if (x === null || x === undefined) return '';
    const s = String(x);
    return s.replace(/\B(?=(\d{3})+(?!\d))/g, ",");
  }

  // pintar gradiente del slider según valor
  function updateRangeBackground(rangeEl){
    const min = Number(rangeEl.min) || 0;
    const max = Number(rangeEl.max) || 100;
    const val = Number(rangeEl.value) || 0;
    const pct = Math.round((val - min) * 100 / (max - min));
    rangeEl.style.background = `linear-gradient(90deg, var(--blue) ${pct}%, #e9f2fb ${pct}%)`;
  }
  // inicial update
  if (metaRange) updateRangeBackground(metaRange);

  /* ------------------ ADDRESS AUTOCOMPLETE (Nominatim) ------------------ */
  function setupAddressAutocomplete(inputId, resultsId, latId, lngId) {
    const input = document.getElementById(inputId);
    const results = document.getElementById(resultsId);
    const latField = document.getElementById(latId);
    const lngField = document.getElementById(lngId);
    let timer = null;

    input.addEventListener('input', () => {
      const q = input.value.trim();
      latField.value = '';
      lngField.value = '';
      if (!q) { results.classList.add('hidden'); results.innerHTML = ''; return; }
      clearTimeout(timer);
      timer = setTimeout(() => {
        fetch(`https://nominatim.openstreetmap.org/search?format=json&addressdetails=1&limit=6&q=${encodeURIComponent(q)}`, {
          headers: { 'Accept': 'application/json' }
        }).then(r => r.json()).then(data => {
          results.innerHTML = '';
          if (!Array.isArray(data) || data.length === 0) { results.classList.add('hidden'); return; }
          data.forEach(item => {
            const div = document.createElement('div');
            div.className = 'addr-item';
            const display = item.display_name;
            div.textContent = display;
            div.dataset.lat = item.lat;
            div.dataset.lon = item.lon;
            div.addEventListener('click', () => {
              input.value = display;
              latField.value = item.lat;
              lngField.value = item.lon;
              results.classList.add('hidden');
            });
            results.appendChild(div);
          });
          results.classList.remove('hidden');
        }).catch(err => {
          console.error('Nominatim error', err);
        });
      }, 350);
    });

    // click fuera para ocultar
    document.addEventListener('click', (e) => {
      if (!input.contains(e.target) && (!results || !results.contains(e.target))) {
        if (results) results.classList.add('hidden');
      }
    });
  }

  setupAddressAutocomplete('addressCamp','addrResultsCamp','latCamp','lngCamp');
  setupAddressAutocomplete('addressCentro','addrResultsCentro','latCentro','lngCentro');

  /* ------------------ IMAGE PREVIEW & REMOVE ------------------ */
  function setupImagePreview(inputId, previewId) {
    const input = document.getElementById(inputId);
    const preview = document.getElementById(previewId);

    function clearPreview() { preview.innerHTML = ''; }

    // file button behavior: clicking label triggers hidden input (handled by HTML)
    input.addEventListener('change', () => {
      clearPreview();
      const files = Array.from(input.files).slice(0, 6); // limitar a 6
      files.forEach((f, idx) => {
        if (!f.type.startsWith('image/')) return;
        const reader = new FileReader();
        reader.onload = (ev) => {
          const box = document.createElement('div');
          box.className = 'preview';
          box.innerHTML = `<img src="${ev.target.result}" alt="img"> <div class="del" data-idx="${idx}" title="Eliminar">×</div>`;
          preview.appendChild(box);
        };
        reader.readAsDataURL(f);
      });
    });

    // eliminar preview (esto no elimina del input File, solo la previsualizacion)
    preview.addEventListener('click', (e) => {
      if (e.target.classList.contains('del')) {
        const idx = Number(e.target.dataset.idx);
        const dataTransfer = new DataTransfer();
        const files = Array.from(input.files);
        files.forEach((f, i) => { if (i !== idx) dataTransfer.items.add(f); });
        input.files = dataTransfer.files;
        // volver a renderizar
        preview.innerHTML = '';
        Array.from(input.files).forEach((f, i) => {
          const reader = new FileReader();
          reader.onload = (ev) => {
            const box = document.createElement('div');
            box.className = 'preview';
            box.innerHTML = `<img src="${ev.target.result}" alt="img"> <div class="del" data-idx="${i}" title="Eliminar">×</div>`;
            preview.appendChild(box);
          };
          reader.readAsDataURL(f);
        });
      }
    });
  }

  setupImagePreview('imgsCamp','previewCamp');
  setupImagePreview('imgsCentro','previewCentro');

  /* ------------------ Ajustes UI menores ------------------ */
  // asegurar que al subir imagenes y al final el bottom nav no tape botones
  window.addEventListener('resize', () => {
    document.querySelector('main.content').style.paddingBottom = (72 + 30) + 'px';
  });

  // evitar envío si lat/lng no seleccionado (en caso de búsqueda)
  const submitCamp = document.querySelector('#formCampana');
  if (submitCamp) {
    submitCamp.addEventListener('submit', (e) => {
      // si hay texto en dirección pero no lat/lng, prevenir y sugerir usar la lista
      const adr = document.getElementById('addressCamp');
      const lat = document.getElementById('latCamp');
      if (adr.value.trim() && (!lat.value || lat.value === '')) {
        if (!confirm('No seleccionaste una dirección desde la lista. ¿Querés continuar igual (la ubicación puede quedar imprecisa)?')) {
          e.preventDefault();
          return;
        }
      }
      // asegurarse de pasar categorías (el hidden)
      const hidden = document.getElementById('categorias_hidden_camp');
      if (!hidden.value) hidden.value = '';
      // quitar comas sobrantes en meta
      const mt = document.getElementById('metaText');
      if (mt) {
        mt.value = mt.value.replace(/[^\d]/g, '') || '0';
      }
    });
  }

  const submitCentro = document.querySelector('#formCentro');
  if (submitCentro) {
    submitCentro.addEventListener('submit', (e) => {
      const adr = document.getElementById('addressCentro');
      const lat = document.getElementById('latCentro');
      if (adr.value.trim() && (!lat.value || lat.value === '')) {
        if (!confirm('No seleccionaste una dirección desde la lista. ¿Querés continuar igual (la ubicación puede quedar imprecisa)?')) {
          e.preventDefault();
          return;
        }
      }
      const hidden = document.getElementById('categorias_hidden_centro');
      if (!hidden.value) hidden.value = '';
    });
  }

  /* Helper: escape html for suggestions */
  function escapeHtml(text = '') {
    return String(text).replace(/[&<>"']/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[m]));
  }
});