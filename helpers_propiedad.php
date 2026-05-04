<?php
/**
 * Quita ciudad y provincia del texto de la propiedad para usar en conceptos (garantía, proporcional, liquidación alquiler).
 * Elimina al final del texto: ", TUCUMAN", ", S. M. DE TUCUMAN", ", SAN MIGUEL DE TUCUMAN" y similares.
 */
function omitir_ciudad_provincia($texto) {
    if ($texto === null || $texto === '') return '';
    $t = trim($texto);
    // Quitar provincia y ciudad al final (varias pasadas por si hay "..., CIUDAD, PROVINCIA")
    $patrones = [
        '/,\s*TUCUMAN\s*$/i',
        '/,\s*S\.\s*M\.\s*DE\s*TUCUMAN\s*$/i',
        '/,\s*SAN\s*MIGUEL\s*DE\s*TUCUMAN\s*$/i',
    ];
    foreach ($patrones as $p) {
        $t = preg_replace($p, '', $t);
        $t = trim($t, " \t,");
    }
    return $t;
}

/**
 * Valida cadena Y-m-d para MySQL DATE (evita valores como solo "2028").
 */
function fecha_valida_ymd($s) {
    $s = trim((string) $s);
    if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $s, $m)) {
        return false;
    }
    return checkdate((int) $m[2], (int) $m[3], (int) $m[1]);
}

/**
 * Misma lógica que recalcularFechaFin() en contrato_alquiler.js: último día del mes
 * (mes inicio + plazo meses - 1).
 */
function calcular_fecha_fin_alquiler_ymd($fecha_inicio, $plazo_meses) {
    $fecha_inicio = trim((string) $fecha_inicio);
    if ($plazo_meses < 1 || !fecha_valida_ymd($fecha_inicio)) {
        return null;
    }
    $d = DateTime::createFromFormat('Y-m-d', $fecha_inicio);
    if (!$d) {
        return null;
    }
    $mesInicio = (int) $d->format('n') - 1;
    $anioInicio = (int) $d->format('Y');
    $mesFinal = $mesInicio + $plazo_meses - 1;
    $anioFinal = $anioInicio + intdiv($mesFinal, 12);
    $mesFinal = $mesFinal % 12;
    if ($mesFinal < 0) {
        $mesFinal += 12;
        $anioFinal--;
    }
    $mesHuman = $mesFinal + 1;
    $tsMes = strtotime(sprintf('%04d-%02d-01', $anioFinal, $mesHuman));
    $ultimoDia = (int) date('t', $tsMes);
    return sprintf('%04d-%02d-%02d', $anioFinal, $mesHuman, $ultimoDia);
}

/**
 * Columna incremento_alquiler_meses en alquileres (1–6). Idempotente.
 */
function alquileres_asegurar_columna_incremento($conexion) {
    static $done = false;
    if ($done || !$conexion) {
        return;
    }
    $r = mysqli_query($conexion, "SHOW COLUMNS FROM alquileres LIKE 'incremento_alquiler_meses'");
    if ($r && mysqli_num_rows($r) > 0) {
        $done = true;
        return;
    }
    $sql = "ALTER TABLE alquileres ADD COLUMN incremento_alquiler_meses TINYINT UNSIGNED NOT NULL DEFAULT 2 COMMENT 'Cada cuántos meses se actualiza el alquiler (1-6)' AFTER plazo_meses";
    if (!@mysqli_query($conexion, $sql)) {
        error_log('alquileres_asegurar_columna_incremento: ' . mysqli_error($conexion));
    }
    $done = true;
}

/**
 * Detalle y coeficiente IPC para liquidación de alquiler (N meses de índice × 1,015).
 * Devuelve:
 * - coef: número final (ej. 1.0842)
 * - formula: multiplicación aplicada (ej. (1+2.10/100) x (1+1.30/100) x 1.015)
 * - detalle: meses/porcentajes usados (ej. 02/2026=2.10%, 01/2026=1.30%)
 */
function liquidar_alquiler_detalle_coef_ipc($conexion, $n_meses) {
    $n = max(1, min(6, (int) $n_meses));
    $anio_actual = (int) date('Y');
    $mes_num_actual = (int) date('m');
    $coef = 1.0;
    $partes_formula = [];
    $partes_detalle = [];
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
        $partes_formula[] = '(1+' . number_format($v, 2, '.', '') . '/100)';
        $partes_detalle[] = sprintf('%02d/%04d=%s%%', $m, $a, number_format($v, 2, '.', ''));
    }
    $coef_final = $coef * 1.015;
    $partes_formula[] = '1.015';
    return [
        'coef' => $coef_final,
        'formula' => implode(' x ', $partes_formula),
        'detalle' => implode(', ', $partes_detalle),
    ];
}

/**
 * Coeficiente IPC para liquidación de alquiler (N meses de índice × 1,015).
 */
function liquidar_alquiler_coef_ipc($conexion, $n_meses) {
    $d = liquidar_alquiler_detalle_coef_ipc($conexion, $n_meses);
    return isset($d['coef']) ? (float) $d['coef'] : 0.0;
}
