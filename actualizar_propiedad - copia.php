<?php
include 'db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['propiedad_id'])) {
    header('Location: propiedades.php');
    exit;
}

$id        = (int)$_POST['propiedad_id'];
$propiedad = mysqli_real_escape_string($conexion, trim($_POST['propiedad'] ?? ''));
$consorcio = mysqli_real_escape_string($conexion, trim($_POST['consorcio'] ?? ''));
$padron    = mysqli_real_escape_string($conexion, trim($_POST['padron'] ?? ''));
$detalle   = mysqli_real_escape_string($conexion, trim($_POST['detalle'] ?? ''));

if ($id <= 0 || $propiedad === '') {
    header('Location: propiedades.php');
    exit;
}

$sql = "UPDATE propiedades SET 
        propiedad = '$propiedad', 
        consorcio = '$consorcio', 
        padron = '$padron', 
        detalle = '$detalle' 
        WHERE propiedad_id = $id";

if (mysqli_query($conexion, $sql)) {
    header('Location: propiedades.php?ok=1');
} else {
    echo "Error al actualizar: " . mysqli_error($conexion);
}
exit;
