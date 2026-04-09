<?php
include 'db.php';
include 'verificar_sesion.php';
require_once __DIR__ . '/helpers_tenant_inmobiliaria.php';
tenant_inmob_asegurar_esquema($conexion);
if (isset($_SESSION['acceso_nivel']) && $_SESSION['acceso_nivel'] < 2) {
    header('HTTP/1.0 403 Forbidden');
    echo '';
    exit;
}
$usuario_id = isset($_GET['usuario_id']) ? (int)$_GET['usuario_id'] : 0;
if ($usuario_id < 1) {
    echo '';
    exit;
}
if (!tenant_inmob_usuario_id_visible($conexion, $usuario_id)) {
    echo '';
    exit;
}
// Última liquidación LIQ EXPENSAS para este consorcio
$res = mysqli_query($conexion, "SELECT referencia FROM cuentas 
    WHERE usuario_id = $usuario_id AND UPPER(TRIM(comprobante)) = 'LIQ EXPENSAS' 
    AND referencia IS NOT NULL AND TRIM(referencia) != ''
    ORDER BY movimiento_id DESC LIMIT 1");
$siguiente_mes = '';
if ($res && $row = mysqli_fetch_assoc($res)) {
    $ref = trim($row['referencia']);
    if (preg_match('/^(\d{1,2})[\/\-](\d{4})$/', $ref, $m)) {
        $mes = (int)$m[1];
        $anio = (int)$m[2];
        $mes++;
        if ($mes > 12) {
            $mes = 1;
            $anio++;
        }
        $siguiente_mes = str_pad((string)$mes, 2, '0', STR_PAD_LEFT) . '/' . $anio;
    }
}
if ($siguiente_mes === '') {
    $siguiente_mes = date('m/Y');
}
header('Content-Type: text/plain; charset=utf-8');
echo $siguiente_mes;
