<?php
include 'db.php';
include 'verificar_sesion.php';
include 'helpers_contrato.php';
include 'smtp_enviar.php';
include_once __DIR__ . '/generar_expensa_html.php';
include_once __DIR__ . '/generar_expensa_pdf.php';

// Configurar zona horaria de Argentina
date_default_timezone_set('America/Argentina/Buenos_Aires');

if (!isset($_GET['id'])) {
    die("ID no recibido");
}

$consorcio_id = (int)$_GET['id'];

// Verificar que el usuario sea Consorcio
$res_u = mysqli_query($conexion, "SELECT id, apellido, consorcio FROM usuarios WHERE id = $consorcio_id LIMIT 1");
$row_u = mysqli_fetch_assoc($res_u);
if (!$row_u || stripos($row_u['apellido'], 'CONSORCIO') !== 0) {
    die("El usuario no es un Consorcio");
}

$nombre_consorcio = trim($row_u['consorcio'] ?? '');
$consorcio_esc = mysqli_real_escape_string($conexion, $nombre_consorcio);

// Obtener última liquidación
$ultimo_liq_id = null;
$ultimo_mes_liq = null;
$res_liq = mysqli_query($conexion, "SELECT movimiento_id, referencia, concepto FROM cuentas 
    WHERE usuario_id = $consorcio_id AND UPPER(TRIM(comprobante)) = 'LIQ EXPENSAS' 
    ORDER BY movimiento_id DESC LIMIT 1");
if ($res_liq && $row_liq = mysqli_fetch_assoc($res_liq)) {
    $ultimo_liq_id = (int)$row_liq['movimiento_id'];
    $ultimo_mes_liq = trim($row_liq['referencia'] ?? '');
}

// Obtener movimientos desde la última liquidación
$movimientos = [];
if ($ultimo_liq_id !== null) {
    $sql_mov = "SELECT fecha, concepto, comprobante, referencia, monto 
                FROM cuentas 
                WHERE usuario_id = $consorcio_id AND movimiento_id > $ultimo_liq_id
                ORDER BY fecha ASC, movimiento_id ASC";
} else {
    $sql_mov = "SELECT fecha, concepto, comprobante, referencia, monto 
                FROM cuentas 
                WHERE usuario_id = $consorcio_id
                ORDER BY fecha ASC, movimiento_id ASC";
}
$res_mov = mysqli_query($conexion, $sql_mov);
while ($mov = mysqli_fetch_assoc($res_mov)) {
    $movimientos[] = [
        'fecha' => $mov['fecha'],
        'concepto' => strtoupper(trim($mov['concepto'])),
        'comprobante' => strtoupper(trim($mov['comprobante'])),
        'referencia' => strtoupper(trim($mov['referencia'] ?? '')),
        'monto' => (float)$mov['monto']
    ];
}

// Calcular totales
$total_ingresos = 0;
$total_egresos = 0;
$total_egresos_extraordinarias = 0;
foreach ($movimientos as $mov) {
    if ($mov['monto'] > 0) {
        $total_ingresos += $mov['monto'];
    } else {
        $monto_abs = abs($mov['monto']);
        $total_egresos += $monto_abs;
        if ($mov['comprobante'] === 'EXP EXTRAORDINARIA') {
            $total_egresos_extraordinarias += $monto_abs;
        }
    }
}
$total_egresos_ordinarias = $total_egresos - $total_egresos_extraordinarias;
$total_expensas = $total_egresos;

// Obtener propiedades del consorcio con porcentaje
$cond_consorcio = $nombre_consorcio === ''
    ? "AND (p.consorcio IS NULL OR TRIM(p.consorcio) = '')"
    : "AND UPPER(TRIM(p.consorcio)) = UPPER('$consorcio_esc')";
$sql_prop = "SELECT p.propiedad_id, p.propietario_id, p.porcentaje, p.propiedad,
                    u.apellido AS propietario_nombre, u.email AS propietario_email
             FROM propiedades p
             INNER JOIN usuarios u ON u.id = p.propietario_id
             WHERE p.porcentaje IS NOT NULL AND p.porcentaje > 0 $cond_consorcio
             ORDER BY p.propiedad ASC";
$res_prop = mysqli_query($conexion, $sql_prop);

$expensas = [];
while ($prop = mysqli_fetch_assoc($res_prop)) {
    $propiedad_id = (int)$prop['propiedad_id'];
    $propietario_id = (int)$prop['propietario_id'];
    $porcentaje = (float)$prop['porcentaje'];
    $monto_expensa = round($total_expensas * ($porcentaje / 100), 2);
    
    // Obtener inquilino si existe
    $inquilino_nombre = '';
    $inquilino_email = '';
    $res_inq = mysqli_query($conexion, "SELECT u.apellido, u.email 
                                        FROM alquileres a
                                        INNER JOIN usuarios u ON u.id = a.inquilino1_id
                                        WHERE a.propiedad_id = $propiedad_id AND a.estado = 'VIGENTE' 
                                        LIMIT 1");
    if ($res_inq && $row_inq = mysqli_fetch_assoc($res_inq)) {
        $inquilino_nombre = strtoupper(trim($row_inq['apellido']));
        $inquilino_email = trim($row_inq['email'] ?? '');
    }
    
    $expensas[] = [
        'propiedad' => strtoupper(trim($prop['propiedad'])),
        'propietario' => strtoupper(trim($prop['propietario_nombre'])),
        'propietario_email' => trim($prop['propietario_email'] ?? ''),
        'inquilino' => $inquilino_nombre,
        'inquilino_email' => $inquilino_email,
        'porcentaje' => $porcentaje,
        'monto' => $monto_expensa
    ];
}

$fecha_actual = date('d/m/Y');


// Enviar mails
$enviados_ok = 0;
$enviados_error = 0;
$errores = [];

foreach ($expensas as $expensa) {
    // Generar HTML con el mismo formato visual
    $contenido_html = generarExpensaHTML($expensa, $movimientos, $total_ingresos, $total_egresos_ordinarias, $total_egresos_extraordinarias, $total_expensas, $ultimo_mes_liq, $fecha_actual, $nombre_consorcio);
    
    // Generar PDF
    $archivo_pdf = generarExpensaPDF($expensa, $movimientos, $total_ingresos, $total_egresos_ordinarias, $total_egresos_extraordinarias, $total_expensas, $ultimo_mes_liq, $fecha_actual, $nombre_consorcio);
    
    $asunto = "Expensa - " . $expensa['propiedad'] . " - " . ($ultimo_mes_liq ? $ultimo_mes_liq : date('m/Y'));
    
    // Enviar a propietario si tiene email
    if (!empty($expensa['propietario_email']) && filter_var($expensa['propietario_email'], FILTER_VALIDATE_EMAIL)) {
        if (enviar_mail_smtp_con_adjunto($expensa['propietario_email'], $asunto, $contenido_html, $archivo_pdf)) {
            $enviados_ok++;
        } else {
            $enviados_error++;
            $errores[] = "Propietario " . $expensa['propietario'] . " (" . $expensa['propietario_email'] . "): " . smtp_ultimo_error();
        }
    }
    
    // Enviar a inquilino si tiene email
    if (!empty($expensa['inquilino_email']) && filter_var($expensa['inquilino_email'], FILTER_VALIDATE_EMAIL)) {
        if (enviar_mail_smtp_con_adjunto($expensa['inquilino_email'], $asunto, $contenido_html, $archivo_pdf)) {
            $enviados_ok++;
        } else {
            $enviados_error++;
            $errores[] = "Inquilino " . $expensa['inquilino'] . " (" . $expensa['inquilino_email'] . "): " . smtp_ultimo_error();
        }
    }
    
    // Limpiar PDF temporal si existe
    if ($archivo_pdf && file_exists($archivo_pdf)) {
        @unlink($archivo_pdf);
    }
}

// Redirigir con resultado
$mensaje = '';
if ($enviados_ok > 0) {
    $mensaje = 'Se enviaron ' . $enviados_ok . ' mail(s) correctamente.';
}
if ($enviados_error > 0) {
    $mensaje .= ($mensaje ? ' ' : '') . 'Error en ' . $enviados_error . ' envío(s).';
}
if (empty($mensaje)) {
    $mensaje = 'No se encontraron emails válidos para enviar.';
}

header('Location: imprimir_expensas_consorcio.php?id=' . $consorcio_id . '&mail_resultado=' . urlencode($mensaje) . ($enviados_error > 0 ? '&mail_errores=' . urlencode(implode(' | ', $errores)) : ''));
exit;
