<?php
/**
 * Usuario técnico para movimientos de nivelación solo en el libro Transferencias (no aparece en la lista).
 */
if (!defined('APELLIDO_USUARIO_LIBRO_TRANSF')) {
    define('APELLIDO_USUARIO_LIBRO_TRANSF', 'TRANSFERENCIAS (LIBRO)');
}

/**
 * Devuelve el id de usuario para el libro; lo crea si no existe.
 */
function transferencias_libro_asegurar_usuario($conexion) {
    $esc = mysqli_real_escape_string($conexion, APELLIDO_USUARIO_LIBRO_TRANSF);
    $r = mysqli_query($conexion, "SELECT id FROM usuarios WHERE apellido = '$esc' LIMIT 1");
    if ($r && $row = mysqli_fetch_assoc($r)) {
        return (int)$row['id'];
    }
    $dni = mysqli_real_escape_string($conexion, 'LIB-TRANSF');
    $sql = "INSERT INTO usuarios (apellido, dni, cuit, domicilio, email, celular, consorcio) 
            VALUES ('$esc', '$dni', '', '', '', '', NULL)";
    if (!mysqli_query($conexion, $sql)) {
        return 0;
    }
    return (int)mysqli_insert_id($conexion);
}

/** Solo lectura: 0 si aún no existe el usuario técnico */
function transferencias_libro_id_existente($conexion) {
    $esc = mysqli_real_escape_string($conexion, APELLIDO_USUARIO_LIBRO_TRANSF);
    $r = mysqli_query($conexion, "SELECT id FROM usuarios WHERE apellido = '$esc' LIMIT 1");
    if ($r && $row = mysqli_fetch_assoc($r)) {
        return (int)$row['id'];
    }
    return 0;
}
