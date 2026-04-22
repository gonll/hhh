<?php
/**
 * Ejecuta solo las correcciones de datos (correcciones_post_deploy) sin git pull.
 * Útil cuando en el servidor ya hicieron git pull a mano y no se disparó sync_git.php.
 *
 * Misma autenticación que sync_git.php: POST uuid=... o header X-Deploy-UUID
 * (valor DEPLOY_UUID en .env del servidor).
 *
 * Respuesta JSON: ok, correcciones, errores, borrados_teran (si aplica)
 */
header('Content-Type: application/json; charset=utf-8');

$envPath = dirname(__DIR__) . '/.env';
if (!is_readable($envPath)) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'No se encuentra .env']);
    exit;
}

$env = parse_ini_file($envPath);
$expectedUuid = trim($env['DEPLOY_UUID'] ?? '');
if ($expectedUuid === '') {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'DEPLOY_UUID no configurado']);
    exit;
}

$uuid = '';
if (!empty($_POST['uuid'])) {
    $uuid = trim($_POST['uuid']);
} elseif (!empty($_SERVER['HTTP_X_DEPLOY_UUID'])) {
    $uuid = trim($_SERVER['HTTP_X_DEPLOY_UUID']);
}

if ($uuid === '' || $uuid !== $expectedUuid) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'UUID inválido o no enviado']);
    exit;
}

require_once __DIR__ . '/correcciones_post_deploy.php';

$dbHost = $env['DB_HOST'] ?? 'localhost';
$dbUser = $env['DB_USER'] ?? '';
$dbPass = $env['DB_PASS'] ?? '';
$dbName = $env['DB_NAME'] ?? '';
$conn = @mysqli_connect($dbHost, $dbUser, $dbPass, $dbName);
if (!$conn) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Conexión MySQL: ' . mysqli_connect_error()]);
    exit;
}
mysqli_set_charset($conn, 'utf8mb4');
$res = ejecutar_correcciones_deploy($conn);
mysqli_close($conn);

$out = [
    'ok' => true,
    'correcciones' => $res['aplicadas'] ?? [],
    'errores' => $res['errores'] ?? [],
];
if (empty($out['correcciones']) && empty($out['errores'])) {
    $out['aviso'] = 'Ninguna corrección retornó mensaje; puede que ya estuvan aplicadas o no hubo coincidencias en la base.';
}
echo json_encode($out, JSON_UNESCAPED_UNICODE);
