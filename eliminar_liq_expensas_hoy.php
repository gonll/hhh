<?php
include 'db.php';
include 'verificar_sesion.php';

if (isset($_SESSION['acceso_nivel']) && $_SESSION['acceso_nivel'] < 2) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => false, 'msg' => 'Sin permiso.']);
    exit;
}

if (!isset($_GET['confirmar']) || $_GET['confirmar'] !== '1') {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'ok' => false,
        'msg' => 'Agregá ?confirmar=1 a la URL para ejecutar la eliminación.',
        'url' => 'eliminar_liq_expensas_hoy.php?confirmar=1'
    ]);
    exit;
}

$fecha_hoy = date('Y-m-d');

// Eliminar movimientos de liquidación de expensas del día de hoy:
// LIQ EXPENSAS, LIQ EXP, LIQ EXP EXT
$sql = "DELETE FROM cuentas 
    WHERE fecha = '$fecha_hoy' 
    AND (
        UPPER(TRIM(comprobante)) = 'LIQ EXPENSAS' 
        OR UPPER(TRIM(comprobante)) = 'LIQ EXP' 
        OR UPPER(TRIM(comprobante)) = 'LIQ EXP EXT'
    )";

if (mysqli_query($conexion, $sql)) {
    $eliminados = mysqli_affected_rows($conexion);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'ok' => true,
        'msg' => "Se eliminaron $eliminados movimientos de liquidación de expensas del $fecha_hoy.",
        'eliminados' => $eliminados,
        'fecha' => $fecha_hoy
    ]);
} else {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => false, 'msg' => 'Error: ' . mysqli_error($conexion)]);
}
