<?php
/**
 * Traspasa saldo de cobro de una operación a otra (mismo comprador / usuario en cuentas).
 * Un asiento negativo en OP origen y otro positivo en OP destino. Sin movimiento de caja.
 */
header('Content-Type: application/json; charset=utf-8');

include 'db.php';
include 'verificar_sesion.php';
require_once __DIR__ . '/helpers_tenant_inmobiliaria.php';
tenant_inmob_asegurar_esquema($conexion);

if (!isset($_SESSION['acceso_nivel']) || (int) $_SESSION['acceso_nivel'] < 2) {
    echo json_encode(['ok' => false, 'error' => 'Sin permiso']);
    exit;
}

$usuario_id = (int) ($_POST['usuario_id'] ?? 0);
$op_origen = (int) ($_POST['operacion_origen'] ?? 0);
$op_dest = (int) ($_POST['operacion_destino'] ?? 0);
$monto = isset($_POST['monto']) ? (float) str_replace(',', '.', (string) $_POST['monto']) : 0.0;
$fecha_raw = trim((string) ($_POST['fecha'] ?? ''));

if ($usuario_id < 1 || !tenant_inmob_usuario_id_visible($conexion, $usuario_id)) {
    echo json_encode(['ok' => false, 'error' => 'Usuario no válido o sin permiso.']);
    exit;
}

if ($op_origen < 1 || $op_dest < 1) {
    echo json_encode(['ok' => false, 'error' => 'Indique operación origen y destino.']);
    exit;
}
if ($op_origen === $op_dest) {
    echo json_encode(['ok' => false, 'error' => 'La operación destino debe ser distinta a la origen.']);
    exit;
}
if ($monto <= 0 || !is_finite($monto)) {
    echo json_encode(['ok' => false, 'error' => 'Indique un monto mayor a cero.']);
    exit;
}

if ($fecha_raw === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha_raw)) {
    $fecha_raw = date('Y-m-d');
}
$d = DateTime::createFromFormat('Y-m-d', $fecha_raw);
if (!$d || $d->format('Y-m-d') !== $fecha_raw) {
    echo json_encode(['ok' => false, 'error' => 'Fecha no válida.']);
    exit;
}
// La operación destino debe existir en ventas de azúcar para este comprador
$qchk = "SELECT 1 FROM stock 
         WHERE operacion = $op_dest AND vendida_a_id = $usuario_id 
         LIMIT 1";
$rch = mysqli_query($conexion, $qchk);
if (!$rch || mysqli_num_rows($rch) === 0) {
    echo json_encode(['ok' => false, 'error' => 'No hay venta del comprador en la operación destino (N° ' . $op_dest . ').']);
    exit;
}

$ref_o = 'OP N° ' . $op_origen;
$ref_d = 'OP N° ' . $op_dest;
$concepto_o = 'TRASPASO A OP N° ' . $op_dest;
$concepto_d = 'TRASPASO DESDE OP N° ' . $op_origen;
$compro_str = 'TRASP-OP';

// Salida de origen: negativo; entrada en destino: positivo (mismo criterio que cobros en cuenta)
$m_neg = -round($monto, 2);
$m_pos = round($monto, 2);

mysqli_begin_transaction($conexion);
$ok = true;
$err = '';

$stmt = mysqli_prepare(
    $conexion,
    'INSERT INTO cuentas (usuario_id, fecha, concepto, comprobante, referencia, monto) VALUES (?, ?, ?, ?, ?, ?)'
);
if (!$stmt) {
    $err = mysqli_error($conexion);
    $ok = false;
} else {
    mysqli_stmt_bind_param(
        $stmt,
        'issssd',
        $usuario_id,
        $fecha_raw,
        $concepto_o,
        $compro_str,
        $ref_o,
        $m_neg
    );
    if (!mysqli_stmt_execute($stmt)) {
        $err = mysqli_error($conexion);
        $ok = false;
    }
    mysqli_stmt_close($stmt);
}
if ($ok) {
    $stmt2 = mysqli_prepare(
        $conexion,
        'INSERT INTO cuentas (usuario_id, fecha, concepto, comprobante, referencia, monto) VALUES (?, ?, ?, ?, ?, ?)'
    );
    if (!$stmt2) {
        $err = mysqli_error($conexion);
        $ok = false;
    } else {
        mysqli_stmt_bind_param(
            $stmt2,
            'issssd',
            $usuario_id,
            $fecha_raw,
            $concepto_d,
            $compro_str,
            $ref_d,
            $m_pos
        );
        if (!mysqli_stmt_execute($stmt2)) {
            $err = mysqli_error($conexion);
            $ok = false;
        }
        mysqli_stmt_close($stmt2);
    }
}
if ($ok) {
    mysqli_commit($conexion);
    echo json_encode(['ok' => true]);
} else {
    mysqli_rollback($conexion);
    echo json_encode(['ok' => false, 'error' => $err !== '' ? $err : 'No se pudo grabar.']);
}
