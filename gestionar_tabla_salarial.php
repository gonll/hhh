<?php
include 'db.php';
include 'verificar_sesion.php';

if (isset($_SESSION['acceso_nivel']) && $_SESSION['acceso_nivel'] < 2) {
    header('Location: index.php?msg=sin_permiso');
    exit;
}

// Crear tabla si no existe
$res_check = mysqli_query($conexion, "SHOW TABLES LIKE 'tabla_salarial'");
if (!$res_check || mysqli_num_rows($res_check) == 0) {
    $sql_create = "CREATE TABLE IF NOT EXISTS tabla_salarial (
        id INT AUTO_INCREMENT PRIMARY KEY,
        valor_hora_comun DECIMAL(10,2) NOT NULL DEFAULT 0,
        valor_hora_tractor DECIMAL(10,2) NOT NULL DEFAULT 0,
        vigencia_desde DATE NULL,
        observaciones VARCHAR(255) NULL,
        fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_vigencia (vigencia_desde)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci";
    mysqli_query($conexion, $sql_create);
}

$mensaje = '';
$editar_id = null;
$fila_edit = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['guardar'])) {
        $valor_comun = (float)str_replace(',', '.', $_POST['valor_hora_comun'] ?? 0);
        $valor_tractor = (float)str_replace(',', '.', $_POST['valor_hora_tractor'] ?? 0);
        $vigencia = !empty($_POST['vigencia_desde']) ? mysqli_real_escape_string($conexion, $_POST['vigencia_desde']) : null;
        $observaciones = mysqli_real_escape_string($conexion, trim($_POST['observaciones'] ?? ''));
        if (isset($_POST['id']) && (int)$_POST['id'] > 0) {
            $id = (int)$_POST['id'];
            $sql = "UPDATE tabla_salarial SET valor_hora_comun = $valor_comun, valor_hora_tractor = $valor_tractor, vigencia_desde = " . ($vigencia ? "'$vigencia'" : "NULL") . ", observaciones = '$observaciones' WHERE id = $id";
            if (mysqli_query($conexion, $sql)) {
                $mensaje = 'Registro actualizado.';
            } else {
                $mensaje = 'Error: ' . mysqli_error($conexion);
            }
        } else {
            $sql = "INSERT INTO tabla_salarial (valor_hora_comun, valor_hora_tractor, vigencia_desde, observaciones) VALUES ($valor_comun, $valor_tractor, " . ($vigencia ? "'$vigencia'" : "NULL") . ", '$observaciones')";
            if (mysqli_query($conexion, $sql)) {
                $mensaje = 'Registro guardado.';
            } else {
                $mensaje = 'Error: ' . mysqli_error($conexion);
            }
        }
    } elseif (isset($_POST['editar'])) {
        $editar_id = (int)$_POST['id'];
        $r = mysqli_query($conexion, "SELECT * FROM tabla_salarial WHERE id = $editar_id LIMIT 1");
        if ($r && $fila_edit = mysqli_fetch_assoc($r)) {
            // ok
        } else {
            $editar_id = null;
            $fila_edit = null;
        }
    } elseif (isset($_POST['eliminar'])) {
        $id = (int)$_POST['id'];
        if (mysqli_query($conexion, "DELETE FROM tabla_salarial WHERE id = $id")) {
            $mensaje = 'Registro eliminado.';
        } else {
            $mensaje = 'Error al eliminar.';
        }
    }
}

$lista = mysqli_query($conexion, "SELECT * FROM tabla_salarial ORDER BY id DESC");
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ABM Tabla salarial</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 15px; background: #f5f5f5; font-size: 12px; }
        .container { max-width: 800px; margin: 0 auto; background: white; padding: 15px; border-radius: 6px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h2 { color: #007bff; margin-top: 0; margin-bottom: 12px; font-size: 18px; }
        .mensaje { padding: 8px; margin-bottom: 12px; border-radius: 4px; font-size: 12px; }
        .mensaje.ok { background: #d4edda; color: #155724; }
        .mensaje.error { background: #f8d7da; color: #721c24; }
        label { display: block; margin-bottom: 2px; font-weight: bold; font-size: 11px; }
        input, textarea { padding: 6px; border: 1px solid #ddd; border-radius: 4px; width: 100%; box-sizing: border-box; font-size: 12px; }
        .form-row { display: flex; gap: 12px; margin-bottom: 10px; flex-wrap: wrap; }
        .form-row .campo { flex: 1; min-width: 120px; }
        .btn { padding: 6px 12px; border: none; border-radius: 4px; cursor: pointer; font-size: 11px; text-decoration: none; display: inline-block; }
        .btn-primary { background: #007bff; color: white; }
        .btn-secondary { background: #6c757d; color: white; }
        .btn-danger { background: #dc3545; color: white; }
        .btn-success { background: #28a745; color: white; }
        table { width: 100%; border-collapse: collapse; margin-top: 15px; font-size: 11px; }
        th, td { border: 1px solid #ddd; padding: 6px 8px; text-align: left; }
        th { background: #007bff; color: white; }
        tr:nth-child(even) { background: #f9f9f9; }
        .acciones { display: flex; gap: 4px; flex-wrap: wrap; }
        .volver { margin-top: 15px; }
    </style>
</head>
<body>
    <div class="container">
        <h2>ABM Tabla salarial (hora común / hora tractor)</h2>
        <?php if ($mensaje): ?>
            <div class="mensaje <?= strpos($mensaje, 'Error') !== false ? 'error' : 'ok' ?>"><?= htmlspecialchars($mensaje) ?></div>
        <?php endif; ?>

        <form method="POST" class="form-nav-enter">
            <?php if ($editar_id): ?>
                <input type="hidden" name="id" value="<?= $editar_id ?>">
            <?php endif; ?>
            <div class="form-row">
                <div class="campo">
                    <label>Valor hora común ($)</label>
                    <input type="text" name="valor_hora_comun" required
                        value="<?= $fila_edit ? number_format($fila_edit['valor_hora_comun'], 2, '.', '') : '' ?>"
                        placeholder="0.00">
                </div>
                <div class="campo">
                    <label>Valor hora tractor ($)</label>
                    <input type="text" name="valor_hora_tractor" required
                        value="<?= $fila_edit ? number_format($fila_edit['valor_hora_tractor'], 2, '.', '') : '' ?>"
                        placeholder="0.00">
                </div>
                <div class="campo">
                    <label>Vigencia desde (opcional)</label>
                    <input type="date" name="vigencia_desde" value="<?= $fila_edit ? ($fila_edit['vigencia_desde'] ?? '') : '' ?>">
                </div>
            </div>
            <div class="form-row">
                <div class="campo" style="flex: 2;">
                    <label>Observaciones</label>
                    <input type="text" name="observaciones" value="<?= $fila_edit ? htmlspecialchars($fila_edit['observaciones'] ?? '') : '' ?>" placeholder="Opcional">
                </div>
                <div class="campo" style="flex: 0; align-self: flex-end;">
                    <button type="submit" name="guardar" class="btn btn-primary"><?= $editar_id ? 'Actualizar' : 'Agregar' ?></button>
                    <?php if ($editar_id): ?>
                        <a href="gestionar_tabla_salarial.php" class="btn btn-secondary">Cancelar</a>
                    <?php endif; ?>
                </div>
            </div>
        </form>

        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Hora común</th>
                    <th>Hora tractor</th>
                    <th>Vigencia desde</th>
                    <th>Observaciones</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($lista && mysqli_num_rows($lista) > 0): ?>
                    <?php while ($f = mysqli_fetch_assoc($lista)): ?>
                        <tr>
                            <td><?= $f['id'] ?></td>
                            <td>$ <?= number_format($f['valor_hora_comun'], 2, ',', '.') ?></td>
                            <td>$ <?= number_format($f['valor_hora_tractor'], 2, ',', '.') ?></td>
                            <td><?= $f['vigencia_desde'] ? date('d/m/Y', strtotime($f['vigencia_desde'])) : '-' ?></td>
                            <td><?= htmlspecialchars($f['observaciones'] ?? '') ?></td>
                            <td>
                                <div class="acciones">
                                    <form method="POST" style="display:inline;">
                                        <input type="hidden" name="id" value="<?= $f['id'] ?>">
                                        <button type="submit" name="editar" class="btn btn-secondary">Editar</button>
                                    </form>
                                    <form method="POST" style="display:inline;" onsubmit="return confirm('¿Eliminar este registro?');">
                                        <input type="hidden" name="id" value="<?= $f['id'] ?>">
                                        <button type="submit" name="eliminar" class="btn btn-danger">Eliminar</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="6" style="text-align:center; padding:15px; color:#666;">No hay registros. Agregue el primer valor.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>

        <p class="volver">
            <a href="gestionar_finca.php" class="btn btn-secondary">← Volver a Finca (PDT)</a>
        </p>
    </div>
<?php include 'nav_enter_form_inc.php'; ?>
</body>
</html>
