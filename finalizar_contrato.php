<?php
ob_start();
if (isset($_GET['debug']) && $_GET['debug'] === '1') {
    ini_set('display_errors', 1);
    error_reporting(E_ALL);
}
ob_end_clean();
header('Content-Type: text/plain; charset=utf-8');
include 'db.php';
include 'verificar_sesion.php';
if (isset($_SESSION['acceso_nivel']) && $_SESSION['acceso_nivel'] < 2) {
    echo 'Sin permiso';
    exit;
}
$id_prop = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id_prop <= 0) {
    echo 'ID inválido';
    exit;
}

require_once __DIR__ . '/helpers_tenant_inmobiliaria.php';
tenant_inmob_asegurar_esquema($conexion);
if (!tenant_inmob_propiedad_id_visible($conexion, $id_prop)) {
    echo 'Sin permiso';
    exit;
}

$sql1 = "UPDATE alquileres SET estado = 'BAJA' WHERE propiedad_id = $id_prop AND estado = 'VIGENTE'";
if (!mysqli_query($conexion, $sql1)) {
    echo 'Error alquileres: ' . mysqli_error($conexion);
    exit;
}

$sql2 = "UPDATE propiedades SET alquiler = 0 WHERE propiedad_id = $id_prop";
@mysqli_query($conexion, $sql2);

echo "OK";
?>