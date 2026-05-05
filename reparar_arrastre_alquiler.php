<?php
/**
 * Reparación one-off: corrige ALQUILER del período cuyo monto quedó en valor viejo
 * cuando debía arrastrar el último valor vigente.
 *
 * Uso:
 * - Abrir: reparar_arrastre_alquiler.php
 * - Opcional: ?ref=05/2026
 * - Modo global (sin filtro de ámbito): ?ref=05/2026&modo=global
 */
include __DIR__ . '/verificar_sesion.php';

if (!isset($_SESSION['acceso_nivel']) || (int) $_SESSION['acceso_nivel'] < 3) {
    die('Se requiere acceso de nivel 3 o superior.');
}

require_once __DIR__ . '/helpers_tenant_inmobiliaria.php';
tenant_inmob_asegurar_esquema($conexion);

function ra_parse_propiedad_desde_concepto($concepto) {
    $c = trim((string) $concepto);
    if (stripos($c, 'ALQUILER ACTUALIZADO - ') === 0) {
        $p = substr($c, strlen('ALQUILER ACTUALIZADO - '));
    } elseif (stripos($c, 'ALQUILER - ') === 0) {
        $p = substr($c, strlen('ALQUILER - '));
    } else {
        return '';
    }
    $partes = explode('|', $p, 2);
    return trim($partes[0]);
}

$ref = isset($_GET['ref']) ? trim((string) $_GET['ref']) : date('m/Y');
if (!preg_match('/^\d{2}\/\d{4}$/', $ref)) {
    $ref = date('m/Y');
}
$modo = (isset($_GET['modo']) && $_GET['modo'] === 'global') ? 'global' : 'ambito';

$twU = tenant_inmob_sql_usuarios($conexion, 'u');
$ref_esc = mysqli_real_escape_string($conexion, $ref);

$from = "FROM cuentas c INNER JOIN usuarios u ON u.id = c.usuario_id
         WHERE ($twU) AND c.comprobante = 'ALQUILER' AND c.referencia = '$ref_esc'";
if ($modo === 'global') {
    $from = "FROM cuentas c WHERE c.comprobante = 'ALQUILER' AND c.referencia = '$ref_esc'";
}

$q = "SELECT c.movimiento_id, c.usuario_id, c.fecha, c.referencia, c.concepto, c.monto $from
      ORDER BY c.fecha ASC, c.movimiento_id ASC";
$res = mysqli_query($conexion, $q);

$errores = [];
$hecho = null;
$candidatos = [];

if (!$res) {
    $errores[] = 'Error consultando movimientos: ' . mysqli_error($conexion);
} else {
    while ($row = mysqli_fetch_assoc($res)) {
        $mov_id = (int) ($row['movimiento_id'] ?? 0);
        $uid = (int) ($row['usuario_id'] ?? 0);
        $fecha = (string) ($row['fecha'] ?? '');
        $concepto = (string) ($row['concepto'] ?? '');
        $monto_actual = abs((float) ($row['monto'] ?? 0));
        if ($uid <= 0 || $mov_id <= 0 || $fecha === '' || $monto_actual <= 0) {
            continue;
        }

        // Solo corrige los conceptos base "ALQUILER - ...".
        if (stripos($concepto, 'ALQUILER - ') !== 0) {
            continue;
        }

        $propiedad = ra_parse_propiedad_desde_concepto($concepto);
        if ($propiedad === '') {
            continue;
        }
        $prop_like = mysqli_real_escape_string($conexion, str_replace(['%', '_'], ['\\%', '\\_'], $propiedad));
        $fecha_esc = mysqli_real_escape_string($conexion, $fecha);

        // Busca último ALQUILER anterior del mismo inquilino y propiedad.
        $qPrev = "SELECT movimiento_id, fecha, concepto, ABS(monto) AS monto_prev
                  FROM cuentas
                  WHERE usuario_id = $uid
                    AND comprobante = 'ALQUILER'
                    AND movimiento_id <> $mov_id
                    AND fecha < '$fecha_esc'
                    AND concepto LIKE '%$prop_like%'
                  ORDER BY fecha DESC, movimiento_id DESC
                  LIMIT 1";
        $rPrev = mysqli_query($conexion, $qPrev);
        if (!$rPrev || mysqli_num_rows($rPrev) === 0) {
            continue;
        }
        $prev = mysqli_fetch_assoc($rPrev);
        $monto_prev = abs((float) ($prev['monto_prev'] ?? 0));
        if ($monto_prev <= 0) {
            continue;
        }

        // Solo candidato si quedó distinto al último monto vigente.
        if (abs($monto_actual - $monto_prev) <= 0.01) {
            continue;
        }

        $candidatos[] = [
            'movimiento_id' => $mov_id,
            'usuario_id' => $uid,
            'fecha' => $fecha,
            'concepto' => $concepto,
            'monto_actual' => $monto_actual,
            'monto_correcto' => $monto_prev,
            'origen_id' => (int) ($prev['movimiento_id'] ?? 0),
            'origen_fecha' => (string) ($prev['fecha'] ?? ''),
            'origen_concepto' => (string) ($prev['concepto'] ?? ''),
        ];
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirmar']) && $_POST['confirmar'] === '1') {
    if (!empty($_POST['csrf']) && !empty($_SESSION['reparar_arrastre_alquiler_csrf'])
        && hash_equals($_SESSION['reparar_arrastre_alquiler_csrf'], (string) $_POST['csrf'])) {
        mysqli_begin_transaction($conexion);
        $ok = true;
        $actualizados = 0;
        foreach ($candidatos as $c) {
            $id = (int) $c['movimiento_id'];
            $nuevo = -abs((float) $c['monto_correcto']);
            $sqlU = "UPDATE cuentas SET monto = $nuevo WHERE movimiento_id = $id LIMIT 1";
            if (!mysqli_query($conexion, $sqlU)) {
                $ok = false;
                $errores[] = 'Error actualizando movimiento ' . $id . ': ' . mysqli_error($conexion);
                break;
            }
            $actualizados += (int) mysqli_affected_rows($conexion);
        }
        if ($ok) {
            mysqli_commit($conexion);
            $hecho = "Se corrigieron $actualizados movimiento(s) de ALQUILER para referencia $ref.";
        } else {
            mysqli_rollback($conexion);
        }
    } else {
        $errores[] = 'Token de confirmación inválido. Vuelva a cargar la página.';
    }
}

$csrf = bin2hex(random_bytes(16));
$_SESSION['reparar_arrastre_alquiler_csrf'] = $csrf;
$n = count($candidatos);
$title = 'Reparar arrastre de alquiler';

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($title) ?></title>
    <style>
        body { font-family: system-ui, sans-serif; max-width: 1100px; margin: 1rem auto; padding: 0 12px; }
        table { border-collapse: collapse; width: 100%; font-size: 13px; }
        th, td { border: 1px solid #ccc; padding: 6px 8px; text-align: left; }
        th { background: #f0f0f0; }
        .ok { color: #0a0; }
        .err { color: #a00; }
        button { padding: 10px 16px; font-size: 14px; cursor: pointer; }
        .mono { font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; }
    </style>
</head>
<body>
<h1><?= htmlspecialchars($title) ?></h1>
<p>Referencia analizada: <strong class="mono"><?= htmlspecialchars($ref) ?></strong>.</p>
<?php if ($modo === 'global'): ?>
    <p class="ok"><strong>Modo global:</strong> sin filtro de ámbito inmobiliario.</p>
<?php else: ?>
    <p>Modo: solo su <strong>ámbito inmobiliario</strong>. Si falta algún caso, pruebe <a href="?ref=<?= urlencode($ref) ?>&modo=global">modo global</a>.</p>
<?php endif; ?>
<p>Criterio: movimientos <code>ALQUILER</code> del período con concepto base (<code>ALQUILER - ...</code>) cuyo monto difiere del último ALQUILER previo del mismo inquilino/propiedad.</p>

<?php if ($hecho): ?>
    <p class="ok"><strong><?= htmlspecialchars($hecho) ?></strong></p>
<?php endif; ?>
<?php foreach ($errores as $e): ?>
    <p class="err"><?= htmlspecialchars($e) ?></p>
<?php endforeach; ?>

<p>Movimientos candidatos a corregir: <strong><?= (int) $n ?></strong></p>

<?php if ($n > 0): ?>
<table>
    <thead>
    <tr>
        <th>movimiento_id</th>
        <th>usuario_id</th>
        <th>fecha</th>
        <th>concepto</th>
        <th>monto actual</th>
        <th>monto correcto</th>
        <th>origen (id/fecha)</th>
    </tr>
    </thead>
    <tbody>
    <?php foreach ($candidatos as $f): ?>
        <tr>
            <td><?= (int) $f['movimiento_id'] ?></td>
            <td><?= (int) $f['usuario_id'] ?></td>
            <td><?= htmlspecialchars($f['fecha']) ?></td>
            <td><?= htmlspecialchars($f['concepto']) ?></td>
            <td><?= htmlspecialchars(number_format((float) $f['monto_actual'], 2, ',', '.')) ?></td>
            <td><?= htmlspecialchars(number_format((float) $f['monto_correcto'], 2, ',', '.')) ?></td>
            <td>#<?= (int) $f['origen_id'] ?> / <?= htmlspecialchars($f['origen_fecha']) ?></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>

<form method="post" action="?ref=<?= urlencode($ref) ?><?= $modo === 'global' ? '&modo=global' : '' ?>" style="margin-top:1rem;" onsubmit="return confirm('¿Actualizar el monto de estos <?= (int) $n ?> movimientos? Esta acción no se puede deshacer.');">
    <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">
    <input type="hidden" name="confirmar" value="1">
    <button type="submit" style="background:#c00;color:#fff;border:0;">Corregir <?= (int) $n ?> movimientos</button>
</form>
<?php endif; ?>

<p style="margin-top:2rem;"><a href="index.php">Volver al inicio</a></p>
</body>
</html>
