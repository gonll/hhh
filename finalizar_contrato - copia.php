<?php
include 'db.php';
$id_prop = (int)$_GET['id'];

mysqli_begin_transaction($conexion);

try {
    // A. Actualizamos el contrato a 'FINALIZADO'
    $sql1 = "UPDATE alquileres SET estado = 'FINALIZADO' WHERE propiedad_id = $id_prop AND estado = 'VIGENTE'";
    mysqli_query($conexion, $sql1);

    // B. Devolvemos la propiedad a estado LIBRE (alquiler = 0)
    $sql2 = "UPDATE propiedades SET alquiler = 0 WHERE propiedad_id = $id_prop";
    mysqli_query($conexion, $sql2);

    mysqli_commit($conexion);
    echo "OK";
} catch (Exception $e) {
    mysqli_rollback($conexion);
    echo "Error";
}
?>