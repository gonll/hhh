<?php
include 'db.php';
include 'verificar_sesion.php';
if (isset($_SESSION['acceso_nivel']) && $_SESSION['acceso_nivel'] < 2) {
    echo 'Sin permiso';
    exit;
}
if (!isset($_GET['id'])) {
    echo "Error: ID no proporcionado.";
    exit;
}

$id = (int)$_GET['id'];

if ($id <= 0) {
    echo "Error: ID inválido.";
    exit;
}

// Verificar si la propiedad tiene un alquiler vigente
$check = mysqli_query($conexion, "SELECT * FROM alquileres WHERE propiedad_id = $id AND estado = 'VIGENTE'");
if (mysqli_num_rows($check) > 0) {
    echo "Error: No se puede eliminar una propiedad con contrato vigente.";
    exit;
}

$sql = "DELETE FROM propiedades WHERE propiedad_id = $id";

if (mysqli_query($conexion, $sql)) {
    echo "OK";
} else {
    echo "Error al eliminar: " . mysqli_error($conexion);
}
?>
