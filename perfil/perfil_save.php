<?php
require_once __DIR__ . '/../db.php';
session_start();
if (!isset($_SESSION['usuario_id'])) { header('Location: ../login/login.html'); exit; }
$uid = intval($_SESSION['usuario_id']);

$nombre = trim($_POST['nombre'] ?? '');
$telefono = trim($_POST['telefono'] ?? '');
$direccion = trim($_POST['direccion'] ?? '');

if ($conn) {
    $stmt = $conn->prepare("UPDATE usuarios SET nombre = ?, telefono = ?, direccion = ? WHERE id_usuario = ?");
    if ($stmt) {
        $stmt->bind_param("sssi", $nombre, $telefono, $direccion, $uid);
        $ok = $stmt->execute();
        $stmt->close();
    } else { $ok = false; }
    $conn->close();
    if ($ok) header('Location: perfil.php?save=ok');
    else header('Location: perfil.php?save=err');
    exit;
}
header('Location: perfil.php?save=err');
