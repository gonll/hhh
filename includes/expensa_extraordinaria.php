<?php
/**
 * Egreso cuenta como extraordinaria para liquidar/informes:
 * comprobante EXP EXTRAORDINARIA, o concepto que empieza con "Expensa extraordinaria" (cualquier comprobante).
 */
if (!function_exists('es_movimiento_expensa_extraordinaria')) {
    function es_movimiento_expensa_extraordinaria($comprobante, $concepto) {
        if (strtoupper(trim((string)$comprobante)) === 'EXP EXTRAORDINARIA') {
            return true;
        }
        $c = trim((string)$concepto);
        if ($c === '') {
            return false;
        }
        return (bool)preg_match('/^expensa\s+extraordinaria/ui', $c);
    }
}
