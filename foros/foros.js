
const feedEl = document.getElementById('feed');
const tplPost = document.getElementById('tpl-post');
const formPublicacion = document.getElementById('form-publicacion');

async function fetchJSON(url, data=null) {
  const opts = data ? { method:'POST', body:data } : { method:'GET' };
  const res = await fetch(url, opts);
  return res.json();
}

async function loadPosts() {
  feedEl.innerHTML = '<p class="muted">Cargando...</p>';
  const res = await fetchJSON('acciones_foros.php?action=get_posts&limit=50');
  if (res.status !== 'ok') { feedEl.innerHTML = '<p>Error al cargar</p>'; return; }
  feedEl.innerHTML = '';
  for (const item of res.posts) renderPost(item);
}

function renderPost(data) {
  const p = data.post;
  const clone = tplPost.content.cloneNode(true);
  const article = clone.querySelector('article');
  article.dataset.id = p.id_publicacion;

  const avatar = clone.querySelector('.post-avatar');
  avatar.src = p.foto ? ('../' + p.foto) : '../img/logo.png';

  clone.querySelector('.post-user-nombre').textContent = p.nombre;
  clone.querySelector('.post-meta').textContent = new Date(p.fecha).toLocaleString();

  const tituloEl = clone.querySelector('.post-titulo');
  if (p.titulo) tituloEl.textContent = p.titulo;
  const contentEl = clone.querySelector('.post-content');
  contentEl.textContent = p.contenido;

  if (p.imagen) {
    const media = clone.querySelector('.post-media');
    const img = document.createElement('img');
    img.src = p.imagen.startsWith('http') ? p.imagen : ('../' + p.imagen);
    img.className = 'post-img';
    media.appendChild(img);
  }

  const likeBtn = clone.querySelector('.btn-like');
  const likeCount = clone.querySelector('.like-count');
  likeCount.textContent = data.likes;
  if (data.liked_by_me) likeBtn.classList.add('liked');

  // Si no hay usuario logueado, mostrar alerta al intentar interactuar
  if (CURRENT_USER_ID === null) {
    likeBtn.addEventListener('click', () => {
      alert('Iniciá sesión para dar me gusta.');
    });
  } else {
    likeBtn.addEventListener('click', async () => {
      const fd = new FormData();
      fd.append('action','toggle_like');
      fd.append('id_publicacion', p.id_publicacion);
      const r = await fetchJSON('acciones_foros.php', fd);
      if (r.status === 'ok') loadPosts();
      else alert(r.msg || 'Error');
    });
  }

  const commentBtn = clone.querySelector('.btn-comment');
  const commentCount = clone.querySelector('.comment-count');
  commentCount.textContent = data.comments;
  commentBtn.addEventListener('click', () => {
    const commentsList = article.querySelector('.comments-list');
    commentsList.style.display = (commentsList.style.display === 'block') ? 'none' : 'block';
    const input = article.querySelector('.input-comment');
    if (input) input.focus();
  });

  // owner actions (editar / eliminar) solo si el usuario logueado es dueño
  const ownerActions = clone.querySelector('.post-actions-owner');
  if (CURRENT_USER_ID !== null && parseInt(p.usuario_id) === parseInt(CURRENT_USER_ID)) {
    const editBtn = document.createElement('button');
    editBtn.className = 'btn-small';
    editBtn.textContent = 'Editar';
    const delBtn = document.createElement('button');
    delBtn.className = 'btn-small btn-danger';
    delBtn.textContent = 'Eliminar';

    editBtn.addEventListener('click', ()=> startEdit(article, p));
    delBtn.addEventListener('click', async () => {
      if (!confirm('¿Eliminar publicación?')) return;
      const fd = new FormData();
      fd.append('action','eliminar_publicacion');
      fd.append('id_publicacion', p.id_publicacion);
      const r = await fetchJSON('acciones_foros.php', fd);
      if (r.status === 'ok') loadPosts(); else alert(r.msg || 'Error');
    });

    ownerActions.appendChild(editBtn);
    ownerActions.appendChild(delBtn);
  }

  // comments preview
  const commentsList = clone.querySelector('.comments-list');
  commentsList.innerHTML = '';
  for (const c of data.last_comments) {
    const div = document.createElement('div');
    div.className = 'comment';
    div.innerHTML = `<strong>${escapeHtml(c.nombre)}</strong> <small>${new Date(c.fecha).toLocaleString()}</small><div>${escapeHtml(c.contenido)}</div>`;
    commentsList.appendChild(div);
  }

  // comment form: si no hay sesión, reemplazar por mensaje para loguearse
  const formC = clone.querySelector('.form-comment');
  if (CURRENT_USER_ID === null) {
    formC.innerHTML = '<div style="padding:.6rem;color:var(--muted)">Iniciá sesión para comentar. <a href="../login/login.php">Entrar</a></div>';
  } else {
    formC.addEventListener('submit', async (e) => {
      e.preventDefault();
      const txt = formC.querySelector('.input-comment').value.trim();
      if (!txt) return;
      const fd = new FormData();
      fd.append('action','crear_comentario');
      fd.append('id_publicacion', p.id_publicacion);
      fd.append('texto', txt);
      const r = await fetchJSON('acciones_foros.php', fd);
      if (r.status === 'ok') loadPosts(); else alert(r.msg || 'Error');
    });
  }

  feedEl.appendChild(clone);
}

function startEdit(article, post) {
  const contentDiv = article.querySelector('.post-content');
  const original = contentDiv.textContent;
  const ta = document.createElement('textarea');
  ta.value = original;
  contentDiv.replaceWith(ta);

  const actions = article.querySelector('.post-actions-owner');
  const save = document.createElement('button');
  save.textContent = 'Guardar';
  const cancel = document.createElement('button');
  cancel.textContent = 'Cancelar';
  actions.appendChild(save); actions.appendChild(cancel);

  save.addEventListener('click', async () => {
    const fd = new FormData();
    fd.append('action','editar_publicacion');
    fd.append('id_publicacion', post.id_publicacion);
    fd.append('contenido', ta.value);
    const r = await fetchJSON('acciones_foros.php', fd);
    if (r.status === 'ok') loadPosts(); else alert(r.msg || 'Error');
  });
  cancel.addEventListener('click', ()=> loadPosts());
}

if (formPublicacion) {
  formPublicacion.addEventListener('submit', async (e) => {
    e.preventDefault();
    if (CURRENT_USER_ID === null) {
      alert('Debes iniciar sesión para publicar.');
      return;
    }
    const fd = new FormData(formPublicacion);
    fd.append('action','crear_publicacion');
    document.getElementById('btn-publicar').disabled = true;
    const r = await fetchJSON('acciones_foros.php', fd);
    document.getElementById('btn-publicar').disabled = false;
    if (r.status === 'ok') { formPublicacion.reset(); loadPosts(); }
    else alert(r.msg || 'Error al publicar');
  });
}

function escapeHtml(s){ return String(s).replace(/[&<>"']/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m])); }

loadPosts();
