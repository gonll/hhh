<?php
/**
 * Descarga un PDF de liquidación de productos con el nombre asignado.
 * Parámetro: f=nombre_del_archivo.pdf
 */
include 'db.php';
include 'verificar_sesion.php';
if (isset($_SESSION['acceso_nivel']) && $_SESSION['acceso_nivel'] < 2) {
    header('Location: index.php?msg=sin_permiso');
    exit;
}

$f = isset($_GET['f']) ? trim($_GET['f']) : '';
if ($f === '' || preg_match('/[\/\\\\]/', $f)) {
    header('Location: gestionar_azucares.php?pdf_liq=error');
    exit;
}
$ruta = __DIR__ . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'pdf_liq_prod' . DIRECTORY_SEPARATOR . $f;
if (!file_exists($ruta) || !is_file($ruta)) {
    header('Location: gestionar_azucares.php?pdf_liq=error');
    exit;
}

$nombreSalida = $f;
if (!empty($_GET['guardar_como'])) {
    $gc = trim($_GET['guardar_como']);
    $gc = basename(str_replace(['\\', '/'], '', $gc));
    if ($gc !== '' && preg_match('/\.pdf$/i', $gc) && !preg_match('/[\/\\\\]/', $gc)) {
        $nombreSalida = $gc;
    }
}

while (ob_get_level() > 0) {
    ob_end_clean();
}

header('Content-Type: application/pdf');
header('X-Content-Type-Options: nosniff');
$fnEsc = str_replace(['\\', '"'], ['', "'"], $nombreSalida);
header('Content-Disposition: attachment; filename="' . $fnEsc . '"');
header('Content-Length: ' . filesize($ruta));
header('Cache-Control: private, no-cache, must-revalidate');
readfile($ruta);
exit;
