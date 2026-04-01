<?php
include 'db.php';
include 'verificar_sesion.php';
include 'helpers_propiedad.php';
if (isset($_SESSION['acceso_nivel']) && $_SESSION['acceso_nivel'] < 2) {
    header('Location: index.php?msg=sin_permiso');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo "Método no permitido";
    exit;
}

// Datos que envía el formulario de contrato_alquiler.php
$propiedad_id  = (int)$_POST['propiedad_id'];
$inq1_id       = (int)$_POST['inq1_id'];
$inq2_id       = isset($_POST['inq2_id']) && $_POST['inq2_id'] !== '' ? (int)$_POST['inq2_id'] : null;
$cod1_id       = (int)$_POST['cod1_id'];
$cod2_id       = isset($_POST['cod2_id']) && $_POST['cod2_id'] !== '' ? (int)$_POST['cod2_id'] : null;
$plazo         = (int)$_POST['plazo'];
$destino       = mysqli_real_escape_string($conexion, $_POST['destino']);
$fecha_inicio  = trim((string)($_POST['fecha_inicio'] ?? ''));
$fecha_fin     = trim((string)($_POST['fecha_fin'] ?? ''));
$precio        = (float)$_POST['precio'];
$fecha_firma   = trim((string)($_POST['fecha_firma'] ?? ''));

if (!fecha_valida_ymd($fecha_inicio)) {
    echo 'Fecha de inicio inválida. Use el calendario o el formato AAAA-MM-DD.';
    exit;
}
if (!fecha_valida_ymd($fecha_firma)) {
    echo 'Fecha de firma inválida.';
    exit;
}
$recalculada = calcular_fecha_fin_alquiler_ymd($fecha_inicio, $plazo);
if (!fecha_valida_ymd($fecha_fin)) {
    $fecha_fin = $recalculada;
}
if (!$fecha_fin || !fecha_valida_ymd($fecha_fin)) {
    echo 'No se pudo determinar la fecha fin del contrato. Revise plazo y fecha de inicio.';
    exit;
}

$fecha_inicio  = mysqli_real_escape_string($conexion, $fecha_inicio);
$fecha_fin     = mysqli_real_escape_string($conexion, $fecha_fin);
$fecha_firma   = mysqli_real_escape_string($conexion, $fecha_firma);
// El depósito de garantía siempre será 1.5 veces el precio del alquiler
$deposito      = round($precio * 1.5, 2);
$nom_prop_raw   = isset($_POST['nom_prop']) ? trim($_POST['nom_prop']) : '';
$nom_prop       = mysqli_real_escape_string($conexion, omitir_ciudad_provincia($nom_prop_raw));

if ($propiedad_id <= 0 || $inq1_id <= 0 || $cod1_id <= 0) {
    echo "Faltan datos obligatorios (propiedad, inquilino 1 o codeudor 1).";
    exit;
}

mysqli_begin_transaction($conexion);

try {
    // Inquilino2 y codeudor2: NULL si no se enviaron
    $sqlAlquiler = "INSERT INTO alquileres (
        propiedad_id, inquilino1_id, inquilino2_id, codeudor1_id, codeudor2_id,
        plazo_meses, destino, fecha_inicio, fecha_fin, precio_convenido, monto_deposito, fecha_firma, estado
    ) VALUES (
        ?, ?, ?, ?, ?,
        ?, ?, ?, ?, ?, ?, ?, 'VIGENTE'
    )";
    $stmtAlq = mysqli_prepare($conexion, $sqlAlquiler);
    if (!$stmtAlq) {
        throw new Exception("Error al guardar contrato: " . mysqli_error($conexion));
    }
    $inq2_param = $inq2_id ?: null;
    $cod2_param = $cod2_id ?: null;
    mysqli_stmt_bind_param(
        $stmtAlq,
        'iiiiiissddds',
        $propiedad_id,
        $inq1_id,
        $inq2_param,
        $cod1_id,
        $cod2_param,
        $plazo,
        $destino,
        $fecha_inicio,
        $fecha_fin,
        $precio,
        $deposito,
        $fecha_firma
    );
    if (!mysqli_stmt_execute($stmtAlq)) {
        $err = mysqli_error($conexion);
        mysqli_stmt_close($stmtAlq);
        throw new Exception("Error al guardar contrato: " . $err);
    }
    mysqli_stmt_close($stmtAlq);

    // Marcar la propiedad como alquilada
    $sqlProp = "UPDATE propiedades SET alquiler = 1 WHERE propiedad_id = ?";
    $stmtProp = mysqli_prepare($conexion, $sqlProp);
    if (!$stmtProp) {
        throw new Exception("Error al actualizar propiedad: " . mysqli_error($conexion));
    }
    mysqli_stmt_bind_param($stmtProp, 'i', $propiedad_id);
    if (!mysqli_stmt_execute($stmtProp)) {
        $err = mysqli_error($conexion);
        mysqli_stmt_close($stmtProp);
        throw new Exception("Error al actualizar propiedad: " . $err);
    }
    mysqli_stmt_close($stmtProp);

    // Fecha actual para los movimientos
    $fecha_hoy = date('Y-m-d');

    // 1. Retiro: depósito de garantía (en la cuenta del inquilino 1)
    $monto_dep_neg = -abs($deposito);
    $concepto1 = mysqli_real_escape_string($conexion, strtoupper("Inicio del contrato " . omitir_ciudad_provincia($nom_prop_raw)));
    $comprobante1 = "DEPÓSITO";
    $referencia1 = "GARANTÍA";
    $sqlCuenta1 = "INSERT INTO cuentas (usuario_id, fecha, concepto, comprobante, referencia, monto) 
                   VALUES (?, ?, ?, ?, ?, ?)";
    $stmtCta1 = mysqli_prepare($conexion, $sqlCuenta1);
    if (!$stmtCta1) {
        throw new Exception("Error al registrar depósito en cuenta: " . mysqli_error($conexion));
    }
    mysqli_stmt_bind_param($stmtCta1, 'issssd', $inq1_id, $fecha_hoy, $concepto1, $comprobante1, $referencia1, $monto_dep_neg);
    if (!mysqli_stmt_execute($stmtCta1)) {
        $err = mysqli_error($conexion);
        mysqli_stmt_close($stmtCta1);
        throw new Exception("Error al registrar depósito en cuenta: " . $err);
    }
    mysqli_stmt_close($stmtCta1);

    // 2. Retiro: alquiler proporcional al mes de inicio (días desde fecha_inicio al fin del mes)
    $ts_inicio = strtotime($fecha_inicio);
    $dias_en_mes = (int)date('t', $ts_inicio);
    $dia_inicio = (int)date('j', $ts_inicio);
    $dias_proporcionales = $dias_en_mes - $dia_inicio + 1;
    $monto_proporcional = -($precio * ($dias_proporcionales / $dias_en_mes));
    $mes_ano = date('m/Y', $ts_inicio);
    $concepto2 = strtoupper("Alquiler $nom_prop proporcional $dias_proporcionales días");
    $comprobante2 = "LIQ ALQUILER";
    $referencia2 = $mes_ano;
    $sqlCuenta2 = "INSERT INTO cuentas (usuario_id, fecha, concepto, comprobante, referencia, monto) 
                   VALUES (?, ?, ?, ?, ?, ?)";
    $stmtCta2 = mysqli_prepare($conexion, $sqlCuenta2);
    if (!$stmtCta2) {
        throw new Exception("Error al registrar alquiler proporcional en cuenta: " . mysqli_error($conexion));
    }
    mysqli_stmt_bind_param($stmtCta2, 'issssd', $inq1_id, $fecha_inicio, $concepto2, $comprobante2, $referencia2, $monto_proporcional);
    if (!mysqli_stmt_execute($stmtCta2)) {
        $err = mysqli_error($conexion);
        mysqli_stmt_close($stmtCta2);
        throw new Exception("Error al registrar alquiler proporcional en cuenta: " . $err);
    }
    mysqli_stmt_close($stmtCta2);

    mysqli_commit($conexion);
    echo "OK";
} catch (Exception $e) {
    mysqli_rollback($conexion);
    echo $e->getMessage();
}
?>
