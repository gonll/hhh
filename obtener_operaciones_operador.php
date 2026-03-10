<?php
include 'db.php';
include 'verificar_sesion.php';

if (!isset($_GET['operador_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['ok' => false, 'error' => 'operador_id requerido']);
    exit;
}

$operador_id = (int)$_GET['operador_id'];
if ($operador_id < 1) {
    header('Content-Type: application/json');
    echo json_encode(['ok' => false, 'error' => 'operador_id inválido']);
    exit;
}

// Nombre del operador
$res_op = mysqli_query($conexion, "SELECT apellido FROM usuarios WHERE id = $operador_id LIMIT 1");
$operador_nombre = ($r = mysqli_fetch_assoc($res_op)) ? trim($r['apellido']) : 'Operador #' . $operador_id;

// Operaciones distintas del stock donde operador_id = X (una fila por operación)
$sql_ops = "SELECT s.operacion, MAX(s.vendida_a_id) AS vendida_a_id
            FROM stock s
            WHERE s.operador_id = $operador_id AND s.operacion IS NOT NULL AND s.operacion > 0
            GROUP BY s.operacion
            ORDER BY s.operacion DESC";
$res_ops = mysqli_query($conexion, $sql_ops);

$operaciones = [];
while ($row = mysqli_fetch_assoc($res_ops)) {
    $op = (int)$row['operacion'];
    $ref_esc = mysqli_real_escape_string($conexion, "OP N° $op");
    
    // Saldo de la operación (suma de movimientos en cuentas)
    $res_saldo = mysqli_query($conexion, "SELECT COALESCE(SUM(monto), 0) AS saldo FROM cuentas WHERE referencia = '$ref_esc'");
    $saldo = ($r = mysqli_fetch_assoc($res_saldo)) ? (float)$r['saldo'] : 0;
    
    // Vendida a (comprador)
    $vendida_a_id = (int)($row['vendida_a_id'] ?? 0);
    $vendida_a = '';
    if ($vendida_a_id > 0) {
        $res_v = mysqli_query($conexion, "SELECT apellido FROM usuarios WHERE id = $vendida_a_id LIMIT 1");
        $vendida_a = ($rv = mysqli_fetch_assoc($res_v)) ? trim($rv['apellido']) : '';
    }
    
    $operaciones[] = [
        'operacion' => $op,
        'saldo' => $saldo,
        'vendida_a' => $vendida_a,
        'vendida_a_id' => $vendida_a_id
    ];
}

header('Content-Type: application/json; charset=utf-8');
echo json_encode([
    'ok' => true,
    'operador_nombre' => $operador_nombre,
    'operador_id' => $operador_id,
    'operaciones' => $operaciones
]);
