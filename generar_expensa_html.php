<?php
/**
 * Genera el HTML de una expensa con el mismo formato visual que se ve en pantalla
 */
if (!function_exists('generarExpensaHTML')) {
function generarExpensaHTML($expensa, $movimientos, $total_ingresos, $total_egresos_ordinarias, $total_egresos_extraordinarias, $total_expensas, $ultimo_mes_liq, $fecha_actual, $nombre_consorcio) {
    ob_start();
    ?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 20px;
            background: #f5f5f5;
        }
        .expensa-container {
            margin-bottom: 30px;
            padding: 20px;
            border: 2px solid #007bff;
            border-radius: 8px;
            background: white;
            max-width: 900px;
        }
        .expensa-header {
            text-align: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #007bff;
        }
        .expensa-title {
            font-size: 18px;
            font-weight: bold;
            color: #007bff;
            margin-bottom: 10px;
        }
        .expensa-info {
            font-size: 12px;
            color: #666;
            margin: 5px 0;
        }
        .expensa-section {
            margin: 15px 0;
        }
        .expensa-section h3 {
            font-size: 14px;
            color: #333;
            margin-bottom: 10px;
            border-bottom: 1px solid #ddd;
            padding-bottom: 5px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 10px 0;
            font-size: 11px;
        }
        th {
            background: #007bff;
            color: white;
            padding: 8px;
            text-align: left;
            font-weight: bold;
        }
        td {
            padding: 6px 8px;
            border-bottom: 1px solid #eee;
        }
        .total-box {
            background: #e7f3ff;
            padding: 15px;
            border-radius: 4px;
            margin-top: 15px;
            text-align: right;
        }
        .total-box strong {
            font-size: 16px;
            color: #007bff;
        }
    </style>
</head>
<body>
        <div class="expensa-container">
            <div class="expensa-header">
                <div class="expensa-title">EXPENSA - <?= htmlspecialchars($expensa['propiedad']) ?> - Porcentaje: <?= number_format($expensa['porcentaje'], 2, ',', '.') ?>%</div>
                <div class="expensa-info">Consorcio: <?= htmlspecialchars(strtoupper($nombre_consorcio)) ?></div>
                <div class="expensa-info">Mes Liquidado: <?= $ultimo_mes_liq ? htmlspecialchars($ultimo_mes_liq) : 'Desde inicio' ?></div>
                <div class="expensa-info">Fecha: <?= htmlspecialchars($fecha_actual) ?></div>
            </div>
            
            <div class="expensa-section">
                <h3>PROPIETARIO: <strong><?= htmlspecialchars($expensa['propietario']) ?></strong></h3>
            </div>
            
            <?php if ($expensa['inquilino']): ?>
            <div class="expensa-section">
                <h3>INQUILINO: <strong><?= htmlspecialchars($expensa['inquilino']) ?></strong></h3>
            </div>
            <?php endif; ?>
        
        <div class="expensa-section">
            <h3>DETALLE DE MOVIMIENTOS</h3>
            <table>
                <thead>
                    <tr>
                        <th style="width: 12%;">Fecha</th>
                        <th style="width: 40%;">Concepto</th>
                        <th style="width: 15%;">Comprobante</th>
                        <th style="width: 13%;">Referencia</th>
                        <th style="width: 20%; text-align: right;">Monto</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($movimientos as $mov): ?>
                    <tr>
                        <td><?= date('d/m/Y', strtotime($mov['fecha'])) ?></td>
                        <td><?= htmlspecialchars($mov['concepto']) ?></td>
                        <td><?= htmlspecialchars($mov['comprobante']) ?></td>
                        <td><?= htmlspecialchars($mov['referencia']) ?></td>
                        <td style="text-align: right; <?= $mov['monto'] >= 0 ? 'color: #28a745;' : 'color: #dc3545;' ?>">
                            <?= $mov['monto'] >= 0 ? '+' : '' ?>$ <?= number_format($mov['monto'], 2, ',', '.') ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr style="background: #f8f9fa;">
                        <td colspan="4" style="text-align: right; font-weight: bold;">Total Ingresos:</td>
                        <td style="text-align: right; color: #28a745; font-weight: bold;">
                            $ <?= number_format($total_ingresos, 2, ',', '.') ?>
                        </td>
                    </tr>
                    <tr style="background: #f8f9fa;">
                        <td colspan="4" style="text-align: right; font-weight: bold;">Gastado en exp ordinarias:</td>
                        <td style="text-align: right; color: #dc3545; font-weight: bold;">
                            $ <?= number_format($total_egresos_ordinarias, 2, ',', '.') ?>
                        </td>
                    </tr>
                    <tr style="background: #f8f9fa;">
                        <td colspan="4" style="text-align: right; font-weight: bold;">Gastado en extraordinarias:</td>
                        <td style="text-align: right; color: #dc3545; font-weight: bold;">
                            $ <?= number_format($total_egresos_extraordinarias, 2, ',', '.') ?>
                        </td>
                    </tr>
                    <tr style="background: #fff3cd;">
                        <td colspan="4" style="text-align: right; font-weight: bold;">Total Expensas:</td>
                        <td style="text-align: right; color: #856404; font-weight: bold; font-size: 14px;">
                            $ <?= number_format($total_expensas, 2, ',', '.') ?>
                        </td>
                    </tr>
                </tfoot>
            </table>
        </div>
        
        <div class="expensa-section">
            <h3>MONTO A PAGAR</h3>
            <div class="total-box">
                <strong>MONTO A PAGAR: $ <?= number_format($expensa['monto'], 2, ',', '.') ?></strong>
            </div>
        </div>
    </div>
</body>
</html>
    <?php
    return ob_get_clean();
}
}
