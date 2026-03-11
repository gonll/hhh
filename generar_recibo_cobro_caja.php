<?php
/**
 * Recibo combinado para cobro en caja (inquilino).
 * Un solo recibo por el monto total, detallando las asignaciones.
 * Parámetros GET: usuario_id, fecha (YYYY-MM-DD), items (JSON array [{concepto, monto}]), total
 */
include 'db.php';
include 'verificar_sesion.php';
include 'helpers_contrato.php';

if (!isset($_GET['usuario_id']) || !isset($_GET['fecha']) || !isset($_GET['items']) || !isset($_GET['total'])) {
    die("Faltan parámetros.");
}

$usuario_id = (int)$_GET['usuario_id'];
$fecha_raw = trim($_GET['fecha'] ?? '');
$items_raw = $_GET['items'] ?? '';
$total = (float)($_GET['total'] ?? 0);
$periodo_cobrado = trim($_GET['periodo'] ?? '');
$pago_saldo = isset($_GET['pago_saldo']) ? (float)$_GET['pago_saldo'] : 0;
$dinero_efvo = isset($_GET['dinero_efvo']) ? (float)$_GET['dinero_efvo'] : 0;

if ($usuario_id < 1 || $total <= 0) {
    die("Datos inválidos.");
}

$items = json_decode($items_raw, true);
if (!is_array($items) || count($items) === 0) {
    die("Items inválidos.");
}

$res_usuario = mysqli_query($conexion, "SELECT apellido FROM usuarios WHERE id = $usuario_id LIMIT 1");
$row_usuario = mysqli_fetch_assoc($res_usuario);
$usuario_nombre = $row_usuario ? strtoupper(trim($row_usuario['apellido'])) : '';

$propietario_nombre = '';
$sql_prop = "SELECT u_prop.apellido AS propietario_nombre
             FROM alquileres a
             INNER JOIN propiedades p ON p.propiedad_id = a.propiedad_id
             INNER JOIN usuarios u_prop ON u_prop.id = p.propietario_id
             WHERE a.estado = 'VIGENTE' 
             AND (a.inquilino1_id = $usuario_id OR a.inquilino2_id = $usuario_id)
             LIMIT 1";
$res_prop = mysqli_query($conexion, $sql_prop);
if ($res_prop && mysqli_num_rows($res_prop) > 0) {
    $row_prop = mysqli_fetch_assoc($res_prop);
    $propietario_nombre = strtoupper(trim($row_prop['propietario_nombre']));
} else {
    $propietario_nombre = 'HERRERA Y LLOBETA S. R. L.';
}
if (empty($propietario_nombre)) {
    $propietario_nombre = 'HERRERA Y LLOBETA S. R. L.';
}

$partes = preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $fecha_raw, $m) ? $m : null;
if ($partes) {
    $ts = strtotime($fecha_raw);
} else {
    $ts = time();
}
$dia = (int)date('j', $ts);
$mes = mesNombre(date('n', $ts));
$anio = date('Y', $ts);
$fecha_formateada = "San Miguel de Tucumán, a los $dia de $mes de $anio";

$monto_letras = numerosALetras(round($total));
$monto_numero = number_format($total, 2, ',', '.');

$nro_recibo = 'CAJA-' . $usuario_id . '-' . date('YmdHis');

function conceptoReciboLimpio($c) {
    $c = trim($c);
    if (stripos($c, 'COBRO DE:') === 0) return trim(substr($c, 10));
    if (stripos($c, '(A ENTREGAR VUELTO)') !== false) return 'A entregar (vuelto)';
    return $c;
}

$detalle_html = '';
foreach ($items as $it) {
    $concepto = trim($it['concepto'] ?? '');
    $monto_item = (float)($it['monto'] ?? 0);
    $periodo_it = trim($it['periodo'] ?? '');
    if ($concepto === '' || $monto_item <= 0) continue;
    $concepto_limpio = htmlspecialchars(conceptoReciboLimpio($concepto));
    if ($periodo_it !== '') $concepto_limpio .= ' — Período: ' . htmlspecialchars($periodo_it);
    $monto_fmt = number_format($monto_item, 2, ',', '.');
    $detalle_html .= "<tr><td>$concepto_limpio</td><td>\$ $monto_fmt</td></tr>";
}

header("Content-Type: application/vnd.ms-word; charset=UTF-8");
header("Content-Disposition: attachment; filename=\"Recibo_Cobro_$nro_recibo.doc\"");
?>
<!DOCTYPE html>
<html xmlns="http://www.w3.org/TR/REC-html40">
<head>
<meta charset="UTF-8">
<style>
    @page { size: 210mm 148.5mm; margin: 6mm; }
    body { font-family: Arial, sans-serif; font-size: 9pt; margin: 0; padding: 5mm; line-height: 1.2; box-sizing: border-box; }
    .recibo-wrap { width: 100%; }
    .titulo { text-align: center; font-size: 11pt; font-weight: bold; letter-spacing: 1px; margin: 0 0 3px 0; }
    .fecha { margin: 0 0 4px 0; text-align: right; font-size: 8pt; }
    .texto { margin: 0 0 5px 0; text-align: justify; font-size: 9pt; }
    .detalle { margin: 4px 0; border-collapse: collapse; width: 100%; font-size: 8pt; }
    .detalle th { background: #f0f0f0; padding: 3px 6px; text-align: left; font-weight: bold; border: 1px solid #ccc; }
    .detalle th:last-child { text-align: right; }
    .detalle td { padding: 2px 6px; border: 1px solid #ddd; }
    .detalle td:last-child { text-align: right; }
    .detalle .fila-total { font-weight: bold; background: #e8f4e8; padding: 3px 6px; }
    .son { margin: 4px 0; font-weight: bold; font-size: 10pt; }
    .firma-container { margin-top: 6px; font-size: 8pt; }
</style>
</head>
<body>
<div class="recibo-wrap">
    <div class="titulo">R&nbsp;E&nbsp;C&nbsp;I&nbsp;B&nbsp;O&nbsp;&nbsp;&nbsp;Nº&nbsp;<?= htmlspecialchars($nro_recibo) ?></div>
    <div class="fecha"><?= $fecha_formateada ?></div>
    <div class="texto">Recibí de <strong><?= htmlspecialchars($usuario_nombre) ?></strong> la suma de pesos <strong><?= $monto_letras ?></strong> ($<?= $monto_numero ?>-) en concepto de las siguientes asignaciones<?= $periodo_cobrado !== '' ? ' — Período cobrado: ' . htmlspecialchars($periodo_cobrado) : '' ?>:</div>
    <table class="detalle">
        <thead>
            <tr>
                <th>Concepto</th>
                <th>Monto</th>
            </tr>
        </thead>
        <tbody>
            <?= $detalle_html ?>
            <tr class="fila-total">
                <td>TOTAL PAGADO</td>
                <td>$ <?= $monto_numero ?></td>
            </tr>
        </tbody>
    </table>
    <?php if ($pago_saldo > 0 && $dinero_efvo > 0): ?>
    <div style="margin-top:4px; font-size:8pt; color:#555;">Pagado con: Efectivo $ <?= number_format($dinero_efvo, 2, ',', '.') ?> + Pago saldo anterior $ <?= number_format($pago_saldo, 2, ',', '.') ?></div>
    <?php endif; ?>
    <div class="son">Son: $<?= $monto_numero ?>-</div>
    <div class="firma-container">
        <table style="width: 100%; border: 0;">
            <tr>
                <td style="width: 70%; border: 0;">&nbsp;</td>
                <td style="text-align: center; border: 0;">..............................................</td>
            </tr>
            <tr>
                <td style="width: 70%; border: 0;">&nbsp;</td>
                <td style="text-align: center; border: 0; padding-top: 2px;"><?= htmlspecialchars($propietario_nombre) ?></td>
            </tr>
        </table>
    </div>
</div>
</body>
</html>
