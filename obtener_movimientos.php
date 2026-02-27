<?php
include 'db.php';
include 'verificar_sesion.php';
include 'crear_tabla_cuentas_arriendo.php';
include 'cargar_arriendos_cuentas.php';

if (!isset($_GET['id'])) {
    die("ID no recibido");
}

$id = (int)$_GET['id'];
$before_fecha = isset($_GET['before_fecha']) ? trim($_GET['before_fecha']) : '';
$before_id = isset($_GET['before_id']) ? (int)$_GET['before_id'] : 0;
$after_fecha = isset($_GET['after_fecha']) ? trim($_GET['after_fecha']) : '';
$after_id = isset($_GET['after_id']) ? (int)$_GET['after_id'] : 0;

$res_total = mysqli_query($conexion, "SELECT COALESCE(SUM(monto), 0) AS total FROM cuentas WHERE usuario_id = $id");
$total_cuenta = ($r = mysqli_fetch_assoc($res_total)) ? (float)$r['total'] : 0;

$filas = [];
$load_older = ($before_fecha !== '' && $before_id > 0);
$load_newer = ($after_fecha !== '' && $after_id > 0);

if ($load_older) {
    $bf_esc = mysqli_real_escape_string($conexion, $before_fecha);
    $sql = "SELECT * FROM (SELECT * FROM cuentas WHERE usuario_id = $id 
            AND (fecha < '$bf_esc' OR (fecha = '$bf_esc' AND movimiento_id < $before_id))
            ORDER BY fecha DESC, movimiento_id DESC LIMIT 10) AS sub ORDER BY fecha ASC, movimiento_id ASC";
    $res = mysqli_query($conexion, $sql);
} elseif ($load_newer) {
    $af_esc = mysqli_real_escape_string($conexion, $after_fecha);
    $sql = "SELECT * FROM cuentas WHERE usuario_id = $id 
            AND (fecha > '$af_esc' OR (fecha = '$af_esc' AND movimiento_id > $after_id))
            ORDER BY fecha ASC, movimiento_id ASC LIMIT 10";
    $res = mysqli_query($conexion, $sql);
} else {
    $sql = "SELECT * FROM (SELECT * FROM cuentas WHERE usuario_id = $id ORDER BY fecha DESC, movimiento_id DESC LIMIT 10) AS sub ORDER BY fecha ASC, movimiento_id ASC";
    $res = mysqli_query($conexion, $sql);
}

if (!$res) {
    die("Error en la consulta: " . mysqli_error($conexion));
}

while ($m = mysqli_fetch_array($res)) {
    $filas[] = $m;
}

// Calcular si hay más movimientos antes/después
$has_more_older = false;
$has_more_newer = false;
$first_fecha = '';
$first_id = 0;
$last_fecha = '';
$last_id = 0;

if (count($filas) > 0) {
    $first = $filas[0];
    $last = $filas[count($filas) - 1];
    $first_fecha = $first['fecha'];
    $first_id = (int)$first['movimiento_id'];
    $last_fecha = $last['fecha'];
    $last_id = (int)$last['movimiento_id'];

    $bf_esc = mysqli_real_escape_string($conexion, $first_fecha);
    $r_older = mysqli_query($conexion, "SELECT 1 FROM cuentas WHERE usuario_id = $id AND (fecha < '$bf_esc' OR (fecha = '$bf_esc' AND movimiento_id < $first_id)) LIMIT 1");
    $has_more_older = ($r_older && mysqli_num_rows($r_older) > 0);

    $lf_esc = mysqli_real_escape_string($conexion, $last_fecha);
    $r_newer = mysqli_query($conexion, "SELECT 1 FROM cuentas WHERE usuario_id = $id AND (fecha > '$lf_esc' OR (fecha = '$lf_esc' AND movimiento_id > $last_id)) LIMIT 1");
    $has_more_newer = ($r_newer && mysqli_num_rows($r_newer) > 0);
}

// Calcular saldo inicial: saldo ANTES de la primera fila (para que al sumar cada monto quede el acumulado correcto)
// Fórmula: saldo_fila_i = saldo_antes_primera + sum(monto de filas 1..i)
// saldo_antes_primera = total_cuenta - suma_despues - monto_primera
// (porque total = saldo_antes_primera + monto_primera + suma_despues)
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
$html = '';

function renderFila($m, &$saldo, $conexion) {
    $saldo += $m['monto'];
    $colorMonto = ($m['monto'] >= 0) ? "#28a745" : "#dc3545";
    $colorSaldo = ($saldo >= 0) ? "#28a745" : "#dc3545";
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
    $fecha_raw = $m['fecha'];
    $clase_fecha = (isset($_SESSION['acceso_nivel']) && $_SESSION['acceso_nivel'] >= 2) ? 'celda-fecha-editable' : '';
    $title_fecha = $clase_fecha ? " title='Clic para editar fecha'" : '';
    return "<tr class='fila-mov' data-movimiento-id=\"{$m['movimiento_id']}\" data-concepto=\"$concepto_attr\" data-compro=\"$compro_attr\" data-ref=\"$ref_attr\" data-monto=\"" . (float)$m['monto'] . "\" data-arriendo-id=\"$arriendo_id\" data-arriendo-fecha=\"$arriendo_fecha\" data-fecha=\"$fecha_raw\" onclick=\"seleccionarFila(this, '{$m['movimiento_id']}', '$fechaFormateada', '" . addslashes(strtoupper($m['concepto'])) . "', '" . addslashes(strtoupper($m['comprobante'])) . "', '" . addslashes(strtoupper($m['referencia'])) . "', '{$m['monto']}')\">
        <td class='al-cen $clase_fecha'$title_fecha>$fechaFormateada</td>
        <td class='al-izq'>" . strtoupper($m['concepto']) . "</td>
        <td class='al-cen'>$compro_html</td>
        <td class='$ref_td_class'$ref_td_attrs>$ref_html</td>
        <td class='al-der' style='color:$colorMonto; font-weight:bold;'>$ " . number_format($m['monto'], 2, ',', '.') . "</td>
        <td class='al-der' style='color:$colorSaldo;'><strong>$ " . number_format($saldo, 2, ',', '.') . "</strong></td>
        <td class='al-cen'>$celdaEliminar</td>
    </tr>";
}

if (count($filas) > 0) {
    foreach ($filas as $m) {
        $html .= renderFila($m, $saldo, $conexion);
    }
} else {
    $html = "<tr><td colspan='7' style='text-align:center; padding:30px; color:gray;'>NO HAY MOVIMIENTOS REGISTRADOS</td></tr>";
}

header('Content-Type: application/json; charset=utf-8');
echo json_encode([
    'html' => $html,
    'has_more_older' => $has_more_older,
    'has_more_newer' => $has_more_newer,
    'first_fecha' => $first_fecha,
    'first_id' => $first_id,
    'last_fecha' => $last_fecha,
    'last_id' => $last_id,
    'saldo_actual' => $total_cuenta
]);
?>
