<?php
include 'db.php';
include 'verificar_sesion.php';
require_once __DIR__ . '/helpers_tenant_inmobiliaria.php';
tenant_inmob_asegurar_esquema($conexion);
include __DIR__ . '/crear_tabla_cuentas_arriendo.php';
include __DIR__ . '/cargar_arriendos_cuentas.php';

header('Content-Type: application/json; charset=utf-8');

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($id <= 0 || !tenant_inmob_usuario_id_visible($conexion, $id)) {
    echo json_encode(['ok' => false, 'msg' => 'Sin permiso']);
    exit;
}

$res_total = mysqli_query($conexion, 'SELECT COALESCE(SUM(monto), 0) AS total FROM cuentas WHERE usuario_id = ' . $id);
$total_cuenta = ($res_total && $r = mysqli_fetch_assoc($res_total)) ? (float) $r['total'] : 0.0;

$ids = [];
$r_ids = mysqli_query(
    $conexion,
    'SELECT movimiento_id FROM cuentas WHERE usuario_id = ' . $id . ' ORDER BY fecha DESC, movimiento_id DESC LIMIT 10'
);
if (!$r_ids) {
    echo json_encode([
        'ok' => false,
        'msg' => 'Error consulta: ' . mysqli_error($conexion),
    ], JSON_UNESCAPED_UNICODE);
    exit;
}
while ($row = mysqli_fetch_assoc($r_ids)) {
    $ids[] = (int) $row['movimiento_id'];
}
$filas = [];
if (count($ids) > 0) {
    $inList = implode(',', array_map('intval', $ids));
    $res = mysqli_query(
        $conexion,
        'SELECT * FROM cuentas WHERE usuario_id = ' . $id . ' AND movimiento_id IN (' . $inList . ') ORDER BY fecha ASC, movimiento_id ASC'
    );
    if (!$res) {
        echo json_encode([
            'ok' => false,
            'msg' => 'Error consulta: ' . mysqli_error($conexion),
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
    while ($m = mysqli_fetch_assoc($res)) {
        $filas[] = $m;
    }
}

if (count($filas) === 0) {
    echo json_encode(['ok' => true, 'movimientos' => []]);
    exit;
}

$primera = $filas[0];
$pf_esc = mysqli_real_escape_string($conexion, $primera['fecha']);
$pid = (int) $primera['movimiento_id'];
$monto_primera = (float) ($primera['monto'] ?? 0);
$r_sum = mysqli_query(
    $conexion,
    "SELECT COALESCE(SUM(monto), 0) AS s FROM cuentas WHERE usuario_id = $id AND (fecha > '$pf_esc' OR (fecha = '$pf_esc' AND movimiento_id > $pid))"
);
$suma_despues = ($r_sum && $row = mysqli_fetch_assoc($r_sum)) ? (float) $row['s'] : 0.0;
$suma_antes = $total_cuenta - $suma_despues - $monto_primera;

$saldo = $suma_antes;
$out = [];
foreach ($filas as $m) {
    $saldo += (float) ($m['monto'] ?? 0);
    $concepto = (string) ($m['concepto'] ?? '');
    if (function_exists('mb_strlen') && function_exists('mb_substr')) {
        if (mb_strlen($concepto, 'UTF-8') > 40) {
            $concepto = mb_substr($concepto, 0, 40, 'UTF-8') . '…';
        }
    } elseif (strlen($concepto) > 40) {
        $concepto = substr($concepto, 0, 40) . '...';
    }
    $out[] = [
        'fecha' => date('d/m/Y', strtotime($m['fecha'])),
        'concepto' => $concepto,
        'monto' => (float) ($m['monto'] ?? 0),
        'saldo' => $saldo,
    ];
}

echo json_encode(['ok' => true, 'movimientos' => $out], JSON_UNESCAPED_UNICODE);
