<?php
include 'db.php';

// 1. Obtener los datos (Ejemplo usando una fuente pública o API)
// Nota: La mayoría de las APIs financieras requieren un Token.
$url = "https://api.estadisticasbcra.com/ipc"; 
$token = "TU_TOKEN_AQUI"; // Debes obtenerlo en estadisticasbcra.com

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ["Authorization: Bearer $token"]);
$response = curl_exec($ch);
curl_close($ch);

$datos = json_decode($response, true);

if ($datos) {
    // Tomamos el último valor disponible en el listado
    $ultimo = end($datos);
    $fecha = $ultimo['d']; // Ejemplo: 2026-01-01
    $valor = $ultimo['v']; // Ejemplo: 1.9

    // 2. Insertar o actualizar en la tabla 'indices'
    $sql = "INSERT INTO indices (fecha, valor, tipo) 
            VALUES ('$fecha', $valor, 'IPC') 
            ON DUPLICATE KEY UPDATE valor = $valor";
    
    mysqli_query($conexion, $sql);
}
?>