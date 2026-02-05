<?php
include 'db.php';

// Verificamos que llegue el ID del usuario
if (!isset($_GET['id'])) {
    die("ID no recibido");
}

$id = (int)$_GET['id'];

// Tabla cuentas: columna usuario_id (FK a usuarios.id)
$sql = "SELECT * FROM cuentas WHERE usuario_id = $id ORDER BY fecha ASC";
$res = mysqli_query($conexion, $sql);

if (!$res) {
    die("Error en la consulta: " . mysqli_error($conexion));
}

$saldo = 0;

if (mysqli_num_rows($res) > 0) {
    while ($m = mysqli_fetch_array($res)) {
        $saldo += $m['monto'];
        $colorMonto = ($m['monto'] >= 0) ? "green" : "red";
        
        // Formateamos la fecha para que se vea bien (dd/mm/aaaa)
        $fechaFormateada = date('d/m/Y', strtotime($m['fecha']));

        echo "<tr class='fila-mov' onclick=\"seleccionarFila(this, '$fechaFormateada', '" . strtoupper($m['concepto']) . "', '" . strtoupper($m['comprobante']) . "', '" . strtoupper($m['referencia']) . "', '{$m['monto']}')\">
                <td class='al-cen'>$fechaFormateada</td>
                <td class='al-izq'>" . strtoupper($m['concepto']) . "</td>
                <td class='al-cen'>" . strtoupper($m['comprobante']) . "</td>
                <td class='al-cen'>" . strtoupper($m['referencia']) . "</td>
                <td class='al-der' style='color:$colorMonto; font-weight:bold;'>$ " . number_format($m['monto'], 2, ',', '.') . "</td>
                <td class='al-der'><strong>$ " . number_format($saldo, 2, ',', '.') . "</strong></td>
                <td class='al-cen'>
                    <button onclick='event.stopPropagation(); eliminarMovSeguro({$m['movimiento_id']})' style='border:none; background:none; cursor:pointer; font-size:14px;'>❌</button>
                </td>
              </tr>";
    }
} else {
    echo "<tr><td colspan='7' style='text-align:center; padding:30px; color:gray;'>NO HAY MOVIMIENTOS REGISTRADOS</td></tr>";
}
?>