<?php
include 'db.php';
include 'verificar_sesion.php';
require_once __DIR__ . '/helpers_tenant_inmobiliaria.php';
require_once __DIR__ . '/config_clave_borrado.php';
tenant_inmob_asegurar_esquema($conexion);

$nivel = (int) ($_SESSION['acceso_nivel'] ?? 0);
$mid = (int) ($_GET['mid'] ?? $_POST['mid'] ?? 0);
if ($mid <= 0) {
    echo 'Sin permiso';
    exit;
}
// Nivel 0/1: solo borrado con POST y clave correcta (validación en servidor).
if ($nivel < 2) {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        echo 'Sin permiso';
        exit;
    }
    $clave_ingresada = trim($_POST['clave'] ?? '');
    if ($clave_ingresada === '' || $clave_ingresada !== obtener_clave_borrado($conexion)) {
        echo 'Sin permiso';
        exit;
    }
}
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