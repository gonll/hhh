<?php
/**
 * Anula liquidaciones de expensas de un consorcio (por defecto Laprida 430).
 *
 * Modo A — rango de fechas (por defecto): fecha >= ayer y <= hoy en el servidor
 * (cuentas.fecha es DATE, sin hora).
 *
 * Modo B — desde movimiento ancla: borra liquidaciones cuyo LIQ EXPENSAS tiene
 * movimiento_id MAYOR al del movimiento ancla (p. ej. el COBRO EXPENSA inmediatamente
 * anterior a la liquidación en el libro).
 *
 * Uso:
 *   php anular_liq_expensas_hoy_consorcio.php --dry-run
 *   php anular_liq_expensas_hoy_consorcio.php --execute
 *   php anular_liq_expensas_hoy_consorcio.php --execute --solo-hoy
 *   php anular_liq_expensas_hoy_consorcio.php --dry-run --despues-de-movimiento=12345
 *   php anular_liq_expensas_hoy_consorcio.php --dry-run --auto-cobro-laprida-mar2026
 *     (localiza el COBRO 30/03/2026 EXP/EFVO 02/2026 DPTO 6° Laprida 430 y usa su movimiento_id como ancla)
 *
 * Opcional: --consorcio="Laprida 430"
 */

if (PHP_SAPI !== 'cli') {
    header('HTTP/1.0 403 Forbidden');
    echo 'Solo CLI';
    exit(1);
}

require __DIR__ . '/db.php';

$dry_run = in_array('--dry-run', $argv, true);
$execute = in_array('--execute', $argv, true);
$solo_hoy = in_array('--solo-hoy', $argv, true);
$consorcio_buscar = 'Laprida 430';
$anchor_m_id = null;
$auto_cobro_laprida_mar2026 = false;

foreach ($argv as $arg) {
    if (strpos($arg, '--consorcio=') === 0) {
        $consorcio_buscar = trim(substr($arg, strlen('--consorcio=')), " \t\n\r\0\x0B\"'");
    }
    if (preg_match('/^--despues-de-movimiento=(\d+)$/', $arg, $m)) {
        $anchor_m_id = (int)$m[1];
    }
    if ($arg === '--auto-cobro-laprida-mar2026') {
        $auto_cobro_laprida_mar2026 = true;
    }
}

if (!$dry_run && !$execute) {
    fwrite(STDERR, "Indique --dry-run (solo listar) o --execute (aplicar borrados).\n");
    fwrite(STDERR, "Por defecto: liquidaciones con fecha ayer u hoy. Opción: --solo-hoy\n");
    fwrite(STDERR, "O bien: --despues-de-movimiento=ID o --auto-cobro-laprida-mar2026\n");
    exit(1);
}

if ($dry_run && $execute) {
    fwrite(STDERR, "Use solo uno: --dry-run o --execute.\n");
    exit(1);
}

if ($anchor_m_id !== null && $anchor_m_id <= 0) {
    fwrite(STDERR, "--despues-de-movimiento requiere un id numérico > 0.\n");
    exit(1);
}

if ($auto_cobro_laprida_mar2026 && $anchor_m_id !== null) {
    fwrite(STDERR, "Use solo uno: --despues-de-movimiento=… o --auto-cobro-laprida-mar2026.\n");
    exit(1);
}

$hoy = date('Y-m-d');
$ayer = date('Y-m-d', strtotime('-1 day'));
$fecha_desde = $solo_hoy ? $hoy : $ayer;
$fecha_hasta = $hoy;
$fecha_desde_esc = mysqli_real_escape_string($conexion, $fecha_desde);
$fecha_hasta_esc = mysqli_real_escape_string($conexion, $fecha_hasta);

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

if ($auto_cobro_laprida_mar2026) {
    // COBRO indicado por el usuario: 30/03/2026, EXP/EFVO, 02/2026, DPTO 6° Laprida 430
    $sql_cobro = "SELECT movimiento_id, usuario_id, fecha, concepto, comprobante, referencia 
        FROM cuentas 
        WHERE fecha = '2026-03-30' 
        AND TRIM(referencia) = '02/2026' 
        AND UPPER(TRIM(comprobante)) = 'EXP/EFVO' 
        AND concepto LIKE '%COBRO EXPENSA%' 
        AND concepto LIKE '%LAPRIDA%' 
        AND (concepto LIKE '%DPTO 6%' OR concepto LIKE '%6°%' OR concepto LIKE '%6 PISO%') 
        ORDER BY movimiento_id ASC";
    $r_cobro = mysqli_query($conexion, $sql_cobro);
    if (!$r_cobro) {
        fwrite(STDERR, 'Error SQL cobro: ' . mysqli_error($conexion) . "\n");
        exit(1);
    }
    $cobros = [];
    while ($c = mysqli_fetch_assoc($r_cobro)) {
        $cobros[] = $c;
    }
    if (count($cobros) === 0) {
        fwrite(STDERR, "No se encontró el movimiento COBRO EXPENSA (30/03/2026, EXP/EFVO, 02/2026, Laprida DPTO 6°).\n");
        fwrite(STDERR, "Puede localizar movimiento_id en la base y usar: --despues-de-movimiento=ID\n");
        exit(1);
    }
    if (count($cobros) > 1) {
        fwrite(STDERR, "Hay varios COBRO que coinciden; elija movimiento_id y use --despues-de-movimiento=ID:\n");
        foreach ($cobros as $c) {
            fwrite(STDERR, "  movimiento_id={$c['movimiento_id']} usuario_id={$c['usuario_id']} " . substr($c['concepto'], 0, 80) . "…\n");
        }
        exit(1);
    }
    $anchor_m_id = (int)$cobros[0]['movimiento_id'];
    echo "Ancla COBRO: movimiento_id={$anchor_m_id} (siguiente LIQ EXPENSAS del consorcio con id mayor).\n\n";
}

$modo_anchor = ($anchor_m_id !== null && $anchor_m_id > 0);

if ($modo_anchor) {
    echo "Modo: liquidaciones con LIQ EXPENSAS donde movimiento_id > {$anchor_m_id} (cuenta del consorcio).\n\n";
} elseif ($solo_hoy) {
    echo "Rango: solo HOY ({$hoy}) según fecha del servidor.\n\n";
} else {
    echo "Rango: desde AYER ({$ayer}) hasta HOY ({$hoy}) inclusive; cuentas.fecha es DATE (sin hora).\n\n";
}

if ($modo_anchor) {
    $aid = (int)$anchor_m_id;
    $res_liq = mysqli_query($conexion, "SELECT movimiento_id, fecha, referencia, concepto, comprobante 
        FROM cuentas 
        WHERE usuario_id = $consorcio_id 
        AND UPPER(TRIM(comprobante)) = 'LIQ EXPENSAS' 
        AND movimiento_id > $aid
        ORDER BY movimiento_id ASC");
} else {
    $res_liq = mysqli_query($conexion, "SELECT movimiento_id, fecha, referencia, concepto, comprobante 
        FROM cuentas 
        WHERE usuario_id = $consorcio_id 
        AND UPPER(TRIM(comprobante)) = 'LIQ EXPENSAS' 
        AND fecha >= '$fecha_desde_esc' 
        AND fecha <= '$fecha_hasta_esc'
        ORDER BY fecha ASC, movimiento_id ASC");
}

if (!$res_liq) {
    fwrite(STDERR, 'Error SQL: ' . mysqli_error($conexion) . "\n");
    exit(1);
}

$liqs = [];
while ($l = mysqli_fetch_assoc($res_liq)) {
    $liqs[] = $l;
}

if (count($liqs) === 0) {
    if ($modo_anchor) {
        fwrite(STDERR, "No hay LIQ EXPENSAS en cuenta consorcio con movimiento_id > {$anchor_m_id}.\n");
    } else {
        fwrite(STDERR, "No hay LIQ EXPENSAS en el rango {$fecha_desde} … {$fecha_hasta} para este consorcio.\n");
    }
    exit(1);
}

// Pares (período, fecha de movimiento): mismo período puede liquidarse en días distintos
$pairs = [];
foreach ($liqs as $l) {
    $ref = trim((string)($l['referencia'] ?? ''));
    if ($ref === '') {
        continue;
    }
    if (!preg_match('/^\d{1,2}\/\d{4}$/', $ref)) {
        fwrite(STDERR, "Referencia inesperada en LIQ EXPENSAS: {$ref}\n");
        exit(1);
    }
    $partes_r = preg_split('/\//', $ref);
    $ref_norm = str_pad((string)(int)$partes_r[0], 2, '0', STR_PAD_LEFT) . '/' . (int)$partes_r[1];
    $fecha_dia = substr($l['fecha'], 0, 10);
    $key = $ref_norm . '|' . $fecha_dia;
    $pairs[$key] = ['referencia' => $ref_norm, 'fecha' => $fecha_dia];
}
$pairs = array_values($pairs);

echo "Lotes a anular (período + fecha de registro): " . count($pairs) . "\n";
foreach ($pairs as $p) {
    echo "  - {$p['referencia']} @ {$p['fecha']}\n";
}
echo "Se borran LIQ EXP / LIQ EXP EXT / Honorarios / LIQ EXPENSAS con esa misma fecha.\n\n";

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

foreach ($pairs as $p) {
    $ref_norm = $p['referencia'];
    $fecha_liq_esc = mysqli_real_escape_string($conexion, $p['fecha']);
    $ref_esc = mysqli_real_escape_string($conexion, $ref_norm);

    $partes = preg_split('/\//', $ref_norm);
    $mes = (int)$partes[0];
    $anio = (int)$partes[1];
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

    echo "Período {$ref_norm} @ {$p['fecha']}: LIQ EXP/LIQ EXP EXT={$n_li}, Honorarios ref {$ref_sig}={$n_hon}, LIQ EXPENSAS={$n_liq}\n";
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
    foreach ($pairs as $p) {
        $ref_norm = $p['referencia'];
        $fecha_liq_esc = mysqli_real_escape_string($conexion, $p['fecha']);
        $ref_esc = mysqli_real_escape_string($conexion, $ref_norm);

        $partes = preg_split('/\//', $ref_norm);
        $mes = (int)$partes[0];
        $anio = (int)$partes[1];
        $mes_sig = ($mes >= 12) ? 1 : $mes + 1;
        $anio_sig = ($mes >= 12) ? $anio + 1 : $anio;
        $ref_sig = str_pad((string)$mes_sig, 2, '0', STR_PAD_LEFT) . '/' . $anio_sig;
        $ref_sig_esc = mysqli_real_escape_string($conexion, $ref_sig);

        // 1) Cargos en propietarios/inquilinos: misma fecha que esa corrida de liquidación
        if ($ids_lista !== '') {
            $sql2 = "DELETE FROM cuentas 
                WHERE usuario_id IN ($ids_lista) 
                AND TRIM(referencia) = '$ref_esc' 
                AND (UPPER(TRIM(comprobante)) = 'LIQ EXP' OR UPPER(TRIM(comprobante)) = 'LIQ EXP EXT')
                AND DATE(fecha) = '$fecha_liq_esc'";
            if (!mysqli_query($conexion, $sql2)) {
                throw new Exception(mysqli_error($conexion));
            }
            echo "Período {$ref_norm} @ {$p['fecha']}: eliminados LIQ EXP / LIQ EXP EXT: " . mysqli_affected_rows($conexion) . "\n";
        }

        $sql_h = "DELETE FROM cuentas WHERE usuario_id = $consorcio_id 
            AND UPPER(TRIM(comprobante)) = 'HONORARIOS' 
            AND TRIM(referencia) = '$ref_sig_esc' 
            AND DATE(fecha) = '$fecha_liq_esc'";
        if (!mysqli_query($conexion, $sql_h)) {
            throw new Exception(mysqli_error($conexion));
        }
        echo "Período {$ref_norm} @ {$p['fecha']}: eliminados Honorarios (ref {$ref_sig}): " . mysqli_affected_rows($conexion) . "\n";

        $sql1 = "DELETE FROM cuentas WHERE usuario_id = $consorcio_id 
            AND TRIM(referencia) = '$ref_esc' 
            AND UPPER(TRIM(comprobante)) = 'LIQ EXPENSAS'
            AND DATE(fecha) = '$fecha_liq_esc'";
        if (!mysqli_query($conexion, $sql1)) {
            throw new Exception(mysqli_error($conexion));
        }
        $aff = mysqli_affected_rows($conexion);
        echo "Período {$ref_norm} @ {$p['fecha']}: eliminados LIQ EXPENSAS: {$aff}\n";

        if ($aff < 1) {
            throw new Exception("No se borró LIQ EXPENSAS para {$ref_norm} @ {$p['fecha']}; rollback.");
        }
    }

    mysqli_commit($conexion);
    $resumen = array_map(function ($x) {
        return $x['referencia'] . ' @ ' . $x['fecha'];
    }, $pairs);
    echo "\nListo: anulado(s) " . count($pairs) . " lote(s): " . implode('; ', $resumen) . ".\n";
} catch (Exception $e) {
    mysqli_rollback($conexion);
    fwrite(STDERR, "Error: " . $e->getMessage() . "\n");
    exit(1);
}

exit(0);
