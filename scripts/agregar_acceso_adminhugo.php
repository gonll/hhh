<?php
/**
 * Vuelve a crear el usuario de acceso adminhugo si no existe (p. ej. tras importar solo el dump BGH).
 * La clave es el hash del respaldo sistemahhh26 del 2026-04-09 (misma contraseña que tenías entonces).
 *
 * Uso: php scripts/agregar_acceso_adminhugo.php
 */
if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "Solo CLI.\n");
    exit(1);
}

$root = dirname(__DIR__);
$env = @parse_ini_file($root . '/.env') ?: [];
$conn = mysqli_connect(
    $env['DB_HOST'] ?? 'localhost',
    $env['DB_USER'] ?? 'root',
    $env['DB_PASS'] ?? '',
    $env['DB_NAME'] ?? 'sistemahhh26'
);
if (!$conn) {
    fwrite(STDERR, mysqli_connect_error() . "\n");
    exit(1);
}
mysqli_set_charset($conn, 'utf8mb4');

$r = mysqli_query($conn, "SELECT id FROM accesos WHERE usuario = 'adminhugo' LIMIT 1");
if ($r && mysqli_num_rows($r) > 0) {
    echo "adminhugo ya existe.\n";
    exit(0);
}

$clave = '$2y$10$DVHtCAT8ldle6tPDOUYS9u4vk.1Ic9QSIBUBj16Kq8juSKQcLIrMW';
$stmt = mysqli_prepare($conn, 'INSERT INTO accesos (usuario, clave, nivel_acceso, creado_por_id) VALUES (?, ?, 3, NULL)');
if (!$stmt) {
    fwrite(STDERR, mysqli_error($conn) . "\n");
    exit(1);
}
$u = 'adminhugo';
mysqli_stmt_bind_param($stmt, 'ss', $u, $clave);
if (!mysqli_stmt_execute($stmt)) {
    fwrite(STDERR, mysqli_stmt_error($stmt) . "\n");
    exit(1);
}
mysqli_stmt_close($stmt);
echo 'adminhugo creado (id=' . mysqli_insert_id($conn) . "). Usá la misma clave que antes del respaldo BGH.\n";
