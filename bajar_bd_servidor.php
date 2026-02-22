<?php
/**
 * Bajar copia de la base de datos desde el servidor.
 * Solo nivel 3. Requiere ENVIRONMENT=dev y DEPLOY_URL en .env.
 */
include __DIR__ . '/db.php';
include __DIR__ . '/verificar_sesion.php';

if (!isset($_SESSION['acceso_nivel']) || $_SESSION['acceso_nivel'] < 3) {
    header('HTTP/1.0 403 Forbidden');
    die('Sin permiso');
}

if (!defined('ENVIRONMENT') || ENVIRONMENT !== 'dev') {
    header('Location: index.php');
    exit;
}

$env = parse_ini_file(__DIR__ . '/.env');
$url = trim($env['DEPLOY_URL'] ?? '');
$uuid = trim($env['DEPLOY_UUID'] ?? '');

if ($url === '' || $uuid === '') {
    header('Location: index.php?deploy=error&msg=' . urlencode('Faltan DEPLOY_URL o DEPLOY_UUID en .env'));
    exit;
}

$urlBajar = preg_replace('/sync_db\.php.*$/i', 'bajar_bd.php', rtrim($url, '/'));
if (strpos($urlBajar, 'bajar_bd.php') === false) {
    $urlBajar = preg_replace('#/[^/]*\.php.*$#', '/bajar_bd.php', $urlBajar);
}
$urlBajar .= (strpos($urlBajar, '?') !== false ? '&' : '?') . 'uuid=' . rawurlencode($uuid);

$ctx = stream_context_create([
    'http' => [
        'method' => 'GET',
        'header' => 'X-Deploy-UUID: ' . $uuid . "\r\n",
    ],
]);

$dump = @file_get_contents($urlBajar, false, $ctx);

if ($dump === false || strlen($dump) < 100) {
    header('Location: index.php?deploy=error&msg=' . urlencode('No se pudo descargar el dump del servidor. Revisar URL y que bajar_bd.php esté desplegado.'));
    exit;
}

$nombre = 'respaldo_servidor_' . date('Y-m-d_H-i-s') . '.sql';
header('Content-Type: application/sql; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $nombre . '"');
header('Content-Length: ' . strlen($dump));
echo $dump;
exit;
