<?php
/**
 * PDF vectorial (TCPDF) — Informe operaciones del operador.
 * Tabla con Cell (sin writeHTML: más estable en servidores compartidos).
 */
require_once __DIR__ . '/pdf_informe_common.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/verificar_sesion.php';

$operador_id = isset($_GET['operador_id']) ? (int) $_GET['operador_id'] : 0;
if ($operador_id < 1) {
    header('HTTP/1.1 400 Bad Request');
    header('Content-Type: text/plain; charset=utf-8');
    echo 'operador_id inválido';
    exit;
}

$res_op = mysqli_query($conexion, "SELECT apellido FROM usuarios WHERE id = $operador_id LIMIT 1");
$res_op = pdf_informe_res_ok($conexion, $res_op, 'usuarios operador');
$operador_nombre = ($r = mysqli_fetch_assoc($res_op)) ? trim($r['apellido']) : 'Operador #' . $operador_id;

$sql_ops = "SELECT s.operacion, MAX(s.vendida_a_id) AS vendida_a_id
            FROM stock s
            WHERE s.operador_id = $operador_id AND s.operacion IS NOT NULL AND s.operacion > 0
            GROUP BY s.operacion
            ORDER BY s.operacion DESC";
$res_ops = mysqli_query($conexion, $sql_ops);
$res_ops = pdf_informe_res_ok($conexion, $res_ops, 'stock operaciones operador');

$operaciones = [];
$total_saldo = 0.0;
while ($row = mysqli_fetch_assoc($res_ops)) {
    $op = (int) $row['operacion'];
    $ref_esc = mysqli_real_escape_string($conexion, "OP N° $op");
    $res_saldo = mysqli_query($conexion, "SELECT COALESCE(SUM(monto), 0) AS saldo FROM cuentas WHERE referencia = '$ref_esc'");
    $res_saldo = pdf_informe_res_ok($conexion, $res_saldo, 'saldo cuenta op ' . $op);
    $saldo = ($r = mysqli_fetch_assoc($res_saldo)) ? (float) $r['saldo'] : 0.0;
    $total_saldo += $saldo;

    $vendida_a_id = (int) ($row['vendida_a_id'] ?? 0);
    $vendida_a = '';
    if ($vendida_a_id > 0) {
        $res_v = mysqli_query($conexion, "SELECT apellido FROM usuarios WHERE id = $vendida_a_id LIMIT 1");
        $res_v = pdf_informe_res_ok($conexion, $res_v, 'usuarios vendida_a');
        $vendida_a = ($rv = mysqli_fetch_assoc($res_v)) ? trim($rv['apellido']) : '';
    }

    $operaciones[] = [
        'operacion' => $op,
        'saldo' => $saldo,
        'vendida_a' => $vendida_a,
    ];
}

function fmt_moneda_pdf($n) {
    $n = (float) $n;
    $signo = $n < 0 ? '-' : '';
    $abs = abs($n);
    return '$ ' . $signo . number_format($abs, 2, ',', '.');
}

$pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
$pdf->SetCreator('Sistema');
$pdf->SetAuthor('Sistema');
$pdf->SetTitle('Informe — Operaciones del operador');
$pdf->setPrintHeader(false);
$pdf->setPrintFooter(false);
$pdf->SetMargins(14, 14, 14);
$pdf->SetAutoPageBreak(true, 16);
$pdf->AddPage();
$pdf->SetFont('dejavusans', 'B', 16);
$pdf->SetTextColor(26, 54, 93);
$pdf->Cell(0, 8, 'Informe', 0, 1, 'L');
$pdf->SetFont('dejavusans', 'B', 11);
$pdf->SetTextColor(0, 0, 0);
$pdf->Cell(0, 6, 'Operaciones del operador', 0, 1, 'L');
$pdf->SetFont('dejavusans', '', 9);
$pdf->SetTextColor(74, 85, 104);
$pdf->Cell(0, 5, 'Generado: ' . date('d/m/Y H:i'), 0, 1, 'L');
$pdf->Ln(2);
$pdf->SetFont('dejavusans', 'B', 10);
$pdf->SetTextColor(26, 32, 44);
$pdf->Cell(0, 6, 'Operador: ' . pdf_txt_celda($operador_nombre), 0, 1, 'L');
$pdf->Ln(3);

$wOp = 28;
$wVen = 100;
$wSal = 48;

if (count($operaciones) === 0) {
    $pdf->SetFont('dejavusans', '', 10);
    $pdf->SetTextColor(100, 100, 100);
    $pdf->Cell(0, 8, 'No hay operaciones para este operador.', 0, 1, 'L');
} else {
    $pdf->SetFont('dejavusans', 'B', 9);
    $pdf->SetFillColor(44, 82, 130);
    $pdf->SetTextColor(255, 255, 255);
    $pdf->Cell($wOp, 8, 'Operación', 1, 0, 'C', true);
    $pdf->Cell($wVen, 8, 'Vendida a', 1, 0, 'L', true);
    $pdf->Cell($wSal, 8, 'Saldo', 1, 1, 'R', true);

    $pdf->SetFont('dejavusans', '', 9);
    $fill = false;
    foreach ($operaciones as $row) {
        $saldo = (float) $row['saldo'];
        $pdf->SetFillColor(241, 245, 249);
        $pdf->SetTextColor(0, 0, 0);
        $pdf->Cell($wOp, 7, (string) $row['operacion'], 1, 0, 'C', $fill);
        $ven = $row['vendida_a'] !== '' ? $row['vendida_a'] : '—';
        $pdf->Cell($wVen, 7, pdf_truncar_linea($ven, 55), 1, 0, 'L', $fill);
        if ($saldo >= 0) {
            $pdf->SetTextColor(40, 167, 69);
        } else {
            $pdf->SetTextColor(220, 53, 69);
        }
        $pdf->Cell($wSal, 7, fmt_moneda_pdf($saldo), 1, 1, 'R', $fill);
        $pdf->SetTextColor(0, 0, 0);
        $fill = !$fill;
    }

    $pdf->SetFont('dejavusans', 'B', 9);
    $pdf->SetFillColor(226, 232, 240);
    $pdf->Cell($wOp + $wVen, 8, 'TOTAL:', 1, 0, 'R', true);
    $tc = $total_saldo >= 0 ? [40, 167, 69] : [220, 53, 69];
    $pdf->SetTextColor($tc[0], $tc[1], $tc[2]);
    $pdf->Cell($wSal, 8, fmt_moneda_pdf($total_saldo), 1, 1, 'R', true);
    $pdf->SetTextColor(0, 0, 0);
}

$fname = 'operaciones_operador_' . $operador_id . '_' . date('Y-m-d') . '.pdf';
$disposition = isset($_GET['disposition']) && $_GET['disposition'] === 'inline' ? 'I' : 'D';
if (ob_get_level() > 0) {
    ob_end_clean();
}
$pdf->Output($fname, $disposition);
