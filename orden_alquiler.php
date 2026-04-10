<?php
include 'db.php';
include 'verificar_sesion.php';
require_once __DIR__ . '/helpers_tenant_inmobiliaria.php';
tenant_inmob_asegurar_esquema($conexion);
require_once __DIR__ . '/includes_propiedad_fotos_mapa.php';
require_once __DIR__ . '/inc_orden_alquiler.php';

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

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
if (!tenant_inmob_propiedad_id_visible($conexion, $id)) {
    header('Location: index.php?msg=sin_permiso');
    exit;
}

$mostrador = trim((string) ($_SESSION['acceso_usuario'] ?? ''));

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_cambio_modo'])) {
    header('Content-Type: application/json; charset=utf-8');
    $modoNuevo = (isset($_POST['modo_operacion']) && $_POST['modo_operacion'] === 'venta') ? 'venta' : 'alquiler';
    $prev = orden_alquiler_cargar_datos($id);
    $modoAnt = (($prev['modo_operacion'] ?? '') === 'venta') ? 'venta' : 'alquiler';
    $nuevaFila = null;
    if ($modoAnt !== $modoNuevo) {
        $prev['modo_operacion'] = $modoNuevo;
        orden_alquiler_agregar_evento_historial($prev, 'cambio_modo', $modoNuevo, '', $mostrador, 'Selección alquiler/venta en pantalla');
        orden_alquiler_guardar_datos($id, $prev);
        $hc = $prev['historial'] ?? [];
        $last = is_array($hc) && $hc !== [] ? $hc[count($hc) - 1] : null;
        if (is_array($last)) {
            $nReal = is_array($hc) ? count($hc) : 0;
            $nuevaFila = [
                'fecha_txt' => date('d/m/Y H:i'),
                'tipo' => $last['tipo'] ?? '',
                'tipo_txt' => oa_label_tipo_hist($last['tipo'] ?? ''),
                'modo' => (($last['modo'] ?? '') === 'venta') ? 'venta' : 'alquiler',
                'cliente' => (string) ($last['cliente'] ?? ''),
                'mostrador' => (string) ($last['mostrador'] ?? ''),
                'nota' => (string) ($last['nota'] ?? ''),
                'historial_real_idx' => $nReal > 0 ? $nReal - 1 : 0,
            ];
        }
    }
    echo json_encode(['ok' => true, 'modo' => $modoNuevo, 'historial_agregado' => $nuevaFila], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['registrar_visita'])) {
    $cliente = trim((string) ($_POST['cliente_visita'] ?? ''));
    $modoVis = (isset($_POST['modo_visita']) && $_POST['modo_visita'] === 'venta') ? 'venta' : 'alquiler';
    $nota = trim((string) ($_POST['nota_visita'] ?? ''));
    $prev = orden_alquiler_cargar_datos($id);
    orden_alquiler_agregar_evento_historial($prev, 'visita', $modoVis, $cliente, $mostrador, $nota);
    $prev = orden_alquiler_normalizar_datos($prev);
    if (orden_alquiler_guardar_datos($id, $prev)) {
        header('Location: orden_alquiler.php?id=' . $id . '&ok_visita=1');
        exit;
    }
    header('Location: orden_alquiler.php?id=' . $id . '&err_visita=1');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['borrar_historial_registro'])) {
    $rid = (int) ($_POST['historial_real_idx'] ?? -1);
    $prev = orden_alquiler_cargar_datos($id);
    $hist = isset($prev['historial']) && is_array($prev['historial']) ? $prev['historial'] : [];
    if ($rid >= 0 && $rid < count($hist)) {
        array_splice($hist, $rid, 1);
        $prev['historial'] = $hist;
        $prev = orden_alquiler_normalizar_datos($prev);
        if (orden_alquiler_guardar_datos($id, $prev)) {
            header('Location: orden_alquiler.php?id=' . $id . '&ok_hist_borrado=1');
            exit;
        }
    }
    header('Location: orden_alquiler.php?id=' . $id . '&err_hist_borrado=1');
    exit;
}

$mensaje = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['guardar_orden_alquiler'])) {
    $prev = orden_alquiler_cargar_datos($id);
    $datos = orden_alquiler_post_a_datos();
    $hist = isset($prev['historial']) && is_array($prev['historial']) ? $prev['historial'] : [];
    $datos['historial'] = $hist;
    $nomCli = trim((string) ($datos['solicitante']['nombre'] ?? ''));
    orden_alquiler_agregar_evento_historial($datos, 'guardado', $datos['modo_operacion'], $nomCli, $mostrador, '');
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
if (isset($_GET['ok_visita'])) {
    $mensaje = 'Visita registrada en el historial.';
}
if (isset($_GET['err_visita'])) {
    $mensaje = 'No se pudo registrar la visita.';
}
if (isset($_GET['ok_hist_borrado'])) {
    $mensaje = 'Registro eliminado del historial.';
}
if (isset($_GET['err_hist_borrado'])) {
    $mensaje = 'No se pudo eliminar el registro del historial.';
}

$modoActual = (($datos['modo_operacion'] ?? '') === 'venta') ? 'venta' : 'alquiler';

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
$zoomMapa = 15;
$osm_embed = '';
$gmaps_link = '';
if ($tieneMapa) {
    $zoomMapa = propiedades_mapa_zoom_efectivo($prop['mapa_enlace'] ?? '', $diskMap);
    $dl = propiedades_mapa_bbox_delta_desde_zoom($zoomMapa);
    $bbox = ($lng - $dl) . '%2C' . ($lat - $dl) . '%2C' . ($lng + $dl) . '%2C' . ($lat + $dl);
    $osm_embed = 'https://www.openstreetmap.org/export/embed.html?bbox=' . $bbox . '&layer=mapnik&marker=' . rawurlencode($lat . ',' . $lng);
    $gmaps_link = 'https://www.google.com/maps?q=' . rawurlencode($lat . ',' . $lng);
}

$primeraFotoUrl = null;
foreach ($fotos as $rel0) {
    $rel0 = str_replace(['..', '\\'], '', (string) $rel0);
    if ($rel0 === '' || strpos($rel0, 'uploads/propiedades/') !== 0) {
        continue;
    }
    $primeraFotoUrl = propiedades_url_publica($rel0);
    break;
}
$fotosRestantes = [];
if ($primeraFotoUrl !== null && count($fotos) > 1) {
    $saltarUna = true;
    foreach ($fotos as $rel0) {
        $rel0 = str_replace(['..', '\\'], '', (string) $rel0);
        if ($rel0 === '' || strpos($rel0, 'uploads/propiedades/') !== 0) {
            continue;
        }
        if ($saltarUna) {
            $saltarUna = false;
            continue;
        }
        $fotosRestantes[] = $rel0;
    }
}

function h($s) {
    return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
}

function oa_label_tipo_hist($tipo) {
    switch ((string) $tipo) {
        case 'guardado':
            return 'Guardado';
        case 'cambio_modo':
            return 'Cambio modo';
        case 'visita':
            return 'Visita';
        default:
            return (string) $tipo;
    }
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
    <title>Orden de alquiler/venta — <?= h($prop['propiedad'] ?? '') ?></title>
    <style>
        * { box-sizing: border-box; }
        body { font-family: 'Segoe UI', sans-serif; background: #f0f2f5; margin: 0; padding: 12px; font-size: 13px; }
        .wrap { max-width: 920px; margin: 0 auto; background: #fff; padding: 18px 20px 28px; border-radius: 8px; box-shadow: 0 2px 12px rgba(0,0,0,0.08); }
        h1 { font-size: 17px; color: #333; margin: 0 0 6px; text-transform: none; font-weight: 700; }
        h1 .tit-prefijo { color: #007bff; text-transform: uppercase; font-size: 14px; }
        h1 .tit-mod { cursor: pointer; text-decoration: underline; font-weight: 800; }
        h1 .tit-mod-alq { color: #1e7e34; }
        h1 .tit-mod-venta { color: #c82333; }
        h1 .tit-mod.dim { opacity: 0.42; text-decoration: none; font-weight: 600; }
        h1 .tit-sep { color: #555; text-decoration: none; font-weight: 600; margin: 0 2px; }
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
        .destaque-visual {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
            align-items: stretch;
            margin-bottom: 18px;
        }
        @media (max-width: 720px) { .destaque-visual { grid-template-columns: 1fr; } }
        .destaque-visual .panel-foto, .destaque-visual .panel-mapa {
            border: 1px solid #dee2e6;
            border-radius: 8px;
            overflow: hidden;
            background: #fafbfc;
        }
        .destaque-visual .panel-foto { display: flex; flex-direction: column; min-height: 220px; }
        .destaque-visual .panel-foto .etiqueta {
            font-size: 11px; font-weight: bold; color: #495057; padding: 8px 10px; background: #e9ecef; border-bottom: 1px solid #dee2e6;
        }
        .destaque-visual .panel-foto .img-wrap {
            flex: 1; display: flex; align-items: center; justify-content: center;
            min-height: 200px; background: #e9ecef;
        }
        .destaque-visual .panel-foto img {
            max-width: 100%; max-height: 280px; width: auto; height: auto; object-fit: contain; display: block;
        }
        .destaque-visual .sin-foto-caja {
            padding: 24px; text-align: center; color: #888; font-size: 12px;
        }
        .destaque-visual .panel-mapa .etiqueta {
            font-size: 11px; font-weight: bold; color: #495057; padding: 8px 10px; background: #e9ecef; border-bottom: 1px solid #dee2e6;
        }
        .destaque-visual .panel-mapa .mapa-wrap { height: 260px; margin: 0; border: none; border-radius: 0; }
        .destaque-visual .panel-mapa .links-mapa { padding: 8px 10px; background: #fff; }
        .destaque-visual .panel-mapa .coords-mini { font-size: 10px; color: #666; padding: 0 10px 8px; }
        .galeria-mini { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 8px; }
        .galeria-mini a { display: block; width: 72px; height: 72px; border-radius: 4px; overflow: hidden; border: 1px solid #ddd; }
        .galeria-mini img { width: 100%; height: 100%; object-fit: cover; }
        .bloque-cond { display: none; }
        .bloque-cond.activo { display: block; }
        .tabla-hist { width: 100%; border-collapse: collapse; font-size: 11px; margin-top: 8px; }
        .tabla-hist th, .tabla-hist td { border: 1px solid #dee2e6; padding: 6px 8px; text-align: left; vertical-align: top; }
        .tabla-hist th { background: #e9ecef; }
        .btn-borrar-hist {
            background: #dc3545; color: #fff; border: none; border-radius: 4px;
            width: 28px; height: 28px; line-height: 1; font-size: 18px; font-weight: bold;
            cursor: pointer; padding: 0; vertical-align: middle;
        }
        .btn-borrar-hist:hover { background: #c82333; }
        .reg-visita { background: #f8f9fa; border: 1px solid #dee2e6; border-radius: 8px; padding: 12px 14px; margin-bottom: 16px; }
        .reg-visita h3 { margin: 0 0 10px; font-size: 12px; color: #333; }
        .reg-visita .grid2 { margin-top: 0; }
    </style>
</head>
<body>

<div class="wrap">
    <h1>
        <span class="tit-prefijo">Orden de </span><span id="tit-alq" class="tit-mod tit-mod-alq<?= $modoActual === 'alquiler' ? '' : ' dim' ?>" role="button" tabindex="0" title="Modo alquiler">alquiler</span><span class="tit-sep">/</span><span id="tit-venta" class="tit-mod tit-mod-venta<?= $modoActual === 'venta' ? '' : ' dim' ?>" role="button" tabindex="0" title="Modo venta">venta</span>
    </h1>
    <p class="sub">Elegí alquiler o venta en el título para adaptar la ficha. Los datos se usan como referencia para contrato u oferta. Guardá antes de imprimir.</p>

    <?php if ($mensaje !== ''): ?>
        <div class="msg <?= (strpos($mensaje, 'No se pudo') !== false) ? 'err' : 'ok' ?>"><?= h($mensaje) ?></div>
    <?php endif; ?>

    <div class="destaque-visual">
        <div class="panel-foto">
            <div class="etiqueta">Foto de la propiedad</div>
            <?php if ($primeraFotoUrl !== null): ?>
                <div class="img-wrap">
                    <a href="<?= h($primeraFotoUrl) ?>" target="_blank" rel="noopener" title="Abrir foto">
                        <img src="<?= h($primeraFotoUrl) ?>" alt="Primera foto de la propiedad">
                    </a>
                </div>
            <?php else: ?>
                <div class="sin-foto-caja">No hay fotos cargadas. Podés agregarlas desde la edición de la propiedad.</div>
            <?php endif; ?>
        </div>
        <div class="panel-mapa">
            <div class="etiqueta">Ubicación (mapa guardado)</div>
            <?php if ($tieneMapa): ?>
                <div class="mapa-wrap">
                    <iframe title="Mapa" loading="lazy" src="<?= h($osm_embed) ?>"></iframe>
                </div>
                <div class="links-mapa">
                    <a href="<?= h($gmaps_link) ?>" target="_blank" rel="noopener">Abrir en Google Maps</a>
                    <?php if (!empty($prop['mapa_enlace'])): ?>
                        <a href="<?= h($prop['mapa_enlace']) ?>" target="_blank" rel="noopener">Enlace guardado</a>
                    <?php endif; ?>
                </div>
                <p class="coords-mini">Encuadre según zoom <?= (int) $zoomMapa ?> (tomado del enlace guardado o valor por defecto).</p>
            <?php else: ?>
                <div class="sin-foto-caja" style="min-height:200px;display:flex;align-items:center;justify-content:center;flex-direction:column;gap:8px;">
                    <span>No hay coordenadas en mapa.</span>
                    <?php if (!empty($prop['mapa_enlace'])): ?>
                        <a href="<?= h($prop['mapa_enlace']) ?>" target="_blank" rel="noopener" style="font-weight:bold;">Abrir ubicación (enlace guardado)</a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="bloque-info">
        <div><strong>Propiedad:</strong> <?= h($prop['propiedad'] ?? '') ?></div>
        <?php if (!empty($prop['consorcio'])): ?><div><strong>Consorcio:</strong> <?= h($prop['consorcio']) ?></div><?php endif; ?>
        <?php if (!empty($prop['padron'])): ?><div><strong>Padrón:</strong> <?= h($prop['padron']) ?></div><?php endif; ?>
        <?php if (!empty($prop['ciudad'])): ?><div><strong>Ciudad:</strong> <?= h($prop['ciudad']) ?></div><?php endif; ?>
        <div style="margin-top:8px;"><strong>Detalle técnico:</strong></div>
        <div style="white-space:pre-wrap; font-size:12px; color:#333;"><?= h($prop['detalle'] ?? '') !== '' ? h($prop['detalle']) : '—' ?></div>
    </div>

    <?php if (count($fotosRestantes) > 0): ?>
    <h2 style="margin-top:8px;">Más fotos</h2>
    <div class="galeria-mini">
        <?php foreach ($fotosRestantes as $rel):
            $rel = str_replace(['..', '\\'], '', $rel);
            if ($rel === '' || strpos($rel, 'uploads/propiedades/') !== 0) {
                continue;
            }
            $url = propiedades_url_publica($rel);
            $src = h($url);
        ?>
        <a href="<?= $src ?>" target="_blank" rel="noopener" title="Ver foto"><img src="<?= $src ?>" alt=""></a>
        <?php endforeach; ?>
    </div>
    <p style="margin:10px 0 0; font-size:12px;"><a href="ver_propiedad.php?id=<?= $id ?>#fotos" target="_blank">Ver galería completa</a></p>
    <?php elseif ($primeraFotoUrl !== null && count($fotos) === 1): ?>
    <p style="margin:0 0 12px; font-size:12px;"><a href="ver_propiedad.php?id=<?= $id ?>#fotos" target="_blank">Abrir ficha de fotos</a></p>
    <?php elseif ($primeraFotoUrl === null): ?>
    <p style="margin:0 0 12px; font-size:12px;"><a href="ver_propiedad.php?id=<?= $id ?>" target="_blank">Ficha de la propiedad</a></p>
    <?php endif; ?>

    <div class="reg-visita">
        <h3>Registrar visita (cliente que vio la ficha)</h3>
        <form method="post" action="orden_alquiler.php">
            <input type="hidden" name="propiedad_id" value="<?= (int) $id ?>">
            <input type="hidden" name="registrar_visita" value="1">
            <div class="grid2">
                <div>
                    <label for="cliente_visita">Cliente o interesado</label>
                    <input type="text" name="cliente_visita" id="cliente_visita" placeholder="Nombre del interesado" autocomplete="off">
                </div>
                <div>
                    <label for="modo_visita">Interés</label>
                    <select name="modo_visita" id="modo_visita" style="width:100%; padding:8px 10px; border:1px solid #ced4da; border-radius:4px; font-size:13px;">
                        <option value="alquiler"<?= $modoActual === 'alquiler' ? ' selected' : '' ?>>Alquiler</option>
                        <option value="venta"<?= $modoActual === 'venta' ? ' selected' : '' ?>>Venta</option>
                    </select>
                </div>
            </div>
            <div style="margin-top:12px;">
                <label for="nota_visita">Nota (opcional)</label>
                <textarea name="nota_visita" id="nota_visita" placeholder="Observaciones de la visita" style="min-height:48px;"></textarea>
            </div>
            <button type="submit" class="btn btn-print" style="margin-top:12px;">Registrar en historial</button>
        </form>
        <p class="nota" style="margin-bottom:0;">Quién mostró queda registrado con el usuario en sesión (<?= h($mostrador !== '' ? $mostrador : '—') ?>).</p>
    </div>

    <?php
    $histRaw = isset($datos['historial']) && is_array($datos['historial']) ? $datos['historial'] : [];
    $nHist = count($histRaw);
    $histRows = $nHist > 0 ? array_reverse($histRaw, false) : [];
    ?>
    <h2 style="margin-top:8px;">Histórico de visitas y cambios</h2>
    <p class="nota">Incluye guardados del formulario, cambios entre alquiler y venta, y visitas registradas. Orden: más reciente arriba. Usá × para borrar una línea.</p>
    <div style="overflow-x:auto;">
        <table class="tabla-hist">
            <thead>
                <tr>
                    <th>Fecha</th>
                    <th>Tipo</th>
                    <th>Modo</th>
                    <th>Cliente / interesado</th>
                    <th>Mostró</th>
                    <th>Nota</th>
                    <th style="width:40px;" aria-label="Eliminar"></th>
                </tr>
            </thead>
            <tbody id="historial-tbody">
                <?php if ($histRows === []): ?>
                    <tr id="historial-vacio"><td colspan="7" class="sin-dato">Sin eventos aún.</td></tr>
                <?php else: ?>
                    <?php foreach ($histRows as $displayIdx => $ev):
                        if (!is_array($ev)) {
                            continue;
                        }
                        $realIdx = $nHist - 1 - (int) $displayIdx;
                        $fRaw = trim((string) ($ev['fecha'] ?? ''));
                        $tsEv = $fRaw !== '' ? strtotime($fRaw) : false;
                        $fTxt = ($tsEv !== false) ? date('d/m/Y H:i', $tsEv) : '—';
                        $modoEv = (($ev['modo'] ?? '') === 'venta') ? 'Venta' : 'Alquiler';
                    ?>
                    <tr>
                        <td><?= h($fTxt) ?></td>
                        <td><?= h(oa_label_tipo_hist($ev['tipo'] ?? '')) ?></td>
                        <td><?= h($modoEv) ?></td>
                        <td><?= h(trim((string) ($ev['cliente'] ?? '')) !== '' ? (string) $ev['cliente'] : '—') ?></td>
                        <td><?= h(trim((string) ($ev['mostrador'] ?? '')) !== '' ? (string) $ev['mostrador'] : '—') ?></td>
                        <td><?= h(trim((string) ($ev['nota'] ?? '')) !== '' ? (string) $ev['nota'] : '—') ?></td>
                        <td style="text-align:center;vertical-align:middle;">
                            <form method="post" action="orden_alquiler.php" style="margin:0;display:inline;" onsubmit="return confirm('¿Eliminar este registro del historial?');">
                                <input type="hidden" name="propiedad_id" value="<?= (int) $id ?>">
                                <input type="hidden" name="borrar_historial_registro" value="1">
                                <input type="hidden" name="historial_real_idx" value="<?= (int) $realIdx ?>">
                                <button type="submit" class="btn-borrar-hist" title="Eliminar registro">×</button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <form method="post" action="orden_alquiler.php">
        <input type="hidden" name="propiedad_id" value="<?= (int) $id ?>">
        <input type="hidden" name="guardar_orden_alquiler" value="1">
        <input type="hidden" name="modo_operacion" id="modo_operacion" value="<?= h($modoActual) ?>">

        <h2>Condiciones solicitadas</h2>
        <div id="bloque-cond-alq" class="bloque-cond<?= $modoActual === 'alquiler' ? ' activo' : '' ?>">
            <div class="grid2">
                <div>
                    <label for="precio_alquiler_pedido">Precio de alquiler pedido</label>
                    <input type="text" name="precio_alquiler_pedido" id="precio_alquiler_pedido" value="<?= h($datos['precio_alquiler_pedido']) ?>" placeholder="Ej: 150000 o 150.000,00" autocomplete="off">
                </div>
                <div></div>
            </div>
            <div style="margin-top:12px;">
                <label for="actualizacion">Actualización (índice / forma de ajuste)</label>
                <textarea name="actualizacion" id="actualizacion" placeholder="Ej: IPC trimestral, porcentaje anual, etc."><?= h($datos['actualizacion']) ?></textarea>
            </div>
        </div>
        <div id="bloque-cond-venta" class="bloque-cond<?= $modoActual === 'venta' ? ' activo' : '' ?>">
            <div class="grid2">
                <div>
                    <label for="precio_venta_pedido">Precio de venta pedido</label>
                    <input type="text" name="precio_venta_pedido" id="precio_venta_pedido" value="<?= h($datos['precio_venta_pedido'] ?? '') ?>" placeholder="Ej: USD o pesos" autocomplete="off">
                </div>
                <div></div>
            </div>
            <div style="margin-top:12px;">
                <label for="condiciones_venta">Condiciones de venta / forma de pago</label>
                <textarea name="condiciones_venta" id="condiciones_venta" placeholder="Ej: contado, financiación, escritura, etc."><?= h($datos['condiciones_venta'] ?? '') ?></textarea>
            </div>
        </div>
        <div class="grid2" style="margin-top:12px;">
            <div>
                <label for="monto_garantia" id="lbl-monto-garantia"><?= $modoActual === 'venta' ? 'Seña / reserva (opc.)' : 'Monto garantía' ?></label>
                <input type="text" name="monto_garantia" id="monto_garantia" value="<?= h($datos['monto_garantia']) ?>" placeholder="Ej: 300000" autocomplete="off">
            </div>
            <div></div>
        </div>

        <h2 id="h2-solicitante"><?= $modoActual === 'venta' ? 'Comprador / ofertante (previsto)' : 'Solicitante (locatario previsto)' ?></h2>
        <div class="persona-box">
            <h3>Datos para el contrato</h3>
            <div class="grid2">
                <div><label for="solicitante_nombre">Apellido y nombre</label><input type="text" name="solicitante_nombre" id="solicitante_nombre" value="<?= h($s['nombre']) ?>" autocomplete="off"></div>
                <div><label for="solicitante_dni">DNI</label><input type="text" name="solicitante_dni" id="solicitante_dni" value="<?= h($s['dni']) ?>" autocomplete="off"></div>
                <div><label for="solicitante_cuit">CUIT</label><input type="text" name="solicitante_cuit" id="solicitante_cuit" value="<?= h($s['cuit']) ?>" autocomplete="off"></div>
                <div><label for="solicitante_domicilio">Domicilio</label><input type="text" name="solicitante_domicilio" id="solicitante_domicilio" value="<?= h($s['domicilio']) ?>" autocomplete="off"></div>
                <div><label for="solicitante_email">Mail</label><input type="email" name="solicitante_email" id="solicitante_email" value="<?= h($s['email']) ?>" autocomplete="off"></div>
                <div style="grid-column: 1 / -1;"><label for="solicitante_celular">Celular</label><input type="text" name="solicitante_celular" id="solicitante_celular" value="<?= h($s['celular']) ?>" autocomplete="off"></div>
            </div>
        </div>

        <h2 id="h2-g1"><?= $modoActual === 'venta' ? 'Referencia / codeudor 1 (opc.)' : 'Garante 1' ?></h2>
        <div class="persona-box">
            <div class="grid2">
                <div><label for="garante1_nombre">Apellido y nombre</label><input type="text" name="garante1_nombre" id="garante1_nombre" value="<?= h($g1['nombre']) ?>" autocomplete="off"></div>
                <div><label for="garante1_dni">DNI</label><input type="text" name="garante1_dni" id="garante1_dni" value="<?= h($g1['dni']) ?>" autocomplete="off"></div>
                <div><label for="garante1_cuit">CUIT</label><input type="text" name="garante1_cuit" id="garante1_cuit" value="<?= h($g1['cuit']) ?>" autocomplete="off"></div>
                <div><label for="garante1_domicilio">Domicilio</label><input type="text" name="garante1_domicilio" id="garante1_domicilio" value="<?= h($g1['domicilio']) ?>" autocomplete="off"></div>
                <div><label for="garante1_email">Mail</label><input type="email" name="garante1_email" id="garante1_email" value="<?= h($g1['email']) ?>" autocomplete="off"></div>
                <div style="grid-column: 1 / -1;"><label for="garante1_celular">Celular</label><input type="text" name="garante1_celular" id="garante1_celular" value="<?= h($g1['celular']) ?>" autocomplete="off"></div>
            </div>
        </div>

        <h2 id="h2-g2"><?= $modoActual === 'venta' ? 'Referencia / codeudor 2 (opc.)' : 'Garante 2' ?></h2>
        <div class="persona-box">
            <div class="grid2">
                <div><label for="garante2_nombre">Apellido y nombre</label><input type="text" name="garante2_nombre" id="garante2_nombre" value="<?= h($g2['nombre']) ?>" autocomplete="off"></div>
                <div><label for="garante2_dni">DNI</label><input type="text" name="garante2_dni" id="garante2_dni" value="<?= h($g2['dni']) ?>" autocomplete="off"></div>
                <div><label for="garante2_cuit">CUIT</label><input type="text" name="garante2_cuit" id="garante2_cuit" value="<?= h($g2['cuit']) ?>" autocomplete="off"></div>
                <div><label for="garante2_domicilio">Domicilio</label><input type="text" name="garante2_domicilio" id="garante2_domicilio" value="<?= h($g2['domicilio']) ?>" autocomplete="off"></div>
                <div><label for="garante2_email">Mail</label><input type="email" name="garante2_email" id="garante2_email" value="<?= h($g2['email']) ?>" autocomplete="off"></div>
                <div style="grid-column: 1 / -1;"><label for="garante2_celular">Celular</label><input type="text" name="garante2_celular" id="garante2_celular" value="<?= h($g2['celular']) ?>" autocomplete="off"></div>
            </div>
        </div>

        <p class="nota">Al generar el contrato desde el sistema, podrán tomarse estos datos si el flujo lo incorpora. Guardá los cambios con el botón inferior.</p>

        <div class="acciones">
            <button type="submit" class="btn btn-guardar">Guardar datos</button>
            <a href="imprimir_orden_alquiler.php?id=<?= (int) $id ?>" target="_blank" class="btn btn-print" id="link-imprimir-oa">Vista imprimible</a>
            <a href="propiedades.php" class="btn btn-volver">← Volver a propiedades</a>
        </div>
    </form>
</div>

<script>
(function () {
    var id = <?= (int) $id ?>;
    var inpModo = document.getElementById('modo_operacion');
    var titAlq = document.getElementById('tit-alq');
    var titVen = document.getElementById('tit-venta');
    var bAlq = document.getElementById('bloque-cond-alq');
    var bVen = document.getElementById('bloque-cond-venta');
    var h2Sol = document.getElementById('h2-solicitante');
    var h2g1 = document.getElementById('h2-g1');
    var h2g2 = document.getElementById('h2-g2');
    var lblMonto = document.getElementById('lbl-monto-garantia');
    var selVisita = document.getElementById('modo_visita');
    var linkImp = document.getElementById('link-imprimir-oa');
    var tbodyHist = document.getElementById('historial-tbody');

    function textoTipoHist(t) {
        var m = { guardado: 'Guardado', cambio_modo: 'Cambio modo', visita: 'Visita' };
        return m[t] || t || '—';
    }

    function prependHistorial(fila) {
        if (!tbodyHist || !fila) return;
        var vac = document.getElementById('historial-vacio');
        if (vac) vac.remove();
        var tr = document.createElement('tr');
        var c = function (txt) {
            var td = document.createElement('td');
            td.textContent = txt;
            tr.appendChild(td);
        };
        c(fila.fecha_txt || '—');
        c(fila.tipo_txt || textoTipoHist(fila.tipo));
        c(fila.modo === 'venta' ? 'Venta' : 'Alquiler');
        c(fila.cliente || '—');
        c(fila.mostrador || '—');
        c(fila.nota || '—');
        var tdDel = document.createElement('td');
        tdDel.style.textAlign = 'center';
        tdDel.style.verticalAlign = 'middle';
        if (typeof fila.historial_real_idx === 'number') {
            var form = document.createElement('form');
            form.method = 'post';
            form.action = 'orden_alquiler.php';
            form.style.margin = '0';
            form.style.display = 'inline';
            form.onsubmit = function () { return window.confirm('¿Eliminar este registro del historial?'); };
            var inpPid = document.createElement('input');
            inpPid.type = 'hidden';
            inpPid.name = 'propiedad_id';
            inpPid.value = String(id);
            var inpBr = document.createElement('input');
            inpBr.type = 'hidden';
            inpBr.name = 'borrar_historial_registro';
            inpBr.value = '1';
            var inpIx = document.createElement('input');
            inpIx.type = 'hidden';
            inpIx.name = 'historial_real_idx';
            inpIx.value = String(fila.historial_real_idx);
            var btn = document.createElement('button');
            btn.type = 'submit';
            btn.className = 'btn-borrar-hist';
            btn.title = 'Eliminar registro';
            btn.appendChild(document.createTextNode('\u00D7'));
            form.appendChild(inpPid);
            form.appendChild(inpBr);
            form.appendChild(inpIx);
            form.appendChild(btn);
            tdDel.appendChild(form);
        }
        tr.appendChild(tdDel);
        tbodyHist.insertBefore(tr, tbodyHist.firstChild);
    }

    function aplicarModoUI(modo, conServidor) {
        var esV = (modo === 'venta');
        if (inpModo) inpModo.value = esV ? 'venta' : 'alquiler';
        if (bAlq) bAlq.classList.toggle('activo', !esV);
        if (bVen) bVen.classList.toggle('activo', esV);
        if (titAlq) titAlq.classList.toggle('dim', esV);
        if (titVen) titVen.classList.toggle('dim', !esV);
        if (h2Sol) h2Sol.textContent = esV ? 'Comprador / ofertante (previsto)' : 'Solicitante (locatario previsto)';
        if (h2g1) h2g1.textContent = esV ? 'Referencia / codeudor 1 (opc.)' : 'Garante 1';
        if (h2g2) h2g2.textContent = esV ? 'Referencia / codeudor 2 (opc.)' : 'Garante 2';
        if (lblMonto) lblMonto.textContent = esV ? 'Seña / reserva (opc.)' : 'Monto garantía';
        if (selVisita) selVisita.value = esV ? 'venta' : 'alquiler';
        if (linkImp) linkImp.href = 'imprimir_orden_alquiler.php?id=' + id;

        if (conServidor) {
            var fd = new FormData();
            fd.append('ajax_cambio_modo', '1');
            fd.append('propiedad_id', String(id));
            fd.append('modo_operacion', esV ? 'venta' : 'alquiler');
            fetch('orden_alquiler.php?id=' + id, { method: 'POST', body: fd, credentials: 'same-origin' })
                .then(function (r) { return r.json(); })
                .then(function (j) {
                    if (j && j.historial_agregado) prependHistorial(j.historial_agregado);
                })
                .catch(function () {});
        }
    }

    function bindTit(el, modo) {
        if (!el) return;
        el.addEventListener('click', function () { aplicarModoUI(modo, true); });
        el.addEventListener('keydown', function (ev) {
            if (ev.key === 'Enter' || ev.key === ' ') {
                ev.preventDefault();
                aplicarModoUI(modo, true);
            }
        });
    }
    bindTit(titAlq, 'alquiler');
    bindTit(titVen, 'venta');
})();
</script>

<?php include 'timeout_sesion_inc.php'; ?>
</body>
</html>
