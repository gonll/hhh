<?php
include 'db.php';
include 'verificar_sesion.php';
require_once __DIR__ . '/helpers_tenant_inmobiliaria.php';
tenant_inmob_asegurar_esquema($conexion);

header('Content-Type: application/json; charset=utf-8');

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($id <= 0 || !tenant_inmob_usuario_id_visible($conexion, $id)) {
    echo json_encode(['ok' => false, 'msg' => 'Sin permiso'], JSON_UNESCAPED_UNICODE);
    exit;
}

$hoy = date('Y-m-d');
$hoy_esc = mysqli_real_escape_string($conexion, $hoy);

$fmtFecha = static function ($fd) {
    if ($fd === null || $fd === '' || $fd === '0000-00-00') {
        return null;
    }
    $ts = strtotime((string) $fd);

    return $ts ? date('d/m/Y', $ts) : null;
};

$labelAlquiler = static function ($consorcio, $propiedad) {
    $c = trim((string) $consorcio);
    $p = trim((string) $propiedad);
    if ($c !== '' && $p !== '') {
        return $c . ' · ' . $p;
    }
    if ($p !== '') {
        return $p;
    }
    if ($c !== '') {
        return $c;
    }

    return 'Propiedad';
};

$filas = [];

if (tenant_inmob_tabla_existe($conexion, 'alquileres')) {
    $tw_p = tenant_inmob_sql_propiedades($conexion, 'p');
    $sql = 'SELECT a.fecha_fin, p.consorcio, p.propiedad
        FROM alquileres a
        INNER JOIN propiedades p ON p.propiedad_id = a.propiedad_id AND (' . $tw_p . ")
        WHERE a.estado = 'VIGENTE' AND (a.inquilino1_id = {$id} OR a.inquilino2_id = {$id})
        ORDER BY COALESCE(p.consorcio, ''), COALESCE(p.propiedad, ''), (a.fecha_fin IS NULL), a.fecha_fin ASC";
    $r = mysqli_query($conexion, $sql);
    if ($r) {
        while ($row = mysqli_fetch_assoc($r)) {
            $filas[] = [
                'origen' => 'alquiler',
                'propiedad' => $labelAlquiler($row['consorcio'] ?? '', $row['propiedad'] ?? ''),
                'vencimiento' => $fmtFecha($row['fecha_fin'] ?? null),
            ];
        }
    }
}

if (tenant_inmob_tabla_existe($conexion, 'arriendos')) {
    $sql = 'SELECT id, descripcion_finca, fecha_vencimiento_contrato FROM arriendos
        WHERE arrendatario_id = ' . $id . "
        AND (fecha_vencimiento_contrato IS NULL OR fecha_vencimiento_contrato >= '{$hoy_esc}')
        ORDER BY descripcion_finca ASC, id ASC";
    $r = mysqli_query($conexion, $sql);
    if ($r) {
        while ($row = mysqli_fetch_assoc($r)) {
            $finca = trim((string) ($row['descripcion_finca'] ?? ''));
            $label = $finca !== '' ? $finca : ('Arriendo campo #' . (int) ($row['id'] ?? 0));
            $filas[] = [
                'origen' => 'arriendo',
                'propiedad' => $label,
                'vencimiento' => $fmtFecha($row['fecha_vencimiento_contrato'] ?? null),
            ];
        }
    }
}

$es_inquilino = $filas !== [];
$texto = $es_inquilino ? '' : 'No consta como inquilino/a con contrato vigente.';

echo json_encode([
    'ok' => true,
    'es_inquilino' => $es_inquilino,
    'filas' => $filas,
    'texto' => $texto,
], JSON_UNESCAPED_UNICODE);
