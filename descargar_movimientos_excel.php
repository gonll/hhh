<?php
include 'db.php';
include 'verificar_sesion.php';

if (!isset($_GET['id'])) {
    die('Falta el parámetro id.');
}

$id = (int)$_GET['id'];

$res_u = mysqli_query($conexion, "SELECT apellido FROM usuarios WHERE id = $id LIMIT 1");
$nombre = ($r = mysqli_fetch_assoc($res_u)) ? trim($r['apellido']) : 'Usuario_' . $id;

$res_total = mysqli_query($conexion, "SELECT COALESCE(SUM(monto), 0) AS total FROM cuentas WHERE usuario_id = $id");
$total_cuenta = ($r = mysqli_fetch_assoc($res_total)) ? (float)$r['total'] : 0;

// Todos los movimientos ordenados cronológicamente
$sql = "SELECT * FROM cuentas WHERE usuario_id = $id ORDER BY fecha ASC, movimiento_id ASC";
$res = mysqli_query($conexion, $sql);

$filas = [];
while ($m = mysqli_fetch_array($res)) {
    $filas[] = $m;
}

$suma_antes = 0;
if (count($filas) > 0) {
    $primera = $filas[0];
    $pf_esc = mysqli_real_escape_string($conexion, $primera['fecha']);
    $pid = (int)$primera['movimiento_id'];
    $monto_primera = (float)($primera['monto'] ?? 0);
    $r_sum = mysqli_query($conexion, "SELECT COALESCE(SUM(monto), 0) AS s FROM cuentas WHERE usuario_id = $id AND (fecha > '$pf_esc' OR (fecha = '$pf_esc' AND movimiento_id > $pid))");
    $suma_despues = ($r_sum && $row = mysqli_fetch_assoc($r_sum)) ? (float)$row['s'] : 0;
    $suma_antes = $total_cuenta - $suma_despues - $monto_primera;
}

$saldo = $suma_antes;

function escaparCsv($texto) {
    $texto = str_replace('"', '""', (string)$texto);
    if (strpos($texto, ';') !== false || strpos($texto, ',') !== false || strpos($texto, '"') !== false || strpos($texto, "\n") !== false) {
        return '"' . $texto . '"';
    }
    return $texto;
}

$nombre_safe = preg_replace('/[^a-zA-Z0-9_-]/', '_', $nombre);
$fecha_archivo = date('Y-m-d_His');
$nombre_archivo = "movimientos_{$nombre_safe}_{$fecha_archivo}.csv";

header('Content-Type: text/csv; charset=utf-8');
header("Content-Disposition: attachment; filename=\"$nombre_archivo\"");
header('Pragma: no-cache');
header('Expires: 0');

echo "\xEF\xBB\xBF";

$sep = ';';
$strUpper = function_exists('mb_strtoupper') ? function($s) { return mb_strtoupper((string)$s, 'UTF-8'); } : 'strtoupper';

echo escaparCsv('DETALLE DE CUENTA - ' . $strUpper($nombre)) . "\n";
echo escaparCsv('Exportado: ' . date('d/m/Y H:i:s')) . "\n";
echo "\n";

echo escaparCsv('FECHA') . $sep . escaparCsv('CONCEPTO') . $sep . escaparCsv('COMPROB') . $sep . escaparCsv('REF') . $sep . escaparCsv('MONTO') . $sep . escaparCsv('SALDO') . "\n";

foreach ($filas as $m) {
    $saldo += $m['monto'];
    $fechaFormateada = date('d/m/Y', strtotime($m['fecha']));
    $concepto = $strUpper($m['concepto'] ?? '');
    $comprob = $strUpper($m['comprobante'] ?? '');
    $ref = $strUpper($m['referencia'] ?? '');
    $montoStr = number_format($m['monto'], 2, ',', '.');
    $saldoStr = number_format($saldo, 2, ',', '.');
    echo escaparCsv($fechaFormateada) . $sep . escaparCsv($concepto) . $sep . escaparCsv($comprob) . $sep . escaparCsv($ref) . $sep . escaparCsv($montoStr) . $sep . escaparCsv($saldoStr) . "\n";
}

echo "\n";
echo $sep . $sep . $sep . $sep . escaparCsv('SALDO FINAL') . $sep . escaparCsv(number_format($saldo, 2, ',', '.')) . "\n";

exit;
