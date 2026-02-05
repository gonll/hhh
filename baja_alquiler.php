<?php
include 'db.php';
include 'verificar_sesion.php';
if (isset($_SESSION['acceso_nivel']) && $_SESSION['acceso_nivel'] < 2) {
    echo 'Sin permiso';
    exit;
}
if (isset($_GET['id'])) {
    $prop_id = (int)$_GET['id'];

    // Iniciamos transacción
    mysqli_begin_transaction($conexion);

    try {
        // A. Ponemos el campo alquiler en NULL en la tabla propiedades
        $sql_prop = "UPDATE propiedades SET alquiler = NULL WHERE propiedad_id = $prop_id";
        mysqli_query($conexion, $sql_prop);

        // B. Ponemos el estado en BAJA en la tabla alquileres para esa propiedad
        // (Afecta al contrato VIGENTE más reciente de esa propiedad)
        $sql_alq = "UPDATE alquileres SET estado = 'BAJA' 
                    WHERE propiedad_id = $prop_id AND estado = 'VIGENTE'";
        mysqli_query($conexion, $sql_alq);

        mysqli_commit($conexion);
        echo "OK";
    } catch (Exception $e) {
        mysqli_rollback($conexion);
        echo "Error";
    }
}
?>