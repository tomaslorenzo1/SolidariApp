<?php
// perfil/upload_foto.php
require_once __DIR__ . '/../db.php';
session_start();

if (!isset($_SESSION['usuario_id'])) {
    header('Location: ../login/login.html');
    exit;
}

$uid = intval($_SESSION['usuario_id']);

if (!isset($_FILES['foto']) || $_FILES['foto']['error'] !== UPLOAD_ERR_OK) {
    header('Location: perfil.php?upload=err');
    exit;
}

// validaciones
$maxSize = 2 * 1024 * 1024; // 2MB
if ($_FILES['foto']['size'] > $maxSize) {
    header('Location: perfil.php?upload=large');
    exit;
}

$tmp = $_FILES['foto']['tmp_name'];
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mime = finfo_file($finfo, $tmp);
finfo_close($finfo);

$allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif', 'image/webp' => 'webp'];
if (!isset($allowed[$mime])) {
    header('Location: perfil.php?upload=type');
    exit;
}

$ext = $allowed[$mime];

// crear carpeta si no existe
$uploadsDir = __DIR__ . '/../uploads/usuarios/';
if (!is_dir($uploadsDir)) {
    mkdir($uploadsDir, 0755, true);
}

// generar nombre seguro
$filename = 'user_' . $uid . '_' . time() . '.' . $ext;
$dest = $uploadsDir . $filename;

if (!move_uploaded_file($tmp, $dest)) {
    header('Location: perfil.php?upload=err');
    exit;
}

// actualizar DB (y eliminar foto vieja si existe)
$stmt = $conn->prepare("SELECT foto FROM usuarios WHERE id_usuario = ? LIMIT 1");
$stmt->bind_param("i", $uid);
$stmt->execute();
$res = $stmt->get_result();
$old = null;
if ($row = $res->fetch_assoc()) {
    $old = $row['foto'];
}
$stmt->close();

$relPath = 'uploads/usuarios/' . $filename;
$upd = $conn->prepare("UPDATE usuarios SET foto = ? WHERE id_usuario = ?");
$upd->bind_param("si", $relPath, $uid);
$ok = $upd->execute();
$upd->close();
$conn->close();

if ($ok) {
    // eliminar archivo anterior si estaba en uploads/usuarios/
    if (!empty($old) && strpos($old, 'uploads/usuarios/') === 0) {
        $oldPath = __DIR__ . '/../' . $old;
        if (is_file($oldPath)) @unlink($oldPath);
    }
    header('Location: perfil.php?upload=ok');
    exit;
} else {
    header('Location: perfil.php?upload=err');
    exit;
}