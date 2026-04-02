<?php
include 'db.php';
include 'verificar_sesion.php';
require_once __DIR__ . '/includes_propiedad_fotos_mapa.php';
propiedades_asegurar_columnas($conexion);

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    header('Location: propiedades.php');
    exit;
}

$res = mysqli_query($conexion, "SELECT * FROM propiedades WHERE propiedad_id = " . $id . " LIMIT 1");
if (!$res) {
    error_log('ver_propiedad SELECT: ' . mysqli_error($conexion));
}
$prop = $res ? mysqli_fetch_assoc($res) : null;
if (!$prop) {
    header('Location: propiedades.php');
    exit;
}

$fotos = propiedades_fotos_unificadas($id, $prop['fotos_json'] ?? null);
$lat = isset($prop['mapa_lat']) && $prop['mapa_lat'] !== null && $prop['mapa_lat'] !== '' ? (float)$prop['mapa_lat'] : null;
$lng = isset($prop['mapa_lng']) && $prop['mapa_lng'] !== null && $prop['mapa_lng'] !== '' ? (float)$prop['mapa_lng'] : null;
$diskMap = propiedades_leer_mapa_disco($id);
if (($lat === null || $lng === null) && is_array($diskMap)) {
    if (isset($diskMap['lat'], $diskMap['lng']) && $diskMap['lat'] !== null && $diskMap['lng'] !== '') {
        $lat = (float)$diskMap['lat'];
        $lng = (float)$diskMap['lng'];
    }
    if (empty($prop['mapa_enlace']) && !empty($diskMap['enlace'])) {
        $prop['mapa_enlace'] = $diskMap['enlace'];
    }
}
$tieneMapa = ($lat !== null && $lng !== null);
if ($tieneMapa) {
    $dl = 0.012;
    $bbox = ($lng - $dl) . '%2C' . ($lat - $dl) . '%2C' . ($lng + $dl) . '%2C' . ($lat + $dl);
    $osm_embed = 'https://www.openstreetmap.org/export/embed.html?bbox=' . $bbox . '&layer=mapnik&marker=' . rawurlencode($lat . ',' . $lng);
    $gmaps_link = 'https://www.google.com/maps?q=' . rawurlencode($lat . ',' . $lng);
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Propiedad — Fotos y ubicación</title>
    <style>
        body { font-family: 'Segoe UI', sans-serif; background: #f0f2f5; margin: 0; padding: 12px; }
        .card { background: white; max-width: 720px; margin: 0 auto; padding: 16px; border-radius: 8px; box-shadow: 0 2px 12px rgba(0,0,0,0.08); }
        h1 { font-size: 15px; color: #007bff; margin: 0 0 8px; text-transform: uppercase; }
        .meta { font-size: 11px; color: #555; margin-bottom: 16px; line-height: 1.5; }
        h2 { font-size: 12px; color: #333; border-bottom: 1px solid #dee2e6; padding-bottom: 6px; margin: 20px 0 10px; }
        .galeria { display: grid; grid-template-columns: repeat(auto-fill, minmax(160px, 1fr)); gap: 10px; }
        .galeria img { width: 100%; height: 140px; object-fit: cover; border-radius: 6px; border: 1px solid #ddd; cursor: zoom-in; }
        .sin-dato { color: #888; font-size: 11px; font-style: italic; }
        .mapa-wrap { width: 100%; height: 320px; border: 1px solid #ccc; border-radius: 6px; overflow: hidden; }
        .mapa-wrap iframe { width: 100%; height: 100%; border: 0; }
        .links-mapa { margin-top: 10px; font-size: 11px; }
        .links-mapa a { color: #007bff; margin-right: 12px; }
        .volver { display: inline-block; margin-top: 18px; background: #ffc107; color: #333; padding: 8px 16px; border-radius: 4px; text-decoration: none; font-weight: bold; font-size: 11px; }
        .volver:hover { background: #e0a800; }
    </style>
</head>
<body>

<div class="card">
    <h1><?= htmlspecialchars($prop['propiedad'] ?? '') ?></h1>
    <div class="meta">
        <?php if (!empty($prop['ciudad'])): ?><div><strong>Ciudad:</strong> <?= htmlspecialchars($prop['ciudad']) ?></div><?php endif; ?>
        <?php if (!empty($prop['consorcio'])): ?><div><strong>Consorcio:</strong> <?= htmlspecialchars($prop['consorcio']) ?></div><?php endif; ?>
        <?php if (!empty($prop['padron'])): ?><div><strong>Padrón:</strong> <?= htmlspecialchars($prop['padron']) ?></div><?php endif; ?>
    </div>

    <h2 id="fotos">Fotos</h2>
    <?php if (count($fotos) === 0): ?>
        <p class="sin-dato">No hay fotos cargadas para esta propiedad.</p>
    <?php else: ?>
        <div class="galeria">
            <?php foreach ($fotos as $rel):
                $rel = str_replace(['..', '\\'], '', $rel);
                if ($rel === '' || strpos($rel, 'uploads/propiedades/') !== 0) {
                    continue;
                }
                $url = propiedades_url_publica($rel);
                $src = htmlspecialchars($url, ENT_QUOTES, 'UTF-8');
            ?>
            <a href="<?= $src ?>" target="_blank" rel="noopener"><img src="<?= $src ?>" alt="Foto propiedad"></a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <h2 id="mapa">Ubicación</h2>
    <?php if (!$tieneMapa): ?>
        <p class="sin-dato">No hay coordenadas guardadas. Edite la propiedad y cargue ubicación desde Google Maps o latitud/longitud.</p>
        <?php if (!empty($prop['mapa_enlace'])): ?>
            <p class="links-mapa"><a href="<?= htmlspecialchars($prop['mapa_enlace'], ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener">Abrir enlace guardado</a></p>
        <?php endif; ?>
    <?php else: ?>
        <div class="mapa-wrap">
            <iframe title="Mapa" loading="lazy" src="<?= htmlspecialchars($osm_embed, ENT_QUOTES, 'UTF-8') ?>"></iframe>
        </div>
        <div class="links-mapa">
            <a href="<?= htmlspecialchars($gmaps_link, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener">Abrir en Google Maps</a>
            <?php if (!empty($prop['mapa_enlace'])): ?>
                <a href="<?= htmlspecialchars($prop['mapa_enlace'], ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener">Enlace original</a>
            <?php endif; ?>
        </div>
        <p style="font-size:10px; color:#666;">Coordenadas: <?= htmlspecialchars((string)$lat) ?>, <?= htmlspecialchars((string)$lng) ?></p>
    <?php endif; ?>

    <a href="propiedades.php" class="volver">← Volver a gestión de propiedades</a>
</div>
<?php include 'timeout_sesion_inc.php'; ?>
</body>
</html>
