<?php
// recuperar/recuperar_contraseña.php
require_once __DIR__ . '/../db.php';

$tokenValido = false;
$error = "";
$success = false;

if (isset($_GET['token'])) {
    $token = $_GET['token'];

    $stmt = $conn->prepare("SELECT id_usuario, reset_expiry FROM usuarios WHERE reset_token = ? LIMIT 1");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $res = $stmt->get_result();

    if ($res->num_rows === 1) {
        $u = $res->fetch_assoc();
        if (strtotime($u['reset_expiry']) > time()) {
            $tokenValido = true;
            $userId = $u['id_usuario'];

            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                $pass1 = $_POST['password'] ?? '';
                $pass2 = $_POST['confirm_password'] ?? '';

                if ($pass1 === '' || $pass2 === '') {
                    $error = "Completá ambos campos.";
                } elseif ($pass1 !== $pass2) {
                    $error = "Las contraseñas no coinciden.";
                } elseif (strlen($pass1) < 6) {
                    $error = "La contraseña debe tener al menos 6 caracteres.";
                } else {
                    // actualizar password (hasheada) y limpiar token
                    $hash = password_hash($pass1, PASSWORD_DEFAULT);
                    $upd = $conn->prepare("UPDATE usuarios SET password = ?, reset_token = NULL, reset_expiry = NULL WHERE id_usuario = ?");
                    $upd->bind_param("si", $hash, $userId);
                    $upd->execute();
                    $upd->close();

                    $success = true;
                    $conn->close();
                    header("Location: recuperar_exito.html");
                    exit;
                }
            }
        } else {
            $error = "El enlace ha expirado.";
        }
    } else {
        $error = "Token inválido.";
    }
    $stmt->close();
} else {
    $error = "Falta token en la URL.";
}
$conn->close();
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>Restablecer contraseña - SolidariApp</title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="recuperar.css">
  <style>
    /* Pequeñas adaptaciones (si querés moverlas al CSS principal, hacelo) */
    .password-container { position: relative; display:flex; align-items:center; }
    .password-container input { padding-right:45px; width:100%; }
    .toggle-password { position:absolute; right:10px; width:26px; height:26px; cursor:pointer; user-select:none; }
    .message { padding:10px;border-radius:8px;margin-bottom:12px;font-weight:600; }
    .message.error { background:#ffecec;border:1px solid #ffbdbd;color:#8b0000; }
  </style>
</head>
<body>
  <header class="topbar">
    <img src="img/logo_header.png" alt="SolidariApp" class="brand-logo">
    <div class="brand-name">SolidariApp</div>
  </header>

  <main>
    <section class="form-section">
      <h2>"<u>Restablecer contraseña</u>"</h2>

      <?php if ($error): ?>
        <div class="message error"><?php echo htmlspecialchars($error); ?></div>
      <?php endif; ?>

      <?php if ($tokenValido): ?>
        <form method="POST" action="" id="resetForm">
          <label for="password">Nueva contraseña</label>
          <div class="password-container">
            <input type="password" id="password" name="password" required autocomplete="new-password">
            <img src="img/eye_closed.png" id="togglePassword" class="toggle-password" alt="Mostrar/Ocultar">
          </div>

          <label for="confirm_password">Confirmar contraseña</label>
          <div class="password-container">
            <input type="password" id="confirm_password" name="confirm_password" required autocomplete="new-password">
            <img src="img/eye_closed.png" id="toggleConfirm" class="toggle-password" alt="Mostrar/Ocultar">
          </div>

          <button type="submit">Confirmar</button>
        </form>
      <?php else: ?>
        <p>Si el enlace no funciona, volvé a solicitar el restablecimiento desde la pantalla de recuperación.</p>
        <p><a href="recuperar.html">Solicitar nuevo enlace</a></p>
      <?php endif; ?>
      
      <div class="link">
      <p style="margin-top:12px;"><a href="../login/login.html">"Volver al inicio de sesión"</a></p>
      </div>

    </section>
  </main>

  <!-- Incluir el JS que gestiona mostrar/ocultar y la máscara de "última letra" -->
  <script src="recuperar.js"></script>
</body>
</html>