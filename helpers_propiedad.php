<?php
/**
 * Quita ciudad y provincia del texto de la propiedad para usar en conceptos (garantía, proporcional, liquidación alquiler).
 * Elimina al final del texto: ", TUCUMAN", ", S. M. DE TUCUMAN", ", SAN MIGUEL DE TUCUMAN" y similares.
 */
function omitir_ciudad_provincia($texto) {
    if ($texto === null || $texto === '') return '';
    $t = trim($texto);
    // Quitar provincia y ciudad al final (varias pasadas por si hay "..., CIUDAD, PROVINCIA")
    $patrones = [
        '/,\s*TUCUMAN\s*$/i',
        '/,\s*S\.\s*M\.\s*DE\s*TUCUMAN\s*$/i',
        '/,\s*SAN\s*MIGUEL\s*DE\s*TUCUMAN\s*$/i',
    ];
    foreach ($patrones as $p) {
        $t = preg_replace($p, '', $t);
        $t = trim($t, " \t,");
    }
    return $t;
}
