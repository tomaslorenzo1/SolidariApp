<?php
require_once("../db.php");
require '../vendor/autoload.php'; // Cargamos PHPMailer

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $nombre    = $_POST['nombre'];
    $email     = $_POST['email'];
    $dni       = $_POST['dni'];
    $telefono  = $_POST['telefono'];
    $direccion = $_POST['direccion'];
    $password  = $_POST['password'];
    $rol       = $_POST['rol']; // <-- nuevo: 'donante' o 'beneficiario'

    // Encriptamos la contraseña
    $password_hash = password_hash($password, PASSWORD_DEFAULT);

    // Código de verificación de 4 dígitos
    $codigo_verificacion = rand(1000, 9999);

    // Fecha de expiración del código (24 horas desde ahora)
    $token_expiry = date("Y-m-d H:i:s", strtotime("+1 day"));

    // MODIFICADO: ahora usamos placeholder para rol y lo insertamos
    $stmt = $conn->prepare("INSERT INTO usuarios 
        (nombre, email, password, rol, dni, telefono, direccion, email_verificado, token_verificacion, token_expiry, fecha_registro) 
        VALUES (?, ?, ?, ?, ?, ?, ?, 0, ?, ?, NOW())");

    if (!$stmt) {
        // fallo en preparar la consulta
        die("Error en la preparación de la consulta: " . $conn->error);
    }

    // bind_param actualizado: ahora hay 9 placeholders (todos strings salvo que quieras otro tipo)
    $stmt->bind_param("sssssssss", $nombre, $email, $password_hash, $rol, $dni, $telefono, $direccion, $codigo_verificacion, $token_expiry);

    if ($stmt->execute()) {
        // Enviar correo con PHPMailer
        $mail = new PHPMailer(true);

        try {
            // Configuración del servidor SMTP (Gmail + App Password)
            $mail->isSMTP();
            $mail->Host       = 'smtp.gmail.com';
            $mail->SMTPAuth   = true;
            $mail->Username   = 'solidariappmail@gmail.com';    // tu correo (ya lo indicaste)
            $mail->Password   = 'ksmhcosgkdintxms';            // App Password (sin espacios)
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = 587;

            // Opcional: evita errores SSL en entornos locales (si diera problemas)
            $mail->SMTPOptions = [
                'ssl' => [
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                    'allow_self_signed' => true
                ]
            ];

            // Remitente y destinatario
            $mail->setFrom('lorenzopernet@gmail.com', 'SolidariApp');
            $mail->addAddress($email, $nombre);

            // Contenido del correo
            $mail->isHTML(true);
            $mail->Subject = 'Verificacion de tu cuenta - SolidariApp';
            $mail->Body    = "
                <div style='font-family: Arial, sans-serif; color: #333; padding: 20px;'>
                    <h2 style='color:#3498db;'>¡Bienvenido a SolidariApp, " . htmlspecialchars($nombre) . "!</h2>
                    <p>Para completar tu registro, por favor utiliza el siguiente código de verificación:</p>
                    <div style='font-size: 24px; font-weight: bold; color: #3498db; margin: 18px 0;'>
                        " . htmlspecialchars($codigo_verificacion) . "
                    </div>
                    <p>Este código expirará en 24 horas.</p>
                    <br>
                    <p style='font-size: 12px; color: #888;'>Si no solicitaste este registro, ignora este mensaje.</p>
                </div>
            ";

            $mail->send();

            // Cerrar statement y conexión antes de redirigir
            $stmt->close();
            $conn->close();

            // Redirigir a página de ingreso de código (misma carpeta)
            header("Location: verificar.php?email=" . urlencode($email));
            exit();

        } catch (Exception $e) {
            // Mostrar error de envío para que puedas depurar (si algo falla)
            $stmt->close();
            $conn->close();
            echo "❌ Error al enviar el correo: " . htmlspecialchars($mail->ErrorInfo);
            exit;
        }

    } else {
        // Error al ejecutar el INSERT (p. ej. email duplicado)
        $err = $stmt->error;
        $stmt->close();
        $conn->close();
        echo "❌ Error en el registro: " . htmlspecialchars($err);
        exit;
    }
}
?>