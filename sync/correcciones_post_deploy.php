<?php
/**
 * Correcciones de datos a aplicar en el servidor después de deploy (git pull o import DB).
 * Se incluye desde sync_git.php y sync_db.php.
 *
 * @param mysqli $conexion Conexión a la base de datos
 * @return array ['aplicadas' => [], 'errores' => []]
 */
function ejecutar_correcciones_deploy($conexion) {
    $correcciones_aplicadas = [];
    $errores = [];

    // --- Corrección: Alquiler COCHERA 1 MOLINA (usuario 22) marzo 2026 ---
    // Contrato desde enero; en marzo corresponde actualización bimestral.
    $usuario_id = 22;
    $referencia = '03/2026';

    $fecha_ipc2 = '2026-01-01';
    $fecha_ipc3 = '2025-12-01';
    $r2 = mysqli_query($conexion, "SELECT valor FROM indices WHERE fecha = '$fecha_ipc2' AND tipo = 'IPC' LIMIT 1");
    $r3 = mysqli_query($conexion, "SELECT valor FROM indices WHERE fecha = '$fecha_ipc3' AND tipo = 'IPC' LIMIT 1");
    $ipc2 = ($r2 && $row2 = mysqli_fetch_assoc($r2)) ? (float)$row2['valor'] : 0;
    $ipc3 = ($r3 && $row3 = mysqli_fetch_assoc($r3)) ? (float)$row3['valor'] : 0;
    $coef = (1 + $ipc2/100) * (1 + $ipc3/100) * 1.015;

    $res_ult = mysqli_query($conexion,
        "SELECT ABS(monto) AS ultimo FROM cuentas 
         WHERE usuario_id = $usuario_id AND comprobante = 'ALQUILER'
         AND concepto LIKE '%COCHERA 1%'
         ORDER BY fecha DESC, movimiento_id DESC LIMIT 1"
    );
    $base = 110000;
    if ($res_ult && ($row_u = mysqli_fetch_assoc($res_ult)) && (float)$row_u['ultimo'] > 0) {
        $base = (float)$row_u['ultimo'];
    }
    $monto_correcto = -round($base * $coef, 2);

    $res_check = mysqli_query($conexion,
        "SELECT movimiento_id, monto FROM cuentas 
         WHERE usuario_id = $usuario_id AND comprobante = 'ALQUILER' 
         AND referencia = '$referencia' AND concepto LIKE '%COCHERA 1%' LIMIT 1"
    );
    if ($res_check && $row_c = mysqli_fetch_assoc($res_check)) {
        $monto_actual = (float)$row_c['monto'];
        if (abs($monto_actual - $monto_correcto) > 0.01) {
            $mov_id = (int)$row_c['movimiento_id'];
            mysqli_query($conexion, "UPDATE cuentas SET monto = $monto_correcto, concepto = 'ALQUILER ACTUALIZADO - COCHERA 1 , SUB SUELO, LAPRIDA 430' WHERE movimiento_id = $mov_id");
            if (mysqli_affected_rows($conexion) > 0) {
                $correcciones_aplicadas[] = "Alquiler cochera MOLINA marzo 2026: $" . number_format(abs($monto_correcto), 2, ',', '.');
            } else {
                $errores[] = "No se pudo actualizar movimiento $mov_id";
            }
        }
    }

    // --- Duplicados TERAN JORGE: alquiler 04/2026 a -490000 (bug liquidación con fecha_inicio 2026-05-01) ---
    $ap_teran = mysqli_real_escape_string($conexion, 'TERAN JORGE LUIS');
    $ref_teran = '04/2026';
    mysqli_query($conexion,
        "DELETE c FROM cuentas c
         INNER JOIN usuarios u ON u.id = c.usuario_id
         WHERE u.apellido = '$ap_teran'
           AND c.comprobante = 'ALQUILER'
           AND c.referencia = '$ref_teran'
           AND c.monto = -490000.00"
    );
    $n_borrados_teran = (int) mysqli_affected_rows($conexion);
    if ($n_borrados_teran > 0) {
        $correcciones_aplicadas[] = "TERAN JORGE LUIS: eliminados $n_borrados_teran movimiento(s) erróneos ALQUILER 04/2026 (-490000, duplicado pre-inicio contrato).";
    }

    return ['aplicadas' => $correcciones_aplicadas, 'errores' => $errores];
}
