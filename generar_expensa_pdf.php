<?php
/**
 * Genera un PDF de una expensa usando wkhtmltopdf o alternativa
 */
function generarExpensaPDF($expensa, $movimientos, $total_ingresos, $total_egresos_ordinarias, $total_egresos_extraordinarias, $total_expensas, $ultimo_mes_liq, $fecha_actual, $nombre_consorcio) {
    include_once __DIR__ . '/generar_expensa_html.php';
    $html = generarExpensaHTML($expensa, $movimientos, $total_ingresos, $total_egresos_ordinarias, $total_egresos_extraordinarias, $total_expensas, $ultimo_mes_liq, $fecha_actual, $nombre_consorcio);
    
    // Directorio temporal para PDFs
    $dir_temp = __DIR__ . DIRECTORY_SEPARATOR . 'temp_pdfs';
    if (!is_dir($dir_temp)) {
        @mkdir($dir_temp, 0755, true);
    }
    
    $nombre_pdf = 'expensa_' . preg_replace('/[^a-zA-Z0-9]/', '_', $expensa['propiedad']) . '_' . date('Ymd_His') . '.pdf';
    $ruta_pdf = $dir_temp . DIRECTORY_SEPARATOR . $nombre_pdf;
    
    // Intentar usar wkhtmltopdf si está disponible
    // Primero probar ruta común en Windows/XAMPP
    $rutas_wkhtmltopdf = [
        'C:\\Program Files\\wkhtmltopdf\\bin\\wkhtmltopdf.exe',
        'C:\\wkhtmltopdf\\bin\\wkhtmltopdf.exe',
        'wkhtmltopdf',
        '/usr/local/bin/wkhtmltopdf',
        '/usr/bin/wkhtmltopdf'
    ];
    
    $wkhtmltopdf = null;
    foreach ($rutas_wkhtmltopdf as $ruta) {
        if (file_exists($ruta) || $ruta === 'wkhtmltopdf') {
            $wkhtmltopdf = $ruta;
            break;
        }
    }
    
    if ($wkhtmltopdf) {
        $comando = "\"$wkhtmltopdf\" --quiet --page-size A4 --margin-top 10mm --margin-bottom 10mm --margin-left 10mm --margin-right 10mm - \"$ruta_pdf\" 2>&1";
        
        $descriptores = [
            0 => ['pipe', 'r'], // stdin
            1 => ['pipe', 'w'], // stdout
            2 => ['pipe', 'w']  // stderr
        ];
        
        $proceso = @proc_open($comando, $descriptores, $pipes);
        
        if (is_resource($proceso)) {
            fwrite($pipes[0], $html);
            fclose($pipes[0]);
            $salida = stream_get_contents($pipes[1]);
            $errores = stream_get_contents($pipes[2]);
            fclose($pipes[1]);
            fclose($pipes[2]);
            proc_close($proceso);
            
            if (file_exists($ruta_pdf) && filesize($ruta_pdf) > 0) {
                return $ruta_pdf;
            }
        }
    }
    
    // Si wkhtmltopdf no está disponible, retornar null (solo se enviará HTML)
    return null;
}
