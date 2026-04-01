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
