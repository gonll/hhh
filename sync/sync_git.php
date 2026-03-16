<?php
/**
 * Sincronizar código: recibe POST con UUID; si coincide con .env ejecuta git pull.
 * Uso: POST uuid=... (o header X-Deploy-UUID)
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

$projectRoot = realpath(dirname(__DIR__));
$gitBin = trim(shell_exec('which git 2>/dev/null') ?: '') ?: '/usr/bin/git';
$cmd = 'cd ' . escapeshellarg($projectRoot) . ' && ' . escapeshellarg($gitBin) . ' pull 2>&1';
$output = [];
exec($cmd, $output, $code);

if ($code !== 0) {
    http_response_code(500);
    $outStr = implode("\n", $output);
    echo json_encode(['ok' => false, 'error' => 'git pull falló', 'output' => $outStr]);
    exit;
}

// Aplicar correcciones de datos en la base (tras pull de código)
$correcciones = [];
$dbHost = $env['DB_HOST'] ?? 'localhost';
$dbUser = $env['DB_USER'] ?? '';
$dbPass = $env['DB_PASS'] ?? '';
$dbName = $env['DB_NAME'] ?? '';
$conn = @mysqli_connect($dbHost, $dbUser, $dbPass, $dbName);
if ($conn) {
    mysqli_set_charset($conn, 'utf8mb4');
    require_once __DIR__ . '/correcciones_post_deploy.php';
    $res = ejecutar_correcciones_deploy($conn);
    $correcciones = $res['aplicadas'];
    mysqli_close($conn);
}

echo json_encode(['ok' => true, 'output' => implode("\n", $output), 'correcciones' => $correcciones]);
