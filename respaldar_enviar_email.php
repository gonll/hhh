<?php
/**
 * Genera respaldo de BD y lo envía por correo a hectorhugoherrera@gmail.com y hyllback@gmail.com.
 * Usado al ingreso (nivel 1-3), al salir, y puede reutilizarse en otros flujos.
 * @param mysqli $conexion Conexión a la base de datos
 * @param string $contexto 'ingreso' o 'salida' (opcional, para el asunto del correo)
 * @return bool true si se generó y envió correctamente
 */
function respaldarYEnviarPorEmail($conexion, $contexto = 'ingreso') {
    if (!$conexion) return false;

    $out = "-- MySQL dump generado por PHP " . date('Y-m-d H:i:s') . "\n\n";
    $out .= "SET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS=0;\n\n";
    $res = mysqli_query($conexion, "SHOW TABLES");
    if (!$res) return false;
    while ($row = mysqli_fetch_array($res)) {
        $tabla = $row[0];
        $out .= "DROP TABLE IF EXISTS `" . mysqli_real_escape_string($conexion, $tabla) . "`;\n";
        $res2 = mysqli_query($conexion, "SHOW CREATE TABLE `" . mysqli_real_escape_string($conexion, $tabla) . "`");
        if ($res2 && $r = mysqli_fetch_row($res2)) {
            $out .= $r[1] . ";\n\n";
        }
        $res3 = mysqli_query($conexion, "SELECT * FROM `" . mysqli_real_escape_string($conexion, $tabla) . "`");
        if ($res3 && mysqli_num_rows($res3) > 0) {
            $fields = mysqli_fetch_fields($res3);
            $cols = $fields ? array_map(function($f) { return "`" . $f->name . "`"; }, $fields) : [];
            mysqli_data_seek($res3, 0);
            while ($r = mysqli_fetch_row($res3)) {
                $vals = array_map(function($v) use ($conexion) {
                    return $v === null ? 'NULL' : "'" . mysqli_real_escape_string($conexion, $v) . "'";
                }, $r);
                $out .= "INSERT INTO `$tabla` (" . implode(',', $cols) . ") VALUES (" . implode(',', $vals) . ");\n";
            }
            $out .= "\n";
        }
    }
    $out .= "SET FOREIGN_KEY_CHECKS=1;\n";

    $fecha = date('Y-m-d_H-i-s');
    $nombre_archivo = "respaldo_sistemahhh26_{$fecha}.sql";
    $es_windows = (DIRECTORY_SEPARATOR === '\\');
    $ruta_completa = $es_windows
        ? (__DIR__ . DIRECTORY_SEPARATOR . $nombre_archivo)
        : (sys_get_temp_dir() . DIRECTORY_SEPARATOR . $nombre_archivo);

    if (file_put_contents($ruta_completa, $out) === false) return false;
    if (filesize($ruta_completa) <= 0) {
        @unlink($ruta_completa);
        return false;
    }

    require_once __DIR__ . '/smtp_enviar.php';
    $txt_ctx = ($contexto === 'salida') ? 'al salir' : 'al ingresar';
    $asunto_mail = 'Respaldo BD sistemahhh26 (' . $txt_ctx . ') - ' . date('d/m/Y H:i');
    $cuerpo_mail = '<p>Se adjunta el respaldo de la base de datos <strong>sistemahhh26</strong> generado ' . $txt_ctx . ' del sistema el ' . date('d/m/Y H:i') . '.</p>';
    @enviar_mail_smtp_con_adjunto('hectorhugoherrera@gmail.com', $asunto_mail, $cuerpo_mail, $ruta_completa, 'application/sql');
    @enviar_mail_smtp_con_adjunto('hyllback@gmail.com', $asunto_mail, $cuerpo_mail, $ruta_completa, 'application/sql');
    @unlink($ruta_completa);
    return true;
}
