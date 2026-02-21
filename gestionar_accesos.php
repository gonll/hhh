<?php
include 'db.php';
include 'verificar_sesion.php';

if (empty($_SESSION['acceso_nivel']) || $_SESSION['acceso_nivel'] < 3) {
    header('Location: index.php');
    exit;
}

$mensaje = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['crear'])) {
    $usuario = trim($_POST['usuario'] ?? '');
    $clave   = $_POST['clave'] ?? '';
    $nivel   = (int)($_POST['nivel_acceso'] ?? 2);
    if ($nivel < 0 || $nivel > 3) $nivel = 2;
    if ($usuario !== '' && $clave !== '') {
        $hash = password_hash($clave, PASSWORD_DEFAULT);
        $usuario_esc = mysqli_real_escape_string($conexion, $usuario);
        $hash_esc = mysqli_real_escape_string($conexion, $hash);
        $creado_por = (int)($_SESSION['acceso_id'] ?? 0);
        $ins = "INSERT INTO accesos (usuario, clave, nivel_acceso, creado_por_id) VALUES ('$usuario_esc', '$hash_esc', $nivel, $creado_por)";
        if (mysqli_query($conexion, $ins)) {
            $mensaje = 'Usuario de acceso creado correctamente.';
        } else {
            $mensaje = 'Error: ' . (strpos(mysqli_error($conexion), 'Duplicate') !== false ? 'Ese usuario ya existe.' : mysqli_error($conexion));
        }
    } else {
        $mensaje = 'Usuario y clave son obligatorios.';
    }
}

$lista = mysqli_query($conexion, "SELECT id, usuario, nivel_acceso, fecha_creacion FROM accesos ORDER BY nivel_acceso DESC, usuario ASC");
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Gestionar accesos - HHH</title>
    <style>
        body { font-family: 'Segoe UI', sans-serif; background: #f0f2f5; margin: 0; padding: 10px; }
        .card { background: white; padding: 12px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); max-width: 500px; margin: 0 auto 12px; }
        h2 { color: #007bff; margin: 0 0 8px 0; font-size: 13px; text-transform: uppercase; border-bottom: 2px solid #007bff; padding-bottom: 5px; }
        label { display: block; margin: 4px 0 2px; font-size: 10px; font-weight: bold; color: #555; }
        input, select { width: 100%; padding: 5px; box-sizing: border-box; border: 1px solid #ccc; border-radius: 4px; font-size: 12px; }
        button { padding: 6px 14px; background: #28a745; color: white; border: none; border-radius: 4px; font-weight: bold; cursor: pointer; font-size: 11px; }
        button:hover { background: #218838; }
        .volver { display: inline-block; margin-top: 8px; padding: 5px 12px; background: #ffc107; color: #333; text-decoration: none; font-weight: bold; border-radius: 4px; font-size: 10px; }
        .volver:hover { background: #e0a800; }
        table { width: 100%; border-collapse: collapse; font-size: 11px; margin-top: 8px; }
        th, td { border: 1px solid #ddd; padding: 5px; text-align: left; }
        th { background: #007bff; color: white; }
        .ok { color: #28a745; font-size: 11px; margin: 4px 0; }
        .err { color: #dc3545; font-size: 11px; margin: 4px 0; }
        .fila-form { display: flex; gap: 10px; align-items: flex-end; flex-wrap: wrap; }
        .fila-form > div { flex: 1; min-width: 100px; }
        .fila-form > div:last-child { flex: 0 0 120px; }
        .fila-form .btn-wrap { flex: 0 0 auto; }
    </style>
</head>
<body>

<div class="card">
    <h2>Gestionar usuarios de acceso</h2>
    <?php if ($mensaje !== ''): ?>
        <p class="<?= strpos($mensaje, 'Error') !== false ? 'err' : 'ok' ?>"><?= htmlspecialchars($mensaje) ?></p>
    <?php endif; ?>
    <form method="POST" class="form-nav-enter">
        <div class="fila-form">
            <div>
                <label>Usuario</label>
                <input type="text" name="usuario" required placeholder="Login">
            </div>
            <div>
                <label>Clave</label>
                <input type="password" name="clave" required placeholder="Contraseña">
            </div>
            <div>
                <label>Nivel</label>
                <select name="nivel_acceso">
                    <option value="0">0 - Partes desde cel</option>
                    <option value="1">1 - Restringido</option>
                    <option value="2" selected>2 - Estándar</option>
                    <option value="3">3 - Máximo</option>
                </select>
            </div>
            <div class="btn-wrap">
                <button type="submit" name="crear">Crear</button>
            </div>
        </div>
    </form>
    <a href="index.php" class="volver">← Volver al panel</a>
</div>

<div class="card">
    <h2>Usuarios de acceso</h2>
    <table>
        <thead>
            <tr><th>Usuario</th><th>Nivel</th><th>Fecha creación</th></tr>
        </thead>
        <tbody>
            <?php
            $nombres_nivel = [0 => 'Partes desde cel', 1 => 'Restringido', 2 => 'Estándar', 3 => 'Máximo'];
            while ($r = mysqli_fetch_assoc($lista)):
                $n = (int)$r['nivel_acceso'];
                $nombre_nivel = $nombres_nivel[$n] ?? $n;
            ?>
                <tr>
                    <td><?= htmlspecialchars($r['usuario']) ?></td>
                    <td><?= $n ?> - <?= htmlspecialchars($nombre_nivel) ?></td>
                    <td><?= date('d/m/Y H:i', strtotime($r['fecha_creacion'])) ?></td>
                </tr>
            <?php endwhile; ?>
        </tbody>
    </table>
</div>

<script>
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        window.location.href = 'index.php';
    }
});
</script>
<?php include 'timeout_sesion_inc.php'; ?>
</body>
</html>
