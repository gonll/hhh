<?php
include 'db.php';
include 'verificar_sesion.php';

if (($_SESSION['acceso_nivel'] ?? 0) < 2) {
    echo 'Sin permiso';
    exit;
}
$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
$valor = isset($_POST['porcentaje_otros']) ? round((float)str_replace(',', '.', $_POST['porcentaje_otros']), 2) : 0;
if ($id <= 0) {
    echo 'ID inválido';
    exit;
}
$valor = max(0, min(100, $valor));
$sql = "UPDATE arriendos SET porcentaje_otros = $valor WHERE id = $id";
if (mysqli_query($conexion, $sql)) {
    echo 'OK';
} else {
    echo 'Error: ' . mysqli_error($conexion);
}
