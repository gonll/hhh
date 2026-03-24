<?php
/**
 * Script de depuración: muestra el texto extraído del PDF y los datos detectados.
 * Sin parámetro: lista todos los PDF para elegir cuál analizar.
 * Con ?f=: analiza el PDF indicado.
 */
header('Content-Type: text/html; charset=utf-8');

$dir_base = __DIR__ . '/uploads/pdf_liq_prod';
$dir_custom = trim($_GET['dir'] ?? '');
if ($dir_custom !== '') {
    $dir_base = __DIR__ . '/' . str_replace(['../', '..\\'], '', $dir_custom);
}

$ruta = $_GET['f'] ?? '';
if (empty($ruta)) {
    // Buscar PDFs en la ruta (y subcarpetas)
    $pdfs = [];
    if (is_dir($dir_base)) {
        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir_base, RecursiveDirectoryIterator::SKIP_DOTS));
        foreach ($it as $f) {
            if ($f->isFile() && strtolower($f->getExtension()) === 'pdf') {
                $pdfs[] = $f->getPathname();
            }
        }
    }
    if (empty($pdfs)) {
        die('<p>No hay PDF en ' . htmlspecialchars($dir_custom ?: 'uploads/pdf_liq_prod') . '</p>');
    }
    usort($pdfs, fn($a,$b) => filemtime($b) - filemtime($a));
    echo '<!DOCTYPE html><html><head><meta charset="utf-8"><title>Elegir PDF</title></head><body>';
    echo '<h2>Seleccione el PDF a analizar</h2>';
    echo '<p><form method="get" style="margin-bottom:1em">';
    echo 'Buscar en ruta: <input type="text" name="dir" value="' . htmlspecialchars($dir_custom ?: 'uploads/pdf_liq_prod') . '" size="40" placeholder="uploads/pdf_liq_prod"> ';
    echo '<button type="submit">Buscar</button>';
    echo '</form></p>';
    echo '<p><strong>Ruta actual:</strong> ' . htmlspecialchars($dir_custom ?: 'uploads/pdf_liq_prod') . ' <small>(' . count($pdfs) . ' PDF encontrados)</small></p>';
    echo '<ul>';
    foreach ($pdfs as $p) {
        $rel = str_replace(__DIR__ . DIRECTORY_SEPARATOR, '', $p);
        $rel = str_replace(DIRECTORY_SEPARATOR, '/', $rel);
        $nom = basename($p);
        $fecha = date('Y-m-d H:i', filemtime($p));
        echo '<li><a href="?f=' . urlencode($rel) . '">' . htmlspecialchars($nom) . '</a> <small>(' . $fecha . ')</small></li>';
    }
    echo '</ul></body></html>';
    exit;
}

$abs = __DIR__ . '/' . str_replace('/', DIRECTORY_SEPARATOR, $ruta);
if (!file_exists($abs)) {
    die('<p>Archivo no encontrado: ' . htmlspecialchars($ruta) . '</p>');
}

$autoload = __DIR__ . '/vendor/autoload.php';
$texto = '';
$error = '';
try {
    require_once $autoload;
    $parser = new \Smalot\PdfParser\Parser();
    $pdf = $parser->parseFile($abs);
    $texto = $pdf->getText();
} catch (Exception $e) {
    $error = $e->getMessage();
}

include_once __DIR__ . '/extraer_datos_liq_prod_pdf.php';
$datos = extraerDatosLiqProdPdf($abs);

?>
<!DOCTYPE html>
<html>
<head><meta charset="utf-8"><title>Debug PDF</title></head>
<body>
<?php $volver_dir = dirname($ruta); $volver = ($volver_dir !== '.' && $volver_dir !== '') ? '?dir=' . urlencode($volver_dir) : '?'; ?>
<p><a href="<?= $volver ?>">← Volver a la lista de PDF</a></p>
<h2>Depuración PDF: <?= htmlspecialchars($ruta) ?></h2>
<?php if ($error): ?>
<p style="color:red">Error: <?= htmlspecialchars($error) ?></p>
<?php endif; ?>

<h3>Texto extraído (primeros 3000 caracteres)</h3>
<pre style="background:#f5f5f5; padding:10px; white-space:pre-wrap; word-break:break-all; max-height:400px; overflow:auto;"><?= htmlspecialchars(mb_substr($texto, 0, 3000)) ?: '(vacío - PDF puede ser escaneado/imagen)' ?></pre>

<h3>Datos extraídos</h3>
<ul>
<li><strong>emisor:</strong> <?= $datos['emisor'] !== null ? htmlspecialchars($datos['emisor']) : '(vacío)' ?></li>
<li><strong>numero / numero_factura:</strong> <?= $datos['numero'] ?? $datos['numero_factura'] ?? '(vacío)' ?></li>
<li><strong>fecha_emision:</strong> <?= $datos['fecha_emision'] ?? '(vacío)' ?></li>
<li><strong>nombre_sugerido:</strong> <?= $datos['nombre_sugerido'] ?? '(vacío)' ?></li>
</ul>
</body>
</html>
