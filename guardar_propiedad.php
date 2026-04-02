<?php
include 'db.php';
include 'verificar_sesion.php';
require_once __DIR__ . '/includes_propiedad_fotos_mapa.php';
propiedades_asegurar_columnas($conexion);

if (isset($_SESSION['acceso_nivel']) && $_SESSION['acceso_nivel'] < 2) {
    header('Location: propiedades.php?msg=sin_permiso');
    exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['propiedad'])) {
    header('Location: propiedades.php');
    exit;
}

$propietario_id = (int)($_POST['propietario_id'] ?? 0);
$propiedad      = trim($_POST['propiedad'] ?? '');
$ciudad         = trim($_POST['ciudad'] ?? '');
$consorcio      = trim($_POST['consorcio'] ?? '');
$porcentaje_raw = trim($_POST['porcentaje'] ?? '');
$porcentaje_raw = str_replace(',', '.', $porcentaje_raw);
$porcentaje     = $porcentaje_raw !== '' ? (float)$porcentaje_raw : null;
$padron         = trim($_POST['padron'] ?? '');
$detalle        = trim($_POST['detalle'] ?? '');

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

if ($propiedad === '' || $propietario_id <= 0) {
    header('Location: nueva_propiedad.php?error=1');
    exit;
}

if ($padron !== '') {
    $stmt_ex = mysqli_prepare($conexion, "SELECT propiedad_id FROM propiedades WHERE padron = ? LIMIT 1");
    if ($stmt_ex) {
        mysqli_stmt_bind_param($stmt_ex, 's', $padron);
        mysqli_stmt_execute($stmt_ex);
        $existe = mysqli_stmt_get_result($stmt_ex);
        if ($existe && mysqli_num_rows($existe) > 0) {
            mysqli_stmt_close($stmt_ex);
            header('Location: nueva_propiedad.php?error=padron_duplicado');
            exit;
        }
        mysqli_stmt_close($stmt_ex);
    }
}

$ciudad_param = ($ciudad === '') ? null : $ciudad;
$consorcio_param = $consorcio;
$porcentaje_param = ($porcentaje !== null) ? $porcentaje : null;
$padron_param = $padron;
$detalle_param = $detalle;
$mapa_enlace_param = $mapa_enlace;
$fotos_null = null;

$sql = "INSERT INTO propiedades (propietario_id, propiedad, ciudad, consorcio, porcentaje, padron, detalle, mapa_lat, mapa_lng, mapa_enlace, fotos_json) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
$stmt_ins = mysqli_prepare($conexion, $sql);
if ($stmt_ins) {
    mysqli_stmt_bind_param(
        $stmt_ins,
        'isssdssddss',
        $propietario_id,
        $propiedad,
        $ciudad_param,
        $consorcio_param,
        $porcentaje_param,
        $padron_param,
        $detalle_param,
        $mapa_lat,
        $mapa_lng,
        $mapa_enlace_param,
        $fotos_null
    );
}

if ($stmt_ins && mysqli_stmt_execute($stmt_ins)) {
    $nuevo_id = (int)mysqli_insert_id($conexion);
    mysqli_stmt_close($stmt_ins);
    $nuevas = propiedades_procesar_subida_fotos($nuevo_id, 'fotos');
    if (count($nuevas) > 0) {
        propiedades_guardar_json_fotos($conexion, $nuevo_id, $nuevas);
    }
    header('Location: propiedades.php?ok=1');
    exit;
}
echo "Falta dato o corregir.";
exit;
