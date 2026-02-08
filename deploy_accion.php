<?php
/**
 * Acciones de deploy (solo ENVIRONMENT=dev).
 * Recibe POST action: subir_codigo | subir_db
 */
include __DIR__ . '/db.php';

if (!defined('ENVIRONMENT') || ENVIRONMENT !== 'dev') {
    header('Location: index.php');
    exit;
}

$env = parse_ini_file(__DIR__ . '/.env');
$action = trim($_POST['action'] ?? '');

if ($action === 'subir_codigo') {
    $dir = __DIR__;
    $cmd = 'cd ' . escapeshellarg($dir) . ' && git add . && git commit -m ' . escapeshellarg('auto commit') . ' --allow-empty && git pull && git push 2>&1';
    $output = [];
    exec($cmd, $output, $code);
    $msg = $code === 0 ? 'ok' : 'error';
    $extra = $code !== 0 ? '&msg=' . urlencode(implode("\n", $output)) : '';
    header('Location: index.php?deploy=' . $msg . $extra);
    exit;
}

if ($action === 'subir_db') {
    $url = trim($env['DEPLOY_URL'] ?? '');
    $uuid = trim($env['DEPLOY_UUID'] ?? '');
    if ($url === '' || $uuid === '') {
        header('Location: index.php?deploy=error&msg=' . urlencode('Faltan DEPLOY_URL o DEPLOY_UUID en .env'));
        exit;
    }
    $host = $env['DB_HOST'] ?? 'localhost';
    $user = $env['DB_USER'] ?? '';
    $pass = $env['DB_PASS'] ?? '';
    $base = $env['DB_NAME'] ?? '';
    $tmp = sys_get_temp_dir() . '/deploy_dump_' . date('Ymd_His') . '.sql';
    $escPass = str_replace("'", "'\\''", $pass);
    $cmd = 'mysqldump -h ' . escapeshellarg($host) . ' -u ' . escapeshellarg($user) . " -p'" . $escPass . "' " . escapeshellarg($base) . ' > ' . escapeshellarg($tmp) . ' 2>&1';
    exec($cmd, $dumpOut, $dumpCode);
    if ($dumpCode !== 0 || !is_readable($tmp)) {
        $err = implode("\n", $dumpOut);
        @unlink($tmp);
        header('Location: index.php?deploy=error&msg=' . urlencode('Dump falló: ' . $err));
        exit;
    }
    $dumpContent = file_get_contents($tmp);
    @unlink($tmp);
    $ctx = stream_context_create([
        'http' => [
            'method' => 'POST',
            'header' => [
                'Content-Type: application/octet-stream',
                'X-Deploy-UUID: ' . $uuid,
            ],
            'content' => $dumpContent,
        ],
    ]);
    $resp = @file_get_contents($url, false, $ctx);
    $ok = ($resp !== false);
    header('Location: index.php?deploy=' . ($ok ? 'ok' : 'error') . ($ok ? '' : '&msg=' . urlencode('Error al enviar el dump al servidor')));
    exit;
}

header('Location: index.php');
exit;
