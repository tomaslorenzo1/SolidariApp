<?php
require_once("../db.php");

$mensajeError = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $email  = trim($_POST['email'] ?? '');
    $codigo = trim($_POST['codigo'] ?? '');

    if ($email === '' || $codigo === '') {
        $mensajeError = "Completá el correo y el código.";
    } else {
        $stmt = $conn->prepare("SELECT id_usuario, token_verificacion, token_expiry FROM usuarios WHERE email = ? LIMIT 1");
        if (!$stmt) {
            $mensajeError = "Error de base de datos: " . $conn->error;
        } else {
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result && $result->num_rows > 0) {
                $usuario = $result->fetch_assoc();

                // Verificar código y expiración
                if ($usuario['token_verificacion'] == $codigo && strtotime($usuario['token_expiry']) > time()) {
                    $update = $conn->prepare("UPDATE usuarios SET email_verificado = 1 WHERE id_usuario = ?");
                    $update->bind_param("i", $usuario['id_usuario']);
                    $update->execute();
                    $update->close();
                    $stmt->close();
                    $conn->close();

                    // Redirigir a pantalla de éxito
                    header("Location: registro_exito.php");
                    exit();
                } else {
                    $mensajeError = "Código incorrecto o expirado.";
                }
            } else {
                $mensajeError = "Usuario no encontrado.";
            }
            if ($stmt) $stmt->close();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Verificar correo - SolidariApp</title>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="registro.css">
  <style>
    /* Ajustes específicos para verificar */
    .otp-inputs {
      display: flex;
      justify-content: center;
      gap: 12px;
      margin: 20px 0;
    }
    .otp-inputs input {
      width: 55px;
      height: 55px;
      text-align: center;
      font-size: 24px;
      font-weight: bold;
      border: 2px solid #ccd1d9;
      border-radius: 8px;
      outline: none;
      transition: border-color 0.2s, box-shadow 0.12s;
      background: #fff;
    }
    .otp-inputs input:focus {
      border-color: var(--blue);
      box-shadow: 0 6px 18px rgba(52,152,219,0.08);
    }
    .error-msg {
      color: #e74c3c;
      text-align: center;
      margin-top: 12px;
      font-weight: 600;
      animation: shake 0.3s;
    }
    @keyframes shake {
      0% { transform: translateX(0); }
      25% { transform: translateX(-5px); }
      50% { transform: translateX(5px); }
      75% { transform: translateX(-5px); }
      100% { transform: translateX(0); }
    }
    .info-note { text-align:center;color:#555;margin-top:8px;font-size:14px; }
  </style>
</head>
<body>
  <header class="topbar">
    <img src="img/logo_header.png" alt="SolidariApp" class="brand-logo">
    <div class="brand-name">SolidariApp</div>
  </header>

  <main>
    <section class="form-section" style="max-width:420px;margin:30px auto;">
      <h2><u>Verificar correo</u></h2>

      <?php if ($mensajeError): ?>
        <div class="error-msg"><?php echo htmlspecialchars($mensajeError); ?></div>
      <?php endif; ?>

      <form method="POST" action="">
        <input type="hidden" name="email" value="<?php echo isset($_GET['email']) ? htmlspecialchars($_GET['email']) : (isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''); ?>">

        <center><label for="codigo">Introduce tu código de 4 dígitos</label></center>

        <div class="otp-inputs" aria-hidden="false">
          <input type="text" maxlength="1" pattern="[0-9]" inputmode="numeric" required>
          <input type="text" maxlength="1" pattern="[0-9]" inputmode="numeric" required>
          <input type="text" maxlength="1" pattern="[0-9]" inputmode="numeric" required>
          <input type="text" maxlength="1" pattern="[0-9]" inputmode="numeric" required>
        </div>

        <!-- input oculto donde concatenamos los 4 dígitos -->
        <input type="hidden" id="codigo" name="codigo">

        <button type="submit">Verificar</button>
      </form>

      <p class="info-note">Te enviamos un código por correo. Si no lo recibiste, revisá la carpeta Spam.</p>
      <p style="margin-top:12px;text-align:center;"><a href="registro.php" style="color:var(--blue)">Volver al registro</a> · <a href="../login/login.html" style="color:var(--blue)">Ir a iniciar sesión</a></p>
    </section>
  </main>

  <script>
    // Manejo de inputs OTP (igual al backup que tenías)
    const inputs = document.querySelectorAll(".otp-inputs input");
    const hiddenInput = document.getElementById("codigo");

    inputs.forEach((input, index) => {
      input.addEventListener("input", () => {
        // sólo permitir dígitos
        input.value = input.value.replace(/\D/g,'').slice(0,1);
        if (input.value.length > 0 && index < inputs.length - 1) {
          inputs[index + 1].focus();
        }
        updateHidden();
      });

      input.addEventListener("keydown", (e) => {
        if (e.key === "Backspace" && input.value === "" && index > 0) {
          inputs[index - 1].focus();
        }
      });

      // focus inicial en primer input si hay email en GET
      if (index === 0 && !document.activeElement) {
        // no forzamos
      }
    });

    function updateHidden() {
      hiddenInput.value = Array.from(inputs).map(i => i.value).join("");
    }
  </script>
</body>
</html>