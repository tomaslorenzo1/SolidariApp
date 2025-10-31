// crear/crear.js
document.addEventListener('DOMContentLoaded', () => {
  // Tabs
  const tabCamp = document.getElementById('tabCamp');
  const tabCentro = document.getElementById('tabCentro');
  const formCamp = document.getElementById('formCampana');
  const formCent = document.getElementById('formCentro');

  // Ajustar visibilidad inicial según lo renderizado por PHP (y accessible role)
  if (tabCamp && tabCentro) {
    tabCamp.addEventListener('click', () => {
      tabCamp.classList.add('active'); tabCentro.classList.remove('active');
      if (formCamp) formCamp.classList.remove('hidden');
      if (formCent) formCent.classList.add('hidden');
      tabCamp.setAttribute('aria-selected','true'); tabCentro.setAttribute('aria-selected','false');
      window.scrollTo({top:0, behavior:'smooth'});
    });
    tabCentro.addEventListener('click', () => {
      tabCentro.classList.add('active'); tabCamp.classList.remove('active');
      if (formCent) formCent.classList.remove('hidden');
      if (formCamp) formCamp.classList.add('hidden');
      tabCentro.setAttribute('aria-selected','true'); tabCamp.setAttribute('aria-selected','false');
      window.scrollTo({top:0, behavior:'smooth'});
    });
  }

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
        el.innerHTML = `${escapeHtml(v)} <span class="remove" data-idx="${idx}" title="Eliminar">×</span>`;
        chipsWrap.appendChild(el);
      });
    }

    function updateSuggestions() {
      const q = (input.value || '').trim().toLowerCase();
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

    if (!input) return;

    input.addEventListener('input', () => {
      const q = input.value.trim();
      if (latField) latField.value = '';
      if (lngField) lngField.value = '';
      if (!q) { if (results) { results.classList.add('hidden'); results.innerHTML = ''; } return; }
      clearTimeout(timer);
      timer = setTimeout(() => {
        // Nominatim query
        fetch(`https://nominatim.openstreetmap.org/search?format=json&addressdetails=1&limit=6&q=${encodeURIComponent(q)}`, {
          headers: { 'Accept': 'application/json' }
        }).then(r => r.json()).then(data => {
          if (!results) return;
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
              if (latField) latField.value = item.lat;
              if (lngField) lngField.value = item.lon;
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

  /* ------------------ IMAGE PREVIEW, MULTI-ADD, REMOVE & DRAG-REORDER ------------------ */
  function createFileManager(inputId, previewId, maxFiles = 6) {
    const input = document.getElementById(inputId);
    const preview = document.getElementById(previewId);
    let filesArr = []; // array of File objects to maintain order

    function render() {
      if (!preview) return;
      preview.innerHTML = '';
      filesArr.forEach((file, idx) => {
        const box = document.createElement('div');
        box.className = 'preview';
        box.draggable = true;
        box.dataset.idx = idx;
        // create image preview
        const img = document.createElement('img');
        img.alt = file.name;
        box.appendChild(img);

        const del = document.createElement('div');
        del.className = 'del';
        del.title = 'Eliminar';
        del.dataset.idx = idx;
        del.textContent = '×';
        box.appendChild(del);

        // cover label for "portada"
        if (idx === 0) {
          const cov = document.createElement('div');
          cov.className = 'cover';
          cov.textContent = 'Portada';
          box.appendChild(cov);
        }

        // drag handlers
        box.addEventListener('dragstart', (e) => {
          e.dataTransfer.setData('text/plain', String(idx));
          e.dataTransfer.effectAllowed = 'move';
        });
        box.addEventListener('dragover', (e) => {
          e.preventDefault();
          e.dataTransfer.dropEffect = 'move';
          box.style.opacity = '0.7';
        });
        box.addEventListener('dragleave', () => {
          box.style.opacity = '1';
        });
        box.addEventListener('drop', (e) => {
          e.preventDefault();
          const src = Number(e.dataTransfer.getData('text/plain'));
          const dst = Number(box.dataset.idx);
          if (!Number.isNaN(src) && !Number.isNaN(dst) && src !== dst) {
            const item = filesArr.splice(src, 1)[0];
            filesArr.splice(dst, 0, item);
            updateInputFiles();
            render();
          }
        });

        // delete handler
        del.addEventListener('click', () => {
          const i = Number(del.dataset.idx);
          filesArr.splice(i, 1);
          updateInputFiles();
          render();
        });

        // read file
        const reader = new FileReader();
        reader.onload = (ev) => {
          img.src = ev.target.result;
        };
        reader.readAsDataURL(file);

        preview.appendChild(box);
      });
    }

    function updateInputFiles() {
      if (!input) return;
      // build DataTransfer from filesArr
      const dt = new DataTransfer();
      filesArr.forEach(f => dt.items.add(f));
      input.files = dt.files;
    }

    if (!input) return {
      getFilesArray: () => [],
      setFilesArray: () => {},
      clear: () => {}
    };

    input.addEventListener('change', (e) => {
      const newFiles = Array.from(e.target.files || []);
      // append but prevent duplicates (by name+size)
      newFiles.forEach(f => {
        if (filesArr.length >= maxFiles) return;
        const exists = filesArr.some(x => x.name === f.name && x.size === f.size && x.type === f.type);
        if (!exists) filesArr.push(f);
      });
      updateInputFiles();
      render();
      // clear input so user can re-open picker and select same file names if needed
      input.value = '';
    });

    return {
      getFilesArray: () => filesArr,
      setFilesArray: (arr) => { filesArr = arr; updateInputFiles(); render(); },
      clear: () => { filesArr = []; updateInputFiles(); render(); }
    };
  }

  const fmCamp = createFileManager('imgsCamp','previewCamp', 6);
  const fmCentro = createFileManager('imgsCentro','previewCentro', 6);

  /* ------------------ HORARIOS: UI para agregar rangos (Camp y Centro) ------------------ */
  function setupHorarios(prefix) {
    const dayInput = document.getElementById('horarioDay' + prefix);
    const startInput = document.getElementById('horarioStart' + prefix);
    const endInput = document.getElementById('horarioEnd' + prefix);
    const addBtn = document.getElementById('addHorario' + prefix);
    const list = document.getElementById('horariosList' + prefix);
    const hidden = document.getElementById('horarioHidden' + prefix);
    if (!list || !hidden || !addBtn) return;

    function renderList() {
      list.innerHTML = '';
      const arr = (hidden.value || '').split('|').map(s=>s.trim()).filter(Boolean);
      arr.forEach((h, idx) => {
        const el = document.createElement('div');
        el.className = 'chip';
        el.innerHTML = `${escapeHtml(h)} <span class="remove" data-idx="${idx}" title="Eliminar">×</span>`;
        list.appendChild(el);
      });
    }

    addBtn.addEventListener('click', () => {
      const d = dayInput ? dayInput.value.trim() : '';
      const s = startInput ? startInput.value : '';
      const e = endInput ? endInput.value : '';
      if (!s || !e) {
        alert('Por favor seleccioná hora de inicio y hora de fin.');
        return;
      }
      const text = d ? `${d} ${s}-${e}` : `${s}-${e}`;
      const arr = (hidden.value || '').split('|').map(s=>s.trim()).filter(Boolean);
      // evitar duplicados
      if (arr.some(x => x.toLowerCase() === text.toLowerCase())) {
        if (dayInput) dayInput.value = '';
        if (startInput) startInput.value = '';
        if (endInput) endInput.value = '';
        return;
      }
      arr.push(text);
      hidden.value = arr.join(' | ');
      renderList();
      if (dayInput) dayInput.value = '';
      if (startInput) startInput.value = '';
      if (endInput) endInput.value = '';
    });

    list.addEventListener('click', (e) => {
      if (e.target.classList.contains('remove')) {
        const idx = Number(e.target.dataset.idx);
        const arr = (hidden.value || '').split('|').map(s=>s.trim()).filter(Boolean);
        arr.splice(idx, 1);
        hidden.value = arr.join(' | ');
        renderList();
      }
    });

    // initial render
    renderList();
  }

  setupHorarios('Camp');
  setupHorarios('Centro');

  /* ------------------ Ajustes UI menores y validaciones antes de submit ------------------ */
  // asegurar que al subir imagenes y al final el bottom nav no tape botones
  window.addEventListener('resize', () => {
    const main = document.querySelector('main.content');
    if (main) main.style.paddingBottom = (72 + 30) + 'px';
  });

  // phone handling & horario hidden sync & telefono hidden value
  const formCampEl = document.getElementById('formCampana');
  if (formCampEl) {
    formCampEl.addEventListener('submit', (e) => {
      // phone
      const local = document.getElementById('phoneLocalCamp');
      const hiddenTel = document.getElementById('telefonoHiddenCamp');
      if (local && hiddenTel) {
        const raw = (local.value || '').replace(/\D/g, '');
        hiddenTel.value = raw ? '+54' + raw : '';
      }
      // direccion: ensure lat/lng if chosen
      const adr = document.getElementById('addressCamp');
      const lat = document.getElementById('latCamp');
      if (adr && lat && adr.value.trim() && (!lat.value || lat.value === '')) {
        if (!confirm('No seleccionaste una dirección desde la lista. ¿Querés continuar igual (la ubicación puede quedar imprecisa)?')) {
          e.preventDefault();
          return;
        }
      }
      // meta cleanup
      const mt = document.getElementById('metaText');
      if (mt) mt.value = mt.value.replace(/[^\d]/g, '') || '0';
      // categories hidden set
      const hidden = document.getElementById('categorias_hidden_camp');
      if (hidden && !hidden.value) hidden.value = '';
    });
  }

  const formCentroEl = document.getElementById('formCentro');
  if (formCentroEl) {
    formCentroEl.addEventListener('submit', (e) => {
      const local = document.getElementById('phoneLocalCentro');
      const hiddenTel = document.getElementById('telefonoHiddenCentro');
      if (local && hiddenTel) {
        const raw = (local.value || '').replace(/\D/g, '');
        hiddenTel.value = raw ? '+54' + raw : '';
      }

      const adr = document.getElementById('addressCentro');
      const lat = document.getElementById('latCentro');
      if (adr && lat && adr.value.trim() && (!lat.value || lat.value === '')) {
        if (!confirm('No seleccionaste una dirección desde la lista. ¿Querés continuar igual (la ubicación puede quedar imprecisa)?')) {
          e.preventDefault();
          return;
        }
      }
      const hidden = document.getElementById('categorias_hidden_centro');
      if (hidden && !hidden.value) hidden.value = '';
    });
  }

  /* Helper: escape html for suggestions */
  function escapeHtml(text = '') {
    return String(text).replace(/[&<>"']/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[m]));
  }
});