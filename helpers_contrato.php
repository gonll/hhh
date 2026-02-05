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
?>
