<?php
include 'verificar_sesion.php';
include 'db.php';

// Verificar nivel de acceso (solo nivel 3 puede respaldar)
if (!isset($_SESSION['acceso_nivel']) || $_SESSION['acceso_nivel'] < 3) {
    header('HTTP/1.0 403 Forbidden');
    die('Sin permiso para respaldar');
}

$servidor = "localhost";
$usuario  = "root";
$clave    = "";
$base     = "sistemahhh26";

// Ruta donde guardar el respaldo (directorio del proyecto)
$directorio_respaldo = __DIR__;
$fecha = date('Y-m-d_H-i-s');
$nombre_archivo = "respaldo_sistemahhh26_{$fecha}.sql";
$ruta_completa = $directorio_respaldo . DIRECTORY_SEPARATOR . $nombre_archivo;

// Ruta de mysqldump (XAMPP en Windows)
$mysqldump = "C:\\xampp\\mysql\\bin\\mysqldump.exe";

// Verificar si existe mysqldump
if (!file_exists($mysqldump)) {
    // Intentar ruta alternativa común
    $mysqldump = "mysqldump";
}

// Comando para respaldar
$comando = "\"$mysqldump\" --host=$servidor --user=$usuario " . 
           (!empty($clave) ? "--password=$clave " : "") . 
           "--single-transaction --routines --triggers $base > \"$ruta_completa\"";

// Ejecutar respaldo
exec($comando . " 2>&1", $salida, $codigo_error);

if ($codigo_error === 0 && file_exists($ruta_completa) && filesize($ruta_completa) > 0) {
    // Enviar respaldo por mail a hectorhugoherrera@gmail.com
    $mail_respaldo = 'hectorhugoherrera@gmail.com';
    require_once __DIR__ . '/smtp_enviar.php';
    $asunto_mail = 'Respaldo BD sistemahhh26 - ' . date('d/m/Y H:i');
    $cuerpo_mail = '<p>Se adjunta el respaldo de la base de datos <strong>sistemahhh26</strong> generado el ' . date('d/m/Y H:i') . '.</p>';
    @enviar_mail_smtp_con_adjunto($mail_respaldo, $asunto_mail, $cuerpo_mail, $ruta_completa, 'application/sql');

    // Respaldar exitoso - descargar el archivo
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="' . $nombre_archivo . '"');
    header('Content-Length: ' . filesize($ruta_completa));
    header('Cache-Control: must-revalidate');
    header('Pragma: public');
    
    readfile($ruta_completa);
    
    // Eliminar archivo temporal después de descargar
    @unlink($ruta_completa);
    exit;
} else {
    // Error en el respaldo
    $mensaje_error = implode("\n", $salida);
    ?>
    <!DOCTYPE html>
    <html lang="es">
    <head>
        <meta charset="UTF-8">
        <title>Error al respaldar</title>
        <style>
            body { font-family: Arial, sans-serif; padding: 20px; background: #f0f2f5; }
            .error { background: #f8d7da; color: #721c24; padding: 15px; border-radius: 4px; margin: 20px 0; }
            .info { background: #d1ecf1; color: #0c5460; padding: 15px; border-radius: 4px; margin: 20px 0; }
            a { color: #007bff; text-decoration: none; }
            a:hover { text-decoration: underline; }
        </style>
    </head>
    <body>
        <h2>Error al generar respaldo</h2>
        <div class="error">
            <strong>No se pudo generar el respaldo de la base de datos.</strong>
            <?php if (!empty($mensaje_error)): ?>
                <pre style="font-size: 11px; margin-top: 10px;"><?= htmlspecialchars($mensaje_error) ?></pre>
            <?php endif; ?>
        </div>
        <div class="info">
            <strong>Posibles causas:</strong>
            <ul>
                <li>mysqldump no está disponible en la ruta esperada (C:\xampp\mysql\bin\mysqldump.exe)</li>
                <li>No hay permisos para escribir en el directorio</li>
                <li>Error de conexión a la base de datos</li>
            </ul>
            <p><strong>Comando ejecutado:</strong></p>
            <pre style="font-size: 10px; background: #fff; padding: 8px; border-radius: 3px;"><?= htmlspecialchars($comando) ?></pre>
        </div>
        <p><a href="index.php">← Volver al panel</a></p>
    </body>
    </html>
    <?php
    exit;
}
?>
