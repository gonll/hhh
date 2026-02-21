<?php
/**
 * Actualiza un movimiento de arriendo con el precio de la bolsa de azúcar.
 * Cálculo: precio_bolsa/50 → si IVA≠0 dividir por (1+IVA/100) → restar dto% → * kilos → monto = -resultado
 * Actualiza: monto, comprobante='PRECIO DE LA BOLSA', referencia=valor_bolsa
 */
include 'db.php';
include 'verificar_sesion.php';
if (isset($_SESSION['acceso_nivel']) && $_SESSION['acceso_nivel'] < 2) {
    header('HTTP/1.0 403 Forbidden');
    echo 'Sin permiso';
    exit;
}

$movimiento_id = (int)($_POST['movimiento_id'] ?? 0);
$arriendo_id = (int)($_POST['arriendo_id'] ?? 0);
$arriendo_fecha = (int)($_POST['arriendo_fecha'] ?? 0);
$precio_bolsa = (float)($_POST['precio_bolsa'] ?? 0);

if ($movimiento_id <= 0 || $precio_bolsa <= 0) {
    echo 'Datos inválidos';
    exit;
}

$mov = mysqli_fetch_assoc(mysqli_query($conexion, "SELECT * FROM cuentas WHERE movimiento_id = $movimiento_id LIMIT 1"));
if (!$mov || strtoupper($mov['comprobante']) !== 'PRECIO AZUCAR' || strtoupper($mov['referencia']) !== 'A DEFINIR') {
    echo 'Movimiento no válido para actualizar';
    exit;
}

$arriendo_id_db = (int)($mov['arriendo_id'] ?? 0);
$arriendo_fecha_db = (int)($mov['arriendo_fecha'] ?? 0);
if ($arriendo_id_db <= 0) $arriendo_id_db = $arriendo_id;
if ($arriendo_fecha_db <= 0) $arriendo_fecha_db = $arriendo_fecha;

$kilos = 0;
$iva = 21;
$dto = 0;
if ($arriendo_id_db > 0) {
    $col_kilos = $arriendo_fecha_db == 2 ? 'kilos_fecha_2' : 'kilos_fecha_1';
    $ar = mysqli_fetch_assoc(mysqli_query($conexion, "SELECT $col_kilos AS kilos, iva_porcentaje, porcentaje_otros FROM arriendos WHERE id = $arriendo_id_db LIMIT 1"));
    if ($ar) {
        $kilos = (float)($ar['kilos'] ?? 0);
        $iva = isset($ar['iva_porcentaje']) && $ar['iva_porcentaje'] !== null ? (float)$ar['iva_porcentaje'] : 21;
        $dto = isset($ar['porcentaje_otros']) && $ar['porcentaje_otros'] !== null ? (float)$ar['porcentaje_otros'] : 0;
    }
}

if ($kilos <= 0) {
    echo 'No se pudo obtener kilos del arriendo';
    exit;
}

$precio_kilo = $precio_bolsa / 50;
if ($iva != 0) {
    $precio_kilo = $precio_kilo / (1 + $iva / 100);
}
$precio_kilo = $precio_kilo * (1 - $dto / 100);
$monto = -($precio_kilo * $kilos);

$comprobante = 'PRECIO DE LA BOLSA';
$referencia = number_format($precio_bolsa, 2, ',', '.');

$comprobante = mysqli_real_escape_string($conexion, $comprobante);
$referencia = mysqli_real_escape_string($conexion, $referencia);

$sql = "UPDATE cuentas SET monto = $monto, comprobante = '$comprobante', referencia = '$referencia' WHERE movimiento_id = $movimiento_id";
if (!mysqli_query($conexion, $sql)) {
    echo 'Error: ' . mysqli_error($conexion);
    exit;
}

echo 'OK';
