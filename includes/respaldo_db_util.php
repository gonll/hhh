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
