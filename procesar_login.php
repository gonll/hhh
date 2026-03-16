<?php
session_start();
include 'db.php';
require_once __DIR__ . '/login_security.php';

// Bloqueo por intentos fallidos
if (login_esta_bloqueado()) {
    header('Location: login.php?error=1');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_POST['usuario']) || empty($_POST['clave'])) {
    header('Location: login.php?error=1');
    exit;
}

// Validar token CSRF
$csrf = trim($_POST['csrf'] ?? '');
if (!login_validar_csrf($csrf)) {
    login_registrar_fallo();
    header('Location: login.php?error=1');
    exit;
}

$usuario = trim($_POST['usuario']);
$clave   = $_POST['clave'];

// Límites de longitud
if (strlen($usuario) > 80 || strlen($clave) > 255) {
    login_registrar_fallo();
    header('Location: login.php?error=1');
    exit;
}

$stmt = mysqli_prepare($conexion, "SELECT id, clave, nivel_acceso FROM accesos WHERE usuario = ? LIMIT 1");
if (!$stmt) {
    login_registrar_fallo();
    header('Location: login.php?error=1');
    exit;
}
mysqli_stmt_bind_param($stmt, 's', $usuario);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);

if (!$res || mysqli_num_rows($res) === 0) {
    login_registrar_fallo();
    header('Location: login.php?error=1');
    exit;
}

$row = mysqli_fetch_assoc($res);
mysqli_stmt_close($stmt);
if (!password_verify($clave, $row['clave'])) {
    login_registrar_fallo();
    header('Location: login.php?error=1');
    exit;
}

// Login exitoso: limpiar intentos fallidos y regenerar sesión
login_limpiar_intentos();
session_regenerate_id(true);

$_SESSION['acceso_id']     = (int)$row['id'];
$_SESSION['acceso_usuario'] = $usuario;
$_SESSION['acceso_nivel']   = (int)$row['nivel_acceso'];

// Respaldo y envío por correo al ingresar (nivel 1 a 3)
$nivel = (int)$row['nivel_acceso'];
if ($nivel >= 1 && $nivel <= 3) {
    require_once __DIR__ . '/respaldar_enviar_email.php';
    @respaldarYEnviarPorEmail($conexion);
}

// Nivel 0 con usuario zafra → ir directo a Cosecha
if ($nivel === 0 && stripos($usuario, 'zafra') !== false) {
    header('Location: cosecha.php');
    exit;
}

header('Location: index.php');
exit;
