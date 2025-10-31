<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8" />
  <title>SolidariApp — Notificaciones</title>
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap" rel="stylesheet">
  <!-- reutilizamos estilos generales de inicio -->
  <link rel="stylesheet" href="../inicio/inicio.css">
  <!-- estilos específicos de notificaciones -->
  <link rel="stylesheet" href="notificaciones.css">
</head>
<body>
  <header class="topbar" role="banner">
    <div class="left">
      <img src="../inicio/img/logo_header.png" class="brand-logo" alt="SolidariApp">
      <div class="brand-name">SolidariApp</div>
    </div>

    <div class="right">
      <button id="btnBack" class="icon-btn" title="Volver" onclick="history.back()" aria-label="Volver">
        <!-- simple chevron -->
        <svg width="24" height="24" viewBox="0 0 24 24" aria-hidden="true"><path fill="#fff" d="M15.41 7.41L14 6l-6 6 6 6 1.41-1.41L10.83 12z"/></svg>
      </button>
    </div>
  </header>

  <main class="content" role="main">
    <section class="search-area" aria-label="Notificaciones">
      <div style="display:flex;justify-content:space-between;align-items:center;gap:12px;max-width:1100px;margin:0 auto;">
        <h2 style="margin:0;color:#16222a">Notificaciones</h2>
        <div style="display:flex;gap:8px">
          <button id="filterUnread" class="chip" type="button" aria-pressed="false">No leídas</button>
          <button id="markAll" class="chip" type="button">Marcar todo leído</button>
          <button id="clearAll" class="chip" type="button" style="background:#fff;color:#c0392b;border:1px solid #f5c6c6">Limpiar</button>
        </div>
      </div>

      <div id="notifContainer" class="results-list" style="margin-top:12px;max-width:1100px;"></div>
    </section>
  </main>

  <nav class="bottom-nav" aria-label="Navegación principal">
    <!-- replicamos nav simplificado -->
    <a href="../inicio/inicio.html" class="nav-item" title="Inicio">
      <svg width="20" height="20" viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M12 3l9 8h-3v7h-4v-5H10v5H6v-7H3z"/></svg>
    </a>
    <a href="../panel/panel.php" class="nav-item" title="Panel"><svg width="20" height="20" viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M3 13h8V3H3v10zm0 8h8v-6H3v6zm10 0h8v-10h-8v10zm0-18v6h8V3h-8z"/></svg></a>
    <a href="../crear/crear.php" class="nav-item" title="Crear"><svg width="24" height="24" viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M11 11V6h2v5h5v2h-5v5h-2v-5H6v-2z"/></svg></a>
    <a href="../foros/foros.php" class="nav-item" title="Foro"><svg width="20" height="20" viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M20 2H4c-1.1 0-2 .9-2 2v14l4-4h14c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2z"/></svg></a>
    <a href="../perfil/perfil.php" class="nav-item" title="Perfil"><svg width="20" height="20" viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M12 12c2.7 0 5-2.3 5-5s-2.3-5-5-5-5 2.3-5 5 2.3 5 5 5zm0 2c-4 0-8 2-8 6v2h16v-2c0-4-4-6-8-6z"/></svg></a>
  </nav>

  <script src="notificaciones.js"></script>
</body>
</html>
