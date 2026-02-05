<?php
include 'db.php';

$apellido  = strtoupper(trim($_POST['apellido']));
$dni       = $_POST['dni'];
$cuit      = $_POST['cuit'];
$domicilio = strtoupper($_POST['domicilio']);
$email     = strtolower(trim($_POST['email'] ?? ''));
$celular   = trim($_POST['celular'] ?? '');

$sql = "INSERT INTO usuarios (apellido, dni, cuit, domicilio, email, celular) 
        VALUES ('$apellido', '$dni', '$cuit', '$domicilio', '$email', '$celular')";

if (mysqli_query($conexion, $sql)) {
    header("Location: index.php");
} else {
    echo "Error: " . mysqli_error($conexion);
}
?>
