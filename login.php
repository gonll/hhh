<?php
session_start();
if (!empty($_SESSION['acceso_id'])) {
    header('Location: index.php');
    exit;
}
include 'db.php';

$hay_accesos = false;
$tabla_existe = false;
$res_t = @mysqli_query($conexion, "SHOW TABLES LIKE 'accesos'");
if ($res_t && mysqli_num_rows($res_t) > 0) {
    $tabla_existe = true;
    $res = mysqli_query($conexion, "SELECT id FROM accesos LIMIT 1");
    if ($res && mysqli_num_rows($res) > 0) {
        $hay_accesos = true;
    }
}
$primera_vez = !$tabla_existe || !$hay_accesos;
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Ingreso al sistema - HHH</title>
    <style>
        body { font-family: 'Segoe UI', sans-serif; background: #f0f2f5; margin: 0; padding: 0; display: flex; justify-content: center; align-items: center; min-height: 100vh; }
        .card { background: white; padding: 30px; border-radius: 8px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); width: 320px; }
        h2 { color: #007bff; margin-top: 0; font-size: 16px; text-transform: uppercase; border-bottom: 2px solid #007bff; padding-bottom: 10px; }
        label { display: block; margin: 10px 0 4px; font-size: 11px; font-weight: bold; color: #555; }
        input, select { width: 100%; padding: 10px; box-sizing: border-box; border: 1px solid #ccc; border-radius: 4px; font-size: 14px; }
        button { width: 100%; padding: 12px; background: #28a745; color: white; border: none; border-radius: 4px; font-weight: bold; cursor: pointer; margin-top: 15px; font-size: 14px; }
        button:hover { background: #218838; }
        .error { background: #f8d7da; color: #721c24; padding: 10px; border-radius: 4px; margin-bottom: 15px; font-size: 12px; }
        .aviso { background: #fff3cd; color: #856404; padding: 10px; border-radius: 4px; margin-bottom: 15px; font-size: 12px; }
        .info { background: #d1ecf1; color: #0c5460; padding: 10px; border-radius: 4px; margin-bottom: 15px; font-size: 12px; }
    </style>
</head>
<body>
<div class="card">
    <?php if ($primera_vez): ?>
        <h2>Primera vez: crear administrador</h2>
        <div class="info">No hay usuarios de acceso. Creá el primero (nivel 3 = máximo).</div>
        <?php if (isset($_GET['error'])): ?>
            <div class="error">Error al crear. ¿La tabla accesos existe? Ejecutá agregar_tabla_accesos.php antes.</div>
        <?php endif; ?>
        <form action="crear_primer_acceso.php" method="POST">
            <label>Usuario</label>
            <input type="text" name="usuario" required autofocus placeholder="Nombre de usuario">
            <label>Clave</label>
            <input type="password" name="clave" required placeholder="Contraseña">
            <label>Nivel de acceso</label>
            <select name="nivel_acceso">
                <option value="3" selected>3 - Máximo (administrador)</option>
                <option value="2">2 - Estándar</option>
                <option value="1">1 - Restringido</option>
            </select>
            <button type="submit">CREAR Y ENTRAR</button>
        </form>
    <?php else: ?>
        <h2>Ingreso al sistema</h2>
        <?php if (isset($_GET['error'])): ?>
            <div class="error">Usuario o clave incorrectos.</div>
        <?php endif; ?>
        <?php if (isset($_GET['salir'])): ?>
            <div class="aviso">Sesión cerrada correctamente.</div>
        <?php endif; ?>
        <form action="procesar_login.php" method="POST">
            <label>Usuario</label>
            <input type="text" name="usuario" required autofocus placeholder="Nombre de usuario">
            <label>Clave</label>
            <input type="password" name="clave" required placeholder="Contraseña">
            <button type="submit">ENTRAR</button>
        </form>
    <?php endif; ?>
</div>
</body>
</html>
