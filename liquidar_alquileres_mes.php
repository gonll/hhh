<?php
/**
 * Evalúa si ya se liquidaron los alquileres del mes para cada contrato vigente.
 * Actualización según incremento_alquiler_meses (1–6): coeficiente = producto de (1+IPC/100)
 * sobre los meses M-2 … M-(N+1) × 1,015 (misma lógica que bimestral con N=2).
 * Si corresponde actualización: concepto "ALQUILER ACTUALIZADO - [propiedad]".
 */
if (!isset($conexion)) return;
include_once __DIR__ . '/helpers_propiedad.php';

$dia = (int)date('j');
if ($dia < 1) return;

$mes_actual     = date('m/Y');
$primer_dia     = date('Y-m-01');
$anio_actual    = (int)date('Y');
$mes_num_actual = (int)date('m');

/**
 * Coeficiente de actualización según N meses de IPC (N=1..6).
 */
function liquidar_alquiler_coef_ipc($conexion, $n_meses) {
    $n = max(1, min(6, (int) $n_meses));
    $anio_actual = (int) date('Y');
    $mes_num_actual = (int) date('m');
    $coef = 1.0;
    for ($j = 2; $j <= $n + 1; $j++) {
        $m = $mes_num_actual - $j;
        $a = $anio_actual;
        while ($m <= 0) {
            $m += 12;
            $a--;
        }
        $fecha_ipc = sprintf('%04d-%02d-01', $a, $m);
        $r = mysqli_query($conexion, "SELECT valor FROM indices WHERE fecha = '$fecha_ipc' AND tipo = 'IPC' LIMIT 1");
        $v = ($r && $row = mysqli_fetch_assoc($r)) ? (float) $row['valor'] : 0;
        $coef *= (1 + $v / 100);
    }
    return $coef * 1.015;
}

$contratos = mysqli_query($conexion,
    "SELECT a.propiedad_id, a.inquilino1_id, a.precio_convenido, a.fecha_inicio,
            COALESCE(a.incremento_alquiler_meses, 2) AS incremento_alquiler_meses,
            p.propiedad AS nombre_propiedad
     FROM alquileres a
     INNER JOIN propiedades p ON a.propiedad_id = p.propiedad_id
     WHERE a.estado = 'VIGENTE'"
);
if (!$contratos) return;

while ($c = mysqli_fetch_assoc($contratos)) {
    $inquilino_id   = (int)$c['inquilino1_id'];
    $precio         = (float)$c['precio_convenido'];
    $fecha_inicio   = $c['fecha_inicio'];
    $incr_meses     = max(1, min(6, (int) $c['incremento_alquiler_meses']));
    $nombre_prop    = mysqli_real_escape_string($conexion, strtoupper(omitir_ciudad_provincia(trim($c['nombre_propiedad']))));
    $concepto_base  = 'ALQUILER - ' . $nombre_prop;
    $concepto_act   = 'ALQUILER ACTUALIZADO - ' . strtoupper($nombre_prop);

    // Ya liquidado este mes si existe movimiento ALQUILER para esta propiedad (verificación por concepto)
    $concepto_base_esc = mysqli_real_escape_string($conexion, $concepto_base);
    $concepto_act_esc  = mysqli_real_escape_string($conexion, $concepto_act);
    $existe = mysqli_query($conexion,
        "SELECT 1 FROM cuentas 
         WHERE usuario_id = $inquilino_id 
           AND comprobante = 'ALQUILER' 
           AND referencia = '$mes_actual'
           AND (concepto = '$concepto_base_esc' OR concepto = '$concepto_act_esc')
         LIMIT 1"
    );
    if ($existe && mysqli_num_rows($existe) > 0) continue;

    // No liquidar este mes si ya existe LIQ ALQUILER para esta propiedad (contrato nuevo con mes prorrateado)
    $nombre_prop_raw = strtoupper(omitir_ciudad_provincia(trim($c['nombre_propiedad'])));
    $nombre_prop_like = mysqli_real_escape_string($conexion, str_replace(['%', '_'], ['\\%', '\\_'], $nombre_prop_raw));
    $existe_liq = mysqli_query($conexion,
        "SELECT 1 FROM cuentas 
         WHERE usuario_id = $inquilino_id 
           AND comprobante = 'LIQ ALQUILER' 
           AND referencia = '$mes_actual'
           AND concepto LIKE '%$nombre_prop_like%'
         LIMIT 1"
    );
    if ($existe_liq && mysqli_num_rows($existe_liq) > 0) continue;

    $ts_inicio  = strtotime($fecha_inicio);
    $anio_inicio  = (int)date('Y', $ts_inicio);
    $mes_inicio   = (int)date('m', $ts_inicio);
    $meses_desde_inicio = ($anio_actual - $anio_inicio) * 12 + ($mes_num_actual - $mes_inicio);

    $aplica_actualizacion = ($meses_desde_inicio >= $incr_meses && ($meses_desde_inicio % $incr_meses == 0));

    $coef_actualizacion = liquidar_alquiler_coef_ipc($conexion, $incr_meses);

    if ($aplica_actualizacion && $coef_actualizacion > 0) {
        // Buscar último monto de ALQUILER para esta propiedad (no de otras propiedades del mismo inquilino)
        $nombre_prop_like = mysqli_real_escape_string($conexion, str_replace(['%', '_'], ['\\%', '\\_'], $nombre_prop_raw));
        $ultimo = mysqli_query($conexion,
            "SELECT ABS(monto) AS ultimo_monto FROM cuentas 
             WHERE usuario_id = $inquilino_id AND comprobante = 'ALQUILER'
             AND concepto LIKE '%$nombre_prop_like%'
             ORDER BY fecha DESC, movimiento_id DESC LIMIT 1"
        );
        $base = $precio;
        if ($ultimo && ($row_u = mysqli_fetch_assoc($ultimo)) && (float)$row_u['ultimo_monto'] > 0) {
            $base = (float)$row_u['ultimo_monto'];
        }
        $monto_liquidar = round($base * $coef_actualizacion, 2);
        $concepto_final = $concepto_act;
    } else {
        $monto_liquidar = $precio;
        $concepto_final = $concepto_base;
    }

    $monto_retiro = -$monto_liquidar;

    mysqli_query($conexion,
        "INSERT INTO cuentas (usuario_id, fecha, concepto, comprobante, referencia, monto) 
         VALUES ($inquilino_id, '$primer_dia', '$concepto_final', 'ALQUILER', '$mes_actual', $monto_retiro)"
    );
}
?>
