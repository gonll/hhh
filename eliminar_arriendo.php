<?php
include 'db.php';
include 'verificar_sesion.php';

if (($_SESSION['acceso_nivel'] ?? 0) < 2) {
    echo 'Sin permiso';
    exit;
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : (isset($_POST['id']) ? (int)$_POST['id'] : 0);
if ($id <= 0) {
    echo 'ID inválido';
    exit;
}

$sql = "DELETE FROM arriendos WHERE id = $id";
if (mysqli_query($conexion, $sql)) {
    echo 'OK';
} else {
    echo 'Error: ' . mysqli_error($conexion);
}
