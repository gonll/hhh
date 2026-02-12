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
$porcentaje_raw = trim($_POST['porcentaje'] ?? '');
$porcentaje_raw = str_replace(',', '.', $porcentaje_raw);
$porcentaje = $porcentaje_raw !== '' ? (float)$porcentaje_raw : null;
$padron    = mysqli_real_escape_string($conexion, trim($_POST['padron'] ?? ''));
$detalle   = mysqli_real_escape_string($conexion, trim($_POST['detalle'] ?? ''));

if ($id <= 0 || $propiedad === '') {
    header('Location: propiedades.php');
    exit;
}

if ($padron !== '') {
    $existe = mysqli_query($conexion, "SELECT propiedad_id FROM propiedades WHERE padron = '$padron' AND propiedad_id != $id LIMIT 1");
    if ($existe && mysqli_num_rows($existe) > 0) {
        header('Location: editar_propiedad.php?id=' . $id . '&error=padron_duplicado');
        exit;
    }
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
    echo "Falta dato o corregir.";
}
exit;
