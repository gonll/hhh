<?php
$conexion = mysqli_connect("localhost", "root", "", "curso_java_php");
$id = (int)$_GET['id'];

$sql = "DELETE FROM usuarios WHERE id = $id";

if (mysqli_query($conexion, $sql)) {
    header("Location: index.php");
} else {
    echo "Error al eliminar: " . mysqli_error($conexion);
}
mysqli_close($conexion);
?>

