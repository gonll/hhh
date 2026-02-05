<?php
// 1. Conexión a la base de datos
$conexion = mysqli_connect("localhost", "root", "", "curso_java_php");

// 2. Obtener el ID del usuario desde la URL
$id = $_GET['id'];

// 3. Consultar los datos actuales (ahora incluye dni, cuit, domicilio)
$resultado = mysqli_query($conexion, "SELECT * FROM usuarios WHERE id = $id");
$u = mysqli_fetch_array($resultado);

if (!$u) {
    die("Usuario no encontrado.");
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>La Nave8 - Modificar Usuario</title>
    <style>
        body { font-family: 'Segoe UI', sans-serif; background: #f0f2f5; text-align: center; padding: 30px; }
        form { background: white; padding: 25px; border-radius: 10px; display: inline-block; box-shadow: 0 4px 15px rgba(0,0,0,0.1); width: 300px; }
        h3 { margin-top: 0; color: #333; font-size: 1.1rem; }
        label { display: block; text-align: left; font-size: 11px; font-weight: bold; margin-top: 10px; color: #666; }
        input { display: block; margin: 5px auto; padding: 10px; width: 100%; box-sizing: border-box; border: 1px solid #ddd; border-radius: 4px; text-transform: uppercase; }
        button { background: #ffc107; color: #212529; border: none; padding: 12px; cursor: pointer; width: 100%; font-weight: bold; border-radius: 4px; margin-top: 20px; }
        button:hover { background: #e0a800; }
        .volver { display: inline-block; margin-top: 15px; padding: 8px 16px; background: #ffc107; color: #333; text-decoration: none; font-size: 12px; font-weight: bold; border-radius: 4px; }
        .volver:hover { background: #e0a800; }
    </style>
</head>
<body>

    <form action="actualizar.php" method="POST">
        <h3>MODIFICAR REGISTRO</h3>
        
        <!-- ID oculto para saber a quién actualizar -->
        <input type="hidden" name="id" value="<?php echo $u['id']; ?>">
        
        <label>APELLIDO:</label>
        <input type="text" name="apellido" value="<?php echo $u['apellido']; ?>" required oninput="this.value = this.value.toUpperCase()">
        
        <label>DNI:</label>
        <input type="text" name="dni" value="<?php echo $u['dni']; ?>" maxlength="14" required>
        
        <label>CUIT:</label>
        <input type="text" name="cuit" value="<?php echo $u['cuit']; ?>" maxlength="14" required>
        
        <label>DOMICILIO:</label>
        <input type="text" name="domicilio" value="<?php echo $u['domicilio']; ?>" maxlength="100" required oninput="this.value = this.value.toUpperCase()">
        
        <button type="submit">GUARDAR CAMBIOS</button>
        <a href="ver_usuarios.php" class="volver">CANCELAR Y VOLVER</a>
    </form>

<script>
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        history.back();
    }
});
</script>
</body>
</html>

