<?php
include 'db.php';
include 'verificar_sesion.php';
require_once __DIR__ . '/config_clave_borrado.php';
require_once __DIR__ . '/includes_propiedad_fotos_mapa.php';
propiedades_asegurar_columnas($conexion);
if (isset($_SESSION['acceso_nivel']) && $_SESSION['acceso_nivel'] < 2) {
    header('Location: index.php?msg=solo_lectura');
    exit;
}
if (!isset($_GET['id'])) {
    header('Location: propiedades.php');
    exit;
}

$id = (int)$_GET['id'];
$res = mysqli_query($conexion, "SELECT * FROM propiedades WHERE propiedad_id = $id LIMIT 1");
$prop = mysqli_fetch_assoc($res);

if (!$prop) {
    echo "Propiedad no encontrada.";
    exit;
}
require_once __DIR__ . '/helpers_tenant_inmobiliaria.php';
tenant_inmob_asegurar_esquema($conexion);
if (!tenant_inmob_propiedad_id_visible($conexion, $id)) {
    header('Location: propiedades.php?msg=sin_permiso');
    exit;
}
$fotos_existentes = propiedades_fotos_unificadas($id, $prop['fotos_json'] ?? null);
$diskMap = propiedades_leer_mapa_disco($id);
$val_lat = isset($prop['mapa_lat']) && $prop['mapa_lat'] !== null && $prop['mapa_lat'] !== '' ? (string)$prop['mapa_lat'] : '';
$val_lng = isset($prop['mapa_lng']) && $prop['mapa_lng'] !== null && $prop['mapa_lng'] !== '' ? (string)$prop['mapa_lng'] : '';
$val_enlace = isset($prop['mapa_enlace']) ? (string)$prop['mapa_enlace'] : '';
if ($val_lat === '' && $val_lng === '' && is_array($diskMap)) {
    if (isset($diskMap['lat'], $diskMap['lng'])) {
        $val_lat = (string)$diskMap['lat'];
        $val_lng = (string)$diskMap['lng'];
    }
    if ($val_enlace === '' && !empty($diskMap['enlace'])) {
        $val_enlace = (string)$diskMap['enlace'];
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars(tenant_inmob_html_title('Editar Propiedad')) ?></title>
    <?php tenant_inmob_echo_head_favicons(); ?>
    <style>
        body { font-family: 'Segoe UI', sans-serif; background: #f0f2f5; padding: 10px; margin: 0; }
        .card { background: white; padding: 15px; border-radius: 8px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); max-width: 500px; margin: auto; }
        h2 { color: #007bff; margin-top: 0; font-size: 14px; text-transform: uppercase; border-bottom: 2px solid #007bff; padding-bottom: 6px; margin-bottom: 10px; }
        label { display: block; margin: 6px 0 3px; font-size: 10px; font-weight: bold; color: #555; text-transform: uppercase; }
        input, textarea { width: 100%; padding: 5px; box-sizing: border-box; border: 1px solid #ccc; border-radius: 4px; text-transform: uppercase; font-size: 11px; }
        textarea { min-height: 50px; resize: vertical; }
        textarea#detalle { text-transform: none; }
        .btns { margin-top: 12px; display: flex; gap: 10px; }
        button, .btn-cancelar { flex: 1; padding: 7px; border: none; border-radius: 4px; cursor: pointer; font-weight: bold; text-align: center; text-decoration: none; font-size: 11px; }
        button { background: #28a745; color: white; }
        .btn-cancelar { background: #ffc107; color: #333; display: block; }
        .btn-cancelar:hover { background: #e0a800; }
        button:hover { background: #218838; }
        .fila-doble { display: flex; gap: 10px; }
        .fila-doble > div { flex: 0 0 100px; }
        .fila-doble label { margin: 6px 0 3px; }
        .fila-doble input { width: 100%; }
        .btn-detalle { background: #17a2b8; color: white; padding: 4px 7px; font-size: 9px; margin: 0; border: none; border-radius: 4px; cursor: pointer; font-weight: bold; line-height: 1.2; }
        .btn-detalle:hover { background: #138496; }
        .carga-rapida { margin-bottom: 6px; display: flex; flex-wrap: wrap; align-items: flex-start; gap: 5px 6px; max-width: 100%; }
        .mapa-caja { background: #f8f9fa; border: 1px solid #dee2e6; border-radius: 6px; padding: 10px; margin-top: 6px; }
        .mapa-caja p { font-size: 9px; color: #666; margin: 0 0 8px; line-height: 1.35; }
        .fila-map-btn { display: flex; gap: 6px; flex-wrap: wrap; align-items: center; margin-top: 6px; }
        .btn-mapa { background: #6c757d; color: white; border: none; padding: 5px 10px; border-radius: 4px; font-size: 10px; cursor: pointer; font-weight: bold; }
        .btn-mapa:hover { background: #5a6268; }
        input[type="file"] { font-size: 10px; text-transform: none; }
        .mini-coord { display: flex; gap: 8px; margin-top: 6px; }
        .mini-coord input { text-transform: none; }
        .galeria-mini { display: flex; flex-wrap: wrap; gap: 8px; margin: 8px 0; }
        .galeria-mini img { width: 72px; height: 72px; object-fit: cover; border-radius: 4px; border: 1px solid #ccc; }
        .link-ver { font-size: 10px; margin-bottom: 8px; display: inline-block; }
    </style>
</head>
<body>

<?php $esNivel3 = isset($_SESSION['acceso_nivel']) && $_SESSION['acceso_nivel'] >= 3; ?>
<div class="card" id="formularioEditar" style="display:<?= $esNivel3 ? 'block' : 'none' ?>;">
    <?php tenant_inmob_echo_header_bar_logo_rectangular('max-height:44px;margin:0 auto 12px;display:block;'); ?>
    <h2>Editar datos de la propiedad</h2>
    <?php if (isset($_GET['error']) && $_GET['error'] === 'migracion'): ?>
    <div style="background:#fff3cd; color:#856404; padding:8px; border-radius:4px; margin-bottom:10px; font-size:11px;">La base de datos del servidor no tiene las columnas para fotos y mapa. Ejecute el SQL de migración con un usuario MySQL con permisos (ver mensaje en «nueva propiedad» o documentación del sistema).</div>
    <?php elseif (isset($_GET['error']) && $_GET['error'] === 'padron_duplicado'): ?>
    <div style="background:#f8d7da; color:#721c24; padding:8px; border-radius:4px; margin-bottom:10px; font-size:11px;">Falta dato o corregir.</div>
    <?php elseif (isset($_GET['error']) && $_GET['error'] === 'padron_largo'): ?>
    <div style="background:#f8d7da; color:#721c24; padding:8px; border-radius:4px; margin-bottom:10px; font-size:11px;">El padrón supera el largo permitido por la base de datos de este servidor. Solicite ampliar la columna PADRON en MySQL.</div>
    <?php endif; ?>
    <?php if (isset($_GET['ok']) && $_GET['ok'] === '1'): ?>
    <div style="background:#d4edda; color:#155724; padding:8px; border-radius:4px; margin-bottom:10px; font-size:11px;">
        Cambios guardados. El detalle y el resto de los datos quedan como los editó.
        <?php if (isset($_GET['fotos_n']) && (int)$_GET['fotos_n'] > 0): ?>
            <strong> Fotos nuevas agregadas: <?= (int)$_GET['fotos_n'] ?>.</strong>
        <?php endif; ?>
    </div>
    <?php endif; ?>
    <?php if (!empty($_GET['fotos_aviso'])): ?>
    <div style="background:#fff3cd; color:#856404; padding:8px; border-radius:4px; margin-bottom:10px; font-size:11px; white-space:pre-wrap;"><?= htmlspecialchars($_GET['fotos_aviso'], ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>
    <a href="ver_propiedad.php?id=<?= (int)$prop['propiedad_id'] ?>" class="link-ver" target="_blank" rel="noopener">Ver fotos y ubicación en pantalla completa</a>
    <form class="form-nav-enter" action="actualizar_propiedad.php" method="POST" enctype="multipart/form-data">
        <input type="hidden" name="propiedad_id" value="<?= $prop['propiedad_id'] ?>">
        
        <label>Propiedad</label>
        <input type="text" name="propiedad" value="<?= htmlspecialchars($prop['propiedad']) ?>" required autofocus>

        <label>Ciudad</label>
        <input type="text" name="ciudad" value="<?= htmlspecialchars($prop['ciudad'] ?? '') ?>" placeholder="Ej: S. M. DE TUCUMAN">
        
        <div class="fila-doble">
            <div>
                <label>Consorcio</label>
                <input type="text" name="consorcio" value="<?= htmlspecialchars($prop['consorcio'] ?? '') ?>">
            </div>
            <div>
                <label>%</label>
                <input type="text" name="porcentaje" inputmode="decimal" value="<?= htmlspecialchars($prop['porcentaje'] ?? '') ?>" placeholder="3,505 o 3.505" style="text-transform:none;" pattern="[0-9]+[.,]?[0-9]*" title="Ej: 3,505 o 3.505">
            </div>
        </div>
        
        <label>Padrón</label>
        <input type="text" name="padron" value="<?= htmlspecialchars($prop['padron'] ?? '') ?>">
        
        <label>Carga rápida detalle</label>
        <div class="carga-rapida">
            <button type="button" class="btn-detalle" onclick="document.getElementById('detalle').value='Un dormitorio con placar completo, baño completo con grifería FV y loza Ferrun revestido con cerámico en perfectas condiciones, cocina revestida en cerámico, con alacena en buen estado de conservación, bacha de acero inoxidable con monocomando, con cocina y lavadero marca Ferrun, todo recién pintado, piso de cerámico, todo en perfecto estado de conservación.'">1 dormitorio</button>
            <button type="button" class="btn-detalle" onclick="document.getElementById('detalle').value='Dos dormitorios con placares completo, baño completo con grifería FV y loza Ferrun revestido con cerámico en perfectas condiciones, cocina revestida en cerámico, con bacha de acero inoxidable y monocomando fv, con alacena en buen estado de conservación, con cocina y lavadero marca Ferrun y grifería FV, con monocomando, toilette completo con grifería FV y loza Ferrun, todo recién pintado, piso de cerámico, todo en perfecto estado de conservación.'">2 dormitorios</button>
            <button type="button" class="btn-detalle" onclick="document.getElementById('detalle').value='Tres dormitorios con placares completo, baño completo con grifería FV y loza Ferrun revestido con cerámico en perfectas condiciones, cocina revestida en cerámico, con bacha de acero inoxidable y monocomando fv, con alacena en buen estado de conservación, con cocina y lavadero marca Ferrun y grifería FV, con monocomando, toilette completo con grifería FV y loza Ferrun, todo recién pintado, piso de cerámico, todo en perfecto estado de conservación.'">3 dormitorios</button>
            <button type="button" class="btn-detalle" onclick="document.getElementById('detalle').value='Monoambiente con placard completo, baño completo con grifería FV y loza Ferrun revestido con cerámico en perfectas condiciones, cocina integrada revestida en cerámico, bacha de acero inoxidable con monocomando, alacena en buen estado, cocina y lavadero marca Ferrun, todo recién pintado, piso de cerámico, todo en perfecto estado de conservación.'">Monoambiente</button>
            <button type="button" class="btn-detalle" onclick="document.getElementById('detalle').value='Oficina o local comercial con baño completo con grifería FV, revestimientos en cerámico en buen estado, instalaciones eléctricas y de iluminación en condiciones de uso, piso de cerámico o similar, frente vidriado o según diseño de la unidad, todo en estado de conservación acorde al uso comercial.'">Oficina / local</button>
            <button type="button" class="btn-detalle" onclick="document.getElementById('detalle').value='Cochera cubierta o descubierta según unidad, con acceso y medidas habituales de consorcio, piso en condiciones de uso, iluminación según instalación existente, cerramiento o portón según corresponda a la unidad funcional, en estado de conservación adecuado.'">Cochera</button>
            <button type="button" class="btn-detalle" onclick="document.getElementById('detalle').value='Consultorio u ambiente profesional con baño completo con grifería FV y loza Ferrun revestido con cerámico, recepción o espera según distribución, instalaciones aptas para actividad profesional, piso de cerámico, pintura en buen estado, todo en condiciones sanitarias y de conservación adecuadas.'">Consultorio</button>
            <button type="button" class="btn-detalle" onclick="document.getElementById('detalle').value='PH o dúplex con escalera interna, dormitorios con placares, baño completo y toilette según distribución, cocina y lavadero con grifería FV y loza Ferrun, revestimientos en cerámico, alacenas en buen estado, todo recién pintado donde corresponda, piso de cerámico, en perfecto estado de conservación general.'">Dúplex / PH</button>
            <button type="button" class="btn-detalle" onclick="document.getElementById('detalle').value='Casa o vivienda en planta con ambientes según distribución, baño completo con grifería FV y loza Ferrun, cocina y lavadero, revestimientos en cerámico, aberturas y cerramientos en buen estado, patio o espacio exterior si corresponde, todo recién pintado según estado, en buen estado de conservación.'">Casa</button>
        </div>
        
        <label>Detalle técnico</label>
        <textarea id="detalle" name="detalle"><?= htmlspecialchars($prop['detalle'] ?? '') ?></textarea>

        <?php if (count($fotos_existentes) > 0): ?>
        <label>Fotos actuales</label>
        <div class="galeria-mini">
            <?php foreach ($fotos_existentes as $rel):
                $rel = str_replace(['..', '\\'], '', $rel);
                if ($rel === '' || strpos($rel, 'uploads/propiedades/') !== 0) {
                    continue;
                }
                $url = propiedades_url_publica($rel);
                $src = htmlspecialchars($url, ENT_QUOTES, 'UTF-8');
            ?>
            <a href="<?= $src ?>" target="_blank" rel="noopener"><img src="<?= $src ?>" alt=""></a>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <label>Agregar más fotos (opcional)</label>
        <input type="file" name="fotos[]" accept="image/jpeg,image/png,image/gif,image/webp" multiple>
        <span style="font-size:9px; color:#666;">Se suman a las existentes. Máx. 5 MB por archivo.</span>

        <label>Ubicación en mapa (Google Maps)</label>
        <div class="mapa-caja">
            <p>Abra <a href="https://www.google.com/maps" target="_blank" rel="noopener">Google Maps</a>, busque el lugar, use <strong>Compartir</strong> y pegue el enlace. Luego pulse <strong>Extraer coordenadas</strong>.</p>
            <input type="text" id="mapa_enlace" name="mapa_enlace" value="<?= htmlspecialchars($val_enlace, ENT_QUOTES, 'UTF-8') ?>" placeholder="Enlace de Google Maps (opcional)" style="text-transform:none;">
            <div class="fila-map-btn">
                <button type="button" class="btn-mapa" onclick="extraerCoordsDeEnlaceMaps()">Extraer coordenadas del enlace</button>
                <button type="button" class="btn-mapa" onclick="window.open('https://www.google.com/maps','_blank')">Abrir Google Maps</button>
            </div>
            <div class="mini-coord">
                <div style="flex:1;">
                    <label style="margin-top:6px;">Latitud</label>
                    <input type="text" id="mapa_lat" name="mapa_lat" value="<?= htmlspecialchars($val_lat) ?>" placeholder="-26.8241" inputmode="decimal" style="text-transform:none;">
                </div>
                <div style="flex:1;">
                    <label style="margin-top:6px;">Longitud</label>
                    <input type="text" id="mapa_lng" name="mapa_lng" value="<?= htmlspecialchars($val_lng) ?>" placeholder="-65.2226" inputmode="decimal" style="text-transform:none;">
                </div>
            </div>
        </div>
        
        <div class="btns">
            <button type="submit">GUARDAR CAMBIOS</button>
            <a href="propiedades.php" class="btn-cancelar">VOLVER</a>
        </div>
    </form>
</div>

<script>
function extraerCoordsDeEnlaceMaps() {
    var raw = (document.getElementById('mapa_enlace').value || '').trim();
    var lat = null, lng = null;
    if (raw) {
        var m = raw.match(/@(-?\d+\.?\d*),(-?\d+\.?\d*)(?:,|\b)/);
        if (m) { lat = parseFloat(m[1]); lng = parseFloat(m[2]); }
        if (lat == null || isNaN(lat)) {
            m = raw.match(/[?&]q=(-?\d+\.?\d*)[+,](-?\d+\.?\d*)/);
            if (m) { lat = parseFloat(m[1]); lng = parseFloat(m[2]); }
        }
        if (lat == null || isNaN(lat)) {
            m = raw.match(/3d(-?\d+\.?\d*)!4d(-?\d+\.?\d*)/);
            if (m) { lat = parseFloat(m[1]); lng = parseFloat(m[2]); }
        }
        if (lat == null || isNaN(lat)) {
            m = raw.match(/ll=(-?\d+\.?\d*),(-?\d+\.?\d*)/);
            if (m) { lat = parseFloat(m[1]); lng = parseFloat(m[2]); }
        }
    }
    if (lat != null && !isNaN(lat) && lng != null && !isNaN(lng)) {
        document.getElementById('mapa_lat').value = String(lat);
        document.getElementById('mapa_lng').value = String(lng);
        alert('Coordenadas detectadas. Revise latitud y longitud antes de guardar.');
    } else {
        alert('No se pudieron leer coordenadas del enlace. Pegue un enlace de compartir de Google Maps o escriba latitud y longitud a mano.');
    }
}
window.addEventListener('DOMContentLoaded', function() {
    var esNivel3 = <?= $esNivel3 ? 'true' : 'false' ?>;
    if (esNivel3) {
        document.getElementById('formularioEditar').style.display = 'block';
        return;
    }
    var clave = prompt("CLAVE DE SEGURIDAD PARA EDITAR PROPIEDAD:");
    if (clave === null) {
        window.location.href = 'propiedades.php';
        return;
    }
    if (clave !== <?= json_encode(obtener_clave_borrado($conexion)) ?>) {
        alert("Clave incorrecta.");
        window.location.href = 'propiedades.php';
        return;
    }
    document.getElementById('formularioEditar').style.display = 'block';
});

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        history.back();
    }
});
</script>
<?php include 'timeout_sesion_inc.php'; ?>
</body>
</html>
