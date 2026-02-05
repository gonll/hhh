<?php
include 'db.php';
include 'verificar_sesion.php';
if (isset($_SESSION['acceso_nivel']) && $_SESSION['acceso_nivel'] < 2) {
    header('Location: index.php?msg=solo_lectura');
    exit;
}
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
        body { font-family: 'Segoe UI', sans-serif; background: #f0f2f5; padding: 10px; margin: 0; }
        .card { background: white; padding: 15px; border-radius: 8px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); max-width: 500px; margin: auto; }
        h2 { color: #007bff; margin-top: 0; font-size: 14px; text-transform: uppercase; border-bottom: 2px solid #007bff; padding-bottom: 6px; margin-bottom: 10px; }
        label { display: block; margin: 6px 0 3px; font-size: 10px; font-weight: bold; color: #555; text-transform: uppercase; }
        input, textarea { width: 100%; padding: 5px; box-sizing: border-box; border: 1px solid #ccc; border-radius: 4px; text-transform: uppercase; font-size: 11px; }
        textarea { min-height: 50px; resize: vertical; }
        .btns { margin-top: 12px; display: flex; gap: 10px; }
        button, .btn-cancelar { flex: 1; padding: 7px; border: none; border-radius: 4px; cursor: pointer; font-weight: bold; text-align: center; text-decoration: none; font-size: 11px; }
        button { background: #28a745; color: white; }
        .btn-cancelar { background: #ffc107; color: #333; display: block; }
        .btn-cancelar:hover { background: #e0a800; }
        button:hover { background: #218838; }
        .fila-doble { display: flex; gap: 10px; }
        .fila-doble > div { flex: 0 0 100px; }
        .fila-doble label { margin: 6px 0 3px; }
        .fila-doble input { width: 100%; }
    </style>
</head>
<body>

<?php $esNivel3 = isset($_SESSION['acceso_nivel']) && $_SESSION['acceso_nivel'] >= 3; ?>
<div class="card" id="formularioEditar" style="display:<?= $esNivel3 ? 'block' : 'none' ?>;">
    <h2>Editar datos de la propiedad</h2>
    <form action="actualizar_propiedad.php" method="POST">
        <input type="hidden" name="propiedad_id" value="<?= $prop['propiedad_id'] ?>">
        
        <label>Propiedad</label>
        <input type="text" name="propiedad" value="<?= htmlspecialchars($prop['propiedad']) ?>" required autofocus>

        <label>Ciudad</label>
        <input type="text" name="ciudad" value="<?= htmlspecialchars($prop['ciudad'] ?? '') ?>" placeholder="Ej: S. M. DE TUCUMAN">
        
        <div class="fila-doble">
            <div>
                <label>Consorcio</label>
                <input type="text" name="consorcio" value="<?= htmlspecialchars($prop['consorcio'] ?? '') ?>">
            </div>
            <div>
                <label>%</label>
                <input type="number" name="porcentaje" step="0.01" min="0" max="100" value="<?= htmlspecialchars($prop['porcentaje'] ?? '') ?>" placeholder="5.25" style="text-transform:none;">
            </div>
        </div>
        
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

<script>
window.addEventListener('DOMContentLoaded', function() {
    var esNivel3 = <?= $esNivel3 ? 'true' : 'false' ?>;
    if (esNivel3) {
        document.getElementById('formularioEditar').style.display = 'block';
        return;
    }
    var clave = prompt("CLAVE DE SEGURIDAD PARA EDITAR PROPIEDAD:");
    if (clave === null) {
        window.location.href = 'propiedades.php';
        return;
    }
    if (clave !== "4961") {
        alert("Clave incorrecta.");
        window.location.href = 'propiedades.php';
        return;
    }
    document.getElementById('formularioEditar').style.display = 'block';
});

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        history.back();
    }
});
</script>
<?php include 'timeout_sesion_inc.php'; ?>
</body>
</html>
