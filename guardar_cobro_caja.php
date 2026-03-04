<?php
/**
 * Cobro en caja: graba cada item por separado con ingreso a caja.
 * Recibe: usuario_id, fecha, items[] (cada item: concepto, monto)
 * Cada item se graba en cuenta del usuario y en Caja (EFVO).
 * Si el item es expensas: también graba en cuenta del consorcio y del propietario (si es inquilino).
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

// Obtener propiedad del inquilino (para items de expensas)
$propiedad_inquilino = null;
$res_alq = mysqli_query($conexion, "SELECT a.propiedad_id, p.propiedad, p.consorcio, p.propietario_id 
    FROM alquileres a 
    INNER JOIN propiedades p ON p.propiedad_id = a.propiedad_id 
    WHERE (a.inquilino1_id = $usuario_id OR a.inquilino2_id = $usuario_id) AND a.estado = 'VIGENTE' 
    LIMIT 1");
if ($res_alq && $row_alq = mysqli_fetch_assoc($res_alq)) {
    $propiedad_inquilino = $row_alq;
}

foreach ($items as $item) {
    $concepto = trim($item['concepto'] ?? '');
    $monto = (float)($item['monto'] ?? 0);
    if ($concepto === '' || $monto <= 0) continue;

    $concepto_orig = $concepto;
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

    // 3. Si es expensas e inquilino: grabar en consorcio y propietario (excluir PAGO A CUENTA, etc.)
    $es_pago_cuenta = (stripos($concepto_orig, 'PAGO A CUENTA') !== false || stripos($concepto_orig, 'A CUENTA PROXIMO') !== false || stripos($concepto_orig, 'A ENTREGAR VUELTO') !== false);
    $es_expensa = !$es_pago_cuenta && (stripos($concepto_orig, 'EXPENSA') !== false || stripos($concepto_orig, 'LIQ EXP') !== false);
    if ($es_expensa && $propiedad_inquilino !== null) {
        $prop_consorcio = trim($propiedad_inquilino['consorcio'] ?? '');
        $nombre_prop = mysqli_real_escape_string($conexion, strtoupper($propiedad_inquilino['propiedad'] ?? ''));
        $propietario_id = (int)($propiedad_inquilino['propietario_id'] ?? 0);

        $consorcio_id = null;
        if ($prop_consorcio !== '') {
            $prop_consorcio_esc = mysqli_real_escape_string($conexion, $prop_consorcio);
            $res_con = mysqli_query($conexion, "SELECT id FROM usuarios 
                WHERE UPPER(apellido) LIKE 'CONSORCIO%' 
                AND UPPER(TRIM(COALESCE(consorcio,''))) = UPPER('$prop_consorcio_esc')
                LIMIT 1");
            if ($res_con && $row_con = mysqli_fetch_assoc($res_con)) {
                $consorcio_id = (int)$row_con['id'];
            }
        }
        if ($consorcio_id === null) {
            $res_con = mysqli_query($conexion, "SELECT id FROM usuarios WHERE UPPER(apellido) LIKE 'CONSORCIO%' LIMIT 1");
            if ($res_con && $row_con = mysqli_fetch_assoc($res_con)) {
                $consorcio_id = (int)$row_con['id'];
            }
        }

        if ($consorcio_id !== null) {
            $concepto_consorcio = "COBRO EXPENSA $nombre_prop - PAGÓ $nom_usuario";
            $concepto_consorcio = mysqli_real_escape_string($conexion, $concepto_consorcio);
            $sql_cons = "INSERT INTO cuentas (usuario_id, fecha, concepto, comprobante, referencia, monto) 
                        VALUES ($consorcio_id, '$fecha', '$concepto_consorcio', '$compro', '$refer', $monto_pos)";
            if (!mysqli_query($conexion, $sql_cons)) {
                echo 'Error al grabar en Consorcio: ' . mysqli_error($conexion);
                exit;
            }
        }

        if ($propietario_id > 0 && $propietario_id != $usuario_id) {
            $concepto_prop = "COBRO EXPENSA $nombre_prop - PAGÓ $nom_usuario";
            $concepto_prop = mysqli_real_escape_string($conexion, $concepto_prop);
            $sql_prop = "INSERT INTO cuentas (usuario_id, fecha, concepto, comprobante, referencia, monto) 
                         VALUES ($propietario_id, '$fecha', '$concepto_prop', '$compro', '$refer', $monto_pos)";
            if (!mysqli_query($conexion, $sql_prop)) {
                echo 'Error al grabar en Propietario: ' . mysqli_error($conexion);
                exit;
            }
        }
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
