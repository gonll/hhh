<?php
include 'db.php';
include 'verificar_sesion.php';
include 'crear_tabla_arriendos.php';

if (($_SESSION['acceso_nivel'] ?? 0) < 2) {
    header('Location: index.php?msg=sin_permiso');
    exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: arriendos.php');
    exit;
}

$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
$propietario_id = (int)$_POST['propietario_id'];
$apoderado_id = (int)$_POST['apoderado_id'];
$arrendatario_id = (int)$_POST['arrendatario_id'];
$padron = ''; // Padrón se incluye en la descripción de la finca
$descripcion_finca = mysqli_real_escape_string($conexion, trim($_POST['descripcion_finca'] ?? ''));
$fecha_cobro_1 = !empty($_POST['fecha_cobro_1']) ? mysqli_real_escape_string($conexion, $_POST['fecha_cobro_1']) : 'NULL';
$fecha_cobro_2 = !empty($_POST['fecha_cobro_2']) ? mysqli_real_escape_string($conexion, $_POST['fecha_cobro_2']) : 'NULL';
$kilos_fecha_1 = isset($_POST['kilos_fecha_1']) && $_POST['kilos_fecha_1'] !== '' ? (float)$_POST['kilos_fecha_1'] : 'NULL';
$kilos_fecha_2 = isset($_POST['kilos_fecha_2']) && $_POST['kilos_fecha_2'] !== '' ? (float)$_POST['kilos_fecha_2'] : 'NULL';
$descontar_iva = isset($_POST['descontar_iva']) && $_POST['descontar_iva'] === '1' ? 1 : 0;
$monto_descuentos = isset($_POST['monto_descuentos']) ? (float)$_POST['monto_descuentos'] : 0;
$paga_comunal = isset($_POST['paga_comunal']) && $_POST['paga_comunal'] === '1' ? 1 : 0;
$paga_provincial = isset($_POST['paga_provincial']) && $_POST['paga_provincial'] === '1' ? 1 : 0;
$fecha_vencimiento_contrato = !empty($_POST['fecha_vencimiento_contrato']) ? mysqli_real_escape_string($conexion, $_POST['fecha_vencimiento_contrato']) : 'NULL';

if ($propietario_id <= 0 || $apoderado_id <= 0 || $arrendatario_id <= 0) {
    echo 'Faltan propietario, apoderado o arrendatario.';
    exit;
}

$f1 = $fecha_cobro_1 === 'NULL' ? 'NULL' : "'$fecha_cobro_1'";
$f2 = $fecha_cobro_2 === 'NULL' ? 'NULL' : "'$fecha_cobro_2'";
$fvenc = $fecha_vencimiento_contrato === 'NULL' ? 'NULL' : "'$fecha_vencimiento_contrato'";
$k1 = $kilos_fecha_1 === 'NULL' ? 'NULL' : $kilos_fecha_1;
$k2 = $kilos_fecha_2 === 'NULL' ? 'NULL' : $kilos_fecha_2;

if ($id > 0) {
    $sql = "UPDATE arriendos SET
        propietario_id = $propietario_id,
        apoderado_id = $apoderado_id,
        arrendatario_id = $arrendatario_id,
        padron = '$padron',
        descripcion_finca = '$descripcion_finca',
        fecha_cobro_1 = $f1,
        fecha_cobro_2 = $f2,
        kilos_fecha_1 = $k1,
        kilos_fecha_2 = $k2,
        descontar_iva = $descontar_iva,
        monto_descuentos = $monto_descuentos,
        paga_comunal = $paga_comunal,
        paga_provincial = $paga_provincial,
        fecha_vencimiento_contrato = $fvenc
        WHERE id = $id";
} else {
    $sql = "INSERT INTO arriendos (propietario_id, apoderado_id, arrendatario_id, padron, descripcion_finca, fecha_cobro_1, fecha_cobro_2, kilos_fecha_1, kilos_fecha_2, descontar_iva, monto_descuentos, paga_comunal, paga_provincial, fecha_vencimiento_contrato)
        VALUES ($propietario_id, $apoderado_id, $arrendatario_id, '$padron', '$descripcion_finca', $f1, $f2, $k1, $k2, $descontar_iva, $monto_descuentos, $paga_comunal, $paga_provincial, $fvenc)";
}

if (mysqli_query($conexion, $sql)) {
    echo 'OK';
} else {
    echo 'Error: ' . mysqli_error($conexion);
}
