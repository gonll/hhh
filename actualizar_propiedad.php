<?php
include 'db.php';
include 'verificar_sesion.php';
if (isset($_SESSION['acceso_nivel']) && $_SESSION['acceso_nivel'] < 2) {
    header('Location: propiedades.php?msg=sin_permiso');
    exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['propiedad_id'])) {
    header('Location: propiedades.php');
    exit;
}

$id        = (int)$_POST['propiedad_id'];
$propiedad = mysqli_real_escape_string($conexion, trim($_POST['propiedad'] ?? ''));
$ciudad    = mysqli_real_escape_string($conexion, trim($_POST['ciudad'] ?? ''));
$consorcio = mysqli_real_escape_string($conexion, trim($_POST['consorcio'] ?? ''));
$porcentaje = isset($_POST['porcentaje']) && $_POST['porcentaje'] !== '' ? (float)$_POST['porcentaje'] : null;
$padron    = mysqli_real_escape_string($conexion, trim($_POST['padron'] ?? ''));
$detalle   = mysqli_real_escape_string($conexion, trim($_POST['detalle'] ?? ''));

if ($id <= 0 || $propiedad === '') {
    header('Location: propiedades.php');
    exit;
}

$porcentaje_sql = $porcentaje !== null ? $porcentaje : 'NULL';

$sql = "UPDATE propiedades SET 
        propiedad = '$propiedad', 
        ciudad = '$ciudad', 
        consorcio = '$consorcio', 
        porcentaje = $porcentaje_sql,
        padron = '$padron', 
        detalle = '$detalle' 
        WHERE propiedad_id = $id";

if (mysqli_query($conexion, $sql)) {
    header('Location: propiedades.php?ok=1');
} else {
    echo "Error al actualizar: " . mysqli_error($conexion);
}
exit;
