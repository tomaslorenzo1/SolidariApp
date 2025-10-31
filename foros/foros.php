<?php
session_start();
require_once '../db.php';

// ✅ Si no hay sesión de usuario, asignar un nombre invitado automáticamente
if (!isset($_SESSION['usuario_id']) && !isset($_SESSION['usuario_nombre'])) {
    $_SESSION['usuario_nombre'] = 'Invitado' . rand(1000, 9999);
}

// ✅ Obtener nombre actual (logueado o invitado)
$nombreUsuario = $_SESSION['usuario_nombre'];

// ✅ Publicar un nuevo mensaje
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['contenido'])) {
    $contenido = trim($_POST['contenido']);
    if (!empty($contenido)) {
        $stmt = $conn->prepare("INSERT INTO publicaciones (usuario_id, contenido) VALUES (?, ?)");
        $stmt->bind_param("is", $_SESSION['usuario_id'], $contenido);
        $stmt->execute();
        $stmt->close();
    }
}

// ✅ Obtener publicaciones existentes
$result = $conn->query("SELECT usuario_id, contenido FROM publicaciones");
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Foros</title>
    <link rel="stylesheet" href="../foros/foros.css">
</head>
<body>
    <header>
        <h1>Bienvenido al Foro</h1>
        <p>Estás conectado como: <strong><?php echo htmlspecialchars($nombreUsuario); ?></strong></p>
    </header>

    <section class="nuevo-post">
        <form action="" method="POST">
            <textarea name="contenido" placeholder="Escribe algo..." required></textarea><br>
            <button type="submit">Publicar</button>
        </form>
    </section>

    <section class="feed">
        <h2>Publicaciones recientes</h2>
        <?php while ($row = $result->fetch_assoc()): ?>
            <div class="post">
                <p><strong><?php echo htmlspecialchars($_SESSION['usuario_nombre']); ?></strong> dijo:</p>
                <p><?php echo nl2br(htmlspecialchars($row['contenido'])); ?></p>
            </div>
        <?php endwhile; ?>
    </section>

    <!-- bottom nav -->
<nav class="bottom-nav" aria-label="Navegación principal">
  <a href="../inicio/inicio.html" class="nav-item" title="Inicio">
    <svg width="20" height="20" viewBox="0 0 24 24"><path fill="currentColor" d="M12 3l9 8h-3v7h-4v-5H10v5H6v-7H3z"/></svg>
    <span class="nav-label">Inicio</span>
  </a>
  <a href="../panel/panel.php" class="nav-item" title="Panel">
    <svg width="20" height="20" viewBox="0 0 24 24"><path fill="currentColor" d="M3 13h8V3H3v10zm0 8h8v-6H3v6zm10 0h8v-10h-8v10zm0-18v6h8V3h-8z"/></svg>
    <span class="nav-label">Panel</span>
  </a>
  <a href="../crear/crear.php" class="nav-item" title="Crear">
    <svg width="24" height="24" viewBox="0 0 24 24"><path fill="currentColor" d="M11 11V6h2v5h5v2h-5v5h-2v-5H6v-2z"/></svg>
    <span class="nav-label">Crear</span>
  </a>
  <a href="../foros/foros.php" class="nav-item active" title="Foro">
    <svg width="20" height="20" viewBox="0 0 24 24"><path fill="currentColor" d="M20 2H4c-1.1 0-2 .9-2 2v14l4-4h14c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2z"/></svg>
    <span class="nav-label">Foro</span>
  </a>
  <a href="../perfil/perfil.php" class="nav-item" title="Perfil">
    <svg width="20" height="20" viewBox="0 0 24 24"><path fill="currentColor" d="M12 12c2.7 0 5-2.3 5-5s-2.3-5-5-5-5 2.3-5 5 2.3 5 5 5zm0 2c-4 0-8 2-8 6v2h16v-2c0-4-4-6-8-6z"/></svg>
    <span class="nav-label">Perfil</span>
  </a>
</nav>

  <script src="foros.js"></script>
</body>
</html>
