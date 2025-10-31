// crear/crear.js
document.addEventListener('DOMContentLoaded', () => {
  // modo compacto por defecto: reduce tamaños/paddings para que la UI se vea "más alejada"
  document.body.classList.add('compact');
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
      // dar un poco más de tiempo y asegurar invalidate cuando se muestre la pestaña
      setTimeout(() => { if (window._mapCamp && window._mapCamp.invalidate) window._mapCamp.invalidate(); }, 150);
    });
    tabCentro.addEventListener('click', () => {
      tabCentro.classList.add('active'); tabCamp.classList.remove('active');
      if (formCent) formCent.classList.remove('hidden');
      if (formCamp) formCamp.classList.add('hidden');
      tabCentro.setAttribute('aria-selected','true'); tabCamp.setAttribute('aria-selected','false');
      window.scrollTo({top:0, behavior:'smooth'});
      // dar un poco más de tiempo y asegurar invalidate cuando se muestre la pestaña
      setTimeout(() => { if (window._mapCentro && window._mapCentro.invalidate) window._mapCentro.invalidate(); }, 150);
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
  function setupAddressAutocomplete(inputId, resultsId, latId, lngId, onSelect, copyToExactId) {
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
        // Usar proxy local para evitar problemas CORS / User-Agent
        fetch(`geo_proxy.php?q=${encodeURIComponent(q)}&limit=6`, {
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
              // copiar también al campo "dirección exacta" que se envía en el form
              if (copyToExactId) {
                const exact = document.getElementById(copyToExactId);
                if (exact) exact.value = display;
              }
              if (latField) latField.value = item.lat;
              if (lngField) lngField.value = item.lon;
              if (typeof onSelect === 'function') {
                const latNum = parseFloat(item.lat);
                const lngNum = parseFloat(item.lon);
                onSelect({ display, lat: latNum, lng: lngNum });
              }
              results.classList.add('hidden');
            });
            results.appendChild(div);
          });
          results.classList.remove('hidden');
        }).catch(err => {
          console.error('Nominatim / proxy error', err);
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

  // Map Picker factory
  function createMapPicker(mapId, latId, lngId, btnGeoId, addrInputId){
    const mapEl = document.getElementById(mapId);
    const latEl = document.getElementById(latId);
    const lngEl = document.getElementById(lngId);
    const btnGeo = document.getElementById(btnGeoId);
    const addrEl = document.getElementById(addrInputId);
    if (!mapEl) return null;

    // default center (Bahía Blanca aprox)
    const def = {lat:-38.72, lng:-62.26};
    const map = L.map(mapEl, { zoomControl: true }).setView([def.lat, def.lng], 13);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
      attribution: '&copy; OpenStreetMap contributors'
    }).addTo(map);

    let marker = null;
    function setMarker(lat, lng){
      const p = [lat, lng];
      if (!marker){
        marker = L.marker(p, { draggable:true }).addTo(map);
        marker.on('dragend', () => {
          const pos = marker.getLatLng();
          if (latEl) latEl.value = String(pos.lat);
          if (lngEl) lngEl.value = String(pos.lng);
        });
      } else {
        marker.setLatLng(p);
      }
      map.setView(p, Math.max(map.getZoom(), 14));
      if (latEl) latEl.value = String(lat);
      if (lngEl) lngEl.value = String(lng);
    }

    // if there are initial coords, place marker
    const initLat = parseFloat(latEl && latEl.value);
    const initLng = parseFloat(lngEl && lngEl.value);
    if (!Number.isNaN(initLat) && !Number.isNaN(initLng)) {
      setMarker(initLat, initLng);
    }

    if (btnGeo && navigator.geolocation){
      btnGeo.addEventListener('click', () => {
        navigator.geolocation.getCurrentPosition((pos) => {
          const { latitude, longitude } = pos.coords;
          setMarker(latitude, longitude);
        }, () => {
          alert('No se pudo obtener tu ubicación. Verificá permisos del navegador.');
        });
      });
    }

    function invalidate(){ try { map.invalidateSize(); } catch(e){} }

    return {
      map,
      setPosition: (lat, lng) => setMarker(lat, lng),
      invalidate,
    };
  }

  // initialize map pickers
  window._mapCamp = createMapPicker('mapCamp','latCamp','lngCamp','btnGeoCamp','addressCamp');
  window._mapCentro = createMapPicker('mapCentro','latCentro','lngCentro','btnGeoCentro','addressCentro');

  // Address autocomplete with map sync
  setupAddressAutocomplete('addressCamp','addrResultsCamp','latCamp','lngCamp', (sel) => {
    if (window._mapCamp && sel && typeof sel.lat === 'number' && typeof sel.lng === 'number') {
      window._mapCamp.setPosition(sel.lat, sel.lng);
    }
  }, 'dirExactaCamp'); // copiar a campo de envío

  setupAddressAutocomplete('addressCentro','addrResultsCentro','latCentro','lngCentro', (sel) => {
    if (window._mapCentro && sel && typeof sel.lat === 'number' && typeof sel.lng === 'number') {
      window._mapCentro.setPosition(sel.lat, sel.lng);
    }
  }, 'dirExactaCentro'); // copiar a campo de envío

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

  /* ------------------ HORARIOS: UI renovada con toggles de días + time pickers ------------------ */
  function setupHorarios(prefix) {
    const container = document.querySelector('#form' + (prefix === 'Camp' ? 'Campana' : 'Centro') + ' .horario-ui');
    const list = document.getElementById('horariosList' + prefix);
    const hidden = document.getElementById('horarioHidden' + prefix);
    if (!container || !list || !hidden) return;

    // limpiar UI previa
    // vaciamos pero reusaremos list y hidden (los reinsertamos después)
    container.innerHTML = '';

    // días de la semana
    const dias = [
      {key:'Lun', label:'L'},
      {key:'Mar', label:'M'},
      {key:'Mie', label:'M'},
      {key:'Jue', label:'J'},
      {key:'Vie', label:'V'},
      {key:'Sab', label:'S'},
      {key:'Dom', label:'D'},
    ];

    const daysRow = document.createElement('div');
    daysRow.className = 'horario-row';
    daysRow.style.gap = '6px';

    const toggles = [];
    dias.forEach(d => {
      const btn = document.createElement('button');
      btn.type = 'button';
      btn.textContent = d.label;
      btn.title = d.key;
      btn.style.padding = '8px 10px';
      btn.style.borderRadius = '8px';
      btn.style.border = '1px solid #e6eefc';
      btn.style.background = '#fff';
      btn.style.cursor = 'pointer';
      btn.style.fontWeight = '700';
      btn.style.minWidth = '36px';
      btn.addEventListener('click', () => {
        btn.classList.toggle('active');
        if (btn.classList.contains('active')) {
          btn.style.background = '#eef6ff';
          btn.style.color = '#2b6cb0';
        } else {
          btn.style.background = '#fff';
          btn.style.color = '#17222a';
        }
      });
      toggles.push({btn, key:d.key});
      daysRow.appendChild(btn);
    });

    const timeRow = document.createElement('div');
    timeRow.className = 'horario-row';
    timeRow.style.display = 'flex';
    timeRow.style.gap = '12px';
    timeRow.style.alignItems = 'flex-start';

    // anotaciones/labels pequeñas sobre cada campo
    const openLabel = document.createElement('div');
    openLabel.className = 'file-note';
    openLabel.textContent = 'Horario de apertura';

    const closeLabel = document.createElement('div');
    closeLabel.className = 'file-note';
    closeLabel.textContent = 'Horario de cierre';

    const startInput = document.createElement('input');
    startInput.type = 'time';
    startInput.ariaLabel = 'Hora inicio';
    const endInput = document.createElement('input');
    endInput.type = 'time';
    endInput.ariaLabel = 'Hora fin';

    const addBtn = document.createElement('button');
    addBtn.type = 'button';
    addBtn.className = 'btn-small';
    addBtn.textContent = 'Agregar rango';

    // columna central con inputs apilados (sin el botón)
    const inputsCol = document.createElement('div');
    inputsCol.style.display = 'flex';
    inputsCol.style.flexDirection = 'column';
    inputsCol.style.flex = '1';
    inputsCol.style.gap = '8px';
    inputsCol.appendChild(openLabel);
    inputsCol.appendChild(startInput);
    inputsCol.appendChild(closeLabel);
    inputsCol.appendChild(endInput);

    // timeRow ahora solo contiene los inputs (el botón se coloca después del info)
    timeRow.appendChild(inputsCol);

    const info = document.createElement('div');
    info.className = 'file-note';
    info.textContent = 'Seleccioná los días y el rango horario, luego presioná Agregar rango';

    // insertamos controles en este orden:
    // 1) días, 2) inputs apilados, 3) info, 4) botón agregar, 5) lista de chips, 6) hidden
    container.appendChild(daysRow);
    container.appendChild(timeRow);
    container.appendChild(info);
    container.appendChild(addBtn);      // <-- botón justo debajo del texto informativo
    container.appendChild(list);        // <-- chips aparecerán debajo del botón
    container.appendChild(hidden);

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
      const s = startInput.value;
      const e = endInput.value;
      if (!s || !e) { alert('Seleccioná hora de inicio y fin'); return; }
      const activeDays = toggles.filter(t => t.btn.classList.contains('active')).map(t => t.key);
      if (activeDays.length === 0) { alert('Seleccioná al menos un día'); return; }
      const text = `${activeDays.join(', ')} ${s}-${e}`;
      const arr = (hidden.value || '').split('|').map(s=>s.trim()).filter(Boolean);
      if (arr.some(x => x.toLowerCase() === text.toLowerCase())) return;
      arr.push(text);
      hidden.value = arr.join(' | ');
      renderList();
      // reset selección
      toggles.forEach(t => { t.btn.classList.remove('active'); t.btn.style.background='#fff'; t.btn.style.color='#17222a'; });
      startInput.value = '';
      endInput.value = '';
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

    renderList();
  }

  setupHorarios('Camp');
  setupHorarios('Centro');

  /* ------------------ STEPPER (3 pasos) para Campaña y Centro ------------------ */
  function setupStepper(formId, stepsConfig){
    const form = document.getElementById(formId);
    if (!form) return;

    // crear contenedor de pasos
    const stepper = document.createElement('div');
    stepper.className = 'stepper';
    stepper.style.marginTop = '8px';

    // crear pasos
    const steps = [document.createElement('div'), document.createElement('div'), document.createElement('div')];
    steps.forEach((s, i) => {
      s.className = 'step';
      if (i !== 0) s.style.display = 'none';
      stepper.appendChild(s);
    });

    // barra de estado
    const status = document.createElement('div');
    status.style.display = 'flex';
    status.style.justifyContent = 'space-between';
    status.style.alignItems = 'center';
    status.style.margin = '8px 0 12px';
    const label = document.createElement('div');
    label.style.fontWeight = '700';
    const prog = document.createElement('div');
    prog.style.flex = '1';
    prog.style.height = '8px';
    prog.style.background = '#eef6ff';
    prog.style.borderRadius = '999px';
    prog.style.marginLeft = '12px';
    const progBar = document.createElement('div');
    progBar.style.height = '100%';
    progBar.style.background = 'var(--blue)';
    progBar.style.width = '0%';
    progBar.style.borderRadius = '999px';
    prog.appendChild(progBar);
    status.append(label, prog);

    // navegación
    const nav = document.createElement('div');
    nav.style.display = 'flex';
    nav.style.gap = '8px';
    nav.style.marginTop = '12px';

    const btnBack = document.createElement('button');
    btnBack.type = 'button';
    btnBack.textContent = 'Atrás';
    btnBack.className = 'btn-small';
    btnBack.style.background = '#eef6ff';
    btnBack.style.color = '#2b6cb0';

    const btnNext = document.createElement('button');
    btnNext.type = 'button';
    btnNext.textContent = 'Siguiente';
    btnNext.className = 'btn-primary';

    const btnCancel = document.createElement('button');
    btnCancel.type = 'button';
    btnCancel.textContent = 'Cancelar';
    btnCancel.className = 'btn-small';

    nav.append(btnBack, btnCancel, btnNext);

    // insertar stepper al inicio del form y mover campos a pasos
    form.insertBefore(status, form.firstChild);
    form.insertBefore(stepper, status.nextSibling);

    function moveWithPrevLabel(el, step){
      if (!el) return;
      // si el selector apunta a un input interno (ej. input file), preferimos mover su contenedor visible
      // (si existe un ancestor con clase file-picker o cat-input o map-wrap, usar ese)
      // Añadimos phone-row / phone-input-group, horario-ui y contenedores de meta/fechas
      // así movemos todo el bloque de horario junto con sus chips.
      const preferContainers = ['file-picker','cat-input','map-wrap','phone-row','phone-input-group','horario-ui','meta-row','range-wrap','grid-2'];
      let container = el;
      for (const cls of preferContainers) {
        const anc = el.closest && el.closest('.' + cls);
        if (anc) { container = anc; break; }
      }
      // si el elemento a mover es el preview o addr-results, moverlo tal cual
      const label = container.previousElementSibling && container.previousElementSibling.tagName === 'LABEL' ? container.previousElementSibling : null;
      if (label) step.appendChild(label);
      step.appendChild(container);
    }

    const which = stepsConfig;
    // mover en el orden exacto indicado por configuration
    which.step1.forEach(sel => {
      const el = form.querySelector(sel);
      if (el) moveWithPrevLabel(el, steps[0]);
    });
    which.step2.forEach(sel => {
      const el = form.querySelector(sel);
      if (el) moveWithPrevLabel(el, steps[1]);
    });
    which.step3.forEach(sel => {
      const el = form.querySelector(sel);
      if (el) moveWithPrevLabel(el, steps[2]);
    });

    // mover submit si existe al final del paso 3
    const submitBtn = form.querySelector('button.btn-primary[type="submit"]');
    // asegurarse de mover solo el botón (no su parentElement) para no romper el submit nativo
    if (submitBtn) steps[2].appendChild(submitBtn);

    // --- NUEVO: mover nodos sobrantes (evita que queden campos fuera del stepper) ---
    // los nodos que queden como hijos directos del form (excepto status/stepper/nav) los colocamos en el paso 3
    const reserved = new Set([status, stepper]);
    // nav será agregado al final; si ya existe, incluirlo en reserved
    // Mover cualquier otro child al paso 3 garantiza que el único contenido visible esté en los steps.
    Array.from(form.children).forEach(ch => {
      if (reserved.has(ch)) return;
      // evitar mover el propio paso (están dentro stepper), y evitar mover inputs ocultos que ya fueron movidos
      // en la mayoría de casos, si el nodo no es el stepper/status lo queremos en el paso 3
      steps[2].appendChild(ch);
    });
    // --- FIN NUEVO ---

    // asegurar que el botón submit quede siempre AL FINAL del paso 3
    // garantizar que el botón esté al final del paso 3 (append nuevamente el botón si existe)
    if (submitBtn) steps[2].appendChild(submitBtn);

    // estado y validación mínima
    let idx = 0;
    function updateUI(){
      steps.forEach((s,i)=> s.style.display = (i===idx?'block':'none'));
      label.textContent = `Paso ${idx+1}/3`;
      progBar.style.width = `${((idx+1)/3)*100}%`;
      btnBack.style.display = idx===0? 'none' : 'inline-block';
      // cambiar texto botón en el último paso
      if (idx === 2) btnNext.style.display = 'none'; else btnNext.style.display = 'inline-block';
      // Después de mostrar el paso, invalidar cualquier mapa dentro del step (con pequeño delay)
      setTimeout(() => { invalidateMapsInStep(steps[idx]); }, 120);
    }

    // invalida mapas Leaflet presentes dentro de un step (busca .map-picker)
    function invalidateMapsInStep(stepEl){
      if (!stepEl) return;
      const maps = stepEl.querySelectorAll('.map-picker');
      maps.forEach(mEl => {
        if (!mEl || !mEl.id) return;
        // casos explícitos para las instancias creadas globalmente
        if (mEl.id === 'mapCamp' && window._mapCamp && typeof window._mapCamp.invalidate === 'function') {
          try { window._mapCamp.invalidate(); } catch(e) {}
        } else if (mEl.id === 'mapCentro' && window._mapCentro && typeof window._mapCentro.invalidate === 'function') {
          try { window._mapCentro.invalidate(); } catch(e) {}
        } else {
          // fallback: si la instancia de leaflet está en el objeto retornado (.map), intentar llamar directamente
          try {
            const inst = window['_' + mEl.id];
            if (inst && inst.invalidate) inst.invalidate();
            else if (inst && inst.map && typeof inst.map.invalidateSize === 'function') inst.map.invalidateSize();
          } catch(e){}
        }
      });
    }

    function validateStep(){
      // validaciones mínimas por paso según form
      if (idx === 0){
        // título y descripción y categorías
        const title = form.querySelector(which.required.title);
        const desc = form.querySelector(which.required.desc);
        const catsHidden = form.querySelector(which.required.cats);
        if (!title || !title.value.trim()) { alert('Completá el título'); return false; }
        if (!desc || !desc.value.trim()) { alert('Completá la descripción'); return false; }
        if (!catsHidden || !catsHidden.value.trim()) { alert('Agregá al menos una categoría'); return false; }
      }
      if (idx === 1){
        // dirección y lat/lng
        const dir = form.querySelector(which.required.dir);
        const lat = form.querySelector(which.required.lat);
        const lng = form.querySelector(which.required.lng);
        if (!dir || !dir.value.trim()) { alert('Completá la dirección'); return false; }
        if (!lat || !lat.value || !lng || !lng.value) { alert('Ubicá el pin en el mapa'); return false; }
      }
      if (idx === 2 && which.required.extra){
        // metas/fechas/horarios/telefono según corresponda
        const ok = which.required.extra(form);
        if (!ok) return false;
      }
      return true;
    }

    btnNext.addEventListener('click', () => { if (validateStep()){ idx = Math.min(2, idx+1); updateUI(); window.scrollTo({top:0, behavior:'smooth'}); } });
    btnBack.addEventListener('click', () => { idx = Math.max(0, idx-1); updateUI(); window.scrollTo({top:0, behavior:'smooth'}); });
    btnCancel.addEventListener('click', () => { if (confirm('¿Cancelar creación? Se perderán los cambios no guardados.')) { window.location.href = '../inicio/inicio.html'; } });

    form.appendChild(nav);
    updateUI();
  }

  // configurar stepper para Campaña (nuevo orden solicitado)
  setupStepper('formCampana', {
    step1: [
      // Fotos primero, luego título, descripción y categorías
      'div.file-picker', '#previewCamp',
      '#camp_title',
      'form#formCampana textarea[name="descripcion"]',
      'div.cat-input', '#categorias_hidden_camp'
    ],
    step2: [
      '#dirExactaCamp', '#addressCamp', '#addrResultsCamp',
      'div.map-wrap', '#latCamp', '#lngCamp', '#btnGeoCamp',
      'div.phone-row', '#telefonoHiddenCamp',
      '#waLinkCamp'
    ],
    step3: [
      // mover el bloque completo de meta (se incluye label + slider + campo numérico)
      '.meta-row', 
      // fechas (los inputs están dentro de .grid-2, preferContainers moverá el contenedor con su label)
      'input[name="fecha_inicio"]', 'input[name="fecha_fin"]',
      // donaciones (alias, cvu, link) en este orden
      '#alias_mp', '#cvu_mp', '#link_pago_mp',
      // horarios
      '#horariosListCamp', '#horarioHiddenCamp'
    ],
    required: {
      title: '#camp_title',
      desc: 'form#formCampana textarea[name="descripcion"]',
      cats: '#categorias_hidden_camp',
      dir: '#dirExactaCamp', lat: '#latCamp', lng: '#lngCamp',
      extra: (form) => {
        const meta = form.querySelector('#metaText');
        const fi = form.querySelector('input[name="fecha_inicio"]');
        const ff = form.querySelector('input[name="fecha_fin"]');
        if (!meta || !meta.value.replace(/\D/g,'').length){ alert('Indicá la meta'); return false; }
        if (!fi || !fi.value || !ff || !ff.value){ alert('Completá las fechas'); return false; }
        if (fi.value && ff.value && new Date(fi.value) > new Date(ff.value)) { alert('La fecha inicio no puede ser mayor que la fecha fin'); return false; }
        return true;
      }
    }
  });

  // configurar stepper para Centro (nuevo orden solicitado)
  setupStepper('formCentro', {
    step1: [
      'div.file-picker', '#previewCentro',
      '#centro_title',
      'form#formCentro textarea[name="descripcion"]',
      'div.cat-input', '#categorias_hidden_centro'
    ],
    step2: [
      '#dirExactaCentro', '#addressCentro', '#addrResultsCentro',
      'div.map-wrap', '#latCentro', '#lngCentro', '#btnGeoCentro',
      'div.phone-row', '#telefonoHiddenCentro',
      '#waLinkCentro'
    ],
    step3: [
      // meta completo
      '.meta-row',
      // fechas
      'input[name="fecha_inicio"]', 'input[name="fecha_fin"]',
      // donaciones (alias/cvu/link)
      'form#formCentro input[name="alias_mp"]', 'form#formCentro input[name="cvu_mp"]', 'form#formCentro input[name="link_pago_mp"]',
      // horarios
      '#horariosListCentro', '#horarioHiddenCentro'
    ],
    required: {
      title: '#centro_title',
      desc: 'form#formCentro textarea[name="descripcion"]',
      cats: '#categorias_hidden_centro',
      dir: '#dirExactaCentro', lat: '#latCentro', lng: '#lngCentro',
      extra: (form) => {
        const telH = form.querySelector('#telefonoHiddenCentro');
        const local = form.querySelector('#phoneLocalCentro');
        if (local && telH) {
          const raw = (local.value || '').replace(/\D/g, '');
          if (!raw) { alert('Indicá un teléfono de contacto'); return false; }
        }
        const hor = form.querySelector('#horarioHiddenCentro');
        if (!hor || !hor.value.trim()) { alert('Agregá al menos un rango horario'); return false; }
        return true;
      }
    }
  });

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
      // sincronizar archivos seleccionados en el file-manager al input real antes de enviar
      try { setFilesToInput('imgsCamp', fmCamp.getFilesArray()); } catch (err) { /* no-crítico */ }
      // phone
      const local = document.getElementById('phoneLocalCamp');
      const hiddenTel = document.getElementById('telefonoHiddenCamp');
      if (local && hiddenTel) {
        const raw = (local.value || '').replace(/\D/g, '');
        hiddenTel.value = raw ? '+54' + raw : '';
      }
      // small delay opcional para asegurar que input.files quede actualizado (suele no ser necesario)
      // pero si alguna validación falla, evitamos submit prematuro
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
      // whatsapp: si ingresó un número pelado, convertir a link
      const wa = document.getElementById('waLinkCamp');
      if (wa && wa.value) {
        const digits = wa.value.replace(/\D/g,'');
        if (/^\d{8,}$/.test(digits)) {
          wa.value = 'https://wa.me/' + (digits.startsWith('54')?digits:('54' + digits.replace(/^0+/g,'')));
        }
      }
    });
  }

  const formCentroEl = document.getElementById('formCentro');
  if (formCentroEl) {
    formCentroEl.addEventListener('submit', (e) => {
      // sincronizar archivos seleccionados en el file-manager al input real antes de enviar
      try { setFilesToInput('imgsCentro', fmCentro.getFilesArray()); } catch (err) { /* no-crítico */ }
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

      const wa = document.getElementById('waLinkCentro');
      if (wa && wa.value) {
        const digits = wa.value.replace(/\D/g,'');
        if (/^\d{8,}$/.test(digits)) {
          wa.value = 'https://wa.me/' + (digits.startsWith('54')?digits:('54' + digits.replace(/^0+/g,'')));
        }
      }
    });
  }

  // helper: asignar array de File[] a un input[type="file"]
  function setFilesToInput(inputId, filesArray = []) {
    const input = document.getElementById(inputId);
    if (!input) return;
    try {
      const dt = new DataTransfer();
      (filesArray || []).forEach(f => { if (f instanceof File) dt.items.add(f); });
      input.files = dt.files;
    } catch (e) {
      // algunos navegadores antiguos pueden no soportar DataTransfer()
      console.warn('No se pudo sincronizar archivos en input', inputId, e);
    }
  }

  // --- FIX: asegurar que el botón "Crear" realmente envíe el formulario ---
  // Campaña
  (function(){
    const btn = document.querySelector('#formCampana button[type="submit"]');
    if (btn) {
      btn.addEventListener('click', (ev) => {
        // prevenir cualquier comportamiento extraño y forzar sincronización + envío
        ev.preventDefault();
        try { setFilesToInput('imgsCamp', fmCamp.getFilesArray()); } catch(e){}
        const form = document.getElementById('formCampana');
        if (form) {
          // usar requestSubmit si está disponible para respetar validaciones nativas
          if (typeof form.requestSubmit === 'function') form.requestSubmit();
          else form.submit();
        }
      });
    }
  })();

  // Centro
  (function(){
    const btn = document.querySelector('#formCentro button[type="submit"]');
    if (btn) {
      btn.addEventListener('click', (ev) => {
        ev.preventDefault();
        try { setFilesToInput('imgsCentro', fmCentro.getFilesArray()); } catch(e){}
        const form = document.getElementById('formCentro');
        if (form) {
          if (typeof form.requestSubmit === 'function') form.requestSubmit();
          else form.submit();
        }
      });
    }
  })();

  /* Helper: escape html for suggestions */
  function escapeHtml(text = '') {
    return String(text).replace(/[&<>"']/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[m]));
  }
});