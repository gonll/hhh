<?php
/**
 * Ingreso/retiro directo en el libro Transferencias (nivelación), sin pasar por otra cuenta de persona.
 */
include 'db.php';
include 'verificar_sesion.php';
require_once __DIR__ . '/includes_transferencias_libro.php';

if (isset($_SESSION['acceso_nivel']) && $_SESSION['acceso_nivel'] < 2) {
    header('HTTP/1.0 403 Forbidden');
    echo 'Sin permiso';
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo 'Error';
    exit;
}

$fecha_raw = trim($_POST['fecha'] ?? '');
$concepto_user = trim($_POST['concepto'] ?? '');
$tipo = strtoupper(trim($_POST['tipo'] ?? ''));
$monto_raw = isset($_POST['monto']) ? str_replace(',', '.', trim((string)$_POST['monto'])) : '';
$refer_user = trim($_POST['refer'] ?? '');

if ($concepto_user === '' || (function_exists('mb_strlen') ? mb_strlen($concepto_user) : strlen($concepto_user)) < 3) {
    echo 'Error: Indique el motivo o concepto (mínimo 3 caracteres).';
    exit;
}
if ($tipo !== 'INGRESO' && $tipo !== 'RETIRO') {
    echo 'Error: Tipo inválido.';
    exit;
}
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha_raw)) {
    echo 'Error: Fecha inválida.';
    exit;
}
$d = DateTime::createFromFormat('Y-m-d', $fecha_raw);
if (!$d || $d->format('Y-m-d') !== $fecha_raw) {
    echo 'Error: Fecha no válida.';
    exit;
}

$monto_abs = (float)$monto_raw;
if (!is_finite($monto_abs) || $monto_abs <= 0) {
    echo 'Error: Monto inválido.';
    exit;
}

$usuario_id = transferencias_libro_asegurar_usuario($conexion);
if ($usuario_id <= 0) {
    echo 'Error: No se pudo preparar la cuenta de libro.';
    exit;
}

$compro = 'NIVELACION TRANSF';
$cup = function_exists('mb_strtoupper') ? mb_strtoupper($concepto_user, 'UTF-8') : strtoupper($concepto_user);
$concepto_plano = 'NIVELACION — ' . $cup;
$refer_plano = $refer_user !== '' ? (function_exists('mb_strtoupper') ? mb_strtoupper($refer_user, 'UTF-8') : strtoupper($refer_user)) : 'NIVEL';
$monto = ($tipo === 'INGRESO') ? $monto_abs : -$monto_abs;

$stmt = mysqli_prepare($conexion, "INSERT INTO cuentas (usuario_id, fecha, concepto, comprobante, referencia, monto) VALUES (?, ?, ?, ?, ?, ?)");
if (!$stmt) {
    echo 'Error SQL';
    exit;
}
mysqli_stmt_bind_param($stmt, 'issssd', $usuario_id, $fecha_raw, $concepto_plano, $compro, $refer_plano, $monto);
if (!mysqli_stmt_execute($stmt)) {
    mysqli_stmt_close($stmt);
    echo 'Error al grabar';
    exit;
}
mysqli_stmt_close($stmt);

echo 'OK';
