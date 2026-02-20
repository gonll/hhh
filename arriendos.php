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
        .tabla-arriendos { width: 100%; border-collapse: collapse; font-size: 11px; }
        .tabla-arriendos th { background: #2c3e50; color: white; padding: 8px 6px; text-align: left; font-weight: 600; }
        .tabla-arriendos td { padding: 6px; border-bottom: 1px solid #e0e0e0; }
        .tabla-arriendos tbody tr:nth-child(even) { background: #f5f5f5; }
        .tabla-arriendos tbody tr:nth-child(odd) { background: #fafafa; }
        .tabla-arriendos tbody tr:hover { background: #e8eaf0; }
        .al-cen { text-align: center; }
        .al-der { text-align: right; }
        .btn { border: none; border-radius: 3px; padding: 5px 10px; cursor: pointer; font-size: 10px; font-weight: bold; text-decoration: none; display: inline-block; }
        .btn-alta { background: #28a745; color: white; }
        .btn-editar { background: #007bff; color: white; }
        .btn-baja { background: #dc3545; color: white; }
        .btn-salir { background: #6c757d; color: white; padding: 8px 20px; border-radius: 4px; font-weight: bold; text-decoration: none; font-size: 12px; display: inline-block; margin-top: 15px; }
        .btn-salir:hover { background: #5a6268; color: white; }
        .pie { margin-top: auto; padding-top: 15px; display: flex; justify-content: flex-end; }
        .sino { font-size: 11px; }
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
                <th class="al-cen">iva</th>
                <th class="al-der">dto</th>
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
                $iva = (int)$f['descontar_iva'] ? 'S' : 'N';
                $montoDto = (float)$f['monto_descuentos'];
                $dtos = number_format($montoDto, 2, ',', '.') . ' ' . ($montoDto > 0 ? 'S' : 'N');
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
                <td class="al-cen sino"><?= $iva ?></td>
                <td class="al-der"><?= $dtos ?></td>
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
            <tr><td colspan="<?= $soloLectura ? 11 : 12 ?>" style="text-align:center; padding:20px; color:#666;">No hay arriendos. <?php if (!$soloLectura): ?>Use «+ Alta» para cargar uno.<?php endif; ?></td></tr>
            <?php endif; ?>
        </tbody>
    </table>

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
        e.preventDefault();
        e.stopPropagation();
        window.location.href = 'index.php';
    }
}, true);

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
