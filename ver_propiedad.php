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
        .galeria-thumb {
            display: block; width: 100%; padding: 0; margin: 0; border: 1px solid #ddd; border-radius: 6px;
            overflow: hidden; cursor: pointer; background: #f5f5f5; -webkit-tap-highlight-color: transparent;
        }
        .galeria-thumb img { width: 100%; height: 140px; object-fit: cover; display: block; pointer-events: none; }
        .visor-fotos {
            display: none; position: fixed; inset: 0; z-index: 99999;
            flex-direction: column; align-items: stretch; justify-content: center;
            padding: env(safe-area-inset-top, 0) env(safe-area-inset-right, 0) env(safe-area-inset-bottom, 0) env(safe-area-inset-left, 0);
            box-sizing: border-box;
        }
        .visor-fotos.abierto { display: flex; }
        .visor-fotos-backdrop {
            position: absolute; inset: 0; background: rgba(0,0,0,0.92);
        }
        .visor-fotos-cerrar {
            position: relative; z-index: 2; align-self: flex-end; margin: 8px 8px 0;
            min-height: 48px; min-width: 120px; padding: 12px 20px; font-size: 16px; font-weight: bold;
            border: none; border-radius: 8px; background: #fff; color: #333; cursor: pointer;
            box-shadow: 0 2px 8px rgba(0,0,0,0.3); -webkit-tap-highlight-color: transparent;
        }
        .visor-fotos-cerrar:active { background: #e9e9e9; }
        .visor-fotos-cont {
            position: relative; z-index: 1; flex: 1; display: flex; align-items: center; justify-content: center;
            padding: 8px 12px 24px; min-height: 0; overflow: auto; -webkit-overflow-scrolling: touch;
        }
        .visor-fotos-cont img {
            max-width: 100%; max-height: calc(100vh - 120px); width: auto; height: auto; object-fit: contain;
        }
        body.visor-abierto { overflow: hidden; touch-action: none; }
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
            <button type="button" class="galeria-thumb" data-src="<?= $src ?>" title="Ver foto">
                <img src="<?= $src ?>" alt="Foto propiedad">
            </button>
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

<div id="visorFotos" class="visor-fotos" aria-hidden="true" role="dialog" aria-modal="true" aria-label="Foto ampliada">
    <div class="visor-fotos-backdrop" id="visorFotosBackdrop"></div>
    <button type="button" class="visor-fotos-cerrar" id="visorFotosCerrar">✕ Cerrar</button>
    <div class="visor-fotos-cont">
        <img id="visorFotosImg" src="" alt="Foto ampliada">
    </div>
</div>

<script>
(function() {
    var visor = document.getElementById('visorFotos');
    var imgEl = document.getElementById('visorFotosImg');
    var btnCerrar = document.getElementById('visorFotosCerrar');
    var backdrop = document.getElementById('visorFotosBackdrop');
    var cont = visor.querySelector('.visor-fotos-cont');

    function abrir(src) {
        imgEl.src = src;
        imgEl.alt = 'Foto ampliada';
        visor.classList.add('abierto');
        visor.setAttribute('aria-hidden', 'false');
        document.body.classList.add('visor-abierto');
        btnCerrar.focus();
    }
    function cerrar() {
        visor.classList.remove('abierto');
        visor.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('visor-abierto');
        imgEl.removeAttribute('src');
    }

    document.querySelectorAll('.galeria-thumb').forEach(function(btn) {
        btn.addEventListener('click', function() {
            abrir(this.getAttribute('data-src'));
        });
    });
    btnCerrar.addEventListener('click', function(e) { e.stopPropagation(); cerrar(); });
    backdrop.addEventListener('click', cerrar);
    cont.addEventListener('click', function(e) {
        if (e.target !== imgEl) {
            cerrar();
        }
    });
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && visor.classList.contains('abierto')) {
            cerrar();
        }
    });
})();
</script>
<?php include 'timeout_sesion_inc.php'; ?>
</body>
</html>
