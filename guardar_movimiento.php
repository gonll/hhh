<?php
include 'db.php';
include 'verificar_sesion.php';
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

    // 2. Insertar en la cuenta del usuario (como siempre)
    $sql = "INSERT INTO cuentas (usuario_id, fecha, concepto, comprobante, referencia, monto) 
            VALUES ($usuario_id, '$fecha', '$concepto', '$compro', '$refer', $monto)";

    if (!mysqli_query($conexion, $sql)) {
        echo "Error en SQL: " . mysqli_error($conexion);
        exit;
    }

    // 3. Grabar en Caja: para usuarios normales siempre; para Consorcio solo si comprobante es BOLETA o EFVO
    $grabado_en_caja = false;
    if ($usuario_id != ID_CAJA) {
        $res_usuario = mysqli_query($conexion, "SELECT apellido FROM usuarios WHERE id = $usuario_id LIMIT 1");
        $row_usuario = mysqli_fetch_assoc($res_usuario);
        $nom_usuario = $row_usuario ? strtoupper($row_usuario['apellido']) : '';
        $es_consorcio = ($nom_usuario && stripos($nom_usuario, 'CONSORCIO') === 0);
        $compro_es_efvo_boleta = ($compro === 'BOLETA' || $compro === 'EFVO');
        $compro_es_sueldo = ($compro === 'SUELDO' || $compro === 'SUELDO/EXTRAS');
        $compro_es_transferencia = ($compro === 'TRANSFERENCIA');
        $compro_es_anticipo = ($compro === 'ANTICIPO');

        // No grabar en Caja si es SUELDO/EXTRAS, TRANSFERENCIA o ANTICIPO (para cualquier usuario) o si es Consorcio sin BOLETA/EFVO
        if (!$compro_es_sueldo && !$compro_es_transferencia && !$compro_es_anticipo && (!$es_consorcio || $compro_es_efvo_boleta)) {
            $concepto_caja = $nom_usuario ? ($nom_usuario . ' - ' . $concepto) : $concepto;
            $concepto_caja = mysqli_real_escape_string($conexion, $concepto_caja);
            $sql_caja = "INSERT INTO cuentas (usuario_id, fecha, concepto, comprobante, referencia, monto) 
                         VALUES (" . ID_CAJA . ", '$fecha', '$concepto_caja', '$compro', '$refer', $monto)";
            if (!mysqli_query($conexion, $sql_caja)) {
                echo "Error al grabar en Caja: " . mysqli_error($conexion);
                exit;
            }
            $grabado_en_caja = ($es_consorcio && $compro_es_efvo_boleta);  // Para avisar al usuario
        }
    }

    echo $grabado_en_caja ? "OK_CAJA" : "OK";

} else {
    echo "Error: No se recibieron datos.";
}
?>