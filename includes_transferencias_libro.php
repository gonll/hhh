<?php
/**
 * Usuario técnico para movimientos de nivelación solo en el libro Transferencias (no aparece en la lista).
 * Sistema principal: una fila con acceso_creador_id NULL.
 * Sofía: fila propia con acceso_creador_id = su id de acceso (aislada del libro global del admin).
 */
if (!defined('APELLIDO_USUARIO_LIBRO_TRANSF')) {
    define('APELLIDO_USUARIO_LIBRO_TRANSF', 'TRANSFERENCIAS (LIBRO)');
}

/**
 * Devuelve el id de usuario para el libro; lo crea si no existe.
 */
function transferencias_libro_asegurar_usuario($conexion)
{
    require_once __DIR__ . '/helpers_tenant_inmobiliaria.php';
    tenant_inmob_asegurar_esquema($conexion);
    $esc = mysqli_real_escape_string($conexion, APELLIDO_USUARIO_LIBRO_TRANSF);

    if (tenant_inmob_es_sofia()) {
        $sid = tenant_inmob_id_acceso_sofia_efectivo($conexion);
        if ($sid <= 0) {
            return 0;
        }
        $sid = (int) $sid;
        $r = mysqli_query($conexion, "SELECT id FROM usuarios WHERE apellido = '$esc' AND acceso_creador_id = $sid LIMIT 1");
        if ($r && $row = mysqli_fetch_assoc($r)) {
            return (int) $row['id'];
        }
        $dni = mysqli_real_escape_string($conexion, 'LIB-TRANSF');
        $sql = "INSERT INTO usuarios (acceso_creador_id, apellido, dni, cuit, domicilio, email, celular, consorcio) 
                VALUES ($sid, '$esc', '$dni', '', '', '', '', NULL)";
        if (!mysqli_query($conexion, $sql)) {
            return 0;
        }

        return (int) mysqli_insert_id($conexion);
    }

    $r = mysqli_query($conexion, "SELECT id FROM usuarios WHERE apellido = '$esc' AND acceso_creador_id IS NULL LIMIT 1");
    if ($r && $row = mysqli_fetch_assoc($r)) {
        return (int) $row['id'];
    }
    $dni = mysqli_real_escape_string($conexion, 'LIB-TRANSF');
    $sql = "INSERT INTO usuarios (apellido, dni, cuit, domicilio, email, celular, consorcio) 
            VALUES ('$esc', '$dni', '', '', '', '', NULL)";
    if (!mysqli_query($conexion, $sql)) {
        return 0;
    }

    return (int) mysqli_insert_id($conexion);
}

/** Solo lectura: 0 si aún no existe el usuario técnico */
function transferencias_libro_id_existente($conexion)
{
    require_once __DIR__ . '/helpers_tenant_inmobiliaria.php';
    tenant_inmob_asegurar_esquema($conexion);
    $esc = mysqli_real_escape_string($conexion, APELLIDO_USUARIO_LIBRO_TRANSF);

    if (tenant_inmob_es_sofia()) {
        $sid = tenant_inmob_id_acceso_sofia_efectivo($conexion);
        if ($sid <= 0) {
            return 0;
        }
        $r = mysqli_query($conexion, "SELECT id FROM usuarios WHERE apellido = '$esc' AND acceso_creador_id = " . (int) $sid . ' LIMIT 1');
    } else {
        $r = mysqli_query($conexion, "SELECT id FROM usuarios WHERE apellido = '$esc' AND acceso_creador_id IS NULL LIMIT 1");
    }
    if ($r && $row = mysqli_fetch_assoc($r)) {
        return (int) $row['id'];
    }

    return 0;
}
