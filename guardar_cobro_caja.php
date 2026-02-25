<?php
/**
 * Cobro en caja: graba cada item por separado con ingreso a caja.
 * Recibe: usuario_id, fecha, items[] (cada item: concepto, monto)
 * Cada item se graba en cuenta del usuario y en Caja (EFVO).
 */
include 'db.php';
include 'verificar_sesion.php';
if (isset($_SESSION['acceso_nivel']) && $_SESSION['acceso_nivel'] < 2) {
    header('HTTP/1.0 403 Forbidden');
    echo 'Sin permiso';
    exit;
}
define('ID_CAJA', 1);

if (!isset($_POST['usuario_id']) || !isset($_POST['fecha']) || !isset($_POST['items'])) {
    echo 'Error: Faltan datos.';
    exit;
}

$usuario_id = (int)$_POST['usuario_id'];
if ($usuario_id < 1 || $usuario_id === ID_CAJA) {
    echo 'Error: Usuario no válido.';
    exit;
}

$fecha_raw = trim($_POST['fecha'] ?? '');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha_raw)) {
    echo 'Error: Fecha inválida.';
    exit;
}
$fecha = mysqli_real_escape_string($conexion, $fecha_raw);

$items_raw = $_POST['items'] ?? '';
$items = is_string($items_raw) ? json_decode($items_raw, true) : $items_raw;
if (!is_array($items) || count($items) === 0) {
    echo 'Error: No hay items para grabar.';
    exit;
}

$res_usuario = mysqli_query($conexion, "SELECT apellido FROM usuarios WHERE id = $usuario_id LIMIT 1");
$row_usuario = mysqli_fetch_assoc($res_usuario);
$nom_usuario = $row_usuario ? strtoupper($row_usuario['apellido']) : '';

$compro = 'EFVO';
$refer = 'COBRO CAJA';

foreach ($items as $item) {
    $concepto = trim($item['concepto'] ?? '');
    $monto = (float)($item['monto'] ?? 0);
    if ($concepto === '' || $monto <= 0) continue;

    $concepto = strtoupper(mysqli_real_escape_string($conexion, $concepto));
    $monto_pos = abs($monto);

    // 1. Insertar en cuenta del usuario (ingreso positivo)
    $sql = "INSERT INTO cuentas (usuario_id, fecha, concepto, comprobante, referencia, monto) 
            VALUES ($usuario_id, '$fecha', '$concepto', '$compro', '$refer', $monto_pos)";
    if (!mysqli_query($conexion, $sql)) {
        echo 'Error al grabar item: ' . mysqli_error($conexion);
        exit;
    }

    // 2. Grabar en Caja (ingreso)
    $concepto_caja = $nom_usuario ? ($nom_usuario . ' - ' . $concepto) : $concepto;
    $concepto_caja = mysqli_real_escape_string($conexion, $concepto_caja);
    $sql_caja = "INSERT INTO cuentas (usuario_id, fecha, concepto, comprobante, referencia, monto) 
                 VALUES (" . ID_CAJA . ", '$fecha', '$concepto_caja', '$compro', '$refer', $monto_pos)";
    if (!mysqli_query($conexion, $sql_caja)) {
        echo 'Error al grabar en Caja: ' . mysqli_error($conexion);
        exit;
    }
}

// Si "Dejar a cuenta próximo pago" y hay vuelto: grabar como ingreso efvo en esa cuenta
$dejar_cuenta = isset($_POST['dejar_cuenta']) && $_POST['dejar_cuenta'] === '1';
$vuelto = (float)($_POST['vuelto'] ?? 0);
if ($dejar_cuenta && $vuelto > 0) {
    $concepto_vuelto = 'A CUENTA PROXIMO PAGO';
    $concepto_vuelto = mysqli_real_escape_string($conexion, $concepto_vuelto);
    $sql_cuenta = "INSERT INTO cuentas (usuario_id, fecha, concepto, comprobante, referencia, monto) 
                   VALUES ($usuario_id, '$fecha', '$concepto_vuelto', '$compro', '$refer', $vuelto)";
    if (!mysqli_query($conexion, $sql_cuenta)) {
        echo 'Error al grabar vuelto a cuenta: ' . mysqli_error($conexion);
        exit;
    }
    $concepto_caja_vuelto = $nom_usuario ? ($nom_usuario . ' - A CUENTA PROXIMO PAGO') : 'A CUENTA PROXIMO PAGO';
    $concepto_caja_vuelto = mysqli_real_escape_string($conexion, $concepto_caja_vuelto);
    $sql_caja_vuelto = "INSERT INTO cuentas (usuario_id, fecha, concepto, comprobante, referencia, monto) 
                        VALUES (" . ID_CAJA . ", '$fecha', '$concepto_caja_vuelto', '$compro', '$refer', $vuelto)";
    if (!mysqli_query($conexion, $sql_caja_vuelto)) {
        echo 'Error al grabar vuelto en Caja: ' . mysqli_error($conexion);
        exit;
    }
}

echo 'OK';
