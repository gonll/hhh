<?php
/**
 * Obtiene todos los movimientos de una orden (suma todas las operaciones de esa orden).
 * Parámetros: orden, linea (opcional, default 1)
 */
include 'db.php';
include 'verificar_sesion.php';

if (!isset($_GET['orden'])) {
    die("Orden no recibida");
}

$orden = (int)$_GET['orden'];
$linea = isset($_GET['linea']) ? (int)$_GET['linea'] : 1;
if ($orden < 1) {
    die("Orden inválida");
}

$compro_buscar = ($linea === 2) ? "N° ORDEN $orden LINEA2" : "N° ORDEN $orden";
$compro_esc = mysqli_real_escape_string($conexion, $compro_buscar);

$refs_orden = [];
$r_ventas = mysqli_query($conexion, "SELECT DISTINCT referencia FROM cuentas WHERE comprobante = '$compro_esc' AND concepto LIKE 'VENTA AZUCAR%' AND referencia LIKE 'OP N° %'");
while ($rv = mysqli_fetch_assoc($r_ventas)) {
    $refs_orden[] = mysqli_real_escape_string($conexion, $rv['referencia']);
}
if (empty($refs_orden)) {
    $refs_orden = ["OP N° 0"];
}
$refs_in = implode(',', array_map(function($r) { return "'$r'"; }, $refs_orden));

$sql = "SELECT c.*, u.apellido AS usuario_nombre 
        FROM cuentas c 
        LEFT JOIN usuarios u ON u.id = c.usuario_id 
        WHERE c.referencia IN ($refs_in)
        ORDER BY c.fecha ASC, c.movimiento_id ASC";
$res = mysqli_query($conexion, $sql);

if (!$res) {
    die("Error en la consulta: " . mysqli_error($conexion));
}

$movimientos = [];
$saldo_total_orden = 0;
$usuario_id_orden = null;
while ($m = mysqli_fetch_array($res)) {
    $movimientos[] = $m;
    $saldo_total_orden += $m['monto'];
    if ($usuario_id_orden === null && !empty($m['usuario_id'])) {
        $usuario_id_orden = (int)$m['usuario_id'];
    }
}

$saldo_acumulado = 0;
if (count($movimientos) > 0 && $usuario_id_orden !== null) {
    echo '<!-- DATA:usuario_id=' . $usuario_id_orden . ' -->';
}

if (count($movimientos) > 0) {
    foreach ($movimientos as $m) {
        $saldo_acumulado += $m['monto'];
        $colorMonto = ($m['monto'] >= 0) ? "#28a745" : "#dc3545";
        $colorSaldo = ($saldo_acumulado >= 0) ? "#28a745" : "#dc3545";
        $fechaFormateada = date('d/m/Y', strtotime($m['fecha']));
        $usuario_nombre = htmlspecialchars($m['usuario_nombre'] ?? 'Sin usuario', ENT_QUOTES, 'UTF-8');
        $montoFormateado = number_format($m['monto'], 2, ',', '.');
        $saldoFormateado = number_format($saldo_acumulado, 2, ',', '.');
        echo "<tr class='fila-mov-op'>
                <td class='al-cen'>$fechaFormateada</td>
                <td class='al-izq'>" . strtoupper($m['concepto']) . "</td>
                <td class='al-cen'>" . strtoupper($m['comprobante']) . "</td>
                <td class='al-cen'>" . strtoupper($m['referencia']) . "</td>
                <td class='al-izq'>$usuario_nombre</td>
                <td class='al-der' style='color:$colorMonto; font-weight:bold; font-family: monospace; text-align: right; padding-right: 8px; white-space: nowrap;'>$ " . $montoFormateado . "</td>
                <td class='al-der' style='color:$colorSaldo; font-weight:bold; font-family: monospace; text-align: right; padding-right: 8px; white-space: nowrap;'>$ " . $saldoFormateado . "</td>
              </tr>";
    }
    $colorTotal = ($saldo_total_orden >= 0) ? "#28a745" : "#dc3545";
    $totalFormateado = number_format($saldo_total_orden, 2, ',', '.');
    echo "<tr style='background:#f8f9fa; font-weight:bold; border-top:2px solid #007bff;'>
            <td colspan='6' class='al-der' style='padding:8px;'>TOTAL ORDEN N° $orden (todas las operaciones):</td>
            <td class='al-der' style='padding:8px; color:$colorTotal; font-size:13px; font-family: monospace; text-align: right; padding-right: 8px;'>$ " . $totalFormateado . "</td>
          </tr>";
} else {
    echo "<tr><td colspan='7' style='text-align:center; padding:30px; color:gray;'>NO HAY MOVIMIENTOS PARA ESTA ORDEN</td></tr>";
}
?>
