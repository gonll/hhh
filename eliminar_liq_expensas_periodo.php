<?php
include 'db.php';
include 'verificar_sesion.php';

if (isset($_SESSION['acceso_nivel']) && $_SESSION['acceso_nivel'] < 3) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => false, 'msg' => 'Sin permiso (requiere nivel 3).']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['periodo'], $_POST['consorcio_id'])) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => false, 'msg' => 'Faltan período o consorcio.']);
    exit;
}

$consorcio_id = (int)$_POST['consorcio_id'];
if ($consorcio_id <= 0) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => false, 'msg' => 'Consorcio inválido.']);
    exit;
}

$res_u = mysqli_query($conexion, "SELECT id, apellido, consorcio FROM usuarios WHERE id = $consorcio_id LIMIT 1");
$row_u = mysqli_fetch_assoc($res_u);
if (!$row_u || stripos($row_u['apellido'], 'CONSORCIO') !== 0) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => false, 'msg' => 'El usuario no es un Consorcio.']);
    exit;
}

$nombre_consorcio = trim($row_u['consorcio'] ?? '');
$consorcio_esc = mysqli_real_escape_string($conexion, $nombre_consorcio);

$periodo = trim($_POST['periodo']);
$periodo = preg_replace('/[^0-9\/\-]/', '', $periodo);
if (!preg_match('/^\d{1,2}[\/\-]\d{4}$/', $periodo)) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => false, 'msg' => 'Período inválido. Use MM/AAAA (ej: 02/2026).']);
    exit;
}

$partes = preg_split('/[\/\-]/', $periodo);
$mes = (int)$partes[0];
$anio = (int)$partes[1];
if ($mes < 1 || $mes > 12) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => false, 'msg' => 'Mes inválido.']);
    exit;
}

$referencia = str_pad((string)$mes, 2, '0', STR_PAD_LEFT) . '/' . $anio;
$ref_esc = mysqli_real_escape_string($conexion, $referencia);

$eliminados = 0;

// 1. Eliminar LIQ EXPENSAS en cuenta del consorcio
$sql1 = "DELETE FROM cuentas 
    WHERE usuario_id = $consorcio_id 
    AND TRIM(referencia) = '$ref_esc' 
    AND UPPER(TRIM(comprobante)) = 'LIQ EXPENSAS'";
if (mysqli_query($conexion, $sql1)) {
    $eliminados += mysqli_affected_rows($conexion);
}

// 2. Obtener usuario_ids de propietarios e inquilinos del consorcio
$cond_consorcio = $nombre_consorcio === ''
    ? "AND (p.consorcio IS NULL OR TRIM(p.consorcio) = '')"
    : "AND UPPER(TRIM(p.consorcio)) = UPPER('$consorcio_esc')";

$ids_usuarios = [];
$res_prop = mysqli_query($conexion, "SELECT p.propiedad_id, p.propietario_id FROM propiedades p 
    WHERE p.porcentaje IS NOT NULL AND p.porcentaje > 0 $cond_consorcio");
if ($res_prop) {
    while ($p = mysqli_fetch_assoc($res_prop)) {
        $ids_usuarios[(int)$p['propietario_id']] = true;
        $propiedad_id = (int)$p['propiedad_id'];
        $res_inq = mysqli_query($conexion, "SELECT inquilino1_id FROM alquileres 
            WHERE propiedad_id = $propiedad_id AND estado = 'VIGENTE' LIMIT 1");
        if ($res_inq && $row_inq = mysqli_fetch_assoc($res_inq) && (int)$row_inq['inquilino1_id'] > 0) {
            $ids_usuarios[(int)$row_inq['inquilino1_id']] = true;
        }
    }
}

if (count($ids_usuarios) > 0) {
    $ids_lista = implode(',', array_map('intval', array_keys($ids_usuarios)));
    $sql2 = "DELETE FROM cuentas 
        WHERE usuario_id IN ($ids_lista) 
        AND TRIM(referencia) = '$ref_esc' 
        AND (UPPER(TRIM(comprobante)) = 'LIQ EXP' OR UPPER(TRIM(comprobante)) = 'LIQ EXP EXT')";
    if (mysqli_query($conexion, $sql2)) {
        $eliminados += mysqli_affected_rows($conexion);
    }
}

header('Content-Type: application/json; charset=utf-8');
echo json_encode([
    'ok' => true,
    'msg' => "Se eliminaron $eliminados movimientos de liquidación del consorcio (período $referencia).",
    'eliminados' => $eliminados,
    'periodo' => $referencia
]);
