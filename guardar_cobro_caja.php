<?php
/**
 * Cobro en caja: graba cada item por separado con ingreso a caja.
 * Recibe: usuario_id, fecha, items[] (cada item: concepto, monto)
 * Cada item se graba en cuenta del usuario y en Caja (EFVO).
 * Si el item es expensas: también graba en cuenta del consorcio y del propietario (si es inquilino).
 */
include 'db.php';
include 'verificar_sesion.php';
require_once __DIR__ . '/helpers_tenant_inmobiliaria.php';
tenant_inmob_asegurar_esquema($conexion);
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
if (tenant_inmob_es_sofia()) {
    echo 'Error: Cobro en caja central no aplica en el ámbito inmobiliario.';
    exit;
}
if (!tenant_inmob_usuario_id_visible($conexion, $usuario_id)) {
    echo 'Error: Sin permiso.';
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
// Obtener apellido del usuario (cache simple por request)
if (!isset($GLOBALS['HHH_USUARIO_APELLIDOS'])) {
    $GLOBALS['HHH_USUARIO_APELLIDOS'] = [];
}
if (!isset($GLOBALS['HHH_USUARIO_APELLIDOS'][$usuario_id])) {
    $stmt_usu = mysqli_prepare($conexion, "SELECT apellido FROM usuarios WHERE id = ? LIMIT 1");
    if ($stmt_usu) {
        mysqli_stmt_bind_param($stmt_usu, 'i', $usuario_id);
        mysqli_stmt_execute($stmt_usu);
        $res_usuario = mysqli_stmt_get_result($stmt_usu);
        $row_usuario = mysqli_fetch_assoc($res_usuario);
        mysqli_stmt_close($stmt_usu);
        $GLOBALS['HHH_USUARIO_APELLIDOS'][$usuario_id] = $row_usuario ? strtoupper($row_usuario['apellido']) : '';
    } else {
        $GLOBALS['HHH_USUARIO_APELLIDOS'][$usuario_id] = '';
    }
}
$nom_usuario = $GLOBALS['HHH_USUARIO_APELLIDOS'][$usuario_id];

$compro = 'EFVO';
$refer = 'COBRO CAJA';

// Obtener propiedad del inquilino (para items de expensas)
$propiedad_inquilino = null;
$stmt_alq = mysqli_prepare($conexion, "SELECT a.propiedad_id, p.propiedad, p.consorcio, p.propietario_id 
    FROM alquileres a 
    INNER JOIN propiedades p ON p.propiedad_id = a.propiedad_id 
    WHERE (a.inquilino1_id = ? OR a.inquilino2_id = ?) AND a.estado = 'VIGENTE' 
    LIMIT 1");
if ($stmt_alq) {
    mysqli_stmt_bind_param($stmt_alq, 'ii', $usuario_id, $usuario_id);
    mysqli_stmt_execute($stmt_alq);
    $res_alq = mysqli_stmt_get_result($stmt_alq);
    if ($res_alq && $row_alq = mysqli_fetch_assoc($res_alq)) {
        $propiedad_inquilino = $row_alq;
    }
    mysqli_stmt_close($stmt_alq);
}

foreach ($items as $item) {
    $concepto = trim($item['concepto'] ?? '');
    $monto = (float)($item['monto'] ?? 0);
    if ($concepto === '' || $monto <= 0) continue;

    $concepto_orig = $concepto;
    $concepto = strtoupper(mysqli_real_escape_string($conexion, $concepto));
    $monto_pos = abs($monto);

    // 1. Insertar en cuenta del usuario (ingreso positivo)
    $stmt_cta = mysqli_prepare($conexion, "INSERT INTO cuentas (usuario_id, fecha, concepto, comprobante, referencia, monto) 
            VALUES (?, ?, ?, ?, ?, ?)");
    if (!$stmt_cta) {
        echo 'Error al grabar item: ' . mysqli_error($conexion);
        exit;
    }
    mysqli_stmt_bind_param($stmt_cta, 'issssd', $usuario_id, $fecha, $concepto, $compro, $refer, $monto_pos);
    if (!mysqli_stmt_execute($stmt_cta)) {
        echo 'Error al grabar item: ' . mysqli_error($conexion);
        mysqli_stmt_close($stmt_cta);
        exit;
    }
    mysqli_stmt_close($stmt_cta);

    // 2. Grabar en Caja (ingreso)
    $concepto_caja = $nom_usuario ? ($nom_usuario . ' - ' . $concepto) : $concepto;
    $concepto_caja = mysqli_real_escape_string($conexion, $concepto_caja);
    $stmt_caja = mysqli_prepare($conexion, "INSERT INTO cuentas (usuario_id, fecha, concepto, comprobante, referencia, monto) 
                 VALUES (?, ?, ?, ?, ?, ?)");
    if (!$stmt_caja) {
        echo 'Error al grabar en Caja: ' . mysqli_error($conexion);
        exit;
    }
    $id_caja = ID_CAJA;
    mysqli_stmt_bind_param($stmt_caja, 'issssd', $id_caja, $fecha, $concepto_caja, $compro, $refer, $monto_pos);
    if (!mysqli_stmt_execute($stmt_caja)) {
        echo 'Error al grabar en Caja: ' . mysqli_error($conexion);
        mysqli_stmt_close($stmt_caja);
        exit;
    }
    mysqli_stmt_close($stmt_caja);

    // 3. Si es expensas e inquilino: grabar en consorcio y propietario (excluir PAGO A CUENTA, etc.)
    $es_pago_cuenta = (stripos($concepto_orig, 'PAGO A CUENTA') !== false || stripos($concepto_orig, 'PAGO SALDO') !== false || stripos($concepto_orig, 'A CUENTA PROXIMO') !== false || stripos($concepto_orig, 'A ENTREGAR VUELTO') !== false);
    $es_expensa = !$es_pago_cuenta && (stripos($concepto_orig, 'EXPENSA') !== false || stripos($concepto_orig, 'LIQ EXP') !== false);
    if ($es_expensa && $propiedad_inquilino !== null) {
        $prop_consorcio = trim($propiedad_inquilino['consorcio'] ?? '');
        $nombre_prop = mysqli_real_escape_string($conexion, strtoupper($propiedad_inquilino['propiedad'] ?? ''));
        $propietario_id = (int)($propiedad_inquilino['propietario_id'] ?? 0);

        // Reutilizar cache de consorcios usada en otros scripts
        if (!isset($GLOBALS['HHH_CONSORCIO_ID_CACHE'])) {
            $GLOBALS['HHH_CONSORCIO_ID_CACHE'] = [];
        }
        $cacheKey = $prop_consorcio !== '' ? strtoupper(trim($prop_consorcio)) : '__DEFAULT__';
        $consorcio_id = $GLOBALS['HHH_CONSORCIO_ID_CACHE'][$cacheKey] ?? null;
        if ($consorcio_id === null) {
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
            $GLOBALS['HHH_CONSORCIO_ID_CACHE'][$cacheKey] = $consorcio_id;
        }

        if ($consorcio_id !== null) {
            $concepto_consorcio = "COBRO EXPENSA $nombre_prop - PAGÓ $nom_usuario";
            $concepto_consorcio = mysqli_real_escape_string($conexion, $concepto_consorcio);
            $stmt_cons = mysqli_prepare($conexion, "INSERT INTO cuentas (usuario_id, fecha, concepto, comprobante, referencia, monto) 
                        VALUES (?, ?, ?, ?, ?, ?)");
            if (!$stmt_cons) {
                echo 'Error al grabar en Consorcio: ' . mysqli_error($conexion);
                exit;
            }
            mysqli_stmt_bind_param($stmt_cons, 'issssd', $consorcio_id, $fecha, $concepto_consorcio, $compro, $refer, $monto_pos);
            if (!mysqli_stmt_execute($stmt_cons)) {
                echo 'Error al grabar en Consorcio: ' . mysqli_error($conexion);
                mysqli_stmt_close($stmt_cons);
                exit;
            }
            mysqli_stmt_close($stmt_cons);
        }

        if ($propietario_id > 0 && $propietario_id != $usuario_id) {
            $concepto_prop = "COBRO EXPENSA $nombre_prop - PAGÓ $nom_usuario";
            $concepto_prop = mysqli_real_escape_string($conexion, $concepto_prop);
            $stmt_prop = mysqli_prepare($conexion, "INSERT INTO cuentas (usuario_id, fecha, concepto, comprobante, referencia, monto) 
                         VALUES (?, ?, ?, ?, ?, ?)");
            if (!$stmt_prop) {
                echo 'Error al grabar en Propietario: ' . mysqli_error($conexion);
                exit;
            }
            mysqli_stmt_bind_param($stmt_prop, 'issssd', $propietario_id, $fecha, $concepto_prop, $compro, $refer, $monto_pos);
            if (!mysqli_stmt_execute($stmt_prop)) {
                echo 'Error al grabar en Propietario: ' . mysqli_error($conexion);
                mysqli_stmt_close($stmt_prop);
                exit;
            }
            mysqli_stmt_close($stmt_prop);
        }
    }
}

// Si "Dejar a cuenta próximo pago" y hay vuelto: grabar como ingreso efvo en esa cuenta
$dejar_cuenta = isset($_POST['dejar_cuenta']) && $_POST['dejar_cuenta'] === '1';
$vuelto = (float)($_POST['vuelto'] ?? 0);
if ($dejar_cuenta && $vuelto > 0) {
    $concepto_vuelto = 'A CUENTA PROXIMO PAGO';
    $concepto_vuelto = mysqli_real_escape_string($conexion, $concepto_vuelto);
    $stmt_vta = mysqli_prepare($conexion, "INSERT INTO cuentas (usuario_id, fecha, concepto, comprobante, referencia, monto) 
                   VALUES (?, ?, ?, ?, ?, ?)");
    if (!$stmt_vta) {
        echo 'Error al grabar vuelto a cuenta: ' . mysqli_error($conexion);
        exit;
    }
    mysqli_stmt_bind_param($stmt_vta, 'issssd', $usuario_id, $fecha, $concepto_vuelto, $compro, $refer, $vuelto);
    if (!mysqli_stmt_execute($stmt_vta)) {
        echo 'Error al grabar vuelto a cuenta: ' . mysqli_error($conexion);
        mysqli_stmt_close($stmt_vta);
        exit;
    }
    mysqli_stmt_close($stmt_vta);
    $concepto_caja_vuelto = $nom_usuario ? ($nom_usuario . ' - A CUENTA PROXIMO PAGO') : 'A CUENTA PROXIMO PAGO';
    $concepto_caja_vuelto = mysqli_real_escape_string($conexion, $concepto_caja_vuelto);
    $stmt_caja_vta = mysqli_prepare($conexion, "INSERT INTO cuentas (usuario_id, fecha, concepto, comprobante, referencia, monto) 
                        VALUES (?, ?, ?, ?, ?, ?)");
    if (!$stmt_caja_vta) {
        echo 'Error al grabar vuelto en Caja: ' . mysqli_error($conexion);
        exit;
    }
    $id_caja_vta = ID_CAJA;
    mysqli_stmt_bind_param($stmt_caja_vta, 'issssd', $id_caja_vta, $fecha, $concepto_caja_vuelto, $compro, $refer, $vuelto);
    if (!mysqli_stmt_execute($stmt_caja_vta)) {
        echo 'Error al grabar vuelto en Caja: ' . mysqli_error($conexion);
        mysqli_stmt_close($stmt_caja_vta);
        exit;
    }
    mysqli_stmt_close($stmt_caja_vta);
}

echo 'OK';
