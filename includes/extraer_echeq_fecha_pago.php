<?php
/**
 * Fechas de eCheq desde texto del PDF / OCR: emisión y cobro (pago).
 *
 * @return array{emision: ?string, cobro: ?string} fechas en formato YYYY-MM-DD
 */
function extraer_fechas_echeq_desde_texto(string $texto): array
{
    $t = $texto;
    $validar = function (int $d, int $mes, int $a): bool {
        return $mes >= 1 && $mes <= 12 && $d >= 1 && $d <= 31 && $a >= 2000 && $a <= 2100;
    };
    $emision = null;
    $cobro = null;

    // --- Fecha de emisión (etiquetas) ---
    $reEmision = [
        '/(?:fecha\s*de\s*emis|fecha\s*emis|emis[ií]on|emis)\b[^0-9]{0,45}(\d{1,2})\s*[\.\/\-]\s*(\d{1,2})\s*[\.\/\-]\s*(\d{4})/iu',
        '/(?:fecha\s*de\s*libram|libram[ií]ento)\b[^0-9]{0,45}(\d{1,2})\s*[\.\/\-]\s*(\d{1,2})\s*[\.\/\-]\s*(\d{4})/iu',
    ];
    foreach ($reEmision as $re) {
        if (preg_match($re, $t, $m)) {
            $d = (int) $m[1];
            $mes = (int) $m[2];
            $a = (int) $m[3];
            if ($validar($d, $mes, $a)) {
                $emision = sprintf('%04d-%02d-%02d', $a, $mes, $d);
                break;
            }
        }
    }

    // --- Fecha de cobro / pago (etiquetas; lo que va al campo Fecha del movimiento) ---
    $reCobro = [
        '/(?:fecha\s*de\s*pago|fecha\s*pago|fecha\s*de\s*cobro|fecha\s*cobro|f\.?\s*pago|fecha\s*de\s*acreditac[ií]on|pago\s+efectivo)\b[^0-9]{0,45}(\d{1,2})\s*[\.\/\-]\s*(\d{1,2})\s*[\.\/\-]\s*(\d{4})/iu',
        '/\bpago\s*:?\s*(\d{1,2})\s*[\.\/\-]\s*(\d{1,2})\s*[\.\/\-]\s*(\d{4})/iu',
    ];
    foreach ($reCobro as $re) {
        if (preg_match($re, $t, $m)) {
            $d = (int) $m[1];
            $mes = (int) $m[2];
            $a = (int) $m[3];
            if ($validar($d, $mes, $a)) {
                $cobro = sprintf('%04d-%02d-%02d', $a, $mes, $d);
                break;
            }
        }
    }

    // --- Heurística: todas las fechas dd/mm/aaaa ---
    if (!preg_match_all('/\b(\d{1,2})[\.\/\-](\d{1,2})[\.\/\-](\d{4})\b/u', $t, $all, PREG_OFFSET_CAPTURE)) {
        return ['emision' => $emision, 'cobro' => $cobro];
    }

    $candidatas = [];
    for ($i = 0, $n = count($all[0]); $i < $n; $i++) {
        $candidatas[] = [
            'd' => (int) $all[1][$i][0],
            'm' => (int) $all[2][$i][0],
            'a' => (int) $all[3][$i][0],
            'off' => (int) $all[0][$i][1],
        ];
    }

    foreach ($candidatas as $c) {
        if (!$validar($c['d'], $c['m'], $c['a'])) {
            continue;
        }
        $start = max(0, $c['off'] - 120);
        $ctx = substr($t, $start, 140);
        if ($emision === null && preg_match('/emis|libram|librad|emis[ií]on/iu', $ctx)) {
            $emision = sprintf('%04d-%02d-%02d', $c['a'], $c['m'], $c['d']);
            continue;
        }
        if ($cobro === null && preg_match('/pago|cobro|acreditac/iu', $ctx) && !preg_match('/emis/iu', $ctx)) {
            $cobro = sprintf('%04d-%02d-%02d', $c['a'], $c['m'], $c['d']);
        }
    }

    // Orden típico en el PDF: primero emisión, después cobro/pago
    if ($emision === null || $cobro === null) {
        if (count($candidatas) >= 2) {
            $c0 = $candidatas[0];
            $c1 = $candidatas[1];
            if ($validar($c0['d'], $c0['m'], $c0['a']) && $validar($c1['d'], $c1['m'], $c1['a'])) {
                if ($emision === null) {
                    $emision = sprintf('%04d-%02d-%02d', $c0['a'], $c0['m'], $c0['d']);
                }
                if ($cobro === null) {
                    $cobro = sprintf('%04d-%02d-%02d', $c1['a'], $c1['m'], $c1['d']);
                }
            }
        } elseif (count($candidatas) === 1) {
            $c0 = $candidatas[0];
            if ($validar($c0['d'], $c0['m'], $c0['a'])) {
                $solo = sprintf('%04d-%02d-%02d', $c0['a'], $c0['m'], $c0['d']);
                if ($cobro === null) {
                    $cobro = $solo;
                }
                if ($emision === null) {
                    $emision = $solo;
                }
            }
        }
    }

    return ['emision' => $emision, 'cobro' => $cobro];
}

/**
 * Compatibilidad: solo fecha de cobro (campo Fecha del cobro en azúcar).
 */
function extraer_fecha_pago_echeq_desde_texto(string $texto): ?string
{
    $f = extraer_fechas_echeq_desde_texto($texto);

    return $f['cobro'];
}

/**
 * Arma el texto de concepto: emisor + CUIT, n° echeq, Fecha de emisión, Fecha de cobro.
 */
function echeq_armar_concepto_azucar(
    string $basePrefijo,
    ?string $emisor,
    ?string $cuit,
    ?string $nroEcheq,
    ?string $emisionYmd,
    ?string $cobroYmd
): string {
    $partes = [];
    $pri = trim((string) ($emisor ?? ''));
    if ($cuit) {
        $pri = trim($pri . ' ' . $cuit);
    }
    if ($pri !== '') {
        $partes[] = $pri;
    }
    if ($nroEcheq !== null && $nroEcheq !== '') {
        $partes[] = 'N° echeq ' . $nroEcheq;
    }
    if ($emisionYmd) {
        $e = explode('-', $emisionYmd);
        if (count($e) === 3) {
            $partes[] = 'Fecha de emisión ' . $e[2] . '/' . $e[1] . '/' . $e[0];
        }
    }
    if ($cobroYmd) {
        $c = explode('-', $cobroYmd);
        if (count($c) === 3) {
            $partes[] = 'Fecha de cobro ' . $c[2] . '/' . $c[1] . '/' . $c[0];
        }
    }

    return $basePrefijo . implode(', ', $partes);
}
