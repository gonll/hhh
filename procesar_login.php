<?php
session_start();
include 'db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_POST['usuario']) || empty($_POST['clave'])) {
    header('Location: login.php?error=1');
    exit;
}

$usuario = trim($_POST['usuario']);
$clave   = $_POST['clave'];

$usuario_esc = mysqli_real_escape_string($conexion, $usuario);
$res = mysqli_query($conexion, "SELECT id, clave, nivel_acceso FROM accesos WHERE usuario = '$usuario_esc' LIMIT 1");

if (!$res || mysqli_num_rows($res) === 0) {
    header('Location: login.php?error=1');
    exit;
}

$row = mysqli_fetch_assoc($res);
if (!password_verify($clave, $row['clave'])) {
    header('Location: login.php?error=1');
    exit;
}

$_SESSION['acceso_id']    = (int)$row['id'];
$_SESSION['acceso_usuario'] = $usuario;
$_SESSION['acceso_nivel']   = (int)$row['nivel_acceso'];

// Respaldo y envío por correo al ingresar (nivel 1 a 3)
$nivel = (int)$row['nivel_acceso'];
if ($nivel >= 1 && $nivel <= 3) {
    require_once __DIR__ . '/respaldar_enviar_email.php';
    @respaldarYEnviarPorEmail($conexion);
}

header('Location: index.php');
exit;
