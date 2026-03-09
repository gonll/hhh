<?php
session_start();
if (empty($_SESSION['acceso_id']) || !isset($_SESSION['acceso_nivel'])) {
    header('Location: login.php');
    exit;
}
// Nivel 0: usuario zafra (nombre contiene "zafra") → solo Cosecha; resto → solo Partes desde cel
if ((int)($_SESSION['acceso_nivel'] ?? -1) === 0) {
    $script = basename($_SERVER['SCRIPT_NAME'] ?? '');
    $usuario = (string)($_SESSION['acceso_usuario'] ?? '');
    $es_usuario_zafra = (stripos($usuario, 'zafra') !== false);
    if ($es_usuario_zafra) {
        $permitidos_nivel0 = array('cosecha.php', 'logout.php');
    } else {
        $permitidos_nivel0 = array('partes_desde_cel.php', 'logout.php');
    }
    if (!in_array($script, $permitidos_nivel0)) {
        header('Location: ' . ($es_usuario_zafra ? 'cosecha.php' : 'partes_desde_cel.php'));
        exit;
    }
}
