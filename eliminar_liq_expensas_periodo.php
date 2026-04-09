<?php
include 'db.php';
include 'verificar_sesion.php';
require_once __DIR__ . '/helpers_tenant_inmobiliaria.php';
tenant_inmob_asegurar_esquema($conexion);

if ((int)($_SESSION['acceso_nivel'] ?? 0) < 3) {
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
if (!$res_u) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => false, 'msg' => 'Error de consulta: ' . mysqli_error($conexion)]);
    exit;
}
$row_u = mysqli_fetch_assoc($res_u);
if (!$row_u || stripos($row_u['apellido'], 'CONSORCIO') !== 0) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => false, 'msg' => 'El usuario no es un Consorcio.']);
    exit;
}
if (!tenant_inmob_usuario_id_visible($conexion, $consorcio_id)) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => false, 'msg' => 'Sin permiso.']);
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
// En la base a veces queda sin cero a la izquierda (3/2026 vs 03/2026)
$referencia_sin_pad = $mes . '/' . $anio;
$ref_sin_pad_esc = mysqli_real_escape_string($conexion, $referencia_sin_pad);

$cond_misma_ref = "(TRIM(referencia) = '$ref_esc' OR TRIM(referencia) = '$ref_sin_pad_esc')";

// Mes siguiente: Honorarios van con ref del mes siguiente (liquidar_expensas_consorcio.php)
$mes_sig = ($mes >= 12) ? 1 : $mes + 1;
$anio_sig = ($mes >= 12) ? $anio + 1 : $anio;
$ref_hon = str_pad((string)$mes_sig, 2, '0', STR_PAD_LEFT) . '/' . $anio_sig;
$ref_hon_esc = mysqli_real_escape_string($conexion, $ref_hon);
$ref_hon_sin_pad = $mes_sig . '/' . $anio_sig;
$ref_hon_sin_pad_esc = mysqli_real_escape_string($conexion, $ref_hon_sin_pad);
$cond_ref_hon = "(TRIM(referencia) = '$ref_hon_esc' OR TRIM(referencia) = '$ref_hon_sin_pad_esc')";

$eliminados = 0;

// 1. LIQ EXPENSAS en cuenta del consorcio (mismo período liquidado)
$sql1 = "DELETE FROM cuentas 
    WHERE usuario_id = $consorcio_id 
    AND $cond_misma_ref 
    AND UPPER(TRIM(comprobante)) = 'LIQ EXPENSAS'";
if (!mysqli_query($conexion, $sql1)) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => false, 'msg' => 'Error al borrar LIQ EXPENSAS: ' . mysqli_error($conexion)]);
    exit;
}
$eliminados += mysqli_affected_rows($conexion);

// 1b. Honorarios en cuenta del consorcio (referencia = mes siguiente al período liquidado)
$sql_hon = "DELETE FROM cuentas 
    WHERE usuario_id = $consorcio_id 
    AND $cond_ref_hon 
    AND UPPER(TRIM(comprobante)) = 'HONORARIOS'";
if (!mysqli_query($conexion, $sql_hon)) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => false, 'msg' => 'Error al borrar Honorarios: ' . mysqli_error($conexion)]);
    exit;
}
$eliminados += mysqli_affected_rows($conexion);

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
        if ($res_inq) {
            $row_inq = mysqli_fetch_assoc($res_inq);
            if ($row_inq !== null && is_array($row_inq) && (int)($row_inq['inquilino1_id'] ?? 0) > 0) {
                $ids_usuarios[(int)$row_inq['inquilino1_id']] = true;
            }
        }
    }
}

if (count($ids_usuarios) > 0) {
    $ids_lista = implode(',', array_map('intval', array_keys($ids_usuarios)));
    $sql2 = "DELETE FROM cuentas 
        WHERE usuario_id IN ($ids_lista) 
        AND $cond_misma_ref 
        AND (UPPER(TRIM(comprobante)) = 'LIQ EXP' OR UPPER(TRIM(comprobante)) = 'LIQ EXP EXT')";
    if (!mysqli_query($conexion, $sql2)) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => false, 'msg' => 'Error al borrar LIQ EXP: ' . mysqli_error($conexion)]);
        exit;
    }
    $eliminados += mysqli_affected_rows($conexion);
}

header('Content-Type: application/json; charset=utf-8');
echo json_encode([
    'ok' => true,
    'msg' => "Se eliminaron $eliminados movimientos de liquidación (LIQ EXPENSAS, Honorarios mes siguiente, LIQ EXP / LIQ EXP EXT; período $referencia).",
    'eliminados' => $eliminados,
    'periodo' => $referencia
]);
