<?php
// perfil/actualizar_password.php
require_once __DIR__ . '/../db.php';
session_start();

if (!isset($_SESSION['usuario_id'])) {
    header('Location: ../login/login.html');
    exit;
}

$uid = intval($_SESSION['usuario_id']);
$current = $_POST['current_password'] ?? '';
$new = $_POST['new_password'] ?? '';
$confirm = $_POST['confirm_password'] ?? '';

if ($current === '' || $new === '' || $confirm === '') {
    header('Location: perfil.php?pw=empty');
    exit;
}

if ($new !== $confirm) {
    header('Location: perfil.php?pw=nomatch');
    exit;
}

// obtener hash actual
$stmt = $conn->prepare("SELECT password FROM usuarios WHERE id_usuario = ?");
$stmt->bind_param("i", $uid);
$stmt->execute();
$res = $stmt->get_result();
if ($res->num_rows === 0) {
    $stmt->close();
    header('Location: ../login/login.html');
    exit;
}
$row = $res->fetch_assoc();
$stmt->close();

if (!password_verify($current, $row['password'])) {
    header('Location: perfil.php?pw=wrong');
    exit;
}

// actualizar
$newHash = password_hash($new, PASSWORD_DEFAULT);
$upd = $conn->prepare("UPDATE usuarios SET password = ? WHERE id_usuario = ?");
$upd->bind_param("si", $newHash, $uid);
$ok = $upd->execute();
$upd->close();
$conn->close();

if ($ok) header('Location: perfil.php?pw=ok');
else header('Location: perfil.php?pw=err');