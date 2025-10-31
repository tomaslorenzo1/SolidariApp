(function(){
  const DATA_KEY = 'solidari_notifs_v1';
  const sample = [
    { id: 'n1', tipo:'campana', titulo: 'Campaña "Ropa para Invierno" aprobada', texto: 'Tu campaña fue aprobada y ya está visible para donantes en tu zona.', time: 'Hace 2 h', unread:true },
    { id: 'n2', tipo:'centro', titulo: 'Nuevo centro cercano: "San Vicente"', texto: 'Se abrió un nuevo centro de donación a 1.2 km de tu ubicación.', time: 'Ayer', unread:true },
    { id: 'n3', tipo:'vol', titulo: 'Recordatorio: Jornada de Voluntariado', texto: 'Recordatorio para la jornada del sábado a las 10:00 en Plaza San Martín.', time: '2 días', unread:false },
    { id: 'n4', tipo:'gratitud', titulo: 'Gracias por tu donación', texto: 'Hemos recibido tu donación y el equipo te agradece públicamente.', time: '1 semana', unread:false }
  ];

  const container = document.getElementById('notifContainer');
  const filterBtn = document.getElementById('filterUnread');
  const markAllBtn = document.getElementById('markAll');
  const clearAllBtn = document.getElementById('clearAll');

  function loadState(){
    try {
      const raw = localStorage.getItem(DATA_KEY);
      if (!raw) return sample;
      const state = JSON.parse(raw);
      // merge to keep same shape
      return sample.map(s => {
        const found = (state.find && state.find(x => x.id === s.id)) || {};
        return Object.assign({}, s, { unread: typeof found.unread === 'boolean' ? found.unread : s.unread });
      });
    } catch(e) {
      return sample;
    }
  }

  function saveState(list){
    try { localStorage.setItem(DATA_KEY, JSON.stringify(list)); } catch(e){}
  }

  let notifs = loadState();

  function render(list){
    container.innerHTML = '';
    if (!Array.isArray(list) || list.length === 0) {
      container.innerHTML = '<div style="padding:18px;background:#fff;border-radius:10px;max-width:1100px;margin:10px auto;color:#556">No hay notificaciones</div>';
      return;
    }

    list.forEach(n => {
      const card = document.createElement('div');
      card.className = 'notification-card' + (n.unread ? ' unread' : '');
      card.setAttribute('data-id', n.id);

      const avatar = document.createElement('div');
      avatar.className = 'notification-avatar';
      // icon según tipo (SVG simple)
      let svg = '';
      if (n.tipo === 'campana') svg = '<svg width="26" height="26" viewBox="0 0 24 24" fill="#3498db"><path d="M12 2L3 7v6c0 3.5-1 4 2 7h14c3-3 2-3.5 2-7V7l-9-5z"/></svg>';
      else if (n.tipo === 'centro') svg = '<svg width="26" height="26" viewBox="0 0 24 24" fill="#2b6cb0"><path d="M12 2L2 7v7c0 5 2 6 10 6s10-1 10-6V7l-10-5z"/></svg>';
      else svg = '<svg width="26" height="26" viewBox="0 0 24 24" fill="#6b7280"><path d="M12 2a7 7 0 100 14 7 7 0 000-14zM2 22v-2a8 8 0 0116 0v2H2z"/></svg>';
      avatar.innerHTML = svg;

      const body = document.createElement('div');
      body.className = 'notification-body';
      const title = document.createElement('div');
      title.className = 'notification-title';
      title.textContent = n.titulo;
      const text = document.createElement('div');
      text.className = 'notification-text';
      text.textContent = n.texto;
      const meta = document.createElement('div');
      meta.className = 'notification-meta';
      meta.innerHTML = `<span>${n.time}</span><span style="margin-left:6px">${n.unread ? '<span class="dot-unread" title="No leído"></span>' : ''}</span>`;

      body.appendChild(title);
      body.appendChild(text);
      body.appendChild(meta);

      const actions = document.createElement('div');
      actions.className = 'notification-actions';
      const btnToggle = document.createElement('button');
      btnToggle.className = 'btn-small';
      btnToggle.textContent = n.unread ? 'Marcar leído' : 'Marcar no leído';
      btnToggle.addEventListener('click', (e) => {
        e.stopPropagation();
        toggleRead(n.id);
      });

      const btnOpen = document.createElement('button');
      btnOpen.className = 'btn-small secondary';
      btnOpen.textContent = 'Ver';
      btnOpen.addEventListener('click', (e) => {
        e.stopPropagation();
        // ejemplo: redirigir según tipo (placeholder)
        if (n.tipo === 'campana') location.href = '../detalle/detalle.php?tipo=campana&id=1';
        else if (n.tipo === 'centro') location.href = '../detalle/detalle.php?tipo=centro&id=1';
        else alert('Abrir notificación: ' + n.titulo);
      });

      actions.appendChild(btnToggle);
      actions.appendChild(btnOpen);

      card.appendChild(avatar);
      card.appendChild(body);
      card.appendChild(actions);

      // click en todo el card abre y marca leído
      card.addEventListener('click', () => {
        if (n.unread) {
          setRead(n.id, false);
        }
        // abrir detalle demo
        // ... aquí podría abrir modal o redirigir ...
      });

      container.appendChild(card);
    });
  }

  function toggleRead(id){
    notifs = notifs.map(n => n.id === id ? Object.assign({}, n, { unread: !n.unread }) : n);
    saveState(notifs);
    render(filterActive ? notifs.filter(f => f.unread) : notifs);
  }

  function setRead(id, value){
    notifs = notifs.map(n => n.id === id ? Object.assign({}, n, { unread: value }) : n);
    saveState(notifs);
    render(filterActive ? notifs.filter(f => f.unread) : notifs);
  }

  function markAllRead(){
    notifs = notifs.map(n => Object.assign({}, n, { unread: false }));
    saveState(notifs);
    render(notifs);
  }

  function clearAll(){
    notifs = [];
    saveState(notifs);
    render(notifs);
  }

  let filterActive = false;
  filterBtn.addEventListener('click', () => {
    filterActive = !filterActive;
    filterBtn.classList.toggle('active', filterActive);
    filterBtn.setAttribute('aria-pressed', String(filterActive));
    render(filterActive ? notifs.filter(f => f.unread) : notifs);
  });

  markAllBtn.addEventListener('click', () => {
    markAllRead();
  });

  clearAllBtn.addEventListener('click', () => {
    if (!confirm('¿Eliminar todas las notificaciones de ejemplo?')) return;
    clearAll();
  });

  // render inicial
  render(notifs);
})();
