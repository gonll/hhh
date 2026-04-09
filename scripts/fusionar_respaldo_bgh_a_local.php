<?php
/**
 * Fusiona un dump BGH (.sql) en la base indicada en .env (DB_NAME, p. ej. sistemahhh26) SIN borrar datos existentes.
 * 1) Importa el dump a una base temporal y la borra al terminar.
 * 2) Crea acceso sofia si no existe; copia usuarios, propiedades, alquileres, cuentas, índices (IPC de sofia) y claves config que falten.
 * 3) Nuevos IDs en destino; FKs remapeadas. Lo importado usa acceso_creador_id = id de sofia (ámbito Sofía).
 *
 * Dedup: no inserta usuario si ya existe mismo dni + mismo acceso_creador_id (sofia); no inserta propiedad si el padrón ya existe;
 * no inserta movimiento si coincide usuario+fecha+concepto+monto+comprobante+referencia.
 *
 * Archivo por defecto: el primero que exista entre respaldo_BGHInmobiliaria migracion.sql y respaldo_BGHInmobiliaria_2026-04-09_14-21-05.sql
 *
 * Servidor: configurar .env con host/usuario/clave de la base receptora (sistemahhh26), subir el .sql y ejecutar por SSH desde la raíz del proyecto:
 *   php scripts/fusionar_respaldo_bgh_a_local.php
 *   php scripts/fusionar_respaldo_bgh_a_local.php "respaldo_BGHInmobiliaria migracion.sql"
 *
 * Uso local (desde la raíz del proyecto):
 *   php scripts/fusionar_respaldo_bgh_a_local.php
 *   php scripts/fusionar_respaldo_bgh_a_local.php ruta/al/otro.sql
 */
if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "Solo CLI.\n");
    exit(1);
}

$root = dirname(__DIR__);
$defaultSqlCandidates = [
    $root . DIRECTORY_SEPARATOR . 'respaldo_BGHInmobiliaria migracion.sql',
    $root . DIRECTORY_SEPARATOR . 'respaldo_BGHInmobiliaria_2026-04-09_14-21-05.sql',
];
$defaultSql = $defaultSqlCandidates[0];
foreach ($defaultSqlCandidates as $cand) {
    if (is_readable($cand)) {
        $defaultSql = $cand;
        break;
    }
}
$sqlPath = $defaultSql;
foreach (array_slice($argv, 1) as $a) {
    if ($a !== '' && $a[0] !== '-') {
        $sqlPath = $a;
    }
}

if (!is_readable($sqlPath)) {
    fwrite(STDERR, "No se puede leer: {$sqlPath}\n");
    exit(1);
}

$env = @parse_ini_file($root . '/.env') ?: [];
$host = $env['DB_HOST'] ?? 'localhost';
$user = $env['DB_USER'] ?? 'root';
$pass = $env['DB_PASS'] ?? '';
$localDb = $env['DB_NAME'] ?? 'sistemahhh26';

$conn = mysqli_connect($host, $user, $pass);
if (!$conn) {
    fwrite(STDERR, 'Conexión: ' . mysqli_connect_error() . "\n");
    exit(1);
}
mysqli_set_charset($conn, 'utf8mb4');

$tmpDb = 'bgh_fusion_tmp_' . gmdate('Ymd_His');
$tmpDbEsc = preg_replace('/[^a-z0-9_]/i', '', $tmpDb);
if ($tmpDbEsc !== $tmpDb) {
    $tmpDbEsc = 'bgh_fusion_tmp_' . bin2hex(random_bytes(4));
}

echo "Creando base temporal `{$tmpDbEsc}` e importando dump...\n";
mysqli_query($conn, 'CREATE DATABASE IF NOT EXISTS `' . $tmpDbEsc . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
mysqli_select_db($conn, $tmpDbEsc);

$sql = file_get_contents($sqlPath);
if ($sql === false) {
    @mysqli_query($conn, 'DROP DATABASE IF EXISTS `' . $tmpDbEsc . '`');
    fwrite(STDERR, "Error leyendo SQL.\n");
    exit(1);
}

mysqli_query($conn, 'SET FOREIGN_KEY_CHECKS=0');
if (!mysqli_multi_query($conn, $sql)) {
    $err = mysqli_error($conn);
    mysqli_select_db($conn, $localDb);
    @mysqli_query($conn, 'DROP DATABASE IF EXISTS `' . $tmpDbEsc . '`');
    fwrite(STDERR, 'Import temp: ' . $err . "\n");
    exit(1);
}
do {
    if ($r = mysqli_store_result($conn)) {
        mysqli_free_result($r);
    }
} while (mysqli_more_results($conn) && mysqli_next_result($conn));
mysqli_query($conn, 'SET FOREIGN_KEY_CHECKS=1');

mysqli_select_db($conn, $localDb);
echo "Fusionando en `{$localDb}`...\n";
echo 'Origen (dump BGH): ' . $sqlPath . "\n";

// --- id de sofia en dump y en local ---
$r = mysqli_query($conn, "SELECT id, clave, nivel_acceso FROM `{$tmpDbEsc}`.accesos WHERE LOWER(TRIM(usuario)) = 'sofia' LIMIT 1");
if (!$r || mysqli_num_rows($r) === 0) {
    @mysqli_query($conn, 'DROP DATABASE IF EXISTS `' . $tmpDbEsc . '`');
    fwrite(STDERR, "El dump no contiene usuario de acceso 'sofia'.\n");
    exit(1);
}
$dumpSofia = mysqli_fetch_assoc($r);
$dumpSofiaId = (int)$dumpSofia['id'];

$r2 = mysqli_query($conn, "SELECT id FROM accesos WHERE LOWER(TRIM(usuario)) = 'sofia' LIMIT 1");
$localSofiaId = 0;
if ($r2 && ($row2 = mysqli_fetch_assoc($r2))) {
    $localSofiaId = (int)$row2['id'];
} else {
    $stmt = mysqli_prepare($conn, 'INSERT INTO accesos (usuario, clave, nivel_acceso, creado_por_id) VALUES (?, ?, ?, NULL)');
    $u = 'sofia';
    $cl = $dumpSofia['clave'];
    $n = (int)$dumpSofia['nivel_acceso'];
    mysqli_stmt_bind_param($stmt, 'ssi', $u, $cl, $n);
    mysqli_stmt_execute($stmt);
    $localSofiaId = (int)mysqli_insert_id($conn);
    mysqli_stmt_close($stmt);
    echo "Creado acceso sofia en local (id={$localSofiaId}).\n";
}

$tagAmbito = $localSofiaId;

$propColsLocal = [];
$rPc = mysqli_query($conn, 'SHOW COLUMNS FROM propiedades');
while ($rPc && ($c = mysqli_fetch_assoc($rPc))) {
    $propColsLocal[$c['Field']] = true;
}

$alqColsLocal = [];
$rAqc = mysqli_query($conn, 'SHOW COLUMNS FROM alquileres');
while ($rAqc && ($c = mysqli_fetch_assoc($rAqc))) {
    $alqColsLocal[$c['Field']] = true;
}

$userMap = [];
$resU = mysqli_query($conn, "SELECT * FROM `{$tmpDbEsc}`.usuarios ORDER BY id ASC");
while ($resU && ($row = mysqli_fetch_assoc($resU))) {
    $oldId = (int)$row['id'];
    $dniEsc = mysqli_real_escape_string($conn, (string)$row['dni']);
    $rEx = mysqli_query($conn, "SELECT id FROM usuarios WHERE dni = '{$dniEsc}' AND acceso_creador_id = " . (int)$tagAmbito . " LIMIT 1");
    if ($rEx && ($ex = mysqli_fetch_assoc($rEx))) {
        $userMap[$oldId] = (int)$ex['id'];
        continue;
    }
    $stmt = mysqli_prepare($conn, 'INSERT INTO usuarios (acceso_creador_id, apellido, dni, cuit, domicilio, email, celular, consorcio) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
    $ac = $tagAmbito;
    $ap = $row['apellido'];
    $dni = $row['dni'];
    $cuit = $row['cuit'];
    $dom = $row['domicilio'];
    $em = $row['email'];
    $cel = $row['celular'];
    $cons = $row['consorcio'];
    mysqli_stmt_bind_param($stmt, 'isssssss', $ac, $ap, $dni, $cuit, $dom, $em, $cel, $cons);
    mysqli_stmt_execute($stmt);
    $userMap[$oldId] = (int)mysqli_insert_id($conn);
    mysqli_stmt_close($stmt);
}
echo 'Usuarios (mapeados / insertados): ' . count($userMap) . "\n";

$propMap = [];
$skippedPadron = 0;
$resP = mysqli_query($conn, "SELECT * FROM `{$tmpDbEsc}`.propiedades ORDER BY propiedad_id ASC");
while ($resP && ($row = mysqli_fetch_assoc($resP))) {
    $oldPid = (int)$row['propiedad_id'];
    $pad = $row['padron'];
    $padEsc = mysqli_real_escape_string($conn, $pad);
    $chk = mysqli_query($conn, "SELECT propiedad_id FROM propiedades WHERE padron = '{$padEsc}' LIMIT 1");
    if ($chk && mysqli_num_rows($chk) > 0) {
        $exP = mysqli_fetch_assoc($chk);
        $propMap[$oldPid] = (int)$exP['propiedad_id'];
        $skippedPadron++;
        continue;
    }
    $propOld = (int)$row['propietario_id'];
    if (!isset($userMap[$propOld])) {
        fwrite(STDERR, "Omito propiedad padron={$pad}: propietario_id tmp {$propOld} sin mapeo.\n");
        continue;
    }
    $newProp = $userMap[$propOld];
    $ac = (int)$tagAmbito;
    $pr = mysqli_real_escape_string($conn, (string)$row['propiedad']);
    $ci = mysqli_real_escape_string($conn, (string)($row['ciudad'] ?? ''));
    $det = mysqli_real_escape_string($conn, (string)$row['detalle']);
    $mapaSrc = $row['ubicacion_mapa'] ?? null;
    $umSql = ($mapaSrc !== null && $mapaSrc !== '')
        ? "'" . mysqli_real_escape_string($conn, (string)$mapaSrc) . "'" : 'NULL';
    $fjSql = ($row['fotos_json'] ?? null) !== null && ($row['fotos_json'] ?? '') !== ''
        ? "'" . mysqli_real_escape_string($conn, (string)$row['fotos_json']) . "'" : 'NULL';
    $co = mysqli_real_escape_string($conn, (string)$row['consorcio']);
    $poSql = isset($row['porcentaje']) && $row['porcentaje'] !== null && $row['porcentaje'] !== ''
        ? sprintf('%.3f', (float)$row['porcentaje']) : 'NULL';
    $alqSql = $row['alquiler'] !== null && $row['alquiler'] !== '' ? (string)(int)$row['alquiler'] : 'NULL';
    $padIns = mysqli_real_escape_string($conn, $pad);

    $fields = [];
    $vals = [];
    $put = function (string $col, string $sqlExpr) use (&$fields, &$vals, $propColsLocal) {
        if (!empty($propColsLocal[$col])) {
            $fields[] = '`' . str_replace('`', '', $col) . '`';
            $vals[] = $sqlExpr;
        }
    };
    $put('acceso_creador_id', (string)$ac);
    $put('propiedad', "'{$pr}'");
    $put('ciudad', "'{$ci}'");
    $put('padron', "'{$padIns}'");
    $put('detalle', "'{$det}'");
    $put('consorcio', "'{$co}'");
    $put('porcentaje', $poSql);
    $put('propietario_id', (string)(int)$newProp);
    $put('alquiler', $alqSql);
    if (!empty($propColsLocal['ubicacion_mapa'])) {
        $put('ubicacion_mapa', $umSql);
    } elseif (!empty($propColsLocal['mapa_enlace'])) {
        $put('mapa_enlace', $umSql);
    }
    if (!empty($propColsLocal['fotos_json'])) {
        $put('fotos_json', $fjSql);
    }
    if (!empty($propColsLocal['mapa_lat'])) {
        $put('mapa_lat', 'NULL');
    }
    if (!empty($propColsLocal['mapa_lng'])) {
        $put('mapa_lng', 'NULL');
    }

    if ($fields === []) {
        fwrite(STDERR, "Propiedad padron={$pad}: tabla propiedades sin columnas reconocidas.\n");
        continue;
    }
    $sqlP = 'INSERT INTO propiedades (' . implode(', ', $fields) . ') VALUES (' . implode(', ', $vals) . ')';
    if (!mysqli_query($conn, $sqlP)) {
        fwrite(STDERR, "Propiedad padron={$pad}: " . mysqli_error($conn) . "\n");
        continue;
    }
    $propMap[$oldPid] = (int)mysqli_insert_id($conn);
}
echo 'Propiedades importadas: ' . count($propMap) . ($skippedPadron ? " (omitidas por padrón duplicado: {$skippedPadron})" : '') . "\n";

$alqN = 0;
$resA = mysqli_query($conn, "SELECT * FROM `{$tmpDbEsc}`.alquileres ORDER BY alquiler_id ASC");
while ($resA && ($row = mysqli_fetch_assoc($resA))) {
    $pid = (int)$row['propiedad_id'];
    if (!isset($propMap[$pid])) {
        continue;
    }
    $newPid = $propMap[$pid];
    $i1 = (int)$row['inquilino1_id'];
    $i2 = $row['inquilino2_id'] !== null && $row['inquilino2_id'] !== '' ? (int)$row['inquilino2_id'] : null;
    $c1 = (int)$row['codeudor1_id'];
    $c2 = $row['codeudor2_id'] !== null && $row['codeudor2_id'] !== '' ? (int)$row['codeudor2_id'] : null;
    foreach ([$i1, $c1] as $req) {
        if (!isset($userMap[$req])) {
            continue 2;
        }
    }
    if ($i2 !== null && !isset($userMap[$i2])) {
        continue;
    }
    if ($c2 !== null && !isset($userMap[$c2])) {
        continue;
    }
    $ni1 = $userMap[$i1];
    $nc1 = $userMap[$c1];
    $ni2 = $i2 !== null ? $userMap[$i2] : null;
    $nc2 = $c2 !== null ? $userMap[$c2] : null;

    $plazo = $row['plazo_meses'];
    $plazoSql = ($plazo === null || $plazo === '') ? 'NULL' : (string)(int)$plazo;
    $inc = (int)$row['incremento_alquiler_meses'];
    $dest = mysqli_real_escape_string($conn, (string)$row['destino']);
    $mod = mysqli_real_escape_string($conn, (string)$row['modelo_contrato']);
    $fi = $row['fecha_inicio'] ? "'" . mysqli_real_escape_string($conn, (string)$row['fecha_inicio']) . "'" : 'NULL';
    $ff = $row['fecha_fin'] ? "'" . mysqli_real_escape_string($conn, (string)$row['fecha_fin']) . "'" : 'NULL';
    $pc = isset($row['precio_convenido']) && $row['precio_convenido'] !== null && $row['precio_convenido'] !== ''
        ? sprintf('%.2f', (float)$row['precio_convenido']) : 'NULL';
    $ffir = $row['fecha_firma'] ? "'" . mysqli_real_escape_string($conn, (string)$row['fecha_firma']) . "'" : 'NULL';
    $md = isset($row['monto_deposito']) && $row['monto_deposito'] !== null && $row['monto_deposito'] !== ''
        ? sprintf('%.2f', (float)$row['monto_deposito']) : 'NULL';
    $est = mysqli_real_escape_string($conn, (string)$row['estado']);
    $fc = mysqli_real_escape_string($conn, (string)$row['fecha_creacion']);

    $vInq2 = $ni2 === null ? 'NULL' : (string)(int)$ni2;
    $vCd2 = $nc2 === null ? 'NULL' : (string)(int)$nc2;

    $alqData = [
        'propiedad_id' => (string)(int)$newPid,
        'inquilino1_id' => (string)(int)$ni1,
        'inquilino2_id' => $vInq2,
        'codeudor1_id' => (string)(int)$nc1,
        'codeudor2_id' => $vCd2,
        'plazo_meses' => $plazoSql,
        'incremento_alquiler_meses' => (string)$inc,
        'destino' => "'{$dest}'",
        'modelo_contrato' => "'{$mod}'",
        'fecha_inicio' => $fi,
        'fecha_fin' => $ff,
        'precio_convenido' => $pc,
        'fecha_firma' => $ffir,
        'monto_deposito' => $md,
        'estado' => "'{$est}'",
        'fecha_creacion' => "'{$fc}'",
    ];
    $af = [];
    $av = [];
    foreach ($alqData as $col => $expr) {
        if (!empty($alqColsLocal[$col])) {
            $af[] = '`' . str_replace('`', '', $col) . '`';
            $av[] = $expr;
        }
    }
    if ($af === []) {
        continue;
    }
    $sqlA = 'INSERT INTO alquileres (' . implode(', ', $af) . ') VALUES (' . implode(', ', $av) . ')';
    if (!mysqli_query($conn, $sqlA)) {
        fwrite(STDERR, 'Alquiler: ' . mysqli_error($conn) . "\n");
        continue;
    }
    $alqN++;
}
echo "Alquileres importados: {$alqN}\n";

$cueN = 0;
$resC = mysqli_query($conn, "SELECT * FROM `{$tmpDbEsc}`.cuentas ORDER BY movimiento_id ASC");
while ($resC && ($row = mysqli_fetch_assoc($resC))) {
    $uid = (int)$row['usuario_id'];
    if (!isset($userMap[$uid])) {
        continue;
    }
    $nuid = $userMap[$uid];
    $fe = mysqli_real_escape_string($conn, (string)$row['fecha']);
    $co = mysqli_real_escape_string($conn, (string)$row['concepto']);
    $comp = mysqli_real_escape_string($conn, (string)($row['comprobante'] ?? ''));
    $ref = mysqli_real_escape_string($conn, (string)($row['referencia'] ?? ''));
    $mo = sprintf('%.2f', (float)$row['monto']);
    $sa = sprintf('%.2f', (float)$row['saldo']);
    $ar = $row['arriendo_id'];
    $arSql = ($ar === null || $ar === '') ? 'NULL' : (string)(int)$ar;
    $arf = $row['arriendo_fecha'];
    $arfSql = ($arf === null || $arf === '') ? 'NULL' : (string)(int)$arf;
    $dup = mysqli_query($conn, "SELECT 1 FROM cuentas WHERE usuario_id = {$nuid} AND fecha = '{$fe}' AND concepto = '{$co}' AND monto = {$mo} AND comprobante = '{$comp}' AND referencia = '{$ref}' LIMIT 1");
    if ($dup && mysqli_num_rows($dup) > 0) {
        continue;
    }
    $sqlC = "INSERT INTO cuentas (usuario_id, fecha, concepto, comprobante, referencia, monto, arriendo_id, arriendo_fecha, saldo) VALUES (
        {$nuid}, '{$fe}', '{$co}', '{$comp}', '{$ref}', {$mo}, {$arSql}, {$arfSql}, {$sa})";
    if (!mysqli_query($conn, $sqlC)) {
        fwrite(STDERR, 'Cuenta: ' . mysqli_error($conn) . "\n");
        continue;
    }
    $cueN++;
}
echo "Movimientos (cuentas) importados: {$cueN}\n";

$idxN = 0;
$resI = mysqli_query($conn, "SELECT fecha, valor, tipo, fecha_registro FROM `{$tmpDbEsc}`.indices WHERE acceso_creador_id = {$dumpSofiaId} ORDER BY id ASC");
while ($resI && ($row = mysqli_fetch_assoc($resI))) {
    $fe = $row['fecha'];
    $va = $row['valor'];
    $ti = $row['tipo'];
    $fr = $row['fecha_registro'];
    $ac = $tagAmbito;
    $stmt = mysqli_prepare($conn, 'INSERT INTO indices (acceso_creador_id, fecha, valor, tipo, fecha_registro) VALUES (?, ?, ?, ?, ?)');
    mysqli_stmt_bind_param($stmt, 'isdss', $ac, $fe, $va, $ti, $fr);
    try {
        mysqli_stmt_execute($stmt);
        $idxN++;
    } catch (mysqli_sql_exception $e) {
        if (stripos($e->getMessage(), 'Duplicate') === false) {
            throw $e;
        }
    }
    mysqli_stmt_close($stmt);
}
echo "Índices importados (nuevos): {$idxN}\n";

$cfgN = 0;
$resCfg = mysqli_query($conn, "SELECT clave, valor FROM `{$tmpDbEsc}`.config");
while ($resCfg && ($row = mysqli_fetch_assoc($resCfg))) {
    $k = $row['clave'];
    $kEsc = mysqli_real_escape_string($conn, $k);
    $chk = mysqli_query($conn, "SELECT 1 FROM config WHERE clave = '{$kEsc}' LIMIT 1");
    if ($chk && mysqli_num_rows($chk) > 0) {
        continue;
    }
    $stmt = mysqli_prepare($conn, 'INSERT INTO config (clave, valor) VALUES (?, ?)');
    $v = $row['valor'];
    mysqli_stmt_bind_param($stmt, 'ss', $k, $v);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    $cfgN++;
}
echo "Claves config añadidas (solo si no existían): {$cfgN}\n";

@mysqli_query($conn, 'DROP DATABASE IF EXISTS `' . $tmpDbEsc . '`');
echo "Base temporal eliminada. Listo.\n";
