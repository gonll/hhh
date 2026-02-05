<?php
include 'db.php';

define('ID_CAJA', 1);

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['usuario_id'], $_POST['propiedad_id'], $_POST['periodo'], $_POST['monto'])) {
    echo "Error: Faltan datos.";
    exit;
}

$usuario_id  = (int)$_POST['usuario_id'];
$propiedad_id = (int)$_POST['propiedad_id'];
$periodo     = trim($_POST['periodo']);
$monto       = (float)str_replace(',', '.', $_POST['monto']);

if ($usuario_id <= 0 || $propiedad_id <= 0 || $periodo === '' || $monto <= 0) {
    echo "Error: Datos inválidos.";
    exit;
}

// Formato periodo MM/AAAA o MM-AAAA
$periodo = strtoupper(preg_replace('/[^0-9\/\-]/', '', $periodo));
if (!preg_match('/^\d{1,2}[\/\-]\d{4}$/', $periodo)) {
    $periodo = str_replace('-', '/', $periodo);
}

$fecha = date('Y-m-d');

// Obtener Consorcio (primer usuario cuyo nombre empiece con CONSORCIO)
$res_con = mysqli_query($conexion, "SELECT id FROM usuarios WHERE UPPER(apellido) LIKE 'CONSORCIO%' LIMIT 1");
$row_con = mysqli_fetch_assoc($res_con);
if (!$row_con) {
    echo "Error: No se encontró usuario Consorcio en el sistema.";
    exit;
}
$consorcio_id = (int)$row_con['id'];

// Obtener nombre de la propiedad
$res_prop = mysqli_query($conexion, "SELECT propiedad FROM propiedades WHERE propiedad_id = $propiedad_id LIMIT 1");
$row_prop = mysqli_fetch_assoc($res_prop);
$nombre_prop = $row_prop ? mysqli_real_escape_string($conexion, strtoupper($row_prop['propiedad'])) : '';

// Obtener nombre del usuario
$res_usu = mysqli_query($conexion, "SELECT apellido FROM usuarios WHERE id = $usuario_id LIMIT 1");
$row_usu = mysqli_fetch_assoc($res_usu);
$nombre_usu = $row_usu ? mysqli_real_escape_string($conexion, strtoupper($row_usu['apellido'])) : '';

$concepto_consorcio = "COBRO EXPENSA $nombre_prop $periodo";
$concepto_usu = "EXPENSA $periodo";

$concepto_consorcio = mysqli_real_escape_string($conexion, $concepto_consorcio);
$concepto_usu = mysqli_real_escape_string($conexion, $concepto_usu);
$refer = mysqli_real_escape_string($conexion, "EXP/TRANSF");

// 1. Asiento en cuenta del Consorcio (INGRESO)
$sql1 = "INSERT INTO cuentas (usuario_id, fecha, concepto, comprobante, referencia, monto) 
         VALUES ($consorcio_id, '$fecha', '$concepto_consorcio', 'EXP/TRANSF', '$refer', $monto)";
if (!mysqli_query($conexion, $sql1)) {
    echo "Error al grabar en Consorcio: " . mysqli_error($conexion);
    exit;
}

// 2. Asiento en cuenta del usuario (INGRESO)
$sql2 = "INSERT INTO cuentas (usuario_id, fecha, concepto, comprobante, referencia, monto) 
         VALUES ($usuario_id, '$fecha', '$concepto_usu', 'EXP/TRANSF', '$refer', $monto)";
if (!mysqli_query($conexion, $sql2)) {
    echo "Error al grabar en usuario: " . mysqli_error($conexion);
    exit;
}

// 3. Un solo asiento en Caja (evitar doble contabilización)
$concepto_caja = $nombre_usu . " - COBRO EXPENSA " . $nombre_prop . " " . $periodo;
$concepto_caja = mysqli_real_escape_string($conexion, $concepto_caja);
$sql_caja = "INSERT INTO cuentas (usuario_id, fecha, concepto, comprobante, referencia, monto) 
             VALUES (" . ID_CAJA . ", '$fecha', '$concepto_caja', 'EXP/TRANSF', '$refer', $monto)";
if (!mysqli_query($conexion, $sql_caja)) {
    echo "Error al grabar en Caja: " . mysqli_error($conexion);
    exit;
}

echo "OK";
?>
