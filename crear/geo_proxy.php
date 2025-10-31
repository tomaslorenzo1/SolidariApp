<?php
// crear/geo_proxy.php
// Proxy simple a Nominatim para evitar problemas de CORS/User-Agent desde el navegador
// Uso: GET ?q=texto&limit=6
header('Content-Type: application/json; charset=utf-8');

$q = isset($_GET['q']) ? trim($_GET['q']) : '';
$limit = isset($_GET['limit']) ? intval($_GET['limit']) : 6;
if ($limit < 1 || $limit > 15) $limit = 6;

if ($q === '') {
    echo json_encode([]);
    exit;
}

$endpoint = 'https://nominatim.openstreetmap.org/search';
$params = http_build_query([
    'format' => 'json',
    'addressdetails' => 1,
    'limit' => $limit,
    'q' => $q,
]);
$url = $endpoint . '?' . $params;

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
// User-Agent recomendado por Nominatim: incluir contacto del proyecto
curl_setopt($ch, CURLOPT_USERAGENT, 'SolidariApp/1.0 (contacto: soporte@solidariapp.local)');
// En algunos hostings, forzar IPv4 ayuda
curl_setopt($ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);

$res = curl_exec($ch);
if ($res === false) {
    http_response_code(502);
    echo json_encode(['error' => 'proxy_error', 'detail' => curl_error($ch)]);
    curl_close($ch);
    exit;
}
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

http_response_code($code);
echo $res;
