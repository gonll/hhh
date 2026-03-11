<?php
include 'db.php';
include 'verificar_sesion.php';

if (!isset($_GET['id'], $_GET['limit'])) {
    die('Faltan parámetros (id, limit).');
}

$id = (int)$_GET['id'];
$limit = (int)$_GET['limit'];
if ($limit < 1) $limit = 20;
if ($limit > 500) $limit = 500;

$res_u = mysqli_query($conexion, "SELECT apellido FROM usuarios WHERE id = $id LIMIT 1");
$nombre = ($r = mysqli_fetch_assoc($res_u)) ? trim($r['apellido']) : 'Usuario #' . $id;

$res_total = mysqli_query($conexion, "SELECT COALESCE(SUM(monto), 0) AS total FROM cuentas WHERE usuario_id = $id");
$total_cuenta = ($r = mysqli_fetch_assoc($res_total)) ? (float)$r['total'] : 0;

$sql = "SELECT * FROM (SELECT * FROM cuentas WHERE usuario_id = $id ORDER BY fecha DESC, movimiento_id DESC LIMIT $limit) AS sub ORDER BY fecha ASC, movimiento_id ASC";
$res = mysqli_query($conexion, $sql);

$filas = [];
while ($m = mysqli_fetch_array($res)) {
    $filas[] = $m;
}

$suma_antes = 0;
if (count($filas) > 0) {
    $primera = $filas[0];
    $pf_esc = mysqli_real_escape_string($conexion, $primera['fecha']);
    $pid = (int)$primera['movimiento_id'];
    $monto_primera = (float)($primera['monto'] ?? 0);
    $r_sum = mysqli_query($conexion, "SELECT COALESCE(SUM(monto), 0) AS s FROM cuentas WHERE usuario_id = $id AND (fecha > '$pf_esc' OR (fecha = '$pf_esc' AND movimiento_id > $pid))");
    $suma_despues = ($r_sum && $row = mysqli_fetch_assoc($r_sum)) ? (float)$row['s'] : 0;
    $suma_antes = $total_cuenta - $suma_despues - $monto_primera;
}

$saldo = $suma_antes;
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Movimientos - <?= htmlspecialchars($nombre) ?></title>
    <style>
        @page { size: A4; margin: 12mm; }
        body { font-family: 'Segoe UI', sans-serif; padding: 15px; font-size: 10px; margin: 0; }
        h1 { font-size: 13px; margin: 0 0 6px; color: #333; }
        .subtitulo { font-size: 9px; color: #666; margin-bottom: 8px; }
        .contenido-impresion { page-break-inside: avoid; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #333; padding: 3px 5px; text-align: left; font-size: 10px; }
        th { background: #007bff; color: white; font-weight: bold; }
        .al-cen { text-align: center; }
        .al-der { text-align: right; }
        .monto-pos { color: #28a745; }
        .monto-neg { color: #dc3545; }
        .no-print { margin-top: 15px; }
        @media print {
            .no-print { display: none !important; }
            body { padding: 0; }
            .contenido-impresion { page-break-after: avoid; }
        }
    </style>
</head>
<body>
<div class="contenido-impresion">
<h1>DETALLE DE CUENTA - <?= htmlspecialchars(strtoupper($nombre)) ?></h1>
<p class="subtitulo">Últimos <?= $limit ?> movimientos — Impreso: <?= date('d/m/Y H:i') ?></p>
<table>
    <thead>
        <tr>
            <th style="width:12%;" class="al-cen">FECHA</th>
            <th style="width:38%;">CONCEPTO</th>
            <th style="width:14%;" class="al-cen">COMPROB</th>
            <th style="width:12%;" class="al-cen">REF</th>
            <th style="width:12%;" class="al-der">MONTO</th>
            <th style="width:12%;" class="al-der">SALDO</th>
        </tr>
    </thead>
    <tbody>
<?php
foreach ($filas as $m) {
    $saldo += $m['monto'];
    $claseMonto = ($m['monto'] >= 0) ? 'monto-pos' : 'monto-neg';
    $fechaFormateada = date('d/m/Y', strtotime($m['fecha']));
    echo '<tr>';
    echo '<td class="al-cen">' . htmlspecialchars($fechaFormateada) . '</td>';
    echo '<td>' . htmlspecialchars(strtoupper($m['concepto'])) . '</td>';
    echo '<td class="al-cen">' . htmlspecialchars(strtoupper($m['comprobante'] ?? '')) . '</td>';
    echo '<td class="al-cen">' . htmlspecialchars(strtoupper($m['referencia'] ?? '')) . '</td>';
    echo '<td class="al-der ' . $claseMonto . '">$ ' . number_format($m['monto'], 2, ',', '.') . '</td>';
    echo '<td class="al-der">$ ' . number_format($saldo, 2, ',', '.') . '</td>';
    echo '</tr>';
}
?>
    </tbody>
</table>
<?php if (count($filas) === 0): ?>
<p style="padding:20px; text-align:center; color:gray;">No hay movimientos.</p>
<?php endif; ?>
</div>
<div class="no-print">
    <button onclick="window.print()" style="background:#007bff; color:white; border:none; padding:8px 16px; border-radius:4px; cursor:pointer; font-weight:bold;">Imprimir</button>
    <a href="descargar_movimientos_excel.php?id=<?= (int)$id ?>" style="background:#28a745; color:white; border:none; padding:8px 16px; border-radius:4px; cursor:pointer; font-weight:bold; text-decoration:none; margin-left:8px; display:inline-block;">Descargar Excel</a>
    <button onclick="window.close()" style="background:#6c757d; color:white; border:none; padding:8px 16px; border-radius:4px; cursor:pointer; margin-left:8px;">Cerrar</button>
</div>
</body>
</html>
