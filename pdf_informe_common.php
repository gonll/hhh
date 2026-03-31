<?php
/**
 * Bootstrap compartido para pdf_operaciones_operador.php y pdf_movimientos_operacion.php.
 * Evita 500 por vendor ausente, consultas SQL fallidas (PHP 8+ con mysqli_fetch_assoc(false)), etc.
 */
if (ob_get_level() === 0) {
    ob_start();
}
if (function_exists('ini_set')) {
    @ini_set('memory_limit', '128M');
    @ini_set('max_execution_time', '120');
}

$autoload = __DIR__ . '/vendor/autoload.php';
if (!is_readable($autoload)) {
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Error: falta la carpeta vendor en el servidor. Suba la carpeta completa o ejecute: composer install';
    exit;
}

require_once $autoload;

if (!class_exists('TCPDF')) {
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Error: TCPDF no está instalado correctamente (composer require tecnickcom/tcpdf).';
    exit;
}

/**
 * @param mysqli $conexion
 * @param mysqli_result|false $res
 * @param string $ctx Etiqueta para log
 * @return mysqli_result
 */
function pdf_informe_res_ok($conexion, $res, $ctx) {
    if ($res === false) {
        $err = mysqli_error($conexion);
        error_log('[PDF informe] ' . $ctx . ': ' . $err);
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        http_response_code(500);
        header('Content-Type: text/plain; charset=utf-8');
        echo 'Error al consultar datos. Revise la estructura de la base en el servidor y el archivo de log de PHP.';
        exit;
    }
    return $res;
}
