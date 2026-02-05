<?php
include 'db.php';

if (!isset($_GET['id'])) {
    header('Location: propiedades.php');
    exit;
}

$id = (int)$_GET['id'];
$res = mysqli_query($conexion, "SELECT * FROM propiedades WHERE propiedad_id = $id LIMIT 1");
$prop = mysqli_fetch_assoc($res);

if (!$prop) {
    echo "Propiedad no encontrada.";
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Editar Propiedad - HHH</title>
    <style>
        body { font-family: 'Segoe UI', sans-serif; background: #f0f2f5; padding: 20px; margin: 0; }
        .card { background: white; padding: 25px; border-radius: 8px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); max-width: 500px; margin: auto; }
        h2 { color: #007bff; margin-top: 0; font-size: 16px; text-transform: uppercase; border-bottom: 2px solid #007bff; padding-bottom: 10px; }
        label { display: block; margin: 10px 0 5px; font-size: 11px; font-weight: bold; color: #555; text-transform: uppercase; }
        input, textarea { width: 100%; padding: 8px; box-sizing: border-box; border: 1px solid #ccc; border-radius: 4px; text-transform: uppercase; font-size: 12px; }
        textarea { min-height: 80px; resize: vertical; }
        .btns { margin-top: 20px; display: flex; gap: 10px; }
        button, .btn-cancelar { flex: 1; padding: 10px; border: none; border-radius: 4px; cursor: pointer; font-weight: bold; text-align: center; text-decoration: none; font-size: 12px; }
        button { background: #28a745; color: white; }
        .btn-cancelar { background: #6c757d; color: white; display: block; }
        button:hover { background: #218838; }
    </style>
</head>
<body>

<div class="card">
    <h2>Editar datos de la propiedad</h2>
    <form action="actualizar_propiedad.php" method="POST">
        <input type="hidden" name="propiedad_id" value="<?= $prop['propiedad_id'] ?>">
        
        <label>Propiedad</label>
        <input type="text" name="propiedad" value="<?= htmlspecialchars($prop['propiedad']) ?>" required autofocus>
        
        <label>Consorcio</label>
        <input type="text" name="consorcio" value="<?= htmlspecialchars($prop['consorcio'] ?? '') ?>">
        
        <label>Padrón</label>
        <input type="text" name="padron" value="<?= htmlspecialchars($prop['padron'] ?? '') ?>">
        
        <label>Detalle técnico</label>
        <textarea name="detalle"><?= htmlspecialchars($prop['detalle'] ?? '') ?></textarea>
        
        <div class="btns">
            <button type="submit">GUARDAR CAMBIOS</button>
            <a href="propiedades.php" class="btn-cancelar">VOLVER</a>
        </div>
    </form>
</div>

</body>
</html>
