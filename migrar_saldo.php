<?php
/**
 * Migrar saldo: toma el saldo actual de la cuenta, considera el saldo objetivo,
 * y graba un movimiento INICIAL tal que: saldo_despues = saldo_objetivo
 *
 * Fórmula: monto_a_grabar = saldo_objetivo - saldo_actual
 * Resultado: saldo_despues = saldo_actual + monto_a_grabar = saldo_objetivo
 */
include 'db.php';
include 'verificar_sesion.php';

if (isset($_SESSION['acceso_nivel']) && $_SESSION['acceso_nivel'] < 3) {
    header('HTTP/1.0 403 Forbidden');
    echo 'Sin permiso';
    exit;
}

if (!isset($_POST['usuario_id']) || !isset($_POST['monto'])) {
    echo 'Error: Faltan datos (usuario_id, monto).';
    exit;
}

$usuario_id = (int)$_POST['usuario_id'];
$monto_str = trim($_POST['monto'] ?? '');
$monto_objetivo = (float)str_replace(',', '.', preg_replace('/[^\d,\-]/', '', $monto_str));

if ($usuario_id <= 0) {
    echo 'Error: Usuario no válido.';
    exit;
}

// 1. Tomar el saldo actual de esa cuenta (suma de todos los movimientos)
$sql_saldo = "SELECT COALESCE(SUM(monto), 0) AS saldo FROM cuentas WHERE usuario_id = $usuario_id";
$res_saldo = mysqli_query($conexion, $sql_saldo);
if (!$res_saldo) {
    echo 'Error: ' . mysqli_error($conexion);
    exit;
}
$row = mysqli_fetch_assoc($res_saldo);
$saldo_actual = (float)($row['saldo'] ?? 0);

// 2. Calcular el monto a grabar para que saldo_despues = saldo_objetivo
$monto_a_grabar = $monto_objetivo - $saldo_actual;

if (abs($monto_a_grabar) < 0.01) {
    echo 'OK'; // Ya está en el monto correcto
    exit;
}

$fecha = date('Y-m-d');
$concepto = 'INICIAL';
$comprobante = 'INICIAL';
$referencia = 'INICIAL';

$sql = "INSERT INTO cuentas (usuario_id, fecha, concepto, comprobante, referencia, monto) 
        VALUES ($usuario_id, '$fecha', '$concepto', '$comprobante', '$referencia', $monto_a_grabar)";

if (!mysqli_query($conexion, $sql)) {
    echo 'Error: ' . mysqli_error($conexion);
    exit;
}

echo 'OK';
?>
