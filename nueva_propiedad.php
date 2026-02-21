<?php
include 'db.php';
include 'verificar_sesion.php';
if (isset($_SESSION['acceso_nivel']) && $_SESSION['acceso_nivel'] < 2) {
    header('Location: index.php?msg=solo_lectura');
    exit;
}
$ultima_ciudad = '';
$ultimo_consorcio = '';
$ultimo_propietario_id = '';
$ultimo_propietario_nombre = '';
$r = @mysqli_query($conexion, "SELECT p.ciudad, p.consorcio, p.propietario_id, u.apellido AS propietario_nombre FROM propiedades p LEFT JOIN usuarios u ON p.propietario_id = u.id ORDER BY p.propiedad_id DESC LIMIT 1");
if ($r && $row = mysqli_fetch_assoc($r)) {
    $ultima_ciudad   = htmlspecialchars($row['ciudad'] ?? '');
    $ultimo_consorcio = htmlspecialchars($row['consorcio'] ?? '');
    if (!empty($row['propietario_id'])) {
        $ultimo_propietario_id = (int)$row['propietario_id'];
        $ultimo_propietario_nombre = htmlspecialchars($row['propietario_nombre'] ?? '');
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Nueva Propiedad - HHH</title>
    <style>
        body { font-family: 'Segoe UI', sans-serif; background: #f0f2f5; padding: 10px; margin: 0; }
        .card { background: white; padding: 15px; border-radius: 8px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); max-width: 500px; margin: auto; }
        h2 { color: #007bff; margin-top: 0; font-size: 14px; text-transform: uppercase; border-bottom: 2px solid #007bff; padding-bottom: 6px; margin-bottom: 10px; }
        label { display: block; margin: 6px 0 3px; font-size: 10px; font-weight: bold; color: #555; text-transform: uppercase; }
        input, textarea { width: 100%; padding: 5px; box-sizing: border-box; border: 1px solid #ccc; border-radius: 4px; text-transform: uppercase; font-size: 11px; }
        textarea { min-height: 50px; resize: vertical; }
        .btns { margin-top: 12px; display: flex; gap: 10px; }
        button, .btn-cancelar { flex: 1; padding: 7px; border: none; border-radius: 4px; cursor: pointer; font-weight: bold; text-align: center; text-decoration: none; font-size: 11px; }
        button { background: #28a745; color: white; }
        .btn-cancelar { background: #ffc107; color: #333; display: block; }
        .btn-cancelar:hover { background: #e0a800; }
        button:hover { background: #218838; }
        .btn-detalle { background: #17a2b8; color: white; padding: 4px 8px; font-size: 10px; margin-right: 6px; margin-bottom: 4px; }
        .btn-detalle:hover { background: #138496; }
        .carga-rapida { margin-bottom: 6px; }
        .buscador-contenedor { position: relative; }
        .sugerencias { position: absolute; width: 100%; background: white; border: 1px solid #ddd; z-index: 100; max-height: 120px; overflow-y: auto; display: none; box-shadow: 0 2px 8px rgba(0,0,0,0.15); }
        .sugerencia-item { padding: 6px; cursor: pointer; font-size: 11px; border-bottom: 1px solid #eee; text-transform: uppercase; }
        .sugerencia-item:hover { background: #e7f3ff; }
        .fila-doble { display: flex; gap: 10px; }
        .fila-doble > div { flex: 0 0 100px; }
        .fila-doble label { margin: 6px 0 3px; }
        .fila-doble input { width: 100%; }
    </style>
</head>
<body>

<div class="card">
    <h2>Nueva propiedad</h2>
    <?php if (isset($_GET['error']) && ($_GET['error'] === '1' || $_GET['error'] === 'padron_duplicado')): ?>
    <div style="background:#f8d7da; color:#721c24; padding:8px; border-radius:4px; margin-bottom:10px; font-size:11px;">Falta dato o corregir.</div>
    <?php endif; ?>
    <form class="form-nav-enter" action="guardar_propiedad.php" method="POST" onsubmit="return validarPropietario()">
        <label>Propiedad *</label>
        <input type="text" name="propiedad" required autofocus placeholder="Ej: DEPTO 1A">

        <label>Ciudad</label>
        <input type="text" name="ciudad" value="<?= $ultima_ciudad ?>" placeholder="Ej: S. M. DE TUCUMAN">

        <label>Consorcio</label>
        <input type="text" name="consorcio" value="<?= $ultimo_consorcio ?>" placeholder="Ej: 430 o 101">

        <div class="buscador-contenedor">
            <label>Propietario *</label>
            <input type="text" id="bus_propietario" placeholder="BUSCAR USUARIO..." onkeyup="buscarPropietario(this.value)" autocomplete="off" value="<?= $ultimo_propietario_nombre ?>">
            <input type="hidden" name="propietario_id" id="propietario_id" value="<?= $ultimo_propietario_id ?>">
            <div id="sug_propietario" class="sugerencias"></div>
        </div>
        
        <label>%</label>
        <input type="text" name="porcentaje" inputmode="decimal" placeholder="3,505 o 3.505" style="text-transform:none; max-width:120px;" pattern="[0-9]+[.,]?[0-9]*" title="Ej: 3,505 o 3.505">
        
        <label>Padrón</label>
        <input type="text" name="padron" placeholder="Ej: 12345">
        
        <label>Carga rápida detalle</label>
        <div class="carga-rapida">
            <button type="button" class="btn-detalle" onclick="document.getElementById('detalle').value='Un dormitorio con placar completo, baño completo con grifería FV y loza Ferrun revestido con cerámico en perfectas condiciones, cocina revestida en cerámico, con alacena en buen estado de conservación, bacha de acero inoxidable con monocomando, con cocina y lavadero marca Ferrun, todo recién pintado, piso de cerámico, todo en perfecto estado de conservación.'">1 dormitorio</button>
            <button type="button" class="btn-detalle" onclick="document.getElementById('detalle').value='Dos dormitorios con placares completo, baño completo con grifería FV y loza Ferrun revestido con cerámico en perfectas condiciones, cocina revestida en cerámico, con bacha de acero inoxidable y monocomando fv, con alacena en buen estado de conservación, con cocina y lavadero marca Ferrun y grifería FV, con monocomando, toilette completo con grifería FV y loza Ferrun, todo recién pintado, piso de cerámico, todo en perfecto estado de conservación.'">2 dormitorios</button>
            <button type="button" class="btn-detalle" onclick="document.getElementById('detalle').value='Tres dormitorios con placares completo, baño completo con grifería FV y loza Ferrun revestido con cerámico en perfectas condiciones, cocina revestida en cerámico, con bacha de acero inoxidable y monocomando fv, con alacena en buen estado de conservación, con cocina y lavadero marca Ferrun y grifería FV, con monocomando, toilette completo con grifería FV y loza Ferrun, todo recién pintado, piso de cerámico, todo en perfecto estado de conservación.'">3 dormitorios</button>
        </div>
        
        <label>Detalle técnico</label>
        <textarea id="detalle" name="detalle" placeholder="Descripción del inmueble"></textarea>
        
        <div class="btns">
            <button type="submit">GUARDAR</button>
            <a href="propiedades.php" class="btn-cancelar">VOLVER</a>
        </div>
    </form>
</div>

<script>
function buscarPropietario(q) {
    var lista = document.getElementById('sug_propietario');
    if (q.length < 2) { lista.style.display = 'none'; return; }
    fetch('buscar_personas.php?q=' + encodeURIComponent(q)).then(function(r) { return r.json(); }).then(function(data) {
        lista.innerHTML = '';
        if (data.length > 0) {
            lista.style.display = 'block';
            data.forEach(function(p) {
                var div = document.createElement('div');
                div.className = 'sugerencia-item';
                div.innerText = p.apellido;
                div.onclick = function() {
                    document.getElementById('propietario_id').value = p.id;
                    document.getElementById('bus_propietario').value = p.apellido;
                    lista.style.display = 'none';
                };
                lista.appendChild(div);
            });
        } else {
            lista.style.display = 'none';
        }
    });
}
function validarPropietario() {
    if (!document.getElementById('propietario_id').value) {
        alert('Seleccione un propietario (busque y elija de la lista).');
        document.getElementById('bus_propietario').focus();
        return false;
    }
    return true;
}
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        history.back();
        return;
    }
    if (e.key === 'Enter' && e.target.closest('form')) {
        if (e.target.tagName === 'TEXTAREA') return;
        if (e.target.type === 'button' || e.target.type === 'submit') return;
        e.preventDefault();
        var form = e.target.closest('form');
        var campos = form.querySelectorAll('input:not([type="hidden"]), textarea, button[type="submit"]');
        for (var i = 0; i < campos.length; i++) {
            if (campos[i] === e.target && i < campos.length - 1) {
                campos[i + 1].focus();
                return;
            }
        }
    }
});
</script>
<?php include 'timeout_sesion_inc.php'; ?>
</body>
</html>
