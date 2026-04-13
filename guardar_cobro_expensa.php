<?php
include 'db.php';
include 'verificar_sesion.php';
require_once __DIR__ . '/helpers_tenant_inmobiliaria.php';
tenant_inmob_asegurar_esquema($conexion);
if (isset($_SESSION['acceso_nivel']) && $_SESSION['acceso_nivel'] < 2) {
    header('HTTP/1.0 403 Forbidden');
    echo 'Sin permiso';
    exit;
}
$id_caja = tenant_inmob_id_usuario_caja_central($conexion);

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['usuario_id'], $_POST['periodo'], $_POST['monto'])) {
    echo "Error: Faltan datos.";
    exit;
}

$usuario_id  = (int)$_POST['usuario_id'];
$propiedad_id = isset($_POST['propiedad_id']) ? (int)$_POST['propiedad_id'] : 0;
$consorcio_param = isset($_POST['consorcio']) ? trim($_POST['consorcio']) : '';
$periodo     = trim($_POST['periodo']);
$monto       = (float)str_replace(',', '.', $_POST['monto']);

if ($usuario_id <= 0 || $periodo === '' || $monto <= 0) {
    echo "Error: Datos inválidos.";
    exit;
}
if (!tenant_inmob_usuario_id_visible($conexion, $usuario_id)) {
    echo "Error: Sin permiso.";
    exit;
}
if ($propiedad_id <= 0 && $consorcio_param === '') {
    echo "Error: Seleccioná propiedad o consorcio.";
    exit;
}

// Formato periodo MM/AAAA o MM-AAAA
$periodo = strtoupper(preg_replace('/[^0-9\/\-]/', '', $periodo));
if (!preg_match('/^\d{1,2}[\/\-]\d{4}$/', $periodo)) {
    $periodo = str_replace('-', '/', $periodo);
}

$fecha = date('Y-m-d');

// Obtener lista de propiedades a cobrar (una o varias si es consorcio)
$propiedades_a_cobrar = [];
if ($consorcio_param !== '') {
    $consorcio_esc = mysqli_real_escape_string($conexion, $consorcio_param);
    $upper_consorcio = mb_strtoupper(trim($consorcio_esc), 'UTF-8');
    $sql_props = "SELECT DISTINCT p.propiedad_id, p.propiedad, p.consorcio, p.propietario_id
        FROM propiedades p
        LEFT JOIN alquileres a ON a.propiedad_id = p.propiedad_id AND a.estado = 'VIGENTE'
        WHERE (p.propietario_id = ? OR a.inquilino1_id = ? OR a.inquilino2_id = ?)
        AND UPPER(TRIM(COALESCE(p.consorcio,''))) = ?
        ORDER BY p.propiedad ASC";
    $stmt_props = mysqli_prepare($conexion, $sql_props);
    if (!$stmt_props) {
        echo "Error: No se pudieron obtener propiedades para este usuario en el consorcio.";
        exit;
    }
    mysqli_stmt_bind_param($stmt_props, 'iiis', $usuario_id, $usuario_id, $usuario_id, $upper_consorcio);
    mysqli_stmt_execute($stmt_props);
    $res_props = mysqli_stmt_get_result($stmt_props);
    if (!$res_props || mysqli_num_rows($res_props) == 0) {
        echo "Error: No se encontraron propiedades para este usuario en el consorcio.";
        exit;
    }
    while ($row = mysqli_fetch_assoc($res_props)) {
        $propiedades_a_cobrar[] = $row;
    }
} else {
    $stmt_prop = mysqli_prepare($conexion, "SELECT propiedad_id, propiedad, consorcio, propietario_id FROM propiedades WHERE propiedad_id = ? LIMIT 1");
    if ($stmt_prop) {
        mysqli_stmt_bind_param($stmt_prop, 'i', $propiedad_id);
        mysqli_stmt_execute($stmt_prop);
        $res_prop = mysqli_stmt_get_result($stmt_prop);
        if ($res_prop && $row = mysqli_fetch_assoc($res_prop)) {
            $propiedades_a_cobrar[] = $row;
        }
        mysqli_stmt_close($stmt_prop);
    }
}
if (empty($propiedades_a_cobrar)) {
    echo "Error: Propiedad no encontrada.";
    exit;
}

// Obtener nombre del usuario
$stmt_usu = mysqli_prepare($conexion, "SELECT apellido FROM usuarios WHERE id = ? LIMIT 1");
if ($stmt_usu) {
    mysqli_stmt_bind_param($stmt_usu, 'i', $usuario_id);
    mysqli_stmt_execute($stmt_usu);
    $res_usu = mysqli_stmt_get_result($stmt_usu);
    $row_usu = mysqli_fetch_assoc($res_usu);
    mysqli_stmt_close($stmt_usu);
} else {
    $row_usu = null;
}
$nombre_usu = $row_usu ? mysqli_real_escape_string($conexion, strtoupper($row_usu['apellido'])) : '';

$grabar_caja = isset($_POST['efvo']) && ($_POST['efvo'] === '1' || $_POST['efvo'] === 'true');
$comprobante = $grabar_caja ? 'EXP/EFVO' : 'EXP/TRANSF';
// Espejo consorcio/propietario: no usar EXP/TRANSF para no duplicar líneas en el libro Transferencias (una sola por transferencia).
$comprobante_espejo_exp = $grabar_caja ? 'EXP/EFVO' : 'COBRO EXP TRANSF';
$refer_periodo = mysqli_real_escape_string($conexion, $periodo);

// Si es pago por consorcio (Recibo N°): UN SOLO registro por cuenta
if ($consorcio_param !== '') {
    $recibo_ingresado = isset($_POST['recibo_numero']) ? trim($_POST['recibo_numero']) : '';
    if ($recibo_ingresado === '') {
        echo "Error: El número de recibo es obligatorio.";
        exit;
    }
    $nro_recibo = $recibo_ingresado;

    $row_primera = $propiedades_a_cobrar[0];
    $prop_consorcio = trim($row_primera['consorcio'] ?? '');
    $prop_consorcio_esc = mysqli_real_escape_string($conexion, $prop_consorcio);

    // Cache simple por request de IDs de consorcio para evitar consultas repetidas
    if (!isset($GLOBALS['HHH_CONSORCIO_ID_CACHE'])) {
        $GLOBALS['HHH_CONSORCIO_ID_CACHE'] = [];
    }
    $cacheKey = $prop_consorcio !== '' ? strtoupper(trim($prop_consorcio)) : '__DEFAULT__';
    $consorcio_id = $GLOBALS['HHH_CONSORCIO_ID_CACHE'][$cacheKey] ?? null;
    if ($consorcio_id === null) {
        if ($prop_consorcio !== '') {
            $res_con = mysqli_query($conexion, "SELECT id FROM usuarios 
                WHERE UPPER(apellido) LIKE 'CONSORCIO%' 
                AND UPPER(TRIM(COALESCE(consorcio,''))) = UPPER('$prop_consorcio_esc')
                LIMIT 1");
            if ($res_con && $row = mysqli_fetch_assoc($res_con)) {
                $consorcio_id = (int)$row['id'];
            }
        }
        if ($consorcio_id === null) {
            $res_con = mysqli_query($conexion, "SELECT id FROM usuarios WHERE UPPER(apellido) LIKE 'CONSORCIO%' LIMIT 1");
            $row_con = mysqli_fetch_assoc($res_con);
            if (!$row_con) {
                echo "Error: No se encontró usuario Consorcio en el sistema.";
                exit;
            }
            $consorcio_id = (int)$row_con['id'];
        }
        $GLOBALS['HHH_CONSORCIO_ID_CACHE'][$cacheKey] = $consorcio_id;
    }

    $consorcio_nom = mysqli_real_escape_string($conexion, strtoupper($consorcio_param));
    $concepto_consorcio = mysqli_real_escape_string($conexion, "COBRO EXPENSA VARIAS PROPIEDADES Recibo N° $nro_recibo - PAGÓ $nombre_usu");
    $concepto_usu = mysqli_real_escape_string($conexion, "Pago expensas Recibo N° $nro_recibo - Consorcio $consorcio_nom");

    $stmt_cta = mysqli_prepare($conexion, "INSERT INTO cuentas (usuario_id, fecha, concepto, comprobante, referencia, monto) 
             VALUES (?, ?, ?, ?, ?, ?)");
    if (!$stmt_cta) {
        echo "Error al grabar en Consorcio: " . mysqli_error($conexion);
        exit;
    }
    mysqli_stmt_bind_param($stmt_cta, 'issssd', $consorcio_id, $fecha, $concepto_consorcio, $comprobante_espejo_exp, $refer_periodo, $monto);
    if (!mysqli_stmt_execute($stmt_cta)) {
        echo "Error al grabar en Consorcio: " . mysqli_error($conexion);
        mysqli_stmt_close($stmt_cta);
        exit;
    }

    mysqli_stmt_bind_param($stmt_cta, 'issssd', $usuario_id, $fecha, $concepto_usu, $comprobante, $refer_periodo, $monto);
    if (!mysqli_stmt_execute($stmt_cta)) {
        echo "Error al grabar en usuario: " . mysqli_error($conexion);
        mysqli_stmt_close($stmt_cta);
        exit;
    }
    mysqli_stmt_close($stmt_cta);

    if ($grabar_caja) {
        $concepto_caja = mysqli_real_escape_string($conexion, "$nombre_usu - Pago expensas Recibo N° $nro_recibo");
        $stmt_caja = mysqli_prepare($conexion, "INSERT INTO cuentas (usuario_id, fecha, concepto, comprobante, referencia, monto) 
                     VALUES (?, ?, ?, ?, ?, ?)");
        if (!$stmt_caja) {
            echo "Error al grabar en Caja: " . mysqli_error($conexion);
            exit;
        }
        mysqli_stmt_bind_param($stmt_caja, 'issssd', $id_caja, $fecha, $concepto_caja, $comprobante, $refer_periodo, $monto);
        if (!mysqli_stmt_execute($stmt_caja)) {
            echo "Error al grabar en Caja: " . mysqli_error($conexion);
            mysqli_stmt_close($stmt_caja);
            exit;
        }
        mysqli_stmt_close($stmt_caja);
    }
} else {
    // Una sola propiedad: lógica original
    $row_prop = $propiedades_a_cobrar[0];
    $propiedad_id_act = (int)$row_prop['propiedad_id'];
    $nombre_prop = mysqli_real_escape_string($conexion, strtoupper($row_prop['propiedad'] ?? ''));
    $prop_consorcio = trim($row_prop['consorcio'] ?? '');
    $prop_consorcio_esc = mysqli_real_escape_string($conexion, $prop_consorcio);
    $propietario_id = (int)($row_prop['propietario_id'] ?? 0);

    if (!isset($GLOBALS['HHH_CONSORCIO_ID_CACHE'])) {
        $GLOBALS['HHH_CONSORCIO_ID_CACHE'] = [];
    }
    $cacheKey = $prop_consorcio !== '' ? strtoupper(trim($prop_consorcio)) : '__DEFAULT__';
    $consorcio_id = $GLOBALS['HHH_CONSORCIO_ID_CACHE'][$cacheKey] ?? null;
    if ($consorcio_id === null) {
        if ($prop_consorcio !== '') {
            $res_con = mysqli_query($conexion, "SELECT id FROM usuarios 
                WHERE UPPER(apellido) LIKE 'CONSORCIO%' 
                AND UPPER(TRIM(COALESCE(consorcio,''))) = UPPER('$prop_consorcio_esc')
                LIMIT 1");
            if ($res_con && $row = mysqli_fetch_assoc($res_con)) {
                $consorcio_id = (int)$row['id'];
            }
        }
        if ($consorcio_id === null) {
            $res_con = mysqli_query($conexion, "SELECT id FROM usuarios WHERE UPPER(apellido) LIKE 'CONSORCIO%' LIMIT 1");
            $row_con = mysqli_fetch_assoc($res_con);
            if (!$row_con) {
                echo "Error: No se encontró usuario Consorcio en el sistema.";
                exit;
            }
            $consorcio_id = (int)$row_con['id'];
        }
        $GLOBALS['HHH_CONSORCIO_ID_CACHE'][$cacheKey] = $consorcio_id;
    }

    $concepto_consorcio = mysqli_real_escape_string($conexion, "COBRO EXPENSA $nombre_prop $periodo - PAGÓ $nombre_usu");
    $concepto_usu = mysqli_real_escape_string($conexion, "EXPENSAS $nombre_prop");

    $stmt_cta2 = mysqli_prepare($conexion, "INSERT INTO cuentas (usuario_id, fecha, concepto, comprobante, referencia, monto) 
             VALUES (?, ?, ?, ?, ?, ?)");
    if (!$stmt_cta2) {
        echo "Error al grabar en Consorcio: " . mysqli_error($conexion);
        exit;
    }
    mysqli_stmt_bind_param($stmt_cta2, 'issssd', $consorcio_id, $fecha, $concepto_consorcio, $comprobante_espejo_exp, $refer_periodo, $monto);
    if (!mysqli_stmt_execute($stmt_cta2)) {
        echo "Error al grabar en Consorcio: " . mysqli_error($conexion);
        mysqli_stmt_close($stmt_cta2);
        exit;
    }

    mysqli_stmt_bind_param($stmt_cta2, 'issssd', $usuario_id, $fecha, $concepto_usu, $comprobante, $refer_periodo, $monto);
    if (!mysqli_stmt_execute($stmt_cta2)) {
        echo "Error al grabar en usuario: " . mysqli_error($conexion);
        mysqli_stmt_close($stmt_cta2);
        exit;
    }

    $es_inquilino = false;
    $stmt_inq = mysqli_prepare($conexion, "SELECT 1 FROM alquileres 
        WHERE propiedad_id = ? AND estado = 'VIGENTE' AND inquilino1_id = ? LIMIT 1");
    if ($stmt_inq) {
        mysqli_stmt_bind_param($stmt_inq, 'ii', $propiedad_id_act, $usuario_id);
        mysqli_stmt_execute($stmt_inq);
        $res_inq = mysqli_stmt_get_result($stmt_inq);
        if ($res_inq && mysqli_num_rows($res_inq) > 0) {
            $es_inquilino = true;
        }
        mysqli_stmt_close($stmt_inq);
    }
    if ($es_inquilino && $propietario_id > 0 && $propietario_id != $usuario_id) {
        $concepto_prop = mysqli_real_escape_string($conexion, "COBRO EXPENSA $nombre_prop $periodo - PAGÓ $nombre_usu");
        $stmt_prop_cta = mysqli_prepare($conexion, "INSERT INTO cuentas (usuario_id, fecha, concepto, comprobante, referencia, monto) 
                 VALUES (?, ?, ?, ?, ?, ?)");
        if (!$stmt_prop_cta) {
            echo "Error al grabar en propietario: " . mysqli_error($conexion);
            exit;
        }
        mysqli_stmt_bind_param($stmt_prop_cta, 'issssd', $propietario_id, $fecha, $concepto_prop, $comprobante_espejo_exp, $refer_periodo, $monto);
        if (!mysqli_stmt_execute($stmt_prop_cta)) {
            echo "Error al grabar en propietario: " . mysqli_error($conexion);
            mysqli_stmt_close($stmt_prop_cta);
            exit;
        }
        mysqli_stmt_close($stmt_prop_cta);
    }

    if ($grabar_caja) {
        $concepto_caja = mysqli_real_escape_string($conexion, "$nombre_usu - COBRO EXPENSA $nombre_prop $periodo");
        $stmt_caja2 = mysqli_prepare($conexion, "INSERT INTO cuentas (usuario_id, fecha, concepto, comprobante, referencia, monto) 
                     VALUES (?, ?, ?, ?, ?, ?)");
        if (!$stmt_caja2) {
            echo "Error al grabar en Caja: " . mysqli_error($conexion);
            exit;
        }
        mysqli_stmt_bind_param($stmt_caja2, 'issssd', $id_caja, $fecha, $concepto_caja, $comprobante, $refer_periodo, $monto);
        if (!mysqli_stmt_execute($stmt_caja2)) {
            echo "Error al grabar en Caja: " . mysqli_error($conexion);
            mysqli_stmt_close($stmt_caja2);
            exit;
        }
        mysqli_stmt_close($stmt_caja2);
    }
}

echo "OK";
?>
