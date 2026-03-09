<?php
/**
 * Obtiene la clave interna de borrado desde la tabla config.
 * Si no existe la tabla o el registro, usa '4961' por defecto.
 * Siempre consulta la BD (sin caché estática) para tener el valor actual.
 */
function obtener_clave_borrado($conexion = null) {
    if (!$conexion && isset($GLOBALS['conexion'])) $conexion = $GLOBALS['conexion'];
    if ($conexion) {
        $r = @mysqli_query($conexion, "SELECT valor FROM config WHERE clave = 'clave_borrado' LIMIT 1");
        if ($r && $row = mysqli_fetch_assoc($r)) {
            $val = trim($row['valor'] ?? '');
            if ($val !== '') return $val;
        }
    }
    return '4961';
}
