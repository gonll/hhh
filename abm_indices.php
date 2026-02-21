<?php
include 'db.php';
include 'verificar_sesion.php';
if (isset($_SESSION['acceso_nivel']) && $_SESSION['acceso_nivel'] < 2) {
    header('Location: index.php?msg=solo_lectura');
    exit;
}
// Manejo de la carga manual o actualización
if (isset($_POST['guardar_indice'])) {
    $fecha = $_POST['fecha'];
    $valor = $_POST['valor'];
    $tipo = 'IPC';

    $sql = "INSERT INTO indices (fecha, valor, tipo) VALUES ('$fecha', $valor, '$tipo') 
            ON DUPLICATE KEY UPDATE valor = $valor";
    mysqli_query($conexion, $sql);
    header("Location: abm_indices.php");
}

// OBTENER SOLO LOS ÚLTIMOS 8 ÍNDICES
$res = mysqli_query($conexion, "SELECT * FROM indices ORDER BY fecha DESC LIMIT 8");
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>ABM Índices IPC - HHH</title>
    <style>
        body { font-family: 'Segoe UI', sans-serif; background: #f0f2f5; padding: 10px; margin: 0; }
        .caja { background: white; padding: 15px; border-radius: 8px; width: 400px; margin: 20px auto; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h3 { color: #007bff; border-bottom: 2px solid #007bff; padding-bottom: 5px; text-transform: uppercase; font-size: 12px; margin-top: 0; }
        label { display: block; font-size: 10px; font-weight: bold; margin: 8px 0 3px; color: #555; }
        input { width: 100%; padding: 6px; box-sizing: border-box; border: 1px solid #ccc; border-radius: 4px; font-size: 11px; }
        .btn-guardar { background: #28a745; color: white; border: none; padding: 8px; width: 100%; margin-top: 12px; cursor: pointer; font-weight: bold; border-radius: 4px; text-transform: uppercase; font-size: 11px; }
        .btn-guardar:hover { background: #218838; }
        
        table { width: 100%; margin-top: 15px; border-collapse: collapse; font-size: 11px; }
        table th, table td { border: 1px solid #eee; padding: 6px; text-align: center; }
        table th { background: #f8f9fa; color: #333; font-size: 9px; text-transform: uppercase; }
        .valor-negrita { font-weight: bold; color: #007bff; }
        
        .volver { display: inline-block; margin-top: 15px; padding: 6px 12px; text-decoration: none; background: #ffc107; color: #333; font-size: 10px; font-weight: bold; text-align: center; border-radius: 4px; }
        .volver:hover { background: #e0a800; }
    </style>
</head>
<body>

<div class="caja">
    <h3>Carga de Índice IPC</h3>
    <form method="POST" class="form-nav-enter" autocomplete="off">
        <label>Mes Correspondiente</label>
        <input type="date" name="fecha" required value="<?= date('Y-m-01') ?>">
        
        <label>Valor del Índice (%)</label>
        <input type="number" step="0.0001" name="valor" placeholder="Ej: 2.45" required>
        
        <button type="submit" name="guardar_indice" class="btn-guardar">Guardar Índice</button>
    </form>

    <h3 style="margin-top: 20px;">Últimos 8 Índices</h3>
    <table>
        <thead>
            <tr>
                <th>Mes/Año</th>
                <th>Tipo</th>
                <th>Valor</th>
            </tr>
        </thead>
        <tbody>
            <?php if(mysqli_num_rows($res) > 0): ?>
                <?php while($row = mysqli_fetch_assoc($res)): ?>
                <tr>
                    <td><?= date('m / Y', strtotime($row['fecha'])) ?></td>
                    <td style="color: #999; font-size: 9px;"><?= $row['tipo'] ?></td>
                    <td class="valor-negrita"><?= number_format($row['valor'], 2) ?>%</td>
                </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr><td colspan="3" style="color: #ccc; padding: 20px;">No hay datos cargados</td></tr>
            <?php endif; ?>
        </tbody>
    </table>

    <a href="index.php" class="volver">← VOLVER AL PANEL</a>
</div>

<script>
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        history.back();
    }
});
</script>
<?php include 'timeout_sesion_inc.php'; ?>
</body>
</html>