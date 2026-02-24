<?php
include 'db.php';
include 'verificar_sesion.php';

if (isset($_SESSION['acceso_nivel']) && $_SESSION['acceso_nivel'] < 2) {
    header('Location: index.php?msg=solo_lectura');
    exit;
}

// Asegurar que la tabla pdt existe
$res_check = mysqli_query($conexion, "SHOW TABLES LIKE 'pdt'");
if (!$res_check || mysqli_num_rows($res_check) == 0) {
    $sql_create = "CREATE TABLE IF NOT EXISTS pdt (
        id INT AUTO_INCREMENT PRIMARY KEY,
        usuario_id INT NOT NULL,
        tipo_horas VARCHAR(50) NOT NULL DEFAULT 'Horas Comunes',
        tractor VARCHAR(100) NULL,
        fecha DATE NOT NULL,
        horas DECIMAL(5,2) DEFAULT 0.00,
        cant_gasoil DECIMAL(6,2) NULL,
        cambio_aceite TINYINT(1) DEFAULT 0,
        en_cc TINYINT(1) DEFAULT 0,
        observaciones TEXT NULL,
        fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        fecha_modificacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_usuario (usuario_id),
        INDEX idx_fecha (fecha)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci";
    mysqli_query($conexion, $sql_create);
}

$mensaje = '';
$editar_id = null;
$editar = null;

// Procesar POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['guardar'])) {
        $pdt_id = isset($_POST['pdt_id']) ? (int)$_POST['pdt_id'] : 0;
        $usuario_id = (int)($_POST['usuario_id'] ?? 0);
        $tipo_horas = mysqli_real_escape_string($conexion, trim($_POST['tipo_horas'] ?? 'Horas Comunes'));
        $tractor = mysqli_real_escape_string($conexion, trim($_POST['tractor'] ?? ''));
        $fecha = mysqli_real_escape_string($conexion, trim($_POST['fecha'] ?? ''));
        $horas = (float)($_POST['horas'] ?? 0);
        $cant_gasoil = isset($_POST['cant_gasoil']) && $_POST['cant_gasoil'] !== '' ? (float)$_POST['cant_gasoil'] : null;
        $cambio_aceite = isset($_POST['cambio_aceite']) && $_POST['cambio_aceite'] == '1' ? 1 : 0;
        $observaciones = mysqli_real_escape_string($conexion, trim($_POST['observaciones'] ?? ''));

        if ($usuario_id < 1 || $fecha === '') {
            $mensaje = 'Faltan datos obligatorios.';
        } else {
            if ($pdt_id > 0) {
                $tractor_val = ($tipo_horas === 'Horas tractos' && $tractor !== '') ? "'$tractor'" : "NULL";
                $sql = "UPDATE pdt SET usuario_id = $usuario_id, tipo_horas = '$tipo_horas', tractor = $tractor_val,
                        fecha = '$fecha', horas = $horas, cant_gasoil = " . ($cant_gasoil !== null ? $cant_gasoil : "NULL") . ",
                        cambio_aceite = $cambio_aceite, observaciones = '$observaciones' WHERE id = $pdt_id";
                if (mysqli_query($conexion, $sql)) {
                    $mensaje = 'Trabajo modificado.';
                    $_SESSION['pdt_ultimo'] = ['usuario_id' => $usuario_id, 'tipo_horas' => $tipo_horas, 'tractor' => ($tipo_horas === 'Horas tractos' ? $tractor : '')];
                    $r_ap = mysqli_query($conexion, "SELECT apellido FROM usuarios WHERE id = $usuario_id LIMIT 1");
                    if ($r_ap && $row_ap = mysqli_fetch_assoc($r_ap)) $_SESSION['pdt_ultimo']['usuario_nombre'] = $row_ap['apellido'];
                    header('Location: gestion_trabajos.php?ok=1');
                    exit;
                } else {
                    $mensaje = 'Error al modificar.';
                }
            } else {
                $tractor_val = ($tipo_horas === 'Horas tractos' && $tractor !== '') ? "'$tractor'" : "NULL";
                $sql = "INSERT INTO pdt (usuario_id, tipo_horas, tractor, fecha, horas, cant_gasoil, cambio_aceite, observaciones) 
                        VALUES ($usuario_id, '$tipo_horas', $tractor_val, '$fecha', $horas, " . ($cant_gasoil !== null ? $cant_gasoil : "NULL") . ", $cambio_aceite, '$observaciones')";
                if (mysqli_query($conexion, $sql)) {
                    $mensaje = 'Trabajo guardado.';
                    $_SESSION['pdt_ultimo'] = ['usuario_id' => $usuario_id, 'tipo_horas' => $tipo_horas, 'tractor' => ($tipo_horas === 'Horas tractos' ? $tractor : '')];
                    $r_ap = mysqli_query($conexion, "SELECT apellido FROM usuarios WHERE id = $usuario_id LIMIT 1");
                    if ($r_ap && $row_ap = mysqli_fetch_assoc($r_ap)) $_SESSION['pdt_ultimo']['usuario_nombre'] = $row_ap['apellido'];
                    header('Location: gestion_trabajos.php?ok=1');
                    exit;
                } else {
                    $mensaje = 'Error al guardar.';
                }
            }
        }
    } elseif (isset($_POST['eliminar']) && isset($_POST['pdt_id'])) {
        $pdt_id = (int)$_POST['pdt_id'];
        if ($pdt_id > 0) {
            $res_gasoil = mysqli_query($conexion, "SHOW TABLES LIKE 'gasoil'");
            if ($res_gasoil && mysqli_num_rows($res_gasoil) > 0) {
                mysqli_query($conexion, "DELETE FROM gasoil WHERE pdt_id = $pdt_id");
            }
            if (mysqli_query($conexion, "DELETE FROM pdt WHERE id = $pdt_id")) {
                $mensaje = 'Trabajo eliminado.';
                header('Location: gestion_trabajos.php?ok=1');
                exit;
            } else {
                $mensaje = 'Error al eliminar.';
            }
        }
    }
}

// Cargar para editar
$nombre_editar = '';
$valores_default = null; // Después de un ciclo (?ok=1): Personal, Tipo, Tractor como predeterminados
if (isset($_GET['editar'])) {
    $editar_id = (int)$_GET['editar'];
    if ($editar_id > 0) {
        $res = mysqli_query($conexion, "SELECT p.*, u.apellido AS usuario_nombre FROM pdt p LEFT JOIN usuarios u ON u.id = p.usuario_id WHERE p.id = $editar_id LIMIT 1");
        if ($res && $row = mysqli_fetch_assoc($res)) {
            $editar = $row;
            $nombre_editar = $row['usuario_nombre'] ?? '';
        }
    }
} elseif (isset($_GET['ok']) && isset($_SESSION['pdt_ultimo'])) {
    $valores_default = $_SESSION['pdt_ultimo'];
}

if (isset($_GET['ok'])) {
    $mensaje = 'Operación realizada correctamente.';
}

// Usuarios para el select
$usuarios = [];
$res_usu = mysqli_query($conexion, "SELECT id, apellido FROM usuarios WHERE id != 1 ORDER BY apellido ASC");
if ($res_usu) {
    while ($u = mysqli_fetch_assoc($res_usu)) {
        $usuarios[] = $u;
    }
}

// Listar trabajos
$lista = [];
$res_lista = mysqli_query($conexion, "SELECT p.*, u.apellido AS usuario_nombre FROM pdt p 
    INNER JOIN usuarios u ON u.id = p.usuario_id 
    ORDER BY p.fecha DESC, p.id DESC LIMIT 500");
if ($res_lista) {
    while ($r = mysqli_fetch_assoc($res_lista)) {
        $lista[] = $r;
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Gestión de Trabajos - HHH</title>
    <style>
        body { font-family: 'Segoe UI', sans-serif; background: #f0f2f5; padding: 15px; margin: 0; }
        .contenedor { max-width: 1100px; margin: 0 auto; }
        h1 { color: #007bff; font-size: 18px; margin: 0 0 15px 0; border-bottom: 2px solid #007bff; padding-bottom: 5px; }
        .form-caja { background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.08); margin-bottom: 20px; }
        .form-caja h2 { font-size: 14px; color: #333; margin: 0 0 15px 0; }
        .grid-form { display: grid; grid-template-columns: repeat(auto-fill, minmax(140px, 1fr)); gap: 12px; align-items: end; }
        label { display: block; font-size: 11px; font-weight: bold; margin-bottom: 3px; color: #555; }
        input, select, textarea { width: 100%; padding: 8px; border: 1px solid #ced4da; border-radius: 4px; font-size: 13px; box-sizing: border-box; }
        textarea { min-height: 36px; resize: vertical; }
        .btn { padding: 8px 16px; border: none; border-radius: 4px; cursor: pointer; font-weight: bold; font-size: 12px; }
        .btn-guardar { background: #28a745; color: white; }
        .btn-guardar:hover { background: #218838; }
        .btn-cancelar { background: #6c757d; color: white; text-decoration: none; display: inline-block; }
        .btn-cancelar:hover { background: #5a6268; color: white; }
        .msg { padding: 10px; margin-bottom: 15px; border-radius: 4px; background: #d4edda; color: #155724; }
        .grid-tabla { overflow-x: auto; background: white; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.08); }
        table { width: 100%; border-collapse: collapse; font-size: 12px; }
        th, td { padding: 10px; text-align: left; border-bottom: 1px solid #eee; }
        th { background: #007bff; color: white; font-weight: bold; }
        tr:hover { background: #f8f9fa; }
        .btn-mod { background: #17a2b8; color: white; padding: 4px 10px; font-size: 11px; border-radius: 3px; text-decoration: none; }
        .btn-mod:hover { color: white; }
        .btn-del { background: #dc3545; color: white; padding: 4px 10px; font-size: 11px; border: none; border-radius: 3px; cursor: pointer; }
        .btn-del:hover { background: #c82333; }
        .acciones { display: flex; gap: 6px; }
        .volver { display: inline-block; margin-bottom: 15px; padding: 8px 16px; text-decoration: none; background: #ffc107; color: #333; font-size: 12px; font-weight: bold; border-radius: 4px; }
        .volver:hover { background: #e0a800; color: #333; }
        .buscador-personal { position: relative; }
        .buscador-personal input[type="text"] { padding-right: 36px; }
        .buscador-personal .ico-lupa { position: absolute; right: 10px; top: 50%; transform: translateY(-50%); pointer-events: none; color: #6c757d; font-size: 1rem; }
        .buscador-personal-resultados { position: absolute; top: 100%; left: 0; right: 0; background: white; border: 1px solid #ced4da; border-top: none; max-height: 180px; overflow-y: auto; z-index: 100; display: none; font-size: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.1); }
        .buscador-personal-resultados .item { padding: 8px 10px; cursor: pointer; border-bottom: 1px solid #eee; }
        .buscador-personal-resultados .item:hover { background: #e7f3ff; }
        /* Tractores: John Deere verde, New Holland azul, Massey Ferguson rojo */
        #campoTractor option.tractor-jd { color: #367c2b; font-weight: bold; }
        #campoTractor option.tractor-nh { color: #0066b3; font-weight: bold; }
        #campoTractor option.tractor-mf { color: #c41e3a; font-weight: bold; }
        #campoTractor.tractor-jd { color: #367c2b; font-weight: bold; }
        #campoTractor.tractor-nh { color: #0066b3; font-weight: bold; }
        #campoTractor.tractor-mf { color: #c41e3a; font-weight: bold; }
    </style>
</head>
<body>
<div class="contenedor">
    <a href="index.php" class="volver">← Volver</a>
    <h1>Gestión de Trabajos (PDT)</h1>

    <?php if ($mensaje): ?>
    <div class="msg" id="msgGuardado"><?= htmlspecialchars($mensaje) ?></div>
    <?php endif; ?>

    <div class="form-caja">
        <h2><?= $editar ? 'Modificar trabajo' : 'Nuevo trabajo' ?></h2>
        <form method="POST">
            <?php if ($editar): ?>
            <input type="hidden" name="pdt_id" value="<?= (int)$editar['id'] ?>">
            <?php endif; ?>
            <?php
            $v = $editar ?: ($valores_default ?: []);
            $pers_nombre = $editar ? ($nombre_editar ?? '') : ($valores_default['usuario_nombre'] ?? '');
            $pers_id = $editar ? (int)($editar['usuario_id'] ?? 0) : (int)($valores_default['usuario_id'] ?? 0);
            $tipo_sel = $v['tipo_horas'] ?? 'Horas Comunes';
            $tractor_sel = $v['tractor'] ?? '';
            $mostrar_tractor = ($tipo_sel === 'Horas tractos');
            ?>
            <div class="grid-form">
                <div>
                    <label>Personal</label>
                    <div class="buscador-personal">
                        <input type="text" id="buscadorPersonal" placeholder="Buscar personal..." autocomplete="off" value="<?= htmlspecialchars($pers_nombre) ?>">
                        <span class="ico-lupa" aria-hidden="true">🔍</span>
                        <input type="hidden" name="usuario_id" id="usuario_id" value="<?= $pers_id ?>" required>
                        <div id="resultadosPersonal" class="buscador-personal-resultados"></div>
                    </div>
                </div>
                <div>
                    <label>Tipo</label>
                    <select name="tipo_horas" id="campoTipo">
                        <option value="Horas Comunes" <?= $tipo_sel === 'Horas Comunes' ? 'selected' : '' ?>>Horas Comunes</option>
                        <option value="Horas tractos" <?= $tipo_sel === 'Horas tractos' ? 'selected' : '' ?>>Horas tractos</option>
                    </select>
                </div>
                <div id="tractorGroup" style="display:<?= $mostrar_tractor ? 'block' : 'none' ?>;">
                    <label>Tractor</label>
                    <select name="tractor" id="campoTractor">
                        <option value="">—</option>
                        <optgroup label="John Deere">
                            <option class="tractor-jd" value="John Deere 200 hp" <?= $tractor_sel === 'John Deere 200 hp' ? 'selected' : '' ?>>🚜 John Deere 200 hp</option>
                            <option class="tractor-jd" value="John Deere 110 hp" <?= $tractor_sel === 'John Deere 110 hp' ? 'selected' : '' ?>>🚜 John Deere 110 hp</option>
                        </optgroup>
                        <optgroup label="New Holland">
                            <option class="tractor-nh" value="New Holland TM150" <?= $tractor_sel === 'New Holland TM150' ? 'selected' : '' ?>>🚜 New Holland TM150</option>
                            <option class="tractor-nh" value="New Holland 7630" <?= $tractor_sel === 'New Holland 7630' ? 'selected' : '' ?>>🚜 New Holland 7630</option>
                        </optgroup>
                        <optgroup label="Massey Ferguson">
                            <option class="tractor-mf" value="Massey Ferguson 1165" <?= $tractor_sel === 'Massey Ferguson 1165' ? 'selected' : '' ?>>🚜 Massey Ferguson 1165</option>
                        </optgroup>
                    </select>
                </div>
                <div>
                    <label>Fecha</label>
                    <input type="date" name="fecha" id="campoFecha" value="<?= htmlspecialchars($editar['fecha'] ?? date('Y-m-d')) ?>" required>
                </div>
                <div>
                    <label>Horas</label>
                    <input type="number" name="horas" id="campoHoras" step="0.01" min="0" value="<?= $editar ? (float)($editar['horas'] ?? 0) : '' ?>" required>
                </div>
                <div>
                    <label>Gasoil</label>
                    <input type="number" name="cant_gasoil" id="campoGasoil" step="0.01" min="0" value="<?= isset($editar['cant_gasoil']) && $editar['cant_gasoil'] !== null ? $editar['cant_gasoil'] : '' ?>" placeholder="Opcional">
                </div>
                <div>
                    <label>Cambio aceite</label>
                    <input type="checkbox" name="cambio_aceite" id="campoCambio" value="1" <?= ($editar && isset($editar['cambio_aceite']) && $editar['cambio_aceite'] == 1) ? 'checked' : '' ?>>
                </div>
                <div style="grid-column: span 2;">
                    <label>Observaciones</label>
                    <textarea name="observaciones" id="campoObservaciones" rows="1" onkeydown="if(event.key==='Enter'&&!event.shiftKey){event.preventDefault();var g=document.getElementById('btnGuardar');if(g)g.focus();}"><?= htmlspecialchars($editar['observaciones'] ?? '') ?></textarea>
                </div>
                <div>
                    <button type="submit" name="guardar" id="btnGuardar" class="btn btn-guardar"><?= $editar ? 'Modificar' : 'Guardar' ?></button>
                    <?php if ($editar): ?>
                    <a href="gestion_trabajos.php" class="btn btn-cancelar" style="margin-left: 8px;">Cancelar</a>
                    <?php endif; ?>
                </div>
            </div>
        </form>
    </div>

    <div class="grid-tabla">
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Personal</th>
                    <th>Tipo</th>
                    <th>Tractor</th>
                    <th>Fecha</th>
                    <th>Horas</th>
                    <th>Gasoil</th>
                    <th>Cambio</th>
                    <th>Observaciones</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($lista)): ?>
                <tr><td colspan="10" style="padding: 30px; text-align: center; color: #999;">No hay trabajos cargados</td></tr>
                <?php else: ?>
                <?php foreach ($lista as $p): ?>
                <tr>
                    <td><?= (int)$p['id'] ?></td>
                    <td><?= htmlspecialchars($p['usuario_nombre'] ?? '') ?></td>
                    <td><?= htmlspecialchars($p['tipo_horas'] ?? '-') ?></td>
                    <td><?= htmlspecialchars($p['tractor'] ?? '-') ?></td>
                    <td><?= date('d/m/Y', strtotime($p['fecha'])) ?></td>
                    <td><?= number_format((float)($p['horas'] ?? 0), 2, ',', '.') ?></td>
                    <td><?= isset($p['cant_gasoil']) && $p['cant_gasoil'] !== null ? number_format((float)$p['cant_gasoil'], 2, ',', '.') : '-' ?></td>
                    <td><?= (isset($p['cambio_aceite']) && $p['cambio_aceite'] == 1) ? '✓' : '-' ?></td>
                    <td style="max-width: 150px; overflow: hidden; text-overflow: ellipsis;"><?= htmlspecialchars(mb_substr($p['observaciones'] ?? '', 0, 30)) ?><?= mb_strlen($p['observaciones'] ?? '') > 30 ? '…' : '' ?></td>
                    <td>
                        <div class="acciones">
                            <a href="gestion_trabajos.php?editar=<?= (int)$p['id'] ?>" class="btn-mod">Modificar</a>
                            <form method="POST" style="display:inline;" onsubmit="return confirm('¿Eliminar este trabajo?');">
                                <input type="hidden" name="pdt_id" value="<?= (int)$p['id'] ?>">
                                <button type="submit" name="eliminar" class="btn-del">Eliminar</button>
                            </form>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<script>
(function() {
    var buscador = document.getElementById('buscadorPersonal');
    var usuarioId = document.getElementById('usuario_id');
    var resultados = document.getElementById('resultadosPersonal');
    if (!buscador || !usuarioId || !resultados) return;

    function buscar() {
        var q = (buscador.value || '').trim();
        if (q.length < 1) {
            resultados.style.display = 'none';
            resultados.innerHTML = '';
            usuarioId.value = '';
            return;
        }
        fetch('buscar_personas.php?q=' + encodeURIComponent(q) + '&excluir=1')
            .then(function(r) { return r.json(); })
            .then(function(data) {
                resultados.innerHTML = '';
                if (data && data.length > 0) {
                    data.forEach(function(p) {
                        var div = document.createElement('div');
                        div.className = 'item';
                        div.textContent = p.apellido;
                        div.setAttribute('data-id', p.id);
                        div.setAttribute('data-nombre', p.apellido);
                        div.onclick = function() {
                            usuarioId.value = this.getAttribute('data-id');
                            buscador.value = this.getAttribute('data-nombre');
                            resultados.style.display = 'none';
                        };
                        resultados.appendChild(div);
                    });
                    resultados.style.display = 'block';
                } else {
                    resultados.style.display = 'none';
                }
            })
            .catch(function() { resultados.style.display = 'none'; });
    }

    buscador.addEventListener('input', buscar);
    buscador.addEventListener('focus', function() {
        if (buscador.value.trim().length >= 1) buscar();
    });

    document.addEventListener('click', function(e) {
        if (!buscador.contains(e.target) && !resultados.contains(e.target)) {
            resultados.style.display = 'none';
        }
    });
})();

// Navegación con Enter: Personal primero, izquierda a derecha hasta Guardar. Después del primer ciclo (guardar), foco en Fecha.
var CAMPOS_ORDEN = ['buscadorPersonal', 'campoTipo', 'campoTractor', 'campoFecha', 'campoHoras', 'campoGasoil', 'campoCambio', 'campoObservaciones', 'btnGuardar'];

function isVisible(id) {
    if (id === 'campoTractor') {
        var g = document.getElementById('tractorGroup');
        return g && g.style.display !== 'none';
    }
    return true;
}

document.addEventListener('keydown', function(e) {
    if (e.key !== 'Enter') return;
    var form = document.querySelector('.form-caja form');
    if (!form || !form.contains(document.activeElement)) return;
    var id = document.activeElement.id;
    var idx = CAMPOS_ORDEN.indexOf(id);
    if (idx === -1) return;
    if (id === 'campoObservaciones' && !e.shiftKey) return; // Ya manejado en onkeydown del textarea
    if (id === 'btnGuardar') return; // Dejar que submit
    e.preventDefault();
    if (id === 'buscadorPersonal' && document.getElementById('resultadosPersonal').style.display === 'block' && document.querySelectorAll('#resultadosPersonal .item').length > 0) {
        var primer = document.querySelector('#resultadosPersonal .item');
        if (primer) {
            document.getElementById('usuario_id').value = primer.getAttribute('data-id');
            document.getElementById('buscadorPersonal').value = primer.getAttribute('data-nombre');
            document.getElementById('resultadosPersonal').style.display = 'none';
        }
    }
    var next = null;
    for (var i = idx + 1; i < CAMPOS_ORDEN.length; i++) {
        var nextId = CAMPOS_ORDEN[i];
        if (isVisible(nextId)) {
            next = document.getElementById(nextId);
            break;
        }
    }
    if (next) next.focus();
});

// Si vino ?ok=1: cartel 2 segundos, luego foco en Fecha (después del primer ciclo). Si es nuevo trabajo, foco inicial en Personal.
(function() {
    var params = new URLSearchParams(window.location.search);
    var campoFecha = document.getElementById('campoFecha');
    var buscadorPersonal = document.getElementById('buscadorPersonal');
    var msg = document.getElementById('msgGuardado');
    if (params.get('ok') === '1') {
        if (campoFecha) setTimeout(function() {
            if (msg) msg.style.display = 'none';
            campoFecha.focus();
        }, 2000);
    } else if (!params.get('editar') && buscadorPersonal) {
        buscadorPersonal.focus();
    }
})();

// Mostrar tractor solo cuando Tipo = Horas tractos
(function() {
    var tipo = document.getElementById('campoTipo');
    var tractorGroup = document.getElementById('tractorGroup');
    if (!tipo || !tractorGroup) return;
    function actualizarTractor() {
        tractorGroup.style.display = (tipo.value === 'Horas tractos') ? 'block' : 'none';
    }
    tipo.addEventListener('change', actualizarTractor);
    actualizarTractor();
})();

// Aplicar color del tractor seleccionado al select
(function() {
    var sel = document.getElementById('campoTractor');
    if (!sel) return;
    function aplicarColor() {
        var opt = sel.options[sel.selectedIndex];
        sel.classList.remove('tractor-jd', 'tractor-nh', 'tractor-mf');
        if (opt && opt.classList.contains('tractor-jd')) sel.classList.add('tractor-jd');
        else if (opt && opt.classList.contains('tractor-nh')) sel.classList.add('tractor-nh');
        else if (opt && opt.classList.contains('tractor-mf')) sel.classList.add('tractor-mf');
    }
    sel.addEventListener('change', aplicarColor);
    aplicarColor();
})();
</script>
<?php include 'timeout_sesion_inc.php'; ?>
</body>
</html>
