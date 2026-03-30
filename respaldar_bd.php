<?php
include 'verificar_sesion.php';
include 'db.php';
require_once __DIR__ . '/includes/respaldo_db_util.php';

// Verificar nivel de acceso (solo nivel 3 puede respaldar)
if (!isset($_SESSION['acceso_nivel']) || $_SESSION['acceso_nivel'] < 3) {
    header('HTTP/1.0 403 Forbidden');
    die('Sin permiso para respaldar');
}

$es_salir = isset($_GET['salir']) && $_GET['salir'] == '1';

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
    // Normalizar collations para importar en MariaDB/MySQL antiguo (evitar utf8mb4_0900_ai_ci)
    normalizarCollationDump($ruta_completa);

    // Enviar respaldo por mail a hyllback@gmail.com
    require_once __DIR__ . '/smtp_enviar.php';
    $asunto_mail = 'Respaldo BD sistemahhh26 - ' . date('d/m/Y H:i');
    $cuerpo_mail = '<p>Se adjunta el respaldo de la base de datos <strong>sistemahhh26</strong> generado el ' . date('d/m/Y H:i') . '.</p>';
    @enviar_mail_smtp_con_adjunto('hyllback@gmail.com', $asunto_mail, $cuerpo_mail, $ruta_completa, 'application/sql');

    if ($es_salir) {
        // Llegó desde Salir: redirigir a logout (el respaldo ya se envió por correo)
        @unlink($ruta_completa);
        header('Location: logout.php');
        exit;
    }

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
