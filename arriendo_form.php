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
        .contenedor { max-width: 700px; background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h2 { margin: 0 0 15px 0; color: #0047AB; font-size: 1.1rem; border-bottom: 2px solid #0047AB; padding-bottom: 6px; }
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
        .btns { margin-top: 20px; display: flex; gap: 10px; flex-wrap: wrap; }
        .btn { padding: 8px 18px; border: none; border-radius: 4px; font-weight: bold; cursor: pointer; font-size: 12px; text-decoration: none; display: inline-block; }
        .btn-guardar { background: #28a745; color: white; }
        .btn-cancelar { background: #6c757d; color: white; }
        .btn-cancelar:hover { color: white; background: #5a6268; }
    </style>
</head>
<body onkeydown="var e=event||window.event;if((e.keyCode||e.which)===27){e.preventDefault();window.location.href='arriendos.php';return false;}">

<div class="contenedor">
    <h2><?= $edicion ? 'Modificar' : 'Alta' ?> Arriendo</h2>

    <form id="formArriendo" method="post" action="guardar_arriendo.php">
        <?php if ($edicion): ?><input type="hidden" name="id" value="<?= $id ?>"><?php endif; ?>

        <div class="form-grid">
            <div class="buscador-contenedor ancho-completo">
                <label>Propietario *</label>
                <input type="text" id="bus_propietario" placeholder="Buscar..." value="<?= htmlspecialchars($nombre_propietario) ?>" autocomplete="off">
                <input type="hidden" name="propietario_id" id="propietario_id" value="<?= $edicion ? (int)$row['propietario_id'] : 0 ?>">
                <div id="sug_propietario" class="sugerencias"></div>
            </div>
            <div class="buscador-contenedor ancho-completo">
                <label>Apoderado *</label>
                <input type="text" id="bus_apoderado" placeholder="Buscar..." value="<?= htmlspecialchars($nombre_apoderado) ?>" autocomplete="off">
                <input type="hidden" name="apoderado_id" id="apoderado_id" value="<?= $edicion ? (int)$row['apoderado_id'] : 0 ?>">
                <div id="sug_apoderado" class="sugerencias"></div>
            </div>
            <div class="buscador-contenedor ancho-completo">
                <label>Arrendatario *</label>
                <input type="text" id="bus_arrendatario" placeholder="Buscar..." value="<?= htmlspecialchars($nombre_arrendatario) ?>" autocomplete="off">
                <input type="hidden" name="arrendatario_id" id="arrendatario_id" value="<?= $edicion ? (int)$row['arrendatario_id'] : 0 ?>">
                <div id="sug_arrendatario" class="sugerencias"></div>
            </div>

            <div class="ancho-completo">
                <label>Finca (descripción)</label>
                <textarea name="descripcion_finca" rows="3" placeholder="Ej: FINCA 29 por 2 Has"><?= htmlspecialchars($edicion ? ($row['descripcion_finca'] ?? '') : '') ?></textarea>
            </div>

            <div>
                <label>Fecha cobro 1 (día y mes, cada año)</label>
                <input type="date" name="fecha_cobro_1" value="<?= htmlspecialchars($edicion ? ($row['fecha_cobro_1'] ?? '') : '') ?>" title="Indica el día y mes de cobro que se repite cada año">
            </div>
            <div>
                <label>Fecha cobro 2 (día y mes, opcional)</label>
                <input type="date" name="fecha_cobro_2" value="<?= htmlspecialchars($edicion ? ($row['fecha_cobro_2'] ?? '') : '') ?>" title="Segunda fecha de cobro cada año">
            </div>
            <div>
                <label>Fecha vencimiento contrato</label>
                <input type="date" name="fecha_vencimiento_contrato" value="<?= htmlspecialchars($edicion ? (isset($row['fecha_vencimiento_contrato']) && $row['fecha_vencimiento_contrato'] !== '' ? $row['fecha_vencimiento_contrato'] : '') : '') ?>">
            </div>
            <div>
                <label>Kilos fecha 1</label>
                <input type="number" name="kilos_fecha_1" step="0.01" min="0" value="<?= ($edicion && $row['kilos_fecha_1'] !== null && $row['kilos_fecha_1'] !== '') ? $row['kilos_fecha_1'] : '' ?>" placeholder="0">
            </div>
            <div>
                <label>Kilos fecha 2 (opcional)</label>
                <input type="number" name="kilos_fecha_2" step="0.01" min="0" value="<?= ($edicion && $row['kilos_fecha_2'] !== null && $row['kilos_fecha_2'] !== '') ? $row['kilos_fecha_2'] : '' ?>" placeholder="0">
            </div>

            <div class="check">
                <input type="checkbox" name="descontar_iva" value="1" id="descontar_iva" <?= ($edicion && !empty($row['descontar_iva'])) ? 'checked' : '' ?>>
                <label for="descontar_iva" style="margin:0;">Descontar IVA del precio final</label>
            </div>
            <div>
                <label>Monto otros descuentos</label>
                <input type="number" name="monto_descuentos" step="0.01" min="0" value="<?= $edicion ? (float)$row['monto_descuentos'] : '0' ?>">
            </div>
            <div class="check">
                <input type="checkbox" name="paga_comunal" value="1" id="paga_comunal" <?= ($edicion && !empty($row['paga_comunal'])) ? 'checked' : '' ?>>
                <label for="paga_comunal" style="margin:0;">Paga impuesto comunal</label>
            </div>
            <div class="check">
                <input type="checkbox" name="paga_provincial" value="1" id="paga_provincial" <?= ($edicion && !empty($row['paga_provincial'])) ? 'checked' : '' ?>>
                <label for="paga_provincial" style="margin:0;">Paga impuesto provincial</label>
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
                    div.onclick = function() {
                        document.getElementById(hiddenId).value = p.id;
                        document.getElementById(inputBusId).value = p.apellido;
                        lista.style.display = 'none';
                    };
                    lista.appendChild(div);
                });
            }
        });
}

document.getElementById('bus_propietario').oninput = function() { buscar(this.value, 'sug_propietario', 'propietario_id', 'bus_propietario'); };
document.getElementById('bus_apoderado').oninput = function() { buscar(this.value, 'sug_apoderado', 'apoderado_id', 'bus_apoderado'); };
document.getElementById('bus_arrendatario').oninput = function() { buscar(this.value, 'sug_arrendatario', 'arrendatario_id', 'bus_arrendatario'); };

document.getElementById('formArriendo').onsubmit = function() {
    if (!document.getElementById('propietario_id').value || !document.getElementById('apoderado_id').value || !document.getElementById('arrendatario_id').value) {
        alert('Debe elegir Propietario, Apoderado y Arrendatario.');
        return false;
    }
    return true;
};

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape' || e.keyCode === 27 || e.which === 27) {
        e.preventDefault();
        window.location.href = 'arriendos.php';
    }
}, true);
</script>
<?php include 'timeout_sesion_inc.php'; ?>
</body>
</html>
