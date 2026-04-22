<?php
/**
 * Reparación one-off: duplicados de ALQUILER 04/2026 a 490.000 (Depto 1 Piso C EE UU 101) por bug de liquidación pre-inicio de contrato.
 * Acceso: sesión con nivel >= 3. No requiere sync_git ni UUID.
 * Abrir en el navegador: reparar_dup_alquiler_abril2026.php
 */
include __DIR__ . '/verificar_sesion.php';

if (!isset($_SESSION['acceso_nivel']) || (int) $_SESSION['acceso_nivel'] < 3) {
    die('Se requiere acceso de nivel 3 o superior.');
}

require_once __DIR__ . '/helpers_tenant_inmobiliaria.php';
tenant_inmob_asegurar_esquema($conexion);

$ref = '04/2026';
$monto_objetivo = 490000.0;
$errores = [];
$hecho = null;
$modo = isset($_GET['modo']) && $_GET['modo'] === 'global' ? 'global' : 'ambito';

$twU = tenant_inmob_sql_usuarios($conexion, 'u');

$ref_esc = mysqli_real_escape_string($conexion, $ref);

// Sin JOIN por ámbito: solo monto, ref y concepto (duplicado del bug; mismo depto y mes)
$sql_where_solo_cuentas = "c.comprobante = 'ALQUILER'
      AND c.referencia = '$ref_esc'
      AND ABS(ABS(c.monto) - $monto_objetivo) < 0.01
      AND c.concepto LIKE '%PISO C%'
      AND (c.concepto LIKE '%EE UU 101%' OR c.concepto LIKE '%ee uu 101%')";

// Con filtro inmobiliario (si no ves filas, probá /reparar_dup_alquiler_abril2026.php?modo=global)
$sql_base = "FROM cuentas c
    INNER JOIN usuarios u ON u.id = c.usuario_id
    WHERE ($twU) AND " . $sql_where_solo_cuentas;
$sql_base_global = "FROM cuentas c WHERE " . $sql_where_solo_cuentas;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirmar']) && $_POST['confirmar'] === '1') {
    if (!empty($_POST['csrf']) && !empty($_SESSION['reparar_dup_csrf']) && hash_equals($_SESSION['reparar_dup_csrf'], (string) $_POST['csrf'])) {
        $es_global = !empty($_POST['global']) && $_POST['global'] === '1';
        $from = $es_global ? $sql_base_global : $sql_base;
        mysqli_query($conexion, "DELETE c $from");
        $n = (int) mysqli_affected_rows($conexion);
        if (mysqli_errno($conexion)) {
            $errores[] = mysqli_error($conexion);
        } else {
            $hecho = "Se eliminaron $n movimiento(s) (" . ($es_global ? 'criterio global' : 'solo ámbito de la sesión') . ").";
        }
    } else {
        $errores[] = 'Token de confirmación inválido. Vuelva a cargar la página.';
    }
}

$csrf = bin2hex(random_bytes(16));
$_SESSION['reparar_dup_csrf'] = $csrf;

$fromList = $modo === 'global' ? $sql_base_global : $sql_base;
$qList = 'SELECT c.movimiento_id, c.usuario_id, c.fecha, c.monto, c.concepto ' . $fromList
    . ' ORDER BY c.movimiento_id ASC';
$res = mysqli_query($conexion, $qList);
$filas = [];
if ($res) {
    while ($row = mysqli_fetch_assoc($res)) {
        $idU = (int) ($row['usuario_id'] ?? 0);
        $ap = '';
        if ($idU > 0) {
            $rAp = mysqli_query($conexion, 'SELECT apellido FROM usuarios WHERE id = ' . $idU . ' LIMIT 1');
            if ($rAp && $ra = mysqli_fetch_assoc($rAp)) {
                $ap = (string) ($ra['apellido'] ?? '');
            }
        }
        $row['apellido'] = $ap;
        $filas[] = $row;
    }
} else {
    $errores[] = 'Consulta: ' . mysqli_error($conexion);
}

$n = count($filas);
$title = 'Reparar duplicados alquiler abril 2026 (490.000)';

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($title) ?></title>
    <style>
        body { font-family: system-ui, sans-serif; max-width: 900px; margin: 1rem auto; padding: 0 12px; }
        table { border-collapse: collapse; width: 100%; font-size: 13px; }
        th, td { border: 1px solid #ccc; padding: 6px 8px; text-align: left; }
        th { background: #f0f0f0; }
        .ok { color: #0a0; }
        .err { color: #a00; }
        button { padding: 10px 16px; font-size: 14px; cursor: pointer; }
    </style>
</head>
<body>
<h1><?= htmlspecialchars($title) ?></h1>
<?php if ($modo === 'global'): ?>
    <p class="ok"><strong>Modo global:</strong> se listan y borran filas <strong>sin</strong> filtrar por ámbito (Sofía / principal), solo por monto, referencia y concepto. Use esto si en modo normal no ve ninguna fila.</p>
<?php else: ?>
    <p>Modo: solo su <strong>ámbito inmobiliario</strong>. Si el listado da 0 filas y sabe que hay duplicados, abra
        <a href="?modo=global">reparar_dup_alquiler_abril2026.php?modo=global</a>.</p>
<?php endif; ?>
<p>Criterio: <code>ALQUILER</code>, referencia <code><?= htmlspecialchars($ref) ?></code>, monto absoluto 490.000, concepto con “PISO C” y “EE UU 101”.</p>
<?php if ($hecho): ?>
    <p class="ok"><strong><?= htmlspecialchars($hecho) ?></strong></p>
<?php endif; ?>
<?php foreach ($errores as $e): ?>
    <p class="err"><?= htmlspecialchars($e) ?></p>
<?php endforeach; ?>

<p>Filas encontradas: <strong><?= (int) $n ?></strong></p>

<?php if ($n > 0): ?>
<table>
    <thead>
    <tr>
        <th>movimiento_id</th>
        <th>usuario_id</th>
        <th>apellido</th>
        <th>fecha</th>
        <th>monto</th>
        <th>concepto (recorte)</th>
    </tr>
    </thead>
    <tbody>
    <?php foreach ($filas as $f): ?>
        <tr>
            <td><?= (int) $f['movimiento_id'] ?></td>
            <td><?= (int) $f['usuario_id'] ?></td>
            <td><?= htmlspecialchars($f['apellido'] ?? '') ?></td>
            <td><?= htmlspecialchars($f['fecha'] ?? '') ?></td>
            <td><?= htmlspecialchars($f['monto'] ?? '') ?></td>
            <td><?php
                $co = (string) ($f['concepto'] ?? '');
                $rec = function_exists('mb_substr') ? mb_substr($co, 0, 60) : substr($co, 0, 60);
                echo htmlspecialchars($rec) . (strlen($co) > 60 ? '…' : '');
                ?></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>

<form method="post" action="<?= $modo === 'global' ? '?modo=global' : '' ?>" style="margin-top:1rem;" onsubmit="return confirm('¿Eliminar TODAS estas <?= (int) $n ?> filas? <?= $modo === 'global' ? 'BORRADO GLOBAL. ' : '' ?>Esta acción no se puede deshacer.');">
    <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">
    <input type="hidden" name="confirmar" value="1">
    <input type="hidden" name="global" value="<?= $modo === 'global' ? '1' : '0' ?>">
    <button type="submit" style="background:#c00;color:#fff;border:0;">Eliminar duplicados (<?= (int) $n ?>)<?= $modo === 'global' ? ' — global' : '' ?></button>
</form>
<?php else: ?>
    <p>Si debería haber filas y aparece 0, puede que el inmueble no coincida con el texto, el monto no sea 490.000, o el inquilino esté en otro ámbito (iniciar sesión con el acceso del sistema que corresponda).</p>
<?php endif; ?>

<p style="margin-top:2rem;"><a href="index.php">Volver al inicio</a></p>
</body>
</html>
