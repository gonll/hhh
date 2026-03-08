<?php
/**
 * Cosecha - Hojas de ruta - Versión móvil
 * Acceso: usuario zafra / clave herrera (solo desde celular)
 */
session_start();
include 'db.php';
include 'crear_tabla_cosecha.php';

function es_movil() {
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
    return (bool)preg_match('/Mobile|Android|iPhone|iPad|iPod|webOS|BlackBerry|IEMobile|Opera Mini/i', $ua);
}

if (!es_movil()) {
    header('Location: login.php');
    exit;
}

// Login zafra/herrera
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login_zafra'])) {
    $u = trim($_POST['usuario'] ?? '');
    $c = $_POST['clave'] ?? '';
    if ($u === 'zafra' && $c === 'herrera') {
        $_SESSION['cosecha_zafra'] = true;
        $z = isset($_GET['zafra']) ? '?zafra=' . (int)$_GET['zafra'] : '';
        header('Location: cosecha_cel.php' . $z);
        exit;
    }
    $login_error = true;
}

if (!isset($_SESSION['cosecha_zafra'])) {
    ?>
    <!DOCTYPE html>
    <html lang="es">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
        <title>Cosecha - Ingreso</title>
        <style>
            * { box-sizing: border-box; }
            body { font-family: Arial, sans-serif; margin: 0; padding: 20px; background: #f5f5f5; min-height: 100vh; display: flex; align-items: center; justify-content: center; font-size: 16px; }
            .card { background: white; padding: 24px; border-radius: 8px; box-shadow: 0 2px 15px rgba(0,0,0,0.1); width: 100%; max-width: 320px; }
            h2 { color: #6f42c1; margin: 0 0 20px 0; font-size: 18px; text-align: center; }
            label { display: block; margin-bottom: 6px; font-weight: bold; font-size: 14px; }
            input { width: 100%; padding: 12px; font-size: 16px; border: 1px solid #ddd; border-radius: 6px; margin-bottom: 16px; }
            button { width: 100%; padding: 14px; background: #6f42c1; color: white; border: none; border-radius: 6px; font-size: 16px; font-weight: bold; cursor: pointer; }
            .error { background: #f8d7da; color: #721c24; padding: 10px; border-radius: 6px; margin-bottom: 16px; font-size: 14px; }
        </style>
    </head>
    <body>
    <div class="card">
        <h2>Cosecha - Hojas de ruta</h2>
        <?php if (isset($login_error)): ?><div class="error">Usuario o clave incorrectos.</div><?php endif; ?>
        <form method="POST" action="cosecha_cel.php<?= isset($_GET['zafra']) ? '?zafra='.(int)$_GET['zafra'] : '' ?>">
            <label>Usuario</label>
            <input type="text" name="usuario" required autocomplete="username" placeholder="Usuario">
            <label>Clave</label>
            <input type="password" name="clave" required autocomplete="current-password" placeholder="Clave">
            <button type="submit" name="login_zafra">ENTRAR</button>
        </form>
    </div>
    </body>
    </html>
    <?php
    exit;
}

// Salir
if (isset($_GET['salir'])) {
    unset($_SESSION['cosecha_zafra']);
    header('Location: cosecha_cel.php');
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

// Exportar Excel
if (isset($_GET['exportar']) && $_GET['exportar'] === 'excel') {
    $r_export = mysqli_query($conexion, "SELECT fecha, hora, tickets, remito, viaje, camion, finca, variedad FROM cosecha_hojas_ruta WHERE anio_zafra = $anio_zafra ORDER BY fecha DESC, hora DESC, id DESC");
    $nombre = 'cosecha_zafra_' . $anio_zafra . '.xls';
    header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $nombre . '"');
    echo "\xEF\xBB\xBF";
    echo '<html xmlns:x="urn:schemas-microsoft-com:office:excel"><head><meta charset="UTF-8"></head><body><table border="1" style="border-collapse:collapse;">';
    echo '<tr style="background:#6f42c1;color:white;">';
    foreach (['Fecha', 'Hora', 'Tickets', 'Remito', 'Viaje', 'Camion', 'Finca', 'Variedad'] as $h) {
        echo '<th style="text-align:center;padding:6px;">' . htmlspecialchars($h) . '</th>';
    }
    echo '</tr>';
    if ($r_export) {
        while ($row = mysqli_fetch_assoc($r_export)) {
            $hora = $row['hora'] ? substr($row['hora'], 0, 5) : '-';
            echo '<tr>';
            foreach ([$row['fecha'], $hora, $row['tickets'] ?? '-', $row['remito'] ?? '-', $row['viaje'] ?? '-', $row['camion'] ?? '-', $row['finca'] ?? '-', $row['variedad'] ?? '-'] as $cel) {
                echo '<td style="text-align:center;padding:6px;">' . htmlspecialchars($cel) . '</td>';
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

// En móvil: solo editar/borrar registros del día de la fecha y el día anterior
$hoy = date('Y-m-d');
$ayer = date('Y-m-d', strtotime('-1 day'));
$fechas_editables = [$hoy, $ayer];
$puede_editar_fecha = function($f) use ($fechas_editables) { return in_array($f ?? '', $fechas_editables); };

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
                if (!$r_check || !($row_check = mysqli_fetch_assoc($r_check)) || !$puede_editar_fecha($row_check['fecha'])) {
                    $mensaje = 'Solo se pueden editar registros del día de hoy o ayer.';
                } else {
                $sql = "UPDATE cosecha_hojas_ruta SET anio_zafra=$anio, fecha='$fecha_esc', hora=$hora_esc, tickets='$tickets_esc', remito='$remito_esc', viaje='$viaje_esc', camion='$camion_esc', finca='$finca_esc', variedad='$variedad_esc' WHERE id=$id";
                if (mysqli_query($conexion, $sql)) {
                    header('Location: cosecha_cel.php?zafra=' . $anio . '&msg=ok');
                    exit;
                } else {
                    $mensaje = 'Error: ' . mysqli_error($conexion);
                }
                }
            } else {
                $sql = "INSERT INTO cosecha_hojas_ruta (anio_zafra, fecha, hora, tickets, remito, viaje, camion, finca, variedad) VALUES ($anio, '$fecha_esc', $hora_esc, '$tickets_esc', '$remito_esc', '$viaje_esc', '$camion_esc', '$finca_esc', '$variedad_esc')";
                if (mysqli_query($conexion, $sql)) {
                    header('Location: cosecha_cel.php?zafra=' . $anio . '&msg=ok');
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
            if (!$puede_editar_fecha($fila_edit['fecha'])) {
                $mensaje = 'Solo se pueden editar registros del día de hoy o ayer.';
                $editar_id = null;
                $fila_edit = null;
            }
        } else {
            $editar_id = null;
            $fila_edit = null;
        }
    } elseif (isset($_POST['eliminar'])) {
        $id = (int)$_POST['id'];
        $r_check = mysqli_query($conexion, "SELECT fecha FROM cosecha_hojas_ruta WHERE id = $id LIMIT 1");
        if (!$r_check || !($row_check = mysqli_fetch_assoc($r_check)) || !$puede_editar_fecha($row_check['fecha'])) {
            $mensaje = 'Solo se pueden eliminar registros del día de hoy o ayer.';
        } elseif (mysqli_query($conexion, "DELETE FROM cosecha_hojas_ruta WHERE id = $id")) {
            $mensaje = 'Registro eliminado.';
        } else {
            $mensaje = 'Error al eliminar.';
        }
    }
}

$lista = mysqli_query($conexion, "SELECT * FROM cosecha_hojas_ruta WHERE anio_zafra = $anio_zafra ORDER BY fecha DESC, hora DESC, id DESC");

$fincas_lista = [];
$r_fincas = mysqli_query($conexion, "SELECT DISTINCT finca FROM cosecha_hojas_ruta WHERE finca != '' AND finca IS NOT NULL ORDER BY finca");
if ($r_fincas) {
    while ($row = mysqli_fetch_assoc($r_fincas)) {
        $fincas_lista[] = $row['finca'];
    }
}

$variedades_lista = [];
$r_variedades = mysqli_query($conexion, "SELECT DISTINCT variedad FROM cosecha_hojas_ruta WHERE variedad != '' AND variedad IS NOT NULL ORDER BY variedad");
if ($r_variedades) {
    while ($row = mysqli_fetch_assoc($r_variedades)) {
        $variedades_lista[] = $row['variedad'];
    }
}

$def_tickets = $def_remito = $def_viaje = $def_finca = $def_variedad = '';
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
        body { font-family: Arial, sans-serif; margin: 0; padding: 12px; background: #f5f5f5; font-size: 14px; -webkit-text-size-adjust: 100%; }
        .container { max-width: 100%; background: white; padding: 12px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.08); }
        h2 { color: #6f42c1; margin: 0 0 12px 0; font-size: 18px; }
        .encabezado { display: flex; flex-wrap: wrap; gap: 10px; align-items: center; justify-content: space-between; margin-bottom: 12px; }
        .zafra-wrap { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
        .zafra-wrap label { font-size: 13px; }
        .zafra-wrap select { padding: 10px 12px; font-size: 16px; border: 1px solid #ddd; border-radius: 6px; min-height: 44px; }
        .mensaje { padding: 10px; margin-bottom: 12px; border-radius: 6px; font-size: 14px; }
        .mensaje.ok { background: #d4edda; color: #155724; }
        .mensaje.error { background: #f8d7da; color: #721c24; }
        label { display: block; margin-bottom: 4px; font-weight: bold; font-size: 13px; }
        input, select { padding: 10px 12px; border: 1px solid #ddd; border-radius: 6px; font-size: 16px; width: 100%; min-height: 44px; }
        .form-row { display: flex; flex-direction: column; gap: 12px; margin-bottom: 12px; }
        .form-row .campo { width: 100%; }
        .finca-variedad { display: flex; flex-direction: column; gap: 12px; }
        .btn { padding: 12px 16px; border: none; border-radius: 6px; cursor: pointer; font-size: 14px; font-weight: bold; text-decoration: none; display: inline-block; text-align: center; min-height: 44px; line-height: 20px; }
        .btn-primary { background: #6f42c1; color: white; }
        .btn-secondary { background: #6c757d; color: white; }
        .btn-danger { background: #dc3545; color: white; }
        .btn-block { display: block; width: 100%; }
        table { width: 100%; border-collapse: collapse; margin-top: 12px; font-size: 12px; }
        th, td { border: 1px solid #ddd; padding: 8px 6px; text-align: left; }
        th { background: #6f42c1; color: white; font-size: 11px; }
        tr:nth-child(even) { background: #f9f9f9; }
        .acciones { display: flex; gap: 6px; flex-wrap: wrap; }
        .acciones form { display: inline; }
        .acciones .btn { padding: 8px 12px; font-size: 12px; min-height: 36px; }
        .wrap-tabla { overflow-x: auto; -webkit-overflow-scrolling: touch; margin-top: 12px; }
        .wrap-tabla table { min-width: 500px; }
        .volver { margin-top: 16px; }
        .volver .btn { margin-bottom: 8px; }
        .links-top { display: flex; flex-wrap: wrap; gap: 8px; align-items: center; margin-bottom: 8px; }
        .links-top a { font-size: 13px; }
    </style>
</head>
<body>

<div class="container">
    <div class="encabezado">
        <div>
            <h2>Cosecha - Hojas de ruta</h2>
            <div class="links-top">
                <a href="cosecha_cel.php?salir=1" class="btn btn-secondary">Salir</a>
            </div>
        </div>
        <div class="zafra-wrap">
            <form method="GET" style="display:inline;">
                <label for="selZafra">Zafra:</label>
                <select name="zafra" id="selZafra" onchange="this.form.submit()">
                    <?php for ($a = $anio_actual + 1; $a >= $anio_actual - 5; $a--): ?>
                        <option value="<?= $a ?>" <?= $anio_zafra == $a ? 'selected' : '' ?>><?= $a ?></option>
                    <?php endfor; ?>
                </select>
            </form>
            <a href="cosecha_cel.php?zafra=<?= $anio_zafra ?>&exportar=excel" class="btn btn-primary">Exportar Excel</a>
        </div>
    </div>

    <?php if ($mensaje): ?>
        <div class="mensaje <?= strpos($mensaje, 'Error') !== false ? 'error' : 'ok' ?>"><?= htmlspecialchars($mensaje) ?></div>
    <?php endif; ?>

    <form method="POST" action="cosecha_cel.php?zafra=<?= $anio_zafra ?>" class="form-nav-enter">
        <?php if ($editar_id): ?>
            <input type="hidden" name="id" value="<?= $editar_id ?>">
        <?php endif; ?>
        <input type="hidden" name="anio_zafra" value="<?= $anio_zafra ?>">
        <div class="form-row">
            <div class="campo">
                <label>Fecha</label>
                <input type="date" name="fecha" value="<?= $fila_edit ? htmlspecialchars($fila_edit['fecha']) : date('Y-m-d') ?>" required>
            </div>
            <div class="campo">
                <label>Hora</label>
                <input type="time" name="hora" value="<?= $fila_edit ? substr($fila_edit['hora'] ?? '', 0, 5) : '' ?>">
            </div>
            <div class="campo">
                <label>Tickets</label>
                <input type="text" name="tickets" value="<?= $fila_edit ? htmlspecialchars($fila_edit['tickets'] ?? '') : htmlspecialchars($def_tickets) ?>" placeholder="">
            </div>
            <div class="campo">
                <label>Remito</label>
                <input type="text" name="remito" value="<?= $fila_edit ? htmlspecialchars($fila_edit['remito'] ?? '') : htmlspecialchars($def_remito) ?>" placeholder="">
            </div>
            <div class="campo">
                <label>Viaje</label>
                <input type="text" name="viaje" value="<?= $fila_edit ? htmlspecialchars($fila_edit['viaje'] ?? '') : htmlspecialchars($def_viaje) ?>" placeholder="">
            </div>
            <div class="campo">
                <label>Camion</label>
                <input type="text" name="camion" value="<?= $fila_edit ? htmlspecialchars($fila_edit['camion'] ?? '') : '' ?>" placeholder="">
            </div>
            <div class="finca-variedad">
                <div class="campo">
                    <label>Finca</label>
                    <select name="finca_sel" id="selFinca" onchange="document.getElementById('fincaOtroWrap').style.display=(this.value==='__otro__')?'block':'none';">
                        <option value="">--</option>
                        <?php foreach ($fincas_lista as $fn): ?>
                        <option value="<?= htmlspecialchars($fn) ?>" <?= (($fila_edit ? ($fila_edit['finca'] ?? '') : $def_finca) === $fn ? 'selected' : '' ?>><?= htmlspecialchars($fn) ?></option>
                        <?php endforeach; ?>
                        <option value="__otro__">+ Nueva finca</option>
                    </select>
                    <div id="fincaOtroWrap" style="display:none; margin-top:8px;">
                        <input type="text" name="finca_nueva" placeholder="Escriba la nueva finca" value="">
                    </div>
                </div>
                <div class="campo">
                    <label>Variedad</label>
                    <select name="variedad_sel" id="selVariedad" onchange="document.getElementById('variedadOtroWrap').style.display=(this.value==='__otro__')?'block':'none';">
                        <?php foreach ($variedades_lista as $vn): ?>
                        <option value="<?= htmlspecialchars($vn) ?>" <?= (($fila_edit ? ($fila_edit['variedad'] ?? '') : $def_variedad) === $vn ? 'selected' : '' ?>><?= htmlspecialchars($vn) ?></option>
                        <?php endforeach; ?>
                        <option value="__otro__">Agregar otra variedad</option>
                    </select>
                    <div id="variedadOtroWrap" style="display:none; margin-top:8px;">
                        <input type="text" name="variedad_nueva" placeholder="Escriba la nueva variedad" value="">
                    </div>
                </div>
            </div>
            <div class="campo">
                <button type="submit" name="guardar" class="btn btn-primary btn-block"><?= $editar_id ? 'Actualizar' : 'Guardar' ?></button>
                <?php if ($editar_id): ?>
                    <a href="cosecha_cel.php?zafra=<?= $anio_zafra ?>" class="btn btn-secondary btn-block" style="margin-top:8px;">Cancelar</a>
                <?php endif; ?>
            </div>
        </div>
    </form>

    <h3 style="margin-top: 20px; font-size: 15px; color: #333;">Registros zafra <?= $anio_zafra ?></h3>
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
                                <?php if ($puede_editar_fecha($f['fecha'])): ?>
                                <div class="acciones">
                                    <form method="POST" action="cosecha_cel.php?zafra=<?= $anio_zafra ?>" style="display:inline;">
                                        <input type="hidden" name="id" value="<?= $f['id'] ?>">
                                        <input type="hidden" name="anio_zafra" value="<?= $anio_zafra ?>">
                                        <button type="submit" name="editar" class="btn btn-secondary">Modificar</button>
                                    </form>
                                    <form method="POST" action="cosecha_cel.php?zafra=<?= $anio_zafra ?>" style="display:inline;" onsubmit="return confirm('¿Eliminar este registro?');">
                                        <input type="hidden" name="id" value="<?= $f['id'] ?>">
                                        <input type="hidden" name="anio_zafra" value="<?= $anio_zafra ?>">
                                        <button type="submit" name="eliminar" class="btn btn-danger">Eliminar</button>
                                    </form>
                                </div>
                                <?php else: ?>—<?php endif; ?>
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
</div>
</body>
</html>
