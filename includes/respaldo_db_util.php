<?php
/**
 * Utilidades compartidas: respaldo vía PHP (sin mysqldump) y normalización de dumps.
 */

if (!function_exists('normalizarCollationDump')) {
    /**
     * Reemplaza collations de MySQL 8 por compatibles con MariaDB/MySQL antiguo.
     */
    function normalizarCollationDump($ruta_archivo) {
        if (!is_readable($ruta_archivo)) {
            return false;
        }
        $contenido = file_get_contents($ruta_archivo);
        $reemplazos = [
            'utf8mb4_0900_ai_ci' => 'utf8mb4_general_ci',
            'utf8mb4_0900_as_cs' => 'utf8mb4_general_ci',
            'utf8mb3_0900_ai_ci' => 'utf8_general_ci',
            'utf8_0900_ai_ci' => 'utf8_general_ci',
        ];
        foreach ($reemplazos as $orig => $dest) {
            $contenido = str_replace($orig, $dest, $contenido);
        }
        return file_put_contents($ruta_archivo, $contenido) !== false;
    }
}

if (!function_exists('respaldoBDConexionGlobal')) {
    /**
     * Abre una conexión directa/global a la BD definida en .env (sin depender del contexto actual).
     * @return mysqli|null
     */
    function respaldoBDConexionGlobal() {
        $env = @parse_ini_file(__DIR__ . '/../.env') ?: [];
        $host = $env['DB_HOST'] ?? 'localhost';
        $user = $env['DB_USER'] ?? '';
        $pass = $env['DB_PASS'] ?? '';
        $name = $env['DB_NAME'] ?? '';
        if ($name === '' || $user === '') {
            return null;
        }
        $cx = @mysqli_connect($host, $user, $pass, $name);
        if (!$cx) {
            return null;
        }
        @mysqli_set_charset($cx, 'utf8mb4');
        return $cx;
    }
}

if (!function_exists('respaldoBDPorPHP')) {
    /**
     * Respaldo de BD usando solo PHP/mysqli (sin mysqldump).
     */
    function respaldoBDPorPHP($conn, $ruta_destino) {
        $out = "-- MySQL dump generado por PHP " . date('Y-m-d H:i:s') . "\n\n";
        $out .= "SET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS=0;\n\n";
        $res = mysqli_query($conn, "SHOW TABLES");
        if (!$res) {
            return null;
        }
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
                $cols = $fields ? array_map(function ($f) {
                    return "`" . $f->name . "`";
                }, $fields) : [];
                mysqli_data_seek($res3, 0);
                while ($r = mysqli_fetch_row($res3)) {
                    $vals = array_map(function ($v) use ($conn) {
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
}

if (!function_exists('generarSqlDumpLocalToFile')) {
    /**
     * Genera un .sql de la BD local (mysqldump o fallback PHP). Normaliza collation para MariaDB.
     * @param mysqli $conexion Conexión activa (fallback PHP).
     * @param array $env Credenciales desde .env: DB_HOST, DB_USER, DB_PASS, DB_NAME
     * @return string|null Ruta al archivo temporal o null si falla
     */
    function generarSqlDumpLocalToFile($conexion, array $env) {
        $servidor = $env['DB_HOST'] ?? 'localhost';
        $usuario = $env['DB_USER'] ?? '';
        $clave = $env['DB_PASS'] ?? '';
        $base = $env['DB_NAME'] ?? '';
        if ($base === '') {
            return null;
        }
        $ruta_completa = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'deploy_dump_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.sql';
        $es_windows = (DIRECTORY_SEPARATOR === '\\');
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
        if (!$mysqldump) {
            $mysqldump = 'mysqldump';
        }
        $opt_pass = '';
        $tmp_cnf = null;
        if ($clave !== '' && $clave !== null) {
            if (!$es_windows) {
                $tmp_cnf = tempnam(sys_get_temp_dir(), 'mycnf_');
                file_put_contents($tmp_cnf, "[client]\npassword=" . addcslashes($clave, "\\\"\n\r") . "\n");
                @chmod($tmp_cnf, 0600);
                $opt_pass = ' --defaults-extra-file=' . escapeshellarg($tmp_cnf);
            } else {
                $opt_pass = ' -p' . escapeshellarg($clave);
            }
        }
        $comando = escapeshellarg($mysqldump) . ' --host=' . escapeshellarg($servidor) .
            ' --user=' . escapeshellarg($usuario) . $opt_pass .
            ' --single-transaction --routines --triggers ' . escapeshellarg($base) .
            ' > ' . escapeshellarg($ruta_completa) . ' 2>&1';
        exec($comando, $salida, $codigo_error);
        if ($tmp_cnf !== null && file_exists($tmp_cnf)) {
            @unlink($tmp_cnf);
        }
        $dump_valido = ($codigo_error === 0 && file_exists($ruta_completa) && filesize($ruta_completa) > 0);
        if ($dump_valido) {
            $primeras = @file_get_contents($ruta_completa, false, null, 0, 200);
            if ($primeras && (stripos($primeras, 'mysqldump:') !== false || stripos($primeras, 'Access denied') !== false || stripos($primeras, 'ERROR') === 0)) {
                $dump_valido = false;
                @unlink($ruta_completa);
            }
        }
        if (!$dump_valido) {
            @unlink($ruta_completa);
            if (!respaldoBDPorPHP($conexion, $ruta_completa)) {
                @unlink($ruta_completa);
                return null;
            }
            $dump_valido = file_exists($ruta_completa) && filesize($ruta_completa) > 0;
        }
        if (!$dump_valido) {
            @unlink($ruta_completa);
            return null;
        }
        normalizarCollationDump($ruta_completa);
        return $ruta_completa;
    }
}
