<?php
/**
 * Extrae datos de un PDF de detalle de ECheq (monto, emisor, CUIT, fecha pago, número).
 * Requiere: composer require smalot/pdfparser
 */
header('Content-Type: application/json; charset=utf-8');

include 'db.php';
include 'verificar_sesion.php';

if (!isset($_SESSION['acceso_nivel']) || (int)$_SESSION['acceso_nivel'] < 2) {
    echo json_encode(['ok' => false, 'error' => 'Sin permiso']);
    exit;
}

if (!isset($_FILES['pdf']) || $_FILES['pdf']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['ok' => false, 'error' => 'No se recibió el archivo PDF o hubo error en la subida']);
    exit;
}

$tmp = $_FILES['pdf']['tmp_name'];
if (!is_uploaded_file($tmp) || !file_exists($tmp)) {
    echo json_encode(['ok' => false, 'error' => 'Archivo no válido']);
    exit;
}

$autoload = __DIR__ . '/vendor/autoload.php';
if (!file_exists($autoload)) {
    echo json_encode([
        'ok' => false,
        'error' => 'Falta la librería PDF. Ejecute en la carpeta del proyecto: composer require smalot/pdfparser'
    ]);
    exit;
}

require_once $autoload;

try {
    $parser = new \Smalot\PdfParser\Parser();
    $pdf = $parser->parseFile($tmp);
    $texto = $pdf->getText();
} catch (Exception $e) {
    echo json_encode(['ok' => false, 'error' => 'No se pudo leer el PDF: ' . $e->getMessage()]);
    exit;
}

// Extraer datos con regex (ajustar patrones según formato real del PDF)
$resultado = [
    'ok' => true,
    'monto' => null,
    'emisor' => null,
    'cuit' => null,
    'fecha_pago' => null,
    'nro_echeq' => null,
    'concepto_sugerido' => null
];

// CUIT: XX-XXXXXXXX-X
if (preg_match('/(\d{2}-\d{8}-\d)/', $texto, $m)) {
    $resultado['cuit'] = $m[1];
}

// Monto: $ 1.234,56 (argentino) o $ 1234.56
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

// Fecha: dd/mm/yyyy o dd-mm-yyyy
if (preg_match('/(\d{1,2})[\/\-](\d{1,2})[\/\-](\d{4})/', $texto, $m)) {
    $resultado['fecha_pago'] = sprintf('%04d-%02d-%02d', (int)$m[3], (int)$m[2], (int)$m[1]);
}

// Número echeq: buscar patrones como "N° 12345", "Número: 12345", "ECHEQ 12345"
if (preg_match('/(?:echeq|n[°º]|nro\.?|número|numero)\s*:?\s*(\d{4,})/i', $texto, $m)) {
    $resultado['nro_echeq'] = $m[1];
} elseif (preg_match('/\b(\d{8,})\b/', $texto, $m)) {
    $resultado['nro_echeq'] = $m[1];
}

// Emisor: nombre de empresa (ej: "Lacteos Ramolac S.a") que aparece antes de "Estado" y CUIT
$cuitEsc = $resultado['cuit'] ? preg_quote($resultado['cuit'], '/') : '';
if ($cuitEsc && preg_match('/([A-Za-zÁ-ú][A-Za-zÁ-ú0-9\s\.\-]{4,55}?(?:S\.?a\.?|S\.?A\.?|SRL|S\.?A\.?S\.?)?)\s+Estado\s+' . $cuitEsc . '/us', $texto, $m)) {
    $resultado['emisor'] = trim(preg_replace('/\s+/', ' ', $m[1]));
} elseif (preg_match('/(?:emisor|librador|titular)\s*:?\s*([^\n\r\d]{3,50})(?:\s+\d{2}-\d{8}-\d)?/iu', $texto, $m)) {
    $resultado['emisor'] = trim(preg_replace('/\s+/', ' ', $m[1]));
} elseif ($cuitEsc && preg_match('/([A-Za-zÁ-ú][A-Za-zÁ-ú\s\.\-]{5,55})\s+' . $cuitEsc . '/u', $texto, $m)) {
    $resultado['emisor'] = trim(preg_replace('/\s+/', ' ', $m[1]));
}
// Quitar etiquetas que no son nombre (ej: "Fecha de pago", "Estado")
if (!empty($resultado['emisor'])) {
    $resultado['emisor'] = preg_replace('/^(?:Fecha de pago|Fecha cobro|Fecha|Estado)\s*/iu', '', $resultado['emisor']);
    $resultado['emisor'] = trim($resultado['emisor']);
}

// Concepto sugerido: COBRO VTA AZUCAR: emisor CUIT, N° echeq [número], Fecha Cobro [fecha]
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
