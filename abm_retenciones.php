<?php
include 'db.php';
include 'verificar_sesion.php';

if (isset($_SESSION['acceso_nivel']) && $_SESSION['acceso_nivel'] < 2) {
    header('Location: index.php?msg=sin_permiso');
    exit;
}

// Crear tabla retenciones si no existe
$res_check = mysqli_query($conexion, "SHOW TABLES LIKE 'retenciones'");
if (!$res_check || mysqli_num_rows($res_check) == 0) {
    $sql_create = "CREATE TABLE IF NOT EXISTS retenciones (
        id INT AUTO_INCREMENT PRIMARY KEY,
        descripcion VARCHAR(255) NOT NULL,
        porcentaje DECIMAL(5,2) NOT NULL DEFAULT 0,
        fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci";
    mysqli_query($conexion, $sql_create);
}

$mensaje = '';
$editar_id = null;
$fila_edit = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['guardar'])) {
        $descripcion = mysqli_real_escape_string($conexion, trim($_POST['descripcion'] ?? ''));
        $porcentaje = (float)str_replace(',', '.', $_POST['porcentaje'] ?? 0);

        if ($descripcion === '') {
            $mensaje = 'La descripción es obligatoria.';
        } else {
            if (isset($_POST['id']) && (int)$_POST['id'] > 0) {
                $id = (int)$_POST['id'];
                $sql = "UPDATE retenciones SET descripcion = '$descripcion', porcentaje = $porcentaje WHERE id = $id";
                if (mysqli_query($conexion, $sql)) {
                    $mensaje = 'Registro actualizado.';
                    header('Location: abm_retenciones.php?ok=1');
                    exit;
                } else {
                    $mensaje = 'Error: ' . mysqli_error($conexion);
                }
            } else {
                $sql = "INSERT INTO retenciones (descripcion, porcentaje) VALUES ('$descripcion', $porcentaje)";
                if (mysqli_query($conexion, $sql)) {
                    $mensaje = 'Registro guardado.';
                    header('Location: abm_retenciones.php?ok=1');
                    exit;
                } else {
                    $mensaje = 'Error: ' . mysqli_error($conexion);
                }
            }
        }
    } elseif (isset($_POST['eliminar']) && isset($_POST['id'])) {
        $id = (int)$_POST['id'];
        if ($id > 0 && mysqli_query($conexion, "DELETE FROM retenciones WHERE id = $id")) {
            $mensaje = 'Registro eliminado.';
            header('Location: abm_retenciones.php?ok=1');
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
        $r = mysqli_query($conexion, "SELECT * FROM retenciones WHERE id = $editar_id LIMIT 1");
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
$res_lista = mysqli_query($conexion, "SELECT * FROM retenciones ORDER BY descripcion ASC");
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
    <title>ABM Retenciones - HHH</title>
    <style>
        body { font-family: 'Segoe UI', sans-serif; margin: 15px; background: #f0f2f5; font-size: 12px; }
        .container { max-width: 700px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h2 { color: #007bff; margin-top: 0; margin-bottom: 15px; font-size: 18px; border-bottom: 2px solid #007bff; padding-bottom: 8px; }
        .mensaje { padding: 10px; margin-bottom: 15px; border-radius: 4px; font-size: 12px; }
        .mensaje.ok { background: #d4edda; color: #155724; }
        .mensaje.error { background: #f8d7da; color: #721c24; }
        label { display: block; margin-bottom: 3px; font-weight: bold; font-size: 11px; color: #555; }
        input { padding: 8px; border: 1px solid #ced4da; border-radius: 4px; width: 100%; box-sizing: border-box; font-size: 12px; }
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
        th { background: #007bff; color: white; font-weight: bold; }
        tr:nth-child(even) { background: #f8f9fa; }
        tr:hover { background: #e9ecef; }
        .acciones { display: flex; gap: 6px; flex-wrap: wrap; }
        .volver { margin-top: 20px; display: inline-block; padding: 10px 20px; background: #6c757d; color: white; text-decoration: none; border-radius: 4px; font-weight: bold; }
        .volver:hover { background: #5a6268; color: white; }
        .links-nav { margin-bottom: 15px; display: flex; gap: 8px; flex-wrap: wrap; }
    </style>
</head>
<body>
    <div class="container">
        <h2>ABM Retenciones</h2>

        <div class="links-nav">
            <a href="recibos_porteros.php" class="btn btn-secondary">← Recibos Porteros</a>
            <a href="index.php" class="btn btn-secondary">Menú principal</a>
        </div>

        <?php if ($mensaje): ?>
            <div class="mensaje <?= strpos($mensaje, 'Error') !== false ? 'error' : 'ok' ?>" id="msgFlash"><?= htmlspecialchars($mensaje) ?></div>
        <?php endif; ?>

        <form method="POST" class="form-nav-enter">
            <?php if ($editar_id): ?>
                <input type="hidden" name="id" value="<?= $editar_id ?>">
            <?php endif; ?>
            <div class="form-row">
                <div class="campo-ancho">
                    <label>Descripción</label>
                    <input type="text" name="descripcion" maxlength="255" required
                        value="<?= $fila_edit ? htmlspecialchars($fila_edit['descripcion']) : '' ?>"
                        placeholder="Ej: Ganancias, IVA, etc.">
                </div>
                <div class="campo">
                    <label>Porcentaje (%)</label>
                    <input type="text" name="porcentaje" required
                        value="<?= $fila_edit ? number_format($fila_edit['porcentaje'], 2, ',', '') : '' ?>"
                        placeholder="0,00">
                </div>
            </div>
            <div class="form-row">
                <button type="submit" name="guardar" class="btn btn-primary"><?= $editar_id ? 'Actualizar' : 'Guardar' ?></button>
                <?php if ($editar_id): ?>
                    <a href="abm_retenciones.php" class="btn btn-secondary">Cancelar</a>
                <?php endif; ?>
            </div>
        </form>

        <h3 style="margin-top: 25px; font-size: 14px; color: #333;">Listado</h3>
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Descripción</th>
                    <th style="text-align: right;">Porcentaje (%)</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($lista) > 0): ?>
                    <?php foreach ($lista as $r): ?>
                    <tr>
                        <td><?= (int)$r['id'] ?></td>
                        <td><?= htmlspecialchars($r['descripcion']) ?></td>
                        <td style="text-align: right;"><?= number_format($r['porcentaje'], 2, ',', '.') ?>%</td>
                        <td>
                            <div class="acciones">
                                <a href="abm_retenciones.php?editar=<?= (int)$r['id'] ?>" class="btn btn-secondary" style="padding: 4px 10px; font-size: 11px;">Modificar</a>
                                <form method="POST" style="display: inline;" onsubmit="return confirm('¿Eliminar este registro?');">
                                    <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                                    <button type="submit" name="eliminar" class="btn btn-danger" style="padding: 4px 10px; font-size: 11px;">Eliminar</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="4" style="text-align: center; padding: 25px; color: #999;">No hay registros cargados.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>

        <a href="index.php" class="volver">← Volver al menú principal</a>
    </div>

    <script>
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
