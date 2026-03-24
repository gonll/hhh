<?php
/**
 * Limpia las ubicaciones guardadas de PDF liq prod en la tabla stock.
 * Ejecutar una vez para borrar las referencias; los archivos en uploads/pdf_liq_prod se mantienen.
 */
include 'db.php';
include 'verificar_sesion.php';

if (!isset($_SESSION['acceso_nivel']) || $_SESSION['acceso_nivel'] < 2) {
    die('Sin permiso.');
}

$res = mysqli_query($conexion, "UPDATE stock SET pdf_liq_prod = NULL WHERE pdf_liq_prod IS NOT NULL AND pdf_liq_prod != ''");
$afectados = mysqli_affected_rows($conexion);

header('Content-Type: text/html; charset=utf-8');
echo '<p>Se limpiaron <strong>' . (int)$afectados . '</strong> registros de pdf_liq_prod en la tabla stock.</p>';
echo '<p><a href="gestionar_azucares.php">Volver a Gestión Azúcares</a></p>';
