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

header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="' . addslashes($f) . '"');
header('Content-Length: ' . filesize($ruta));
header('Cache-Control: no-cache, must-revalidate');
readfile($ruta);
exit;
