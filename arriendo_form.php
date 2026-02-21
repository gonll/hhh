<?php
include 'db.php';
include 'verificar_sesion.php';
include 'crear_tabla_arriendos.php';

if (($_SESSION['acceso_nivel'] ?? 0) < 2) {
    header('Location: index.php?msg=sin_permiso');
    exit;
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$edicion = $id > 0;
$row = null;
if ($edicion) {
    $res = mysqli_query($conexion, "SELECT * FROM arriendos WHERE id = $id");
    $row = mysqli_fetch_assoc($res);
    if (!$row) {
        header('Location: arriendos.php');
        exit;
    }
}

// Nombres para los buscadores
$nombre_propietario = $nombre_apoderado = $nombre_arrendatario = '';
if ($row) {
    foreach (['propietario_id' => 'nombre_propietario', 'apoderado_id' => 'nombre_apoderado', 'arrendatario_id' => 'nombre_arrendatario'] as $campo => $nombre) {
        $uid = (int)$row[$campo];
        if ($uid) {
            $r = mysqli_query($conexion, "SELECT apellido FROM usuarios WHERE id = $uid");
            if ($r && $u = mysqli_fetch_assoc($r)) {
                $$nombre = $u['apellido'];
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title><?= $edicion ? 'Modificar' : 'Alta' ?> Arriendo - HHH</title>
    <style>
        body { font-family: 'Segoe UI', sans-serif; background: #f0f2f5; margin: 0; padding: 15px; }
        .contenedor { max-width: 98%; width: 98vw; background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); box-sizing: border-box; }
        h2 { margin: 0 0 15px 0; color: #0047AB; font-size: 1.1rem; border-bottom: 2px solid #0047AB; padding-bottom: 6px; }
        .fila-tres { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 15px; margin-bottom: 12px; }
        .fila-dos { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 12px; }
        .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px 20px; }
        .ancho-completo { grid-column: 1 / -1; }
        label { display: block; font-size: 11px; font-weight: bold; color: #444; margin-bottom: 3px; }
        input[type="text"], input[type="number"], input[type="date"], textarea, select { width: 100%; padding: 6px 8px; border: 1px solid #ccc; border-radius: 4px; font-size: 12px; box-sizing: border-box; }
        textarea { min-height: 60px; resize: vertical; }
        .buscador-contenedor { position: relative; }
        .sugerencias { position: absolute; width: 100%; background: #fff; border: 1px solid #ddd; z-index: 100; max-height: 120px; overflow-y: auto; display: none; box-shadow: 0 4px 8px rgba(0,0,0,0.1); }
        .sugerencia-item { padding: 6px 8px; cursor: pointer; font-size: 12px; border-bottom: 1px solid #eee; }
        .sugerencia-item:hover { background: #e7f3ff; }
        .check { display: flex; align-items: center; gap: 8px; }
        .check input { width: auto; }
        .fila-fecha { display: flex; gap: 10px; align-items: flex-end; margin-bottom: 10px; }
        .fila-fecha label { white-space: nowrap; }
        .bloque-dia-mes { display: flex; gap: 6px; align-items: center; }
        .bloque-dia-mes input[type="number"]::-webkit-inner-spin-button,
        .bloque-dia-mes input[type="number"]::-webkit-outer-spin-button { -webkit-appearance: none; margin: 0; }
        .bloque-dia-mes input[type="number"] { -moz-appearance: textfield; }
        .bloque-dia-mes select.campo-mes { appearance: none; -webkit-appearance: none; -moz-appearance: none; }
        .campo-mes { width: 45px; min-width: 45px; }
        .campo-kilos { width: 65px; min-width: 65px; -moz-appearance: textfield; }
        .campo-kilos::-webkit-inner-spin-button, .campo-kilos::-webkit-outer-spin-button { -webkit-appearance: none; margin: 0; }
        .fila-prov-otros input[type="number"]::-webkit-inner-spin-button,
        .fila-prov-otros input[type="number"]::-webkit-outer-spin-button { -webkit-appearance: none; margin: 0; }
        .fila-prov-otros input[type="number"] { -moz-appearance: textfield; }
        .fila-opciones input[type="number"]::-webkit-inner-spin-button,
        .fila-opciones input[type="number"]::-webkit-outer-spin-button { -webkit-appearance: none; margin: 0; }
        .fila-opciones input[type="number"] { -moz-appearance: textfield; }
        .fila-opciones { display: flex; flex-wrap: wrap; gap: 12px 20px; align-items: flex-end; margin-bottom: 12px; }
        .fila-opciones .item { display: flex; flex-direction: column; align-items: flex-start; gap: 2px; }
        .fila-opciones .item.check { flex-direction: row; align-items: center; }
        .fila-opciones .item-venc { min-width: 120px; }
        .item-prov-otros { flex: 1; min-width: 180px; }
        .fila-prov-otros { display: flex; gap: 12px; align-items: flex-end; }
        .fila-prov-otros .item:last-child { margin-left: auto; }
        .fila-opciones .item-venc input { width: 100%; }
        .btns { margin-top: 20px; display: flex; gap: 10px; flex-wrap: wrap; }
        .btn { padding: 8px 18px; border: none; border-radius: 4px; font-weight: bold; cursor: pointer; font-size: 12px; text-decoration: none; display: inline-block; }
        .btn-guardar { background: #28a745; color: white; }
        .btn-cancelar { background: #6c757d; color: white; }
        .btn-cancelar:hover { color: white; background: #5a6268; }
        .modal-aviso-overlay { display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.4); z-index: 9999; align-items: center; justify-content: center; }
        .modal-aviso-overlay.visible { display: flex; }
        .modal-aviso { background: white; padding: 20px; border-radius: 8px; box-shadow: 0 4px 20px rgba(0,0,0,0.25); max-width: 360px; min-width: 280px; }
        .modal-aviso-msg { margin-bottom: 16px; font-size: 13px; color: #333; }
        .modal-aviso-btn { display: block; margin-left: auto; padding: 8px 20px; background: #0047AB; color: white; border: none; border-radius: 4px; cursor: pointer; font-weight: bold; font-size: 12px; }
        .modal-aviso-btn:hover { background: #003d99; }
    </style>
</head>
<body onkeydown="var e=event||window.event;if((e.keyCode||e.which)===27){e.preventDefault();window.location.href='arriendos.php';return false;}">

<div id="modalAviso" class="modal-aviso-overlay" role="dialog" aria-modal="true">
    <div class="modal-aviso">
        <div class="modal-aviso-msg" id="modalAvisoMsg"></div>
        <button type="button" class="modal-aviso-btn" id="modalAvisoCerrar">Cerrar</button>
    </div>
</div>

<div class="contenedor">
    <h2><?= $edicion ? 'Modificar' : 'Alta' ?> Arriendo</h2>

    <form id="formArriendo" class="form-nav-enter" method="post" action="guardar_arriendo.php">
        <?php if ($edicion): ?><input type="hidden" name="id" value="<?= $id ?>"><?php endif; ?>

        <div class="fila-tres">
            <div class="buscador-contenedor">
                <label>Propietario *</label>
                <input type="text" id="bus_propietario" placeholder="Buscar..." value="<?= htmlspecialchars($nombre_propietario) ?>" autocomplete="off">
                <input type="hidden" name="propietario_id" id="propietario_id" value="<?= $edicion ? (int)$row['propietario_id'] : 0 ?>">
                <div id="sug_propietario" class="sugerencias"></div>
            </div>
            <div class="buscador-contenedor">
                <label>Apoderado *</label>
                <input type="text" id="bus_apoderado" placeholder="Buscar..." value="<?= htmlspecialchars($nombre_apoderado) ?>" autocomplete="off">
                <input type="hidden" name="apoderado_id" id="apoderado_id" value="<?= $edicion ? (int)$row['apoderado_id'] : 0 ?>">
                <div id="sug_apoderado" class="sugerencias"></div>
            </div>
            <div class="buscador-contenedor">
                <label>Arrendatario *</label>
                <input type="text" id="bus_arrendatario" placeholder="Buscar..." value="<?= htmlspecialchars($nombre_arrendatario) ?>" autocomplete="off">
                <input type="hidden" name="arrendatario_id" id="arrendatario_id" value="<?= $edicion ? (int)$row['arrendatario_id'] : 0 ?>">
                <div id="sug_arrendatario" class="sugerencias"></div>
            </div>
        </div>

        <div style="margin-bottom:12px;">
            <label>Finca (descripción) *</label>
            <textarea name="descripcion_finca" id="descripcion_finca" rows="3" placeholder="Ej: FINCA 29 por 2 Has"><?= htmlspecialchars($edicion ? ($row['descripcion_finca'] ?? '') : '') ?></textarea>
        </div>

        <div class="fila-fecha">
            <div>
                <label>Fecha cobro 1 *</label>
                <div class="bloque-dia-mes">
                    <input type="number" name="dia_cobro_1" class="nav-enter" min="1" max="31" placeholder="Día" value="<?= $edicion && !empty($row['fecha_cobro_1']) ? (int)date('j', strtotime($row['fecha_cobro_1'])) : '' ?>" style="width:42px;">
                    <select name="mes_cobro_1" class="nav-enter campo-mes">
                        <option value="">--</option>
                        <?php for ($m=1;$m<=12;$m++): $sel = ($edicion && !empty($row['fecha_cobro_1']) && (int)date('n', strtotime($row['fecha_cobro_1']))==$m) ? 'selected' : ''; ?>
                        <option value="<?= $m ?>" <?= $sel ?>><?= str_pad($m,2,'0',STR_PAD_LEFT) ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
            </div>
            <div>
                <label>Kilos 1 *</label>
                <input type="number" name="kilos_fecha_1" id="kilos_fecha_1" class="nav-enter campo-kilos" step="1" min="0" value="<?= ($edicion && $row['kilos_fecha_1'] !== null && $row['kilos_fecha_1'] !== '') ? $row['kilos_fecha_1'] : '0' ?>" placeholder="0">
            </div>
        </div>
        <div class="fila-fecha">
            <div>
                <label>Fecha cobro 2</label>
                <div class="bloque-dia-mes">
                    <input type="number" name="dia_cobro_2" class="nav-enter" min="1" max="31" placeholder="Día" value="<?= $edicion && !empty($row['fecha_cobro_2']) ? (int)date('j', strtotime($row['fecha_cobro_2'])) : '' ?>" style="width:42px;">
                    <select name="mes_cobro_2" class="nav-enter campo-mes">
                        <option value="">--</option>
                        <?php for ($m=1;$m<=12;$m++): $sel = ($edicion && !empty($row['fecha_cobro_2']) && (int)date('n', strtotime($row['fecha_cobro_2']))==$m) ? 'selected' : ''; ?>
                        <option value="<?= $m ?>" <?= $sel ?>><?= str_pad($m,2,'0',STR_PAD_LEFT) ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
            </div>
            <div>
                <label>Kilos 2</label>
                <input type="number" name="kilos_fecha_2" class="nav-enter campo-kilos" step="1" min="0" value="<?= ($edicion && $row['kilos_fecha_2'] !== null && $row['kilos_fecha_2'] !== '') ? $row['kilos_fecha_2'] : '' ?>" placeholder="0">
            </div>
        </div>

        <div class="fila-opciones">
            <div class="item item-venc">
                <label>Venc. contrato *</label>
                <input type="date" name="fecha_vencimiento_contrato" id="fecha_vencimiento_contrato" value="<?= htmlspecialchars($edicion ? (isset($row['fecha_vencimiento_contrato']) && $row['fecha_vencimiento_contrato'] !== '' ? $row['fecha_vencimiento_contrato'] : '') : '') ?>">
            </div>
            <div class="item">
                <label>IVA %</label>
                <input type="number" name="iva_porcentaje" id="iva_porcentaje" step="0.01" min="0" max="100" value="<?= $edicion && isset($row['iva_porcentaje']) && $row['iva_porcentaje'] !== '' && $row['iva_porcentaje'] !== null ? number_format((float)$row['iva_porcentaje'], 2, '.', '') : '21.00' ?>" placeholder="21" style="width:65px;">
            </div>
            <div class="item check">
                <input type="checkbox" name="paga_comunal" value="1" id="paga_comunal" <?= ($edicion && !empty($row['paga_comunal'])) ? 'checked' : '' ?>>
                <label for="paga_comunal" style="margin:0;">Municipal</label>
            </div>
            <div class="item item-prov-otros">
                <div class="fila-prov-otros">
                    <div class="item check">
                        <input type="checkbox" name="paga_provincial" value="1" id="paga_provincial" <?= ($edicion && !empty($row['paga_provincial'])) ? 'checked' : '' ?>>
                        <label for="paga_provincial" style="margin:0;">Provincial</label>
                    </div>
                    <div class="item">
                        <label>Otros %</label>
                        <input type="number" name="porcentaje_otros" id="porcentaje_otros" step="0.01" min="0" max="100" value="<?= $edicion && isset($row['porcentaje_otros']) && $row['porcentaje_otros'] !== '' && $row['porcentaje_otros'] !== null ? number_format((float)$row['porcentaje_otros'], 2, '.', '') : '0.00' ?>" placeholder="%" style="width:60px;">
                    </div>
                </div>
            </div>
        </div>

        <div class="btns">
            <button type="submit" class="btn btn-guardar">Guardar</button>
            <a href="arriendos.php" class="btn btn-cancelar">Cancelar</a>
        </div>
    </form>
</div>

<script>
function buscar(q, listaId, hiddenId, inputBusId) {
    var lista = document.getElementById(listaId);
    if (q.length < 2) { lista.style.display = 'none'; return; }
    fetch('buscar_personas.php?q=' + encodeURIComponent(q))
        .then(function(r) { return r.json(); })
        .then(function(data) {
            lista.innerHTML = '';
            if (data.length) {
                lista.style.display = 'block';
                data.forEach(function(p) {
                    var div = document.createElement('div');
                    div.className = 'sugerencia-item';
                    div.textContent = p.apellido;
                    div.setAttribute('data-id', p.id);
                    div.onclick = function() {
                        document.getElementById(hiddenId).value = p.id;
                        document.getElementById(inputBusId).value = p.apellido;
                        lista.style.display = 'none';
                        document.getElementById(inputBusId).focus();
                    };
                    lista.appendChild(div);
                });
            }
        });
}

document.getElementById('porcentaje_otros').onfocus = function() { this.select(); };
document.getElementById('iva_porcentaje').onfocus = function() { this.select(); };

document.getElementById('bus_propietario').oninput = function() { buscar(this.value, 'sug_propietario', 'propietario_id', 'bus_propietario'); };
document.getElementById('bus_apoderado').oninput = function() { buscar(this.value, 'sug_apoderado', 'apoderado_id', 'bus_apoderado'); };
document.getElementById('bus_arrendatario').oninput = function() { buscar(this.value, 'sug_arrendatario', 'arrendatario_id', 'bus_arrendatario'); };

(function() {
    var buscampos = [
        { input: 'bus_propietario', lista: 'sug_propietario', hidden: 'propietario_id', next: 'bus_apoderado' },
        { input: 'bus_apoderado', lista: 'sug_apoderado', hidden: 'apoderado_id', next: 'bus_arrendatario' },
        { input: 'bus_arrendatario', lista: 'sug_arrendatario', hidden: 'arrendatario_id', next: 'descripcion_finca' }
    ];
    document.getElementById('formArriendo').addEventListener('keydown', function(e) {
        if (e.key !== 'Enter' && e.keyCode !== 13) return;
        var el = document.activeElement;
        if (!el || el.tagName !== 'INPUT') return;
        for (var i = 0; i < buscampos.length; i++) {
            var c = buscampos[i];
            if (el.id === c.input) {
                var lista = document.getElementById(c.lista);
                var first = lista && lista.style.display !== 'none' ? lista.querySelector('.sugerencia-item') : null;
                if (first) {
                    e.preventDefault();
                    e.stopPropagation();
                    document.getElementById(c.hidden).value = first.getAttribute('data-id') || '';
                    el.value = first.textContent;
                    lista.style.display = 'none';
                    var nextEl = document.getElementById(c.next);
                    if (nextEl) nextEl.focus();
                }
                return;
            }
        }
    }, true);
})();

function mostrarAviso(msg) {
    var overlay = document.getElementById('modalAviso');
    document.getElementById('modalAvisoMsg').textContent = msg;
    overlay.classList.add('visible');
}
function cerrarAviso() {
    document.getElementById('modalAviso').classList.remove('visible');
}
document.getElementById('modalAvisoCerrar').onclick = cerrarAviso;
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape' || e.keyCode === 27 || e.which === 27) {
        var overlay = document.getElementById('modalAviso');
        if (overlay.classList.contains('visible')) {
            cerrarAviso();
            e.preventDefault();
            e.stopPropagation();
        }
    }
}, true);

document.getElementById('formArriendo').onsubmit = function(e) {
    if (!document.getElementById('propietario_id').value || !document.getElementById('apoderado_id').value || !document.getElementById('arrendatario_id').value) {
        e.preventDefault();
        mostrarAviso('Debe elegir Propietario, Apoderado y Arrendatario.');
        return false;
    }
    if (!document.getElementById('descripcion_finca').value.trim()) {
        e.preventDefault();
        mostrarAviso('Finca (descripción) es obligatoria.');
        return false;
    }
    var dia1 = document.querySelector('input[name="dia_cobro_1"]').value;
    var mes1 = document.querySelector('select[name="mes_cobro_1"]').value;
    if (!dia1 || !mes1) {
        e.preventDefault();
        mostrarAviso('Fecha cobro 1 es obligatoria (día y mes).');
        return false;
    }
    var k1 = document.getElementById('kilos_fecha_1').value;
    if (k1 === '' || k1 === null || parseInt(k1, 10) < 0) {
        e.preventDefault();
        mostrarAviso('Kilos 1 es obligatorio.');
        return false;
    }
    if (!document.getElementById('fecha_vencimiento_contrato').value) {
        e.preventDefault();
        mostrarAviso('Vencimiento del contrato es obligatorio.');
        return false;
    }
    return true;
};

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape' || e.keyCode === 27 || e.which === 27) {
        e.preventDefault();
        window.location.href = 'arriendos.php';
        return;
    }
}, true);
</script>
<?php include 'timeout_sesion_inc.php'; ?>
</body>
</html>
