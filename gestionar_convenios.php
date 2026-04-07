<?php
include 'db.php';
include 'verificar_sesion.php';
require_once __DIR__ . '/config_clave_borrado.php';

if (!isset($_SESSION['acceso_nivel']) || $_SESSION['acceso_nivel'] < 3) {
    header('Location: index.php?msg=sin_permiso');
    exit;
}

// Crear tabla convenios_finca si no existe
$res_check = mysqli_query($conexion, "SHOW TABLES LIKE 'convenios_finca'");
if (!$res_check || mysqli_num_rows($res_check) == 0) {
    $sql_create = "CREATE TABLE IF NOT EXISTS convenios_finca (
        id INT AUTO_INCREMENT PRIMARY KEY,
        usuario_id INT NOT NULL,
        tipo_trabajo VARCHAR(50) NOT NULL DEFAULT 'Horas Comunes',
        anio INT NOT NULL,
        monto_mes_1 DECIMAL(12,2) DEFAULT 0,
        monto_mes_2 DECIMAL(12,2) DEFAULT 0,
        monto_mes_3 DECIMAL(12,2) DEFAULT 0,
        monto_mes_4 DECIMAL(12,2) DEFAULT 0,
        monto_mes_5 DECIMAL(12,2) DEFAULT 0,
        monto_mes_6 DECIMAL(12,2) DEFAULT 0,
        monto_mes_7 DECIMAL(12,2) DEFAULT 0,
        monto_mes_8 DECIMAL(12,2) DEFAULT 0,
        monto_mes_9 DECIMAL(12,2) DEFAULT 0,
        monto_mes_10 DECIMAL(12,2) DEFAULT 0,
        monto_mes_11 DECIMAL(12,2) DEFAULT 0,
        monto_mes_12 DECIMAL(12,2) DEFAULT 0,
        fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
        INDEX idx_usuario (usuario_id),
        INDEX idx_anio (anio),
        UNIQUE KEY uk_usuario_tipo_anio (usuario_id, tipo_trabajo, anio)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci";
    mysqli_query($conexion, $sql_create);
}
// Tabla para controlar qué meses ya fueron liquidados (evitar doble aplicación)
$res_liq = mysqli_query($conexion, "SHOW TABLES LIKE 'convenios_finca_liquidado'");
if (!$res_liq || mysqli_num_rows($res_liq) == 0) {
    mysqli_query($conexion, "CREATE TABLE IF NOT EXISTS convenios_finca_liquidado (
        convenio_id INT NOT NULL,
        mes TINYINT NOT NULL,
        PRIMARY KEY (convenio_id, mes),
        FOREIGN KEY (convenio_id) REFERENCES convenios_finca(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci");
}

// POST limpiar + clave: borrar liquidaciones (requiere clave de seguridad)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['limpiar']) && $_POST['limpiar'] === '1') {
    $clave_ingresada = trim($_POST['clave_limpiar'] ?? '');
    if ($clave_ingresada === obtener_clave_borrado($conexion)) {
        mysqli_query($conexion, "DELETE FROM cuentas WHERE comprobante = 'trabajo' AND (concepto LIKE 'Fijo de mes%' OR concepto LIKE 'FIJO DE MES%')");
        $res_trunc = mysqli_query($conexion, "SHOW TABLES LIKE 'convenios_finca_liquidado'");
        if ($res_trunc && mysqli_num_rows($res_trunc) > 0) {
            mysqli_query($conexion, "TRUNCATE TABLE convenios_finca_liquidado");
        }
        header('Location: gestionar_convenios.php?msg=limpiado');
        exit;
    } else {
        $mensaje = 'Clave incorrecta. No se borraron las liquidaciones.';
    }
}

/**
 * Liquida todos los meses pendientes (no liquidados) hasta el último mes cerrado.
 * Si $forzar_reconciliacion=true, primero limpia marcas "liquidado" que no tengan movimiento en cuenta.
 */
function liquidar_meses_pendientes_convenio($conexion, $convenio_id, $usuario_id, $tipo_trabajo, $anio_convenio, $montos, $forzar_reconciliacion = false) {
    $mes_actual = (int)date('n');
    $anio_actual_hoy = (int)date('Y');
    if ($anio_convenio > $anio_actual_hoy) return [];

    $ultimo_mes_liquidable = ($anio_convenio === $anio_actual_hoy) ? ($mes_actual - 1) : 12;
    if ($ultimo_mes_liquidable < 1) return [];

    $res_ts = mysqli_query($conexion, "SELECT valor_hora_comun, valor_hora_tractor FROM tabla_salarial ORDER BY id DESC LIMIT 1");
    $vh_comun = 0;
    $vh_tractor = 0;
    if ($res_ts && $ts = mysqli_fetch_assoc($res_ts)) {
        $vh_comun = (float)($ts['valor_hora_comun'] ?? 0);
        $vh_tractor = (float)($ts['valor_hora_tractor'] ?? 0);
    }
    $valor_hora = (stripos($tipo_trabajo, 'tract') !== false) ? $vh_tractor : $vh_comun;
    if ($valor_hora <= 0) return [];

    $detalle_tipo = (stripos($tipo_trabajo, 'tract') !== false) ? 'Horas tractos' : 'Horas Comunes';
    $tipo_like = mysqli_real_escape_string($conexion, (stripos($tipo_trabajo, 'tract') !== false) ? '%Horas tractos%' : '%Horas Comunes%');
    $nombres_mes = ['Enero','Febrero','Marzo','Abril','Mayo','Junio','Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'];
    $comprobante = mysqli_real_escape_string($conexion, 'trabajo');
    $aplicados = [];

    for ($mes = 1; $mes <= $ultimo_mes_liquidable; $mes++) {
        $jornales = (float)($montos[$mes] ?? 0);
        if ($jornales <= 0) continue;

        $ref = sprintf('%02d/%04d', $mes, $anio_convenio);
        $ref_esc = mysqli_real_escape_string($conexion, $ref);

        $existe_cuenta = mysqli_query($conexion,
            "SELECT 1 FROM cuentas WHERE usuario_id = $usuario_id AND comprobante = 'trabajo' AND referencia = '$ref_esc' AND (concepto LIKE 'Fijo de mes%' OR concepto LIKE 'FIJO DE MES%') AND concepto LIKE '$tipo_like' LIMIT 1"
        );
        $tiene_cuenta = ($existe_cuenta && mysqli_num_rows($existe_cuenta) > 0);

        if ($forzar_reconciliacion && !$tiene_cuenta) {
            mysqli_query($conexion, "DELETE FROM convenios_finca_liquidado WHERE convenio_id = $convenio_id AND mes = $mes");
        }

        $existe_liquidado = mysqli_query($conexion, "SELECT 1 FROM convenios_finca_liquidado WHERE convenio_id = $convenio_id AND mes = $mes LIMIT 1");
        if ($existe_liquidado && mysqli_num_rows($existe_liquidado) > 0) continue;

        if ($tiene_cuenta) {
            mysqli_query($conexion, "INSERT IGNORE INTO convenios_finca_liquidado (convenio_id, mes) VALUES ($convenio_id, $mes)");
            continue;
        }

        $mes_fecha = $mes + 1;
        $anio_fecha = $anio_convenio;
        if ($mes_fecha > 12) {
            $mes_fecha = 1;
            $anio_fecha++;
        }
        $fecha = sprintf('%04d-%02d-01', $anio_fecha, $mes_fecha);
        $monto = round(8 * $jornales * $valor_hora, 2);
        $jornales_fmt = number_format($jornales, 2, ',', '.');
        $valor_fmt = number_format($valor_hora, 2, ',', '.');
        $concepto = "Fijo de mes {$nombres_mes[$mes-1]}, cantidad $jornales_fmt jornales (8 h c/u) $detalle_tipo, valor $valor_fmt por hora";
        $concepto_esc = mysqli_real_escape_string($conexion, $concepto);
        $sql_cuenta = "INSERT INTO cuentas (usuario_id, fecha, concepto, comprobante, referencia, monto) VALUES ($usuario_id, '$fecha', '$concepto_esc', '$comprobante', '$ref_esc', $monto)";
        if (mysqli_query($conexion, $sql_cuenta)) {
            mysqli_query($conexion, "INSERT IGNORE INTO convenios_finca_liquidado (convenio_id, mes) VALUES ($convenio_id, $mes)");
            $aplicados[] = $nombres_mes[$mes - 1] . '/' . $anio_convenio;
        }
    }

    return $aplicados;
}

/**
 * Reconciliación global de convenios:
 * - Limpia marcas "liquidado" sin movimiento en cuenta.
 * - Reaplica automáticamente cualquier mes pendiente.
 */
function reconciliar_liquidaciones_convenios($conexion) {
    $anio_actual_hoy = (int)date('Y');
    $convenios = mysqli_query($conexion, "SELECT * FROM convenios_finca WHERE anio <= $anio_actual_hoy");
    if (!$convenios) return '';

    $mensajes = [];
    while ($c = mysqli_fetch_assoc($convenios)) {
        $montos = [];
        for ($m = 1; $m <= 12; $m++) {
            $montos[$m] = (float)($c["monto_mes_$m"] ?? 0);
        }
        $aplicados = liquidar_meses_pendientes_convenio(
            $conexion,
            (int)$c['id'],
            (int)$c['usuario_id'],
            (string)$c['tipo_trabajo'],
            (int)$c['anio'],
            $montos,
            true
        );
        if (!empty($aplicados)) {
            $mensajes = array_merge($mensajes, $aplicados);
        }
    }

    if (empty($mensajes)) return '';
    $mensajes = array_values(array_unique($mensajes));
    return ' Se repuso/aplicó liquidación de: ' . implode(', ', $mensajes) . '.';
}

// Al salir: reconciliar y redirigir a gestionar_finca
if (isset($_GET['salir']) && $_GET['salir'] === '1') {
    reconciliar_liquidaciones_convenios($conexion);
    header('Location: gestionar_finca.php');
    exit;
}

// Usuarios finca (mismo criterio que gestionar_finca)
$sql_usuarios = "SELECT id, apellido FROM usuarios WHERE apellido LIKE '%(FINCA)%' ORDER BY apellido ASC";
$res_usuarios = mysqli_query($conexion, $sql_usuarios);
$usuarios = [];
while ($u = mysqli_fetch_assoc($res_usuarios)) {
    $usuarios[] = $u;
}

if (!isset($mensaje)) $mensaje = '';
if (isset($_GET['msg']) && $_GET['msg'] === 'limpiado') $mensaje = 'Liquidaciones de convenios borradas. Guarde de nuevo para aplicar solo el mes anterior.';
$editar_id = null;
$fila_edit = null;
$anio_actual = (int)date('Y');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['guardar'])) {
        $usuario_id = (int)($_POST['usuario_id'] ?? 0);
        $tipo_trabajo = isset($_POST['tipo_tractor']) && $_POST['tipo_tractor'] === '1' ? 'Horas tractos' : 'Horas Comunes';
        $anio = $anio_actual; // Siempre año en curso
        if ($usuario_id < 1) {
            $mensaje = 'Seleccione un usuario.';
        } else {
            $montos = [];
            for ($m = 1; $m <= 12; $m++) {
                $val = $_POST["mes_$m"] ?? '';
                $montos[$m] = $val !== '' ? (float)str_replace(',', '.', $val) : 0;
            }
            $tipo_esc = mysqli_real_escape_string($conexion, $tipo_trabajo);
            $convenio_id = null;
            if (isset($_POST['id']) && (int)$_POST['id'] > 0) {
                $id = (int)$_POST['id'];
                $r_edit = mysqli_query($conexion, "SELECT anio FROM convenios_finca WHERE id = $id LIMIT 1");
                $anio = ($r_edit && $row_edit = mysqli_fetch_assoc($r_edit)) ? (int)$row_edit['anio'] : $anio_actual;
                $sets = [];
                for ($m = 1; $m <= 12; $m++) {
                    $sets[] = "monto_mes_$m = " . $montos[$m];
                }
                $sql = "UPDATE convenios_finca SET usuario_id = $usuario_id, tipo_trabajo = '$tipo_esc', " . implode(', ', $sets) . " WHERE id = $id";
                if (mysqli_query($conexion, $sql)) {
                    $convenio_id = $id;
                    $mensaje = 'Convenio actualizado.';
                    $editar_id = null;
                    $fila_edit = null;
                    $montos_aplicar = $montos;
                    $anio_aplicar = $anio;
                } else {
                    $mensaje = 'Error: ' . mysqli_error($conexion);
                }
            } else {
                $cols = 'usuario_id, tipo_trabajo, anio';
                $vals = "$usuario_id, '$tipo_esc', $anio";
                for ($m = 1; $m <= 12; $m++) {
                    $cols .= ", monto_mes_$m";
                    $vals .= ", " . $montos[$m];
                }
                $sql = "INSERT INTO convenios_finca ($cols) VALUES ($vals)";
                if (mysqli_query($conexion, $sql)) {
                    $convenio_id = (int)mysqli_insert_id($conexion);
                    $mensaje = 'Convenio guardado.';
                    $montos_aplicar = $montos;
                    $anio_aplicar = $anio;
                } else {
                    if (mysqli_errno($conexion) == 1062) {
                        $mensaje = 'Ya existe un convenio para este usuario, tipo de trabajo y año. Use Editar para modificarlo.';
                    } else {
                        $mensaje = 'Error: ' . mysqli_error($conexion);
                    }
                }
            }
            // Aplicar al guardar: todos los meses pendientes (no liquidados), hasta el último mes cerrado.
            if ($convenio_id && isset($montos_aplicar) && isset($anio_aplicar)) {
                $aplicados = liquidar_meses_pendientes_convenio(
                    $conexion,
                    (int)$convenio_id,
                    (int)$usuario_id,
                    (string)$tipo_trabajo,
                    (int)$anio_aplicar,
                    $montos_aplicar,
                    false
                );
                if (!empty($aplicados)) {
                    $mensaje .= " Se aplicó en cuenta corriente: " . implode(', ', $aplicados) . ".";
                }
            }
        }
    } elseif (isset($_POST['editar'])) {
        $editar_id = (int)$_POST['id'];
        $r = mysqli_query($conexion, "SELECT c.*, u.apellido AS usuario_nombre FROM convenios_finca c INNER JOIN usuarios u ON u.id = c.usuario_id WHERE c.id = $editar_id LIMIT 1");
        if ($r && $fila_edit = mysqli_fetch_assoc($r)) {
            // ok
        } else {
            $editar_id = null;
            $fila_edit = null;
        }
    } elseif (isset($_POST['eliminar'])) {
        $id = (int)$_POST['id'];
        if (mysqli_query($conexion, "DELETE FROM convenios_finca WHERE id = $id")) {
            $mensaje = 'Convenio eliminado.';
        } else {
            $mensaje = 'Error al eliminar.';
        }
    }
    // Reconciliación: si algún usuario borró el movimiento en su cuenta, reponerlo
    $rec = reconciliar_liquidaciones_convenios($conexion);
    if ($rec !== '') {
        $mensaje = (isset($mensaje) ? $mensaje : '') . $rec;
    }
}

$lista = mysqli_query($conexion, "
    SELECT c.*, u.apellido AS usuario_nombre 
    FROM convenios_finca c 
    INNER JOIN usuarios u ON u.id = c.usuario_id 
    ORDER BY c.anio DESC, u.apellido ASC, c.tipo_trabajo ASC
");
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Convenios - Liquidación mensual</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 15px; background: #f5f5f5; font-size: 12px; }
        .container { max-width: 1100px; margin: 0 auto; background: white; padding: 15px; border-radius: 6px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h2 { color: #007bff; margin-top: 0; margin-bottom: 12px; font-size: 18px; }
        .mensaje { padding: 8px; margin-bottom: 12px; border-radius: 4px; font-size: 12px; }
        .mensaje.ok { background: #d4edda; color: #155724; }
        .mensaje.error { background: #f8d7da; color: #721c24; }
        label { display: block; margin-bottom: 2px; font-weight: bold; font-size: 11px; }
        input, select, textarea { padding: 6px; border: 1px solid #ddd; border-radius: 4px; width: 100%; box-sizing: border-box; font-size: 12px; }
        .form-row { display: flex; gap: 12px; margin-bottom: 10px; flex-wrap: wrap; }
        .form-row .campo { flex: 1; min-width: 120px; }
        .btn { padding: 6px 12px; border: none; border-radius: 4px; cursor: pointer; font-size: 11px; text-decoration: none; display: inline-block; }
        .btn-primary { background: #007bff; color: white; }
        .btn-secondary { background: #6c757d; color: white; }
        .btn-danger { background: #dc3545; color: white; }
        table { width: 100%; border-collapse: collapse; margin-top: 15px; font-size: 11px; }
        th, td { border: 1px solid #ddd; padding: 6px 8px; text-align: left; }
        th { background: #007bff; color: white; }
        tr:nth-child(even) { background: #f9f9f9; }
        .acciones { display: flex; gap: 4px; flex-wrap: wrap; }
        .volver { margin-top: 15px; }
        .buscador-usuario { position: relative; overflow: visible; }
        .buscador-usuario input[type="text"] { padding-right: 36px; }
        .buscador-usuario .ico-lupa { position: absolute; right: 10px; top: 50%; transform: translateY(-50%); pointer-events: none; color: #6c757d; font-size: 1rem; }
        #resultadosUsuario { position: absolute; top: 100%; left: 0; right: 0; background: white; border: 1px solid #ddd; border-top: none; max-height: 150px; overflow-y: auto; z-index: 1000; display: none; font-size: 11px; }
        #resultadosUsuario .usuario-item { padding: 6px; cursor: pointer; border-bottom: 1px solid #eee; }
        #resultadosUsuario .usuario-item:hover { background: #f0f0f0; }
        .grid-meses { display: grid; grid-template-columns: repeat(12, 1fr); gap: 6px; margin-bottom: 12px; }
        .grid-meses .mes-campo { min-width: 0; }
        .grid-meses .mes-campo label { font-size: 10px; padding: 2px 0; }
        .grid-meses .mes-campo input { width: 100%; padding: 4px; font-size: 11px; text-align: right; }
        .checkbox-wrapper { display: flex; align-items: center; gap: 8px; padding: 8px 0; }
        .checkbox-wrapper input[type="checkbox"] { width: 18px; height: 18px; margin: 0; }
        .wrap-tabla { overflow-x: auto; }
    </style>
</head>
<body>
    <div class="container">
        <h2>Convenios - Liquidación mensual</h2>
        <p style="margin:0 0 10px 0; font-size:11px;">Al guardar se aplica solo el <strong>mes anterior</strong> (ej: 01/03 liquida febrero). Fórmula: 8 × jornales × valor/hora. Fecha: día 1 del mes en curso.
        <form method="POST" style="display:inline;" onsubmit="return confirm('¿Borrar todas las liquidaciones de convenios?');">
            <input type="hidden" name="limpiar" value="1">
            <input type="password" name="clave_limpiar" placeholder="Clave" required style="width:70px; padding:2px 6px; font-size:11px;" maxlength="10" autocomplete="off">
            <button type="submit" class="btn btn-secondary" style="padding:2px 8px; font-size:10px;">Borrar liquidaciones</button>
        </form>
        </p>
        <?php if ($mensaje): ?>
            <div class="mensaje <?= strpos($mensaje, 'Error') !== false ? 'error' : 'ok' ?>"><?= htmlspecialchars($mensaje) ?></div>
        <?php endif; ?>

        <form method="POST" class="form-nav-enter">
            <?php if ($editar_id): ?>
                <input type="hidden" name="id" value="<?= $editar_id ?>">
            <?php endif; ?>
            <div class="form-row">
                <div class="campo" style="flex: 0 0 220px;">
                    <label>Usuario (finca)</label>
                    <div class="buscador-usuario">
                        <input type="text" id="buscadorUsuario" placeholder="Buscar usuario..." autocomplete="off" 
                            value="<?= $fila_edit ? htmlspecialchars($fila_edit['usuario_nombre'] ?? '') : '' ?>">
                        <span class="ico-lupa" aria-hidden="true">🔍</span>
                        <input type="hidden" name="usuario_id" id="usuario_id" value="<?= $fila_edit ? $fila_edit['usuario_id'] : '' ?>" required>
                        <div id="resultadosUsuario"></div>
                    </div>
                </div>
                <div class="campo" style="flex: 0 0 180px;">
                    <label>Tipo de trabajo</label>
                    <div class="checkbox-wrapper">
                        <input type="checkbox" name="tipo_tractor" id="tipo_tractor" value="1" 
                            <?= ($fila_edit && ($fila_edit['tipo_trabajo'] ?? '') === 'Horas tractos') ? 'checked' : '' ?>>
                        <label for="tipo_tractor" style="margin:0; font-weight:normal;">Horas tractos</label>
                    </div>
                    <span style="font-size: 10px; color: #666;">(Sin marcar = Horas Comunes)</span>
                </div>
            </div>
            <div class="form-row">
                <div class="campo" style="flex: 2;">
                    <label>Jornales por mes (8 h c/u). Al guardar se aplica cada mes con valor &gt; 0 si no fue aplicado antes.</label>
                    <div class="grid-meses">
                        <?php for ($m = 1; $m <= 12; $m++): 
                            $nombres = ['Ene','Feb','Mar','Abr','May','Jun','Jul','Ago','Sep','Oct','Nov','Dic'];
                            $val = $fila_edit ? ($fila_edit["monto_mes_$m"] ?? 0) : '';
                            if ($val !== '' && $val !== null) $val = number_format((float)$val, 2, '.', '');
                        ?>
                            <div class="mes-campo">
                                <label><?= $m ?>. <?= $nombres[$m-1] ?></label>
                                <input type="text" name="mes_<?= $m ?>" placeholder="0" value="<?= htmlspecialchars($val) ?>" inputmode="decimal">
                            </div>
                        <?php endfor; ?>
                    </div>
                </div>
                <div class="campo" style="flex: 0; align-self: flex-end;">
                    <button type="submit" name="guardar" class="btn btn-primary"><?= $editar_id ? 'Actualizar' : 'Guardar' ?></button>
                    <?php if ($editar_id): ?>
                        <a href="gestionar_convenios.php" class="btn btn-secondary">Cancelar</a>
                    <?php endif; ?>
                </div>
            </div>
        </form>

        <h3 style="margin-top: 25px; font-size: 14px; color: #333;">Usuarios con convenios cargados</h3>
        <div class="wrap-tabla">
            <table>
                <thead>
                    <tr>
                        <th>Usuario</th>
                        <th>Tipo</th>
                        <th>Año</th>
                        <th>Ene</th><th>Feb</th><th>Mar</th><th>Abr</th><th>May</th><th>Jun</th>
                        <th>Jul</th><th>Ago</th><th>Sep</th><th>Oct</th><th>Nov</th><th>Dic</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($lista && mysqli_num_rows($lista) > 0): ?>
                        <?php while ($f = mysqli_fetch_assoc($lista)): ?>
                            <tr>
                                <td><?= htmlspecialchars($f['usuario_nombre']) ?></td>
                                <td><?= htmlspecialchars($f['tipo_trabajo']) ?></td>
                                <td><?= $f['anio'] ?></td>
                                <?php for ($m = 1; $m <= 12; $m++): ?>
                                    <td style="text-align:right;"><?= number_format((float)($f["monto_mes_$m"] ?? 0), 2, ',', '.') ?></td>
                                <?php endfor; ?>
                                <td>
                                    <div class="acciones">
                                        <form method="POST" style="display:inline;">
                                            <input type="hidden" name="id" value="<?= $f['id'] ?>">
                                            <button type="submit" name="editar" class="btn btn-secondary">Modificar</button>
                                        </form>
                                        <form method="POST" style="display:inline;" onsubmit="return confirm('¿Eliminar este convenio?');">
                                            <input type="hidden" name="id" value="<?= $f['id'] ?>">
                                            <button type="submit" name="eliminar" class="btn btn-danger">Eliminar</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="16" style="text-align:center; padding:15px; color:#666;">No hay convenios cargados. Agregue el primero.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <p class="volver">
            <a href="gestionar_convenios.php?salir=1" class="btn btn-secondary">← Volver a Finca (PDT)</a>
        </p>
    </div>
    <script>
    (function(){
        var u = <?= json_encode(array_values($usuarios), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) ?>;
        var b = document.getElementById('buscadorUsuario');
        var r = document.getElementById('resultadosUsuario');
        var uid = document.getElementById('usuario_id');
        if(!b||!r||!uid) return;
        var minC = 2;
        function buscar(){
            var t = (b.value||'').toLowerCase().trim();
            if(t.length < minC){ r.style.display='none'; r.innerHTML=''; if(!t){ uid.value=''; } return; }
            if(!u.length){ r.innerHTML='<div class="usuario-item">No hay usuarios finca</div>'; r.style.display='block'; return; }
            var list = [];
            for(var i=0;i<u.length;i++){ var ap = (u[i].apellido||'').toLowerCase(); if(ap.indexOf(t)!==-1) list.push(u[i]); }
            if(!list.length){ r.innerHTML='<div class="usuario-item">No se encontraron usuarios</div>'; r.style.display='block'; return; }
            var html = '';
            for(var j=0;j<Math.min(10,list.length);j++){
                var x=list[j];
                var apEsc = (x.apellido||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/"/g,'&quot;');
                html += '<div class="usuario-item" data-id="'+x.id+'" data-nombre="'+apEsc+'">'+apEsc+'</div>';
            }
            r.innerHTML = html;
            r.style.display = 'block';
        }
        b.oninput = buscar;
        b.onkeyup = buscar;
        b.onfocus = function(){ if((b.value||'').trim().length>=minC) buscar(); };
        function findItem(tgt, cont){ var n=(tgt&&tgt.nodeType===1)?tgt:(tgt?tgt.parentElement:null); while(n&&n!==cont){ if(n.classList&&n.classList.contains('usuario-item')) return n; n=n.parentElement||n.parentNode; } return null; }
        r.onclick = function(e){
            var el = findItem(e.target, r);
            if(!el||!el.getAttribute('data-id')) return;
            uid.value = el.getAttribute('data-id');
            b.value = el.getAttribute('data-nombre')||'';
            r.style.display = 'none';
        };
        r.ontouchend = function(e){
            var el = findItem(e.target, r);
            if(el&&el.getAttribute('data-id')){ e.preventDefault(); uid.value = el.getAttribute('data-id'); b.value = el.getAttribute('data-nombre')||''; r.style.display = 'none'; }
        };
    })();
    </script>
<?php include 'nav_enter_form_inc.php'; ?>
</body>
</html>
