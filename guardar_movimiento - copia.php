<?php
// Incluimos la conexión a la base de datos
include 'db.php';

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

    // 3. Grabar también en Caja cuando el movimiento es de un usuario real (no cuando es de Caja)
    if ($usuario_id != ID_CAJA) {
        $res_usuario = mysqli_query($conexion, "SELECT apellido FROM usuarios WHERE id = $usuario_id LIMIT 1");
        $row_usuario = mysqli_fetch_assoc($res_usuario);
        $nom_usuario = $row_usuario ? strtoupper($row_usuario['apellido']) : '';
        $concepto_caja = $nom_usuario ? ($nom_usuario . ' - ' . $concepto) : $concepto;
        $concepto_caja = mysqli_real_escape_string($conexion, $concepto_caja);
        $sql_caja = "INSERT INTO cuentas (usuario_id, fecha, concepto, comprobante, referencia, monto) 
                     VALUES (" . ID_CAJA . ", '$fecha', '$concepto_caja', '$compro', '$refer', $monto)";
        if (!mysqli_query($conexion, $sql_caja)) {
            echo "Error al grabar en Caja: " . mysqli_error($conexion);
            exit;
        }
    }

    echo "OK";

} else {
    echo "Error: No se recibieron datos.";
}
?>