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
    $timeout = 15;

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
    // Timeout en lecturas/escrituras para no colgar (p. ej. 20 s por operación)
    stream_set_timeout($sock, 20);

    $resp = function() use ($sock) {
        $line = @fgets($sock, 512);
        if ($line === false && stream_get_meta_data($sock)['timed_out']) {
            return 'TIMEOUT';
        }
        return $line === false ? '' : trim($line);
    };
    $envia = function($cmd) use ($sock) {
        fwrite($sock, $cmd . "\r\n");
    };

    $r = $resp(); // 220 greeting
    if ($r === 'TIMEOUT') { $GLOBALS['smtp_ultimo_error'] = 'Timeout esperando respuesta del servidor (¿red lenta o bloqueada?)'; fclose($sock); return false; }
    $envia("EHLO localhost");
    while (($r = $resp()) !== '') { if ($r === 'TIMEOUT') { $GLOBALS['smtp_ultimo_error'] = 'Timeout en EHLO'; fclose($sock); return false; } }

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
    while (($r = $resp()) !== '') { if ($r === 'TIMEOUT') { $GLOBALS['smtp_ultimo_error'] = 'Timeout en EHLO (TLS)'; fclose($sock); return false; } }

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
    $cabeceras .= "Content-Type: text/plain; charset=UTF-8\r\n";
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
