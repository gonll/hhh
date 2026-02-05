<?php
$conexion = mysqli_connect("localhost", "root", "", "curso_java_php");

$id = $_POST['id'];
$apellido = strtoupper($_POST['apellido']);
$dni = $_POST['dni'];
$cuit = $_POST['cuit'];
$domicilio = strtoupper($_POST['domicilio']);

$sql = "UPDATE usuarios SET 
        apellido='$apellido', 
        dni='$dni', 
        cuit='$cuit', 
        domicilio='$domicilio' 
        WHERE id=$id";

if (mysqli_query($conexion, $sql)) {
    header("Location: index.php");
} else {
    echo "Error al actualizar: " . mysqli_error($conexion);
}
mysqli_close($conexion);
?>

