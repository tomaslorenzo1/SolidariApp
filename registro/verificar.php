<?php
require_once("../db.php");

$mensajeError = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $email  = $_POST['email'];
    $codigo = $_POST['codigo'];

    $stmt = $conn->prepare("SELECT id_usuario, token_verificacion, token_expiry FROM usuarios WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $usuario = $result->fetch_assoc();

        // Verificar código y expiración
        if ($usuario['token_verificacion'] == $codigo && strtotime($usuario['token_expiry']) > time()) {
            $update = $conn->prepare("UPDATE usuarios SET email_verificado = 1 WHERE id_usuario = ?");
            $update->bind_param("i", $usuario['id_usuario']);
            $update->execute();

            // Redirigir a pantalla de éxito
            header("Location: registro_exito.php");
            exit();
        } else {
            $mensajeError = "Código incorrecto o expirado.";
        }
    } else {
        $mensajeError = "Usuario no encontrado.";
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
      transition: border-color 0.2s;
    }
    .otp-inputs input:focus {
      border-color: var(--blue);
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
  </style>
</head>
<body>
  <header class="topbar">
    <img src="img/logo_header.png" alt="SolidariApp" class="brand-logo">
    <div class="brand-name">SolidariApp</div>
  </header>

  <main>
    <section class="form-section">
      <h2><u>Verificar correo</u></h2>

      <?php if ($mensajeError): ?>
        <div class="error-msg"><?php echo htmlspecialchars($mensajeError); ?></div>
      <?php endif; ?>

      <form method="POST" action="">
        <input type="hidden" name="email" value="<?php echo isset($_GET['email']) ? htmlspecialchars($_GET['email']) : (isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''); ?>">

        <center><label for="codigo">Introduce tu código de 4 dígitos</label></center>
        <div class="otp-inputs">
          <input type="text" maxlength="1" pattern="[0-9]" required>
          <input type="text" maxlength="1" pattern="[0-9]" required>
          <input type="text" maxlength="1" pattern="[0-9]" required>
          <input type="text" maxlength="1" pattern="[0-9]" required>
        </div>

        <!-- input oculto donde concatenamos los 4 dígitos -->
        <input type="hidden" id="codigo" name="codigo">

        <button type="submit">Verificar</button>
      </form>
    </section>
  </main>

  <script>
    // Manejo de inputs OTP
    const inputs = document.querySelectorAll(".otp-inputs input");
    const hiddenInput = document.getElementById("codigo");

    inputs.forEach((input, index) => {
      input.addEventListener("input", () => {
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
    });

    function updateHidden() {
      hiddenInput.value = Array.from(inputs).map(i => i.value).join("");
    }
  </script>
</body>
</html>