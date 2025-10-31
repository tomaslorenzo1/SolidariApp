<?php
// index/api_campanas.php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../db.php';

$q = trim($_GET['q'] ?? '');
$categoria = trim($_GET['categoria'] ?? '');

// Fecha actual para filtrar campañas activas
$hoy = date('Y-m-d');

$sql = "SELECT `id_campaña` AS id, titulo, descripcion, lat, lng, categorias, horario
        FROM `campañas`
        WHERE (fecha_inicio IS NULL OR fecha_inicio <= ?) 
          AND (fecha_fin IS NULL OR fecha_fin >= ?)";

$params = [];
$types = '';
$params[] = &$hoy;
$params[] = &$hoy;
$types .= 'ss';

// filtro por texto (nombre/descr)
if ($q !== '') {
    $sql .= " AND (titulo LIKE ? OR descripcion LIKE ?)";
    $like = '%' . $q . '%';
    $params[] = &$like;
    $params[] = &$like;
    $types .= 'ss';
}

// filtro por categoría (busca dentro del campo 'categorias', separado por comas)
if ($categoria !== '') {
    // normalizar espacios antes de usar FIND_IN_SET
    $sql .= " AND FIND_IN_SET(?, REPLACE(categorias, ', ', ',')) > 0";
    $params[] = &$categoria;
    $types .= 's';
}

$stmt = $conn->prepare($sql);
if (!$stmt) {
    echo json_encode(['error' => 'Error en la consulta: ' . $conn->error], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($params) {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$res = $stmt->get_result();

$data = [];
while ($row = $res->fetch_assoc()) {
    // asegurar lat/lng como float para JS
    $row['lat'] = $row['lat'] !== null ? floatval($row['lat']) : null;
    $row['lng'] = $row['lng'] !== null ? floatval($row['lng']) : null;
    $data[] = $row;
}

echo json_encode($data, JSON_UNESCAPED_UNICODE);
$conn->close();