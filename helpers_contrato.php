<?php
/**
 * Convierte un número entero a letras en español (para contratos).
 */
function numerosALetras($n) {
    $n = (int)round($n);
    $unidades = ['', 'UN', 'DOS', 'TRES', 'CUATRO', 'CINCO', 'SEIS', 'SIETE', 'OCHO', 'NUEVE'];
    $dieces = ['DIEZ', 'ONCE', 'DOCE', 'TRECE', 'CATORCE', 'QUINCE', 'DIECISÉIS', 'DIECISIETE', 'DIECIOCHO', 'DIECINUEVE'];
    $decenas = ['', '', 'VEINTE', 'TREINTA', 'CUARENTA', 'CINCUENTA', 'SESENTA', 'SETENTA', 'OCHENTA', 'NOVENTA'];
    $cientos = ['', 'CIENTO', 'DOSCIENTOS', 'TRESCIENTOS', 'CUATROCIENTOS', 'QUINIENTOS', 'SEISCIENTOS', 'SETECIENTOS', 'OCHOCIENTOS', 'NOVECIENTOS'];
    
    if ($n == 0) return 'CERO';
    if ($n == 100) return 'CIEN';
    
    $t = '';
    if ($n >= 1000000) {
        $millones = (int)($n / 1000000);
        $t .= numerosALetras($millones) . ' MILLÓN' . ($millones > 1 ? 'ES' : '') . ' ';
        $n %= 1000000;
    }
    if ($n >= 1000) {
        $miles = (int)($n / 1000);
        if ($miles == 1) $t .= 'MIL ';
        else $t .= numerosALetras($miles) . ' MIL ';
        $n %= 1000;
    }
    if ($n >= 100) {
        $c = (int)($n / 100);
        $t .= $cientos[$c] . ' ';
        $n %= 100;
    }
    if ($n >= 10 && $n <= 19) {
        $t .= $dieces[$n - 10] . ' ';
        $n = 0;
    } elseif ($n >= 20) {
        $d = (int)($n / 10);
        $t .= $decenas[$d];
        $n %= 10;
        if ($n > 0) $t .= ' Y ';
    }
    if ($n > 0) $t .= $unidades[$n] . ' ';
    return trim($t);
}

function mesNombre($mes) {
    $m = ['ENERO','FEBRERO','MARZO','ABRIL','MAYO','JUNIO','JULIO','AGOSTO','SEPTIEMBRE','OCTUBRE','NOVIEMBRE','DICIEMBRE'];
    return $m[(int)$mes - 1] ?? '';
}

/**
 * Texto legal para cláusula de actualización del alquiler según periodicidad (1–6 meses).
 *
 * @return array{adverbio: string, calculo: string, primer_periodo: string}
 */
function contrato_texto_actualizacion_ipc($meses) {
    $n = max(1, min(6, (int) $meses));
    $adverbios = [
        1 => 'mensualmente',
        2 => 'bimestralmente',
        3 => 'trimestralmente',
        4 => 'cuatrimestralmente',
        5 => 'cada cinco meses',
        6 => 'semestralmente',
    ];
    $adverbio = $adverbios[$n - 1] ?? 'bimestralmente';
    $calculos = [
        1 => 'La actualización mensual se calcula con el último mes de IPC disponible más el 1,5%.',
        2 => 'La actualización bimestral se calcula con los dos últimos meses de IPC disponibles más el 1,5%.',
        3 => 'La actualización trimestral se calcula con los tres últimos meses de IPC disponibles más el 1,5%.',
        4 => 'La actualización cuatrimestral se calcula con los cuatro últimos meses de IPC disponibles más el 1,5%.',
        5 => 'La actualización cada cinco meses se calcula con los cinco últimos meses de IPC disponibles más el 1,5%.',
        6 => 'La actualización semestral se calcula con los seis últimos meses de IPC disponibles más el 1,5%.',
    ];
    $calculo = $calculos[$n - 1] ?? $calculos[1];
    $primer = $n === 1
        ? 'el primer mes de vigencia del presente contrato'
        : 'los primeros ' . $n . ' meses de vigencia del presente contrato';
    return [
        'adverbio' => $adverbio,
        'calculo' => $calculo,
        'primer_periodo' => $primer,
    ];
}
?>
