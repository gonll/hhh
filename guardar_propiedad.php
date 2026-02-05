<?php
include 'db.php';
include 'verificar_sesion.php';
if (isset($_SESSION['acceso_nivel']) && $_SESSION['acceso_nivel'] < 2) {
    header('Location: propiedades.php?msg=sin_permiso');
    exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['propiedad'])) {
    header('Location: propiedades.php');
    exit;
}

$propietario_id = (int)($_POST['propietario_id'] ?? 0);
$propiedad      = mysqli_real_escape_string($conexion, trim($_POST['propiedad'] ?? ''));
$ciudad         = mysqli_real_escape_string($conexion, trim($_POST['ciudad'] ?? ''));
$consorcio      = mysqli_real_escape_string($conexion, trim($_POST['consorcio'] ?? ''));
$porcentaje     = isset($_POST['porcentaje']) && $_POST['porcentaje'] !== '' ? (float)$_POST['porcentaje'] : null;
$padron         = mysqli_real_escape_string($conexion, trim($_POST['padron'] ?? ''));
$detalle        = mysqli_real_escape_string($conexion, trim($_POST['detalle'] ?? ''));

if ($propiedad === '' || $propietario_id <= 0) {
    header('Location: nueva_propiedad.php?error=1');
    exit;
}

$porcentaje_sql = $porcentaje !== null ? $porcentaje : 'NULL';
$ciudad_sql     = $ciudad === '' ? 'NULL' : "'$ciudad'";

$sql = "INSERT INTO propiedades (propietario_id, propiedad, ciudad, consorcio, porcentaje, padron, detalle) 
        VALUES ($propietario_id, '$propiedad', $ciudad_sql, '$consorcio', $porcentaje_sql, '$padron', '$detalle')";

if (mysqli_query($conexion, $sql)) {
    header('Location: propiedades.php?ok=1');
    exit;
}
echo "Error al guardar: " . mysqli_error($conexion);
exit;
