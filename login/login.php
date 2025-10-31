<?php
// login.php (ruta: SolidariApp/login/login.php)
require_once __DIR__ . '/../db.php';
session_start();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: login.html');
    exit;
}

$email = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';

if ($email === '' || $password === '') {
    header('Location: login.html?err=' . urlencode('Completá todos los campos.'));
    exit;
}

// Preparar la consulta
$stmt = $conn->prepare("SELECT id_usuario, nombre, password, email_verificado 
                        FROM usuarios 
                        WHERE email = ? 
                        LIMIT 1");
if (!$stmt) {
    header('Location: login.html?err=' . urlencode('Error de base de datos.'));
    exit;
}
$stmt->bind_param("s", $email);
$stmt->execute();
$res = $stmt->get_result();

if ($res->num_rows === 0) {
    header('Location: login.html?err=' . urlencode('No existe una cuenta con ese correo.'));
    exit;
}

$user = $res->fetch_assoc();
$hash = $user['password'];

// ✅ Verificación segura de contraseña
if (!password_verify($password, $hash)) {
    header('Location: login.html?err=' . urlencode('Contraseña incorrecta.'));
    exit;
}

// ✅ Bloqueamos si el email no está verificado
if (isset($user['email_verificado']) && intval($user['email_verificado']) === 0) {
    header('Location: login.html?err=' . urlencode('Verificá tu email antes de ingresar.'));
    exit;
}

// ✅ Login correcto → crear sesión
$_SESSION['usuario_id'] = $user['id_usuario'];
$_SESSION['usuario_nombre'] = $user['nombre'];

header('Location: ../inicio/inicio.html');
exit;