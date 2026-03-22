<?php
/**
 * Extrae datos de un texto (obtenido por OCR de imagen o pegando) de detalle de ECheq.
 * Recibe POST texto=... y devuelve el mismo formato que extraer_echeq_pdf.php
 */
header('Content-Type: application/json; charset=utf-8');

include 'db.php';
include 'verificar_sesion.php';

if (!isset($_SESSION['acceso_nivel']) || (int)$_SESSION['acceso_nivel'] < 2) {
    echo json_encode(['ok' => false, 'error' => 'Sin permiso']);
    exit;
}

$texto = trim($_POST['texto'] ?? '');
if ($texto === '') {
    echo json_encode(['ok' => false, 'error' => 'No se recibió texto']);
    exit;
}

// Misma lógica de extracción que extraer_echeq_pdf.php
$resultado = [
    'ok' => true,
    'monto' => null,
    'emisor' => null,
    'cuit' => null,
    'fecha_pago' => null,
    'nro_echeq' => null,
    'concepto_sugerido' => null
];

if (preg_match('/(\d{2}-\d{8}-\d)/', $texto, $m)) {
    $resultado['cuit'] = $m[1];
}

if (preg_match('/\$\s*([\d.,]+)/', $texto, $m)) {
    $s = trim($m[1]);
    if (strpos($s, ',') !== false && strpos($s, '.') !== false) {
        $s = str_replace('.', '', $s);
        $s = str_replace(',', '.', $s);
    } elseif (strpos($s, ',') !== false) {
        $s = str_replace(',', '.', $s);
    }
    $resultado['monto'] = (float)preg_replace('/[^\d.]/', '', $s) ?: null;
}

if (preg_match('/(\d{1,2})[\/\-](\d{1,2})[\/\-](\d{4})/', $texto, $m)) {
    $resultado['fecha_pago'] = sprintf('%04d-%02d-%02d', (int)$m[3], (int)$m[2], (int)$m[1]);
}

if (preg_match('/(?:echeq|n[°º]|nro\.?|número|numero)\s*:?\s*(\d{4,})/i', $texto, $m)) {
    $resultado['nro_echeq'] = $m[1];
} elseif (preg_match('/\b(\d{8,})\b/', $texto, $m)) {
    $resultado['nro_echeq'] = $m[1];
}

// Emisor: nombre de empresa entre el monto ($) y el CUIT (ej: "Froneri Arg Sa", "Lacteos Ramolac S.a")
$cuitEsc = $resultado['cuit'] ? preg_quote($resultado['cuit'], '/') : '';
if ($cuitEsc) {
    if (preg_match('/\$\s*[\d.,]+\s*(.+?)\s*' . $cuitEsc . '/us', $texto, $m)) {
        $entreMontoYcuit = trim($m[1]);
        $lineas = preg_split('/[\r\n]+/', $entreMontoYcuit, -1, PREG_SPLIT_NO_EMPTY);
        foreach ($lineas as $lin) {
            $lin = trim(preg_replace('/\s+/', ' ', $lin));
            if (strlen($lin) < 4) continue;
            if (preg_match('/^(?:Ruta|Av\.?|Calle|Km\.?|N°|Estado|Fecha)/iu', $lin)) continue;
            if (preg_match('/\d{2}-\d{8}-\d/', $lin)) continue;
            if (preg_match('/(?:S\.?a\.?|S\.?A\.?|SRL|S\.?A\.?S\.?|Sa|SA)\s*$/iu', $lin) || strlen($lin) >= 8) {
                $resultado['emisor'] = $lin;
                break;
            }
        }
        if (empty($resultado['emisor']) && !empty($lineas)) {
            foreach ($lineas as $lin) {
                $lin = trim(preg_replace('/\s+/', ' ', $lin));
                if (strlen($lin) >= 5 && !preg_match('/^(?:Ruta|Av\.?|Calle|Km\.?|N°|Estado|Fecha)/iu', $lin)) {
                    $resultado['emisor'] = $lin;
                    break;
                }
            }
        }
    }
}
if (empty($resultado['emisor']) && preg_match('/(?:emisor|librador|titular)\s*:?\s*([^\n\r\d]{3,50})(?:\s+\d{2}-\d{8}-\d)?/iu', $texto, $m)) {
    $resultado['emisor'] = trim(preg_replace('/\s+/', ' ', $m[1]));
}
if (!empty($resultado['emisor'])) {
    $resultado['emisor'] = preg_replace('/^(?:Fecha de pago|Fecha cobro|Fecha|Estado)\s*/iu', '', $resultado['emisor']);
    $resultado['emisor'] = trim($resultado['emisor']);
}

$partes = [];
if ($resultado['emisor'] || $resultado['cuit']) {
    $emisorCuit = trim(($resultado['emisor'] ?? '') . ($resultado['cuit'] ? ' ' . $resultado['cuit'] : ''));
    if ($emisorCuit) $partes[] = $emisorCuit;
}
if ($resultado['nro_echeq']) $partes[] = 'N° echeq ' . $resultado['nro_echeq'];
if ($resultado['fecha_pago']) {
    $f = explode('-', $resultado['fecha_pago']);
    $partes[] = 'Fecha Cobro ' . $f[2] . '/' . $f[1] . '/' . $f[0];
}
$resultado['concepto_sugerido'] = 'COBRO VTA AZUCAR: ' . implode(', ', $partes);

echo json_encode($resultado);
