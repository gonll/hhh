<?php
/**
 * PDF vectorial (TCPDF) — Informe operaciones del operador.
 * Misma lógica de datos que obtener_operaciones_operador.php (no captura HTML/imagen).
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

function h($s)
{
    return htmlspecialchars((string) $s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function fmt_moneda($n)
{
    $n = (float) $n;
    $signo = $n < 0 ? '-' : '';
    $abs = abs($n);
    return '$ ' . $signo . number_format($abs, 2, ',', '.');
}

try {
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
    $pdf->Ln(3);
    $pdf->SetFont('dejavusans', 'B', 10);
    $pdf->SetTextColor(26, 32, 44);
    $pdf->Cell(0, 6, 'Operador: ' . h($operador_nombre), 0, 1, 'L');
    $pdf->Ln(4);

    if (count($operaciones) === 0) {
        $pdf->SetFont('dejavusans', '', 10);
        $pdf->SetTextColor(100, 100, 100);
        $pdf->Cell(0, 8, 'No hay operaciones para este operador.', 0, 1, 'L');
    } else {
        $html = '<style>
        table.tab-inf { border-collapse: collapse; width: 100%; font-size: 9pt; }
        table.tab-inf th { background-color: #2c5282; color: #ffffff; font-weight: bold; padding: 6px 5px; border: 1px solid #1a365d; }
        table.tab-inf td { padding: 5px; border: 1px solid #cbd5e0; vertical-align: top; }
        table.tab-inf tr:nth-child(even) td { background-color: #f1f5f9; }
        .c-op { width: 18%; text-align: center; }
        .c-vend { width: 50%; text-align: left; }
        .c-saldo { width: 32%; text-align: right; }
        .total td { background-color: #e2e8f0 !important; font-weight: bold; border-top: 2px solid #2c5282 !important; }
    </style>';
        $html .= '<table class="tab-inf" cellspacing="0" cellpadding="3"><thead><tr>';
        $html .= '<th class="c-op">Operación</th><th class="c-vend">Vendida a</th><th class="c-saldo">Saldo</th>';
        $html .= '</tr></thead><tbody>';

        foreach ($operaciones as $row) {
            $saldo = (float) $row['saldo'];
            $color = $saldo >= 0 ? '#28a745' : '#dc3545';
            $html .= '<tr>';
            $html .= '<td class="c-op">' . h((string) $row['operacion']) . '</td>';
            $html .= '<td class="c-vend">' . h($row['vendida_a'] !== '' ? $row['vendida_a'] : '—') . '</td>';
            $html .= '<td class="c-saldo" style="color:' . $color . ';">' . h(fmt_moneda($saldo)) . '</td>';
            $html .= '</tr>';
        }

        $tc = $total_saldo >= 0 ? '#28a745' : '#dc3545';
        $html .= '<tr class="total">';
        $html .= '<td colspan="2" style="text-align:right;">TOTAL:</td>';
        $html .= '<td class="c-saldo" style="color:' . $tc . ';">' . h(fmt_moneda($total_saldo)) . '</td>';
        $html .= '</tr>';
        $html .= '</tbody></table>';

        $pdf->writeHTML($html, true, false, true, false, '');
    }

    $fname = 'operaciones_operador_' . $operador_id . '_' . date('Y-m-d') . '.pdf';
    $disposition = isset($_GET['disposition']) && $_GET['disposition'] === 'inline' ? 'I' : 'D';
    if (ob_get_level() > 0) {
        ob_end_clean();
    }
    $pdf->Output($fname, $disposition);
} catch (Throwable $e) {
    error_log('[PDF operaciones_operador] ' . $e->getMessage() . "\n" . $e->getTraceAsString());
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Error al generar el PDF. Revise el log del servidor (PHP/TCPDF).';
    exit;
}
