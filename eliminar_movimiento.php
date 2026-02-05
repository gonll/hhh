<?php
include 'db.php';
include 'verificar_sesion.php';
if (isset($_SESSION['acceso_nivel']) && $_SESSION['acceso_nivel'] < 2) {
    echo 'Sin permiso';
    exit;
}
$mid = $_GET['mid'];
$mid = (int)$mid;
$sql = "DELETE FROM cuentas WHERE movimiento_id = $mid";
if(mysqli_query($conexion, $sql)) {
    echo "OK";
}
?>