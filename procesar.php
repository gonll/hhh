<?php
include 'db.php';
include 'verificar_sesion.php';
require_once __DIR__ . '/helpers_tenant_inmobiliaria.php';
tenant_inmob_asegurar_esquema($conexion);
if (isset($_SESSION['acceso_nivel']) && $_SESSION['acceso_nivel'] < 2) {
    header('Location: index.php?msg=sin_permiso');
    exit;
}
$apellido  = mysqli_real_escape_string($conexion, strtoupper(trim($_POST['apellido'])));
$dni       = mysqli_real_escape_string($conexion, $_POST['dni']);
$cuit      = mysqli_real_escape_string($conexion, $_POST['cuit']);
$domicilio = mysqli_real_escape_string($conexion, strtoupper($_POST['domicilio']));
$email     = mysqli_real_escape_string($conexion, strtolower(trim($_POST['email'] ?? '')));
$celular   = mysqli_real_escape_string($conexion, trim($_POST['celular'] ?? ''));
$consorcio = strtoupper(trim($_POST['consorcio'] ?? ''));
$consorcio = mysqli_real_escape_string($conexion, $consorcio);
$consorcio_sql = $consorcio === '' ? 'NULL' : "'$consorcio'";

$acc_ins = tenant_inmob_usuario_acceso_creador_insert_sql($conexion);
$sql = "INSERT INTO usuarios (acceso_creador_id, apellido, dni, cuit, domicilio, email, celular, consorcio) 
        VALUES ($acc_ins, '$apellido', '$dni', '$cuit', '$domicilio', '$email', '$celular', $consorcio_sql)";

if (mysqli_query($conexion, $sql)) {
    $volver = isset($_POST['volver']) ? trim($_POST['volver']) : '';
    if ($volver !== '' && preg_match('/^[a-zA-Z0-9_\.\-]+\.php(\?[^#]*)?$/', $volver)) {
        header("Location: $volver");
    } else {
        header("Location: index.php");
    }
    exit;
} else {
    echo "Error: " . mysqli_error($conexion);
}
?>
