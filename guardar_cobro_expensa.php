<?php
include 'db.php';
include 'verificar_sesion.php';
if (isset($_SESSION['acceso_nivel']) && $_SESSION['acceso_nivel'] < 2) {
    header('HTTP/1.0 403 Forbidden');
    echo 'Sin permiso';
    exit;
}

define('ID_CAJA', 1);

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
    $res_props = mysqli_query($conexion, "SELECT DISTINCT p.propiedad_id, p.propiedad, p.consorcio, p.propietario_id
        FROM propiedades p
        LEFT JOIN alquileres a ON a.propiedad_id = p.propiedad_id AND a.estado = 'VIGENTE'
        WHERE (p.propietario_id = $usuario_id OR a.inquilino1_id = $usuario_id OR a.inquilino2_id = $usuario_id)
        AND UPPER(TRIM(COALESCE(p.consorcio,''))) = UPPER('$consorcio_esc')
        ORDER BY p.propiedad ASC");
    if (!$res_props || mysqli_num_rows($res_props) == 0) {
        echo "Error: No se encontraron propiedades para este usuario en el consorcio.";
        exit;
    }
    while ($row = mysqli_fetch_assoc($res_props)) {
        $propiedades_a_cobrar[] = $row;
    }
} else {
    $res_prop = mysqli_query($conexion, "SELECT propiedad_id, propiedad, consorcio, propietario_id FROM propiedades WHERE propiedad_id = $propiedad_id LIMIT 1");
    if ($res_prop && $row = mysqli_fetch_assoc($res_prop)) {
        $propiedades_a_cobrar[] = $row;
    }
}
if (empty($propiedades_a_cobrar)) {
    echo "Error: Propiedad no encontrada.";
    exit;
}

// Obtener nombre del usuario
$res_usu = mysqli_query($conexion, "SELECT apellido FROM usuarios WHERE id = $usuario_id LIMIT 1");
$row_usu = mysqli_fetch_assoc($res_usu);
$nombre_usu = $row_usu ? mysqli_real_escape_string($conexion, strtoupper($row_usu['apellido'])) : '';

$grabar_caja = isset($_POST['efvo']) && ($_POST['efvo'] === '1' || $_POST['efvo'] === 'true');
$comprobante = $grabar_caja ? 'EXP/EFVO' : 'EXP/TRANSF';
$refer_periodo = mysqli_real_escape_string($conexion, $periodo);

// Si es pago por consorcio: usar número de recibo (ingresado o auto-generado)
$nro_recibo = null;
if ($consorcio_param !== '') {
    $recibo_ingresado = isset($_POST['recibo_numero']) ? trim($_POST['recibo_numero']) : '';
    if ($recibo_ingresado !== '') {
        $nro_recibo = $recibo_ingresado;
    } else {
        $res_rec = mysqli_query($conexion, "SELECT COALESCE(MAX(movimiento_id), 0) + 1 AS nuevo FROM cuentas");
        $row_rec = $res_rec ? mysqli_fetch_assoc($res_rec) : null;
        $nro_recibo = $row_rec ? (int)$row_rec['nuevo'] : 1;
    }
}

// Procesar cada propiedad
foreach ($propiedades_a_cobrar as $row_prop) {
    $propiedad_id_act = (int)$row_prop['propiedad_id'];
    $nombre_prop = mysqli_real_escape_string($conexion, strtoupper($row_prop['propiedad'] ?? ''));
    $prop_consorcio = trim($row_prop['consorcio'] ?? '');
    $prop_consorcio_esc = mysqli_real_escape_string($conexion, $prop_consorcio);
    $propietario_id = (int)($row_prop['propietario_id'] ?? 0);

    // Obtener usuario Consorcio
    $consorcio_id = null;
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

    if ($nro_recibo !== null) {
        $concepto_consorcio = "COBRO EXPENSA Recibo N° $nro_recibo - PAGÓ $nombre_usu";
        $concepto_usu = "Pago expensas Recibo N° $nro_recibo";
    } else {
        $concepto_consorcio = "COBRO EXPENSA $nombre_prop $periodo - PAGÓ $nombre_usu";
        $concepto_usu = "EXPENSAS $nombre_prop";
    }
    $concepto_consorcio = mysqli_real_escape_string($conexion, $concepto_consorcio);
    $concepto_usu = mysqli_real_escape_string($conexion, $concepto_usu);

    // 1. Asiento en cuenta del Consorcio (INGRESO)
    $sql1 = "INSERT INTO cuentas (usuario_id, fecha, concepto, comprobante, referencia, monto) 
             VALUES ($consorcio_id, '$fecha', '$concepto_consorcio', '$comprobante', '$refer_periodo', $monto)";
    if (!mysqli_query($conexion, $sql1)) {
        echo "Error al grabar en Consorcio: " . mysqli_error($conexion);
        exit;
    }

    // 2. Asiento en cuenta del usuario / pagador (INGRESO)
    $sql2 = "INSERT INTO cuentas (usuario_id, fecha, concepto, comprobante, referencia, monto) 
             VALUES ($usuario_id, '$fecha', '$concepto_usu', '$comprobante', '$refer_periodo', $monto)";
    if (!mysqli_query($conexion, $sql2)) {
        echo "Error al grabar en usuario: " . mysqli_error($conexion);
        exit;
    }

    // 3. Si el usuario es INQUILINO: grabar también en la cuenta del PROPIETARIO
    $es_inquilino = false;
    $res_inq = mysqli_query($conexion, "SELECT 1 FROM alquileres 
        WHERE propiedad_id = $propiedad_id_act AND estado = 'VIGENTE' AND inquilino1_id = $usuario_id LIMIT 1");
    if ($res_inq && mysqli_num_rows($res_inq) > 0) {
        $es_inquilino = true;
    }
    if ($es_inquilino && $propietario_id > 0 && $propietario_id != $usuario_id) {
        $concepto_prop = ($nro_recibo !== null)
            ? "COBRO EXPENSA Recibo N° $nro_recibo $nombre_prop $periodo - PAGÓ $nombre_usu"
            : "COBRO EXPENSA $nombre_prop $periodo - PAGÓ $nombre_usu";
        $concepto_prop = mysqli_real_escape_string($conexion, $concepto_prop);
        $sql3 = "INSERT INTO cuentas (usuario_id, fecha, concepto, comprobante, referencia, monto) 
                 VALUES ($propietario_id, '$fecha', '$concepto_prop', '$comprobante', '$refer_periodo', $monto)";
        if (!mysqli_query($conexion, $sql3)) {
            echo "Error al grabar en propietario: " . mysqli_error($conexion);
            exit;
        }
    }

    // 4. Si es cobro en efectivo: grabar también en Caja
    if ($grabar_caja) {
        $concepto_caja = ($nro_recibo !== null)
            ? "$nombre_usu - Pago expensas Recibo N° $nro_recibo"
            : "$nombre_usu - COBRO EXPENSA $nombre_prop $periodo";
        $concepto_caja = mysqli_real_escape_string($conexion, $concepto_caja);
        $sql_caja = "INSERT INTO cuentas (usuario_id, fecha, concepto, comprobante, referencia, monto) 
                     VALUES (" . ID_CAJA . ", '$fecha', '$concepto_caja', '$comprobante', '$refer_periodo', $monto)";
        if (!mysqli_query($conexion, $sql_caja)) {
            echo "Error al grabar en Caja: " . mysqli_error($conexion);
            exit;
        }
    }
}

echo "OK";
?>
