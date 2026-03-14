<?php
include 'db.php';
include 'verificar_sesion.php';
include 'crear_tabla_cosecha.php';

$nivelAcceso = (int)($_SESSION['acceso_nivel'] ?? 0);
$usuario = (string)($_SESSION['acceso_usuario'] ?? '');
$es_usuario_zafra = ($nivelAcceso === 0 && stripos($usuario, 'zafra') !== false);
if ($nivelAcceso < 2 && !$es_usuario_zafra) {
    header('Location: index.php?msg=sin_permiso');
    exit;
}

$anio_actual = (int)date('Y');
$anio_zafra = $anio_actual;
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['zafra'])) {
    $anio_zafra = (int)$_GET['zafra'];
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['anio_zafra'])) {
    $anio_zafra = (int)$_POST['anio_zafra'];
}
if ($anio_zafra < 2000 || $anio_zafra > 2100) $anio_zafra = $anio_actual;

// Exportar a Excel (HTML con alineación central) cuando se solicita - solo nivel 3
if (isset($_GET['exportar']) && $_GET['exportar'] === 'excel') {
    if ($nivelAcceso < 3) {
        header('Location: cosecha.php?zafra=' . $anio_zafra . '&msg=sin_permiso');
        exit;
    }
    $r_export = mysqli_query($conexion, "SELECT fecha, hora, tickets, remito, viaje, camion, finca, variedad FROM cosecha_hojas_ruta WHERE anio_zafra = $anio_zafra ORDER BY fecha DESC, hora DESC, id DESC");
    $nombre = 'cosecha_zafra_' . $anio_zafra . '.xls';
    header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $nombre . '"');
    header('Cache-Control: no-cache, must-revalidate');
    echo "\xEF\xBB\xBF"; // UTF-8 BOM
    echo '<html xmlns:x="urn:schemas-microsoft-com:office:excel"><head><meta charset="UTF-8"></head><body><table border="1" style="border-collapse:collapse;">';
    echo '<tr style="background:#6f42c1;color:white;">';
    foreach (['Fecha', 'Hora', 'Tickets', 'Remito', 'Viaje', 'Camion', 'Finca', 'Variedad'] as $h) {
        echo '<th style="text-align:center;padding:6px;">' . htmlspecialchars($h, ENT_QUOTES | ENT_HTML5, 'UTF-8') . '</th>';
    }
    echo '</tr>';
    if ($r_export) {
        while ($row = mysqli_fetch_assoc($r_export)) {
            $hora = $row['hora'] ? substr($row['hora'], 0, 5) : '-';
            echo '<tr>';
            foreach ([$row['fecha'], $hora, $row['tickets'] ?? '-', $row['remito'] ?? '-', $row['viaje'] ?? '-', $row['camion'] ?? '-', $row['finca'] ?? '-', $row['variedad'] ?? '-'] as $cel) {
                echo '<td style="text-align:center;padding:6px;">' . htmlspecialchars((string)$cel, ENT_QUOTES | ENT_HTML5, 'UTF-8') . '</td>';
            }
            echo '</tr>';
        }
    }
    echo '</table></body></html>';
    exit;
}

$mensaje = '';
$editar_id = null;
$fila_edit = null;
if (isset($_GET['msg']) && $_GET['msg'] === 'ok') $mensaje = 'Registro guardado.';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['guardar'])) {
        $fecha = trim($_POST['fecha'] ?? '');
        $hora = trim($_POST['hora'] ?? '');
        $tickets = trim($_POST['tickets'] ?? '');
        $remito = trim($_POST['remito'] ?? '');
        $viaje = trim($_POST['viaje'] ?? '');
        $camion = trim($_POST['camion'] ?? '');
        $finca_sel = trim($_POST['finca_sel'] ?? '');
        $finca_nueva = trim($_POST['finca_nueva'] ?? '');
        $finca = ($finca_sel === '__otro__') ? $finca_nueva : $finca_sel;
        $variedad_sel = trim($_POST['variedad_sel'] ?? '');
        $variedad_nueva = trim($_POST['variedad_nueva'] ?? '');
        $variedad = ($variedad_sel === '__otro__') ? $variedad_nueva : $variedad_sel;
        $anio = (int)($_POST['anio_zafra'] ?? $anio_zafra);

        if ($fecha === '') {
            $mensaje = 'Ingrese la fecha.';
        } else {
            $fecha_esc = mysqli_real_escape_string($conexion, $fecha);
            $hora_esc = $hora !== '' ? "'" . mysqli_real_escape_string($conexion, $hora) . "'" : "NULL";
            $tickets_esc = mysqli_real_escape_string($conexion, $tickets);
            $remito_esc = mysqli_real_escape_string($conexion, $remito);
            $viaje_esc = mysqli_real_escape_string($conexion, $viaje);
            $camion_esc = mysqli_real_escape_string($conexion, $camion);
            $finca_esc = mysqli_real_escape_string($conexion, $finca);
            $variedad_esc = mysqli_real_escape_string($conexion, $variedad);

            if (isset($_POST['id']) && (int)$_POST['id'] > 0) {
                $id = (int)$_POST['id'];
                $r_check = mysqli_query($conexion, "SELECT fecha FROM cosecha_hojas_ruta WHERE id = $id LIMIT 1");
                $puede_actualizar = ($nivelAcceso >= 3);
                if (!$puede_actualizar && $r_check && $row_check = mysqli_fetch_assoc($r_check)) {
                    $fecha_orig = substr(trim($row_check['fecha'] ?? ''), 0, 10);
                    $hoy_check = date('Y-m-d');
                    $ayer_check = date('Y-m-d', strtotime('-1 day'));
                    $puede_actualizar = ($fecha_orig === $hoy_check || $fecha_orig === $ayer_check);
                }
                if (!$puede_actualizar) {
                    $mensaje = 'Solo se puede modificar registros del día de la fecha o del día anterior.';
                } else {
                $sql = "UPDATE cosecha_hojas_ruta SET anio_zafra=$anio, fecha='$fecha_esc', hora=$hora_esc, tickets='$tickets_esc', remito='$remito_esc', viaje='$viaje_esc', camion='$camion_esc', finca='$finca_esc', variedad='$variedad_esc' WHERE id=$id";
                if (mysqli_query($conexion, $sql)) {
                    header('Location: cosecha.php?zafra=' . $anio . '&msg=ok');
                    exit;
                } else {
                    $mensaje = 'Error: ' . mysqli_error($conexion);
                }
                }
            } else {
                $sql = "INSERT INTO cosecha_hojas_ruta (anio_zafra, fecha, hora, tickets, remito, viaje, camion, finca, variedad) VALUES ($anio, '$fecha_esc', $hora_esc, '$tickets_esc', '$remito_esc', '$viaje_esc', '$camion_esc', '$finca_esc', '$variedad_esc')";
                if (mysqli_query($conexion, $sql)) {
                    header('Location: cosecha.php?zafra=' . $anio . '&msg=ok');
                    exit;
                } else {
                    $mensaje = 'Error: ' . mysqli_error($conexion);
                }
            }
        }
    } elseif (isset($_POST['editar'])) {
        $editar_id = (int)$_POST['id'];
        $r = mysqli_query($conexion, "SELECT * FROM cosecha_hojas_ruta WHERE id = $editar_id LIMIT 1");
        if ($r && $fila_edit = mysqli_fetch_assoc($r)) {
            $fecha_reg = substr(trim($fila_edit['fecha'] ?? ''), 0, 10);
            $hoy = date('Y-m-d');
            $ayer = date('Y-m-d', strtotime('-1 day'));
            if ($nivelAcceso < 3 && $fecha_reg !== $hoy && $fecha_reg !== $ayer) {
                $mensaje = 'Solo se puede modificar registros del día de la fecha o del día anterior.';
                $editar_id = null;
                $fila_edit = null;
            }
        } else {
            $editar_id = null;
            $fila_edit = null;
        }
    } elseif (isset($_POST['eliminar'])) {
        $id = (int)$_POST['id'];
        $puede_eliminar = ($nivelAcceso >= 3);
        if (!$puede_eliminar) {
            $r = mysqli_query($conexion, "SELECT fecha FROM cosecha_hojas_ruta WHERE id = $id LIMIT 1");
            if ($r && $row = mysqli_fetch_assoc($r)) {
                $fecha_reg = substr(trim($row['fecha'] ?? ''), 0, 10);
                $hoy = date('Y-m-d');
                $ayer = date('Y-m-d', strtotime('-1 day'));
                $puede_eliminar = ($fecha_reg === $hoy || $fecha_reg === $ayer);
            }
        }
        if ($puede_eliminar && mysqli_query($conexion, "DELETE FROM cosecha_hojas_ruta WHERE id = $id")) {
            $mensaje = 'Registro eliminado.';
        } else {
            $mensaje = $puede_eliminar ? 'Error al eliminar.' : 'Solo se puede eliminar registros del día de la fecha o del día anterior.';
        }
    }
}

$lista = mysqli_query($conexion, "SELECT id, anio_zafra, fecha, hora, tickets, remito, viaje, camion, finca, variedad, fecha_creacion FROM cosecha_hojas_ruta WHERE anio_zafra = $anio_zafra ORDER BY fecha DESC, hora DESC, id DESC");
$hoy = date('Y-m-d');
$ayer = date('Y-m-d', strtotime('-1 day'));
$puede_modificar_eliminar = function($fecha_reg) use ($nivelAcceso, $hoy, $ayer) {
    if ($nivelAcceso >= 3) return true;
    if (empty($fecha_reg)) return false;
    $f = substr(trim($fecha_reg), 0, 10); // Y-m-d
    return ($f === $hoy || $f === $ayer);
};

// Fincas fijas por defecto + las agregadas por "Nueva finca" (desde BD)
$fincas_fijas = ['Finca 6', 'Finca 4', 'Finca 7', 'Finca 5', 'Finca 2', 'Finca 10', 'Finca 11', 'Finca 47', 'Finca 29'];
$fincas_lista = $fincas_fijas;
$r_fincas = mysqli_query($conexion, "SELECT DISTINCT finca FROM cosecha_hojas_ruta WHERE finca != '' AND finca IS NOT NULL ORDER BY finca");
if ($r_fincas) {
    while ($row = mysqli_fetch_assoc($r_fincas)) {
        $fn = trim($row['finca']);
        if ($fn !== '' && !in_array($fn, $fincas_lista)) {
            $fincas_lista[] = $fn;
        }
    }
}

// Variedades fijas para elegir (03/12, 02/22, 06/7) + opción otra
$variedades_fijas = ['03/12', '02/22', '06/7'];

// Último registro para valores por defecto (solo cuando no estamos editando)
        $def_tickets = '';
        $def_remito = '';
        $def_viaje = '';
        $def_finca = '';
        $def_variedad = '';
        if (!$editar_id) {
            $r_ult = mysqli_query($conexion, "SELECT tickets, remito, viaje, finca, variedad FROM cosecha_hojas_ruta WHERE anio_zafra = $anio_zafra ORDER BY fecha DESC, hora DESC, id DESC LIMIT 1");
            if ($r_ult && $ult = mysqli_fetch_assoc($r_ult)) {
                $def_finca = $ult['finca'] ?? '';
                $def_variedad = $ult['variedad'] ?? '';
                $t = trim($ult['tickets'] ?? '');
                if ($t !== '' && preg_match('/(\d+)\s*$/', $t, $mt)) {
                    $def_tickets = preg_replace('/(\d+)\s*$/', (string)((int)$mt[1] + 1), $t);
                } else {
                    $def_tickets = (is_numeric($t) && $t !== '') ? (string)((int)$t + 1) : $t;
                }
                $r = trim($ult['remito'] ?? '');
                if ($r !== '' && preg_match('/(\d+)\s*$/', $r, $mr)) {
                    $def_remito = preg_replace('/(\d+)\s*$/', (string)((int)$mr[1] + 1), $r);
                } else {
                    $def_remito = (is_numeric($r) && $r !== '') ? (string)((int)$r + 1) : $r;
                }
                $v = trim($ult['viaje'] ?? '');
                if ($v !== '' && preg_match('/(\d+)\s*$/', $v, $mv)) {
                    $def_viaje = preg_replace('/(\d+)\s*$/', (string)((int)$mv[1] + 1), $v);
                } else {
                    $def_viaje = (is_numeric($v) && $v !== '') ? (string)((int)$v + 1) : $v;
                }
            }
        }
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Cosecha - Hojas de ruta</title>
    <style>
        * { box-sizing: border-box; }
        body { font-family: Arial, sans-serif; margin: 15px; background: #f5f5f5; font-size: 12px; -webkit-text-size-adjust: 100%; }
        .container { max-width: 1200px; margin: 0 auto; background: white; padding: 15px; border-radius: 6px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h2 { color: #6f42c1; margin-top: 0; margin-bottom: 12px; font-size: 18px; }
        .encabezado { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 15px; flex-wrap: wrap; gap: 10px; }
        .zafra-wrap { margin-left: auto; display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
        .zafra-wrap label { font-size: 11px; margin-right: 6px; }
        .zafra-wrap select { padding: 6px 10px; font-size: 12px; border: 1px solid #ddd; border-radius: 4px; }
        .mensaje { padding: 8px; margin-bottom: 12px; border-radius: 4px; font-size: 12px; }
        .mensaje.ok { background: #d4edda; color: #155724; }
        .mensaje.error { background: #f8d7da; color: #721c24; }
        label { display: block; margin-bottom: 2px; font-weight: bold; font-size: 11px; }
        input, select { padding: 6px; border: 1px solid #ddd; border-radius: 4px; font-size: 12px; }
        .form-row { display: flex; gap: 12px; margin-bottom: 10px; flex-wrap: wrap; align-items: flex-end; }
        .form-row .campo { flex: 1; min-width: 100px; }
        .finca-variedad { display: flex; gap: 4px; flex: 1; min-width: 180px; }
        .finca-variedad .campo { flex: 1; min-width: 0; }
        .finca-variedad .campo-variedad { flex: 0 0 auto; min-width: 140px; }
        .variedad-row { display: flex; align-items: flex-end; gap: 6px; flex-wrap: nowrap; }
        .variedad-row #variedadOtroWrap { display: none; margin-top: 0; }
        .variedad-row #variedadOtroWrap.visible { display: inline-block !important; }
        .btn { padding: 6px 12px; border: none; border-radius: 4px; cursor: pointer; font-size: 11px; font-weight: bold; text-decoration: none; display: inline-block; }
        .btn-primary { background: #6f42c1; color: white; }
        .btn-secondary { background: #6c757d; color: white; }
        .btn-danger { background: #dc3545; color: white; }
        table { width: 100%; border-collapse: collapse; margin-top: 15px; font-size: 11px; }
        th, td { border: 1px solid #ddd; padding: 6px 8px; text-align: left; }
        th { background: #6f42c1; color: white; }
        tr:nth-child(even) { background: #f9f9f9; }
        .acciones { display: flex; gap: 4px; flex-wrap: wrap; }
        .volver { margin-top: 15px; }
        .wrap-tabla { overflow-x: auto; max-height: 400px; overflow-y: scroll; -webkit-overflow-scrolling: touch; }
        @media (max-width: 768px) {
            body { margin: 10px; font-size: 14px; }
            .container { padding: 12px; }
            .encabezado { flex-direction: column; align-items: stretch; }
            .zafra-wrap { margin-left: 0; }
            .form-row { flex-direction: column; gap: 12px; }
            .form-row .campo { min-width: 100%; }
            .finca-variedad { flex-direction: column; min-width: 100%; }
            .finca-variedad .campo-variedad { flex: 1 1 auto; transform: none; }
            .variedad-row { flex-wrap: wrap; }
            input, select { padding: 10px 12px; font-size: 16px; min-height: 44px; }
            .btn { padding: 12px 16px; font-size: 14px; min-height: 44px; }
            .acciones .btn { padding: 8px 12px; font-size: 12px; min-height: 36px; }
            .wrap-tabla { max-height: 50vh; overflow-y: scroll; }
            table { font-size: 12px; min-width: 500px; }
        }
    </style>
</head>
<body onkeydown="var e=event||window.event;if((e.keyCode||e.which)===27){e.preventDefault();window.location.href='<?= $es_usuario_zafra ? 'logout.php' : 'index.php' ?>';return false;}">

<div class="container">
    <div class="encabezado">
        <div>
            <h2>Cosecha - Hojas de ruta</h2>
            <a href="<?= $es_usuario_zafra ? 'logout.php' : 'index.php' ?>" class="btn btn-secondary" style="margin-top:4px;"><?= $es_usuario_zafra ? 'Salir' : '← Volver a página principal' ?></a>
        </div>
        <div class="zafra-wrap" style="display:flex; align-items:center; gap:8px;">
            <form method="GET" style="display:inline;">
                <label for="selZafra">Zafra:</label>
                <select name="zafra" id="selZafra" onchange="this.form.submit()">
                    <?php for ($a = $anio_actual + 1; $a >= $anio_actual - 5; $a--): ?>
                        <option value="<?= $a ?>" <?= $anio_zafra == $a ? 'selected' : '' ?>><?= $a ?></option>
                    <?php endfor; ?>
                </select>
            </form>
            <?php if ($nivelAcceso >= 3): ?><a href="cosecha.php?zafra=<?= $anio_zafra ?>&exportar=excel" class="btn btn-primary">Exportar Excel</a><?php endif; ?>
        </div>
    </div>

    <?php if ($mensaje): ?>
        <div class="mensaje <?= strpos($mensaje, 'Error') !== false ? 'error' : 'ok' ?>"><?= htmlspecialchars($mensaje) ?></div>
    <?php endif; ?>

    <form method="POST" action="cosecha.php?zafra=<?= $anio_zafra ?>" class="form-nav-enter" id="formCosecha">
        <?php if ($editar_id): ?>
            <input type="hidden" name="id" value="<?= $editar_id ?>">
        <?php endif; ?>
        <input type="hidden" name="anio_zafra" value="<?= $anio_zafra ?>">
        <div class="form-row">
            <div class="campo" style="flex:0 0 120px;">
                <label>Fecha</label>
                <input type="date" name="fecha" value="<?= $fila_edit ? htmlspecialchars($fila_edit['fecha']) : date('Y-m-d') ?>" required>
            </div>
            <div class="campo" style="flex:0 0 100px;">
                <label>Hora</label>
                <input type="time" name="hora" value="<?= $fila_edit ? substr($fila_edit['hora'] ?? '', 0, 5) : '' ?>">
            </div>
            <div class="campo" style="flex:0 0 100px;">
                <label>Tickets</label>
                <input type="text" name="tickets" value="<?= $fila_edit ? htmlspecialchars($fila_edit['tickets'] ?? '') : htmlspecialchars($def_tickets) ?>" placeholder="">
            </div>
            <div class="campo" style="flex:0 0 100px;">
                <label>Remito</label>
                <input type="text" name="remito" value="<?= $fila_edit ? htmlspecialchars($fila_edit['remito'] ?? '') : htmlspecialchars($def_remito) ?>" placeholder="">
            </div>
            <div class="campo" style="flex:0 0 80px;">
                <label>Viaje</label>
                <input type="text" name="viaje" value="<?= $fila_edit ? htmlspecialchars($fila_edit['viaje'] ?? '') : htmlspecialchars($def_viaje) ?>" placeholder="">
            </div>
            <div class="campo" style="flex:0 0 120px;">
                <label>Camion</label>
                <input type="text" name="camion" value="<?= $fila_edit ? htmlspecialchars($fila_edit['camion'] ?? '') : '' ?>" placeholder="">
            </div>
            <div class="finca-variedad">
                <div class="campo">
                    <label>Finca</label>
                    <select name="finca_sel" id="selFinca" onchange="document.getElementById('fincaOtroWrap').style.display=(this.value==='__otro__')?'block':'none';">
                        <?php 
                        $finca_sel = $fila_edit ? ($fila_edit['finca'] ?? '') : $def_finca;
                        if ($finca_sel === '' && count($fincas_lista) > 0) $finca_sel = $fincas_lista[0];
                        foreach ($fincas_lista as $fn): ?>
                        <option value="<?= htmlspecialchars($fn) ?>" <?= ($finca_sel === $fn) ? 'selected' : '' ?>><?= htmlspecialchars($fn) ?></option>
                        <?php endforeach; ?>
                        <option value="__otro__">+ Nueva finca</option>
                    </select>
                    <div id="fincaOtroWrap" style="display:none; margin-top:4px;">
                        <input type="text" name="finca_nueva" id="fincaNueva" placeholder="Escriba la nueva finca" value="">
                    </div>
                </div>
                <?php
                $variedad_actual = $fila_edit ? ($fila_edit['variedad'] ?? '') : $def_variedad;
                $es_otra_variedad = ($variedad_actual !== '' && !in_array($variedad_actual, $variedades_fijas));
                $variedad_sel_val = $es_otra_variedad ? '__otro__' : $variedad_actual;
                $variedad_nueva_val = $es_otra_variedad ? $variedad_actual : '';
                ?>
                <div class="campo campo-variedad">
                    <label>Variedad</label>
                    <div class="variedad-row">
                        <select name="variedad_sel" id="selVariedad" onchange="var w=document.getElementById('variedadOtroWrap');w.classList.toggle('visible',this.value==='__otro__');">
                            <option value="">--</option>
                            <?php foreach ($variedades_fijas as $vn): ?>
                            <option value="<?= htmlspecialchars($vn) ?>" <?= ($variedad_sel_val === $vn) ? 'selected' : '' ?>><?= htmlspecialchars($vn) ?></option>
                            <?php endforeach; ?>
                            <option value="__otro__" <?= ($variedad_sel_val === '__otro__') ? 'selected' : '' ?>>Otra variedad</option>
                        </select>
                        <div id="variedadOtroWrap" class="<?= $es_otra_variedad ? 'visible' : '' ?>" style="margin-top:0;">
                            <input type="text" name="variedad_nueva" id="variedadNueva" placeholder="Escriba la variedad" value="<?= htmlspecialchars($variedad_nueva_val) ?>" style="min-width:100px;">
                        </div>
                    </div>
                </div>
            </div>
            <div class="campo" style="flex:0; align-self:flex-end;">
                <label style="visibility:hidden;">.</label>
                <button type="submit" name="guardar" class="btn btn-primary"><?= $editar_id ? 'Actualizar' : 'Guardar' ?></button>
                <?php if ($editar_id): ?>
                    <a href="cosecha.php?zafra=<?= $anio_zafra ?>" class="btn btn-secondary">Cancelar</a>
                <?php endif; ?>
            </div>
        </div>
    </form>

    <h3 style="margin-top: 25px; font-size: 14px; color: #333;">Registros zafra <?= $anio_zafra ?></h3>
    <div class="wrap-tabla">
        <table>
            <thead>
                <tr>
                    <th>Fecha</th>
                    <th>Hora</th>
                    <th>Tickets</th>
                    <th>Remito</th>
                    <th>Viaje</th>
                    <th>Camion</th>
                    <th>Finca</th>
                    <th>Variedad</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($lista && mysqli_num_rows($lista) > 0): ?>
                    <?php while ($f = mysqli_fetch_assoc($lista)): ?>
                        <tr>
                            <td><?= htmlspecialchars($f['fecha']) ?></td>
                            <td><?= $f['hora'] ? substr($f['hora'], 0, 5) : '-' ?></td>
                            <td><?= htmlspecialchars($f['tickets'] ?? '-') ?></td>
                            <td><?= htmlspecialchars($f['remito'] ?? '-') ?></td>
                            <td><?= htmlspecialchars($f['viaje'] ?? '-') ?></td>
                            <td><?= htmlspecialchars($f['camion'] ?? '-') ?></td>
                            <td><?= htmlspecialchars($f['finca'] ?? '-') ?></td>
                            <td><?= htmlspecialchars($f['variedad'] ?? '-') ?></td>
                            <td>
                                <div class="acciones">
                                    <?php $puede = $puede_modificar_eliminar($f['fecha'] ?? ''); ?>
                                    <?php if ($puede): ?>
                                    <form method="POST" action="cosecha.php?zafra=<?= $anio_zafra ?>" style="display:inline;">
                                        <input type="hidden" name="id" value="<?= $f['id'] ?>">
                                        <input type="hidden" name="anio_zafra" value="<?= $anio_zafra ?>">
                                        <button type="submit" name="editar" class="btn btn-secondary">Modificar</button>
                                    </form>
                                    <form method="POST" action="cosecha.php?zafra=<?= $anio_zafra ?>" style="display:inline;" onsubmit="return confirm('¿Eliminar este registro?');">
                                        <input type="hidden" name="id" value="<?= $f['id'] ?>">
                                        <input type="hidden" name="anio_zafra" value="<?= $anio_zafra ?>">
                                        <button type="submit" name="eliminar" class="btn btn-danger">Eliminar</button>
                                    </form>
                                    <?php else: ?>
                                    <span style="font-size:10px; color:#999;">—</span>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="9" style="text-align:center; padding:15px; color:#666;">No hay registros para esta zafra.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <p class="volver">
        <a href="<?= $es_usuario_zafra ? 'logout.php' : 'index.php' ?>" class="btn btn-secondary"><?= $es_usuario_zafra ? 'Salir' : '← Volver a página principal' ?></a>
    </p>
</div>
<?php include 'nav_enter_form_inc.php'; ?>
</body>
</html>
