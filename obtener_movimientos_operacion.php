<?php
include 'db.php';
include 'verificar_sesion.php';

// Verificamos que llegue el número de operación
if (!isset($_GET['operacion'])) {
    die("Operación no recibida");
}

$operacion = (int)$_GET['operacion'];
if ($operacion < 1) {
    die("Operación inválida");
}

// Buscar movimientos donde la referencia contenga "OP N° X" (donde X es el número de operación)
$ref_buscar = "OP N° $operacion";
$ref_buscar_esc = mysqli_real_escape_string($conexion, $ref_buscar);

// Tabla cuentas: buscar por referencia que contenga "OP N° X"
$sql = "SELECT c.*, u.apellido AS usuario_nombre 
        FROM cuentas c 
        LEFT JOIN usuarios u ON u.id = c.usuario_id 
        WHERE c.referencia = '$ref_buscar_esc' 
        ORDER BY c.fecha DESC, c.movimiento_id DESC";
$res = mysqli_query($conexion, $sql);

if (!$res) {
    die("Error en la consulta: " . mysqli_error($conexion));
}

// Primero obtener todos los movimientos para calcular el saldo total de la operación
$movimientos = [];
$saldo_total_operacion = 0;
$usuario_id_operacion = null;
while ($m = mysqli_fetch_array($res)) {
    $movimientos[] = $m;
    $saldo_total_operacion += $m['monto'];
    if ($usuario_id_operacion === null && !empty($m['usuario_id'])) {
        $usuario_id_operacion = (int)$m['usuario_id'];
    }
}

// Ordenar por fecha ASC para mostrar cronológicamente y calcular saldo acumulado
usort($movimientos, function($a, $b) {
    $fechaA = strtotime($a['fecha']);
    $fechaB = strtotime($b['fecha']);
    if ($fechaA === $fechaB) {
        return (int)$a['movimiento_id'] - (int)$b['movimiento_id'];
    }
    return $fechaA - $fechaB;
});

$saldo_acumulado = 0;

// Devolver usuario_id como atributo data en el primer elemento si existe
if (count($movimientos) > 0 && $usuario_id_operacion !== null) {
    echo '<!-- DATA:usuario_id=' . $usuario_id_operacion . ' -->';
}

if (count($movimientos) > 0) {
    foreach ($movimientos as $m) {
        $saldo_acumulado += $m['monto'];
        $colorMonto = ($m['monto'] >= 0) ? "#28a745" : "#dc3545";
        $colorSaldo = ($saldo_acumulado >= 0) ? "#28a745" : "#dc3545";

        // Formateamos la fecha para que se vea bien (dd/mm/aaaa)
        $fechaFormateada = date('d/m/Y', strtotime($m['fecha']));
        $usuario_nombre = htmlspecialchars($m['usuario_nombre'] ?? 'Sin usuario', ENT_QUOTES, 'UTF-8');
        
        // Formatear números con coma decimal alineada
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
    // Mostrar total al final
    $colorTotal = ($saldo_total_operacion >= 0) ? "#28a745" : "#dc3545";
    $totalFormateado = number_format($saldo_total_operacion, 2, ',', '.');
    echo "<tr style='background:#f8f9fa; font-weight:bold; border-top:2px solid #007bff;'>
            <td colspan='6' class='al-der' style='padding:8px;'>TOTAL OPERACIÓN:</td>
            <td class='al-der' style='padding:8px; color:$colorTotal; font-size:13px; font-family: monospace; text-align: right; padding-right: 8px;'>$ " . $totalFormateado . "</td>
          </tr>";
} else {
    echo "<tr><td colspan='7' style='text-align:center; padding:30px; color:gray;'>NO HAY MOVIMIENTOS PARA ESTA OPERACIÓN</td></tr>";
}
?>
