<?php
/**
 * Barreras de protección para el login.
 * - Límite de intentos fallidos por IP (bloqueo temporal)
 * - Token CSRF
 */

define('LOGIN_MAX_INTENTOS', 5);
define('LOGIN_BLOQUEO_MINUTOS', 15);
define('LOGIN_ARCHIVO_INTENTOS', sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'hhh_login_' . md5(__DIR__) . '.json');

function login_get_ip() {
    $ip = '';
    if (!empty($_SERVER['HTTP_CF_CONNECTING_IP'])) {
        $ip = $_SERVER['HTTP_CF_CONNECTING_IP'];
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ip = trim(explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0]);
    } elseif (!empty($_SERVER['HTTP_X_REAL_IP'])) {
        $ip = $_SERVER['HTTP_X_REAL_IP'];
    } elseif (!empty($_SERVER['REMOTE_ADDR'])) {
        $ip = $_SERVER['REMOTE_ADDR'];
    }
    return preg_replace('/[^0-9a-f.:]/', '', $ip) ?: '0.0.0.0';
}

function login_esta_bloqueado() {
    $ip = login_get_ip();
    $archivo = LOGIN_ARCHIVO_INTENTOS;
    if (!file_exists($archivo)) return false;
    $datos = @json_decode(file_get_contents($archivo), true);
    if (!is_array($datos) || !isset($datos[$ip])) return false;
    $intentos = (int)($datos[$ip]['intentos'] ?? 0);
    $ultimo = (int)($datos[$ip]['ultimo'] ?? 0);
    if ($intentos < LOGIN_MAX_INTENTOS) return false;
    $minutos_transcurridos = (time() - $ultimo) / 60;
    return $minutos_transcurridos < LOGIN_BLOQUEO_MINUTOS;
}

function login_minutos_restantes() {
    $ip = login_get_ip();
    $archivo = LOGIN_ARCHIVO_INTENTOS;
    if (!file_exists($archivo)) return 0;
    $datos = @json_decode(file_get_contents($archivo), true);
    if (!is_array($datos) || !isset($datos[$ip])) return 0;
    $ultimo = (int)($datos[$ip]['ultimo'] ?? 0);
    $transcurridos = (time() - $ultimo) / 60;
    $restantes = LOGIN_BLOQUEO_MINUTOS - $transcurridos;
    return max(0, (int)ceil($restantes));
}

function login_registrar_fallo() {
    $ip = login_get_ip();
    $archivo = LOGIN_ARCHIVO_INTENTOS;
    $datos = [];
    if (file_exists($archivo)) {
        $datos = @json_decode(file_get_contents($archivo), true) ?: [];
    }
    if (!isset($datos[$ip])) {
        $datos[$ip] = ['intentos' => 0, 'ultimo' => 0];
    }
    $datos[$ip]['intentos'] = (int)($datos[$ip]['intentos']) + 1;
    $datos[$ip]['ultimo'] = time();
    @file_put_contents($archivo, json_encode($datos), LOCK_EX);
}

function login_limpiar_intentos() {
    $ip = login_get_ip();
    $archivo = LOGIN_ARCHIVO_INTENTOS;
    if (!file_exists($archivo)) return;
    $datos = @json_decode(file_get_contents($archivo), true);
    if (is_array($datos) && isset($datos[$ip])) {
        unset($datos[$ip]);
        @file_put_contents($archivo, json_encode($datos), LOCK_EX);
    }
}

function login_generar_csrf() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    $token = bin2hex(random_bytes(32));
    $_SESSION['login_csrf'] = $token;
    return $token;
}

function login_validar_csrf($token) {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    $esperado = $_SESSION['login_csrf'] ?? '';
    unset($_SESSION['login_csrf']);
    return $token !== '' && hash_equals($esperado, $token);
}
