<?php
include 'db.php';
include 'verificar_sesion.php';
include 'crear_tabla_arriendos.php';

$nivelAcceso = (int)($_SESSION['acceso_nivel'] ?? 0);
$soloLectura = ($nivelAcceso < 2);
if ($nivelAcceso < 1) {
    header('Location: index.php?msg=sin_permiso');
    exit;
}

$sql = "SELECT a.*,
        p.apellido AS nombre_propietario,
        ap.apellido AS nombre_apoderado,
        ar.apellido AS nombre_arrendatario
        FROM arriendos a
        LEFT JOIN usuarios p ON a.propietario_id = p.id
        LEFT JOIN usuarios ap ON a.apoderado_id = ap.id
        LEFT JOIN usuarios ar ON a.arrendatario_id = ar.id
        ORDER BY a.id DESC";
$resultado = mysqli_query($conexion, $sql);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Arriendos - HHH</title>
    <style>
        body { font-family: 'Segoe UI', sans-serif; background: #f0f2f5; margin: 0; padding: 15px; min-height: 100vh; display: flex; flex-direction: column; box-sizing: border-box; }
        .contenedor { flex: 1; background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .encabezado { display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px; flex-wrap: wrap; gap: 8px; }
        h2 { margin: 0; color: #333; font-size: 1.1rem; font-weight: 600; }
        .buscar-wrap { display: flex; align-items: center; gap: 6px; }
        .buscar-wrap input { padding: 6px 10px; border: 1px solid #ccc; border-radius: 4px; font-size: 12px; width: 180px; }
        .tabla-arriendos-wrap { max-height: 428px; overflow-y: auto; border: 1px solid #ddd; border-radius: 4px; }
        .tabla-arriendos { width: 100%; border-collapse: collapse; font-size: 11px; }
        .tabla-arriendos th { background: #2c3e50; color: white; padding: 8px 6px; text-align: left; font-weight: 600; position: sticky; top: 0; z-index: 1; }
        .tabla-arriendos td { padding: 6px; border-bottom: 1px solid #e0e0e0; }
        .tabla-arriendos tbody tr:nth-child(even) { background: #f5f5f5; }
        .tabla-arriendos tbody tr:nth-child(odd) { background: #fafafa; }
        .tabla-arriendos tbody tr:hover { background: #e8eaf0; }
        .al-cen { text-align: center; }
        .al-der { text-align: right; }
        .col-iva, .col-otros { min-width: 75px; text-align: right; padding-right: 12px; }
        .btn { border: none; border-radius: 3px; padding: 5px 10px; cursor: pointer; font-size: 10px; font-weight: bold; text-decoration: none; display: inline-block; }
        .btn-alta, .btn-salir { min-width: 120px; padding: 10px 24px; font-size: 12px; text-align: center; box-sizing: border-box; }
        .btn-alta { background: #28a745; color: white; }
        .btn-alta:hover { background: #218838; color: white; }
        .btn-editar { background: #007bff; color: white; }
        .btn-baja { background: #dc3545; color: white; }
        .btn-salir { background: #6c757d; color: white; border-radius: 4px; margin-top: 15px; }
        .btn-salir:hover { background: #5a6268; color: white; }
        .pie { margin-top: auto; padding-top: 15px; display: flex; justify-content: flex-end; }
        .sino { font-size: 11px; }
        .celda-dto, .celda-otros, .celda-iva { cursor: pointer; min-width: 70px; }
        .celda-dto:hover, .celda-otros:hover, .celda-iva:hover { background: #e3f2fd !important; }
        .celda-dto.editando, .celda-otros.editando, .celda-iva.editando { padding: 0; }
        .celda-dto input, .celda-otros input, .celda-iva input { width: 100%; padding: 4px 6px; border: 2px solid #007bff; border-radius: 3px; font-size: 11px; text-align: right; box-sizing: border-box; }
    </style>
</head>
<body onkeydown="var e=event||window.event;if((e.keyCode||e.which)===27){e.preventDefault();window.location.href='index.php';return false;}">

<div class="contenedor">
    <div class="encabezado">
        <h2>Cronograma Arriendos</h2>
        <div class="buscar-wrap">
            <input type="text" id="txtBuscar" placeholder="Buscar..." onkeyup="filtrarArriendos()">
        </div>
        <?php if (!$soloLectura): ?>
        <a href="arriendo_form.php" class="btn btn-alta">+ Alta</a>
        <?php endif; ?>
    </div>

    <div class="tabla-arriendos-wrap">
    <table class="tabla-arriendos">
        <thead>
            <tr>
                <th>Propietario</th>
                <th>Apoderado</th>
                <th>Arrendatario</th>
                <th>Finca</th>
                <th class="al-cen">Fecha cobro (d/m)</th>
                <th class="al-cen">Venc. contrato</th>
                <th class="al-der">kilos</th>
                <th class="al-der col-iva">IVA %</th>
                <th class="al-der col-otros">Otros %</th>
                <th class="al-cen">comunal</th>
                <th class="al-cen">provincial</th>
                <?php if (!$soloLectura): ?><th class="al-cen">Acciones</th><?php endif; ?>
            </tr>
        </thead>
        <tbody>
            <?php
            $numFilas = 0;
            while ($f = mysqli_fetch_assoc($resultado)) {
                $numFilas++;
                $fecha1 = !empty($f['fecha_cobro_1']) ? date('d/m', strtotime($f['fecha_cobro_1'])) : '-';
                $fecha2 = !empty($f['fecha_cobro_2']) ? date('d/m', strtotime($f['fecha_cobro_2'])) : '';
                $fechas = $fecha1 . ($fecha2 ? ' / ' . $fecha2 : '');
                $venc_contrato = (isset($f['fecha_vencimiento_contrato']) && $f['fecha_vencimiento_contrato'] !== '' && $f['fecha_vencimiento_contrato'] !== null) ? date('d/m/Y', strtotime($f['fecha_vencimiento_contrato'])) : '-';
                $kilos1 = $f['kilos_fecha_1'] !== null ? number_format((float)$f['kilos_fecha_1'], 2, ',', '.') : '-';
                $kilos2 = $f['kilos_fecha_2'] !== null ? number_format((float)$f['kilos_fecha_2'], 2, ',', '.') : '';
                $kilos = $kilos1 . ($kilos2 ? ' / ' . $kilos2 : '');
                $ivaPct = isset($f['iva_porcentaje']) && $f['iva_porcentaje'] !== null && $f['iva_porcentaje'] !== '' ? (float)$f['iva_porcentaje'] : 21;
                $pctOtros = isset($f['porcentaje_otros']) && $f['porcentaje_otros'] !== null && $f['porcentaje_otros'] !== '' ? (float)$f['porcentaje_otros'] : 0;
                $comunal = (int)$f['paga_comunal'] ? 'S' : 'N';
                $provincial = (int)$f['paga_provincial'] ? 'S' : 'N';
                $finca = !empty($f['descripcion_finca']) ? $f['descripcion_finca'] : '-';
                if (strlen($finca) > 60) $finca = substr($finca, 0, 57) . '…';
            ?>
            <tr class="fila-arriendo">
                <td><?= htmlspecialchars($f['nombre_propietario'] ?? '-') ?></td>
                <td><?= htmlspecialchars($f['nombre_apoderado'] ?? '-') ?></td>
                <td><?= htmlspecialchars($f['nombre_arrendatario'] ?? '-') ?></td>
                <td><?= htmlspecialchars($finca) ?></td>
                <td class="al-cen"><?= $fechas ?></td>
                <td class="al-cen"><?= $venc_contrato ?></td>
                <td class="al-der"><?= $kilos ?></td>
                <td class="al-der col-iva<?= !$soloLectura ? ' celda-iva' : '' ?>"<?= !$soloLectura ? ' data-id="'.(int)$f['id'].'" data-valor="'.htmlspecialchars($ivaPct).'"' : '' ?>><?= number_format($ivaPct, 2, ',', '.') ?>%</td>
                <td class="al-der col-otros<?= !$soloLectura ? ' celda-otros' : '' ?>"<?= !$soloLectura ? ' data-id="'.(int)$f['id'].'" data-valor="'.htmlspecialchars($pctOtros).'"' : '' ?>><?= number_format($pctOtros, 2, ',', '.') ?>%</td>
                <td class="al-cen sino"><?= $comunal ?></td>
                <td class="al-cen sino"><?= $provincial ?></td>
                <?php if (!$soloLectura): ?>
                <td class="al-cen">
                    <a href="arriendo_form.php?id=<?= (int)$f['id'] ?>" class="btn btn-editar">Modificar</a>
                    <button type="button" class="btn btn-baja" onclick="bajaArriendo(<?= (int)$f['id'] ?>)">Baja</button>
                </td>
                <?php endif; ?>
            </tr>
            <?php } ?>
            <?php if ($numFilas === 0): ?>
            <tr><td colspan="<?= $soloLectura ? 12 : 13 ?>" style="text-align:center; padding:20px; color:#666;">No hay arriendos. <?php if (!$soloLectura): ?>Use «+ Alta» para cargar uno.<?php endif; ?></td></tr>
            <?php endif; ?>
        </tbody>
    </table>
    </div>

    <div class="pie">
        <a href="index.php" class="btn-salir">Salir</a>
    </div>
</div>

<script>
function filtrarArriendos() {
    var q = document.getElementById('txtBuscar').value.toUpperCase().trim();
    var terminos = q.split(/\s+/);
    document.querySelectorAll('.fila-arriendo').forEach(function(tr) {
        var texto = tr.innerText.toUpperCase();
        var coincide = terminos.length === 0 || terminos.every(function(t) { return texto.indexOf(t) >= 0; });
        tr.style.display = coincide ? '' : 'none';
    });
}

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape' || e.keyCode === 27 || e.which === 27) {
        if (document.activeElement && document.activeElement.closest && (document.activeElement.closest('.celda-dto.editando') || document.activeElement.closest('.celda-otros.editando') || document.activeElement.closest('.celda-iva.editando'))) return;
        e.preventDefault();
        e.stopPropagation();
        window.location.href = 'index.php';
    }
}, true);

(function() {
    var soloLectura = <?= $soloLectura ? 'true' : 'false' ?>;
    if (soloLectura) return;
    function initCeldaEditable(td, campo, url, sufijoFn) {
        td.addEventListener('click', function() {
            if (td.classList.contains('editando')) return;
            var id = td.getAttribute('data-id');
            var valor = td.getAttribute('data-valor') || '0';
            td.classList.add('editando');
            var inp = document.createElement('input');
            inp.type = 'text';
            inp.inputMode = 'decimal';
            inp.value = valor.replace('.', ',');
            inp.style.width = (td.offsetWidth - 4) + 'px';
            td.textContent = '';
            td.appendChild(inp);
            inp.focus();
            setTimeout(function() { inp.select(); }, 10);
            var guardado = false;
            function guardar() {
                if (guardado) return;
                guardado = true;
                var v = inp.value.replace(',', '.').replace(/\s/g, '');
                var num = parseFloat(v) || 0;
                num = (campo === 'porcentaje_otros' || campo === 'iva_porcentaje') ? Math.max(0, Math.min(100, num)) : Math.max(0, num);
                td.classList.remove('editando');
                inp.remove();
                var formData = new FormData();
                formData.append('id', id);
                formData.append(campo, num);
                fetch(url, { method: 'POST', body: formData })
                    .then(function(r) { return r.text(); })
                    .then(function(txt) {
                        if (txt.trim() === 'OK') {
                            td.setAttribute('data-valor', num);
                            var fmt = num.toFixed(2).replace('.', ',');
                            td.textContent = fmt + sufijoFn(num);
                        }
                    });
            }
            inp.onblur = guardar;
            inp.onkeydown = function(e) {
                if (e.key === 'Enter') { e.preventDefault(); guardar(); }
                if (e.key === 'Escape') {
                    guardado = true;
                    td.classList.remove('editando');
                    inp.remove();
                    var fmt = (parseFloat(valor) || 0).toFixed(2).replace('.', ',');
                    td.textContent = fmt + sufijoFn(parseFloat(valor) || 0);
                }
            };
        });
    }
    document.querySelectorAll('.celda-iva').forEach(function(td) { initCeldaEditable(td, 'iva_porcentaje', 'actualizar_iva_arriendo.php', function(n) { return '%'; }); });
    document.querySelectorAll('.celda-otros').forEach(function(td) { initCeldaEditable(td, 'porcentaje_otros', 'actualizar_porcentaje_otros_arriendo.php', function(n) { return '%'; }); });
})();

function bajaArriendo(id) {
    if (!confirm('¿Dar de baja este arriendo?')) return;
    fetch('eliminar_arriendo.php?id=' + id)
        .then(function(r) { return r.text(); })
        .then(function(txt) {
            if (txt.trim() === 'OK') window.location.reload();
            else alert(txt);
        });
}
</script>
<?php include 'timeout_sesion_inc.php'; ?>
</body>
</html>
