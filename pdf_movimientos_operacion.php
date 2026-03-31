<?php
/**
 * PDF vectorial (TCPDF) — Informe movimientos de pago por operación.
 * Misma lógica que obtener_movimientos_operacion.php (cuentas con referencia OP N° X).
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

function up($s)
{
    return function_exists('mb_strtoupper') ? mb_strtoupper((string) $s, 'UTF-8') : strtoupper((string) $s);
}

try {
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

    if (count($movimientos) === 0) {
        $pdf->SetFont('dejavusans', '', 10);
        $pdf->SetTextColor(100, 100, 100);
        $pdf->Cell(0, 8, 'No hay movimientos para esta operación.', 0, 1, 'L');
    } else {
        $saldo_acumulado = 0;
        $html = '<style>
        table.tab-mov { border-collapse: collapse; width: 100%; font-size: 7.5pt; }
        table.tab-mov th { background-color: #2c5282; color: #ffffff; font-weight: bold; padding: 4px 3px; border: 1px solid #1a365d; }
        table.tab-mov td { padding: 3px; border: 1px solid #cbd5e0; vertical-align: top; }
        table.tab-mov tr:nth-child(even) td { background-color: #f1f5f9; }
        .c-fecha { width: 9%; text-align: center; }
        .c-conc { width: 26%; text-align: left; }
        .c-comp { width: 10%; text-align: center; }
        .c-ref { width: 10%; text-align: center; }
        .c-user { width: 18%; text-align: left; }
        .c-monto { width: 13.5%; text-align: right; }
        .c-saldo { width: 13.5%; text-align: right; }
        .total td { background-color: #e2e8f0 !important; font-weight: bold; border-top: 2px solid #2c5282 !important; }
    </style>';
        $html .= '<table class="tab-mov" cellspacing="0" cellpadding="2"><thead><tr>';
        $html .= '<th class="c-fecha">Fecha</th><th class="c-conc">Concepto</th><th class="c-comp">Comprobante</th><th class="c-ref">Referencia</th><th class="c-user">Usuario</th><th class="c-monto">Monto</th><th class="c-saldo">Saldo</th>';
        $html .= '</tr></thead><tbody>';

        foreach ($movimientos as $m) {
            $monto = (float) $m['monto'];
            $saldo_acumulado += $monto;
            $fechaFormateada = date('d/m/Y', strtotime($m['fecha']));
            $usuario_nombre = htmlspecialchars($m['usuario_nombre'] ?? 'Sin usuario', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $cm = $monto >= 0 ? '#28a745' : '#dc3545';
            $cs = $saldo_acumulado >= 0 ? '#28a745' : '#dc3545';
            $html .= '<tr>';
            $html .= '<td class="c-fecha">' . h($fechaFormateada) . '</td>';
            $html .= '<td class="c-conc">' . h(up($m['concepto'] ?? '')) . '</td>';
            $html .= '<td class="c-comp">' . h(up($m['comprobante'] ?? '')) . '</td>';
            $html .= '<td class="c-ref">' . h(up($m['referencia'] ?? '')) . '</td>';
            $html .= '<td class="c-user">' . $usuario_nombre . '</td>';
            $html .= '<td class="c-monto" style="color:' . $cm . ';">' . h(fmt_moneda($monto)) . '</td>';
            $html .= '<td class="c-saldo" style="color:' . $cs . ';">' . h(fmt_moneda($saldo_acumulado)) . '</td>';
            $html .= '</tr>';
        }

        $tc = $saldo_total_operacion >= 0 ? '#28a745' : '#dc3545';
        $html .= '<tr class="total">';
        $html .= '<td colspan="6" style="text-align:right;">TOTAL OPERACIÓN:</td>';
        $html .= '<td class="c-saldo" style="color:' . $tc . ';">' . h(fmt_moneda($saldo_total_operacion)) . '</td>';
        $html .= '</tr>';
        $html .= '</tbody></table>';

        $pdf->writeHTML($html, true, false, true, false, '');
    }

    $fname = 'movimientos_operacion_' . $operacion . '_' . date('Y-m-d') . '.pdf';
    $disposition = isset($_GET['disposition']) && $_GET['disposition'] === 'inline' ? 'I' : 'D';
    if (ob_get_level() > 0) {
        ob_end_clean();
    }
    $pdf->Output($fname, $disposition);
} catch (Throwable $e) {
    error_log('[PDF movimientos_operacion] ' . $e->getMessage() . "\n" . $e->getTraceAsString());
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Error al generar el PDF. Revise el log del servidor (PHP/TCPDF).';
    exit;
}
