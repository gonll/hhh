<?php
/**
 * Bajar base de datos: genera un dump del servidor y lo devuelve para descarga.
 * Se llama desde el cliente (local) con UUID. Solo nivel 3.
 * Uso: GET uuid=... o header X-Deploy-UUID
 */
$envPath = dirname(__DIR__) . '/.env';
if (!is_readable($envPath)) {
    http_response_code(500);
    header('Content-Type: text/plain');
    echo 'No se encuentra .env';
    exit;
}

$env = parse_ini_file($envPath);
$expectedUuid = trim($env['DEPLOY_UUID'] ?? '');
if ($expectedUuid === '') {
    http_response_code(500);
    header('Content-Type: text/plain');
    echo 'DEPLOY_UUID no configurado';
    exit;
}

$uuid = trim($_GET['uuid'] ?? $_SERVER['HTTP_X_DEPLOY_UUID'] ?? '');
if ($uuid === '' || $uuid !== $expectedUuid) {
    http_response_code(403);
    header('Content-Type: text/plain');
    echo 'UUID inválido';
    exit;
}

$host = $env['DB_HOST'] ?? 'localhost';
$user = $env['DB_USER'] ?? '';
$pass = $env['DB_PASS'] ?? '';
$base = $env['DB_NAME'] ?? '';

$mysqldump = 'mysqldump';
if (PHP_OS_FAMILY === 'Windows') {
    $mysqldump = 'C:\\xampp\\mysql\\bin\\mysqldump.exe';
    if (!file_exists($mysqldump)) $mysqldump = 'mysqldump';
} elseif (file_exists('/usr/bin/mysqldump')) {
    $mysqldump = '/usr/bin/mysqldump';
}

$tmp = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'bajar_bd_' . date('Ymd_His') . '.sql';
$escPass = str_replace("'", "'\\''", $pass);
if (PHP_OS_FAMILY === 'Windows') {
    $cmd = 'cd /d ' . escapeshellarg(dirname(__DIR__)) . ' && "' . $mysqldump . '" -h ' . escapeshellarg($host) . ' -u ' . escapeshellarg($user) . " -p'" . $escPass . "' " . escapeshellarg($base) . ' > ' . escapeshellarg($tmp) . ' 2>&1';
} else {
    $cmd = $mysqldump . ' -h ' . escapeshellarg($host) . ' -u ' . escapeshellarg($user) . " -p'" . $escPass . "' " . escapeshellarg($base) . ' > ' . escapeshellarg($tmp) . ' 2>/dev/null';
}
exec($cmd, $out, $code);

if ($code !== 0 || !is_readable($tmp)) {
    @unlink($tmp);
    http_response_code(500);
    header('Content-Type: text/plain');
    echo 'Error al generar dump: ' . implode("\n", $out);
    exit;
}

$nombre = 'respaldo_servidor_' . date('Y-m-d_H-i-s') . '.sql';
header('Content-Type: application/sql; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $nombre . '"');
header('Content-Length: ' . filesize($tmp));
readfile($tmp);
@unlink($tmp);
exit;
