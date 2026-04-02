<?php
include 'db.php';
include 'verificar_sesion.php';
require_once __DIR__ . '/includes_propiedad_fotos_mapa.php';

if (isset($_SESSION['acceso_nivel']) && $_SESSION['acceso_nivel'] < 2) {
    header('Location: propiedades.php?msg=sin_permiso');
    exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['propiedad_id'])) {
    header('Location: propiedades.php');
    exit;
}

propiedades_asegurar_columnas($conexion);

$id        = (int)$_POST['propiedad_id'];
$propiedad = trim($_POST['propiedad'] ?? '');
$ciudad    = trim($_POST['ciudad'] ?? '');
$consorcio = trim($_POST['consorcio'] ?? '');
$porcentaje_raw = trim($_POST['porcentaje'] ?? '');
$porcentaje_raw = str_replace(',', '.', $porcentaje_raw);
$porcentaje = $porcentaje_raw !== '' ? (float)$porcentaje_raw : null;
$padron    = trim($_POST['padron'] ?? '');
$detalle   = trim($_POST['detalle'] ?? '');

$mapa_lat = null;
$mapa_lng = null;
if (isset($_POST['mapa_lat']) && trim((string)$_POST['mapa_lat']) !== '') {
    $mapa_lat = (float)str_replace(',', '.', trim($_POST['mapa_lat']));
}
if (isset($_POST['mapa_lng']) && trim((string)$_POST['mapa_lng']) !== '') {
    $mapa_lng = (float)str_replace(',', '.', trim($_POST['mapa_lng']));
}
$mapa_enlace_raw = trim($_POST['mapa_enlace'] ?? '');
$mapa_enlace = $mapa_enlace_raw === '' ? null : $mapa_enlace_raw;

if ($id <= 0 || $propiedad === '') {
    header('Location: propiedades.php');
    exit;
}

if (!propiedades_columna_existe($conexion, 'mapa_lat') || !propiedades_columna_existe($conexion, 'fotos_json')) {
    error_log('actualizar_propiedad: faltan columnas mapa/fotos.');
    header('Location: editar_propiedad.php?id=' . $id . '&error=migracion');
    exit;
}

if ($padron !== '') {
    $stmt_ex = mysqli_prepare($conexion, "SELECT propiedad_id FROM propiedades WHERE padron = ? AND propiedad_id != ? LIMIT 1");
    if ($stmt_ex) {
        mysqli_stmt_bind_param($stmt_ex, 'si', $padron, $id);
        mysqli_stmt_execute($stmt_ex);
        $existe = mysqli_stmt_get_result($stmt_ex);
        if ($existe && mysqli_num_rows($existe) > 0) {
            mysqli_stmt_close($stmt_ex);
            header('Location: editar_propiedad.php?id=' . $id . '&error=padron_duplicado');
            exit;
        }
        mysqli_stmt_close($stmt_ex);
    }
}

$ciudad_param = ($ciudad === '') ? null : $ciudad;
$porcentaje_param = ($porcentaje !== null) ? $porcentaje : null;
$mapa_enlace_param = $mapa_enlace;

$sql = "UPDATE propiedades SET 
        propiedad = ?, 
        ciudad = ?, 
        consorcio = ?, 
        porcentaje = ?, 
        padron = ?, 
        detalle = ?,
        mapa_lat = ?,
        mapa_lng = ?,
        mapa_enlace = ?
        WHERE propiedad_id = ?";
$stmt = mysqli_prepare($conexion, $sql);
if (!$stmt) {
    echo "Error al preparar.";
    exit;
}
mysqli_stmt_bind_param(
    $stmt,
    'sssdssddsi',
    $propiedad,
    $ciudad_param,
    $consorcio,
    $porcentaje_param,
    $padron,
    $detalle,
    $mapa_lat,
    $mapa_lng,
    $mapa_enlace_param,
    $id
);

if (mysqli_stmt_execute($stmt)) {
    mysqli_stmt_close($stmt);
    $existentes = [];
    $r0 = mysqli_query($conexion, "SELECT fotos_json FROM propiedades WHERE propiedad_id = " . (int)$id . " LIMIT 1");
    if ($r0 && $row0 = mysqli_fetch_assoc($r0)) {
        $existentes = propiedades_fotos_desde_json($row0['fotos_json'] ?? null);
    }
    $nuevas = propiedades_procesar_subida_fotos($id, 'fotos');
    if (count($nuevas) > 0) {
        $todas = array_merge($existentes, $nuevas);
        if (!propiedades_guardar_json_fotos($conexion, $id, $todas)) {
            error_log('actualizar_propiedad: fotos_json id=' . $id . ' err=' . mysqli_error($conexion));
        }
    }
    header('Location: propiedades.php?ok=1');
} else {
    error_log('actualizar_propiedad execute: ' . mysqli_stmt_error($stmt));
    mysqli_stmt_close($stmt);
    header('Location: editar_propiedad.php?id=' . $id . '&error=1');
}
exit;
