<?php
/**
 * Extrae emisor, número y fecha desde un nombre de archivo (ej. COCO_Group_SRL_Fact00002281_2025-11-28.pdf)
 */
function extraerDatosDesdeNombreArchivo($nombreArchivo) {
    $r = ['emisor' => null, 'numero' => null, 'fecha_emision' => null];
    $sinExt = pathinfo($nombreArchivo, PATHINFO_FILENAME);
    if (empty($sinExt)) return $r;
    $partes = preg_split('/_+/', $sinExt, -1, PREG_SPLIT_NO_EMPTY);
    foreach ($partes as $i => $p) {
        if (preg_match('/^Fact(\d+)$/i', $p, $m)) {
            $r['numero'] = $m[1];
        } elseif (preg_match('/^\d{4}-\d{2}-\d{2}$/', $p)) {
            $r['fecha_emision'] = $p;
        } elseif (!is_numeric($p) && !preg_match('/^Fact\d+$/i', $p) && strlen($p) > 2) {
            $r['emisor'] = ($r['emisor'] ? $r['emisor'] . '_' : '') . $p;
        }
    }
    return $r;
}

/**
 * Extrae del PDF de liquidación de productos: emisor, número liquidación, fecha emisión.
 * Retorna array con keys: emisor, numero, fecha_emision, nombre_sugerido
 * Construye nombre: Emisor_FactNumero_Fecha_timestamp.pdf (ej. COCO_Group_SRL_Fact00002281_2025-11-28_xxx.pdf)
 */
function extraerDatosLiqProdPdf($rutaPdf) {
    $resultado = ['emisor' => null, 'numero' => null, 'numero_factura' => null, 'fecha_emision' => null, 'nombre_sugerido' => null];
    if (!file_exists($rutaPdf)) return $resultado;

    $autoload = __DIR__ . '/vendor/autoload.php';
    if (!file_exists($autoload)) return $resultado;

    try {
        require_once $autoload;
        $parser = new \Smalot\PdfParser\Parser();
        $pdf = $parser->parseFile($rutaPdf);
        $texto = $pdf->getText();
        $metadatos = method_exists($pdf, 'getDetails') ? $pdf->getDetails() : [];
    } catch (Exception $e) {
        return $resultado;
    }

    // Intentar texto por páginas si el global está vacío
    if (empty($texto) && method_exists($pdf, 'getPages')) {
        $pags = $pdf->getPages();
        $bloques = [];
        foreach ($pags as $pag) {
            $bloques[] = $pag->getText();
        }
        $texto = implode("\n", $bloques);
    }

    if (empty($texto) && empty($metadatos)) return $resultado;

    // Asegurar UTF-8 (algunos PDFs extraen en Latin-1)
    $enc = mb_detect_encoding($texto, ['UTF-8', 'ISO-8859-1', 'Windows-1252'], true);
    if ($enc && $enc !== 'UTF-8') {
        $texto = mb_convert_encoding($texto, 'UTF-8', $enc);
    }

    // Normalizar texto
    $texto = preg_replace('/\s+/u', ' ', (string)$texto);
    $texto = preg_replace('/[\x{2013}\x{2014}\x{2212}]/u', '-', $texto);
    $texto = str_replace(['&amp;', '&#38;'], '&', $texto);
    $texto_lower = mb_strtolower($texto);

    // --- EMISOR ---
    // 1. Etiquetas explícitas
    if (preg_match('/(?:razón\s*social|razon\s*social|emisor|proveedor|liquidador|empresa|denominación|denominacion)\s*:?\s*([^\n\r]{3,80})/iu', $texto, $m)) {
        $r = trim(preg_replace('/\s+/', ' ', $m[1]));
        $r = preg_replace('/\d{2}-\d{8}-\d.*$/u', '', $r);
        if (strlen(trim($r)) > 3) $resultado['emisor'] = trim($r);
    }
    // 2. CO&CO Group S.R.L. y similares (nombre + Group + S.R.L.)
    if (empty($resultado['emisor']) && preg_match('/((?:CO\s*&\s*CO|CO&CO|CO\s+CO|COCO)\s+Group\s+S\.?R\.?L\.?)/iu', $texto, $m)) {
        $resultado['emisor'] = trim(preg_replace('/\s+/', ' ', $m[1]));
    }
    if (empty($resultado['emisor']) && preg_match('/([A-Za-z0-9][A-Za-z0-9\s\.\-&]{3,45})\s+Group\s+(S\.?R\.?L\.?|S\.?A\.?)/iu', $texto, $m)) {
        $resultado['emisor'] = trim($m[1]) . ' Group ' . trim($m[2]);
    }
    // 3. Cualquier "Nombre S.R.L." o "Nombre S.A." (evitar matches genéricos)
    if (empty($resultado['emisor']) && preg_match('/([A-Za-z0-9&ÁÉÍÓÚáéíóúÑñ][A-Za-z0-9\s&\.\-]{4,50})\s+(?:S\.?R\.?L\.?|S\.?A\.?)(?:\s|$|[^\pL])/u', $texto, $m)) {
        $cand = trim(preg_replace('/\s+/', ' ', $m[1]));
        if (!preg_match('/^(cuit|total|importe|fecha|neto|iva|peso)$/i', $cand) && strlen($cand) > 4) {
            $resultado['emisor'] = $cand . (strpos($m[0], 'S.A') !== false ? ' S.A.' : ' S.R.L.');
        }
    }
    // 4. Junto a CUIT
    if (empty($resultado['emisor']) && preg_match('/(?:cuit|c\.?u\.?i\.?t\.?)\s*:?\s*\d[\d\s\-\.]{8,}\s+([A-Za-zÁÉÍÓÚáéíóúÑñ0-9\s\.\-&]{6,55})/u', $texto, $m)) {
        $r = trim(preg_replace('/\s+/', ' ', $m[1]));
        $r = preg_replace('/\d{2}-\d{8}-\d.*$/u', '', $r);
        if (strlen($r) > 4) $resultado['emisor'] = trim($r);
    }
    // 5. Metadata del PDF (Author, Title, Producer)
    if (empty($resultado['emisor']) && !empty($metadatos) && is_array($metadatos)) {
        $meta_lower = array_change_key_case($metadatos, CASE_LOWER);
        foreach (['author', 'title', 'subject', 'creator', 'producer'] as $k) {
            $v = trim((string)($meta_lower[$k] ?? $metadatos[$k] ?? ''));
            if (strlen($v) > 4 && preg_match('/[a-z]/i', $v) && !preg_match('/^(adobe|acrobat|pdf|foxit|nitro)/i', $v)) {
                $resultado['emisor'] = $v;
                break;
            }
        }
    }
    // 6. Primera línea con aspecto de razón social (antes de LIQUIDO PRODUCTO / comprobante)
    if (empty($resultado['emisor']) && preg_match('/^([A-Za-z0-9&][A-Za-z0-9\s&\.\-]{5,50})\s+(?:S\.?R\.?L\.?|S\.?A\.?)\b/u', $texto, $m)) {
        $resultado['emisor'] = trim($m[0]);
    }

    // --- NÚMERO (factura / comprobante) ---
    // Nº 0001 - 00002281 / N° 0001-00002281 / Nro 0001 - 00002281
    if (preg_match('/(?:n[°º\x{00BA}]?\s*|numero\s*|nro\.?\s*|comp\.?\s*)(\d{1,4})\s*-\s*(\d{5,})/iu', $texto, $m)) {
        $resultado['numero_factura'] = $m[2];
    } elseif (preg_match('/(\d{3,4})\s*-\s*(\d{5,})/', $texto, $m)) {
        $resultado['numero_factura'] = $m[2];
    } elseif (preg_match('/(?:factura|n[°º]?\s*factura|comprobante)\s*:?\s*(\d[\d\-\.\s]{4,})/iu', $texto, $m)) {
        $resultado['numero_factura'] = preg_replace('/[^\d]/', '', $m[1]);
    } elseif (preg_match('/(?:liquidación|liquidacion|comprobante)\s*(?:n[°º]?\.?)?\s*:?\s*(\d{5,})/iu', $texto, $m)) {
        $resultado['numero_factura'] = $m[1];
    } elseif (preg_match('/(?:punto\s*de\s*venta|p\.?\s*v\.?)\s*:?\s*(\d+)\s*(?:numero\s*:?\s*)?(\d{5,})/iu', $texto, $m)) {
        $resultado['numero_factura'] = $m[2];
    } elseif (preg_match('/(?:n[°º]?\s*|numero\s*|nro\.?\s*:?\s*)(\d{5,})/iu', $texto, $m)) {
        $resultado['numero_factura'] = $m[1];
    } elseif (preg_match('/\b(\d{8})\b/', $texto, $m)) {
        $resultado['numero_factura'] = $m[1];
    }
    $resultado['numero'] = $resultado['numero_factura'] ?? null;

    // --- FECHA (emisión) ---
    // Preferir fecha cercana a "fecha emision" / "emitido"
    if (preg_match('/(?:fecha\s*(?:de\s*)?emisi[oó]n|fecha\s*:?|emitido\s*:?)\s*(\d{1,2})\s*[\/\-]\s*(\d{1,2})\s*[\/\-]\s*(\d{2,4})/iu', $texto, $m)) {
        $y = (int)$m[3];
        if ($y < 100) $y += ($y > 50 ? 1900 : 2000);
        $resultado['fecha_emision'] = sprintf('%04d-%02d-%02d', $y, (int)$m[2], (int)$m[1]);
    } elseif (preg_match('/(\d{1,2})\s*[\/\-]\s*(\d{1,2})\s*[\/\-]\s*(\d{2,4})/', $texto, $m)) {
        $y = (int)$m[3];
        if ($y < 100) $y += ($y > 50 ? 1900 : 2000);
        $resultado['fecha_emision'] = sprintf('%04d-%02d-%02d', $y, (int)$m[2], (int)$m[1]);
    }
    // Metadata CreationDate / CreatedOn
    if (empty($resultado['fecha_emision']) && !empty($metadatos) && is_array($metadatos)) {
        $meta_lower = array_change_key_case($metadatos, CASE_LOWER);
        $d = (string)($meta_lower['creationdate'] ?? $meta_lower['createdon'] ?? $metadatos['CreationDate'] ?? '');
        if (preg_match('/(\d{4})-(\d{2})-(\d{2})/', $d, $m)) {
            $resultado['fecha_emision'] = $m[1] . '-' . $m[2] . '-' . $m[3];
        }
    }

    // --- NOMBRE SUGERIDO: Emisor_FactNumero_Fecha.pdf ---
    $partes = [];
    if (!empty($resultado['emisor'])) {
        $e = str_replace('&', '_', $resultado['emisor']);
        $e = preg_replace('/[^\pL\pN\s\-\.]/u', '', $e);
        $e = str_replace('.', '', $e);
        $e = preg_replace('/\s+/', '_', trim($e));
        $partes[] = substr($e, 0, 50);
    }
    if (!empty($resultado['numero'])) {
        $partes[] = 'Fact' . preg_replace('/[^\d]/', '', $resultado['numero']);
    }
    if (!empty($resultado['fecha_emision'])) {
        $partes[] = $resultado['fecha_emision'];
    }
    if (!empty($partes)) {
        $resultado['nombre_sugerido'] = implode('_', $partes) . '.pdf';
    }

    return $resultado;
}
