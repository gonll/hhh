<?php
/**
 * JSON: fotos de una propiedad para galería en contrato_alquiler (GET id).
 */
include 'db.php';
include 'verificar_sesion.php';
require_once __DIR__ . '/includes_propiedad_fotos_mapa.php';
propiedades_asegurar_columnas($conexion);

header('Content-Type: application/json; charset=UTF-8');

$id = (int)($_GET['id'] ?? $_GET['propiedad_id'] ?? 0);
if ($id <= 0) {
    echo json_encode(['ok' => true, 'fotos' => []]);
    exit;
}

require_once __DIR__ . '/helpers_tenant_inmobiliaria.php';
tenant_inmob_asegurar_esquema($conexion);
if (!tenant_inmob_propiedad_id_visible($conexion, $id)) {
    echo json_encode(['ok' => true, 'fotos' => []]);
    exit;
}

$res = mysqli_query($conexion, 'SELECT fotos_json FROM propiedades WHERE propiedad_id = ' . $id . ' LIMIT 1');
$row = $res ? mysqli_fetch_assoc($res) : null;
$fotos = $row ? propiedades_fotos_unificadas($id, $row['fotos_json'] ?? null) : [];

$urls = [];
foreach ($fotos as $rel) {
    $urls[] = propiedades_url_publica($rel);
}

echo json_encode(['ok' => true, 'fotos' => $urls], JSON_UNESCAPED_UNICODE);
