<?php
include 'db.php';
include 'verificar_sesion.php';
if (isset($_SESSION['acceso_nivel']) && $_SESSION['acceso_nivel'] < 2) {
    header('Location: propiedades.php?msg=sin_permiso');
    exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['propiedad'])) {
    header('Location: propiedades.php');
    exit;
}

$propietario_id = (int)($_POST['propietario_id'] ?? 0);
$propiedad      = mysqli_real_escape_string($conexion, trim($_POST['propiedad'] ?? ''));
$ciudad         = mysqli_real_escape_string($conexion, trim($_POST['ciudad'] ?? ''));
$consorcio      = mysqli_real_escape_string($conexion, trim($_POST['consorcio'] ?? ''));
$porcentaje_raw = trim($_POST['porcentaje'] ?? '');
$porcentaje_raw = str_replace(',', '.', $porcentaje_raw);
$porcentaje     = $porcentaje_raw !== '' ? (float)$porcentaje_raw : null;
$padron         = mysqli_real_escape_string($conexion, trim($_POST['padron'] ?? ''));
$detalle        = mysqli_real_escape_string($conexion, trim($_POST['detalle'] ?? ''));

if ($propiedad === '' || $propietario_id <= 0) {
    header('Location: nueva_propiedad.php?error=1');
    exit;
}

if ($padron !== '') {
    $existe = mysqli_query($conexion, "SELECT propiedad_id FROM propiedades WHERE padron = '$padron' LIMIT 1");
    if ($existe && mysqli_num_rows($existe) > 0) {
        header('Location: nueva_propiedad.php?error=padron_duplicado');
        exit;
    }
}

$porcentaje_sql = $porcentaje !== null ? $porcentaje : 'NULL';
$ciudad_sql     = $ciudad === '' ? 'NULL' : "'$ciudad'";

$sql = "INSERT INTO propiedades (propietario_id, propiedad, ciudad, consorcio, porcentaje, padron, detalle) 
        VALUES ($propietario_id, '$propiedad', $ciudad_sql, '$consorcio', $porcentaje_sql, '$padron', '$detalle')";

if (mysqli_query($conexion, $sql)) {
    header('Location: propiedades.php?ok=1');
    exit;
}
echo "Falta dato o corregir.";
exit;
