<?php
/**
 * Extrae monto y número de certificado desde PDF de retención Impuesto a las Ganancias (SICORE/AFIP o similar).
 * Requiere: composer require smalot/pdfparser
 * El n° de certificado no se agrega al concepto sugerido (solo monto + texto fijo).
 */
header('Content-Type: application/json; charset=utf-8');

include 'db.php';
include 'verificar_sesion.php';

if (!isset($_SESSION['acceso_nivel']) || (int) $_SESSION['acceso_nivel'] < 2) {
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
        'error' => 'Falta la librería PDF. En la carpeta del proyecto: composer require smalot/pdfparser',
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

$resultado = [
    'ok' => true,
    'monto' => null,
    'nro_certificado' => null,
    'concepto_sugerido' => null,
];

$t = $texto;
$tNorm = preg_replace('/\s+/u', ' ', $t);

function parseMontoAr(string $s): ?float
{
    $s = trim($s);
    if ($s === '') {
        return null;
    }
    if (strpos($s, ',') !== false && strpos($s, '.') !== false) {
        $s = str_replace('.', '', $s);
        $s = str_replace(',', '.', $s);
    } elseif (strpos($s, ',') !== false) {
        $s = str_replace(',', '.', $s);
    }

    $v = (float) preg_replace('/[^\d.]/', '', $s);

    return $v > 0 ? $v : null;
}

// --- Número de certificado (para referencia/JSON, no se pone en concepto) ---
$reCert = [
    '/(?:n[°º]?\s*(?:de\s*)?certificado|certificado\s*n[°º]|comprobante\s*(?:de\s*retenc[ií]on|n[°º])|c[oó]digo\s*de?\s*comprobante|n[°º]\s*comprobante|sicore)\s*[:#]?\s*([\d\.\-]{4,32})/iu',
    '/certificado[^0-9\n]{0,30}(\d{8,20})/iu',
    '/retenci[oó]n[^0-9\n]{0,50}(?:n[°º]?\s*)?([0-9][\d\.\-]{5,20}\d)/iu',
];
foreach ($reCert as $re) {
    if (preg_match($re, $tNorm, $m)) {
        $ref = trim(preg_replace('/[^\d]/', '', $m[1]));
        if (strlen($ref) >= 4) {
            $resultado['nro_certificado'] = $ref;
            break;
        }
    }
}
if (empty($resultado['nro_certificado']) && preg_match_all('/\b(\d{10,20})\b/u', $t, $all)) {
    $posG = stripos($t, 'retenc') !== false ? stripos($t, 'retenc') : 0;
    $best = null;
    $bestD = 999999;
    foreach ($all[0] as $i => $num) {
        $p = strpos($t, $num);
        if ($p === false) {
            continue;
        }
        if (strlen($num) >= 4 && (stripos($t, 'cuit', $p - 30) === false || strlen($num) > 11)) {
            $d = $posG > 0 ? abs($p - $posG) : $p;
            if ($d < $bestD) {
                $bestD = $d;
                $best = $num;
            }
        }
    }
    if ($best !== null) {
        $resultado['nro_certificado'] = $best;
    }
}

// --- Monto: mismo criterio que la primera versión (sí capturaba el importe) ---
$reMonto = [
    '/(?:importe\s*(?:de\s*la\s*)?retenc|monto\s*reten|total\s*retenc|retenc[ií]on\s*(?:\(|de)?\s*gananc|impuesto\s*reten|importe)\s*[^$0-9\n]{0,15}\$?\s*([\d\.,]+)/iu',
    '/\$?\s*([\d]{1,3}(?:\.\d{3})+,\d{2})\b/u',
    '/\$?\s*([\d]+,\d{2})\b/u',
];
foreach ($reMonto as $re) {
    if (preg_match($re, $t, $m)) {
        $v = parseMontoAr($m[1]);
        if ($v !== null) {
            $resultado['monto'] = round($v, 2);
            break;
        }
    }
}
if ($resultado['monto'] === null && preg_match_all('/\$\s*([\d\.,]+)/', $t, $mm)) {
    $max = 0.0;
    foreach ($mm[1] as $s) {
        $v = parseMontoAr($s);
        if ($v !== null && $v > $max) {
            $max = $v;
        }
    }
    if ($max > 0) {
        $resultado['monto'] = round($max, 2);
    }
}

// Concepto: sin n° de certificado (solo retención + importe si hay monto)
$partes = ['COBRO VTA AZUCAR: RET. IMP. GANANCIAS'];
if ($resultado['monto'] !== null) {
    $partes[] = 'Importe retenido $' . number_format($resultado['monto'], 2, ',', '.');
}
$resultado['concepto_sugerido'] = implode(' — ', $partes);

echo json_encode($resultado, JSON_UNESCAPED_UNICODE);
