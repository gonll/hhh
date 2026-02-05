<?php
include 'db.php';
include 'verificar_sesion.php';

if (!isset($_GET['id'])) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Falta id']);
    exit;
}

$id = (int)$_GET['id'];
$res_u = mysqli_query($conexion, "SELECT id, apellido FROM usuarios WHERE id = $id LIMIT 1");
$row_u = mysqli_fetch_assoc($res_u);
if (!$row_u || stripos($row_u['apellido'], 'CONSORCIO') !== 0) {
    header('Content-Type: application/json');
    echo json_encode(['liq_ordinarias' => null, 'liq_extraordinarias' => null, 'cobrado_mes' => 0, 'gastado_mes' => 0]);
    exit;
}

// Último registro con comprobante LIQ EXPENSAS: extraer montos Ordinarias y Extraordinarias del concepto
$liq_ordinarias = null;
$liq_extraordinarias = null;
$ultimo_liq_id = null;
$res_liq = mysqli_query($conexion, "SELECT movimiento_id, concepto FROM cuentas 
    WHERE usuario_id = $id AND UPPER(TRIM(comprobante)) = 'LIQ EXPENSAS' 
    ORDER BY movimiento_id DESC LIMIT 1");
if ($res_liq && $row_liq = mysqli_fetch_assoc($res_liq)) {
    $ultimo_liq_id = (int)$row_liq['movimiento_id'];
    $concepto = isset($row_liq['concepto']) ? trim($row_liq['concepto']) : '';
    // Formato: "LIQUIDACIÓN DE EXPENSAS Ordinarias 0,00 - Extraordinarias 2.500,00" (argentino: punto miles, coma decimal)
    if (preg_match('/Ordinarias\s+([\d.,]+)\s*-\s*Extraordinarias\s+([\d.,]+)/i', $concepto, $m)) {
        $toFloat = function ($s) {
            $s = str_replace('.', '', $s); // quitar separador de miles
            $s = str_replace(',', '.', $s); // coma decimal a punto
            return (float) $s;
        };
        $liq_ordinarias = $toFloat($m[1]);
        $liq_extraordinarias = $toFloat($m[2]);
    }
}

if ($ultimo_liq_id !== null) {
    $res_cob = mysqli_query($conexion, "SELECT COALESCE(SUM(monto), 0) AS total FROM cuentas 
        WHERE usuario_id = $id AND monto > 0 AND movimiento_id > $ultimo_liq_id");
    $res_gast = mysqli_query($conexion, "SELECT COALESCE(SUM(ABS(monto)), 0) AS total FROM cuentas 
        WHERE usuario_id = $id AND monto < 0 AND movimiento_id > $ultimo_liq_id");
} else {
    $res_cob = mysqli_query($conexion, "SELECT COALESCE(SUM(monto), 0) AS total FROM cuentas 
        WHERE usuario_id = $id AND monto > 0");
    $res_gast = mysqli_query($conexion, "SELECT COALESCE(SUM(ABS(monto)), 0) AS total FROM cuentas 
        WHERE usuario_id = $id AND monto < 0");
}
$cobrado_mes = (float)(mysqli_fetch_assoc($res_cob)['total'] ?? 0);
$gastado_mes = (float)(mysqli_fetch_assoc($res_gast)['total'] ?? 0);

header('Content-Type: application/json');
echo json_encode([
    'liq_ordinarias'      => $liq_ordinarias,
    'liq_extraordinarias' => $liq_extraordinarias,
    'cobrado_mes'         => $cobrado_mes,
    'gastado_mes'         => $gastado_mes
]);
