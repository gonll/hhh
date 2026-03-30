<?php
/**
 * Restauración completa de la base desde un archivo .sql (subida).
 * Solo nivel 3. Genera respaldo previo automático. Usa cliente mysql si existe; si no, importación vía PHP (tamaños moderados).
 */
include 'verificar_sesion.php';
include 'db.php';
require_once __DIR__ . '/includes/respaldo_db_util.php';

if ((int)($_SESSION['acceso_nivel'] ?? 0) < 3) {
    header('HTTP/1.0 403 Forbidden');
    die('Sin permiso');
}

$env = @parse_ini_file(__DIR__ . '/.env') ?: [];
$servidor = $env['DB_HOST'] ?? 'localhost';
$usuario = $env['DB_USER'] ?? 'root';
$clave = $env['DB_PASS'] ?? '';
$base = $env['DB_NAME'] ?? 'sistemahhh26';
$mysqlOverride = isset($env['MYSQL_RESTORE_PATH']) ? trim($env['MYSQL_RESTORE_PATH']) : '';

$dirRespaldoAuto = __DIR__ . DIRECTORY_SEPARATOR . 'respaldo_automatico';
if (!is_dir($dirRespaldoAuto)) {
    @mkdir($dirRespaldoAuto, 0755, true);
}

$maxBytesPhpFallback = 12 * 1024 * 1024; // 12 MiB: límite prudente para mysqli_multi_query

function encontrarBinarioMysql($override) {
    if ($override !== '' && file_exists($override)) {
        return $override;
    }
    $esWin = (DIRECTORY_SEPARATOR === '\\');
    $rutas = $esWin
        ? [
            'C:\\xampp\\mysql\\bin\\mysql.exe',
            'C:\\wamp64\\bin\\mysql\\mysql8.0.31\\bin\\mysql.exe',
        ]
        : [
            '/usr/bin/mysql',
            '/usr/local/mysql/bin/mysql',
            '/usr/local/bin/mysql',
        ];
    foreach ($rutas as $r) {
        if (file_exists($r)) {
            return $r;
        }
    }
    return 'mysql';
}

function crearCnfClienteMysql($host, $user, $pass) {
    $tmp = tempnam(sys_get_temp_dir(), 'mycnf_restore_');
    $h = $host;
    if ($h === 'localhost') {
        $h = '127.0.0.1';
    }
    $passEsc = addcslashes($pass, "\\\"\n\r");
    $cont = "[client]\nhost=" . $h . "\nuser=" . $user . "\npassword=" . $passEsc . "\n";
    file_put_contents($tmp, $cont);
    @chmod($tmp, 0600);
    return $tmp;
}

function restaurarConMysqlCli($mysqlBin, $cnfPath, $nombreBd, $sqlPath) {
    $desc = [
        0 => ['file', $sqlPath, 'rb'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ];
    $cmd = escapeshellarg($mysqlBin) . ' --defaults-extra-file=' . escapeshellarg($cnfPath) . ' ' . escapeshellarg($nombreBd);
    $procOpts = (PHP_OS_FAMILY === 'Windows') ? [] : ['bypass_shell' => true];
    $proc = @proc_open($cmd, $desc, $pipes, null, null, $procOpts);
    if (!is_resource($proc)) {
        return [false, 'No se pudo iniciar el proceso mysql.'];
    }
    fclose($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[2]);
    $code = proc_close($proc);
    if ($code !== 0) {
        return [false, trim($stderr) !== '' ? $stderr : ('Código de salida: ' . $code)];
    }
    return [true, ''];
}

function restaurarConMysqli($conn, $sqlPath) {
    $sql = file_get_contents($sqlPath);
    if ($sql === false) {
        return [false, 'No se pudo leer el archivo SQL.'];
    }
    mysqli_query($conn, 'SET FOREIGN_KEY_CHECKS=0');
    if (!mysqli_multi_query($conn, $sql)) {
        return [false, mysqli_error($conn)];
    }
    do {
        if ($r = mysqli_store_result($conn)) {
            mysqli_free_result($r);
        }
    } while (mysqli_more_results($conn) && mysqli_next_result($conn));
    if (mysqli_errno($conn)) {
        return [false, mysqli_error($conn)];
    }
    mysqli_query($conn, 'SET FOREIGN_KEY_CHECKS=1');
    return [true, ''];
}

$mensaje = '';
$error = '';
$ok = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf'] ?? '';
    if (empty($_SESSION['restaurar_csrf']) || !hash_equals($_SESSION['restaurar_csrf'], $token)) {
        $error = 'Sesión de seguridad inválida. Recargue la página e intente de nuevo.';
    } elseif (empty($_POST['confirmo_riesgo'])) {
        $error = 'Debe marcar que entiende el riesgo.';
    } elseif (trim($_POST['confirmar_frase'] ?? '') !== 'RESTAURAR') {
        $error = 'Debe escribir exactamente: RESTAURAR';
    } elseif (trim($_POST['confirmar_bd'] ?? '') !== $base) {
        $error = 'El nombre de la base de datos no coincide con el configurado.';
    } elseif (empty($_FILES['archivo_sql']['tmp_name']) || $_FILES['archivo_sql']['error'] !== UPLOAD_ERR_OK) {
        $error = 'No se recibió el archivo o hubo error de subida (tamaño máximo según PHP: upload_max_filesize / post_max_size).';
    } else {
        $tmp = $_FILES['archivo_sql']['tmp_name'];
        $nombreOrig = $_FILES['archivo_sql']['name'] ?? '';
        $ext = strtolower(pathinfo($nombreOrig, PATHINFO_EXTENSION));
        if ($ext !== 'sql') {
            $error = 'Solo se permiten archivos .sql';
        } elseif (!is_uploaded_file($tmp)) {
            $error = 'Archivo de subida inválido.';
        } else {
            $destSql = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'restore_' . bin2hex(random_bytes(8)) . '.sql';
            if (!move_uploaded_file($tmp, $destSql)) {
                $error = 'No se pudo guardar el archivo temporal.';
            } else {
                @chmod($destSql, 0600);
                normalizarCollationDump($destSql);

                $fechaPre = date('Y-m-d_H-i-s');
                $rutaPre = $dirRespaldoAuto . DIRECTORY_SEPARATOR . 'restaurar_pre_' . $fechaPre . '.sql';
                @set_time_limit(0);
                if (!respaldoBDPorPHP($conexion, $rutaPre) || !is_file($rutaPre) || filesize($rutaPre) < 10) {
                    @unlink($destSql);
                    $error = 'No se pudo generar el respaldo de seguridad previo. No se aplicó ningún cambio.';
                } else {
                    mysqli_close($conexion);
                    $mysqlBin = encontrarBinarioMysql($mysqlOverride);
                    $cnf = crearCnfClienteMysql($servidor, $usuario, $clave);
                    [$cliOk, $cliErr] = restaurarConMysqlCli($mysqlBin, $cnf, $base, $destSql);
                    @unlink($cnf);

                    if ($cliOk) {
                        @unlink($destSql);
                        $ok = true;
                        $mensaje = 'Restauración completada. Se guardó respaldo previo en: respaldo_automatico/restaurar_pre_' . $fechaPre . '.sql';
                    } else {
                        $tam = filesize($destSql);
                        if ($tam > 0 && $tam <= $maxBytesPhpFallback) {
                            include 'db.php';
                            [$phpOk, $phpErr] = restaurarConMysqli($conexion, $destSql);
                            @unlink($destSql);
                            if ($phpOk) {
                                $ok = true;
                                $mensaje = 'Restauración completada (importación por PHP). Respaldo previo: respaldo_automatico/restaurar_pre_' . $fechaPre . '.sql';
                            } else {
                                $error = 'Falló el cliente mysql: ' . htmlspecialchars($cliErr) . '. Falló el import por PHP: ' . htmlspecialchars($phpErr) . ' — Revise el respaldo previo y restaure manualmente si la base quedó inconsistente.';
                            }
                        } else {
                            @unlink($destSql);
                            $error = 'Falló la restauración con mysql: ' . htmlspecialchars($cliErr);
                            if ($tam > $maxBytesPhpFallback) {
                                $error .= ' El archivo es demasiado grande para importar por PHP. Instale/configure el cliente mysql (variable MYSQL_RESTORE_PATH en .env) o aumente max_allowed_packet.';
                            }
                        }
                    }
                }
            }
        }
    }
}

$_SESSION['restaurar_csrf'] = bin2hex(random_bytes(16));
$csrf = $_SESSION['restaurar_csrf'];

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Restaurar base de datos</title>
    <style>
        body { font-family: Segoe UI, Arial, sans-serif; background: #f0f2f5; margin: 0; padding: 20px; color: #333; }
        .caja { max-width: 560px; margin: 0 auto; background: #fff; padding: 24px; border-radius: 8px; box-shadow: 0 1px 6px rgba(0,0,0,.08); }
        h1 { font-size: 1.1rem; margin: 0 0 12px; color: #721c24; }
        .aviso { background: #f8d7da; color: #721c24; padding: 12px; border-radius: 6px; font-size: 13px; line-height: 1.45; margin-bottom: 16px; border: 1px solid #f5c6cb; }
        label { display: block; font-size: 12px; font-weight: 600; margin: 12px 0 4px; }
        input[type="text"], input[type="file"] { width: 100%; box-sizing: border-box; padding: 8px; font-size: 13px; }
        .chk { margin: 12px 0; font-size: 12px; }
        button { background: #721c24; color: #fff; border: none; padding: 10px 18px; border-radius: 6px; font-weight: bold; cursor: pointer; margin-top: 16px; }
        button:hover { background: #5a151c; }
        .ok { background: #d4edda; color: #155724; padding: 12px; border-radius: 6px; margin-bottom: 12px; border: 1px solid #c3e6cb; font-size: 13px; }
        .err { background: #f8d7da; color: #721c24; padding: 12px; border-radius: 6px; margin-bottom: 12px; border: 1px solid #f5c6cb; font-size: 12px; word-break: break-word; }
        .meta { font-size: 11px; color: #666; margin-top: 16px; }
        a { color: #007bff; }
        code { background: #eee; padding: 2px 6px; border-radius: 3px; font-size: 11px; }
    </style>
</head>
<body>
    <div class="caja">
        <h1>Restaurar base de datos (reemplazo completo)</h1>
        <div class="aviso">
            <strong>Peligro:</strong> el contenido del archivo .sql reemplaza las tablas/datos de la base configurada en <code>.env</code>.
            Antes se genera un respaldo automático en <code>respaldo_automatico/restaurar_pre_*.sql</code>.
            Use solo archivos de confianza generados por este sistema o mysqldump compatible.
        </div>

        <?php if ($ok): ?>
            <div class="ok"><?= htmlspecialchars($mensaje) ?></div>
            <p><a href="index.php">← Volver al panel</a></p>
        <?php else: ?>
            <?php if ($error): ?>
                <div class="err"><?= $error ?></div>
            <?php endif; ?>

            <form method="post" enctype="multipart/form-data" autocomplete="off">
                <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">

                <label>Archivo .sql</label>
                <input type="file" name="archivo_sql" accept=".sql,text/plain" required>

                <label>Escriba el nombre exacto de la base de datos (confirmación)</label>
                <input type="text" name="confirmar_bd" placeholder="<?= htmlspecialchars($base) ?>" required>
                <p class="meta">Base configurada: <strong><?= htmlspecialchars($base) ?></strong></p>

                <label>Escriba la frase exacta: RESTAURAR</label>
                <input type="text" name="confirmar_frase" placeholder="RESTAURAR" required autocapitalize="off" autocomplete="off">

                <div class="chk">
                    <label><input type="checkbox" name="confirmo_riesgo" value="1" required> Entiendo que se sobrescribirá la base actual y que debo usar un respaldo válido.</label>
                </div>

                <button type="submit">Restaurar base</button>
            </form>

            <p class="meta">
                Límites PHP actuales: <code>upload_max_filesize</code> <?= htmlspecialchars(ini_get('upload_max_filesize')) ?>,
                <code>post_max_size</code> <?= htmlspecialchars(ini_get('post_max_size')) ?>.
                En servidor Linux suele hacer falta el cliente <code>mysql</code> en PATH o definir <code>MYSQL_RESTORE_PATH</code> en <code>.env</code>.
                Archivos &gt; ~12&nbsp;MiB no se importan por PHP si falla el cliente mysql.
            </p>
            <p><a href="index.php">← Volver al panel</a></p>
        <?php endif; ?>
    </div>
</body>
</html>
