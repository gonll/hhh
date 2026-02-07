<?php
session_start();
if (empty($_SESSION['acceso_id']) || !isset($_SESSION['acceso_nivel'])) {
    header('Location: login.php');
    exit;
}
// Nivel 0 = solo Partes desde cel
if ((int)($_SESSION['acceso_nivel'] ?? -1) === 0) {
    $script = basename($_SERVER['SCRIPT_NAME'] ?? '');
    $permitidos_nivel0 = array('partes_desde_cel.php', 'logout.php');
    if (!in_array($script, $permitidos_nivel0)) {
        header('Location: partes_desde_cel.php');
        exit;
    }
}
