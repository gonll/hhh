<?php
/**
 * Acciones de deploy (solo ENVIRONMENT=dev).
 * POST action: subir_codigo | subir_db
 * Requiere sesión nivel ≥ 3.
 */
include __DIR__ . '/verificar_sesion.php';

if (!isset($_SESSION['acceso_nivel']) || (int)$_SESSION['acceso_nivel'] < 3) {
    header('HTTP/1.0 403 Forbidden');
    die('Sin permiso para deploy');
}

require_once __DIR__ . '/includes/respaldo_db_util.php';

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
                'timeout' => 120,
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
                if (!empty($json['output'])) {
                    $serverError .= ' ' . $json['output'];
                }
            } elseif ($resp !== false && $json === null && trim($resp) !== '') {
                $serverError = 'Servidor no devolvió JSON (¿error PHP?): ' . substr(trim($resp), 0, 200);
            }
        } else {
            $serverError = 'No se pudo conectar con el servidor (revisar URL, firewall, HTTPS).';
        }
        if (!$syncOk) {
            $msg = 'Push ok, pero falló pull en el servidor.';
            if ($serverError !== '') {
                $msg .= ' ' . $serverError;
            }
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
        header('Location: index.php?deploy=error&msg=' . urlencode('Faltan DEPLOY_URL o DEPLOY_UUID en .env (ej. DEPLOY_URL=https://sitio.com/sync/sync_db.php)'));
        exit;
    }

    $tmp = generarSqlDumpLocalToFile($conexion, $env);
    if ($tmp === null || !is_readable($tmp)) {
        header('Location: index.php?deploy=error&msg=' . urlencode('No se pudo generar el dump local (mysqldump o PHP). Revise credenciales en .env.'));
        exit;
    }

    $dumpContent = file_get_contents($tmp);
    @unlink($tmp);

    if ($dumpContent === false || strlen($dumpContent) < 50) {
        header('Location: index.php?deploy=error&msg=' . urlencode('El dump generado está vacío o es inválido.'));
        exit;
    }

    $ctx = stream_context_create([
        'http' => [
            'method' => 'POST',
            'header' => [
                'Content-Type: application/octet-stream',
                'X-Deploy-UUID: ' . $uuid,
            ],
            'content' => $dumpContent,
            'timeout' => 600,
            'ignore_errors' => true,
        ],
    ]);
    $resp = @file_get_contents($url, false, $ctx);
    $httpErr = '';
    if (isset($http_response_header) && is_array($http_response_header) && isset($http_response_header[0])) {
        $status = $http_response_header[0];
        if (!preg_match('/\s(200|201)\s/', $status)) {
            $httpErr = $status;
        }
    }

    $ok = false;
    $msgErr = 'Error al enviar o aplicar el dump en el servidor.';
    if ($resp !== false) {
        $json = @json_decode($resp, true);
        if (is_array($json) && isset($json['ok']) && $json['ok'] === true) {
            $ok = true;
        } elseif (is_array($json) && !empty($json['error'])) {
            $msgErr = $json['error'];
        } elseif ($resp !== false && trim($resp) !== '') {
            $msgErr = 'Respuesta no válida: ' . substr(trim(strip_tags($resp)), 0, 300);
        }
    } else {
        $msgErr = 'No se pudo conectar a DEPLOY_URL. Revise HTTPS, firewall y que sync/sync_db.php exista en el servidor.';
    }
    if (!$ok && $httpErr !== '') {
        $msgErr = $httpErr . ' — ' . $msgErr;
    }

    header('Location: index.php?deploy=' . ($ok ? 'ok' : 'error') . ($ok ? '' : '&msg=' . urlencode($msgErr)));
    exit;
}

header('Location: index.php');
exit;
