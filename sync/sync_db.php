<?php
/**
 * Sincronizar base de datos: recibe POST con UUID y dump SQL; si UUID coincide con .env ejecuta el dump en MySQL.
 * Uso: POST uuid=... y dump=... (o header X-Deploy-UUID y cuerpo = dump)
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
$dump = '';
if (!empty($_POST['uuid'])) {
    $uuid = trim($_POST['uuid']);
    $dump = isset($_POST['dump']) ? $_POST['dump'] : '';
} elseif (!empty($_SERVER['HTTP_X_DEPLOY_UUID'])) {
    $uuid = trim($_SERVER['HTTP_X_DEPLOY_UUID']);
    $dump = file_get_contents('php://input');
} else {
    $uuid = '';
}

if ($uuid === '' || $uuid !== $expectedUuid) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'UUID inválido o no enviado']);
    exit;
}

if ($dump === '') {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'No se recibió el dump']);
    exit;
}

$host = $env['DB_HOST'] ?? 'localhost';
$user = $env['DB_USER'] ?? '';
$pass = $env['DB_PASS'] ?? '';
$base = $env['DB_NAME'] ?? '';

$conn = @mysqli_connect($host, $user, $pass, $base);
if (!$conn) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Conexión MySQL: ' . mysqli_connect_error()]);
    exit;
}

mysqli_set_charset($conn, 'utf8mb4');

// Quitar líneas DELIMITER (comando del cliente mysqldump) para que multi_query funcione
$dump = preg_replace('/^\s*DELIMITER\s+.+$/m', '', $dump);
$dump = str_replace(';;', ';', $dump);

if (!mysqli_multi_query($conn, $dump)) {
    $err = mysqli_error($conn);
    mysqli_close($conn);
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Error al ejecutar el dump: ' . $err]);
    exit;
}

do {
    if ($result = mysqli_store_result($conn)) {
        mysqli_free_result($result);
    }
} while (mysqli_next_result($conn));

if (mysqli_errno($conn)) {
    $err = mysqli_error($conn);
    mysqli_close($conn);
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Error en el dump: ' . $err]);
    exit;
}

mysqli_close($conn);
echo json_encode(['ok' => true]);
