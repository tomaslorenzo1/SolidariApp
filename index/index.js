// index/index.js
const API_URL = './api_campanas.php';
let map, markers = [], campaigns = [];
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
        loadCampaigns({ categoria: cat, q: document.getElementById('searchInput').value.trim() });
      } else {
        loadCampaigns({ q: document.getElementById('searchInput').value.trim() });
      }
    };
    chips.appendChild(btn);
  });

  // carga inicial (muestra todos)
  loadCampaigns({});
}

// carga campañas desde API con filtros opcionales
function loadCampaigns(opts = {}) {
  const params = new URLSearchParams();
  if (opts.q) params.set('q', opts.q);
  if (opts.categoria) params.set('categoria', opts.categoria);

  fetch(API_URL + (params.toString() ? ('?' + params.toString()) : ''))
    .then(r => {
      if (!r.ok) throw new Error('Error en la respuesta del servidor');
      return r.json();
    })
    .then(data => {
      campaigns = Array.isArray(data) ? data : [];
      clearMarkers();
      addMarkers(campaigns);

      // si el usuario indicó buscar (q o categoria) o llamamos en vivo, mostramos resultados
      if ((opts.q && opts.q !== '') || (opts.categoria && opts.categoria !== '')) {
        fillResults(campaigns);
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
      console.error('Error al cargar campañas:', err);
      // mostrar mensaje liviano en UI si querés
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
  list.forEach(c => {
    const lat = parseFloat(c.lat);
    const lng = parseFloat(c.lng);
    if (isNaN(lat) || isNaN(lng)) return; // ignorar si no tiene coords

    const marker = L.marker([lat, lng]).addTo(map);
    // guardamos id para luego encontrar marker fácilmente
    marker._campId = c.id;

    // construir HTML del popup (imagen, título, descripción, chips, horario, distancia, botón)
    const slug = slugify(c.titulo || '');
    const imgPath = `../centros/${slug}/img/Portada.png`; // ruta tentativa según convención
    const description = truncate(c.descripcion || '', 140);
    const categoriesHtml = buildCategoryChips(c.categorias || '');
    const horarioHtml = c.horario ? `<div style="color:#777;font-size:13px;margin-top:6px">${escapeHtml(c.horario)}</div>` : '';
    let distanceHtml = '';
    if (userLocation && !isNaN(parseFloat(c.lat)) && !isNaN(parseFloat(c.lng))) {
      const d = calcDistance(userLocation.lat, userLocation.lng, parseFloat(c.lat), parseFloat(c.lng));
      distanceHtml = `<div style="font-size:13px;color:#555;margin-top:6px">A ${d.toFixed(1)} km</div>`;
    } else {
      distanceHtml = `<div style="font-size:13px;color:#555;margin-top:6px">Ubicación: ${lat.toFixed(5)}, ${lng.toFixed(5)}</div>`;
    }

    const popupHtml = `
      <div style="min-width:260px;font-family:inherit;display:flex;gap:10px">
        <div style="flex:0 0 110px;">
          <img src="${imgPath}" alt="${escapeHtml(c.titulo)}" style="width:110px;height:80px;object-fit:cover;border-radius:6px" onerror="this.style.display='none'">
        </div>
        <div style="flex:1 1 auto;">
          <div style="font-weight:700;font-size:15px;color:#16222a">${escapeHtml(c.titulo)}</div>
          <div style="font-size:13px;color:#333;margin-top:6px;line-height:1.2">${escapeHtml(description)}</div>
          <div style="margin-top:8px">${categoriesHtml}</div>
          ${horarioHtml}
          ${distanceHtml}
          <div style="margin-top:8px;text-align:right">
            <button onclick="onViewDetails(${c.id})" style="padding:8px 10px;background:#3498db;color:#fff;border:none;border-radius:6px;cursor:pointer;font-weight:700;">Ver más</button>
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
function onViewDetails(id) {
  // Mantengo la redirección que ya venías usando; podés cambiar la ruta si querés otra convención
  window.location.href = `../centros/centrodedonacionesej/centro.html?campana_id=${id}`;
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
    // también cargamos todas las campañas sin filtro (opcional)
    loadCampaigns({});
    return;
  }

  // al pedir force buscamos y hacemos fit bounds
  loadCampaigns({ q, categoria, forceFit: !!force });
  // si queremos que el usuario vea la lista cuando presiona Enter, dejamos fillResults en loadCampaigns
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

  // buscar marker por id
  const marker = markers.find(m => String(m._campId) === String(camp.id));
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