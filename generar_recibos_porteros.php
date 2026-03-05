<?php
include 'db.php';
include 'verificar_sesion.php';

if (isset($_SESSION['acceso_nivel']) && $_SESSION['acceso_nivel'] < 2) {
    header('Location: index.php?msg=sin_permiso');
    exit;
}

$tipos_opciones = ['HABER' => 'Haber', 'BONO' => 'Bono', 'RETENCIONES' => 'Retenciones', 'DEDUCCIONES' => 'Deducciones', 'ASIGNACIONES' => 'Asignaciones', 'NOREMUNERATIVO' => 'No Remunerativos'];
$mensaje = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['guardar_ficha'])) {
        $usuario_id = (int)($_POST['usuario_id'] ?? 0);
        $items = isset($_POST['items']) && is_array($_POST['items']) ? array_map('intval', $_POST['items']) : [];
        if ($usuario_id < 1) {
            $mensaje = 'Seleccione un portero.';
        } elseif (empty($items)) {
            $mensaje = 'Seleccione al menos un ítem de la tabla salarial.';
        } else {
            $insertados = 0;
            foreach ($items as $tid) {
                if ($tid > 0) {
                    $sql_ins = "INSERT IGNORE INTO ficha_portero (usuario_id, tabsalpor_id) VALUES ($usuario_id, $tid)";
                    if (mysqli_query($conexion, $sql_ins) && mysqli_affected_rows($conexion) > 0) {
                        $insertados++;
                    }
                }
            }
            $mensaje = $insertados > 0 ? "Ficha guardada. Se agregaron $insertados ítem(s)." : "Los ítems ya estaban asignados a este portero.";
            header('Location: generar_recibos_porteros.php?ok=1');
            exit;
        }
    } elseif (isset($_POST['eliminar_ficha']) && isset($_POST['ficha_id'])) {
        $fid = (int)$_POST['ficha_id'];
        if ($fid > 0 && mysqli_query($conexion, "DELETE FROM ficha_portero WHERE id = $fid")) {
            $mensaje = 'Ítem quitado de la ficha.';
            header('Location: generar_recibos_porteros.php?ok=1');
            exit;
        }
    }
}

if (isset($_GET['ok'])) {
    $mensaje = 'Operación realizada correctamente.';
}

$lista = [];
$res_lista = mysqli_query($conexion, "SELECT * FROM tabsalpor ORDER BY tipo ASC, descripcion ASC, fecha DESC");
if ($res_lista) {
    while ($row = mysqli_fetch_assoc($res_lista)) {
        $lista[] = $row;
    }
}

$usuarios_porteros = [];
$res_usu = mysqli_query($conexion, "SELECT id, apellido FROM usuarios WHERE id != 1 ORDER BY apellido ASC");
if ($res_usu) {
    while ($u = mysqli_fetch_assoc($res_usu)) {
        $usuarios_porteros[] = $u;
    }
}

$fichas_por_portero = [];
$res_fichas = mysqli_query($conexion, "SELECT fp.id AS ficha_id, fp.usuario_id, fp.tabsalpor_id, u.apellido AS usuario_nombre, t.descripcion AS item_desc, t.tipo AS item_tipo, t.monto, t.unidad
    FROM ficha_portero fp
    INNER JOIN usuarios u ON u.id = fp.usuario_id
    INNER JOIN tabsalpor t ON t.id = fp.tabsalpor_id
    ORDER BY u.apellido ASC, t.tipo ASC, t.descripcion ASC");
if ($res_fichas) {
    while ($f = mysqli_fetch_assoc($res_fichas)) {
        $uid = (int)$f['usuario_id'];
        if (!isset($fichas_por_portero[$uid])) {
            $fichas_por_portero[$uid] = ['nombre' => $f['usuario_nombre'], 'items' => []];
        }
        $fichas_por_portero[$uid]['items'][] = $f;
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Generar Recibos Porteros - HHH</title>
    <style>
        body { font-family: 'Segoe UI', sans-serif; margin: 15px; background: #f0f2f5; font-size: 12px; }
        .container { max-width: 900px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h2 { color: #28a745; margin-top: 0; margin-bottom: 15px; font-size: 18px; border-bottom: 2px solid #28a745; padding-bottom: 8px; }
        h3 { color: #6f42c1; font-size: 16px; margin: 25px 0 15px; }
        .mensaje { padding: 10px; margin-bottom: 15px; border-radius: 4px; font-size: 12px; }
        .mensaje.ok { background: #d4edda; color: #155724; }
        .mensaje.error { background: #f8d7da; color: #721c24; }
        label { display: block; margin-bottom: 3px; font-weight: bold; font-size: 11px; color: #555; }
        input:not([type="checkbox"]):not([type="radio"]), textarea, select { padding: 8px; border: 1px solid #ced4da; border-radius: 4px; width: 100%; box-sizing: border-box; font-size: 12px; }
        input[type="checkbox"], input[type="radio"] { width: auto; }
        .form-row { display: flex; gap: 12px; margin-bottom: 12px; flex-wrap: wrap; }
        .form-row .campo { flex: 1; min-width: 120px; }
        .form-row .campo-ancho { flex: 2; min-width: 200px; }
        .btn { padding: 8px 16px; border: none; border-radius: 4px; cursor: pointer; font-size: 12px; font-weight: bold; text-decoration: none; display: inline-block; }
        .btn-primary { background: #28a745; color: white; }
        .btn-primary:hover { background: #218838; }
        .btn-secondary { background: #6c757d; color: white; }
        .btn-secondary:hover { background: #5a6268; color: white; }
        .btn-danger { background: #dc3545; color: white; }
        .btn-danger:hover { background: #c82333; }
        .volver { margin-top: 20px; display: inline-block; padding: 10px 20px; background: #6c757d; color: white; text-decoration: none; border-radius: 4px; font-weight: bold; }
        .volver:hover { background: #5a6268; color: white; }
        .buscador-portero { position: relative; }
        .buscador-portero input[type="text"] { padding-right: 36px; }
        .buscador-portero .ico-lupa { position: absolute; right: 10px; top: 50%; transform: translateY(-50%); pointer-events: none; color: #6c757d; font-size: 1rem; }
        .buscador-portero-resultados { position: absolute; top: 100%; left: 0; right: 0; background: white; border: 1px solid #ced4da; border-top: none; max-height: 180px; overflow-y: auto; z-index: 100; display: none; font-size: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.1); }
        .buscador-portero-resultados .item-portero { padding: 8px 10px; cursor: pointer; border-bottom: 1px solid #eee; }
        .buscador-portero-resultados .item-portero:hover { background: #e7f3ff; }
        .seccion-generar { margin-top: 30px; padding: 20px; border: 2px dashed #28a745; border-radius: 8px; background: #f8fff8; }
        .lista-items-tabsalpor label { display: block; margin-bottom: 6px; font-weight: normal; cursor: pointer; }
        .lista-items-tabsalpor input[type="checkbox"] { width: auto; margin-right: 8px; vertical-align: middle; }
    </style>
</head>
<body>
    <div class="container">
        <div style="display: flex; gap: 10px; margin-bottom: 20px; flex-wrap: wrap;">
            <a href="recibos_porteros.php" class="btn btn-secondary">← Recibos Porteros</a>
            <a href="recibos_porteros.php?seccion=tabsalpor" class="btn" style="background: #6f42c1; color: white;">ABM TabSalPor</a>
        </div>

        <h2>Generar recibos portero</h2>

        <?php if ($mensaje): ?>
            <div class="mensaje <?= strpos($mensaje, 'Error') !== false ? 'error' : 'ok' ?>" id="msgFlash"><?= htmlspecialchars($mensaje) ?></div>
        <?php endif; ?>

        <h3>1. ABM Fichas (asignar ítems a liquidar por portero)</h3>

        <h4 style="font-size: 13px; color: #333; margin-bottom: 10px;">Fichas guardadas</h4>
        <?php if (count($fichas_por_portero) > 0): ?>
            <div style="margin-bottom: 25px;">
                <?php foreach ($fichas_por_portero as $uid => $datos): ?>
                <div style="border: 1px solid #dee2e6; border-radius: 6px; padding: 12px; margin-bottom: 12px; background: #f8f9fa;">
                    <strong style="font-size: 12px; color: #6f42c1;"><?= htmlspecialchars($datos['nombre']) ?></strong>
                    <ul style="margin: 8px 0 0 0; padding-left: 20px; font-size: 11px;">
                        <?php foreach ($datos['items'] as $it): 
                            $monto_disp = ($it['unidad'] ?? '') === '%' ? number_format($it['monto'], 2, ',', '.') . '%' : '$' . number_format($it['monto'], 2, ',', '.');
                        ?>
                        <li style="margin-bottom: 4px;">
                            <?= htmlspecialchars($it['item_desc']) ?> (<?= htmlspecialchars($tipos_opciones[$it['item_tipo'] ?? ''] ?? $it['item_tipo'] ?? '-') ?>) — <?= $monto_disp ?>
                            <form method="POST" style="display: inline; margin-left: 8px;" onsubmit="return confirm('¿Quitar este ítem de la ficha?');">
                                <input type="hidden" name="ficha_id" value="<?= (int)$it['ficha_id'] ?>">
                                <button type="submit" name="eliminar_ficha" class="btn btn-danger" style="padding: 2px 8px; font-size: 10px;">Quitar</button>
                            </form>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <p style="color: #999; font-size: 11px; margin-bottom: 20px;">No hay fichas cargadas.</p>
        <?php endif; ?>

        <h4 style="font-size: 13px; color: #333; margin-bottom: 10px;">Agregar ficha por portero</h4>
        <p style="margin-bottom: 15px; font-size: 11px; color: #666;">Seleccione el portero y los ítems de la tabla salarial que se liquidarán.</p>
        <form method="POST">
            <div class="form-row">
                <div class="campo-ancho">
                    <label>Portero (usuario)</label>
                    <div class="buscador-portero">
                        <input type="text" id="buscadorPortero" placeholder="Escribir letras para buscar portero..." autocomplete="off">
                        <span class="ico-lupa" aria-hidden="true">🔍</span>
                        <input type="hidden" name="usuario_id" id="ficha_usuario_id" value="" required>
                        <div id="resultadosPortero" class="buscador-portero-resultados"></div>
                    </div>
                </div>
            </div>
            <div class="form-row">
                <div class="campo-ancho" style="flex: 1 1 100%;">
                    <label>Ítems de la tabla salarial a liquidar</label>
                    <div class="lista-items-tabsalpor" style="max-height: 200px; overflow-y: auto; border: 1px solid #ced4da; border-radius: 4px; padding: 10px; background: #f8f9fa;">
                        <?php if (count($lista) > 0): ?>
                            <?php foreach ($lista as $it): ?>
                            <label>
                                <input type="checkbox" name="items[]" value="<?= (int)$it['id'] ?>">
                                <?= htmlspecialchars($it['descripcion'] ?? '-') ?> 
                                (<?= htmlspecialchars($tipos_opciones[$it['tipo'] ?? ''] ?? $it['tipo'] ?? '-') ?>)
                                — <?= ($it['unidad'] ?? '') === '%' ? number_format($it['monto'], 2, ',', '.') . '%' : '$' . number_format($it['monto'], 2, ',', '.') ?>
                            </label>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <span style="color: #999;">Primero cargue ítems en la tabla salarial.</span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <div class="form-row">
                <button type="submit" name="guardar_ficha" class="btn btn-primary">Guardar ficha</button>
            </div>
        </form>

        <h3>2. Generar recibos</h3>
        <div class="seccion-generar">
            <p style="margin: 0 0 15px 0; color: #333; font-size: 12px;">Seleccione el portero. La ficha guardada se usará para el recibo y es la base para el próximo recibo.</p>
            <div class="form-row">
                <div class="campo-ancho">
                    <label>Portero para recibo</label>
                    <div class="buscador-portero buscador-recibo">
                        <input type="text" id="buscadorPorteroRecibo" placeholder="Escribir letras para buscar portero..." autocomplete="off">
                        <span class="ico-lupa" aria-hidden="true">🔍</span>
                        <div id="resultadosPorteroRecibo" class="buscador-portero-resultados"></div>
                    </div>
                </div>
            </div>
            <div id="fichaReciboWrap" style="display: none; margin-top: 15px; padding: 12px; background: white; border: 1px solid #28a745; border-radius: 6px;">
                <strong style="font-size: 12px; color: #28a745;">Ficha que se usará para el recibo:</strong>
                <p style="margin: 4px 0 0 0; font-size: 11px; color: #666;">(Base para este y el próximo recibo)</p>
                <ul id="fichaReciboItems" style="margin: 10px 0 0 0; padding-left: 20px; font-size: 12px;"></ul>
            </div>
            <p id="fichaReciboVacia" style="display: none; margin-top: 10px; color: #856404; font-size: 11px; background: #fff3cd; padding: 10px; border-radius: 4px;">Este portero no tiene ficha guardada. Primero agregue una ficha en la sección 1.</p>
        </div>

        <a href="index.php" class="volver">← Volver al menú principal</a>
    </div>

    <script>
    var porterosLista = <?= json_encode(array_map(function($u) { return ['id' => (int)$u['id'], 'apellido' => $u['apellido']]; }, $usuarios_porteros)) ?>;
    var fichasPorPortero = <?= json_encode($fichas_por_portero) ?>;
    var tiposOpciones = <?= json_encode($tipos_opciones) ?>;
    (function() {
        var buscador = document.getElementById('buscadorPortero');
        var resultados = document.getElementById('resultadosPortero');
        var hiddenId = document.getElementById('ficha_usuario_id');
        if (!buscador || !resultados || !hiddenId) return;
        function filtrarPortero() {
            var q = (buscador.value || '').toUpperCase().trim();
            var lista = porterosLista || [];
            var filtrados = q ? lista.filter(function(u) { return (u.apellido || '').toUpperCase().indexOf(q) >= 0; }) : lista;
            resultados.innerHTML = filtrados.slice(0, 50).map(function(u) {
                return '<div class="item-portero" data-id="' + u.id + '" data-nombre="' + (u.apellido || '').replace(/"/g, '&quot;') + '">' + (u.apellido || '') + '</div>';
            }).join('');
            resultados.style.display = filtrados.length > 0 ? 'block' : 'none';
        }
        buscador.addEventListener('input', filtrarPortero);
        buscador.addEventListener('focus', filtrarPortero);
        resultados.addEventListener('click', function(e) {
            var item = e.target.closest('.item-portero');
            if (item) {
                hiddenId.value = item.getAttribute('data-id');
                buscador.value = item.getAttribute('data-nombre') || '';
                resultados.style.display = 'none';
            }
        });
        document.addEventListener('click', function(e) {
            var wrap = document.querySelector('.buscador-portero');
            if (wrap && resultados && !wrap.contains(e.target)) resultados.style.display = 'none';
        });
    })();
    (function() {
        var buscador = document.getElementById('buscadorPorteroRecibo');
        var resultados = document.getElementById('resultadosPorteroRecibo');
        var fichaWrap = document.getElementById('fichaReciboWrap');
        var fichaItems = document.getElementById('fichaReciboItems');
        var fichaVacia = document.getElementById('fichaReciboVacia');
        if (!buscador || !resultados) return;
        function filtrar() {
            var q = (buscador.value || '').toUpperCase().trim();
            var lista = porterosLista || [];
            var filtrados = q ? lista.filter(function(u) { return (u.apellido || '').toUpperCase().indexOf(q) >= 0; }) : lista;
            resultados.innerHTML = filtrados.slice(0, 50).map(function(u) {
                return '<div class="item-portero" data-id="' + u.id + '" data-nombre="' + (u.apellido || '').replace(/"/g, '&quot;') + '">' + (u.apellido || '') + '</div>';
            }).join('');
            resultados.style.display = filtrados.length > 0 ? 'block' : 'none';
        }
        function mostrarFicha(uid) {
            var fichas = fichasPorPortero || {};
            var datos = fichas[uid];
            fichaWrap.style.display = 'none';
            fichaVacia.style.display = 'none';
            if (!datos || !datos.items || datos.items.length === 0) {
                fichaVacia.style.display = 'block';
                return;
            }
            var html = datos.items.map(function(it) {
                var montoDisp = (it.unidad || '') === '%' ? (parseFloat(it.monto) || 0).toLocaleString('es-AR', {minimumFractionDigits: 2}) + '%' : '$' + (parseFloat(it.monto) || 0).toLocaleString('es-AR', {minimumFractionDigits: 2});
                var tipoNombre = (tiposOpciones || {})[it.item_tipo || ''] || it.item_tipo || '-';
                return '<li>' + (it.item_desc || '-') + ' (' + tipoNombre + ') — ' + montoDisp + '</li>';
            }).join('');
            fichaItems.innerHTML = html;
            fichaWrap.style.display = 'block';
        }
        buscador.addEventListener('input', filtrar);
        buscador.addEventListener('focus', filtrar);
        resultados.addEventListener('click', function(e) {
            var item = e.target.closest('.item-portero');
            if (item) {
                var uid = parseInt(item.getAttribute('data-id'), 10);
                buscador.value = item.getAttribute('data-nombre') || '';
                resultados.style.display = 'none';
                mostrarFicha(uid);
            }
        });
        document.addEventListener('click', function(e) {
            var wrap = document.querySelector('.buscador-recibo');
            if (wrap && resultados && !wrap.contains(e.target)) resultados.style.display = 'none';
        });
    })();
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            window.location.href = 'index.php';
        }
    });
    (function() {
        var msg = document.getElementById('msgFlash');
        if (msg) setTimeout(function() { msg.style.opacity = '0'; msg.style.transition = 'opacity 0.3s'; setTimeout(function() { msg.remove(); }, 300); }, 2500);
    })();
    </script>
    <?php include 'timeout_sesion_inc.php'; ?>
</body>
</html>
