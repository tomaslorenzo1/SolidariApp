/* Reemplazo completo de panel.js: unifica handlers, evita duplicados y añade comprobaciones */
document.addEventListener('DOMContentLoaded', () => {
  // Elementos principales (pueden no existir en todas las pantallas)
  const modalConfirm = document.getElementById('modal-confirm');
  const modalConfirmBtn = document.getElementById('modal-confirm-btn');
  const modalCancel = document.getElementById('modal-cancel');

  const modalEdit = document.getElementById('modal-edit');
  const modalEditForm = document.getElementById('modal-edit-form');
  const modalEditFields = document.getElementById('edit-fields');
  const modalEditTitle = document.getElementById('modal-edit-title');
  const modalEditClose = document.getElementById('modal-edit-close');
  const modalEditSave = document.getElementById('modal-edit-save');
  const modalEditCancel = document.getElementById('modal-edit-cancel');

  const modalUser = document.getElementById('modal-user');
  const modalUserBody = document.getElementById('modal-user-body');
  const modalUserTitle = document.getElementById('modal-user-title');
  const modalUserClose = document.getElementById('modal-user-close');

  // Subtabs (Aprobaciones)
  const subTabs = document.querySelectorAll('.subtab');
  if (subTabs && subTabs.length) {
    subTabs.forEach(st => st.addEventListener('click', () => {
      subTabs.forEach(x => x.classList.toggle('active', x === st));
      const sub = st.dataset.sub;
      const c1 = document.getElementById('sub-campanas');
      const c2 = document.getElementById('sub-centros');
      if (c1) c1.style.display = sub === 'campanas' ? 'block' : 'none';
      if (c2) c2.style.display = sub === 'centros' ? 'block' : 'none';
    }));
  }

  // UTIL: escape
  function escapeHtml(text=''){ return String(text).replace(/[&<>"']/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[m])); }

  // UTIL: obtener item desde window.PANEL_DATA (si existe)
  function findItem(type, id){
    const data = window.PANEL_DATA || {};
    const idn = parseInt(id,10);
    const listMap = {
      campana: [].concat(data.pendingCampanas || [], data.allCampanas || [], data.myCampanas || []),
      centro:  [].concat(data.pendingCentros || [], data.allCentros || [], data.myCentros || [])
    };
    return (listMap[type] || []).find(x => {
      const key = type === 'campana' ? (x.id_campaña ?? x.id) : (x.id_centro ?? x.id);
      return parseInt(key || 0,10) === idn;
    });
  }

  // Abrir modal edición (reutilizable para "ver" también)
  function openEditModal(type, id){
    if (!modalEdit || !modalEditFields || !modalEditTitle) { alert('Modal de edición no disponible'); return; }
    const item = findItem(type, id);
    if (!item) { alert('Elemento no encontrado'); return; }
    modalEditFields.innerHTML = '';
    modalEditTitle.textContent = (type === 'campana' ? 'Editar campaña' : 'Editar centro') + ' — ID ' + id;
    const inType = document.getElementById('edit-type');
    const inId = document.getElementById('edit-id');
    if (inType) inType.value = type;
    if (inId) inId.value = id;

    if (type === 'campana') {
      modalEditFields.innerHTML = `
        <label>Título</label><input name="titulo" id="edit-titulo" type="text" value="${escapeHtml(item.titulo ?? item.nombre ?? '')}">
        <label>Descripción</label><textarea name="descripcion" id="edit-descripcion">${escapeHtml(item.descripcion ?? '')}</textarea>
        <label>Dirección</label><input name="direccion" id="edit-direccion" type="text" value="${escapeHtml(item.direccion ?? '')}">
        <div style="display:flex;gap:8px"><div style="flex:1"><label>Fecha inicio</label><input type="date" name="fecha_inicio" id="edit-fi" value="${escapeHtml(item.fecha_inicio ?? '')}"></div><div style="flex:1"><label>Fecha fin</label><input type="date" name="fecha_fin" id="edit-ff" value="${escapeHtml(item.fecha_fin ?? '')}"></div></div>
        <label>Meta</label><input name="meta" id="edit-meta" type="text" value="${escapeHtml(item.meta ?? '')}">
        <label>Categorías</label><input name="categorias" id="edit-cats" type="text" value="${escapeHtml(item.categorias ?? '')}">
      `;
    } else {
      modalEditFields.innerHTML = `
        <label>Nombre</label><input name="nombre" id="edit-nombre" type="text" value="${escapeHtml(item.nombre ?? item.titulo ?? '')}">
        <label>Descripción</label><textarea name="descripcion" id="edit-descripcion">${escapeHtml(item.descripcion ?? '')}</textarea>
        <label>Dirección</label><input name="direccion" id="edit-direccion" type="text" value="${escapeHtml(item.direccion ?? '')}">
        <label>Categorías</label><input name="categorias" id="edit-cats" type="text" value="${escapeHtml(item.categorias ?? '')}">
      `;
    }

    modalEdit.classList.remove('hidden'); modalEdit.setAttribute('aria-hidden','false');
    setTimeout(() => { const f = modalEdit.querySelector('input,textarea,select'); if (f) f.focus(); }, 80);
  }

  // Cerrar modal editar
  if (modalEditClose) modalEditClose.addEventListener('click', () => { modalEdit.classList.add('hidden'); modalEdit.setAttribute('aria-hidden','true'); });
  if (modalEditCancel) modalEditCancel.addEventListener('click', () => { modalEdit.classList.add('hidden'); modalEdit.setAttribute('aria-hidden','true'); });

  // Envío edición (AJAX)
  if (modalEditForm) {
    modalEditForm.addEventListener('submit', (e) => {
      e.preventDefault();
      const fd = new FormData(modalEditForm);
      fd.append('action','save');
      fd.append('from','panel_js');
      if (modalEditSave) { modalEditSave.disabled = true; modalEditSave.textContent = 'Guardando...'; }
      fetch('./panel.php', { method:'POST', body: fd })
        .then(r => r.json())
        .then(json => {
          if (json && json.ok) {
            const type = fd.get('type'); const id = fd.get('id');
            const row = document.querySelector(`tr[data-row-type="${type}"][data-row-id="${id}"]`);
            if (row) {
              const titleCell = row.querySelector('.td-title');
              if (titleCell) titleCell.textContent = (fd.get('titulo') || fd.get('nombre') || titleCell.textContent);
            }
            alert(json.msg || 'Guardado');
            modalEdit.classList.add('hidden'); modalEdit.setAttribute('aria-hidden','true');
          } else {
            alert((json && json.msg) ? json.msg : 'Error al guardar');
          }
        }).catch(err => {
          console.error(err); alert('Error de comunicación.');
        }).finally(() => {
          if (modalEditSave) { modalEditSave.disabled = false; modalEditSave.textContent = 'Guardar'; }
        });
    });
  }

  // Confirm modal: botón confirmar
  if (modalConfirmBtn) {
    modalConfirmBtn.addEventListener('click', () => {
      const action = modalConfirm.dataset.action;
      const type = modalConfirm.dataset.type;
      const id = modalConfirm.dataset.id;
      if (!action || !type || !id) { if (modalConfirm) modalConfirm.classList.add('hidden'); return; }
      const form = new FormData();
      form.append('action', action);
      form.append('type', type);
      form.append('id', id);
      form.append('from', 'panel_js');
      modalConfirmBtn.disabled = true;
      modalConfirmBtn.textContent = 'Procesando...';
      fetch('./panel.php', { method: 'POST', body: form })
        .then(r => r.json())
        .then(json => {
          if (json && json.ok) {
            const row = document.querySelector(`tr[data-row-type="${type}"][data-row-id="${id}"]`);
            if (row) row.remove();
            // actualizar DATA in-memory si existe
            try {
              const pd = window.PANEL_DATA || {};
              ['pendingCampanas','allCampanas','myCampanas'].forEach(k => { if (pd[k]) pd[k] = pd[k].filter(x => parseInt((x.id_campaña ?? x.id),10) !== parseInt(id,10)); });
              ['pendingCentros','allCentros','myCentros'].forEach(k => { if (pd[k]) pd[k] = pd[k].filter(x => parseInt((x.id_centro ?? x.id),10) !== parseInt(id,10)); });
              window.PANEL_DATA = pd;
            } catch(e){}
            alert(json.msg || 'Acción realizada');
          } else {
            alert((json && json.msg) ? json.msg : 'Error en la acción');
          }
        }).catch(err => {
          console.error(err); alert('Error de comunicación con el servidor.');
        }).finally(() => {
          modalConfirmBtn.disabled = false;
          modalConfirmBtn.textContent = 'Confirmar';
          if (modalConfirm) { modalConfirm.classList.add('hidden'); modalConfirm.setAttribute('aria-hidden','true'); }
        });
    });
  }
  if (modalCancel) modalCancel.addEventListener('click', () => { if (modalConfirm) { modalConfirm.classList.add('hidden'); modalConfirm.setAttribute('aria-hidden','true'); } });

  // Delegación: clics en tabla (acciones, editar, ver)
  document.addEventListener('click', (e) => {
    // acciones admin (approve/reject/delete)
    const act = e.target.closest && e.target.closest('.action-admin');
    if (act) {
      e.preventDefault();
      const action = act.dataset.action;
      const type = act.dataset.type;
      const id = act.dataset.id;
      if (!action || !type || !id) return alert('Acción inválida');
      if (modalConfirm) {
        const modalMsg = document.getElementById('modal-msg');
        const modalSub = document.getElementById('modal-sub');
        if (modalMsg) modalMsg.textContent = (action === 'approve' ? 'Aprobar elemento' : action === 'reject' ? 'Rechazar elemento' : 'Eliminar elemento');
        if (modalSub) modalSub.textContent = `Tipo: ${type} — ID: ${id}`;
        modalConfirm.dataset.action = action;
        modalConfirm.dataset.type = type;
        modalConfirm.dataset.id = id;
        modalConfirm.classList.remove('hidden');
        modalConfirm.setAttribute('aria-hidden','false');
      } else {
        // fallback simple
        if (!confirm('Confirmar acción: ' + action)) return;
        const form = new FormData();
        form.append('action', action); form.append('type', type); form.append('id', id); form.append('from','panel_js');
        fetch('./panel.php', { method:'POST', body: form })
          .then(r=>r.json()).then(json => {
            if (json && json.ok) {
              const row = document.querySelector(`tr[data-row-type="${type}"][data-row-id="${id}"]`);
              if (row) row.remove();
              alert(json.msg||'Ok');
            } else alert((json && json.msg) ? json.msg : 'Error');
          }).catch(()=>alert('Error comunicación'));
      }
      return;
    }

    // Editar
    const eb = e.target.closest && e.target.closest('.edit-btn');
    if (eb) {
      const type = eb.dataset.type;
      const id = eb.dataset.id;
      if (type && id) openEditModal(type, id);
      return;
    }

    // Ver (usa el mismo modal-edit en modo lectura)
    const vb = e.target.closest && e.target.closest('.view-btn');
    if (vb) {
      const type = vb.dataset.type;
      const id = vb.dataset.id;
      if (type && id) openEditModal(type, id);
      return;
    }
  });

  // Cambio de rol: botón "Guardar rol" (delegado)
  document.addEventListener('click', (e) => {
    const rb = e.target.closest && e.target.closest('.role-save');
    if (!rb) return;
    const userId = rb.dataset.user;
    const sel = document.querySelector(`select.role-select[data-user="${userId}"]`);
    if (!sel) { alert('Selector no encontrado'); return; }
    const newRole = sel.value;
    if (!confirm(`Cambiar rol del usuario #${userId} a "${newRole}"?`)) return;
    const fd = new FormData();
    fd.append('action','change_role'); fd.append('user_id', String(userId)); fd.append('new_role', newRole); fd.append('from','panel_js');
    rb.disabled = true; rb.textContent = 'Guardando...';
    fetch('./panel.php', { method: 'POST', body: fd })
      .then(r => r.json())
      .then(json => {
        if (json && json.ok) {
          alert(json.msg || 'Rol actualizado');
          try {
            const pd = window.PANEL_DATA || {};
            if (pd.allUsers && Array.isArray(pd.allUsers)) {
              pd.allUsers = pd.allUsers.map(u => { if (String(u.id_usuario) === String(userId)) u.rol = newRole; return u; });
              window.PANEL_DATA = pd;
            }
          } catch(e){ console.warn(e); }
        } else {
          alert((json && json.msg) ? json.msg : 'Error al actualizar rol');
        }
      }).catch(err => { console.error(err); alert('Error de comunicación'); })
      .finally(() => { rb.disabled = false; rb.textContent = 'Guardar rol'; });
  });

  // Modal usuario (detalle)
  if (modalUserClose) modalUserClose.addEventListener('click', () => { if (modalUser) { modalUser.classList.add('hidden'); modalUser.setAttribute('aria-hidden','true'); } });

  document.addEventListener('click', (e) => {
    const v = e.target.closest && e.target.closest('.view-user');
    if (!v) return;
    const uid = v.dataset.user;
    const user = (window.PANEL_DATA && Array.isArray(window.PANEL_DATA.allUsers)) ? window.PANEL_DATA.allUsers.find(x => String(x.id_usuario) === String(uid)) : null;
    if (!user) { alert('Usuario no encontrado'); return; }
    if (!modalUser || !modalUserBody || !modalUserTitle) { alert('Modal usuario no disponible'); return; }
    modalUserTitle.textContent = `Usuario #${user.id_usuario}`;
    modalUserBody.innerHTML = `
      <div style="font-weight:800">${escapeHtml(user.nombre)}</div>
      <div class="muted-small">${escapeHtml(user.email)}</div>
      <div style="margin-top:8px"><strong>Rol:</strong> ${escapeHtml(user.rol)}</div>
      <div style="margin-top:12px" class="muted-small">Información completa (puedes agregar más campos según BD)</div>
    `;
    modalUser.classList.remove('hidden'); modalUser.setAttribute('aria-hidden','false');
  });

  // Métricas: render mini y detalladas
  function renderMiniDoughnuts(){
    if (typeof Chart === 'undefined') return;
    try {
      const pd = window.PANEL_DATA || {};
      const g = (pd.stats && pd.stats.global_metrics) ? pd.stats.global_metrics : {visitas:0, donaciones_transferencia:0, donaciones_presencial:0};
      const cfg = (id, value, color, bg) => {
        const el = document.getElementById(id);
        if (!el) return;
        if (el._chartInstance) try{ el._chartInstance.destroy(); }catch(e){}
        // Forzar tamaño visible (css controlará el tamaño real)
        el.style.width = '120px';
        el.style.height = '100px';
        el._chartInstance = new Chart(el, {
          type:'doughnut',
          data:{ labels:['a','b'], datasets:[{data:[value, Math.max(1,value)], backgroundColor:[color,bg]}] },
          options:{ plugins:{legend:{display:false}}, cutout: '70%', maintainAspectRatio: false, responsive: true }
        });
      };
      cfg('doughnut-visitas', g.visitas||0, '#2b6cb0', '#e9f2fb');
      cfg('doughnut-transf', g.donaciones_transferencia||0, '#3b82f6', '#eaf5ff');
      cfg('doughnut-pres', g.donaciones_presencial||0, '#06b6d4', '#e8fbfd');
    } catch(e){ console.warn('mini charts', e); }
  }

  function renderDetailedCharts(){
    if (typeof Chart === 'undefined') return;
    try {
      const visits = [10,20,35,30,45,50,60,55,70,80];
      const labels = visits.map((v,i)=> 'M' + (i+1));
      const ctxL = document.getElementById('chart-line-visits');
      const ctxB = document.getElementById('chart-bar-donations');
      if (ctxL) { if (ctxL._chartInstance) try{ ctxL._chartInstance.destroy(); }catch(e){}; ctxL._chartInstance = new Chart(ctxL, { type:'line', data:{ labels, datasets:[{ label:'Visitas', data:visits, borderColor:'#2b6cb0', backgroundColor:'rgba(43,108,176,0.08)', fill:true }] }, options:{responsive:true, maintainAspectRatio:false} }); }
      if (ctxB) { if (ctxB._chartInstance) try{ ctxB._chartInstance.destroy(); }catch(e){}; const pd = window.PANEL_DATA||{}; ctxB._chartInstance = new Chart(ctxB, { type:'bar', data:{ labels:['Transfer','Presencial'], datasets:[{ label:'Donaciones', data:[pd.stats?.global_metrics?.donaciones_transferencia||0, pd.stats?.global_metrics?.donaciones_presencial||0], backgroundColor:['#3b82f6','#06b6d4'] }] }, options:{responsive:true, maintainAspectRatio:false, plugins:{legend:{display:false}} } }); }
    } catch(e){ console.warn('detailed charts', e); }
  }

  // --- Notificaciones: cargar/guardar en localStorage (ficticio)
  function loadNotifPrefs(){
    try {
      const prefs = JSON.parse(localStorage.getItem('panel_notif_prefs') || '{}');
      document.getElementById('notif-email').checked = !!prefs.email;
      document.getElementById('notif-web').checked = !!prefs.web;
      document.getElementById('notif-sms').checked = !!prefs.sms;
    } catch(e){}
  }
  function saveNotifPrefs(){
    try {
      const prefs = { email: !!document.getElementById('notif-email').checked, web: !!document.getElementById('notif-web').checked, sms: !!document.getElementById('notif-sms').checked };
      localStorage.setItem('panel_notif_prefs', JSON.stringify(prefs));
      alert('Preferencias guardadas (simulado).');
    } catch(e){ alert('No se pudo guardar preferencias.'); }
  }
  const btnSaveNotif = document.getElementById('save-notif');
  if (btnSaveNotif) { btnSaveNotif.addEventListener('click', saveNotifPrefs); loadNotifPrefs(); }

  // --- Reporte de problema (AJAX) ---
  const sendReportBtn = document.getElementById('send-report');
  if (sendReportBtn) {
    sendReportBtn.addEventListener('click', () => {
      const subject = document.getElementById('report-subject').value || '';
      const message = document.getElementById('report-message').value || '';
      if (!message.trim()) { alert('Escribe una descripción del problema.'); return; }
      sendReportBtn.disabled = true; sendReportBtn.textContent = 'Enviando...';
      const fd = new FormData();
      fd.append('action','report_issue');
      fd.append('subject', subject);
      fd.append('message', message);
      fd.append('from','panel_js');
      fetch('./panel.php', { method:'POST', body: fd })
        .then(r => r.json())
        .then(json => {
          if (json && json.ok) {
            alert(json.msg || 'Reporte enviado.');
            document.getElementById('report-subject').value = '';
            document.getElementById('report-message').value = '';
          } else {
            alert((json && json.msg) ? json.msg : 'Error al enviar reporte.');
          }
        }).catch(err => {
          console.error(err); alert('Error de comunicación.');
        }).finally(() => {
          sendReportBtn.disabled = false; sendReportBtn.textContent = 'Enviar reporte';
        });
    });
  }

  // Botón metrics toggler
  const btnMetrics = document.getElementById('btn-metrics-open');
  const metricsDetailed = document.getElementById('metrics-detailed');
  if (btnMetrics && metricsDetailed) {
    btnMetrics.addEventListener('click', () => {
      const isHidden = metricsDetailed.style.display === 'none' || metricsDetailed.style.display === '';
      metricsDetailed.style.display = isHidden ? 'block' : 'none';
      if (isHidden) renderDetailedCharts();
    });
  }

  // Navegación local (mosaico -> secciones) sin recarga
  const sectionMap = {
    overview: 'sec-overview',
    aprobaciones: 'sec-aprobaciones',
    historial: 'sec-historial',
    usuarios: 'sec-usuarios',
    ajustes: 'sec-ajustes',
    buttons: 'sec-buttons'
  };

  function showOnlyButtons() {
    const buttons = document.getElementById('sec-buttons');
    if (buttons) buttons.style.display = 'flex';
    Object.values(sectionMap).forEach(id => {
      if (id === 'sec-buttons') return;
      const el = document.getElementById(id);
      if (el) el.style.display = 'none';
    });
    const firstBtn = document.querySelector('#sec-buttons .btn-small');
    if (firstBtn) firstBtn.focus();
  }

  function showSection(view, pushState = true) {
    const targetId = sectionMap[view] || sectionMap['buttons'];
    const buttons = document.getElementById('sec-buttons');
    if (buttons) buttons.style.display = 'none';
    Object.values(sectionMap).forEach(id => {
      const el = document.getElementById(id);
      if (el) el.style.display = (id === targetId) ? 'block' : 'none';
    });
    if (pushState) {
      try { history.pushState({view: view}, '', '?view=' + view); } catch(e){}
    }
    const main = document.querySelector('.panel-main');
    if (main) main.scrollIntoView({behavior:'smooth'});
    if (view === 'overview') { renderMiniDoughnuts(); /* y renderDetailedCharts si está abierto */ }
  }

  // Hacer tarjetas del mosaico clicables (sin interceptar botones internos)
  document.querySelectorAll('#sec-buttons .panel-card').forEach(card => {
    card.setAttribute('role','button'); card.tabIndex = 0;
    card.addEventListener('click', (ev) => {
      if (ev.target.closest('button') || ev.target.closest('a') || ev.target.closest('input') || ev.target.closest('select')) return;
      const form = card.querySelector('form');
      if (!form) return;
      const fd = new FormData(form);
      const view = fd.get('view') || form.querySelector('input[name="view"]')?.value;
      if (view) showSection(view, true);
    });
    card.addEventListener('keydown', (e) => {
      if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); card.click(); }
    });
  });

  // Interceptar submit de forms en mosaico (fallback)
  document.querySelectorAll('#sec-buttons form').forEach(f => {
    f.addEventListener('submit', (ev) => {
      ev.preventDefault();
      const fd = new FormData(f);
      const view = fd.get('view') || f.querySelector('input[name="view"]')?.value;
      if (view) showSection(view, true);
      else f.submit();
    });
  });

  // Botón volver (delegado)
  document.addEventListener('click', (e) => {
    const back = e.target.closest && e.target.closest('.btn-back');
    if (!back) return;
    e.preventDefault();
    showOnlyButtons();
    try { history.pushState({view:'buttons'}, '', '?view=buttons'); } catch(e){}
  });

  // Manejar popstate (back/forward)
  window.addEventListener('popstate', (ev) => {
    const v = (ev.state && ev.state.view) || (new URLSearchParams(location.search).get('view')) || 'buttons';
    if (v === 'buttons') showOnlyButtons(); else showSection(v, false);
  });

  // Inicial: mostrar la vista que PHP definió sin duplicar history
  try {
    const init = (window.INIT_VIEW || '').toString() || (new URLSearchParams(location.search).get('view') || 'buttons');
    if (init && init !== 'buttons') showSection(init, false);
    else showOnlyButtons();
  } catch(e) { showOnlyButtons(); }

  // Render inicial de mini doughnuts
  renderMiniDoughnuts();
});
