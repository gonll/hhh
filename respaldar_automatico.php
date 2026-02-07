<?php
/**
 * Función para hacer respaldo automático diario de la base de datos
 * Se ejecuta silenciosamente y guarda el respaldo en el servidor
 */

function hacerRespaldoAutomatico() {
    $servidor = "localhost";
    $usuario  = "root";
    $clave    = "";
    $base     = "sistemahhh26";
    
    // Directorio donde guardar los respaldos automáticos
    $directorio_respaldo = __DIR__ . DIRECTORY_SEPARATOR . 'respaldo_automatico';
    
    // Crear directorio si no existe
    if (!is_dir($directorio_respaldo)) {
        @mkdir($directorio_respaldo, 0755, true);
    }
    
    // Archivo que guarda la fecha del último respaldo
    $archivo_ultimo_respaldo = $directorio_respaldo . DIRECTORY_SEPARATOR . 'ultimo_respaldo.txt';
    
    // Verificar si ya se hizo respaldo hoy
    $fecha_hoy = date('Y-m-d');
    if (file_exists($archivo_ultimo_respaldo)) {
        $ultima_fecha = trim(file_get_contents($archivo_ultimo_respaldo));
        if ($ultima_fecha === $fecha_hoy) {
            // Ya se hizo respaldo hoy, no hacer nada
            return null;
        }
    }
    
    // Ruta de mysqldump (XAMPP en Windows)
    $mysqldump = "C:\\xampp\\mysql\\bin\\mysqldump.exe";
    if (!file_exists($mysqldump)) {
        $mysqldump = "mysqldump";
    }
    
    // Nombre del archivo de respaldo
    $fecha = date('Y-m-d_H-i-s');
    $nombre_archivo = "respaldo_auto_{$fecha}.sql";
    $ruta_completa = $directorio_respaldo . DIRECTORY_SEPARATOR . $nombre_archivo;
    
    // Comando para respaldar
    $comando = "\"$mysqldump\" --host=$servidor --user=$usuario " . 
               (!empty($clave) ? "--password=$clave " : "") . 
               "--single-transaction --routines --triggers $base > \"$ruta_completa\"";
    
    // Ejecutar respaldo (silenciosamente)
    exec($comando . " 2>&1", $salida, $codigo_error);
    
    if ($codigo_error === 0 && file_exists($ruta_completa) && filesize($ruta_completa) > 0) {
        // Respaldar exitoso - guardar fecha del último respaldo
        file_put_contents($archivo_ultimo_respaldo, $fecha_hoy);
        
        // Limpiar respaldos antiguos (mantener solo los últimos 30 días)
        $archivos = glob($directorio_respaldo . DIRECTORY_SEPARATOR . 'respaldo_auto_*.sql');
        $limite_tiempo = time() - (30 * 24 * 60 * 60); // 30 días en segundos
        
        foreach ($archivos as $archivo) {
            if (filemtime($archivo) < $limite_tiempo) {
                @unlink($archivo);
            }
        }
        
        // Retornar el nombre del archivo para descarga automática
        return $nombre_archivo;
    }
    
    // Error en el respaldo (no mostrar error al usuario, solo registrar si es posible)
    return false;
}
