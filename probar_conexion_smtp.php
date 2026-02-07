<?php
/**
 * Script para probar la conexión SMTP a Gmail
 */
$host = 'smtp.gmail.com';
$port = 587;

echo "<h2>Probando conexión SMTP a Gmail</h2>";
echo "<p>Host: $host<br>Puerto: $port</p>";

// Probar conexión básica
$errno = 0;
$errstr = '';
$timeout = 10;

echo "<h3>1. Probando conexión TCP...</h3>";
$sock = @stream_socket_client(
    "tcp://{$host}:{$port}",
    $errno,
    $errstr,
    $timeout,
    STREAM_CLIENT_CONNECT
);

if (!$sock) {
    echo "<p style='color:red;'>❌ No se pudo conectar. Error [$errno]: $errstr</p>";
    echo "<p><strong>Posibles causas:</strong></p>";
    echo "<ul>";
    echo "<li>Firewall bloqueando el puerto 587</li>";
    echo "<li>Proveedor de internet bloqueando SMTP</li>";
    echo "<li>Servidor sin acceso a internet</li>";
    echo "</ul>";
    exit;
}

echo "<p style='color:green;'>✓ Conexión TCP establecida</p>";

// Leer respuesta inicial
stream_set_timeout($sock, 10);
$line = @fgets($sock, 512);
echo "<h3>2. Respuesta del servidor:</h3>";
echo "<pre>" . htmlspecialchars($line) . "</pre>";

if (strpos($line, '220') === 0) {
    echo "<p style='color:green;'>✓ Servidor SMTP respondió correctamente</p>";
} else {
    echo "<p style='color:orange;'>⚠ Respuesta inesperada</p>";
}

fclose($sock);
echo "<p><strong>Conclusión:</strong> La conexión básica funciona. Si el envío sigue fallando, puede ser un problema de autenticación o configuración SMTP.</p>";
?>
