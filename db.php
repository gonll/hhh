<?php
// Cache simple en proceso para evitar parsear .env múltiples veces por request
if (!isset($GLOBALS['HHH_ENV_CACHE']) || !is_array($GLOBALS['HHH_ENV_CACHE'])) {
    $env = @parse_ini_file(__DIR__ . '/.env') ?: [];
    $GLOBALS['HHH_ENV_CACHE'] = $env;
} else {
    $env = $GLOBALS['HHH_ENV_CACHE'];
}

if (!defined('ENVIRONMENT')) {
    define('ENVIRONMENT', $env['ENVIRONMENT'] ?? 'production');
}

$conexion = mysqli_connect(
    $env['DB_HOST'],
    $env['DB_USER'],
    $env['DB_PASS'],
    $env['DB_NAME']
);

if (!$conexion) {
    die("Error de conexión: " . mysqli_connect_error());
}

mysqli_set_charset($conexion, 'utf8mb4');
