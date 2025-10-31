<?php
// recuperar/recuperar.php
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$mensaje = "";
$tipo = ""; // "success" o "error"

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $email = trim($_POST['email'] ?? '');

    if ($email === '') {
        $mensaje = "Completá el correo.";
        $tipo = "error";
    } else {
        // buscar usuario
        $stmt = $conn->prepare("SELECT id_usuario, nombre FROM usuarios WHERE email = ? LIMIT 1");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $res = $stmt->get_result();

        if ($res->num_rows === 0) {
            $mensaje = "No existe una cuenta asociada a ese correo.";
            $tipo = "error";
        } else {
            $u = $res->fetch_assoc();
            $userId = $u['id_usuario'];
            $userName = $u['nombre'];

            // generar token y expiry
            $token = bin2hex(random_bytes(16));
            $expiry = date("Y-m-d H:i:s", strtotime('+1 hour'));

            // Guardar token
            $upd = $conn->prepare("UPDATE usuarios SET reset_token = ?, reset_expiry = ? WHERE id_usuario = ?");
            $upd->bind_param("ssi", $token, $expiry, $userId);
            $upd->execute();
            $upd->close();

            // Preparar y enviar mail con PHPMailer
            try {
                $mail = new PHPMailer(true);
                $mail->isSMTP();
                $mail->Host = 'smtp.gmail.com';
                $mail->SMTPAuth = true;
                $mail->Username   = 'solidariappmail@gmail.com';    // tu correo (ya lo indicaste)
                $mail->Password   = 'ksmhcosgkdintxms';            // App Password (sin espacios)
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                $mail->Port = 587;

                // Opciones SSL (útil en local si hay problemas)
                $mail->SMTPOptions = [
                    'ssl' => [
                        'verify_peer' => false,
                        'verify_peer_name' => false,
                        'allow_self_signed' => true
                    ]
                ];

                // Forzar charset utf-8 para evitar problemas con acentos/ñ en asunto y cuerpo
                $mail->CharSet = 'UTF-8';
                $mail->Encoding = 'base64';

                // Remitente y destinatario
                $mail->setFrom('solidariappmail@gmail.com', 'SolidariApp');
                $mail->addAddress($email, $userName);

                // Adjuntar logo embebido si existe (usa CID en el HTML)
                $logoPath = __DIR__ . '/img/logo.png';
                if (file_exists($logoPath)) {
                    $mail->addEmbeddedImage($logoPath, 'logo_cid');
                }

                $mail->isHTML(true);

                // Asunto (con acentos: PHPMailer manejará la codificación porque seteamos CharSet)
                $mail->Subject = 'Recuperar contraseña - SolidariApp';

                $link = "http://localhost/SolidariApp/recuperar/recuperar_contraseña.php?token=" . urlencode($token);

                // Body: usar cid:logo_cid para mostrar la imagen embebida en el mail
                $mail->Body = "
                <div style='font-family:Arial,Helvetica,sans-serif;color:#222;'>
                  <div style='max-width:600px;margin:0 auto;padding:20px;border:1px solid #eee;border-radius:8px;background:#fff;'>
                    <div style='text-align:center;margin-bottom:20px;'>
                      <img src='cid:logo_cid' alt='SolidariApp' style='height:60px' />
                      <h2 style='color:#2b6cb0;margin:10px 0 0'>Recuperación de contraseña</h2>
                    </div>
                    <p>Hola <strong>" . htmlspecialchars($userName) . "</strong>,</p>
                    <p>Hiciste una solicitud de restablecimiento de contraseña para tu cuenta. Hacé clic en el botón de abajo para crear una nueva contraseña.</p>
                    <div style='text-align:center;margin:20px 0;'>
                      <a href='$link' style='display:inline-block;padding:12px 20px;background:#3498db;color:#fff;border-radius:6px;text-decoration:none;font-weight:700;'>Restablecer contraseña</a>
                    </div>
                    <p>Si no solicitaste esto, podés ignorar este correo. El enlace expira en 1 hora.</p>
                    <p style='margin-top:20px;color:#555;font-size:13px'>SolidariApp</p>
                  </div>
                </div>";

                // Texto alternativo plano (por si cliente no muestra HTML)
                $mail->AltBody = "Hola {$userName},\n\nUsá el siguiente enlace para restablecer tu contraseña: $link\n\nSi no solicitaste esto, ignorá este mensaje.";

                $mail->send();

                $mensaje = "Se envió un enlace de recuperación a tu correo.";
                $tipo = "success";
            } catch (Exception $e) {
                $mensaje = "No se pudo enviar el correo: " . $mail->ErrorInfo;
                $tipo = "error";
            }
        }

        $stmt->close();
    }
}
$conn->close();
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>Recuperar cuenta - SolidariApp</title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="recuperar.css">
</head>
<body>
  <header class="topbar">
    <img src="img/logo_header.png" alt="SolidariApp" class="brand-logo">
    <div class="brand-name">SolidariApp</div>
  </header>

  <main>
    <section class="form-section">
      <h2>"<u>Recuperar cuenta</u>"</h2>

      <?php if ($mensaje): ?>
        <div class="message <?php echo $tipo === 'success' ? 'success' : 'error'; ?>">
          <?php echo htmlspecialchars($mensaje); ?>
        </div>
      <?php endif; ?>

      <form action="" method="POST">
        <label for="email">Correo electrónico</label>
        <input type="email" id="email" name="email" required placeholder="tucorreo@dominio.com">
        <button type="submit">Enviar enlace</button>
      </form>
      
      <div class="link">
      <p><a href="../login/login.html">"Volver al inicio de sesión"</a></p>
      </div>

    </section>
  </main>
</body>
</html>