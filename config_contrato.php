<?php
/**
 * Ruta donde se guardan los contratos generados.
 * Descomentá y editá la línea de abajo para usar "Mis Documentos":
 *   $RUTA_CONTRATOS = 'C:\\Users\\USUARIO\\Documents\\contratos de alquiler';
 * Reemplazá USUARIO por tu usuario de Windows.
 */
// Ruta a OneDrive/Documentos (ajustá USUARIO si es otro)
$RUTA_CONTRATOS = 'C:\\Users\\USUARIO\\OneDrive\\Documentos\\contratos de alquiler';
if ($RUTA_CONTRATOS === '' || !@is_dir(dirname($RUTA_CONTRATOS))) {
    $prof = getenv('USERPROFILE');
    $RUTA_CONTRATOS = ($prof ? rtrim($prof, '\\/') . DIRECTORY_SEPARATOR : '') . 'OneDrive\\Documentos\\contratos de alquiler';
}
if (!@is_dir($RUTA_CONTRATOS) && !@mkdir($RUTA_CONTRATOS, 0755, true)) {
    $RUTA_CONTRATOS = __DIR__ . DIRECTORY_SEPARATOR . 'contratos_alquiler';
}
?>
