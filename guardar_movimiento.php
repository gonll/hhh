<?php
include 'db.php';
include 'verificar_sesion.php';
include 'crear_tabla_arriendos.php';
if (isset($_SESSION['acceso_nivel']) && $_SESSION['acceso_nivel'] < 2) {
    header('HTTP/1.0 403 Forbidden');
    echo 'Sin permiso';
    exit;
}
// ID del usuario CAJA (según index.php)
define('ID_CAJA', 1);

// Verificamos que los datos hayan sido enviados mediante el método POST
if (isset($_POST['id'])) {
    
    // 1. Captura y limpieza de datos (Seguridad básica)
    $usuario_id = (int)$_POST['id'];
    $fecha_raw  = trim($_POST['fecha'] ?? '');
    // Formato esperado: YYYY-MM-DD (igual que input type="date")
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha_raw)) {
        echo "Error: La fecha debe estar en formato AAAA-MM-DD (ej: " . date('Y-m-d') . ").";
        exit;
    }
    $d = DateTime::createFromFormat('Y-m-d', $fecha_raw);
    if (!$d || $d->format('Y-m-d') !== $fecha_raw) {
        echo "Error: Fecha no válida.";
        exit;
    }
    $fecha = mysqli_real_escape_string($conexion, $fecha_raw);
    
    // Convertimos a MAYÚSCULAS para mantener uniformidad en la base de datos
    $concepto   = strtoupper(mysqli_real_escape_string($conexion, $_POST['concepto']));
    $compro     = strtoupper(mysqli_real_escape_string($conexion, $_POST['compro']));
    $refer      = strtoupper(mysqli_real_escape_string($conexion, $_POST['refer']));
    
    // Aseguramos que el monto sea un número decimal
    $monto      = (float)$_POST['monto'];

    // Arriendos: si el usuario es apoderado y el comprobante es PGO ARRIENDO, grabar en cuenta del propietario
    $cuenta_usuario_id = $usuario_id;
    if ($compro === 'PGO ARRIENDO' || (stripos($concepto, 'PAGO DE') !== false && stripos($concepto, 'PRECIO REF') !== false)) {
        $r_apod = mysqli_query($conexion, "SELECT propietario_id FROM arriendos WHERE apoderado_id = $usuario_id ORDER BY id DESC LIMIT 1");
        if ($r_apod && $row_apod = mysqli_fetch_assoc($r_apod)) {
            $cuenta_usuario_id = (int)$row_apod['propietario_id'];
        }
    }

    // 2. Insertar en la cuenta del usuario (propietario si es arriendo con apoderado)
    $sql = "INSERT INTO cuentas (usuario_id, fecha, concepto, comprobante, referencia, monto) 
            VALUES ($cuenta_usuario_id, '$fecha', '$concepto', '$compro', '$refer', $monto)";

    if (!mysqli_query($conexion, $sql)) {
        echo "Error en SQL: " . mysqli_error($conexion);
        exit;
    }

    // 3. Grabar en Caja: según checkbox "Grabar en Caja" (si se envía); si no, lógica anterior
    $grabado_en_caja = false;
    $grabar_caja_param = isset($_POST['grabar_caja']) ? (int)$_POST['grabar_caja'] : -1;
    
    if ($cuenta_usuario_id != ID_CAJA) {
        $res_usuario = mysqli_query($conexion, "SELECT apellido FROM usuarios WHERE id = $cuenta_usuario_id LIMIT 1");
        $row_usuario = mysqli_fetch_assoc($res_usuario);
        $nom_usuario = $row_usuario ? strtoupper($row_usuario['apellido']) : '';
        $es_consorcio = ($nom_usuario && stripos($nom_usuario, 'CONSORCIO') === 0);
        $compro_es_efvo_boleta = ($compro === 'BOLETA' || $compro === 'EFVO');
        $compro_es_sueldo = ($compro === 'SUELDO' || $compro === 'SUELDO/EXTRAS');
        $compro_es_transferencia = ($compro === 'TRANSFERENCIA');
        $compro_es_anticipo = ($compro === 'ANTICIPO');
        $concepto_es_cobro = (stripos($concepto, 'COBRO') === 0);

        $debe_grabar = false;
        if ($grabar_caja_param === 1) {
            $debe_grabar = true;
        } elseif ($grabar_caja_param === 0) {
            $debe_grabar = false;
        } else {
            // Lógica anterior (compatibilidad)
            $debe_grabar = (!$compro_es_sueldo && !$compro_es_transferencia && !$compro_es_anticipo && !$concepto_es_cobro && (!$es_consorcio || $compro_es_efvo_boleta));
        }
        // Arriendos: ingreso y retiro NO impactan en caja
        $es_arriendo = ($compro === 'PGO ARRIENDO' || $compro === 'PRECIO DE LA BOLSA' || $compro === 'PRECIO AZUCAR' || (stripos($concepto, 'PAGO') !== false && (stripos($concepto, 'ARRIENDO') !== false || stripos($concepto, 'PRECIO REF') !== false)));
        if ($es_arriendo) $debe_grabar = false;

        if ($debe_grabar) {
            $concepto_caja = $nom_usuario ? ($nom_usuario . ' - ' . $concepto) : $concepto;
            $concepto_caja = mysqli_real_escape_string($conexion, $concepto_caja);
            $sql_caja = "INSERT INTO cuentas (usuario_id, fecha, concepto, comprobante, referencia, monto) 
                         VALUES (" . ID_CAJA . ", '$fecha', '$concepto_caja', '$compro', '$refer', $monto)";
            if (!mysqli_query($conexion, $sql_caja)) {
                echo "Error al grabar en Caja: " . mysqli_error($conexion);
                exit;
            }
            $grabado_en_caja = true;
        }
    }

    echo $grabado_en_caja ? "OK_CAJA" : "OK";

} else {
    echo "Error: No se recibieron datos.";
}
?>