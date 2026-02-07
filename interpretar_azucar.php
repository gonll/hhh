<?php
include __DIR__ . '/db.php';
include __DIR__ . '/verificar_sesion.php';

header('Content-Type: application/json; charset=utf-8');

if (isset($_SESSION['acceso_nivel']) && $_SESSION['acceso_nivel'] < 2) {
    echo json_encode(['ok' => false, 'error' => 'Sin permiso']);
    exit;
}

$api_key = '';
if (file_exists(__DIR__ . '/config_openai.php')) {
    include __DIR__ . '/config_openai.php';
    $api_key = $openai_api_key ?? '';
}
if (empty($api_key)) {
    $api_key = getenv('OPENAI_API_KEY') ?: '';
}
if (empty($api_key)) {
    echo json_encode(['ok' => false, 'error' => 'Falta configurar OPENAI_API_KEY. Creá config_openai.php con $openai_api_key o definí la variable de entorno OPENAI_API_KEY.']);
    exit;
}

$texto = isset($_POST['texto']) ? trim($_POST['texto']) : '';
$imagen_b64 = isset($_POST['imagen']) ? trim($_POST['imagen']) : '';

if (empty($texto) && empty($imagen_b64)) {
    echo json_encode(['ok' => false, 'error' => 'Escribí algo o subí una imagen.']);
    exit;
}

$prompt = "Interpretá los datos que te envío (texto o imagen de una tabla/listado de stock o ventas de azúcar). " .
    "Devolvé ÚNICAMENTE un JSON válido: un array de objetos. Cada objeto debe tener exactamente estas claves con valores acordes: " .
    "fecha (formato DD/MM/YYYY), linea (número), articulo (texto), orden (número), cantidad (número), deposito (texto), " .
    "fecha_vta (DD/MM/YYYY o vacío), cant_vta (número), vendida_a (texto o vacío), precio_vta (número), " .
    "fecha_fact (DD/MM/YYYY o vacío), cant_fact (número), facturada_a (texto o vacío), precio_fac (número), n_fact (texto o vacío), n_remt (texto o vacío). " .
    "Si no hay dato para un campo usá cadena vacía o 0. No incluyas explicaciones ni markdown, solo el array JSON.";

$content = [];
$content[] = ['type' => 'text', 'text' => $prompt . "\n\n" . ($texto ?: "Los datos están en la imagen adjunta.")];

if (!empty($imagen_b64)) {
    $url_imagen = 'data:image/jpeg;base64,' . $imagen_b64;
    $content[] = ['type' => 'image_url', 'image_url' => ['url' => $url_imagen]];
}

$body = [
    'model' => 'gpt-4o-mini',
    'messages' => [
        ['role' => 'user', 'content' => $content]
    ],
    'max_tokens' => 4096
];

if (empty($imagen_b64) && !empty($texto)) {
    $body['messages'][0]['content'] = $prompt . "\n\n" . $texto;
}

$ch = curl_init('https://api.openai.com/v1/chat/completions');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: Bearer ' . $api_key,
    'Content-Type: application/json'
]);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));

$resp = curl_exec($ch);
$http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($resp === false) {
    echo json_encode(['ok' => false, 'error' => 'Error de conexión con OpenAI.']);
    exit;
}

$data = json_decode($resp, true);
if (!isset($data['choices'][0]['message']['content'])) {
    $err = isset($data['error']['message']) ? $data['error']['message'] : 'Respuesta inesperada de OpenAI.';
    echo json_encode(['ok' => false, 'error' => $err]);
    exit;
}

$raw = trim($data['choices'][0]['message']['content']);
// Quitar posible markdown ```json ... ```
if (preg_match('/```(?:json)?\s*([\s\S]*?)```/', $raw, $m)) {
    $raw = trim($m[1]);
}
$lista = json_decode($raw, true);

if (!is_array($lista)) {
    echo json_encode(['ok' => false, 'error' => 'ChatGPT no devolvió un JSON válido.', 'raw' => mb_substr($raw, 0, 500)]);
    exit;
}

echo json_encode(['ok' => true, 'datos' => $lista]);
