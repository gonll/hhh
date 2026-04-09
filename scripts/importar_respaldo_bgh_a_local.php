<?php
/**
 * Por defecto este script YA NO pisa la base local. Para sumar datos BGH sin borrar adminhugo ni el resto:
 *
 *   php scripts/fusionar_respaldo_bgh_a_local.php
 *   php scripts/fusionar_respaldo_bgh_a_local.php ruta/al/archivo.sql
 *
 * Solo en emergencia (reemplaza tablas del dump y BORRA esos datos en la base indicada en .env):
 *
 *   php scripts/importar_respaldo_bgh_a_local.php --pisar-base-entera [archivo.sql]
 *
 * Opciones que aplican solo con --pisar-base-entera:
 *   --vincular-sofia   Tras el import destructivo, asigna acceso_creador_id de sofia a filas NULL.
 * --solo-vincular-sofia   No importa; solo ejecuta el UPDATE de vinculación (útil si ya pisaste antes).
 */
if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "Ejecutar solo por línea de comandos.\n");
    exit(1);
}

$root = dirname(__DIR__);
$defaultSql = $root . DIRECTORY_SEPARATOR . 'respaldo_BGHInmobiliaria_2026-04-09_14-21-05.sql';

$pisar = false;
$vincularSofia = false;
$soloVincular = false;
$sqlPath = $defaultSql;
foreach (array_slice($argv, 1) as $a) {
    if ($a === '--pisar-base-entera') {
        $pisar = true;
        continue;
    }
    if ($a === '--vincular-sofia' || $a === '-v') {
        $vincularSofia = true;
        continue;
    }
    if ($a === '--solo-vincular-sofia') {
        $soloVincular = true;
        continue;
    }
    if ($a !== '' && $a[0] !== '-') {
        $sqlPath = $a;
    }
}

if (!$pisar && !$soloVincular) {
    fwrite(STDERR, "El import que pisa toda la base está desactivado por defecto.\n\n");
    fwrite(STDERR, "Para agregar datos BGH a sistemahhh26 sin borrar lo existente:\n");
    fwrite(STDERR, "  php scripts/fusionar_respaldo_bgh_a_local.php\n");
    fwrite(STDERR, "  php scripts/fusionar_respaldo_bgh_a_local.php \"ruta\\archivo.sql\"\n\n");
    fwrite(STDERR, "Si necesitás reemplazar por completo las tablas del dump (peligroso):\n");
    fwrite(STDERR, "  php scripts/importar_respaldo_bgh_a_local.php --pisar-base-entera [archivo.sql]\n");
    exit(2);
}

$env = @parse_ini_file($root . '/.env') ?: [];
$host = $env['DB_HOST'] ?? 'localhost';
$user = $env['DB_USER'] ?? 'root';
$pass = $env['DB_PASS'] ?? '';
$db = $env['DB_NAME'] ?? 'sistemahhh26';

$conn = mysqli_connect($host, $user, $pass, $db);
if (!$conn) {
    fwrite(STDERR, 'Conexión fallida: ' . mysqli_connect_error() . "\n");
    exit(1);
}
mysqli_set_charset($conn, 'utf8mb4');

function tenant_inmob_vincular_sofia_datos_principal(mysqli $conn): void
{
    $r = mysqli_query($conn, "SELECT id FROM accesos WHERE LOWER(TRIM(usuario)) = 'sofia' LIMIT 1");
    if (!$r || !($row = mysqli_fetch_assoc($r))) {
        fwrite(STDERR, "No hay usuario de acceso 'sofia'; no se puede vincular.\n");
        return;
    }
    $sid = (int)$row['id'];
    if ($sid <= 0) {
        return;
    }
    mysqli_query($conn, "UPDATE propiedades SET acceso_creador_id = $sid WHERE acceso_creador_id IS NULL");
    $p = (int)mysqli_affected_rows($conn);
    mysqli_query($conn, "UPDATE usuarios SET acceso_creador_id = $sid WHERE acceso_creador_id IS NULL");
    $u = (int)mysqli_affected_rows($conn);
    echo "\nVinculado ámbito Sofía (acceso id={$sid}): propiedades: {$p}, usuarios: {$u}\n";
}

if (!$soloVincular && $pisar) {
    if (!is_readable($sqlPath)) {
        fwrite(STDERR, "No se puede leer: {$sqlPath}\n");
        exit(1);
    }
    $sql = file_get_contents($sqlPath);
    if ($sql === false) {
        fwrite(STDERR, "Error leyendo SQL.\n");
        exit(1);
    }

    echo "(--pisar-base-entera) Importando en `{$db}` desde: {$sqlPath}\n";

    mysqli_query($conn, 'SET FOREIGN_KEY_CHECKS=0');
    if (!mysqli_multi_query($conn, $sql)) {
        fwrite(STDERR, 'Error en importación: ' . mysqli_error($conn) . "\n");
        exit(1);
    }
    do {
        if ($r = mysqli_store_result($conn)) {
            mysqli_free_result($r);
        }
    } while (mysqli_more_results($conn) && mysqli_next_result($conn));
    if (mysqli_errno($conn)) {
        fwrite(STDERR, 'Error tras consultas: ' . mysqli_error($conn) . "\n");
        exit(1);
    }
    mysqli_query($conn, 'SET FOREIGN_KEY_CHECKS=1');

    $tablas = ['accesos', 'alquileres', 'arriendos', 'config', 'cuentas', 'indices', 'propiedades', 'usuarios'];
    echo "\nFilas por tabla:\n";
    foreach ($tablas as $t) {
        $tEsc = mysqli_real_escape_string($conn, $t);
        $q = mysqli_query($conn, "SELECT COUNT(*) AS c FROM `{$tEsc}`");
        $c = $q ? (int)(mysqli_fetch_assoc($q)['c'] ?? 0) : -1;
        echo "  {$t}: {$c}\n";
    }
}

if ($vincularSofia || $soloVincular) {
    tenant_inmob_vincular_sofia_datos_principal($conn);
}

mysqli_close($conn);
echo "Listo.\n";
