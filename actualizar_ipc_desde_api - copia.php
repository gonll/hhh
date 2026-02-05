<?php
/**
 * Actualiza el IPC en la tabla indices desde la API (una vez al mes, después del día 10).
 * Incluir este archivo al inicio del index.php para que se ejecute al abrir el sistema.
 */
if (!isset($conexion)) return;

$dia_actual = (int)date('j');
if ($dia_actual < 11) return;

$json = @file_get_contents('https://api.argly.com.ar/api/ipc');
if ($json === false) return;

$resp = @json_decode($json, true);
if (!isset($resp['data']['indice_ipc'], $resp['data']['anio'], $resp['data']['mes'])) return;

$valor = (float)$resp['data']['indice_ipc'];
$anio  = (int)$resp['data']['anio'];
$mes   = (int)$resp['data']['mes'];
$fecha = sprintf('%04d-%02d-01', $anio, $mes);

$existe = mysqli_query($conexion, "SELECT id FROM indices WHERE fecha = '$fecha' AND tipo = 'IPC' LIMIT 1");
if ($existe && mysqli_num_rows($existe) > 0) return;

$valor = number_format($valor, 4, '.', '');
$sql   = "INSERT INTO indices (fecha, valor, tipo) VALUES ('$fecha', $valor, 'IPC')";
@mysqli_query($conexion, $sql);
?>
