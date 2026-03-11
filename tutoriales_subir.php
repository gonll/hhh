<?php
/**
 * Subir videos de tutoriales a videos/capturas/
 */
include 'db.php';
include 'verificar_sesion.php';
if (isset($_SESSION['acceso_nivel']) && $_SESSION['acceso_nivel'] < 2) {
    header('Location: index.php?msg=sin_permiso');
    exit;
}

$carpeta = __DIR__ . '/videos/descargas';
if (!is_dir($carpeta)) {
    mkdir($carpeta, 0755, true);
}

$mensaje = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['video'])) {
    $num = (int)($_POST['tutorial_num'] ?? 1);
    if ($num < 1 || $num > 10) {
        $error = 'Número de tutorial inválido.';
    } else {
        $f = $_FILES['video'];
        if ($f['error'] !== UPLOAD_ERR_OK) {
            $error = 'Error al subir: ' . ($f['error'] === UPLOAD_ERR_INI_SIZE ? 'archivo muy grande (aumente upload_max_filesize en php.ini)' : ($f['error'] === UPLOAD_ERR_FORM_SIZE ? 'archivo muy grande' : 'código ' . $f['error']));
        } else {
            $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
            $permitidos = ['mp4', 'webm', 'avi', 'mov', 'mkv'];
            if (!in_array($ext, $permitidos)) {
                $error = 'Formato no permitido. Use: ' . implode(', ', $permitidos);
            } else {
                $nombre = sprintf('tutorial_%02d.%s', $num, $ext);
                $destino = $carpeta . '/' . $nombre;
                if (move_uploaded_file($f['tmp_name'], $destino)) {
                    $mensaje = 'Video subido correctamente: ' . htmlspecialchars($nombre);
                } else {
                    $error = 'No se pudo guardar el archivo. Verifique permisos de la carpeta videos/descargas.';
                }
            }
        }
    }
}

$videos_existentes = [];
if (is_dir($carpeta)) {
    foreach (glob($carpeta . '/tutorial_*') as $f) {
        $videos_existentes[basename($f)] = basename($f);
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Subir tutoriales</title>
    <style>
        body { font-family: 'Segoe UI', sans-serif; padding: 20px; max-width: 500px; margin: 0 auto; }
        h2 { color: #007bff; }
        .msg { padding: 10px; margin: 10px 0; border-radius: 4px; }
        .ok { background: #d4edda; color: #155724; }
        .err { background: #f8d7da; color: #721c24; }
        form { margin: 15px 0; }
        label { display: block; margin: 8px 0 4px; font-weight: bold; }
        input[type="file"], select { margin-bottom: 10px; }
        button { background: #28a745; color: white; border: none; padding: 10px 20px; border-radius: 4px; cursor: pointer; font-weight: bold; }
        button:hover { background: #218838; }
        a { color: #007bff; text-decoration: none; }
        a:hover { text-decoration: underline; }
        ul { margin: 10px 0; padding-left: 20px; }
    </style>
</head>
<body>
    <h2>Subir video de tutorial</h2>
    <p><a href="index.php">← Volver al principal</a></p>
    <?php if ($mensaje): ?>
        <div class="msg ok"><?= $mensaje ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="msg err"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <form method="post" enctype="multipart/form-data">
        <label>Tutorial número:</label>
        <select name="tutorial_num">
            <?php for ($i = 1; $i <= 10; $i++): ?>
                <option value="<?= $i ?>"><?= $i ?></option>
            <?php endfor; ?>
        </select>
        <label>Archivo de video (mp4, webm, avi, mov, mkv):</label>
        <input type="file" name="video" accept="video/mp4,video/webm,video/avi,video/quicktime,video/x-matroska" required>
        <br><br>
        <button type="submit">Subir</button>
    </form>
    <?php if (!empty($videos_existentes)): ?>
        <h3>Videos en carpeta:</h3>
        <ul>
            <?php foreach ($videos_existentes as $v): ?>
                <li><?= htmlspecialchars($v) ?></li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
    <hr style="margin: 20px 0;">
    <p style="font-size: 12px; color: #666;"><strong>Si la subida falla:</strong> Copiá el video manualmente a la carpeta <code>videos/descargas/</code> del proyecto. Para el tutorial 1, nombrá el archivo <code>tutorial_01.mp4</code> (o "Tutorial 1, abm usuarios.mp4").</p>
</body>
</html>
