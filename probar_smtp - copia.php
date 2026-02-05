<?php
/**
 * Prueba de envío SMTP: envía un solo correo a la dirección configurada
 * y muestra el resultado o el error detallado. Útil para diagnosticar por qué no envía.
 */
include_once __DIR__ . '/smtp_enviar.php';

if (!file_exists(__DIR__ . '/mail_config.php')) {
    die('No existe mail_config.php');
}
include __DIR__ . '/mail_config.php';
$destino = !empty($MAIL_SMTP_USER) ? $MAIL_SMTP_USER : 'tu_email@gmail.com';
$listo = false;
$mensaje = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $listo = enviar_mail_smtp(
        $destino,
        'Prueba SMTP - Sistema HHH ' . date('H:i:s'),
        "Este es un correo de prueba.\n\nSi lo recibiste, el envío por Gmail está funcionando."
    );
    if (!$listo) {
        $mensaje = smtp_ultimo_error();
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Probar SMTP</title>
    <style>
        body { font-family: 'Segoe UI', sans-serif; background: #f0f2f5; margin: 20px; }
        .card { background: white; padding: 25px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); max-width: 560px; margin: 0 auto; }
        h2 { color: #17a2b8; margin-top: 0; }
        .ok { color: #28a745; font-weight: bold; }
        .err { color: #dc3545; background: #fff3cd; padding: 12px; border-radius: 4px; margin: 10px 0; white-space: pre-wrap; word-break: break-word; }
        .btn { display: inline-block; padding: 10px 20px; border-radius: 4px; text-decoration: none; font-weight: bold; border: none; cursor: pointer; font-size: 14px; margin-top: 10px; }
        .btn-enviar { background: #17a2b8; color: white; }
        .btn-volver { background: #6c757d; color: white; margin-left: 8px; }
    </style>
</head>
<body>
<div class="card">
    <h2>🔧 Probar envío SMTP (Gmail)</h2>
    <p>Envía un correo de prueba a <strong><?= htmlspecialchars($destino) ?></strong> para comprobar la conexión.</p>
    <?php if ($_SERVER['REQUEST_METHOD'] === 'POST'): ?>
        <?php if ($listo): ?>
            <p class="ok">✓ Correo enviado. Revisá la bandeja de entrada (y spam) de <?= htmlspecialchars($destino) ?>.</p>
        <?php else: ?>
            <p class="err"><strong>Error:</strong><br><?= htmlspecialchars($mensaje) ?></p>
        <?php endif; ?>
    <?php endif; ?>
    <form method="POST">
        <button type="submit" class="btn btn-enviar">Enviar correo de prueba</button>
    </form>
    <a href="index.php" class="btn btn-volver">Volver al inicio</a>
</div>
</body>
</html>
