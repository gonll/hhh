<?php
include 'db.php';
include 'verificar_sesion.php';

if (isset($_SESSION['acceso_nivel']) && $_SESSION['acceso_nivel'] < 2) {
    header('Location: index.php?msg=sin_permiso');
    exit;
}

$redir = 'gestionar_finca.php';
$usuario_id = isset($_POST['usuario_id']) ? (int)$_POST['usuario_id'] : 0;
if ($usuario_id < 1) {
    header('Location: ' . $redir . '?cc=sin_usuario');
    exit;
}

// Sumar horas con CC=NO para este usuario (antes de actualizarlas)
$res_sums = mysqli_query($conexion, "
    SELECT 
        COALESCE(SUM(CASE WHEN tipo_horas = 'Horas Comunes' THEN horas ELSE 0 END), 0) AS horas_comunes,
        COALESCE(SUM(CASE WHEN tipo_horas = 'Horas tractos' THEN horas ELSE 0 END), 0) AS horas_tractor,
        MIN(fecha) AS fecha_min
    FROM pdt 
    WHERE usuario_id = $usuario_id AND COALESCE(en_cc, 0) = 0
");
if (!$res_sums || !$row_sums = mysqli_fetch_assoc($res_sums)) {
    header('Location: ' . $redir . '?cc=sin_datos');
    exit;
}

$horas_comunes = (float)$row_sums['horas_comunes'];
$horas_tractor = (float)$row_sums['horas_tractor'];
$fecha_min = $row_sums['fecha_min'];

if ($horas_comunes <= 0 && $horas_tractor <= 0) {
    header('Location: ' . $redir . '?cc=sin_horas');
    exit;
}

// Valores vigentes de tabla salarial
$res_ts = mysqli_query($conexion, "SELECT valor_hora_comun, valor_hora_tractor FROM tabla_salarial ORDER BY id DESC LIMIT 1");
if (!$res_ts || !$ts = mysqli_fetch_assoc($res_ts)) {
    header('Location: ' . $redir . '?cc=sin_tabla_salarial');
    exit;
}
$valor_hora_comun = (float)$ts['valor_hora_comun'];
$valor_hora_tractor = (float)$ts['valor_hora_tractor'];

// Referencia: mes y año de los trabajos (desde la fecha mínima de los PDT)
$ref_mes_anio = $fecha_min ? date('m/Y', strtotime($fecha_min)) : date('m/Y');
$ref_esc = mysqli_real_escape_string($conexion, $ref_mes_anio);
$fecha_hoy = date('Y-m-d');

// 1) Pasar todos los PDT de este usuario con CC=NO a CC=SI
if (!mysqli_query($conexion, "UPDATE pdt SET en_cc = 1 WHERE usuario_id = $usuario_id AND COALESCE(en_cc, 0) = 0")) {
    header('Location: ' . $redir . '?cc=error&msg=' . urlencode(mysqli_error($conexion)));
    exit;
}

// 2) Cargar en cuentas: un movimiento por horas tractor y otro por horas comunes (mismo criterio que registro individual)
$comprobante = mysqli_real_escape_string($conexion, 'trabajo');
if ($horas_tractor > 0 && $valor_hora_tractor > 0) {
    $monto_tractor = round($horas_tractor * $valor_hora_tractor, 2);
    $cant_tractor = number_format($horas_tractor, 2, ',', '.');
    $valor_h_t = number_format($valor_hora_tractor, 2, ',', '.');
    $concepto_t = "Trabajo: Horas tractos, Cantidad $cant_tractor, y valor $valor_h_t";
    $concepto_t = mysqli_real_escape_string($conexion, $concepto_t);
    $sql_t = "INSERT INTO cuentas (usuario_id, fecha, concepto, comprobante, referencia, monto) 
              VALUES ($usuario_id, '$fecha_hoy', '$concepto_t', '$comprobante', '$ref_esc', $monto_tractor)";
    if (!mysqli_query($conexion, $sql_t)) {
        header('Location: ' . $redir . '?cc=error&msg=' . urlencode(mysqli_error($conexion)));
        exit;
    }
}
if ($horas_comunes > 0 && $valor_hora_comun > 0) {
    $monto_comun = round($horas_comunes * $valor_hora_comun, 2);
    $cant_comun = number_format($horas_comunes, 2, ',', '.');
    $valor_h_c = number_format($valor_hora_comun, 2, ',', '.');
    $concepto_c = "Trabajo: Horas Comunes, Cantidad $cant_comun, y valor $valor_h_c";
    $concepto_c = mysqli_real_escape_string($conexion, $concepto_c);
    $sql_c = "INSERT INTO cuentas (usuario_id, fecha, concepto, comprobante, referencia, monto) 
              VALUES ($usuario_id, '$fecha_hoy', '$concepto_c', '$comprobante', '$ref_esc', $monto_comun)";
    if (!mysqli_query($conexion, $sql_c)) {
        header('Location: ' . $redir . '?cc=error&msg=' . urlencode(mysqli_error($conexion)));
        exit;
    }
}

header('Location: ' . $redir . '?cc=ok&usuario=' . $usuario_id);
exit;
