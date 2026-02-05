<?php
include 'db.php';
$mid = $_GET['mid'];
$mid = (int)$mid;
$sql = "DELETE FROM cuentas WHERE movimiento_id = $mid";
if(mysqli_query($conexion, $sql)) {
    echo "OK";
}
?>