<?php
require_once __DIR__ . '/../db.php';
session_start();
if (!isset($_SESSION['usuario_id'])) { header('Location: ../login/login.html'); exit; }
$uid = intval($_SESSION['usuario_id']);

if (!isset($_FILES['foto'])) { header('Location: perfil.php?upload=err'); exit; }
$file = $_FILES['foto'];
$max = 2 * 1024 * 1024;
if ($file['size'] > $max) { header('Location: perfil.php?upload=large'); exit; }

$allowed = ['image/jpeg','image/png','image/gif','image/webp'];
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mime = finfo_file($finfo, $file['tmp_name']);
finfo_close($finfo);
if (!in_array($mime, $allowed)) { header('Location: perfil.php?upload=type'); exit; }

$dir = __DIR__ . '/../uploads/avatars';
if (!is_dir($dir)) @mkdir($dir, 0755, true);
$ext = pathinfo($file['name'], PATHINFO_EXTENSION);
$fname = 'avatar_' . $uid . '_' . time() . '.' . $ext;
$dest = $dir . '/' . $fname;
if (!move_uploaded_file($file['tmp_name'], $dest)) { header('Location: perfil.php?upload=err'); exit; }

// guardar ruta relativa en BD
$rel = 'uploads/avatars/' . $fname;
if ($conn) {
    $stmt = $conn->prepare("UPDATE usuarios SET foto = ? WHERE id_usuario = ?");
    if ($stmt) { $stmt->bind_param("si", $rel, $uid); $stmt->execute(); $stmt->close(); }
    $conn->close();
}

header('Location: perfil.php?upload=ok');
exit;