<?php
include 'db.php';
include 'verificar_sesion.php';
if (isset($_SESSION['acceso_nivel']) && $_SESSION['acceso_nivel'] < 2) {
    header('Location: index.php?msg=solo_lectura');
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Nuevo Usuario</title>
    <style>
        body { font-family: 'Segoe UI', sans-serif; background: #f0f2f5; display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0; }
        .caja { background: white; padding: 30px; border-radius: 8px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); width: 300px; text-align: center; }
        input { display: block; margin: 10px 0; padding: 10px; width: 100%; box-sizing: border-box; text-transform: uppercase; border: 1px solid #ccc; border-radius: 4px; }
        button { background: #28a745; color: white; border: none; padding: 12px; cursor: pointer; border-radius: 4px; font-weight: bold; width: 100%; }
        .volver { display: inline-block; margin-top: 15px; padding: 6px 12px; text-decoration: none; background: #ffc107; color: #333; font-size: 0.8rem; border-radius: 4px; font-weight: bold; }
        .volver:hover { background: #e0a800; }
    </style>
</head>
<body>
    <div class="caja">
        <?php $volver = isset($_GET['volver']) ? trim($_GET['volver']) : 'index.php'; $volver_enc = htmlspecialchars($volver); ?>
        <form action="procesar.php" method="POST" onsubmit="return validarNombre()">
            <?php if ($volver !== 'index.php') { ?><input type="hidden" name="volver" value="<?= htmlspecialchars($volver) ?>"><?php } ?>
            <h2 style="margin-top:0;">NUEVO USUARIO</h2>
            <div id="alertaConsorcio" style="display:none; background:#d1ecf1; color:#0c5460; padding:8px; border-radius:4px; margin-bottom:10px; font-size:11px; border:1px solid #bee5eb;">
                ℹ️ El nombre "Consorcio" está reservado para usuarios consorcio.
            </div>
            <input type="text" name="apellido" id="apellido" placeholder="APELLIDO Y NOMBRE" required oninput="validarNombreConsorcio()">
            <div id="divConsorcio" style="display:none;">
                <input type="text" name="consorcio" id="consorcio" placeholder="CONSORCIO (Ej: EDIFICIO CENTRAL)" style="text-transform:uppercase;">
                <small style="color:#666; font-size:10px;">Nombre del edificio/consorcio para asociar con propiedades</small>
            </div>
            <input type="text" name="dni" placeholder="DNI" required>
            <input type="text" name="cuit" placeholder="CUIT">
            <input type="text" name="domicilio" placeholder="DOMICILIO">
            <input type="email" name="email" placeholder="EMAIL" style="text-transform:lowercase;">
            <input type="text" name="celular" placeholder="CELULAR">
            <button type="submit">GUARDAR REGISTRO</button>
            <a href="<?= $volver === 'index.php' ? 'index.php' : htmlspecialchars($volver) ?>" class="volver">← VOLVER</a>
        </form>
    </div>
    <script>
    function validarNombreConsorcio() {
        var apellido = document.getElementById('apellido').value.toUpperCase().trim();
        var alerta = document.getElementById('alertaConsorcio');
        var divCons = document.getElementById('divConsorcio');
        if (apellido.indexOf('CONSORCIO') >= 0) {
            alerta.style.display = 'block';
            divCons.style.display = 'block';
        } else {
            alerta.style.display = 'none';
            divCons.style.display = 'none';
        }
    }
    function validarNombre() {
        return true;
    }
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            history.back();
        }
    });
    </script>
<?php include 'timeout_sesion_inc.php'; ?>
</body>
</html>
