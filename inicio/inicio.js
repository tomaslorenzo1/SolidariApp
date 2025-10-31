// index/index.js (modificado para manejar campañas + centros)
const API_URL = './api_lugares.php';
let map, markers = [], places = [];
let userLocation = null; // ubicación del usuario
let resultsListEl = null;
let debounceTimer = null;

function init() {
  // mapa centrado por defecto (Bahía Blanca aprox)
  map = L.map('map').setView([-38.72, -62.26], 13);
  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '&copy; OpenStreetMap contributors'
  }).addTo(map);

  // intentar geolocalizar usuario y centrar
  if (navigator.geolocation) {
    navigator.geolocation.getCurrentPosition(pos => {
      const lat = pos.coords.latitude;
      const lng = pos.coords.longitude;
      userLocation = { lat, lng };
      map.setView([lat, lng], 13);
      // marker opcional de usuario
      L.circleMarker([lat, lng], { radius: 6, color: '#3498db' }).addTo(map);
      // recargar lugares para que las distancias se muestren desde el inicio
      try { loadPlaces({}); } catch(e) {}
    }, () => {
      // si el usuario no permite, dejamos centro por defecto
    });
  }

  // elementos UI
  const searchBtn = document.getElementById('searchBtn');
  const searchInput = document.getElementById('searchInput');
  resultsListEl = document.getElementById('resultsList');

  // eventos
  searchBtn.addEventListener('click', () => onSearch(true));
  searchInput.addEventListener('keyup', (e) => {
    // si presionó Enter: búsqueda inmediata
    if (e.key === 'Enter') {
      onSearch(true);
      return;
    }
    // para teclas de navegación no hacemos nada
    if (['ArrowUp','ArrowDown','ArrowLeft','ArrowRight','Escape','Tab'].includes(e.key)) return;

    // búsqueda en vivo con debounce
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(() => {
      onSearch(false); // no forzamos centrar en markers (pero sí actualiza markers y lista)
    }, 300);
  });

  // chips (categorías)
  const defaultCats = ['Ropa','Alimentos','Muebles','Voluntariado','Utiles'];
  const chips = document.getElementById('chipsContainer');
  chips.innerHTML = '';
  defaultCats.forEach(cat => {
    const btn = document.createElement('button');
    btn.className = 'chip';
    btn.type = 'button';
    btn.textContent = cat;
    btn.onclick = () => {
      const active = btn.classList.toggle('active');
      document.querySelectorAll('.chip').forEach(c => { if (c !== btn) c.classList.remove('active'); });
      if (active) {
        loadPlaces({ categoria: cat, q: document.getElementById('searchInput').value.trim() });
      } else {
        loadPlaces({ q: document.getElementById('searchInput').value.trim() });
      }
    };
    chips.appendChild(btn);
  });

  // carga inicial (muestra todos)
  loadPlaces({});
}

// carga campañas y centros desde API con filtros opcionales
function loadPlaces(opts = {}) {
  const params = new URLSearchParams();
  if (opts.q) params.set('q', opts.q);
  if (opts.categoria) params.set('categoria', opts.categoria);

  fetch(API_URL + (params.toString() ? ('?' + params.toString()) : ''))
    .then(r => {
      if (!r.ok) throw new Error('Error en la respuesta del servidor');
      return r.json();
    })
    .then(data => {
      places = Array.isArray(data) ? data : [];
      clearMarkers();
      addMarkers(places);

      // si el usuario indicó buscar (q o categoria) o llamamos en vivo, mostramos resultados
      if ((opts.q && opts.q !== '') || (opts.categoria && opts.categoria !== '')) {
        fillResults(places);
      } else {
        hideResults();
      }

      // Si estamos en una búsqueda "forzada" (por ejemplo con Enter) ajustamos bounds
      if (opts.forceFit && markers.length > 0) {
        const group = L.featureGroup(markers);
        map.fitBounds(group.getBounds().pad(0.2));
      }
    })
    .catch(err => {
      console.error('Error al cargar lugares:', err);
      hideResults();
    });
}

function clearMarkers() {
  markers.forEach(m => {
    try { map.removeLayer(m); } catch(e) {}
  });
  markers = [];
}

/* ------------------ POPUP MEJORADO (addMarkers) ------------------ */
function addMarkers(list) {
  list.forEach(item => {
    // item expected fields: id, tipo ('campana'|'centro'), titulo, descripcion, lat, lng, categorias, horario, imagenes (array)
    const lat = parseFloat(item.lat);
    const lng = parseFloat(item.lng);
    if (isNaN(lat) || isNaN(lng)) return; // ignorar si no tiene coords

    const marker = L.marker([lat, lng]).addTo(map);
    // guardamos id y tipo para luego encontrar marker fácilmente
    marker._campId = item.id;
    marker._type = item.tipo || 'campana';

    // construir HTML del popup (imagen, título, descripción, chips, horario, distancia, botón)
    const description = truncate(item.descripcion || '', 140);
    const categoriesHtml = buildCategoryChips(item.categorias || '');
    const horarioHtml = item.horario ? `<div style="color:#777;font-size:13px;margin-top:6px">${escapeHtml(item.horario)}</div>` : '';
    let distanceKm = null;
    if (userLocation && !isNaN(parseFloat(item.lat)) && !isNaN(parseFloat(item.lng))) {
      distanceKm = calcDistance(userLocation.lat, userLocation.lng, parseFloat(item.lat), parseFloat(item.lng));
    }
    let locationHtml = '';
    if (item.direccion && String(item.direccion).trim() !== '') {
      const distText = (distanceKm !== null) ? ` — A ${distanceKm.toFixed(1)} km` : '';
      locationHtml = `<div style="font-size:13px;color:#555;margin-top:6px">Dirección: ${escapeHtml(String(item.direccion))}${distText}</div>`;
    } else {
      if (distanceKm !== null) {
        locationHtml = `<div style="font-size:13px;color:#555;margin-top:6px">A ${distanceKm.toFixed(1)} km</div>`;
      } else {
        locationHtml = `<div style="font-size:13px;color:#555;margin-top:6px">Ubicación no disponible</div>`;
      }
    }

    // determinar imagen portada: si item.imagenes es array y tiene elementos, usamos el primero; si es string tratamos de parsear JSON
    let portada = '';
    try {
      if (Array.isArray(item.imagenes) && item.imagenes.length > 0) {
        portada = item.imagenes[0];
      } else if (typeof item.imagenes === 'string' && item.imagenes.trim() !== '') {
        // puede venir como JSON string
        const parsed = JSON.parse(item.imagenes);
        if (Array.isArray(parsed) && parsed.length > 0) portada = parsed[0];
      }
    } catch(e) {
      portada = '';
    }
    // fallback si no hay imagen
    const fallbackImg = 'img/logo_header.png';
    const imgPath = portada ? (portada.startsWith('http') ? portada : ('../' + portada)) : fallbackImg;

    // tipo label
    const tipoLabel = item.tipo === 'centro' ? 'Centro' : 'Campaña';

    const popupHtml = `
      <div style="min-width:260px;font-family:inherit;display:flex;gap:10px">
        <div style="flex:0 0 110px;">
          <img src="${escapeHtml(imgPath)}" alt="${escapeHtml(item.titulo)}" style="width:110px;height:80px;object-fit:cover;border-radius:6px" onerror="this.src='${fallbackImg}';">
        </div>
        <div style="flex:1 1 auto;">
          <div style="font-weight:700;font-size:15px;color:#16222a">${escapeHtml(item.titulo)}</div>
          <div style="font-size:13px;color:#333;margin-top:6px;line-height:1.2">${escapeHtml(description)}</div>
          <div style="margin-top:8px">${categoriesHtml}</div>
          ${horarioHtml}
          <div style="margin-top:6px;font-size:12px;color:#777">${escapeHtml(tipoLabel)}</div>
          ${locationHtml}
          <div style="margin-top:8px;text-align:right">
            <button onclick="onViewDetails('${escapeHtml(item.tipo)}', ${item.id})" style="padding:8px 10px;background:#3498db;color:#fff;border:none;border-radius:6px;cursor:pointer;font-weight:700;">Ver más</button>
          </div>
        </div>
      </div>
    `;

    marker.bindPopup(popupHtml, { maxWidth: 420 });
    markers.push(marker);
  });

  // Nota: no hacemos fitBounds aquí para no saltar al usuario cada letra — lo hacemos si la búsqueda fue forzada
}
/* ------------------ FIN POPUP MEJORADO ------------------ */

 // al hacer clic en "Ver detalles" del popup
function onViewDetails(tipo, id) {
  // redirige a la vista de detalle centralizada.
  // Usa ../detalle/detalle.php como plantilla que renderizará según tipo e id.
  const safeTipo = encodeURIComponent(String(tipo || 'campana'));
  const safeId = encodeURIComponent(String(id));
  window.location.href = `../detalle/detalle.php?tipo=${safeTipo}&id=${safeId}`;
}

// manejador general de búsqueda
// if force => hay Enter o botón; else => búsqueda en vivo
function onSearch(force = false) {
  const q = document.getElementById('searchInput').value.trim();
  const activeChip = document.querySelector('.chip.active');
  const categoria = activeChip ? activeChip.textContent : '';

  // si está vacío y no hay filtros, ocultamos
  if (!q && !categoria) {
    hideResults();
    // también cargamos todos los lugares sin filtro (opcional)
    loadPlaces({});
    return;
  }

  // al pedir force buscamos y hacemos fit bounds
  loadPlaces({ q, categoria, forceFit: !!force });
  // si queremos que el usuario vea la lista cuando presiona Enter, dejamos fillResults en loadPlaces
}

// muestra la lista de resultados (estilo autocompletar)
function fillResults(list) {
  if (!resultsListEl) return;
  resultsListEl.innerHTML = '';

  if (!Array.isArray(list) || list.length === 0) {
    hideResults();
    return;
  }

  // ordenar por distancia si tenemos userLocation
  if (userLocation) {
    list.sort((a, b) => {
      const da = calcDistance(userLocation.lat, userLocation.lng, parseFloat(a.lat) || 0, parseFloat(a.lng) || 0);
      const db = calcDistance(userLocation.lat, userLocation.lng, parseFloat(b.lat) || 0, parseFloat(b.lng) || 0);
      return da - db;
    });
  }

  // crear items
  list.forEach(c => {
    const item = document.createElement('div');
    item.className = 'result-item';
    item.style.padding = '10px';
    item.style.cursor = 'pointer';
    item.style.display = 'flex';
    item.style.flexDirection = 'column';
    item.style.borderBottom = '1px solid #f0f0f0';
    item.style.background = '#fff';

    const titleRow = document.createElement('div');
    titleRow.style.display = 'flex';
    titleRow.style.justifyContent = 'space-between';
    titleRow.style.alignItems = 'center';

    const title = document.createElement('strong');
    title.textContent = c.titulo || 'Sin título';
    title.style.fontSize = '14px';
    title.style.color = '#16222a';

    titleRow.appendChild(title);

    // distancia
    if (userLocation && c.lat && c.lng) {
      const d = calcDistance(userLocation.lat, userLocation.lng, parseFloat(c.lat), parseFloat(c.lng));
      const distEl = document.createElement('span');
      distEl.style.fontSize = '13px';
      distEl.style.color = '#6b7280';
      distEl.textContent = `${d.toFixed(1)} km`;
      titleRow.appendChild(distEl);
    }

    const meta = document.createElement('div');
    meta.style.fontSize = '13px';
    meta.style.color = '#556';
    meta.style.marginTop = '6px';
    meta.textContent = (c.categorias || '');

    item.appendChild(titleRow);
    item.appendChild(meta);

    // click en resultado: centrar y abrir popup
    item.addEventListener('click', () => {
      focusOnCenter(c);
      hideResults();
    });

    // hover visual
    item.addEventListener('mouseenter', () => { item.style.background = '#f6fbff'; });
    item.addEventListener('mouseleave', () => { item.style.background = '#fff'; });

    resultsListEl.appendChild(item);
  });

  resultsListEl.style.display = 'block';
}

// centra el mapa en la campaña y abre su popup
function focusOnCenter(camp) {
  const lat = parseFloat(camp.lat);
  const lng = parseFloat(camp.lng);
  if (isNaN(lat) || isNaN(lng)) return;

  map.setView([lat, lng], 15);

  // buscar marker por id y tipo
  const marker = markers.find(m => String(m._campId) === String(camp.id) && String(m._type) === String(camp.tipo));
  if (marker) {
    marker.openPopup();
  } else {
    // si no existe (por ejemplo no fue cargado por filtro), crear un temporary marker y abrir popup
    const tmp = L.marker([lat, lng]).addTo(map);
    tmp.bindPopup(`<strong>${escapeHtml(camp.titulo)}</strong>`).openPopup();
    setTimeout(() => { try { map.removeLayer(tmp); } catch(e) {} }, 5000);
  }
}

function hideResults() {
  if (!resultsListEl) return;
  resultsListEl.innerHTML = '';
  resultsListEl.style.display = 'none';
}

// distancia Haversine en km
function calcDistance(lat1, lon1, lat2, lon2) {
  const aLat = Number(lat1);
  const aLon = Number(lon1);
  const bLat = Number(lat2);
  const bLon = Number(lon2);
  if ([aLat,aLon,bLat,bLon].some(v => Number.isNaN(v))) return Infinity;
  const R = 6371;
  const dLat = (bLat - aLat) * Math.PI/180;
  const dLon = (bLon - aLon) * Math.PI/180;
  const A =
    Math.sin(dLat/2) * Math.sin(dLat/2) +
    Math.cos(aLat * Math.PI/180) * Math.cos(bLat * Math.PI/180) *
    Math.sin(dLon/2) * Math.sin(dLon/2);
  const C = 2 * Math.atan2(Math.sqrt(A), Math.sqrt(1-A));
  return R * C;
}

function escapeHtml(text = '') {
  if (!text) return '';
  return String(text).replace(/[&<>"']/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[m]));
}

/* ---------- Helpers: slugify, truncate, buildCategoryChips ---------- */
function slugify(text = '') {
  // convierte "Centro San Vicente" -> "CentroSanVicente"
  // paso 1: normalizar (quitar tildes)
  const from = "ÁÀÄÂáàäâÉÈËÊéèëêÍÌÏÎíìïîÓÒÖÔóòöôÚÙÜÛúùüûÑñÇç";
  const to   = "AAAAaaaaEEEEeeeeIIIIiiiiOOOOooooUUUUuuuuNnCc";
  let s = text.split('').map((c, i) => {
    const idx = from.indexOf(c);
    return idx > -1 ? to[idx] : c;
  }).join('');
  // quitar caracteres no alfanuméricos (permitimos espacio)
  s = s.replace(/[^a-zA-Z0-9\s]/g, '');
  // dividir palabras y capitalizar primera letra
  const parts = s.split(/\s+/).filter(Boolean).map(p => p.charAt(0).toUpperCase() + p.slice(1));
  return parts.join('');
}

function truncate(text = '', max = 120) {
  if (!text) return '';
  if (text.length <= max) return text;
  return text.slice(0, max - 1).trim() + '…';
}

function buildCategoryChips(cats) {
  if (!cats) return '';
  return cats.split(',').map(x => x.trim()).filter(Boolean).map(x => {
    return `<span style="display:inline-block;background:#eef6ff;color:#2b6cb0;padding:4px 8px;border-radius:12px;font-size:12px;margin-right:6px;margin-top:4px">${escapeHtml(x)}</span>`;
  }).join('');
}
/* ---------------------------------------------------------------- */

document.addEventListener('DOMContentLoaded', init);