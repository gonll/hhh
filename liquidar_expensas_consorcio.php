<?php
include 'db.php';
include 'verificar_sesion.php';
if (isset($_SESSION['acceso_nivel']) && $_SESSION['acceso_nivel'] < 2) {
    header('HTTP/1.0 403 Forbidden');
    echo 'Sin permiso';
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['usuario_id'], $_POST['mes'])) {
    echo 'Error: Faltan datos (usuario_id, mes).';
    exit;
}

$consorcio_id = (int)$_POST['usuario_id'];
$mes_input   = trim($_POST['mes']);
// Aceptar MM/AAAA o MM-AAAA
$mes_input = preg_replace('/[^0-9\/\-]/', '', $mes_input);
if (!preg_match('/^\d{1,2}[\/\-]\d{4}$/', $mes_input)) {
    echo 'Error: Mes debe ser MM/AAAA (ej: 01/2025).';
    exit;
}
$partes = preg_split('/[\/\-]/', $mes_input);
$mes_num = (int)$partes[0];
$anio    = (int)$partes[1];
if ($mes_num < 1 || $mes_num > 12) {
    echo 'Error: Mes inválido.';
    exit;
}

$referencia_mes = str_pad((string)$mes_num, 2, '0', STR_PAD_LEFT) . '/' . $anio;

// Verificar que el usuario sea Consorcio
$res_u = mysqli_query($conexion, "SELECT id, apellido, consorcio FROM usuarios WHERE id = $consorcio_id LIMIT 1");
$row_u = mysqli_fetch_assoc($res_u);
if (!$row_u || stripos($row_u['apellido'], 'CONSORCIO') !== 0) {
    echo 'Error: El usuario no es un Consorcio.';
    exit;
}

$nombre_consorcio = trim($row_u['consorcio'] ?? '');
$consorcio_esc    = mysqli_real_escape_string($conexion, $nombre_consorcio);

// Última liquidación por comprobante LIQ EXPENSAS (excluir referencia = este mes para no tomar la del mismo período)
$ref_mes_esc = mysqli_real_escape_string($conexion, $referencia_mes);
$ultimo_liq_id = null;
$res_liq = mysqli_query($conexion, "SELECT movimiento_id FROM cuentas 
    WHERE usuario_id = $consorcio_id AND UPPER(TRIM(comprobante)) = 'LIQ EXPENSAS' 
    AND (referencia IS NULL OR TRIM(referencia) != '$ref_mes_esc')
    ORDER BY movimiento_id DESC LIMIT 1");
if ($res_liq && $row_liq = mysqli_fetch_assoc($res_liq)) {
    $ultimo_liq_id = (int)$row_liq['movimiento_id'];
}

// Condición de período: después de la última LIQ EXPENSAS
$cond_periodo = $ultimo_liq_id !== null
    ? "AND movimiento_id > $ultimo_liq_id"
    : "";

// Total expensas = suma de todos los movimientos negativos en el período
$res_sum = mysqli_query($conexion, "SELECT COALESCE(SUM(monto), 0) AS total FROM cuentas 
    WHERE usuario_id = $consorcio_id AND monto < 0 $cond_periodo");
$row_sum = mysqli_fetch_assoc($res_sum);
$suma_negativos = (float)($row_sum['total'] ?? 0);
$total_expensa   = abs($suma_negativos);

// Suma solo movimientos con comprobante "Exp Extraordinaria" (extraordinarias)
$res_extra = mysqli_query($conexion, "SELECT COALESCE(SUM(monto), 0) AS total FROM cuentas 
    WHERE usuario_id = $consorcio_id AND monto < 0 $cond_periodo 
    AND UPPER(TRIM(comprobante)) = 'EXP EXTRAORDINARIA'");
$row_extra = mysqli_fetch_assoc($res_extra);
$suma_extraordinarias = abs((float)($row_extra['total'] ?? 0));

// Ordinarias = total menos extraordinarias
$total_ordinarias    = $total_expensa - $suma_extraordinarias;
$total_extraordinarias = $suma_extraordinarias;

$fecha_hoy = date('Y-m-d');
$ref_esc = mysqli_real_escape_string($conexion, $referencia_mes);
$monto_ord_fmt = number_format($total_ordinarias, 2, ',', '.');
$monto_ext_fmt = number_format($total_extraordinarias, 2, ',', '.');
$concepto_consorcio = "LIQUIDACIÓN DE EXPENSAS Ordinarias $monto_ord_fmt - Extraordinarias $monto_ext_fmt";
$concepto_consorcio = mysqli_real_escape_string($conexion, $concepto_consorcio);

// 1. Movimiento en cuenta del consorcio: monto 0, concepto con ambos totales, comprobante LIQ EXPENSAS
$sql_cons = "INSERT INTO cuentas (usuario_id, fecha, concepto, comprobante, referencia, monto) 
             VALUES ($consorcio_id, '$fecha_hoy', '$concepto_consorcio', 'LIQ EXPENSAS', '$ref_esc', 0)";
if (!mysqli_query($conexion, $sql_cons)) {
    echo 'Error al grabar liquidación en Consorcio: ' . mysqli_error($conexion);
    exit;
}

// 2. Propiedades: cargar ordinarias en propietario e inquilino (como antes); extraordinarias solo en propietario
$cond_consorcio = $nombre_consorcio === ''
    ? "AND (p.consorcio IS NULL OR TRIM(p.consorcio) = '')"
    : "AND UPPER(TRIM(p.consorcio)) = UPPER('$consorcio_esc')";
$res_prop = mysqli_query($conexion, "SELECT p.propiedad_id, p.propietario_id, p.porcentaje, p.propiedad 
    FROM propiedades p 
    WHERE p.porcentaje IS NOT NULL AND p.porcentaje > 0 $cond_consorcio");
if (!$res_prop) {
    echo 'OK';
    exit;
}

while ($prop = mysqli_fetch_assoc($res_prop)) {
    $propietario_id = (int)$prop['propietario_id'];
    $porcentaje     = (float)$prop['porcentaje'];
    $nombre_prop    = mysqli_real_escape_string($conexion, trim($prop['propiedad'] ?? ''));
    $propiedad_id   = (int)$prop['propiedad_id'];

    // Ordinarias: monto por % → propietario e inquilino (como antes)
    $monto_ord = round($total_ordinarias * ($porcentaje / 100), 2);
    if ($monto_ord > 0) {
        $concepto_ord = "EXPENSAS, $nombre_prop";
        $concepto_ord = mysqli_real_escape_string($conexion, $concepto_ord);
        $sql_cuenta = "INSERT INTO cuentas (usuario_id, fecha, concepto, comprobante, referencia, monto) 
                       VALUES ($propietario_id, '$fecha_hoy', '$concepto_ord', 'LIQ EXP', '$ref_esc', -$monto_ord)";
        mysqli_query($conexion, $sql_cuenta);

        $res_inq = mysqli_query($conexion, "SELECT inquilino1_id FROM alquileres 
            WHERE propiedad_id = $propiedad_id AND estado = 'VIGENTE' LIMIT 1");
        if ($res_inq && $row_inq = mysqli_fetch_assoc($res_inq)) {
            $inquilino_id = (int)$row_inq['inquilino1_id'];
            if ($inquilino_id > 0) {
                $sql_inq = "INSERT INTO cuentas (usuario_id, fecha, concepto, comprobante, referencia, monto) 
                            VALUES ($inquilino_id, '$fecha_hoy', '$concepto_ord', 'LIQ EXP', '$ref_esc', -$monto_ord)";
                mysqli_query($conexion, $sql_inq);
            }
        }
    }

    // Extraordinarias: monto por % solo en cuenta del propietario
    $monto_ext = round($total_extraordinarias * ($porcentaje / 100), 2);
    if ($monto_ext > 0) {
        $concepto_ext = "EXPENSAS EXTRAORDINARIAS, $nombre_prop";
        $concepto_ext = mysqli_real_escape_string($conexion, $concepto_ext);
        $sql_ext = "INSERT INTO cuentas (usuario_id, fecha, concepto, comprobante, referencia, monto) 
                    VALUES ($propietario_id, '$fecha_hoy', '$concepto_ext', 'LIQ EXP EXT', '$ref_esc', -$monto_ext)";
        mysqli_query($conexion, $sql_ext);
    }
}

echo 'OK';
?>
