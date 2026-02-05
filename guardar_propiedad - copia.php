<?php
include 'db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['propiedad'])) {
    header('Location: propiedades.php');
    exit;
}

$propietario_id = (int)($_POST['propietario_id'] ?? 0);
$propiedad      = mysqli_real_escape_string($conexion, trim($_POST['propiedad'] ?? ''));
$consorcio      = mysqli_real_escape_string($conexion, trim($_POST['consorcio'] ?? ''));
$padron         = mysqli_real_escape_string($conexion, trim($_POST['padron'] ?? ''));
$detalle        = mysqli_real_escape_string($conexion, trim($_POST['detalle'] ?? ''));

if ($propiedad === '' || $propietario_id <= 0) {
    header('Location: nueva_propiedad.php?error=1');
    exit;
}

$sql = "INSERT INTO propiedades (propietario_id, propiedad, consorcio, padron, detalle) 
        VALUES ($propietario_id, '$propiedad', '$consorcio', '$padron', '$detalle')";

if (mysqli_query($conexion, $sql)) {
    header('Location: propiedades.php?ok=1');
    exit;
}
echo "Error al guardar: " . mysqli_error($conexion);
exit;
