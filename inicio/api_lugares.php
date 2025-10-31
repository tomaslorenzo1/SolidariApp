<?php
// index/api_lugares.php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../db.php';

// Parámetros opcionales
$q = trim($_GET['q'] ?? '');
$categoria = trim($_GET['categoria'] ?? '');

// Fecha actual para filtrar campañas activas
$hoy = date('Y-m-d');

$data = [];

// 1) CAMPANAS aprobadas y dentro de rango de fechas (si tienen fechas)
$sqlCamp = "SELECT id_campaña AS id, titulo, descripcion, direccion, lat, lng, categorias, horario, imagenes, fecha_inicio, fecha_fin
            FROM `campañas`
            WHERE estado = 'aprobado'
              AND (fecha_inicio IS NULL OR fecha_inicio <= ?)
              AND (fecha_fin IS NULL OR fecha_fin >= ?)";

// preparar y ejecutar para campañas
if ($stmt = $conn->prepare($sqlCamp)) {
    $stmt->bind_param('ss', $hoy, $hoy);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        // filtros por q / categoria (si vienen) en PHP para simplicidad
        $include = true;
        if ($q !== '') {
            $needle = mb_strtolower($q);
            $hay = mb_strtolower(($row['titulo'] ?? '') . ' ' . ($row['descripcion'] ?? '') . ' ' . ($row['categorias'] ?? ''));
            if (mb_strpos($hay, $needle) === false) $include = false;
        }
        if ($include && $categoria !== '') {
            // busco dentro de categorias separadas por coma (ignoro espacios)
            $cats = array_map('trim', explode(',', $row['categorias'] ?? ''));
            $found = false;
            foreach ($cats as $c) {
                if (mb_strtolower($c) === mb_strtolower($categoria)) { $found = true; break; }
            }
            if (!$found) $include = false;
        }
        if (!$include) continue;

        // asegurarnos lat/lng como float o null
        $row['lat'] = $row['lat'] !== null && $row['lat'] !== '' ? floatval($row['lat']) : null;
        $row['lng'] = $row['lng'] !== null && $row['lng'] !== '' ? floatval($row['lng']) : null;

        // imágenes: mantener como array si es JSON, o como string vacío
        $imgs = [];
        if (!empty($row['imagenes'])) {
            // intentar decode
            $dec = json_decode($row['imagenes'], true);
            if (is_array($dec)) $imgs = $dec;
            else {
                // si no es JSON, intentar parsear coma-sep
                $imgs = array_map('trim', explode(',', $row['imagenes']));
            }
        }
        $row['imagenes'] = $imgs;

        // tipo
        $row['tipo'] = 'campana';

        $data[] = $row;
    }
    $stmt->close();
}

// 2) CENTROS aprobados
$sqlCentro = "SELECT id_centro AS id, nombre, descripcion, direccion, lat, lng, categorias, horario, imagenes, fecha_creacion
              FROM `centros_donacion`
              WHERE estado = 'aprobado'";

if ($res = $conn->query($sqlCentro)) {
    while ($row = $res->fetch_assoc()) {
        $include = true;
        if ($q !== '') {
            $needle = mb_strtolower($q);
            $hay = mb_strtolower(($row['nombre'] ?? '') . ' ' . ($row['descripcion'] ?? '') . ' ' . ($row['categorias'] ?? '') . ' ' . ($row['direccion'] ?? ''));
            if (mb_strpos($hay, $needle) === false) $include = false;
        }
        if ($include && $categoria !== '') {
            $cats = array_map('trim', explode(',', $row['categorias'] ?? ''));
            $found = false;
            foreach ($cats as $c) {
                if (mb_strtolower($c) === mb_strtolower($categoria)) { $found = true; break; }
            }
            if (!$found) $include = false;
        }
        if (!$include) continue;

        // map fields to same shape as campañas
        $out = [];
        $out['id'] = $row['id'];
        $out['titulo'] = $row['nombre'];
        $out['descripcion'] = $row['descripcion'];
        $out['direccion'] = $row['direccion'];
        $out['lat'] = $row['lat'] !== null && $row['lat'] !== '' ? floatval($row['lat']) : null;
        $out['lng'] = $row['lng'] !== null && $row['lng'] !== '' ? floatval($row['lng']) : null;
        $out['categorias'] = $row['categorias'];
        $out['horario'] = $row['horario'];
        $imgs = [];
        if (!empty($row['imagenes'])) {
            $dec = json_decode($row['imagenes'], true);
            if (is_array($dec)) $imgs = $dec;
            else $imgs = array_map('trim', explode(',', $row['imagenes']));
        }
        $out['imagenes'] = $imgs;
        $out['tipo'] = 'centro';
        $data[] = $out;
    }
}

// devolver JSON
echo json_encode($data, JSON_UNESCAPED_UNICODE);
$conn->close();