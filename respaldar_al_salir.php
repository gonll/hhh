<?php
/**
 * Respaldo y envío por correo al hacer clic en Salir (niveles 1, 2 y 3).
 * Luego redirige a logout.
 */
include 'verificar_sesion.php';
include 'db.php';

$nivel = (int)($_SESSION['acceso_nivel'] ?? -1);
if ($nivel >= 1 && $nivel <= 3) {
    require_once __DIR__ . '/respaldar_enviar_email.php';
    @respaldarYEnviarPorEmail($conexion, 'salida');
}

header('Location: logout.php');
exit;
