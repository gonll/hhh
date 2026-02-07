<?php
/**
 * Envía un correo vía Gmail SMTP (puerto 587, STARTTLS).
 * Requiere mail_config.php con $MAIL_SMTP_USER y $MAIL_SMTP_PASS.
 * Si falla, usar smtp_ultimo_error() para ver el motivo.
 * @param string $para   Dirección del destinatario
 * @param string $asunto Asunto del correo
 * @param string $cuerpo Cuerpo en texto plano (UTF-8)
 * @return bool true si se envió correctamente
 */
function smtp_ultimo_error() {
    return isset($GLOBALS['smtp_ultimo_error']) ? $GLOBALS['smtp_ultimo_error'] : '';
}

function enviar_mail_smtp($para, $asunto, $cuerpo) {
    $GLOBALS['smtp_ultimo_error'] = '';
    if (!file_exists(__DIR__ . '/mail_config.php')) {
        $GLOBALS['smtp_ultimo_error'] = 'No existe mail_config.php';
        return false;
    }
    include __DIR__ . '/mail_config.php';
    if (empty($MAIL_SMTP_USER) || empty($MAIL_SMTP_PASS)) {
        $GLOBALS['smtp_ultimo_error'] = 'Falta usuario o contraseña en mail_config.php';
        return false;
    }

    $host = 'smtp.gmail.com';
    $port = 587;
    $errno = 0;
    $errstr = '';
    $timeout = 30; // Aumentado de 15 a 30 segundos

    $sock = @stream_socket_client(
        "tcp://{$host}:{$port}",
        $errno,
        $errstr,
        $timeout,
        STREAM_CLIENT_CONNECT
    );
    if (!$sock) {
        $GLOBALS['smtp_ultimo_error'] = "No se pudo conectar a {$host}:{$port} (¿firewall o red bloquea el puerto 587?). Error: [{$errno}] {$errstr}";
        return false;
    }
    // Timeout en lecturas/escrituras aumentado a 30 segundos
    stream_set_timeout($sock, 30);

    $resp = function() use ($sock) {
        $line = @fgets($sock, 512);
        if ($line === false) {
            $meta = stream_get_meta_data($sock);
            if ($meta['timed_out']) {
                return 'TIMEOUT';
            }
            return '';
        }
        return trim($line);
    };
    
    // Función para leer respuesta multilínea (como EHLO)
    $resp_multiline = function() use ($sock, $resp) {
        $lineas = [];
        $primera = $resp();
        if ($primera === 'TIMEOUT' || $primera === '') {
            return $primera;
        }
        $lineas[] = $primera;
        // Leer líneas adicionales hasta encontrar una que termine con espacio (código de estado)
        while (true) {
            $linea = $resp();
            if ($linea === 'TIMEOUT' || $linea === '') {
                break;
            }
            $lineas[] = $linea;
            // Si la línea empieza con un código de 3 dígitos seguido de espacio, es la última
            if (preg_match('/^\d{3}\s/', $linea)) {
                break;
            }
        }
        return implode("\n", $lineas);
    };
    $envia = function($cmd) use ($sock) {
        fwrite($sock, $cmd . "\r\n");
    };

    $r = $resp(); // 220 greeting
    if ($r === 'TIMEOUT') { $GLOBALS['smtp_ultimo_error'] = 'Timeout esperando respuesta del servidor (¿red lenta o bloqueada?). Verificá tu conexión a internet y que el puerto 587 no esté bloqueado.'; fclose($sock); return false; }
    if (strpos($r, '220') !== 0) {
        $GLOBALS['smtp_ultimo_error'] = 'Respuesta inesperada del servidor SMTP: ' . $r;
        fclose($sock);
        return false;
    }
    
    $envia("EHLO localhost");
    // Leer respuesta EHLO (puede ser multilínea)
    $r = $resp();
    if ($r === 'TIMEOUT' || $r === '') { 
        $GLOBALS['smtp_ultimo_error'] = 'Timeout en EHLO. El servidor SMTP de Gmail no responde. Posibles causas: firewall bloqueando puerto 587, conexión a internet lenta o bloqueada, o Gmail temporalmente no disponible.'; 
        fclose($sock); 
        return false; 
    }
    
    // Leer líneas adicionales de EHLO hasta encontrar una que termine con espacio (código 250 seguido de espacio)
    // Gmail puede enviar múltiples líneas 250-xxx y termina con 250 espacio
    while (true) {
        $linea_siguiente = $resp();
        if ($linea_siguiente === 'TIMEOUT') {
            // Si ya tenemos al menos una línea válida, continuar (puede ser que la última línea no llegó)
            if (strpos($r, '250') === 0) {
                break; // Tenemos una respuesta válida, continuar
            }
            $GLOBALS['smtp_ultimo_error'] = 'Timeout leyendo respuesta EHLO completa'; 
            fclose($sock); 
            return false;
        }
        if ($linea_siguiente === '') {
            break; // Fin de respuesta
        }
        $r = $linea_siguiente; // Actualizar última línea leída
        // Si la línea empieza con "250 " (con espacio), es la última línea de EHLO
        if (strpos($linea_siguiente, '250 ') === 0) {
            break;
        }
    }

    $envia("STARTTLS");
    $r = $resp();
    if ($r === 'TIMEOUT' || strpos($r, '220') !== 0) {
        $GLOBALS['smtp_ultimo_error'] = 'STARTTLS falló. Respuesta: ' . $r;
        fclose($sock);
        return false;
    }
    if (!stream_socket_enable_crypto($sock, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
        $GLOBALS['smtp_ultimo_error'] = 'No se pudo activar TLS/SSL (revisar que PHP tenga OpenSSL habilitado)';
        fclose($sock);
        return false;
    }

    $envia("EHLO localhost");
    $r = $resp();
    if ($r === 'TIMEOUT' || $r === '') {
        $GLOBALS['smtp_ultimo_error'] = 'Timeout en EHLO (TLS)';
        fclose($sock);
        return false;
    }
    // Leer líneas adicionales de EHLO después de TLS
    while (true) {
        $linea_siguiente = $resp();
        if ($linea_siguiente === 'TIMEOUT') {
            if (strpos($r, '250') === 0) {
                break; // Tenemos respuesta válida
            }
            $GLOBALS['smtp_ultimo_error'] = 'Timeout en EHLO (TLS)';
            fclose($sock);
            return false;
        }
        if ($linea_siguiente === '') {
            break;
        }
        $r = $linea_siguiente;
        if (strpos($linea_siguiente, '250 ') === 0) {
            break;
        }
    }

    $envia("AUTH LOGIN");
    $r = $resp(); if ($r === 'TIMEOUT') { $GLOBALS['smtp_ultimo_error'] = 'Timeout en AUTH'; fclose($sock); return false; }
    $envia(base64_encode($MAIL_SMTP_USER));
    $r = $resp(); if ($r === 'TIMEOUT') { $GLOBALS['smtp_ultimo_error'] = 'Timeout en AUTH'; fclose($sock); return false; }
    $envia(base64_encode($MAIL_SMTP_PASS));
    $r = $resp();
    if ($r === 'TIMEOUT' || strpos($r, '235') !== 0) {
        $GLOBALS['smtp_ultimo_error'] = 'Gmail rechazó la contraseña. Respuesta: ' . $r . '. Revisá que sea la contraseña de aplicación (16 caracteres, sin espacios) y que la verificación en 2 pasos esté activa.';
        fclose($sock);
        return false;
    }

    $envia("MAIL FROM:<" . $MAIL_SMTP_USER . ">");
    $r = $resp(); if ($r === 'TIMEOUT') { $GLOBALS['smtp_ultimo_error'] = 'Timeout en MAIL FROM'; fclose($sock); return false; }
    $envia("RCPT TO:<" . trim($para) . ">");
    $r = $resp();
    if ($r === 'TIMEOUT' || (strpos($r, '250') !== 0 && strpos($r, '251') !== 0)) {
        $GLOBALS['smtp_ultimo_error'] = 'Destinatario rechazado: ' . $para . '. Respuesta: ' . $r . ' (revisá que el email esté bien escrito, ej. gmail no gmai)';
        fclose($sock);
        return false;
    }
    $envia("DATA");
    $r = $resp(); if ($r === 'TIMEOUT') { $GLOBALS['smtp_ultimo_error'] = 'Timeout en DATA'; fclose($sock); return false; }

    $cabeceras = "From: " . ($MAIL_FROM_NAME ?? 'Sistema') . " <" . $MAIL_SMTP_USER . ">\r\n";
    $cabeceras .= "To: " . $para . "\r\n";
    $cabeceras .= "Subject: " . $asunto . "\r\n";
    
    // Detectar si el cuerpo es HTML
    $es_html = (stripos($cuerpo, '<html') !== false || stripos($cuerpo, '<body') !== false);
    
    if ($es_html) {
        $cabeceras .= "Content-Type: text/html; charset=UTF-8\r\n";
    } else {
        $cabeceras .= "Content-Type: text/plain; charset=UTF-8\r\n";
    }
    $cabeceras .= "MIME-Version: 1.0\r\n\r\n";

    $cuerpo = str_replace("\r\n", "\n", $cuerpo);
    $cuerpo = str_replace("\n", "\r\n", $cuerpo);
    $cuerpo = str_replace("\r\n.", "\r\n..", $cuerpo);
    if (strlen($cuerpo) > 0 && $cuerpo[0] === '.') {
        $cuerpo = '.' . $cuerpo;
    }
    $mensaje = $cabeceras . $cuerpo . "\r\n.\r\n";
    fwrite($sock, $mensaje);
    $r = $resp();
    fclose($sock);

    if ($r === 'TIMEOUT' || strpos($r, '250') !== 0) {
        $GLOBALS['smtp_ultimo_error'] = 'Falló al enviar el mensaje. Respuesta: ' . $r;
        return false;
    }
    return true;
}

/**
 * Envía un correo con HTML y archivo adjunto (PDF, SQL, etc.)
 * @param string $archivo_adjunto Ruta del archivo a adjuntar
 * @param string $mime_adjunto   MIME type del adjunto (ej: application/pdf, application/sql). Por defecto se infiere por extensión.
 */
function enviar_mail_smtp_con_adjunto($para, $asunto, $cuerpo_html, $archivo_adjunto = null, $mime_adjunto = null) {
    $GLOBALS['smtp_ultimo_error'] = '';
    if (!file_exists(__DIR__ . '/mail_config.php')) {
        $GLOBALS['smtp_ultimo_error'] = 'No existe mail_config.php';
        return false;
    }
    include __DIR__ . '/mail_config.php';
    if (empty($MAIL_SMTP_USER) || empty($MAIL_SMTP_PASS)) {
        $GLOBALS['smtp_ultimo_error'] = 'Falta usuario o contraseña en mail_config.php';
        return false;
    }

    $host = 'smtp.gmail.com';
    $port = 587;
    $errno = 0;
    $errstr = '';
    $timeout = 30;

    $sock = @stream_socket_client("tcp://{$host}:{$port}", $errno, $errstr, $timeout, STREAM_CLIENT_CONNECT);
    if (!$sock) {
        $GLOBALS['smtp_ultimo_error'] = "No se pudo conectar a {$host}:{$port}. Error: [{$errno}] {$errstr}";
        return false;
    }
    stream_set_timeout($sock, 30);

    $resp = function() use ($sock) {
        $line = @fgets($sock, 512);
        if ($line === false) {
            $meta = stream_get_meta_data($sock);
            if ($meta['timed_out']) return 'TIMEOUT';
            return '';
        }
        return trim($line);
    };
    $envia = function($cmd) use ($sock) { fwrite($sock, $cmd . "\r\n"); };

    $r = $resp();
    if ($r === 'TIMEOUT' || strpos($r, '220') !== 0) {
        $GLOBALS['smtp_ultimo_error'] = 'Timeout o respuesta inesperada: ' . $r;
        fclose($sock);
        return false;
    }

    $envia("EHLO localhost");
    $r = $resp();
    if ($r === 'TIMEOUT' || $r === '') {
        $GLOBALS['smtp_ultimo_error'] = 'Timeout en EHLO';
        fclose($sock);
        return false;
    }
    while (true) {
        $linea = $resp();
        if ($linea === 'TIMEOUT') {
            if (strpos($r, '250') === 0) break;
            $GLOBALS['smtp_ultimo_error'] = 'Timeout en EHLO';
            fclose($sock);
            return false;
        }
        if ($linea === '' || strpos($linea, '250 ') === 0) break;
        $r = $linea;
    }

    $envia("STARTTLS");
    $r = $resp();
    if ($r === 'TIMEOUT' || strpos($r, '220') !== 0) {
        $GLOBALS['smtp_ultimo_error'] = 'STARTTLS falló: ' . $r;
        fclose($sock);
        return false;
    }
    if (!stream_socket_enable_crypto($sock, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
        $GLOBALS['smtp_ultimo_error'] = 'No se pudo activar TLS';
        fclose($sock);
        return false;
    }

    $envia("EHLO localhost");
    $r = $resp();
    if ($r === 'TIMEOUT' || $r === '') {
        $GLOBALS['smtp_ultimo_error'] = 'Timeout en EHLO (TLS)';
        fclose($sock);
        return false;
    }
    while (true) {
        $linea = $resp();
        if ($linea === 'TIMEOUT') {
            if (strpos($r, '250') === 0) break;
            $GLOBALS['smtp_ultimo_error'] = 'Timeout en EHLO (TLS)';
            fclose($sock);
            return false;
        }
        if ($linea === '' || strpos($linea, '250 ') === 0) break;
        $r = $linea;
    }

    $envia("AUTH LOGIN");
    $r = $resp();
    if ($r === 'TIMEOUT') { $GLOBALS['smtp_ultimo_error'] = 'Timeout en AUTH'; fclose($sock); return false; }
    $envia(base64_encode($MAIL_SMTP_USER));
    $r = $resp();
    if ($r === 'TIMEOUT') { $GLOBALS['smtp_ultimo_error'] = 'Timeout en AUTH'; fclose($sock); return false; }
    $envia(base64_encode($MAIL_SMTP_PASS));
    $r = $resp();
    if ($r === 'TIMEOUT' || strpos($r, '235') !== 0) {
        $GLOBALS['smtp_ultimo_error'] = 'Gmail rechazó la contraseña: ' . $r;
        fclose($sock);
        return false;
    }

    $envia("MAIL FROM:<" . $MAIL_SMTP_USER . ">");
    $r = $resp();
    if ($r === 'TIMEOUT') { $GLOBALS['smtp_ultimo_error'] = 'Timeout en MAIL FROM'; fclose($sock); return false; }
    $envia("RCPT TO:<" . trim($para) . ">");
    $r = $resp();
    if ($r === 'TIMEOUT' || (strpos($r, '250') !== 0 && strpos($r, '251') !== 0)) {
        $GLOBALS['smtp_ultimo_error'] = 'Destinatario rechazado: ' . $para . '. Respuesta: ' . $r;
        fclose($sock);
        return false;
    }
    $envia("DATA");
    $r = $resp();
    if ($r === 'TIMEOUT') { $GLOBALS['smtp_ultimo_error'] = 'Timeout en DATA'; fclose($sock); return false; }

    // Construir mensaje multipart si hay adjunto
    $boundary = '----=_NextPart_' . md5(time());
    $cabeceras = "From: " . ($MAIL_FROM_NAME ?? 'Sistema') . " <" . $MAIL_SMTP_USER . ">\r\n";
    $cabeceras .= "To: " . $para . "\r\n";
    $cabeceras .= "Subject: " . $asunto . "\r\n";
    
    if ($archivo_adjunto && file_exists($archivo_adjunto)) {
        $cabeceras .= "MIME-Version: 1.0\r\n";
        $cabeceras .= "Content-Type: multipart/mixed; boundary=\"$boundary\"\r\n\r\n";
        
        // Parte HTML
        $mensaje = "--$boundary\r\n";
        $mensaje .= "Content-Type: text/html; charset=UTF-8\r\n";
        $mensaje .= "Content-Transfer-Encoding: 7bit\r\n\r\n";
        $mensaje .= $cuerpo_html . "\r\n\r\n";
        
        // Adjunto
        $nombre_archivo = basename($archivo_adjunto);
        $contenido = file_get_contents($archivo_adjunto);
        $contenido_base64 = chunk_split(base64_encode($contenido));
        if ($mime_adjunto === null) {
            $ext = strtolower(pathinfo($nombre_archivo, PATHINFO_EXTENSION));
            $mime_adjunto = ($ext === 'sql') ? 'application/sql' : 'application/pdf';
        }
        $mensaje .= "--$boundary\r\n";
        $mensaje .= "Content-Type: $mime_adjunto; name=\"$nombre_archivo\"\r\n";
        $mensaje .= "Content-Disposition: attachment; filename=\"$nombre_archivo\"\r\n";
        $mensaje .= "Content-Transfer-Encoding: base64\r\n\r\n";
        $mensaje .= $contenido_base64 . "\r\n";
        $mensaje .= "--$boundary--\r\n";
    } else {
        $cabeceras .= "Content-Type: text/html; charset=UTF-8\r\n";
        $cabeceras .= "MIME-Version: 1.0\r\n\r\n";
        $mensaje = $cuerpo_html;
    }

    $mensaje_completo = $cabeceras . $mensaje;
    $mensaje_completo = str_replace("\r\n.", "\r\n..", $mensaje_completo);
    if (strlen($mensaje_completo) > 0 && $mensaje_completo[0] === '.') {
        $mensaje_completo = '.' . $mensaje_completo;
    }
    $mensaje_completo .= "\r\n.\r\n";
    
    fwrite($sock, $mensaje_completo);
    $r = $resp();
    fclose($sock);

    if ($r === 'TIMEOUT' || strpos($r, '250') !== 0) {
        $GLOBALS['smtp_ultimo_error'] = 'Falló al enviar el mensaje. Respuesta: ' . $r;
        return false;
    }
    return true;
}
