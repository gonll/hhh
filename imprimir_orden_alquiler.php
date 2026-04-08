<?php
include 'db.php';
include 'verificar_sesion.php';
require_once __DIR__ . '/inc_orden_alquiler.php';
require_once __DIR__ . '/includes_propiedad_fotos_mapa.php';

propiedades_asegurar_columnas($conexion);

$nivelAcceso = (int) ($_SESSION['acceso_nivel'] ?? 0);
if ($nivelAcceso < 2) {
    header('Location: index.php?msg=sin_permiso');
    exit;
}

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($id < 1) {
    die('Propiedad no especificada.');
}

$res = mysqli_query($conexion, 'SELECT * FROM propiedades WHERE propiedad_id = ' . $id . ' LIMIT 1');
$p = $res ? mysqli_fetch_assoc($res) : null;
if (!$p) {
    die('Propiedad no encontrada.');
}

$d = orden_alquiler_cargar_datos($id);

$fotos = propiedades_fotos_unificadas($id, $p['fotos_json'] ?? null);
$primeraFotoUrl = null;
foreach ($fotos as $rel0) {
    $rel0 = str_replace(['..', '\\'], '', (string) $rel0);
    if ($rel0 === '' || strpos($rel0, 'uploads/propiedades/') !== 0) {
        continue;
    }
    $primeraFotoUrl = propiedades_url_publica($rel0);
    break;
}

$lat = isset($p['mapa_lat']) && $p['mapa_lat'] !== null && $p['mapa_lat'] !== '' ? (float) $p['mapa_lat'] : null;
$lng = isset($p['mapa_lng']) && $p['mapa_lng'] !== null && $p['mapa_lng'] !== '' ? (float) $p['mapa_lng'] : null;
$diskMap = propiedades_leer_mapa_disco($id);
if (($lat === null || $lng === null) && is_array($diskMap)) {
    if (isset($diskMap['lat'], $diskMap['lng']) && $diskMap['lat'] !== null && $diskMap['lng'] !== '') {
        $lat = (float) $diskMap['lat'];
        $lng = (float) $diskMap['lng'];
    }
    if (empty($p['mapa_enlace']) && !empty($diskMap['enlace'])) {
        $p['mapa_enlace'] = $diskMap['enlace'];
    }
}
$tieneMapa = ($lat !== null && $lng !== null);
$zoomMapa = $tieneMapa ? propiedades_mapa_zoom_efectivo($p['mapa_enlace'] ?? '', $diskMap) : 15;
$gmaps_link = $tieneMapa ? ('https://www.google.com/maps?q=' . rawurlencode($lat . ',' . $lng)) : '';
/** Mapa estático OSM (imagen) para que imprima bien en PDF */
$staticMapUrl = '';
if ($tieneMapa) {
    $staticMapUrl = 'https://staticmap.openstreetmap.de/staticmap.php?center=' . rawurlencode($lat . ',' . $lng)
        . '&zoom=' . (int) $zoomMapa . '&size=520x240&markers=' . rawurlencode($lat . ',' . $lng) . ',red-pushpin';
}

function imprimir_url_absoluta_img($pathRel) {
    $pathRel = (string) $pathRel;
    if ($pathRel === '') {
        return '';
    }
    if (preg_match('#^https?://#i', $pathRel)) {
        return $pathRel;
    }
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    if ($pathRel[0] !== '/') {
        $pathRel = '/' . $pathRel;
    }
    return $scheme . '://' . $host . $pathRel;
}

function h($s) {
    return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
}

function fila_persona($titulo, array $persona) {
    $n = trim((string) ($persona['nombre'] ?? ''));
    $bloque = '<h3 style="margin:14px 0 8px; font-size:13px;">' . h($titulo) . '</h3><table style="width:100%; border-collapse:collapse; font-size:12px;">';
    $rows = [
        'Apellido y nombre' => $n,
        'DNI' => $persona['dni'] ?? '',
        'CUIT' => $persona['cuit'] ?? '',
        'Mail' => $persona['email'] ?? '',
        'Celular' => $persona['celular'] ?? '',
    ];
    foreach ($rows as $lab => $val) {
        $bloque .= '<tr><th style="border:1px solid #ccc; padding:6px 8px; background:#f5f5f5; width:28%; text-align:left;">' . h($lab) . '</th>';
        $bloque .= '<td style="border:1px solid #ccc; padding:6px 8px;">' . h($val !== '' ? $val : '—') . '</td></tr>';
    }
    return $bloque . '</table>';
}

$fecha = date('d/m/Y H:i');
$upd = !empty($d['updated_at']) ? date('d/m/Y H:i', strtotime($d['updated_at'])) : '—';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Orden de alquiler — <?= h($p['propiedad'] ?? '') ?></title>
    <style>
        /* Vista previa en pantalla: hoja A4 */
        html { box-sizing: border-box; }
        *, *::before, *::after { box-sizing: inherit; }
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 16px;
            color: #222;
            font-size: 12px;
            background: #c5c5c5;
        }
        .hoja-a4 {
            width: 210mm;
            max-width: 100%;
            min-height: 297mm;
            margin: 0 auto;
            padding: 14mm 12mm;
            background: #fff;
            box-shadow: 0 2px 14px rgba(0,0,0,0.2);
        }
        h1 { font-size: 17px; margin: 0 0 4px; }
        h2 { font-size: 14px; }
        .meta { font-size: 10px; color: #555; margin-bottom: 12px; }
        table.datos { border-collapse: collapse; width: 100%; margin-top: 6px; font-size: 11px; }
        table.datos th, table.datos td { border: 1px solid #ccc; padding: 6px 8px; text-align: left; }
        table.datos th { background: #f0f0f0; width: 22%; }
        .detalle { white-space: pre-wrap; font-size: 11px; word-break: break-word; }
        .no-print { margin-bottom: 16px; }
        .pie-doc { margin-top: 16px; font-size: 10px; color: #666; }
        .bloque-foto-mapa { width: 100%; border-collapse: collapse; margin: 12px 0 16px; font-size: 11px; }
        .bloque-foto-mapa td { border: 1px solid #ccc; padding: 8px; vertical-align: top; width: 50%; }
        .bloque-foto-mapa .tit-cel { font-weight: bold; margin-bottom: 6px; color: #333; }
        .bloque-foto-mapa img { max-width: 100%; height: auto; max-height: 200px; display: block; margin: 0 auto; }
        .bloque-foto-mapa .coords { font-size: 9px; color: #555; margin-top: 6px; }
        .bloque-foto-mapa .sin-dato { color: #888; font-style: italic; }

        /* Impresión: papel A4 con márgenes seguros */
        @page {
            size: A4;
            margin: 12mm 10mm;
        }
        @media print {
            body {
                padding: 0;
                background: #fff;
            }
            .no-print { display: none !important; }
            .hoja-a4 {
                width: 100%;
                max-width: none;
                min-height: auto;
                margin: 0;
                padding: 0;
                box-shadow: none;
                box-sizing: border-box;
            }
            table.datos, .detalle, .bloque-foto-mapa { page-break-inside: avoid; }
            h2 { page-break-after: avoid; }
            .bloque-foto-mapa img { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        }
    </style>
</head>
<body>
    <div class="no-print">
        <button type="button" onclick="window.print();" style="background:#28a745;color:#fff;border:none;padding:8px 16px;border-radius:4px;cursor:pointer;font-weight:bold;">Imprimir</button>
        <a href="orden_alquiler.php?id=<?= (int) $id ?>" style="margin-left:12px;">← Editar formulario</a>
        <a href="propiedades.php" style="margin-left:12px;">Gestión propiedades</a>
    </div>

    <div class="hoja-a4">
    <h1>Orden de alquiler</h1>
    <p class="meta">Impreso: <?= h($fecha) ?> · Último guardado en formulario: <?= h($upd) ?></p>

    <table class="bloque-foto-mapa">
        <tr>
            <td>
                <div class="tit-cel">Foto de la propiedad</div>
                <?php if ($primeraFotoUrl !== null):
                    $imgAbs = imprimir_url_absoluta_img($primeraFotoUrl);
                ?>
                    <img src="<?= h($imgAbs) ?>" alt="Foto principal">
                <?php else: ?>
                    <span class="sin-dato">Sin foto cargada.</span>
                <?php endif; ?>
            </td>
            <td>
                <div class="tit-cel">Ubicación</div>
                <?php if ($tieneMapa && $staticMapUrl !== ''): ?>
                    <img src="<?= h($staticMapUrl) ?>" alt="Mapa de ubicación">
                    <p class="coords">Zoom <?= (int) $zoomMapa ?> · <a href="<?= h($gmaps_link) ?>" target="_blank" rel="noopener">Google Maps</a><?php if (!empty($p['mapa_enlace'])): ?> · <a href="<?= h($p['mapa_enlace']) ?>" target="_blank" rel="noopener">Enlace guardado</a><?php endif; ?></p>
                <?php elseif (!empty($p['mapa_enlace'])): ?>
                    <p class="sin-dato" style="margin:0;">Mapa: enlace guardado (sin coordenadas en sistema).</p>
                    <p style="margin:6px 0 0; font-size:10px; word-break:break-all;"><?= h($p['mapa_enlace']) ?></p>
                <?php else: ?>
                    <span class="sin-dato">Sin ubicación en mapa.</span>
                <?php endif; ?>
            </td>
        </tr>
    </table>

    <table class="datos">
        <tr><th>Propiedad</th><td><?= h($p['propiedad'] ?? '') ?></td></tr>
        <tr><th>Consorcio</th><td><?= h($p['consorcio'] ?? '') ?></td></tr>
        <tr><th>Padrón</th><td><?= h($p['padron'] ?? '') ?></td></tr>
        <tr><th>Ciudad</th><td><?= h($p['ciudad'] ?? '') ?></td></tr>
        <tr><th>Detalle técnico</th><td class="detalle"><?= h($p['detalle'] ?? '') !== '' ? h($p['detalle']) : '—' ?></td></tr>
    </table>

    <h2 style="font-size:15px; margin-top:20px; border-bottom:1px solid #333; padding-bottom:4px;">Condiciones</h2>
    <table class="datos">
        <tr><th>Precio de alquiler pedido</th><td><?= h($d['precio_alquiler_pedido'] ?? '') !== '' ? h($d['precio_alquiler_pedido']) : '—' ?></td></tr>
        <tr><th>Actualización</th><td class="detalle"><?= h($d['actualizacion'] ?? '') !== '' ? nl2br(h($d['actualizacion'])) : '—' ?></td></tr>
        <tr><th>Monto garantía</th><td><?= h($d['monto_garantia'] ?? '') !== '' ? h($d['monto_garantia']) : '—' ?></td></tr>
    </table>

    <?= fila_persona('Solicitante', $d['solicitante'] ?? []) ?>
    <?= fila_persona('Garante 1', $d['garante1'] ?? []) ?>
    <?= fila_persona('Garante 2', $d['garante2'] ?? []) ?>

    <p class="pie-doc">Documento generado desde el sistema. Los datos de personas se utilizan como referencia para el contrato.</p>
    </div>
</body>
</html>
