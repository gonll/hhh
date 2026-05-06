<?php
include 'db.php';
include 'verificar_sesion.php';
require_once __DIR__ . '/helpers_tenant_inmobiliaria.php';
require_once __DIR__ . '/helpers_propiedad.php';
tenant_inmob_asegurar_esquema($conexion);
include 'crear_tabla_cuentas_arriendo.php';
include 'cargar_arriendos_cuentas.php';

if (!isset($_GET['id'])) {
    die("ID no recibido");
}

$id = (int)$_GET['id'];
if (!tenant_inmob_usuario_id_visible($conexion, $id)) {
    die('Sin permiso');
}
$before_fecha = isset($_GET['before_fecha']) ? trim($_GET['before_fecha']) : '';
$before_id = isset($_GET['before_id']) ? (int)$_GET['before_id'] : 0;
$after_fecha = isset($_GET['after_fecha']) ? trim($_GET['after_fecha']) : '';
$after_id = isset($_GET['after_id']) ? (int)$_GET['after_id'] : 0;

function asegurar_alquiler_mes_usuario($conexion, $usuario_id) {
    if (!function_exists('alquileres_asegurar_columna_incremento') || !function_exists('omitir_ciudad_provincia')) {
        return;
    }
    alquileres_asegurar_columna_incremento($conexion);

    $mes_actual = date('m/Y');
    $primer_dia = date('Y-m-01');
    $anio_actual = (int)date('Y');
    $mes_num_actual = (int)date('m');

    $uid = (int)$usuario_id;
    if ($uid <= 0) return;

    $sql_contratos = "SELECT a.inquilino1_id, a.precio_convenido, a.fecha_inicio,
                             COALESCE(a.incremento_alquiler_meses, 2) AS incremento_alquiler_meses,
                             p.propiedad AS nombre_propiedad
                      FROM alquileres a
                      INNER JOIN propiedades p ON a.propiedad_id = p.propiedad_id
                      WHERE a.estado = 'VIGENTE'
                        AND a.inquilino1_id = $uid";
    $contratos = mysqli_query($conexion, $sql_contratos);
    if (!$contratos) return;

    while ($c = mysqli_fetch_assoc($contratos)) {
        $inquilino_id = (int)$c['inquilino1_id'];
        $precio = (float)$c['precio_convenido'];
        $fecha_inicio = trim((string)$c['fecha_inicio']);
        $incr_meses = max(1, min(6, (int)$c['incremento_alquiler_meses']));
        $nombre_prop_raw = strtoupper(omitir_ciudad_provincia(trim((string)$c['nombre_propiedad'])));
        $nombre_prop = mysqli_real_escape_string($conexion, $nombre_prop_raw);
        $concepto_base = 'ALQUILER - ' . $nombre_prop;
        $concepto_act = 'ALQUILER ACTUALIZADO - ' . $nombre_prop;

        $ts_inicio = strtotime($fecha_inicio);
        if ($ts_inicio === false) continue;
        $primer_mes_contrato = date('Y-m-01', $ts_inicio);
        if ($primer_dia < $primer_mes_contrato) continue;

        $fi_esc = mysqli_real_escape_string($conexion, $fecha_inicio);
        $primer_dia_esc = mysqli_real_escape_string($conexion, $primer_dia);
        $concepto_base_esc = mysqli_real_escape_string($conexion, $concepto_base);
        $concepto_act_like_esc = mysqli_real_escape_string(
            $conexion,
            str_replace(['%', '_'], ['\\%', '\\_'], $concepto_act) . '%'
        );
        $nombre_prop_like = mysqli_real_escape_string($conexion, str_replace(['%', '_'], ['\\%', '\\_'], $nombre_prop_raw));

        // Si ya existe alquiler del mes, no hace nada.
        $existe = mysqli_query($conexion,
            "SELECT 1 FROM cuentas
             WHERE usuario_id = $inquilino_id
               AND comprobante = 'ALQUILER'
               AND referencia = '$mes_actual'
               AND (concepto = '$concepto_base_esc' OR concepto LIKE '$concepto_act_like_esc')
               AND (fecha >= '$fi_esc' OR fecha = '$primer_dia_esc')
             LIMIT 1"
        );
        if ($existe && mysqli_num_rows($existe) > 0) continue;

        // Si ya existe liquidación prorrateada del mes, tampoco inserta ALQUILER mensual.
        $existe_liq = mysqli_query($conexion,
            "SELECT 1 FROM cuentas
             WHERE usuario_id = $inquilino_id
               AND comprobante = 'LIQ ALQUILER'
               AND referencia = '$mes_actual'
               AND concepto LIKE '%$nombre_prop_like%'
               AND fecha >= '$fi_esc'
             LIMIT 1"
        );
        if ($existe_liq && mysqli_num_rows($existe_liq) > 0) continue;

        $anio_inicio = (int)date('Y', $ts_inicio);
        $mes_inicio = (int)date('m', $ts_inicio);
        $meses_desde_inicio = ($anio_actual - $anio_inicio) * 12 + ($mes_num_actual - $mes_inicio);
        $aplica_actualizacion = ($meses_desde_inicio >= $incr_meses && ($meses_desde_inicio % $incr_meses == 0));

        $detalle_coef = ['coef' => 0, 'formula' => '', 'detalle' => ''];
        if (function_exists('liquidar_alquiler_detalle_coef_ipc')) {
            $detalle_coef = liquidar_alquiler_detalle_coef_ipc($conexion, $incr_meses);
        } elseif (function_exists('liquidar_alquiler_coef_ipc')) {
            $detalle_coef['coef'] = (float)liquidar_alquiler_coef_ipc($conexion, $incr_meses);
        }
        $coef_actualizacion = isset($detalle_coef['coef']) ? (float)$detalle_coef['coef'] : 0;

        $ultimo = mysqli_query($conexion,
            "SELECT ABS(monto) AS ultimo_monto FROM cuentas
             WHERE usuario_id = $inquilino_id
               AND comprobante IN ('ALQUILER', 'LIQ ALQUILER')
               AND concepto LIKE '%$nombre_prop_like%'
               AND fecha >= '$fi_esc'
             ORDER BY fecha DESC, movimiento_id DESC LIMIT 1"
        );
        $base = $precio;
        if ($ultimo && ($row_u = mysqli_fetch_assoc($ultimo)) && (float)$row_u['ultimo_monto'] > 0) {
            $base = (float)$row_u['ultimo_monto'];
        }

        if ($aplica_actualizacion && $coef_actualizacion > 0) {
            $monto_liquidar = round($base * $coef_actualizacion, 2);
            $monto_origen_txt = number_format($base, 2, '.', '');
            $coef_txt = number_format($coef_actualizacion, 4, '.', '');
            $monto_final_txt = number_format($monto_liquidar, 2, '.', '');
            $formula_coef_txt = isset($detalle_coef['formula']) ? $detalle_coef['formula'] : '';
            $detalle_meses_txt = isset($detalle_coef['detalle']) ? $detalle_coef['detalle'] : '';
            $concepto_final = $concepto_act
                . ' | ORIGEN: $' . $monto_origen_txt
                . ' | COEF: ' . $coef_txt
                . ' (de ' . $formula_coef_txt . '; IPC usados: ' . $detalle_meses_txt . ')'
                . ' | CALCULO: ' . $monto_origen_txt . ' x ' . $coef_txt
                . ' | FINAL: $' . $monto_final_txt;
        } else {
            $monto_liquidar = $base;
            $concepto_final = $concepto_base;
        }

        $monto_retiro = -$monto_liquidar;
        $concepto_final_esc = mysqli_real_escape_string($conexion, $concepto_final);
        $mes_actual_esc = mysqli_real_escape_string($conexion, $mes_actual);
        mysqli_query($conexion,
            "INSERT INTO cuentas (usuario_id, fecha, concepto, comprobante, referencia, monto)
             VALUES ($inquilino_id, '$primer_dia_esc', '$concepto_final_esc', 'ALQUILER', '$mes_actual_esc', $monto_retiro)"
        );
    }
}

// Solo en carga principal del detalle (no al paginar) para evitar costo extra.
if ($before_fecha === '' && $after_fecha === '') {
    asegurar_alquiler_mes_usuario($conexion, $id);
}

// Si es consorcio: obtener fecha de la última LIQ EXPENSAS (para no mostrar X en movimientos anteriores)
$ultima_liq_fecha = null;
$ultima_liq_id = null;
$r_u = mysqli_query($conexion, "SELECT 1 FROM usuarios WHERE id = $id AND UPPER(apellido) LIKE 'CONSORCIO%' LIMIT 1");
if ($r_u && mysqli_num_rows($r_u) > 0) {
    $r_liq = mysqli_query($conexion, "SELECT fecha, movimiento_id FROM cuentas WHERE usuario_id = $id AND UPPER(TRIM(comprobante)) = 'LIQ EXPENSAS' ORDER BY fecha DESC, movimiento_id DESC LIMIT 1");
    if ($r_liq && $row_liq = mysqli_fetch_assoc($r_liq)) {
        $ultima_liq_fecha = $row_liq['fecha'];
        $ultima_liq_id = (int)$row_liq['movimiento_id'];
    }
}

$res_total = mysqli_query($conexion, "SELECT COALESCE(SUM(monto), 0) AS total FROM cuentas WHERE usuario_id = $id");
$total_cuenta = ($r = mysqli_fetch_assoc($res_total)) ? (float)$r['total'] : 0;

$es_arrendador = false;
$r_arrend = mysqli_query($conexion, "SELECT 1 FROM arriendos WHERE propietario_id = $id LIMIT 1");
if ($r_arrend && mysqli_num_rows($r_arrend) > 0) $es_arrendador = true;

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
    $sql = "SELECT * FROM (SELECT * FROM cuentas WHERE usuario_id = $id ORDER BY fecha DESC, movimiento_id DESC LIMIT 30) AS sub ORDER BY fecha ASC, movimiento_id ASC";
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

function renderFila($m, &$saldo, $conexion, $ultima_liq_fecha = null, $ultima_liq_id = null) {
    $saldo += $m['monto'];
    $colorMonto = ($m['monto'] >= 0) ? "#28a745" : "#dc3545";
    $colorSaldo = ($saldo >= 0) ? "#28a745" : "#dc3545";
    $fechaFormateada = date('d/m/Y', strtotime($m['fecha']));
    $mostrarX = (isset($_SESSION['acceso_nivel']) && $_SESSION['acceso_nivel'] >= 2);
    if ($mostrarX && $ultima_liq_fecha !== null && $ultima_liq_id !== null) {
        $mov_fecha = $m['fecha'];
        $mov_id = (int)$m['movimiento_id'];
        if ($mov_fecha < $ultima_liq_fecha || ($mov_fecha === $ultima_liq_fecha && $mov_id < $ultima_liq_id)) {
            $mostrarX = false;
        }
    }
    $celdaEliminar = $mostrarX
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
    $es_fijo_mes = (stripos($m['concepto'] ?? '', 'FIJO DE MES') === 0);
    $tr_style = $es_fijo_mes ? " style='color:#E91E63 !important; font-weight:bold;'" : '';
    return "<tr class='fila-mov'$tr_style data-movimiento-id=\"{$m['movimiento_id']}\" data-concepto=\"$concepto_attr\" data-compro=\"$compro_attr\" data-ref=\"$ref_attr\" data-monto=\"" . (float)$m['monto'] . "\" data-arriendo-id=\"$arriendo_id\" data-arriendo-fecha=\"$arriendo_fecha\" data-fecha=\"$fecha_raw\" onclick=\"seleccionarFila(this, '{$m['movimiento_id']}', '$fechaFormateada', '" . addslashes(strtoupper($m['concepto'])) . "', '" . addslashes(strtoupper($m['comprobante'])) . "', '" . addslashes(strtoupper($m['referencia'])) . "', '{$m['monto']}')\">
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
        $html .= renderFila($m, $saldo, $conexion, $ultima_liq_fecha, $ultima_liq_id);
    }
} else {
    $html = "<tr><td colspan='7' style='text-align:center; padding:30px; color:gray;'>NO HAY MOVIMIENTOS REGISTRADOS</td></tr>";
}

header('Content-Type: application/json; charset=utf-8');
echo json_encode([
    'html' => $html,
    'es_arrendador' => $es_arrendador,
    'has_more_older' => $has_more_older,
    'has_more_newer' => $has_more_newer,
    'first_fecha' => $first_fecha,
    'first_id' => $first_id,
    'last_fecha' => $last_fecha,
    'last_id' => $last_id,
    'saldo_actual' => $total_cuenta
]);
?>
