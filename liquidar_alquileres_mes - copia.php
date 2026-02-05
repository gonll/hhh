<?php
/**
 * Evalúa si ya se liquidaron los alquileres del mes para cada contrato vigente.
 * Considera fecha_inicio: actualización bimestral usando IPC ante anterior y ante ante anterior + 1,5%.
 * Si corresponde actualización: concepto "ALQUILER ACTUALIZADO - [propiedad]".
 */
if (!isset($conexion)) return;

$dia = (int)date('j');
if ($dia < 1) return;

$mes_actual     = date('m/Y');
$primer_dia     = date('Y-m-01');
$anio_actual    = (int)date('Y');
$mes_num_actual = (int)date('m');

// IPC mes-2 (ante anterior) y mes-3 (ante ante anterior) - ya publicados
$mes2 = $mes_num_actual - 2;
$anio2 = $anio_actual;
if ($mes2 <= 0) { $mes2 += 12; $anio2--; }
$mes3 = $mes_num_actual - 3;
$anio3 = $anio_actual;
if ($mes3 <= 0) { $mes3 += 12; $anio3--; }

$fecha_ipc2 = sprintf('%04d-%02d-01', $anio2, $mes2);
$fecha_ipc3 = sprintf('%04d-%02d-01', $anio3, $mes3);

$r2 = mysqli_query($conexion, "SELECT valor FROM indices WHERE fecha = '$fecha_ipc2' AND tipo = 'IPC' LIMIT 1");
$r3 = mysqli_query($conexion, "SELECT valor FROM indices WHERE fecha = '$fecha_ipc3' AND tipo = 'IPC' LIMIT 1");
$ipc2 = ($r2 && $row2 = mysqli_fetch_assoc($r2)) ? (float)$row2['valor'] : 0;
$ipc3 = ($r3 && $row3 = mysqli_fetch_assoc($r3)) ? (float)$row3['valor'] : 0;

$coef_actualizacion = (1 + $ipc2/100) * (1 + $ipc3/100) * 1.015;

$contratos = mysqli_query($conexion,
    "SELECT a.propiedad_id, a.inquilino1_id, a.precio_convenido, a.fecha_inicio, p.propiedad AS nombre_propiedad
     FROM alquileres a
     INNER JOIN propiedades p ON a.propiedad_id = p.propiedad_id
     WHERE a.estado = 'VIGENTE'"
);
if (!$contratos) return;

while ($c = mysqli_fetch_assoc($contratos)) {
    $inquilino_id   = (int)$c['inquilino1_id'];
    $precio         = (float)$c['precio_convenido'];
    $fecha_inicio   = $c['fecha_inicio'];
    $nombre_prop    = mysqli_real_escape_string($conexion, trim($c['nombre_propiedad']));
    $concepto_base  = 'ALQUILER - ' . strtoupper($nombre_prop);
    $concepto_act   = 'ALQUILER ACTUALIZADO - ' . strtoupper($nombre_prop);

    $existe = mysqli_query($conexion,
        "SELECT 1 FROM cuentas 
         WHERE usuario_id = $inquilino_id 
           AND comprobante = 'ALQUILER' 
           AND referencia = '$mes_actual' 
           AND (concepto = '$concepto_base' OR concepto = '$concepto_act')
         LIMIT 1"
    );
    if ($existe && mysqli_num_rows($existe) > 0) continue;

    $ts_inicio  = strtotime($fecha_inicio);
    $anio_inicio  = (int)date('Y', $ts_inicio);
    $mes_inicio   = (int)date('m', $ts_inicio);
    $meses_desde_inicio = ($anio_actual - $anio_inicio) * 12 + ($mes_num_actual - $mes_inicio);

    $aplica_actualizacion = ($meses_desde_inicio >= 2 && ($meses_desde_inicio % 2 == 0));

    if ($aplica_actualizacion && $coef_actualizacion > 0) {
        $ultimo = mysqli_query($conexion,
            "SELECT ABS(monto) AS ultimo_monto FROM cuentas 
             WHERE usuario_id = $inquilino_id AND comprobante = 'ALQUILER' 
               AND (concepto = '$concepto_base' OR concepto = '$concepto_act')
             ORDER BY fecha DESC LIMIT 1"
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
