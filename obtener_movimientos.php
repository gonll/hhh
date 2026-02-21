<?php
include 'db.php';
include 'verificar_sesion.php';
include 'crear_tabla_cuentas_arriendo.php';
include 'cargar_arriendos_cuentas.php';

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
        $colorMonto = ($m['monto'] >= 0) ? "#28a745" : "#dc3545";  /* ingresos verde, egresos rojo */
        $colorSaldo = ($saldo >= 0) ? "#28a745" : "#dc3545";

        // Formateamos la fecha para que se vea bien (dd/mm/aaaa)
        $fechaFormateada = date('d/m/Y', strtotime($m['fecha']));

        $celdaEliminar = (isset($_SESSION['acceso_nivel']) && $_SESSION['acceso_nivel'] >= 2)
            ? "<button onclick='event.stopPropagation(); eliminarMovSeguro({$m['movimiento_id']})' style='border:none; background:none; cursor:pointer; font-size:14px;'>❌</button>"
            : "";
        $concepto_attr = htmlspecialchars($m['concepto'], ENT_QUOTES, 'UTF-8');
        $compro_attr  = htmlspecialchars($m['comprobante'], ENT_QUOTES, 'UTF-8');
        $ref_attr     = htmlspecialchars($m['referencia'], ENT_QUOTES, 'UTF-8');
        $ref_upper = strtoupper($m['referencia']);
        $compro_upper = strtoupper($m['comprobante']);
        $ref_td_class = 'al-cen';
        $ref_td_attrs = '';
        $es_precio_azucar = ($compro_upper === 'PRECIO AZUCAR' && $ref_upper === 'A DEFINIR' && (float)$m['monto'] == 0);
        $arriendo_id = isset($m['arriendo_id']) ? (int)$m['arriendo_id'] : 0;
        $arriendo_fecha = isset($m['arriendo_fecha']) ? (int)$m['arriendo_fecha'] : 0;
        if (!$es_precio_azucar && preg_match('/^OP N°\s*(\d+)/i', $ref_upper, $matches)) {
            $ref_td_class .= ' col-operacion-link';
            $ref_td_attrs = ' data-operacion="' . (int)$matches[1] . '"';
        }
        $compro_html = $compro_upper;
        $ref_html = $ref_upper;
        if ($es_precio_azucar) {
            $compro_html = "<a href='#' class='link-precio-azucar' onclick='event.stopPropagation(); abrirModalPrecioAzucar(" . (int)$m['movimiento_id'] . ", $arriendo_id, $arriendo_fecha); return false;'>$compro_upper</a>";
            $ref_html = "<a href='#' class='link-precio-azucar' onclick='event.stopPropagation(); abrirModalPrecioAzucar(" . (int)$m['movimiento_id'] . ", $arriendo_id, $arriendo_fecha); return false;'>$ref_upper</a>";
        }
        echo "<tr class='fila-mov' data-movimiento-id=\"{$m['movimiento_id']}\" data-concepto=\"$concepto_attr\" data-compro=\"$compro_attr\" data-ref=\"$ref_attr\" data-monto=\"" . (float)$m['monto'] . "\" data-arriendo-id=\"$arriendo_id\" data-arriendo-fecha=\"$arriendo_fecha\" onclick=\"seleccionarFila(this, '{$m['movimiento_id']}', '$fechaFormateada', '" . addslashes(strtoupper($m['concepto'])) . "', '" . addslashes(strtoupper($m['comprobante'])) . "', '" . addslashes(strtoupper($m['referencia'])) . "', '{$m['monto']}')\">
                <td class='al-cen'>$fechaFormateada</td>
                <td class='al-izq'>" . strtoupper($m['concepto']) . "</td>
                <td class='al-cen'>$compro_html</td>
                <td class='$ref_td_class'$ref_td_attrs>$ref_html</td>
                <td class='al-der' style='color:$colorMonto; font-weight:bold;'>$ " . number_format($m['monto'], 2, ',', '.') . "</td>
                <td class='al-der' style='color:$colorSaldo;'><strong>$ " . number_format($saldo, 2, ',', '.') . "</strong></td>
                <td class='al-cen'>$celdaEliminar</td>
              </tr>";
    }
} else {
    echo "<tr><td colspan='7' style='text-align:center; padding:30px; color:gray;'>NO HAY MOVIMIENTOS REGISTRADOS</td></tr>";
}
?>