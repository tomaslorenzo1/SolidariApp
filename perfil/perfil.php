<?php
// perfil/perfil.php
require_once __DIR__ . '/../db.php';
session_start();

// si no hay sesión, ir a login
if (!isset($_SESSION['usuario_id'])) {
    header('Location: ../login/login.html');
    exit;
}

$uid = intval($_SESSION['usuario_id']);

// obtener datos del usuario
$stmt = $conn->prepare("SELECT id_usuario, nombre, email, rol, dni, telefono, direccion, email_verificado, fecha_registro, foto FROM usuarios WHERE id_usuario = ? LIMIT 1");
$stmt->bind_param("i", $uid);
$stmt->execute();
$res = $stmt->get_result();
if ($res->num_rows === 0) {
    // no existe -> cerrar sesión y redirigir
    session_destroy();
    header('Location: ../login/login.html');
    exit;
}
$user = $res->fetch_assoc();
$stmt->close();
$conn->close();

// rutas de imagen para mostrar
if (!empty($user['foto'])) {
    $avatarUrl = '/SolidariApp/' . ltrim($user['foto'], '/');
} else {
    $avatarUrl = 'img/default_avatar.png';
}

// mensajes (flash)
$msg = '';
$msg_type = '';
if (isset($_GET['upload'])) {
    if ($_GET['upload'] === 'ok') {
        $msg = 'Foto actualizada correctamente.';
        $msg_type = 'success';
    } elseif ($_GET['upload'] === 'large') {
        $msg = 'Archivo demasiado grande (límite 2MB).';
        $msg_type = 'error';
    } elseif ($_GET['upload'] === 'type') {
        $msg = 'Formato no permitido. Solo JPG, PNG, GIF, WEBP.';
        $msg_type = 'error';
    } else {
        $msg = 'Error al subir la imagen.';
        $msg_type = 'error';
    }
}

if (isset($_GET['pw'])) {
    if ($_GET['pw'] === 'ok') {
        $msg = 'Contraseña actualizada correctamente.';
        $msg_type = 'success';
    } elseif ($_GET['pw'] === 'wrong') {
        $msg = 'Contraseña actual incorrecta.';
        $msg_type = 'error';
    } elseif ($_GET['pw'] === 'nomatch') {
        $msg = 'La nueva contraseña y la confirmación no coinciden.';
        $msg_type = 'error';
    } elseif ($_GET['pw'] === 'empty') {
        $msg = 'Completá todos los campos de contraseña.';
        $msg_type = 'error';
    } else {
        $msg = 'Error al actualizar contraseña.';
        $msg_type = 'error';
    }
}

function role_label($r){
    switch ($r) {
        case 'donante': return 'Donante';
        case 'beneficiario': return 'Centro / Beneficiario';
        case 'admin': return 'Administrador';
        default: return ucfirst($r);
    }
}
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>Perfil - SolidariApp</title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="perfil.css">
</head>
<body>
<header class="topbar">
  <div class="left">
    <img src="img/logo_header.png" class="brand-logo" alt="SolidariApp">
    <span class="brand-name">SolidariApp</span>
  </div>
  <div class="right">
    <button class="icon-btn bell-btn" title="Notificaciones" onclick="location.href='../notificaciones/'">
      <img src="img/campanita.png" alt="Notificaciones">
    </button>
  </div>
</header>

<main class="content">
  <section class="profile-card">
    <?php if ($msg): ?>
      <div class="alert <?php echo $msg_type === 'success' ? 'success' : 'error'; ?>">
        <?php echo htmlspecialchars($msg); ?>
      </div>
    <?php endif; ?>

    <div class="profile-top">
      <form id="fotoForm" action="upload_foto.php" method="POST" enctype="multipart/form-data">
        <input type="file" id="fotoInput" name="foto" accept="image/*" style="display:none">
        <div class="avatar-wrap" id="avatarWrap">
          <img id="avatarImg" src="<?php echo htmlspecialchars($avatarUrl); ?>" alt="Avatar">
          <div class="avatar-overlay">
            <svg xmlns="http://www.w3.org/2000/svg" class="icon" viewBox="0 0 24 24"><path fill="currentColor" d="M3 17.25V21h3.75L17.81 9.94l-3.75-3.75L3 17.25M21.41 6.34c.38-.38.38-1.02 0-1.41L19.07 2.59c-.39-.38-1.03-.38-1.41 0l-1.83 1.83 3.75 3.75 1.83-1.83z"/></svg>
            <span>Cambiar foto de perfil</span>
          </div>
        </div>
      </form>

      <div class="profile-info">
        <h2><?php echo htmlspecialchars($user['nombre']); ?></h2>
        <div class="role"><?php echo role_label($user['rol']); ?></div>
        <div class="info-list">
          <div><strong>Email:</strong> <?php echo htmlspecialchars($user['email']); ?></div>
          <div><strong>DNI:</strong> <?php echo htmlspecialchars($user['dni']); ?></div>
          <div><strong>Teléfono:</strong> <?php echo htmlspecialchars($user['telefono']); ?></div>
          <div><strong>Dirección:</strong> <?php echo htmlspecialchars($user['direccion']); ?></div>
          <div><strong>Registrado el:</strong> <?php echo htmlspecialchars($user['fecha_registro']); ?></div>
        </div>
      </div>
    </div>

    <hr>

    <div class="password-section">
      <a href="../recuperar/recuperar.html" class="btn-link">Cambiar contraseña</a>
    </div>

    <div style="margin-top:18px;">
      <a class="btn-logout" href="../login/logout.php">Cerrar sesión</a>
    </div>
  </section>
</main>

<!-- bottom nav -->
<nav class="bottom-nav" aria-label="Navegación principal">
  <a href="../inicio/inicio.html" class="nav-item" title="Inicio">
    <svg width="20" height="20" viewBox="0 0 24 24"><path fill="currentColor" d="M12 3l9 8h-3v7h-4v-5H10v5H6v-7H3z"/></svg>
    <span class="nav-label">Inicio</span>
  </a>
  <a href="../panel/panel.html" class="nav-item" title="Panel">
    <svg width="20" height="20" viewBox="0 0 24 24"><path fill="currentColor" d="M3 13h8V3H3v10zm0 8h8v-6H3v6zm10 0h8v-10h-8v10zm0-18v6h8V3h-8z"/></svg>
    <span class="nav-label">Panel</span>
  </a>
  <a href="../crear/crear.php" class="nav-item" title="Crear">
    <svg width="24" height="24" viewBox="0 0 24 24"><path fill="currentColor" d="M11 11V6h2v5h5v2h-5v5h-2v-5H6v-2z"/></svg>
    <span class="nav-label">Crear</span>
  </a>
  <a href="../foro/foro.html" class="nav-item" title="Foro">
    <svg width="20" height="20" viewBox="0 0 24 24"><path fill="currentColor" d="M20 2H4c-1.1 0-2 .9-2 2v14l4-4h14c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2z"/></svg>
    <span class="nav-label">Foro</span>
  </a>
  <a href="perfil.php" class="nav-item active" title="Perfil">
    <svg width="20" height="20" viewBox="0 0 24 24"><path fill="currentColor" d="M12 12c2.7 0 5-2.3 5-5s-2.3-5-5-5-5 2.3-5 5 2.3 5 5 5zm0 2c-4 0-8 2-8 6v2h16v-2c0-4-4-6-8-6z"/></svg>
    <span class="nav-label">Perfil</span>
  </a>
</nav>

<script src="perfil.js"></script>
</body>
</html>