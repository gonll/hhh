<?php
/**
 * PDF vectorial (TCPDF) — Informe movimientos de pago por operación.
 * Tabla con Cell (sin writeHTML: más estable en servidores compartidos).
 */
require_once __DIR__ . '/pdf_informe_common.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/verificar_sesion.php';

$operacion = isset($_GET['operacion']) ? (int) $_GET['operacion'] : 0;
if ($operacion < 1) {
    header('HTTP/1.1 400 Bad Request');
    header('Content-Type: text/plain; charset=utf-8');
    echo 'operación inválida';
    exit;
}

$ref_buscar_esc = mysqli_real_escape_string($conexion, "OP N° $operacion");

$sql = "SELECT c.*, u.apellido AS usuario_nombre 
        FROM cuentas c 
        LEFT JOIN usuarios u ON u.id = c.usuario_id 
        WHERE c.referencia = '$ref_buscar_esc' 
        ORDER BY c.fecha DESC, c.movimiento_id DESC";
$res = mysqli_query($conexion, $sql);
$res = pdf_informe_res_ok($conexion, $res, 'movimientos cuentas por operación');

$movimientos = [];
$saldo_total_operacion = 0;
while ($m = mysqli_fetch_assoc($res)) {
    $movimientos[] = $m;
    $saldo_total_operacion += (float) $m['monto'];
}

usort($movimientos, function ($a, $b) {
    $fechaA = strtotime($a['fecha']);
    $fechaB = strtotime($b['fecha']);
    if ($fechaA === $fechaB) {
        return (int) $a['movimiento_id'] - (int) $b['movimiento_id'];
    }
    return $fechaA - $fechaB;
});

function fmt_moneda_pdf($n) {
    $n = (float) $n;
    $signo = $n < 0 ? '-' : '';
    $abs = abs($n);
    return '$ ' . $signo . number_format($abs, 2, ',', '.');
}

function up_pdf($s) {
    return function_exists('mb_strtoupper') ? mb_strtoupper((string) $s, 'UTF-8') : strtoupper((string) $s);
}

$pdf = new TCPDF('L', 'mm', 'A4', true, 'UTF-8', false);
$pdf->SetCreator('Sistema');
$pdf->SetAuthor('Sistema');
$pdf->SetTitle('Informe — Movimientos de pago');
$pdf->setPrintHeader(false);
$pdf->setPrintFooter(false);
$pdf->SetMargins(12, 12, 12);
$pdf->SetAutoPageBreak(true, 14);
$pdf->AddPage();
$pdf->SetFont('dejavusans', 'B', 14);
$pdf->SetTextColor(26, 54, 93);
$pdf->Cell(0, 8, 'Informe', 0, 1, 'L');
$pdf->SetFont('dejavusans', 'B', 11);
$pdf->SetTextColor(0, 0, 0);
$pdf->Cell(0, 6, 'Movimientos de pago — Operación N° ' . $operacion, 0, 1, 'L');
$pdf->SetFont('dejavusans', '', 9);
$pdf->SetTextColor(74, 85, 104);
$pdf->Cell(0, 5, 'Generado: ' . date('d/m/Y H:i'), 0, 1, 'L');
$pdf->Ln(4);

$wF = 20;
$wC = 78;
$wCo = 24;
$wR = 24;
$wU = 40;
$wM = 28;
$wS = 28;

if (count($movimientos) === 0) {
    $pdf->SetFont('dejavusans', '', 10);
    $pdf->SetTextColor(100, 100, 100);
    $pdf->Cell(0, 8, 'No hay movimientos para esta operación.', 0, 1, 'L');
} else {
    $pdf->SetFont('dejavusans', 'B', 7);
    $pdf->SetFillColor(44, 82, 130);
    $pdf->SetTextColor(255, 255, 255);
    $pdf->Cell($wF, 7, 'Fecha', 1, 0, 'C', true);
    $pdf->Cell($wC, 7, 'Concepto', 1, 0, 'L', true);
    $pdf->Cell($wCo, 7, 'Comprob.', 1, 0, 'C', true);
    $pdf->Cell($wR, 7, 'Referencia', 1, 0, 'C', true);
    $pdf->Cell($wU, 7, 'Usuario', 1, 0, 'L', true);
    $pdf->Cell($wM, 7, 'Monto', 1, 0, 'R', true);
    $pdf->Cell($wS, 7, 'Saldo', 1, 1, 'R', true);

    $pdf->SetFont('dejavusans', '', 7);
    $saldo_acumulado = 0;
    $fill = false;
    foreach ($movimientos as $m) {
        $monto = (float) $m['monto'];
        $saldo_acumulado += $monto;
        $fechaFormateada = date('d/m/Y', strtotime($m['fecha']));
        $usuario_nombre = pdf_txt_celda($m['usuario_nombre'] ?? 'Sin usuario');

        $pdf->SetFillColor(241, 245, 249);
        $pdf->SetTextColor(0, 0, 0);
        $pdf->Cell($wF, 6, $fechaFormateada, 1, 0, 'C', $fill);
        $pdf->Cell($wC, 6, pdf_truncar_linea(up_pdf($m['concepto'] ?? ''), 48), 1, 0, 'L', $fill);
        $pdf->Cell($wCo, 6, pdf_truncar_linea(up_pdf($m['comprobante'] ?? ''), 14), 1, 0, 'C', $fill);
        $pdf->Cell($wR, 6, pdf_truncar_linea(up_pdf($m['referencia'] ?? ''), 14), 1, 0, 'C', $fill);
        $pdf->Cell($wU, 6, pdf_truncar_linea($usuario_nombre, 28), 1, 0, 'L', $fill);

        if ($monto >= 0) {
            $pdf->SetTextColor(40, 167, 69);
        } else {
            $pdf->SetTextColor(220, 53, 69);
        }
        $pdf->Cell($wM, 6, fmt_moneda_pdf($monto), 1, 0, 'R', $fill);
        if ($saldo_acumulado >= 0) {
            $pdf->SetTextColor(40, 167, 69);
        } else {
            $pdf->SetTextColor(220, 53, 69);
        }
        $pdf->Cell($wS, 6, fmt_moneda_pdf($saldo_acumulado), 1, 1, 'R', $fill);
        $pdf->SetTextColor(0, 0, 0);
        $fill = !$fill;
    }

    $pdf->SetFont('dejavusans', 'B', 8);
    $pdf->SetFillColor(226, 232, 240);
    $tc = $saldo_total_operacion >= 0 ? [40, 167, 69] : [220, 53, 69];
    $pdf->Cell($wF + $wC + $wCo + $wR + $wU + $wM, 7, 'TOTAL OPERACIÓN:', 1, 0, 'R', true);
    $pdf->SetTextColor($tc[0], $tc[1], $tc[2]);
    $pdf->Cell($wS, 7, fmt_moneda_pdf($saldo_total_operacion), 1, 1, 'R', true);
    $pdf->SetTextColor(0, 0, 0);
}

$fname = 'movimientos_operacion_' . $operacion . '_' . date('Y-m-d') . '.pdf';
$disposition = isset($_GET['disposition']) && $_GET['disposition'] === 'inline' ? 'I' : 'D';
if (ob_get_level() > 0) {
    ob_end_clean();
}
$pdf->Output($fname, $disposition);
