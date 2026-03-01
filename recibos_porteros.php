<?php
include 'db.php';
include 'verificar_sesion.php';

if (isset($_SESSION['acceso_nivel']) && $_SESSION['acceso_nivel'] < 2) {
    header('Location: index.php?msg=sin_permiso');
    exit;
}

// Crear tabla TabSalPor si no existe
$res_check = mysqli_query($conexion, "SHOW TABLES LIKE 'tabsalpor'");
if (!$res_check || mysqli_num_rows($res_check) == 0) {
    $sql_create = "CREATE TABLE IF NOT EXISTS tabsalpor (
        id INT AUTO_INCREMENT PRIMARY KEY,
        fecha DATE NOT NULL,
        descripcion VARCHAR(255) NULL,
        tipo VARCHAR(50) NULL,
        convenio VARCHAR(100) NULL,
        unidad VARCHAR(50) NULL,
        monto DECIMAL(12,2) NOT NULL DEFAULT 0,
        fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_fecha (fecha)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci";
    mysqli_query($conexion, $sql_create);
} else {
    // Migración: agregar columna tipo si no existe
    $res_col = mysqli_query($conexion, "SHOW COLUMNS FROM tabsalpor LIKE 'tipo'");
    if ($res_col && mysqli_num_rows($res_col) == 0) {
        mysqli_query($conexion, "ALTER TABLE tabsalpor ADD COLUMN tipo VARCHAR(50) NULL AFTER descripcion");
    }
}

// Crear tabla ficha_portero (portero + items a liquidar)
$res_fp = mysqli_query($conexion, "SHOW TABLES LIKE 'ficha_portero'");
if (!$res_fp || mysqli_num_rows($res_fp) == 0) {
    $sql_fp = "CREATE TABLE IF NOT EXISTS ficha_portero (
        id INT AUTO_INCREMENT PRIMARY KEY,
        usuario_id INT NOT NULL,
        tabsalpor_id INT NOT NULL,
        fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uk_portero_item (usuario_id, tabsalpor_id),
        INDEX idx_usuario (usuario_id),
        INDEX idx_tabsalpor (tabsalpor_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci";
    mysqli_query($conexion, $sql_fp);
}

$tipos_opciones = ['HABER' => 'Haber', 'BONO' => 'Bono', 'RETENCIONES' => 'Retenciones', 'DEDUCCIONES' => 'Deducciones', 'ASIGNACIONES' => 'Asignaciones', 'NOREMUNERATIVO' => 'No Remunerativos'];
$convenios_opciones = ['FATERYH' => 'FATERYH', 'UOCRA' => 'UOCRA'];

$mensaje = '';
$editar_id = null;
$fila_edit = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['guardar'])) {
        $fecha = mysqli_real_escape_string($conexion, trim($_POST['fecha'] ?? ''));
        $descripcion = mb_strtoupper(mysqli_real_escape_string($conexion, trim($_POST['descripcion'] ?? '')), 'UTF-8');
        $tipo = mb_strtoupper(mysqli_real_escape_string($conexion, trim($_POST['tipo'] ?? '')), 'UTF-8');
        $convenio = mb_strtoupper(mysqli_real_escape_string($conexion, trim($_POST['convenio'] ?? '')), 'UTF-8');
        $unidad = mb_strtoupper(mysqli_real_escape_string($conexion, trim($_POST['unidad'] ?? '')), 'UTF-8');
        $monto_str = str_replace(',', '.', $_POST['monto'] ?? '0');
        $monto = (float)preg_replace('/[^\d.\-]/', '', $monto_str);
        $monto = round($monto, 2);

        if ($fecha === '') {
            $mensaje = 'La fecha es obligatoria.';
        } else {
            $tipo_val = in_array($tipo, array_keys($tipos_opciones)) ? "'$tipo'" : "NULL";
            if (isset($_POST['id']) && (int)$_POST['id'] > 0) {
                $id = (int)$_POST['id'];
                $sql = "UPDATE tabsalpor SET fecha = '$fecha', descripcion = '$descripcion', tipo = $tipo_val, convenio = '$convenio', unidad = '$unidad', monto = $monto WHERE id = $id";
                if (mysqli_query($conexion, $sql)) {
                    $mensaje = 'Registro actualizado.';
                    header('Location: recibos_porteros.php?seccion=tabsalpor&ok=1');
                    exit;
                } else {
                    $mensaje = 'Error: ' . mysqli_error($conexion);
                }
            } else {
                $sql = "INSERT INTO tabsalpor (fecha, descripcion, tipo, convenio, unidad, monto) VALUES ('$fecha', '$descripcion', $tipo_val, '$convenio', '$unidad', $monto)";
                if (mysqli_query($conexion, $sql)) {
                    $mensaje = 'Registro guardado.';
                    header('Location: recibos_porteros.php?seccion=tabsalpor&ok=1');
                    exit;
                } else {
                    $mensaje = 'Error: ' . mysqli_error($conexion);
                }
            }
        }
    } elseif (isset($_POST['eliminar']) && isset($_POST['id'])) {
        $id = (int)$_POST['id'];
        if ($id > 0 && mysqli_query($conexion, "DELETE FROM tabsalpor WHERE id = $id")) {
            $mensaje = 'Registro eliminado.';
            header('Location: recibos_porteros.php?seccion=tabsalpor&ok=1');
            exit;
        } else {
            $mensaje = 'Error al eliminar.';
        }
    }
}

// Cargar para editar
if (isset($_GET['editar'])) {
    $editar_id = (int)$_GET['editar'];
    if ($editar_id > 0) {
        $r = mysqli_query($conexion, "SELECT * FROM tabsalpor WHERE id = $editar_id LIMIT 1");
        if ($r && $fila_edit = mysqli_fetch_assoc($r)) {
            // ok
        } else {
            $editar_id = null;
            $fila_edit = null;
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

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recibos Porteros - Tabla Salarial (TabSalPor)</title>
    <style>
        body { font-family: 'Segoe UI', sans-serif; margin: 15px; background: #f0f2f5; font-size: 12px; }
        .container { max-width: 900px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h2 { color: #6f42c1; margin-top: 0; margin-bottom: 15px; font-size: 18px; border-bottom: 2px solid #6f42c1; padding-bottom: 8px; }
        .mensaje { padding: 10px; margin-bottom: 15px; border-radius: 4px; font-size: 12px; }
        .mensaje.ok { background: #d4edda; color: #155724; }
        .mensaje.error { background: #f8d7da; color: #721c24; }
        label { display: block; margin-bottom: 3px; font-weight: bold; font-size: 11px; color: #555; }
        input, textarea, select { padding: 8px; border: 1px solid #ced4da; border-radius: 4px; width: 100%; box-sizing: border-box; font-size: 12px; }
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
        table { width: 100%; border-collapse: collapse; margin-top: 20px; font-size: 11px; }
        th, td { border: 1px solid #dee2e6; padding: 8px 10px; text-align: left; }
        th { background: #6f42c1; color: white; font-weight: bold; }
        tr:nth-child(even) { background: #f8f9fa; }
        tr:hover { background: #e9ecef; }
        .acciones { display: flex; gap: 6px; flex-wrap: wrap; }
        .volver { margin-top: 20px; display: inline-block; padding: 10px 20px; background: #6f42c1; color: white; text-decoration: none; border-radius: 4px; font-weight: bold; }
        .volver:hover { background: #5a32a3; color: white; }
    </style>
</head>
<body>
    <div class="container">
        <div style="display: flex; gap: 10px; margin-bottom: 20px; flex-wrap: wrap;">
            <a href="recibos_porteros.php?seccion=tabsalpor" class="btn" style="background: #6f42c1; color: white;">ABM TabSalPor</a>
            <a href="generar_recibos_porteros.php" class="btn" style="background: #28a745; color: white;">Generar recibos</a>
        </div>

        <?php $mostrar_tabsalpor = (isset($_GET['seccion']) && $_GET['seccion'] === 'tabsalpor') || isset($_GET['editar']); ?>
        <?php if ($mostrar_tabsalpor): ?>
        <h2 id="seccion-tabsalpor">ABM Tabla Salarial Porteros (TabSalPor)</h2>

        <?php if ($mensaje): ?>
            <div class="mensaje <?= strpos($mensaje, 'Error') !== false ? 'error' : 'ok' ?>" id="msgFlash"><?= htmlspecialchars($mensaje) ?></div>
        <?php endif; ?>

        <form method="POST" class="form-nav-enter">
            <?php if ($editar_id): ?>
                <input type="hidden" name="id" value="<?= $editar_id ?>">
            <?php endif; ?>
            <div class="form-row">
                <div class="campo">
                    <label>Fecha</label>
                    <input type="date" name="fecha" required
                        value="<?= $fila_edit ? htmlspecialchars($fila_edit['fecha']) : date('Y-m-d') ?>">
                </div>
                <div class="campo-ancho">
                    <label>Descripción</label>
                    <input type="text" name="descripcion" maxlength="255"
                        value="<?= $fila_edit ? htmlspecialchars($fila_edit['descripcion']) : '' ?>"
                        placeholder="Ej: Salario básico portero">
                </div>
            </div>
            <div class="form-row">
                <div class="campo">
                    <label>Tipo</label>
                    <select name="tipo">
                        <option value="">-- Seleccionar --</option>
                        <?php foreach ($tipos_opciones as $v => $l): ?>
                        <option value="<?= htmlspecialchars($v) ?>" <?= ($fila_edit && ($fila_edit['tipo'] ?? '') === $v) ? 'selected' : '' ?>><?= htmlspecialchars($l) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="campo">
                    <label>Convenio</label>
                    <select name="convenio">
                        <option value="">-- SELECCIONAR --</option>
                        <?php foreach ($convenios_opciones as $v => $l): ?>
                        <option value="<?= htmlspecialchars($v) ?>" <?= ($fila_edit && ($fila_edit['convenio'] ?? '') === $v) ? 'selected' : '' ?>><?= htmlspecialchars($l) ?></option>
                        <?php endforeach;
                        $conv_edit = $fila_edit['convenio'] ?? '';
                        if ($conv_edit && !isset($convenios_opciones[$conv_edit])): ?>
                        <option value="<?= htmlspecialchars($conv_edit) ?>" selected><?= htmlspecialchars($conv_edit) ?></option>
                        <?php endif; ?>
                    </select>
                </div>
                <div class="campo">
                    <label>Unidad</label>
                    <select name="unidad" id="unidadSelect" onchange="actualizarLabelMonto()">
                        <option value="">-- SELECCIONAR --</option>
                        <option value="MENSUAL" <?= ($fila_edit && ($fila_edit['unidad'] ?? '') === 'MENSUAL') ? 'selected' : '' ?>>MENSUAL</option>
                        <option value="HORA" <?= ($fila_edit && ($fila_edit['unidad'] ?? '') === 'HORA') ? 'selected' : '' ?>>HORA</option>
                        <option value="%" <?= ($fila_edit && ($fila_edit['unidad'] ?? '') === '%') ? 'selected' : '' ?>>%</option>
                    </select>
                </div>
                <div class="campo">
                    <label id="labelMonto">Monto ($)</label>
                    <input type="text" name="monto" id="inputMonto" required
                        value="<?= $fila_edit ? number_format($fila_edit['monto'], 2, ',', '') : '' ?>"
                        placeholder="0,00">
                </div>
            </div>
            <div class="form-row">
                <button type="submit" name="guardar" class="btn btn-primary"><?= $editar_id ? 'Actualizar' : 'Guardar' ?></button>
                <?php if ($editar_id): ?>
                    <a href="recibos_porteros.php?seccion=tabsalpor" class="btn btn-secondary">Cancelar</a>
                <?php endif; ?>
            </div>
        </form>

        <h3 style="margin-top: 25px; font-size: 14px; color: #333;">Listado tabla salarial</h3>
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Fecha</th>
                    <th>Descripción</th>
                    <th>Tipo</th>
                    <th>Convenio</th>
                    <th>Unidad</th>
                    <th style="text-align: right;">Monto</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($lista) > 0): ?>
                    <?php foreach ($lista as $r): 
                        $unidad_r = $r['unidad'] ?? '';
                        $monto_r = (float)($r['monto'] ?? 0);
                        $monto_display = ($unidad_r === '%') ? number_format($monto_r, 2, ',', '.') . '%' : number_format($monto_r, 2, ',', '.');
                    ?>
                    <tr>
                        <td><?= (int)$r['id'] ?></td>
                        <td><?= date('d/m/Y', strtotime($r['fecha'])) ?></td>
                        <td><?= htmlspecialchars($r['descripcion'] ?? '-') ?></td>
                        <td><?= htmlspecialchars($tipos_opciones[$r['tipo'] ?? ''] ?? ($r['tipo'] ?? '-')) ?></td>
                        <td><?= htmlspecialchars($r['convenio'] ?? '-') ?></td>
                        <td><?= htmlspecialchars($unidad_r ?: '-') ?></td>
                        <td style="text-align: right;"><?= $monto_display ?></td>
                        <td>
                            <div class="acciones">
                                <a href="recibos_porteros.php?seccion=tabsalpor&editar=<?= (int)$r['id'] ?>" class="btn btn-secondary" style="padding: 4px 10px; font-size: 11px;">Modificar</a>
                                <form method="POST" style="display: inline;" onsubmit="return confirm('¿Eliminar este registro?');">
                                    <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                                    <button type="submit" name="eliminar" class="btn btn-danger" style="padding: 4px 10px; font-size: 11px;">Eliminar</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="8" style="text-align: center; padding: 25px; color: #999;">No hay registros cargados.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
        <p style="margin-top: 15px;"><a href="recibos_porteros.php" class="btn btn-secondary">← Volver</a></p>
        <?php endif; ?>

        <?php if (!$mostrar_tabsalpor): ?>
        <p style="color: #666; font-size: 13px;">Seleccione una opción:</p>
        <ul style="margin: 10px 0 0 20px; color: #555;">
            <li><strong>ABM TabSalPor</strong> — Gestionar la tabla salarial (ítems, montos, convenios)</li>
            <li><strong>Generar recibos</strong> — Asignar fichas a porteros y generar recibos</li>
        </ul>
        <?php endif; ?>

        <a href="index.php" class="volver" style="margin-top: 20px; display: inline-block;">← Volver al menú principal</a>
    </div>

    <script>
    function actualizarLabelMonto() {
        var sel = document.getElementById('unidadSelect');
        var lbl = document.getElementById('labelMonto');
        var inp = document.getElementById('inputMonto');
        if (sel && lbl && inp) {
            if (sel.value === '%') {
                lbl.textContent = 'Monto (%)';
                inp.placeholder = '0,00';
            } else {
                lbl.textContent = 'Monto ($)';
                inp.placeholder = '0,00';
            }
        }
    }
    document.addEventListener('DOMContentLoaded', function() {
        actualizarLabelMonto();
    });
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
