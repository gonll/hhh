<?php
include 'verificar_sesion.php';
include 'db.php';
include 'smtp_enviar.php';

// Buscar el email del usuario "herrera hugo" - búsqueda más flexible
$sql_busqueda = "SELECT apellido, email FROM usuarios 
    WHERE (UPPER(apellido) LIKE '%HERRERA%HUG%' 
        OR UPPER(apellido) LIKE '%HERRERA HUG%' 
        OR UPPER(apellido) LIKE '%HERRERA%HECTOR%'
        OR UPPER(apellido) = 'HERRERA HUGO'
        OR UPPER(apellido) LIKE 'HERRERA HECTOR%')
    AND email IS NOT NULL 
    AND email != ''
    AND TRIM(email) != ''
    LIMIT 1";

$res_usuario = mysqli_query($conexion, $sql_busqueda);
$email_destino = null;
$nombre_usuario = '';

if ($res_usuario && $row_usuario = mysqli_fetch_assoc($res_usuario)) {
    $email_destino = trim($row_usuario['email'] ?? '');
    $nombre_usuario = trim($row_usuario['apellido'] ?? '');
}

if (empty($email_destino)) {
    // Intentar buscar sin restricción de email para debug
    $sql_debug = "SELECT apellido, email FROM usuarios WHERE UPPER(apellido) LIKE '%HERRERA%HUG%' LIMIT 5";
    $res_debug = mysqli_query($conexion, $sql_debug);
    $debug_info = 'No se encontró email. Usuarios encontrados: ';
    while ($row = mysqli_fetch_assoc($res_debug)) {
        $debug_info .= $row['apellido'] . ' (email: ' . ($row['email'] ?? 'NULL') . '); ';
    }
    header('Location: index.php?mail_error=' . urlencode('No se encontró el email del usuario Herrera Hugo. ' . $debug_info));
    exit;
}

// Validar formato de email
if (!filter_var($email_destino, FILTER_VALIDATE_EMAIL)) {
    header('Location: index.php?mail_error=' . urlencode('Email inválido: ' . $email_destino));
    exit;
}

// Enviar el mail
$asunto = 'Mensaje desde Sistema';
$cuerpo = 'hola';

$enviado = enviar_mail_smtp($email_destino, $asunto, $cuerpo);

if ($enviado) {
    header('Location: index.php?mail_enviado=1');
    exit;
} else {
    $error = smtp_ultimo_error();
    if (empty($error)) {
        $error = 'Error desconocido al enviar el mail';
    }
    header('Location: index.php?mail_error=' . urlencode('Error al enviar a ' . $email_destino . ': ' . $error));
    exit;
}
