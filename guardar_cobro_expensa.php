<?php
include 'db.php';
include 'verificar_sesion.php';
if (isset($_SESSION['acceso_nivel']) && $_SESSION['acceso_nivel'] < 2) {
    header('HTTP/1.0 403 Forbidden');
    echo 'Sin permiso';
    exit;
}

define('ID_CAJA', 1);

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['usuario_id'], $_POST['propiedad_id'], $_POST['periodo'], $_POST['monto'])) {
    echo "Error: Faltan datos.";
    exit;
}

$usuario_id  = (int)$_POST['usuario_id'];
$propiedad_id = (int)$_POST['propiedad_id'];
$periodo     = trim($_POST['periodo']);
$monto       = (float)str_replace(',', '.', $_POST['monto']);

if ($usuario_id <= 0 || $propiedad_id <= 0 || $periodo === '' || $monto <= 0) {
    echo "Error: Datos inválidos.";
    exit;
}

// Formato periodo MM/AAAA o MM-AAAA
$periodo = strtoupper(preg_replace('/[^0-9\/\-]/', '', $periodo));
if (!preg_match('/^\d{1,2}[\/\-]\d{4}$/', $periodo)) {
    $periodo = str_replace('-', '/', $periodo);
}

$fecha = date('Y-m-d');

// Obtener propiedad, consorcio y propietario
$res_prop = mysqli_query($conexion, "SELECT propiedad, consorcio, propietario_id FROM propiedades WHERE propiedad_id = $propiedad_id LIMIT 1");
$row_prop = mysqli_fetch_assoc($res_prop);
$nombre_prop = $row_prop ? mysqli_real_escape_string($conexion, strtoupper($row_prop['propiedad'])) : '';
$prop_consorcio = $row_prop ? trim($row_prop['consorcio'] ?? '') : '';
$prop_consorcio_esc = mysqli_real_escape_string($conexion, $prop_consorcio);
$propietario_id = $row_prop ? (int)($row_prop['propietario_id'] ?? 0) : 0;

// Obtener usuario Consorcio: el que coincida por usuarios.consorcio = propiedades.consorcio, o el primero si no hay match
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

// Obtener nombre del usuario
$res_usu = mysqli_query($conexion, "SELECT apellido FROM usuarios WHERE id = $usuario_id LIMIT 1");
$row_usu = mysqli_fetch_assoc($res_usu);
$nombre_usu = $row_usu ? mysqli_real_escape_string($conexion, strtoupper($row_usu['apellido'])) : '';

$concepto_consorcio = "COBRO EXPENSA $nombre_prop $periodo - PAGÓ $nombre_usu";
$concepto_usu = "EXPENSAS $nombre_prop";

$concepto_consorcio = mysqli_real_escape_string($conexion, $concepto_consorcio);
$concepto_usu = mysqli_real_escape_string($conexion, $concepto_usu);
$refer_periodo = mysqli_real_escape_string($conexion, $periodo);

$grabar_caja = isset($_POST['efvo']) && ($_POST['efvo'] === '1' || $_POST['efvo'] === 'true');
$comprobante = $grabar_caja ? 'EXP/EFVO' : 'EXP/TRANSF';

// 1. Asiento en cuenta del Consorcio (INGRESO) - referencia = mes/año
$sql1 = "INSERT INTO cuentas (usuario_id, fecha, concepto, comprobante, referencia, monto) 
         VALUES ($consorcio_id, '$fecha', '$concepto_consorcio', '$comprobante', '$refer_periodo', $monto)";
if (!mysqli_query($conexion, $sql1)) {
    echo "Error al grabar en Consorcio: " . mysqli_error($conexion);
    exit;
}

// 2. Asiento en cuenta del usuario de referencia / pagador (INGRESO)
$sql2 = "INSERT INTO cuentas (usuario_id, fecha, concepto, comprobante, referencia, monto) 
         VALUES ($usuario_id, '$fecha', '$concepto_usu', '$comprobante', '$refer_periodo', $monto)";
if (!mysqli_query($conexion, $sql2)) {
    echo "Error al grabar en usuario: " . mysqli_error($conexion);
    exit;
}

// 3. Si el usuario es INQUILINO de esta propiedad: grabar también en la cuenta del PROPIETARIO
$es_inquilino = false;
$res_inq = mysqli_query($conexion, "SELECT 1 FROM alquileres 
    WHERE propiedad_id = $propiedad_id AND estado = 'VIGENTE' AND inquilino1_id = $usuario_id LIMIT 1");
if ($res_inq && mysqli_num_rows($res_inq) > 0) {
    $es_inquilino = true;
}
if ($es_inquilino && $propietario_id > 0 && $propietario_id != $usuario_id) {
    $concepto_prop = "COBRO EXPENSA $nombre_prop $periodo - PAGÓ $nombre_usu";
    $concepto_prop = mysqli_real_escape_string($conexion, $concepto_prop);
    $sql3 = "INSERT INTO cuentas (usuario_id, fecha, concepto, comprobante, referencia, monto) 
             VALUES ($propietario_id, '$fecha', '$concepto_prop', '$comprobante', '$refer_periodo', $monto)";
    if (!mysqli_query($conexion, $sql3)) {
        echo "Error al grabar en propietario: " . mysqli_error($conexion);
        exit;
    }
}
// Si el usuario es PROPIETARIO: no se graba en cuenta de inquilino (solo consorcio + propietario ya grabados)

// 4. Si es cobro en efectivo (efvo): grabar también en Caja
if ($grabar_caja) {
    $concepto_caja = "$nombre_usu - COBRO EXPENSA $nombre_prop $periodo";
    $concepto_caja = mysqli_real_escape_string($conexion, $concepto_caja);
    $sql_caja = "INSERT INTO cuentas (usuario_id, fecha, concepto, comprobante, referencia, monto) 
                 VALUES (" . ID_CAJA . ", '$fecha', '$concepto_caja', '$comprobante', '$refer_periodo', $monto)";
    if (!mysqli_query($conexion, $sql_caja)) {
        echo "Error al grabar en Caja: " . mysqli_error($conexion);
        exit;
    }
}

echo "OK";
?>
