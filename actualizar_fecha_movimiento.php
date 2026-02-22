<?php
include 'db.php';
include 'verificar_sesion.php';
if (isset($_SESSION['acceso_nivel']) && $_SESSION['acceso_nivel'] < 2) {
    header('HTTP/1.0 403 Forbidden');
    echo 'Sin permiso';
    exit;
}

$movimiento_id = (int)($_POST['movimiento_id'] ?? 0);
$fecha_raw = trim($_POST['fecha'] ?? '');

if ($movimiento_id <= 0) {
    echo 'ID inválido';
    exit;
}

// Aceptar yyyy-mm-dd (input date) o dd/mm/yyyy
$fecha_ok = false;
if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha_raw)) {
    $fecha_ok = $fecha_raw;
} elseif (preg_match('/^(\d{1,2})\/(\d{1,2})\/(\d{4})$/', $fecha_raw, $m)) {
    $fecha_ok = sprintf('%04d-%02d-%02d', (int)$m[3], (int)$m[2], (int)$m[1]);
}

if (!$fecha_ok) {
    echo 'Fecha inválida';
    exit;
}

$fecha_esc = mysqli_real_escape_string($conexion, $fecha_ok);
$sql = "UPDATE cuentas SET fecha = '$fecha_esc' WHERE movimiento_id = $movimiento_id";
if (!mysqli_query($conexion, $sql)) {
    echo 'Error: ' . mysqli_error($conexion);
    exit;
}

echo 'OK';
