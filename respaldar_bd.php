<?php
include 'verificar_sesion.php';
include 'db.php';

/**
 * Respaldo de BD usando solo PHP/mysqli (sin mysqldump). Útil cuando mysqldump no está disponible.
 */
function respaldoBDPorPHP($conn, $ruta_destino) {
    $out = "-- MySQL dump generado por PHP " . date('Y-m-d H:i:s') . "\n\n";
    $out .= "SET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS=0;\n\n";
    $res = mysqli_query($conn, "SHOW TABLES");
    if (!$res) return null;
    while ($row = mysqli_fetch_array($res)) {
        $tabla = $row[0];
        $out .= "DROP TABLE IF EXISTS `" . mysqli_real_escape_string($conn, $tabla) . "`;\n";
        $res2 = mysqli_query($conn, "SHOW CREATE TABLE `" . mysqli_real_escape_string($conn, $tabla) . "`");
        if ($res2 && $r = mysqli_fetch_row($res2)) {
            $out .= $r[1] . ";\n\n";
        }
        $res3 = mysqli_query($conn, "SELECT * FROM `" . mysqli_real_escape_string($conn, $tabla) . "`");
        if ($res3 && mysqli_num_rows($res3) > 0) {
            $fields = mysqli_fetch_fields($res3);
            $cols = $fields ? array_map(function($f) { return "`" . $f->name . "`"; }, $fields) : [];
            mysqli_data_seek($res3, 0);
            while ($r = mysqli_fetch_row($res3)) {
                $vals = array_map(function($v) use ($conn) {
                    return $v === null ? 'NULL' : "'" . mysqli_real_escape_string($conn, $v) . "'";
                }, $r);
                $out .= "INSERT INTO `$tabla` (" . implode(',', $cols) . ") VALUES (" . implode(',', $vals) . ");\n";
            }
            $out .= "\n";
        }
    }
    $out .= "SET FOREIGN_KEY_CHECKS=1;\n";
    return file_put_contents($ruta_destino, $out) !== false ? $ruta_destino : null;
}

// Verificar nivel de acceso (solo nivel 3 puede respaldar)
if (!isset($_SESSION['acceso_nivel']) || $_SESSION['acceso_nivel'] < 3) {
    header('HTTP/1.0 403 Forbidden');
    die('Sin permiso para respaldar');
}

$env = parse_ini_file(__DIR__ . '/.env');
$servidor = $env['DB_HOST'] ?? 'localhost';
$usuario  = $env['DB_USER'] ?? 'root';
$clave    = $env['DB_PASS'] ?? '';
$base     = $env['DB_NAME'] ?? 'sistemahhh26';

// Ruta: en Linux usar /tmp (siempre escribible); en Windows usar directorio del proyecto
$fecha = date('Y-m-d_H-i-s');
$nombre_archivo = "respaldo_sistemahhh26_{$fecha}.sql";
$es_windows = (DIRECTORY_SEPARATOR === '\\');
$ruta_completa = $es_windows
    ? (__DIR__ . DIRECTORY_SEPARATOR . $nombre_archivo)
    : (sys_get_temp_dir() . DIRECTORY_SEPARATOR . $nombre_archivo);

// En Linux/servidor: usar PHP directamente (más fiable; mysqldump suele fallar por permisos)
$usar_php_directo = !$es_windows && isset($conexion);

// Buscar mysqldump: Windows XAMPP, Linux típico, o en PATH
$mysqldump = null;
$rutas_posibles = [
    'C:\\xampp\\mysql\\bin\\mysqldump.exe',
    '/usr/bin/mysqldump',
    '/usr/local/mysql/bin/mysqldump',
    'mysqldump',
];
foreach ($rutas_posibles as $r) {
    if ($r === 'mysqldump' || file_exists($r)) {
        $mysqldump = $r;
        break;
    }
}
if (!$mysqldump) $mysqldump = 'mysqldump';

// Contraseña para mysqldump (en Linux usar archivo temporal evita problemas con caracteres especiales)
$es_windows = (DIRECTORY_SEPARATOR === '\\');
$opt_pass = '';
if (!empty($clave)) {
    if (!$es_windows) {
        $tmp_cnf = tempnam(sys_get_temp_dir(), 'mycnf_');
        file_put_contents($tmp_cnf, "[client]\npassword=" . addcslashes($clave, "\\\"\n\r") . "\n");
        @chmod($tmp_cnf, 0600);
        $opt_pass = " --defaults-extra-file=" . escapeshellarg($tmp_cnf);
    } else {
        $opt_pass = " -p" . escapeshellarg($clave);
    }
}

// Comando para respaldar
$comando = escapeshellarg($mysqldump) . " --host=" . escapeshellarg($servidor) . " --user=" . escapeshellarg($usuario) .
           $opt_pass . " --single-transaction --routines --triggers " . escapeshellarg($base) . " > " . escapeshellarg($ruta_completa) . " 2>&1";

// Ejecutar respaldo
if ($usar_php_directo) {
    respaldoBDPorPHP($conexion, $ruta_completa);
} else {
    exec($comando, $salida, $codigo_error);
    if (isset($tmp_cnf) && file_exists($tmp_cnf)) @unlink($tmp_cnf);
    $dump_valido = ($codigo_error === 0 && file_exists($ruta_completa) && filesize($ruta_completa) > 0);
    if ($dump_valido) {
        $primeras = @file_get_contents($ruta_completa, false, null, 0, 200);
        if ($primeras && (stripos($primeras, 'mysqldump:') !== false || stripos($primeras, 'Access denied') !== false || stripos($primeras, 'ERROR') === 0)) {
            $dump_valido = false;
            @unlink($ruta_completa);
        }
    }
    if (!$dump_valido && isset($conexion)) {
        respaldoBDPorPHP($conexion, $ruta_completa);
    }
}

if (file_exists($ruta_completa) && filesize($ruta_completa) > 0) {
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
                <li>mysqldump no está instalado o no está en PATH (en Linux suele estar en /usr/bin/mysqldump)</li>
                <li>No hay permisos para escribir en el directorio del proyecto</li>
                <li>Error de conexión a la base de datos (verificar credenciales en .env)</li>
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
