<?php
session_start();
if (empty($_SESSION['acceso_id']) || !isset($_SESSION['acceso_nivel'])) {
    header('Location: login.php');
    exit;
}
