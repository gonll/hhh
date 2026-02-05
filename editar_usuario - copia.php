<?php
include 'db.php';

if (!isset($_GET['id'])) {
    die("ID de usuario no proporcionado.");
}

$id = $_GET['id'];
// Obtener datos actuales
$query = mysqli_query($conexion, "SELECT * FROM usuarios WHERE id = $id");
$user = mysqli_fetch_assoc($query);

if (!$user) {
    die("Usuario no encontrado.");
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Editar Usuario</title>
    <style>
        body { font-family: 'Segoe UI', sans-serif; background: #f0f2f5; display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0; }
        .card { background: white; padding: 25px; border-radius: 8px; box-shadow: 0 4px 15px rgba(0,0,0,0.2); width: 380px; }
        h2 { color: #007bff; margin-top: 0; font-size: 18px; text-transform: uppercase; border-bottom: 2px solid #eee; padding-bottom: 10px; }
        label { display: block; margin: 10px 0 5px; font-size: 12px; font-weight: bold; color: #555; }
        input { width: 100%; padding: 8px; box-sizing: border-box; border: 1px solid #ccc; border-radius: 4px; text-transform: uppercase; }
        .btns { margin-top: 20px; display: flex; gap: 10px; }
        button, .btn-cancelar { flex: 1; padding: 10px; border: none; border-radius: 4px; cursor: pointer; font-weight: bold; text-align: center; text-decoration: none; font-size: 13px; }
        button { background: #28a745; color: white; }
        .btn-cancelar { background: #6c757d; color: white; }
        button:hover { background: #218838; }
    </style>
</head>
<body>

<div class="card">
    <h2>Editar Usuario</h2>
    <form action="actualizar_usuario.php" method="POST">
        <input type="hidden" name="id" value="<?= $user['id'] ?>">
        
        <label>Apellido y nombre</label>
        <input type="text" name="apellido" value="<?= htmlspecialchars($user['apellido'] ?? '') ?>" required autofocus>
        
        <label>DNI</label>
        <input type="text" name="dni" value="<?= htmlspecialchars($user['dni'] ?? '') ?>" maxlength="14" placeholder="Sin puntos">
        
        <label>CUIT</label>
        <input type="text" name="cuit" value="<?= htmlspecialchars($user['cuit'] ?? '') ?>" maxlength="14" placeholder="Sin guiones">
        
        <label>Domicilio</label>
        <input type="text" name="domicilio" value="<?= htmlspecialchars($user['domicilio'] ?? '') ?>" maxlength="150" placeholder="Calle, número, localidad">
        
        <label>Email</label>
        <input type="email" name="email" value="<?= htmlspecialchars($user['email'] ?? '') ?>" maxlength="100" placeholder="ejemplo@correo.com" style="text-transform:lowercase;">
        
        <label>Celular</label>
        <input type="text" name="celular" value="<?= htmlspecialchars($user['celular'] ?? '') ?>" maxlength="20" placeholder="Ej: 381-1234567">
        
        <div class="btns">
            <button type="submit">GUARDAR CAMBIOS</button>
            <a href="index.php" class="btn-cancelar">VOLVER</a>
        </div>
    </form>
</div>

</body>
</html>