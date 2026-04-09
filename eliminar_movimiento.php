<?php
include 'db.php';
include 'verificar_sesion.php';
require_once __DIR__ . '/helpers_tenant_inmobiliaria.php';
tenant_inmob_asegurar_esquema($conexion);
if (isset($_SESSION['acceso_nivel']) && $_SESSION['acceso_nivel'] < 2) {
    echo 'Sin permiso';
    exit;
}
$mid = $_GET['mid'];
$mid = (int)$mid;
$r0 = mysqli_query($conexion, "SELECT usuario_id FROM cuentas WHERE movimiento_id = $mid LIMIT 1");
if (!$r0 || !($row0 = mysqli_fetch_assoc($r0))) {
    exit;
}
if (!tenant_inmob_usuario_id_visible($conexion, (int)$row0['usuario_id'])) {
    echo 'Sin permiso';
    exit;
}
$sql = "DELETE FROM cuentas WHERE movimiento_id = $mid";
if(mysqli_query($conexion, $sql)) {
    echo "OK";
}
?>