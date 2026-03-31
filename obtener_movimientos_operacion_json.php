<?php
/**
 * Misma lógica que obtener_movimientos_operacion.php — respuesta JSON para PDF en el navegador (jsPDF).
 */
header('Content-Type: application/json; charset=utf-8');
include 'db.php';
include 'verificar_sesion.php';

$operacion = isset($_GET['operacion']) ? (int) $_GET['operacion'] : 0;
if ($operacion < 1) {
    echo json_encode(['ok' => false, 'error' => 'operación inválida']);
    exit;
}

$ref_buscar_esc = mysqli_real_escape_string($conexion, "OP N° $operacion");

$sql = "SELECT c.*, u.apellido AS usuario_nombre 
        FROM cuentas c 
        LEFT JOIN usuarios u ON u.id = c.usuario_id 
        WHERE c.referencia = '$ref_buscar_esc' 
        ORDER BY c.fecha DESC, c.movimiento_id DESC";
$res = mysqli_query($conexion, $sql);

if ($res === false) {
    echo json_encode(['ok' => false, 'error' => 'Error en consulta: ' . mysqli_error($conexion)]);
    exit;
}

$movimientos = [];
$saldo_total_operacion = 0;
while ($m = mysqli_fetch_assoc($res)) {
    $movimientos[] = $m;
    $saldo_total_operacion += (float) $m['monto'];
}

usort($movimientos, function ($a, $b) {
    $fechaA = strtotime($a['fecha']);
    $fechaB = strtotime($b['fecha']);
    if ($fechaA === $fechaB) {
        return (int) $a['movimiento_id'] - (int) $b['movimiento_id'];
    }
    return $fechaA - $fechaB;
});

$up = function ($s) {
    return function_exists('mb_strtoupper') ? mb_strtoupper((string) $s, 'UTF-8') : strtoupper((string) $s);
};

$filas = [];
$saldo_acumulado = 0;
foreach ($movimientos as $m) {
    $monto = (float) $m['monto'];
    $saldo_acumulado += $monto;
    $filas[] = [
        'fecha' => date('d/m/Y', strtotime($m['fecha'])),
        'concepto' => $up($m['concepto'] ?? ''),
        'comprobante' => $up($m['comprobante'] ?? ''),
        'referencia' => $up($m['referencia'] ?? ''),
        'usuario' => trim($m['usuario_nombre'] ?? '') ?: 'Sin usuario',
        'monto' => $monto,
        'saldo_acumulado' => $saldo_acumulado,
    ];
}

echo json_encode([
    'ok' => true,
    'operacion' => $operacion,
    'total_operacion' => $saldo_total_operacion,
    'movimientos' => $filas,
]);
