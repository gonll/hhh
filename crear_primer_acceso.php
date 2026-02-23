<?php
session_start();
include 'db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_POST['usuario']) || empty($_POST['clave'])) {
    header('Location: login.php?error=1');
    exit;
}

$sql_tabla = "CREATE TABLE IF NOT EXISTS accesos (
  id INT AUTO_INCREMENT PRIMARY KEY,
  usuario VARCHAR(80) NOT NULL UNIQUE,
  clave VARCHAR(255) NOT NULL,
  nivel_acceso TINYINT NOT NULL DEFAULT 2,
  creado_por_id INT NULL,
  fecha_creacion DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_usuario (usuario),
  INDEX idx_nivel (nivel_acceso)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

if (!mysqli_query($conexion, $sql_tabla)) {
    header('Location: login.php?error=1');
    exit;
}

$usuario = trim($_POST['usuario']);
$clave   = $_POST['clave'];
$nivel   = (int)($_POST['nivel_acceso'] ?? 3);
if ($nivel < 0 || $nivel > 3) $nivel = 3;

$hash = password_hash($clave, PASSWORD_DEFAULT);
$usuario_esc = mysqli_real_escape_string($conexion, $usuario);
$hash_esc = mysqli_real_escape_string($conexion, $hash);

$ins = "INSERT INTO accesos (usuario, clave, nivel_acceso) VALUES ('$usuario_esc', '$hash_esc', $nivel)";
if (!mysqli_query($conexion, $ins)) {
    header('Location: login.php?error=1');
    exit;
}

$id = (int)mysqli_insert_id($conexion);
$_SESSION['acceso_id']     = $id;
$_SESSION['acceso_usuario'] = $usuario;
$_SESSION['acceso_nivel']  = $nivel;

// Respaldo y envío por correo al ingresar (nivel 1 a 3)
if ($nivel >= 1 && $nivel <= 3) {
    require_once __DIR__ . '/respaldar_enviar_email.php';
    @respaldarYEnviarPorEmail($conexion);
}

header('Location: index.php');
exit;
