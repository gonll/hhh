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
    if ($code !== 0) {
        header('Location: index.php?deploy=error&msg=' . urlencode(implode("\n", $output)));
        exit;
    }
    
    $uuid = trim($env['DEPLOY_UUID'] ?? '');
    $urlGit = trim($env['DEPLOY_URL_GIT'] ?? '');
    if ($urlGit === '' && !empty($env['DEPLOY_URL'])) {
        $urlGit = str_replace('sync_db.php', 'sync_git.php', trim($env['DEPLOY_URL']));
    }
    if ($urlGit !== '' && $uuid !== '') {
        $ctx = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => 'Content-Type: application/x-www-form-urlencoded',
                'content' => 'uuid=' . rawurlencode($uuid),
            ],
        ]);
        $resp = @file_get_contents($urlGit, false, $ctx);
        $syncOk = ($resp !== false);
        $serverError = '';
        if ($resp !== false) {
            $json = @json_decode($resp, true);
            $syncOk = isset($json['ok']) && $json['ok'] === true;
            if (!$syncOk && is_array($json)) {
                $serverError = isset($json['error']) ? $json['error'] : '';
                if (!empty($json['output'])) $serverError .= ' ' . $json['output'];
            } elseif ($resp !== false && $json === null && trim($resp) !== '') {
                $serverError = 'Servidor no devolvió JSON (¿error PHP?): ' . substr(trim($resp), 0, 200);
            }
        } else {
            $serverError = 'No se pudo conectar con el servidor (revisar URL, firewall, HTTPS).';
        }
        if (!$syncOk) {
            $msg = 'Push ok, pero falló pull en el servidor.';
            if ($serverError !== '') $msg .= ' ' . $serverError;
            header('Location: index.php?deploy=error&msg=' . urlencode($msg));
            exit;
        }
    }
    header('Location: index.php?deploy=ok');
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
