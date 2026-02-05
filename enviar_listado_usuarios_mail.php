<?php
include 'db.php';
include_once __DIR__ . '/smtp_enviar.php';

$FROM_EMAIL = 'herrerayllobeta@gmail.com';
$FROM_NAME  = 'Sistema HHH';

// Comprobar si está configurada la contraseña SMTP
$smtp_ok = false;
if (file_exists(__DIR__ . '/mail_config.php')) {
    include __DIR__ . '/mail_config.php';
    $smtp_ok = !empty($MAIL_SMTP_USER) && !empty($MAIL_SMTP_PASS);
}

// Usuarios con email (excluir vacíos y null)
$sql = "SELECT id, apellido, dni, cuit, domicilio, email, celular FROM usuarios 
        WHERE email IS NOT NULL AND TRIM(email) != '' ORDER BY apellido";
$res = mysqli_query($conexion, $sql);
$usuarios_con_mail = [];
while ($row = mysqli_fetch_assoc($res)) {
    $usuarios_con_mail[] = $row;
}

// Construir listado en texto
function armarListado($conexion) {
    $sql = "SELECT apellido, dni, cuit, domicilio, email, celular FROM usuarios ORDER BY apellido";
    $res = mysqli_query($conexion, $sql);
    $lineas = ["LISTADO DE USUARIOS - Sistema HHH", "Fecha: " . date('d/m/Y H:i'), str_repeat("-", 60), ""];
    while ($f = mysqli_fetch_assoc($res)) {
        $lineas[] = "Apellido: " . ($f['apellido'] ?? '');
        $lineas[] = "  DNI: " . ($f['dni'] ?? '-') . "  CUIT: " . ($f['cuit'] ?? '-');
        $lineas[] = "  Domicilio: " . ($f['domicilio'] ?? '-');
        $lineas[] = "  Email: " . ($f['email'] ?? '-') . "  Celular: " . ($f['celular'] ?? '-');
        $lineas[] = "";
    }
    return implode("\r\n", $lineas);
}

$enviado = false;
$errores = [];
$ok_count = 0;
$detalle_error_smtp = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirmar'])) {
    set_time_limit(300); // 5 minutos para enviar a todos los destinatarios
    if (!$smtp_ok) {
        $errores[] = 'No está configurada la contraseña SMTP en mail_config.php (Contraseña de aplicación de Gmail).';
    } else {
        $listado = armarListado($conexion);
        $asunto = "Listado de usuarios - Sistema HHH " . date('d/m/Y');

        foreach ($usuarios_con_mail as $u) {
            $destino = trim($u['email']);
            if ($destino === '') continue;
            if (enviar_mail_smtp($destino, $asunto, $listado)) {
                $ok_count++;
            } else {
                $errores[] = $u['apellido'] . " (" . $destino . ")";
                // Guardar el motivo del fallo (solo la primera vez) para mostrarlo
                if (empty($detalle_error_smtp) && function_exists('smtp_ultimo_error')) {
                    $detalle_error_smtp = smtp_ultimo_error();
                }
            }
        }
    }
    $enviado = true;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Enviar listado por email</title>
    <style>
        body { font-family: 'Segoe UI', sans-serif; background: #f0f2f5; margin: 20px; }
        .card { background: white; padding: 25px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); max-width: 500px; margin: 0 auto; }
        h2 { color: #17a2b8; margin-top: 0; font-size: 18px; }
        p { margin: 10px 0; font-size: 14px; }
        .btn { display: inline-block; padding: 10px 20px; border-radius: 4px; text-decoration: none; font-weight: bold; cursor: pointer; border: none; font-size: 14px; }
        .btn-enviar { background: #17a2b8; color: white; }
        .btn-enviar:hover { background: #138496; }
        .btn-volver { background: #ffc107; color: #333; margin-top: 15px; }
        .btn-volver:hover { background: #e0a800; }
        form { margin: 15px 0; }
        .ok { color: #28a745; font-weight: bold; }
        .err { color: #dc3545; font-size: 12px; margin-top: 5px; }
        ul { margin: 5px 0; padding-left: 20px; }
    </style>
</head>
<body>
<div class="card">
    <h2>📧 Enviar listado de usuarios por email</h2>

    <?php if ($enviado): ?>
        <p class="ok">Se envió el listado a <?= $ok_count ?> usuario(s) con email.</p>
        <?php if (count($errores) > 0): ?>
            <p class="err">No se pudo enviar a: <?= count($errores) ?> destinatario(s)</p>
            <?php if (!empty($detalle_error_smtp)): ?>
                <p class="err" style="background:#fff3cd; padding:10px; border-radius:4px; margin:10px 0;"><strong>Motivo:</strong> <?= htmlspecialchars($detalle_error_smtp) ?></p>
            <?php endif; ?>
            <ul><li><?= implode("</li><li>", array_map('htmlspecialchars', $errores)) ?></li></ul>
        <?php endif; ?>
        <a href="index.php" class="btn btn-volver">← Volver al inicio</a>
    <?php elseif (count($usuarios_con_mail) === 0): ?>
        <p>No hay usuarios con email cargado. No se puede enviar el listado.</p>
        <a href="index.php" class="btn btn-volver">← Volver al inicio</a>
    <?php else: ?>
        <?php if (!$smtp_ok): ?>
            <p class="err">Para enviar por Gmail debes configurar <strong>mail_config.php</strong>: poné la <strong>contraseña de aplicación</strong> de Gmail (no la contraseña normal). Si tenés verificación en 2 pasos: Cuenta Google → Seguridad → Contraseñas de aplicaciones → Generar.</p>
        <?php endif; ?>
        <p>Se enviará un correo desde <strong><?= htmlspecialchars($FROM_EMAIL) ?></strong> a los <strong><?= count($usuarios_con_mail) ?></strong> usuario(s) que tienen email, con el listado completo de usuarios.</p>
        <form method="POST">
            <input type="hidden" name="confirmar" value="1">
            <button type="submit" class="btn btn-enviar">Enviar listado ahora</button>
        </form>
        <a href="index.php" class="btn btn-volver">Cancelar</a>
    <?php endif; ?>
</div>
</body>
</html>
