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

$porcentaje_sql = $porcentaje !== null ? $porcentaje : 'NULL';
$ciudad_sql     = $ciudad === '' ? 'NULL' : "'$ciudad'";

$sql = "INSERT INTO propiedades (propietario_id, propiedad, ciudad, consorcio, porcentaje, padron, detalle) 
        VALUES (?, ?, ?, ?, ?, ?, ?)";
$stmt_ins = mysqli_prepare($conexion, $sql);
if ($stmt_ins) {
    // ciudad_sql y porcentaje_sql pueden ser NULL literales; armamos variables PHP coherentes
    $ciudad_param = ($ciudad === '') ? null : $ciudad;
    $porcentaje_param = ($porcentaje !== null) ? $porcentaje : null;
    mysqli_stmt_bind_param(
        $stmt_ins,
        'isssdds',
        $propietario_id,
        $propiedad,
        $ciudad_param,
        $consorcio,
        $porcentaje_param,
        $padron,
        $detalle
    );
}

if ($stmt_ins && mysqli_stmt_execute($stmt_ins)) {
    mysqli_stmt_close($stmt_ins);
    header('Location: propiedades.php?ok=1');
    exit;
}
echo "Falta dato o corregir.";
exit;
