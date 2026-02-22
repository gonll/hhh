<?php
/**
 * Migrar saldo: ajusta el saldo del usuario seleccionado al monto indicado.
 * Crea un movimiento INICIAL con la diferencia necesaria.
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
$monto_objetivo = (float)$_POST['monto'];

if ($usuario_id <= 0) {
    echo 'Error: Usuario no válido.';
    exit;
}

// Calcular saldo actual sumando todos los movimientos
$sql_saldo = "SELECT COALESCE(SUM(monto), 0) AS saldo FROM cuentas WHERE usuario_id = $usuario_id";
$res_saldo = mysqli_query($conexion, $sql_saldo);
if (!$res_saldo) {
    echo 'Error: ' . mysqli_error($conexion);
    exit;
}
$row = mysqli_fetch_assoc($res_saldo);
$saldo_actual = (float)($row['saldo'] ?? 0);

$diferencia = $monto_objetivo - $saldo_actual;

if (abs($diferencia) < 0.01) {
    echo 'OK'; // Ya está en el monto correcto
    exit;
}

$fecha = date('Y-m-d');
$concepto = 'INICIAL';
$comprobante = 'INICIAL';
$referencia = 'INICIAL';

$sql = "INSERT INTO cuentas (usuario_id, fecha, concepto, comprobante, referencia, monto) 
        VALUES ($usuario_id, '$fecha', '$concepto', '$comprobante', '$referencia', $diferencia)";

if (!mysqli_query($conexion, $sql)) {
    echo 'Error: ' . mysqli_error($conexion);
    exit;
}

echo 'OK';
?>
