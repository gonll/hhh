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

// Respaldo y envío por correo al ingresar (nivel 1 a 3), con guarda de frecuencia
$nivel = (int)$row['nivel_acceso'];
if ($nivel >= 1 && $nivel <= 3) {
    // Solo generar respaldo si pasó suficiente tiempo desde el último
    $minutos_entre_respaldos = 240; // 4 horas
    $archivo_marca = __DIR__ . DIRECTORY_SEPARATOR . 'ultimo_respaldo_login.txt';
    $ahora = time();
    $debe_respaldar = true;
    if (is_file($archivo_marca)) {
        $ts_ultimo = (int)@file_get_contents($archivo_marca);
        if ($ts_ultimo > 0 && ($ahora - $ts_ultimo) < ($minutos_entre_respaldos * 60)) {
            $debe_respaldar = false;
        }
    }
    if ($debe_respaldar) {
        require_once __DIR__ . '/respaldar_enviar_email.php';
        if (@respaldarYEnviarPorEmail($conexion)) {
            @file_put_contents($archivo_marca, (string)$ahora);
        }
    }
}

// Nivel 0 con usuario zafra → ir directo a Cosecha
if ($nivel === 0 && stripos($usuario, 'zafra') !== false) {
    header('Location: cosecha.php');
    exit;
}

require_once __DIR__ . '/helpers_movil.php';
if (empty($_SESSION['vista_escritorio_movil']) && hh_es_user_agent_movil()) {
    if (hh_movil_ir_escritorio_desde_acceso($usuario)) {
        $_SESSION['vista_escritorio_movil'] = 1;
        header('Location: index.php');
        exit;
    }
    header('Location: ctacel.php');
    exit;
}

header('Location: index.php');
exit;
