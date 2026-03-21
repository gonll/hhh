<?php
/**
 * Autoloader simple para Smalot\PdfParser (instalado manualmente)
 * PSR-0: Smalot\PdfParser\* => src/
 */
spl_autoload_register(function ($class) {
    $prefix = 'Smalot\\PdfParser\\';
    $baseDir = __DIR__ . '/smalot/pdfparser/';
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }
    $relativeClass = substr($class, $len);
    $file = $baseDir . 'src/' . str_replace('\\', '/', $class) . '.php';
    if (file_exists($file)) {
        require $file;
    }
});
