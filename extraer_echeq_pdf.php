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

require_once __DIR__ . '/includes/extraer_echeq_fecha_pago.php';

// Extraer datos con regex (ajustar patrones según formato real del PDF)
$resultado = [
    'ok' => true,
    'monto' => null,
    'emisor' => null,
    'cuit' => null,
    'fecha_emision' => null,
    'fecha_cobro' => null,
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

$fechas = extraer_fechas_echeq_desde_texto($texto);
$resultado['fecha_emision'] = $fechas['emision'];
$resultado['fecha_cobro'] = $fechas['cobro'];
$resultado['fecha_pago'] = $fechas['cobro'];

// Número echeq: buscar patrones como "N° 12345", "Número: 12345", "ECHEQ 12345"
if (preg_match('/(?:echeq|n[°º]|nro\.?|número|numero)\s*:?\s*(\d{4,})/i', $texto, $m)) {
    $resultado['nro_echeq'] = $m[1];
} elseif (preg_match('/\b(\d{8,})\b/', $texto, $m)) {
    $resultado['nro_echeq'] = $m[1];
}

// Emisor: nombre de empresa entre el monto ($) y el CUIT (ej: "Froneri Arg Sa", "Lacteos Ramolac S.a")
// Excluir direcciones como "Ruta Panamericana Km"
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

$resultado['concepto_sugerido'] = echeq_armar_concepto_azucar(
    'COBRO VTA AZUCAR: ',
    $resultado['emisor'] ?? null,
    $resultado['cuit'] ?? null,
    $resultado['nro_echeq'] ?? null,
    $fechas['emision'],
    $fechas['cobro']
);

echo json_encode($resultado);
