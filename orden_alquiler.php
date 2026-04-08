<?php
include 'db.php';
include 'verificar_sesion.php';
require_once __DIR__ . '/includes_propiedad_fotos_mapa.php';
require_once __DIR__ . '/inc_orden_alquiler.php';

$nivelAcceso = (int) ($_SESSION['acceso_nivel'] ?? 0);
if ($nivelAcceso < 2) {
    header('Location: index.php?msg=sin_permiso');
    exit;
}

propiedades_asegurar_columnas($conexion);

$id = (int) ($_GET['id'] ?? $_POST['propiedad_id'] ?? 0);
if ($id <= 0) {
    header('Location: propiedades.php');
    exit;
}

$res = mysqli_query($conexion, 'SELECT * FROM propiedades WHERE propiedad_id = ' . $id . ' LIMIT 1');
$prop = $res ? mysqli_fetch_assoc($res) : null;
if (!$prop) {
    header('Location: propiedades.php');
    exit;
}

$mensaje = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['guardar_orden_alquiler'])) {
    $datos = orden_alquiler_post_a_datos();
    if (orden_alquiler_guardar_datos($id, $datos)) {
        header('Location: orden_alquiler.php?id=' . $id . '&ok=1');
        exit;
    }
    $mensaje = 'No se pudo guardar. Verifique permisos en la carpeta uploads/propiedades.';
}

$datos = orden_alquiler_cargar_datos($id);
if (isset($_GET['ok'])) {
    $mensaje = 'Datos guardados.';
}

$fotos = propiedades_fotos_unificadas($id, $prop['fotos_json'] ?? null);
$lat = isset($prop['mapa_lat']) && $prop['mapa_lat'] !== null && $prop['mapa_lat'] !== '' ? (float) $prop['mapa_lat'] : null;
$lng = isset($prop['mapa_lng']) && $prop['mapa_lng'] !== null && $prop['mapa_lng'] !== '' ? (float) $prop['mapa_lng'] : null;
$diskMap = propiedades_leer_mapa_disco($id);
if (($lat === null || $lng === null) && is_array($diskMap)) {
    if (isset($diskMap['lat'], $diskMap['lng']) && $diskMap['lat'] !== null && $diskMap['lng'] !== '') {
        $lat = (float) $diskMap['lat'];
        $lng = (float) $diskMap['lng'];
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

function h($s) {
    return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
}

$s = $datos['solicitante'];
$g1 = $datos['garante1'];
$g2 = $datos['garante2'];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Orden de alquiler — <?= h($prop['propiedad'] ?? '') ?></title>
    <style>
        * { box-sizing: border-box; }
        body { font-family: 'Segoe UI', sans-serif; background: #f0f2f5; margin: 0; padding: 12px; font-size: 13px; }
        .wrap { max-width: 920px; margin: 0 auto; background: #fff; padding: 18px 20px 28px; border-radius: 8px; box-shadow: 0 2px 12px rgba(0,0,0,0.08); }
        h1 { font-size: 17px; color: #007bff; margin: 0 0 6px; text-transform: uppercase; }
        .sub { color: #555; font-size: 12px; margin-bottom: 16px; }
        h2 { font-size: 13px; color: #333; border-bottom: 2px solid #007bff; padding-bottom: 6px; margin: 22px 0 12px; }
        .msg { padding: 10px 12px; border-radius: 6px; margin-bottom: 14px; font-weight: bold; }
        .msg.ok { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .msg.err { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .bloque-info { background: #f8f9fa; border: 1px solid #dee2e6; border-radius: 6px; padding: 12px 14px; margin-bottom: 12px; line-height: 1.55; }
        .bloque-info strong { color: #1a1a1a; }
        .galeria { display: grid; grid-template-columns: repeat(auto-fill, minmax(140px, 1fr)); gap: 10px; margin-top: 10px; }
        .galeria a { display: block; border: 1px solid #ddd; border-radius: 6px; overflow: hidden; background: #f5f5f5; }
        .galeria img { width: 100%; height: 120px; object-fit: cover; display: block; }
        .sin-dato { color: #888; font-style: italic; font-size: 12px; }
        .mapa-wrap { width: 100%; height: 280px; border: 1px solid #ccc; border-radius: 6px; overflow: hidden; margin-top: 8px; }
        .mapa-wrap iframe { width: 100%; height: 100%; border: 0; }
        .links-mapa { margin-top: 8px; font-size: 12px; }
        .links-mapa a { color: #007bff; margin-right: 12px; }
        .grid2 { display: grid; grid-template-columns: 1fr 1fr; gap: 12px 16px; }
        @media (max-width: 640px) { .grid2 { grid-template-columns: 1fr; } }
        label { display: block; font-size: 11px; font-weight: bold; color: #444; margin-bottom: 4px; }
        input[type="text"], input[type="email"], input[type="number"], textarea {
            width: 100%; padding: 8px 10px; border: 1px solid #ced4da; border-radius: 4px; font-size: 13px;
        }
        textarea { min-height: 64px; resize: vertical; }
        .persona-box { border: 1px solid #dee2e6; border-radius: 8px; padding: 14px; margin-bottom: 14px; background: #fafbfc; }
        .persona-box h3 { margin: 0 0 12px; font-size: 13px; color: #0d47a1; }
        .acciones { display: flex; flex-wrap: wrap; gap: 10px; margin-top: 20px; align-items: center; }
        .btn { display: inline-block; padding: 10px 18px; border: none; border-radius: 5px; font-weight: bold; cursor: pointer; font-size: 13px; text-decoration: none; }
        .btn-guardar { background: #007bff; color: #fff; }
        .btn-guardar:hover { background: #0069d9; }
        .btn-print { background: #28a745; color: #fff; }
        .btn-print:hover { background: #218838; }
        .btn-volver { background: #ffc107; color: #333; }
        .nota { font-size: 11px; color: #666; margin-top: 8px; line-height: 1.4; }
    </style>
</head>
<body>

<div class="wrap">
    <h1>Orden de alquiler</h1>
    <p class="sub">Los datos de solicitante y garantes se utilizarán al generar el contrato. Guarde antes de imprimir.</p>

    <?php if ($mensaje !== ''): ?>
        <div class="msg <?= (strpos($mensaje, 'No se pudo') !== false) ? 'err' : 'ok' ?>"><?= h($mensaje) ?></div>
    <?php endif; ?>

    <div class="bloque-info">
        <div><strong>Propiedad:</strong> <?= h($prop['propiedad'] ?? '') ?></div>
        <?php if (!empty($prop['consorcio'])): ?><div><strong>Consorcio:</strong> <?= h($prop['consorcio']) ?></div><?php endif; ?>
        <?php if (!empty($prop['padron'])): ?><div><strong>Padrón:</strong> <?= h($prop['padron']) ?></div><?php endif; ?>
        <?php if (!empty($prop['ciudad'])): ?><div><strong>Ciudad:</strong> <?= h($prop['ciudad']) ?></div><?php endif; ?>
        <div style="margin-top:8px;"><strong>Detalle técnico:</strong></div>
        <div style="white-space:pre-wrap; font-size:12px; color:#333;"><?= h($prop['detalle'] ?? '') !== '' ? h($prop['detalle']) : '—' ?></div>
    </div>

    <h2>Fotos</h2>
    <?php if (count($fotos) === 0): ?>
        <p class="sin-dato">No hay fotos cargadas. Podés cargarlas desde la edición de la propiedad o la ficha de fotos.</p>
    <?php else: ?>
        <div class="galeria">
            <?php foreach ($fotos as $rel):
                $rel = str_replace(['..', '\\'], '', $rel);
                if ($rel === '' || strpos($rel, 'uploads/propiedades/') !== 0) {
                    continue;
                }
                $url = propiedades_url_publica($rel);
                $src = h($url);
            ?>
            <a href="<?= $src ?>" target="_blank" rel="noopener" title="Abrir foto"><img src="<?= $src ?>" alt="Foto"></a>
            <?php endforeach; ?>
        </div>
        <p style="margin-top:10px;"><a href="ver_propiedad.php?id=<?= $id ?>#fotos" target="_blank">Abrir galería completa</a></p>
    <?php endif; ?>

    <h2>Mapa</h2>
    <?php if (!$tieneMapa): ?>
        <p class="sin-dato">No hay ubicación en mapa. Cargá coordenadas desde la edición de la propiedad.</p>
        <?php if (!empty($prop['mapa_enlace'])): ?>
            <p><a href="<?= h($prop['mapa_enlace']) ?>" target="_blank" rel="noopener">Abrir enlace guardado</a></p>
        <?php endif; ?>
    <?php else: ?>
        <div class="mapa-wrap">
            <iframe title="Mapa" loading="lazy" src="<?= h($osm_embed) ?>"></iframe>
        </div>
        <div class="links-mapa">
            <a href="<?= h($gmaps_link) ?>" target="_blank" rel="noopener">Google Maps</a>
            <?php if (!empty($prop['mapa_enlace'])): ?>
                <a href="<?= h($prop['mapa_enlace']) ?>" target="_blank" rel="noopener">Enlace original</a>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <form method="post" action="orden_alquiler.php">
        <input type="hidden" name="propiedad_id" value="<?= (int) $id ?>">
        <input type="hidden" name="guardar_orden_alquiler" value="1">

        <h2>Condiciones solicitadas</h2>
        <div class="grid2">
            <div>
                <label for="precio_alquiler_pedido">Precio de alquiler pedido</label>
                <input type="text" name="precio_alquiler_pedido" id="precio_alquiler_pedido" value="<?= h($datos['precio_alquiler_pedido']) ?>" placeholder="Ej: 150000 o 150.000,00" autocomplete="off">
            </div>
            <div>
                <label for="monto_garantia">Monto garantía</label>
                <input type="text" name="monto_garantia" id="monto_garantia" value="<?= h($datos['monto_garantia']) ?>" placeholder="Ej: 300000" autocomplete="off">
            </div>
        </div>
        <div style="margin-top:12px;">
            <label for="actualizacion">Actualización (índice / forma de ajuste)</label>
            <textarea name="actualizacion" id="actualizacion" placeholder="Ej: IPC trimestral, porcentaje anual, etc."><?= h($datos['actualizacion']) ?></textarea>
        </div>

        <h2>Solicitante (locatario previsto)</h2>
        <div class="persona-box">
            <h3>Datos para el contrato</h3>
            <div class="grid2">
                <div><label for="solicitante_nombre">Apellido y nombre</label><input type="text" name="solicitante_nombre" id="solicitante_nombre" value="<?= h($s['nombre']) ?>" autocomplete="off"></div>
                <div><label for="solicitante_dni">DNI</label><input type="text" name="solicitante_dni" id="solicitante_dni" value="<?= h($s['dni']) ?>" autocomplete="off"></div>
                <div><label for="solicitante_cuit">CUIT</label><input type="text" name="solicitante_cuit" id="solicitante_cuit" value="<?= h($s['cuit']) ?>" autocomplete="off"></div>
                <div><label for="solicitante_email">Mail</label><input type="email" name="solicitante_email" id="solicitante_email" value="<?= h($s['email']) ?>" autocomplete="off"></div>
                <div style="grid-column: 1 / -1;"><label for="solicitante_celular">Celular</label><input type="text" name="solicitante_celular" id="solicitante_celular" value="<?= h($s['celular']) ?>" autocomplete="off"></div>
            </div>
        </div>

        <h2>Garante 1</h2>
        <div class="persona-box">
            <div class="grid2">
                <div><label for="garante1_nombre">Apellido y nombre</label><input type="text" name="garante1_nombre" id="garante1_nombre" value="<?= h($g1['nombre']) ?>" autocomplete="off"></div>
                <div><label for="garante1_dni">DNI</label><input type="text" name="garante1_dni" id="garante1_dni" value="<?= h($g1['dni']) ?>" autocomplete="off"></div>
                <div><label for="garante1_cuit">CUIT</label><input type="text" name="garante1_cuit" id="garante1_cuit" value="<?= h($g1['cuit']) ?>" autocomplete="off"></div>
                <div><label for="garante1_email">Mail</label><input type="email" name="garante1_email" id="garante1_email" value="<?= h($g1['email']) ?>" autocomplete="off"></div>
                <div style="grid-column: 1 / -1;"><label for="garante1_celular">Celular</label><input type="text" name="garante1_celular" id="garante1_celular" value="<?= h($g1['celular']) ?>" autocomplete="off"></div>
            </div>
        </div>

        <h2>Garante 2</h2>
        <div class="persona-box">
            <div class="grid2">
                <div><label for="garante2_nombre">Apellido y nombre</label><input type="text" name="garante2_nombre" id="garante2_nombre" value="<?= h($g2['nombre']) ?>" autocomplete="off"></div>
                <div><label for="garante2_dni">DNI</label><input type="text" name="garante2_dni" id="garante2_dni" value="<?= h($g2['dni']) ?>" autocomplete="off"></div>
                <div><label for="garante2_cuit">CUIT</label><input type="text" name="garante2_cuit" id="garante2_cuit" value="<?= h($g2['cuit']) ?>" autocomplete="off"></div>
                <div><label for="garante2_email">Mail</label><input type="email" name="garante2_email" id="garante2_email" value="<?= h($g2['email']) ?>" autocomplete="off"></div>
                <div style="grid-column: 1 / -1;"><label for="garante2_celular">Celular</label><input type="text" name="garante2_celular" id="garante2_celular" value="<?= h($g2['celular']) ?>" autocomplete="off"></div>
            </div>
        </div>

        <p class="nota">Al generar el contrato desde el sistema, podrán tomarse estos datos si el flujo lo incorpora. Guardá los cambios con el botón inferior.</p>

        <div class="acciones">
            <button type="submit" class="btn btn-guardar">Guardar datos</button>
            <a href="imprimir_orden_alquiler.php?id=<?= (int) $id ?>" target="_blank" class="btn btn-print">Vista imprimible</a>
            <a href="propiedades.php" class="btn btn-volver">← Volver a propiedades</a>
        </div>
    </form>
</div>

<?php include 'timeout_sesion_inc.php'; ?>
</body>
</html>
