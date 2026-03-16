<?php
/**
 * Script de correcciones para ejecutar manualmente en el servidor.
 * Las correcciones también se aplican automáticamente al hacer "Subir código" o "Subir base de datos".
 *
 * Uso: desde navegador /aplicar_correcciones_deploy.php o: php aplicar_correcciones_deploy.php
 */
include 'db.php';
require_once __DIR__ . '/sync/correcciones_post_deploy.php';

$res = ejecutar_correcciones_deploy($conexion);
$correcciones_aplicadas = $res['aplicadas'];
$errores = $res['errores'];

header('Content-Type: text/html; charset=utf-8');
echo "<h3>Correcciones de deploy</h3>";
if (!empty($correcciones_aplicadas)) {
    echo "<p><strong>Aplicadas:</strong></p><ul>";
    foreach ($correcciones_aplicadas as $c) echo "<li>$c</li>";
    echo "</ul>";
} else {
    echo "<p>No había correcciones pendientes (datos ya correctos).</p>";
}
if (!empty($errores)) {
    echo "<p><strong>Errores:</strong></p><ul>";
    foreach ($errores as $e) echo "<li>$e</li>";
    echo "</ul>";
}
?>
