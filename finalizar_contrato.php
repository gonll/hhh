<?php
include 'db.php';
include 'verificar_sesion.php';
header('Content-Type: text/plain; charset=utf-8');
if (isset($_SESSION['acceso_nivel']) && $_SESSION['acceso_nivel'] < 2) {
    echo 'Sin permiso';
    exit;
}
$id_prop = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id_prop <= 0) {
    echo 'ID inválido';
    exit;
}

mysqli_begin_transaction($conexion);

$ok = true;
$sql1 = "UPDATE alquileres SET estado = 'FINALIZADO' WHERE propiedad_id = $id_prop AND estado = 'VIGENTE'";
if (!mysqli_query($conexion, $sql1)) {
    $ok = false;
}

if ($ok) {
    $sql2 = "UPDATE propiedades SET alquiler = 0 WHERE propiedad_id = $id_prop";
    if (!mysqli_query($conexion, $sql2)) {
        $ok = false;
    }
}

if ($ok) {
    mysqli_commit($conexion);
    echo "OK";
} else {
    mysqli_rollback($conexion);
    echo "Error: " . mysqli_error($conexion);
}
?>