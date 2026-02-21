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
// Fechas de cobro: solo día y mes (se aplican cada año hasta fin del contrato). Se guarda con año 2000 como referencia.
$dia1 = isset($_POST['dia_cobro_1']) ? (int)$_POST['dia_cobro_1'] : 0;
$mes1 = isset($_POST['mes_cobro_1']) ? (int)$_POST['mes_cobro_1'] : 0;
$dia2 = isset($_POST['dia_cobro_2']) ? (int)$_POST['dia_cobro_2'] : 0;
$mes2 = isset($_POST['mes_cobro_2']) ? (int)$_POST['mes_cobro_2'] : 0;
$fecha_cobro_1 = ($dia1 >= 1 && $dia1 <= 31 && $mes1 >= 1 && $mes1 <= 12) ? sprintf('2000-%02d-%02d', $mes1, $dia1) : 'NULL';
$fecha_cobro_2 = ($dia2 >= 1 && $dia2 <= 31 && $mes2 >= 1 && $mes2 <= 12) ? sprintf('2000-%02d-%02d', $mes2, $dia2) : 'NULL';
$kilos_fecha_1 = isset($_POST['kilos_fecha_1']) && $_POST['kilos_fecha_1'] !== '' ? (int)$_POST['kilos_fecha_1'] : 'NULL';
$kilos_fecha_2 = isset($_POST['kilos_fecha_2']) && $_POST['kilos_fecha_2'] !== '' ? (int)$_POST['kilos_fecha_2'] : 'NULL';
$iva_porcentaje = isset($_POST['iva_porcentaje']) && $_POST['iva_porcentaje'] !== '' ? round((float)$_POST['iva_porcentaje'], 2) : 21;
$porcentaje_otros = isset($_POST['porcentaje_otros']) && $_POST['porcentaje_otros'] !== '' ? round((float)$_POST['porcentaje_otros'], 2) : 'NULL';
$paga_comunal = isset($_POST['paga_comunal']) && $_POST['paga_comunal'] === '1' ? 1 : 0;
$paga_provincial = isset($_POST['paga_provincial']) && $_POST['paga_provincial'] === '1' ? 1 : 0;
$fecha_vencimiento_contrato = !empty($_POST['fecha_vencimiento_contrato']) ? mysqli_real_escape_string($conexion, $_POST['fecha_vencimiento_contrato']) : 'NULL';

if ($propietario_id <= 0 || $apoderado_id <= 0 || $arrendatario_id <= 0) {
    echo 'Faltan propietario, apoderado o arrendatario.';
    exit;
}
if (trim($descripcion_finca) === '') {
    echo 'Finca (descripción) es obligatoria.';
    exit;
}
if ($dia1 < 1 || $dia1 > 31 || $mes1 < 1 || $mes1 > 12) {
    echo 'Fecha cobro 1 es obligatoria (día y mes).';
    exit;
}
if (!isset($_POST['kilos_fecha_1']) || $_POST['kilos_fecha_1'] === '' || (int)$_POST['kilos_fecha_1'] < 0) {
    echo 'Kilos 1 es obligatorio.';
    exit;
}
if ($fecha_vencimiento_contrato === 'NULL' || empty($_POST['fecha_vencimiento_contrato'])) {
    echo 'Vencimiento del contrato es obligatorio.';
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
        iva_porcentaje = $iva_porcentaje,
        paga_comunal = $paga_comunal,
        paga_provincial = $paga_provincial,
        porcentaje_otros = " . ($porcentaje_otros === 'NULL' ? 'NULL' : $porcentaje_otros) . ",
        fecha_vencimiento_contrato = $fvenc
        WHERE id = $id";
} else {
    $pct_otros = $porcentaje_otros === 'NULL' ? 'NULL' : $porcentaje_otros;
    $sql = "INSERT INTO arriendos (propietario_id, apoderado_id, arrendatario_id, padron, descripcion_finca, fecha_cobro_1, fecha_cobro_2, kilos_fecha_1, kilos_fecha_2, iva_porcentaje, paga_comunal, paga_provincial, porcentaje_otros, fecha_vencimiento_contrato)
        VALUES ($propietario_id, $apoderado_id, $arrendatario_id, '$padron', '$descripcion_finca', $f1, $f2, $k1, $k2, $iva_porcentaje, $paga_comunal, $paga_provincial, $pct_otros, $fvenc)";
}

if (mysqli_query($conexion, $sql)) {
    header('Location: arriendos.php');
    exit;
} else {
    $err = mysqli_error($conexion);
    echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Error</title></head><body style="font-family:sans-serif;padding:20px;">';
    echo '<p style="color:#c00;">Error al guardar: ' . htmlspecialchars($err) . '</p>';
    echo '<p><a href="arriendo_form.php' . ($id > 0 ? '?id=' . $id : '') . '">Volver al formulario</a> | <a href="arriendos.php">Ir al cronograma</a></p></body></html>';
}
