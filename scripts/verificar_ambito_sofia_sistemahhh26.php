<?php
/**
 * Verificación de conteos en sistemahhh26 (o DB_NAME del .env): totales y ámbito Sofía.
 * Uso:
 *   php scripts/verificar_ambito_sofia_sistemahhh26.php
 *   php scripts/verificar_ambito_sofia_sistemahhh26.php ruta/al/.env_del_servidor
 *
 * En el servidor (SSH, con el .env de producción en la raíz del proyecto):
 *   php scripts/verificar_ambito_sofia_sistemahhh26.php
 */
if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "Solo CLI.\n");
    exit(1);
}

$root = dirname(__DIR__);
$envPath = $root . '/.env';
foreach (array_slice($argv, 1) as $a) {
    if ($a !== '' && $a[0] !== '-' && is_readable($a)) {
        $envPath = $a;
        break;
    }
}

$env = @parse_ini_file($envPath) ?: [];
$host = $env['DB_HOST'] ?? 'localhost';
$user = $env['DB_USER'] ?? 'root';
$pass = $env['DB_PASS'] ?? '';
$db = $env['DB_NAME'] ?? 'sistemahhh26';

$conn = @mysqli_connect($host, $user, $pass, $db);
if (!$conn) {
    fwrite(STDERR, 'Conexión fallida: ' . mysqli_connect_error() . "\n");
    fwrite(STDERR, "Archivo env: {$envPath}\n");
    exit(1);
}
mysqli_set_charset($conn, 'utf8mb4');

echo "Host: {$host}  Base: {$db}  (env: {$envPath})\n\n";

$q = function (string $sql) use ($conn) {
    $r = mysqli_query($conn, $sql);
    if (!$r) {
        fwrite(STDERR, mysqli_error($conn) . "\n");
        return null;
    }
    return mysqli_fetch_assoc($r);
};

$t = ['accesos', 'usuarios', 'propiedades', 'alquileres', 'cuentas', 'indices', 'config'];
foreach ($t as $tab) {
    $row = $q("SELECT COUNT(*) AS n FROM `{$tab}`");
    echo str_pad($tab, 14) . (int)($row['n'] ?? 0) . "\n";
}

$sof = $q("SELECT id FROM accesos WHERE LOWER(TRIM(usuario)) = 'sofia' LIMIT 1");
$sid = $sof ? (int)$sof['id'] : 0;
echo "\nSofía acceso id: " . ($sid ?: '(no existe)') . "\n";

if ($sid > 0) {
    $row = $q("SELECT COUNT(*) AS n FROM usuarios WHERE acceso_creador_id = {$sid}");
    echo "Usuarios ámbito Sofía: " . (int)($row['n'] ?? 0) . "\n";
    $row = $q("SELECT COUNT(*) AS n FROM propiedades WHERE acceso_creador_id = {$sid}");
    echo "Propiedades ámbito Sofía: " . (int)($row['n'] ?? 0) . "\n";
    $row = $q("SELECT COUNT(DISTINCT al.alquiler_id) AS n FROM alquileres al
        INNER JOIN propiedades p ON p.propiedad_id = al.propiedad_id
        WHERE p.acceso_creador_id = {$sid}");
    echo "Alquileres (prop. Sofía): " . (int)($row['n'] ?? 0) . "\n";
    $row = $q("SELECT COUNT(*) AS n FROM cuentas c
        INNER JOIN usuarios u ON u.id = c.usuario_id
        WHERE u.acceso_creador_id = {$sid}");
    echo "Cuentas (usuarios Sofía): " . (int)($row['n'] ?? 0) . "\n";
}

mysqli_close($conn);
echo "\nListo.\n";
