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

require_once __DIR__ . '/includes/extraer_echeq_fecha_pago.php';

// Misma lógica de extracción que extraer_echeq_pdf.php
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

$fechas = extraer_fechas_echeq_desde_texto($texto);
$resultado['fecha_emision'] = $fechas['emision'];
$resultado['fecha_cobro'] = $fechas['cobro'];
$resultado['fecha_pago'] = $fechas['cobro'];

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

$resultado['concepto_sugerido'] = echeq_armar_concepto_azucar(
    'COBRO VTA AZUCAR: ',
    $resultado['emisor'] ?? null,
    $resultado['cuit'] ?? null,
    $resultado['nro_echeq'] ?? null,
    $fechas['emision'],
    $fechas['cobro']
);

echo json_encode($resultado);
