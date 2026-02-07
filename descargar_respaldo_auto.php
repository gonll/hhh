<?php
include 'verificar_sesion.php';

if (!isset($_GET['archivo'])) {
    header('HTTP/1.0 400 Bad Request');
    die('Archivo no especificado');
}

$archivo = basename($_GET['archivo']); // Prevenir path traversal
$directorio_respaldo = __DIR__ . DIRECTORY_SEPARATOR . 'respaldo_automatico';
$ruta_completa = $directorio_respaldo . DIRECTORY_SEPARATOR . $archivo;

// Verificar que el archivo existe y está en el directorio correcto
if (!file_exists($ruta_completa) || strpos(realpath($ruta_completa), realpath($directorio_respaldo)) !== 0) {
    header('HTTP/1.0 404 Not Found');
    die('Archivo no encontrado');
}

// Verificar que es un archivo de respaldo automático
if (strpos($archivo, 'respaldo_auto_') !== 0 || substr($archivo, -4) !== '.sql') {
    header('HTTP/1.0 403 Forbidden');
    die('Tipo de archivo no permitido');
}

// Descargar el archivo
header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="' . $archivo . '"');
header('Content-Length: ' . filesize($ruta_completa));
header('Cache-Control: must-revalidate');
header('Pragma: public');

readfile($ruta_completa);
exit;
