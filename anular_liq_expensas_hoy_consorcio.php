<?php
/**
 * Anula la liquidación de expensas registrada HOY para un consorcio (por defecto Laprida 430).
 * Ejecutar en el servidor con la misma .env que la app.
 *
 * Uso (desde la carpeta del proyecto):
 *   php anular_liq_expensas_hoy_consorcio.php --dry-run
 *   php anular_liq_expensas_hoy_consorcio.php --execute
 *
 * Opcional: --consorcio="Laprida 430"  (coincide con campo usuarios.consorcio o apellido CONSORCIO…)
 */

if (PHP_SAPI !== 'cli') {
    header('HTTP/1.0 403 Forbidden');
    echo 'Solo CLI';
    exit(1);
}

require __DIR__ . '/db.php';

$dry_run = in_array('--dry-run', $argv, true);
$execute = in_array('--execute', $argv, true);
$consorcio_buscar = 'Laprida 430';

foreach ($argv as $arg) {
    if (strpos($arg, '--consorcio=') === 0) {
        $consorcio_buscar = trim(substr($arg, strlen('--consorcio=')), " \t\n\r\0\x0B\"'");
    }
}

if (!$dry_run && !$execute) {
    fwrite(STDERR, "Indique --dry-run (solo listar) o --execute (aplicar borrados).\n");
    exit(1);
}

if ($dry_run && $execute) {
    fwrite(STDERR, "Use solo uno: --dry-run o --execute.\n");
    exit(1);
}

$hoy = date('Y-m-d');
$bus_upper = strtoupper(trim($consorcio_buscar));

// Resolver consorcio: apellido CONSORCIO* y nombre en consorcio o apellido
$res_u = mysqli_query($conexion, "SELECT id, apellido, consorcio FROM usuarios 
    WHERE LOWER(apellido) LIKE 'consorcio%' 
    AND (
        UPPER(TRIM(COALESCE(consorcio,''))) = '" . mysqli_real_escape_string($conexion, $bus_upper) . "'
        OR REPLACE(UPPER(TRIM(COALESCE(consorcio,''))), ' ', '') = '" . mysqli_real_escape_string($conexion, str_replace(' ', '', $bus_upper)) . "'
        OR UPPER(TRIM(COALESCE(apellido,''))) LIKE '%" . mysqli_real_escape_string($conexion, $bus_upper) . "%'
    )
    LIMIT 5");

if (!$res_u) {
    fwrite(STDERR, 'Error SQL usuarios: ' . mysqli_error($conexion) . "\n");
    exit(1);
}

$rows_u = [];
while ($r = mysqli_fetch_assoc($res_u)) {
    $rows_u[] = $r;
}

if (count($rows_u) === 0) {
    fwrite(STDERR, "No se encontró consorcio para: {$consorcio_buscar}\n");
    exit(1);
}

if (count($rows_u) > 1) {
    fwrite(STDERR, "Varios usuarios coinciden; refine --consorcio o elija id en la base:\n");
    foreach ($rows_u as $r) {
        fwrite(STDERR, "  id={$r['id']} apellido={$r['apellido']} consorcio={$r['consorcio']}\n");
    }
    exit(1);
}

$row_u = $rows_u[0];
$consorcio_id = (int)$row_u['id'];
$nombre_consorcio = trim($row_u['consorcio'] ?? '');
$consorcio_esc = mysqli_real_escape_string($conexion, $nombre_consorcio);

echo "Consorcio: id={$consorcio_id} apellido={$row_u['apellido']} consorcio={$nombre_consorcio}\n";
echo "Fecha objetivo (liquidación): {$hoy}\n\n";

$res_liq = mysqli_query($conexion, "SELECT movimiento_id, fecha, referencia, concepto, comprobante 
    FROM cuentas 
    WHERE usuario_id = $consorcio_id 
    AND UPPER(TRIM(comprobante)) = 'LIQ EXPENSAS' 
    AND DATE(fecha) = '$hoy'
    ORDER BY movimiento_id DESC");

if (!$res_liq) {
    fwrite(STDERR, 'Error SQL: ' . mysqli_error($conexion) . "\n");
    exit(1);
}

$liqs = [];
while ($l = mysqli_fetch_assoc($res_liq)) {
    $liqs[] = $l;
}

if (count($liqs) === 0) {
    fwrite(STDERR, "No hay movimiento LIQ EXPENSAS con fecha {$hoy} para este consorcio.\n");
    exit(1);
}

// Agrupar por referencia (período): puede haber más de una liquidación el mismo día
$por_ref = [];
foreach ($liqs as $l) {
    $ref = trim((string)($l['referencia'] ?? ''));
    if ($ref === '') {
        continue;
    }
    if (!preg_match('/^\d{1,2}\/\d{4}$/', $ref)) {
        fwrite(STDERR, "Referencia inesperada en LIQ EXPENSAS: {$ref}\n");
        exit(1);
    }
    $por_ref[$ref] = true;
}
$referencias = array_keys($por_ref);
$fecha_liq = substr($liqs[0]['fecha'], 0, 10);
$fecha_liq_esc = mysqli_real_escape_string($conexion, $fecha_liq);

echo "Liquidaciones del día en cuenta consorcio: " . count($referencias) . " período(s): " . implode(', ', $referencias) . "\n";
echo "Se borran solo movimientos con DATE(fecha) = {$fecha_liq} (misma corrida que la liquidación web).\n\n";

// Propietarios + inquilinos del consorcio (mismo criterio que al liquidar / eliminar_liq_expensas_periodo.php)
$cond_consorcio = $nombre_consorcio === ''
    ? "AND (p.consorcio IS NULL OR TRIM(p.consorcio) = '')"
    : "AND UPPER(TRIM(p.consorcio)) = UPPER('$consorcio_esc')";

$ids_usuarios = [];
$res_prop = mysqli_query($conexion, "SELECT p.propiedad_id, p.propietario_id FROM propiedades p 
    WHERE p.porcentaje IS NOT NULL AND p.porcentaje > 0 $cond_consorcio");
if ($res_prop) {
    while ($p = mysqli_fetch_assoc($res_prop)) {
        $ids_usuarios[(int)$p['propietario_id']] = true;
        $propiedad_id = (int)$p['propiedad_id'];
        $res_inq = mysqli_query($conexion, "SELECT inquilino1_id FROM alquileres 
            WHERE propiedad_id = $propiedad_id AND estado = 'VIGENTE' LIMIT 1");
        if ($res_inq && $row_inq = mysqli_fetch_assoc($res_inq) && (int)$row_inq['inquilino1_id'] > 0) {
            $ids_usuarios[(int)$row_inq['inquilino1_id']] = true;
        }
    }
}

function contar_select($conexion, $sql) {
    $r = mysqli_query($conexion, $sql);
    if (!$r) {
        return -1;
    }
    $row = mysqli_fetch_row($r);
    return (int)($row[0] ?? 0);
}

$ids_lista = count($ids_usuarios) > 0 ? implode(',', array_map('intval', array_keys($ids_usuarios))) : '';

$total_n_li = 0;
$total_n_hon = 0;
$total_n_liq = 0;

foreach ($referencias as $referencia) {
    $partes = preg_split('/\//', $referencia);
    $mes = (int)$partes[0];
    $anio = (int)$partes[1];
    $ref_norm = str_pad((string)$mes, 2, '0', STR_PAD_LEFT) . '/' . $anio;
    $ref_esc = mysqli_real_escape_string($conexion, $ref_norm);

    $mes_sig = ($mes >= 12) ? 1 : $mes + 1;
    $anio_sig = ($mes >= 12) ? $anio + 1 : $anio;
    $ref_sig = str_pad((string)$mes_sig, 2, '0', STR_PAD_LEFT) . '/' . $anio_sig;
    $ref_sig_esc = mysqli_real_escape_string($conexion, $ref_sig);

    $n_li = 0;
    if ($ids_lista !== '') {
        $n_li = contar_select($conexion, "SELECT COUNT(*) FROM cuentas WHERE usuario_id IN ($ids_lista) 
            AND TRIM(referencia) = '$ref_esc' 
            AND (UPPER(TRIM(comprobante)) = 'LIQ EXP' OR UPPER(TRIM(comprobante)) = 'LIQ EXP EXT')
            AND DATE(fecha) = '$fecha_liq_esc'");
    }
    $n_hon = contar_select($conexion, "SELECT COUNT(*) FROM cuentas WHERE usuario_id = $consorcio_id 
        AND UPPER(TRIM(comprobante)) = 'HONORARIOS' 
        AND TRIM(referencia) = '$ref_sig_esc' 
        AND DATE(fecha) = '$fecha_liq_esc'");
    $n_liq = contar_select($conexion, "SELECT COUNT(*) FROM cuentas WHERE usuario_id = $consorcio_id 
        AND UPPER(TRIM(comprobante)) = 'LIQ EXPENSAS' 
        AND TRIM(referencia) = '$ref_esc' 
        AND DATE(fecha) = '$fecha_liq_esc'");

    echo "Período {$ref_norm}: LIQ EXP/LIQ EXP EXT (fecha hoy)={$n_li}, Honorarios ref {$ref_sig}={$n_hon}, LIQ EXPENSAS={$n_liq}\n";
    $total_n_li += $n_li;
    $total_n_hon += $n_hon;
    $total_n_liq += $n_liq;
}

echo "\nTotal estimado: LIQ EXP/LIQ EXP EXT={$total_n_li}, Honorarios={$total_n_hon}, LIQ EXPENSAS={$total_n_liq}\n";

if ($dry_run) {
    echo "\n[DRY-RUN] No se modificó la base. Ejecute con --execute para aplicar.\n";
    exit(0);
}

mysqli_begin_transaction($conexion);

try {
    foreach ($referencias as $referencia) {
        $partes = preg_split('/\//', $referencia);
        $mes = (int)$partes[0];
        $anio = (int)$partes[1];
        $ref_norm = str_pad((string)$mes, 2, '0', STR_PAD_LEFT) . '/' . $anio;
        $ref_esc = mysqli_real_escape_string($conexion, $ref_norm);

        $mes_sig = ($mes >= 12) ? 1 : $mes + 1;
        $anio_sig = ($mes >= 12) ? $anio + 1 : $anio;
        $ref_sig = str_pad((string)$mes_sig, 2, '0', STR_PAD_LEFT) . '/' . $anio_sig;
        $ref_sig_esc = mysqli_real_escape_string($conexion, $ref_sig);

        // 1) Cargos en propietarios/inquilinos: solo los insertados en la misma fecha (evita borrar liquidaciones viejas del mismo período)
        if ($ids_lista !== '') {
            $sql2 = "DELETE FROM cuentas 
                WHERE usuario_id IN ($ids_lista) 
                AND TRIM(referencia) = '$ref_esc' 
                AND (UPPER(TRIM(comprobante)) = 'LIQ EXP' OR UPPER(TRIM(comprobante)) = 'LIQ EXP EXT')
                AND DATE(fecha) = '$fecha_liq_esc'";
            if (!mysqli_query($conexion, $sql2)) {
                throw new Exception(mysqli_error($conexion));
            }
            echo "Período {$ref_norm}: eliminados LIQ EXP / LIQ EXP EXT: " . mysqli_affected_rows($conexion) . "\n";
        }

        // 2) Honorarios al consorcio (referencia = mes siguiente), misma fecha que la liquidación
        $sql_h = "DELETE FROM cuentas WHERE usuario_id = $consorcio_id 
            AND UPPER(TRIM(comprobante)) = 'HONORARIOS' 
            AND TRIM(referencia) = '$ref_sig_esc' 
            AND DATE(fecha) = '$fecha_liq_esc'";
        if (!mysqli_query($conexion, $sql_h)) {
            throw new Exception(mysqli_error($conexion));
        }
        echo "Período {$ref_norm}: eliminados Honorarios (ref {$ref_sig}): " . mysqli_affected_rows($conexion) . "\n";

        // 3) Movimiento LIQ EXPENSAS en cuenta del consorcio
        $sql1 = "DELETE FROM cuentas WHERE usuario_id = $consorcio_id 
            AND TRIM(referencia) = '$ref_esc' 
            AND UPPER(TRIM(comprobante)) = 'LIQ EXPENSAS'
            AND DATE(fecha) = '$fecha_liq_esc'";
        if (!mysqli_query($conexion, $sql1)) {
            throw new Exception(mysqli_error($conexion));
        }
        $aff = mysqli_affected_rows($conexion);
        echo "Período {$ref_norm}: eliminados LIQ EXPENSAS: {$aff}\n";

        if ($aff < 1) {
            throw new Exception("No se borró LIQ EXPENSAS para período {$ref_norm}; rollback.");
        }
    }

    mysqli_commit($conexion);
    echo "\nListo: anulada(s) la(s) liquidación(es) del {$fecha_liq} (" . implode(', ', $referencias) . ").\n";
} catch (Exception $e) {
    mysqli_rollback($conexion);
    fwrite(STDERR, "Error: " . $e->getMessage() . "\n");
    exit(1);
}

exit(0);
