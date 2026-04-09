<?php
include 'db.php';
include 'verificar_sesion.php';
require_once __DIR__ . '/helpers_tenant_inmobiliaria.php';
tenant_inmob_asegurar_esquema($conexion);

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    header('Content-Type: application/json');
    echo json_encode(['saldo' => 0]);
    exit;
}

if (!tenant_inmob_usuario_id_visible($conexion, $id)) {
    header('Content-Type: application/json');
    echo json_encode(['saldo' => 0]);
    exit;
}

$res = mysqli_query($conexion, "SELECT COALESCE(SUM(monto), 0) AS saldo FROM cuentas WHERE usuario_id = $id");
$row = $res ? mysqli_fetch_assoc($res) : null;
$saldo = $row ? (float)$row['saldo'] : 0;

header('Content-Type: application/json');
echo json_encode(['saldo' => $saldo]);
?>
