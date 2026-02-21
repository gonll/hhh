<?php
include 'db.php';
include 'verificar_sesion.php';

if (($_SESSION['acceso_nivel'] ?? 0) < 2) {
    echo 'Sin permiso';
    exit;
}
$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
$monto = isset($_POST['monto_descuentos']) ? (float)str_replace(',', '.', $_POST['monto_descuentos']) : 0;
if ($id <= 0) {
    echo 'ID inválido';
    exit;
}
$monto = max(0, $monto);
$sql = "UPDATE arriendos SET monto_descuentos = $monto WHERE id = $id";
if (mysqli_query($conexion, $sql)) {
    echo 'OK';
} else {
    echo 'Error: ' . mysqli_error($conexion);
}
