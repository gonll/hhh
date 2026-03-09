<?php
/**
 * Verifica si la clave ingresada coincide con la clave de borrado configurada.
 * Uso: POST con clave=xxx
 * Retorna: OK si coincide, ERROR si no.
 */
include 'db.php';
include 'verificar_sesion.php';
require_once __DIR__ . '/config_clave_borrado.php';

header('Content-Type: text/plain; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo 'ERROR';
    exit;
}

$clave_ingresada = trim($_POST['clave'] ?? '');
$clave_correcta = obtener_clave_borrado($conexion);

echo ($clave_ingresada === $clave_correcta) ? 'OK' : 'ERROR';
