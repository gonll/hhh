<?php
/**
 * Texto de concepto para la hoja de expensa (impresión/mail): mantiene COBRO EXPENSA, depto/propiedad, período, etc.
 * y omite el nombre de quien pagó (sufijo " - PAGÓ ..." / " - PAGO ..." grabado en cuentas del consorcio).
 */
if (!function_exists('expensa_concepto_publico_impresion')) {
    function expensa_concepto_publico_impresion($concepto) {
        $c = trim((string)$concepto);
        if ($c === '') {
            return '';
        }
        $c = preg_replace('/\s*-\s*PAG(O|Ó)\s+.+$/iu', '', $c);
        return trim($c);
    }
}

/**
 * Hoja única de expensa por propiedad (misma vista en pantalla, impresión y mail).
 *
 * @param array $p expensa, movimientos, total_ingresos, total_egresos_ordinarias,
 *               total_egresos_extraordinarias, total_expensas, ultimo_mes_liq, fecha_actual,
 *               nombre_consorcio, logo_src (ruta img), container_id (opcional)
 */
function expensa_hoja_propiedad_fragmento_html(array $p) {
    $expensa = $p['expensa'];
    $movimientos = $p['movimientos'];
    $total_ingresos = (float)$p['total_ingresos'];
    $total_egresos_ordinarias = (float)$p['total_egresos_ordinarias'];
    $total_egresos_extraordinarias = (float)$p['total_egresos_extraordinarias'];
    $total_expensas = (float)$p['total_expensas'];
    $ultimo_mes_liq = $p['ultimo_mes_liq'] ?? null;
    $fecha_actual = $p['fecha_actual'] ?? '';
    $nombre_consorcio = $p['nombre_consorcio'] ?? '';
    $logo_src = $p['logo_src'] ?? 'assets/logo.png';

    ob_start();
    ?>
            <img src="<?= htmlspecialchars($logo_src) ?>" alt="Logo" class="expensa-logo" width="36" height="36" style="width:36px;height:36px;margin-bottom:8px;display:block;">
            <div class="expensa-header">
                <div class="expensa-title expensa-row-montos">
                    <span class="expensa-title-izq">EXPENSA - <?= htmlspecialchars($expensa['propiedad']) ?> — Porcentaje: <?= number_format($expensa['porcentaje'], 2, ',', '.') ?>%</span>
                    <span class="expensa-monto-alineado expensa-monto-total-header">MONTO TOTAL A PAGAR: $ <?= number_format((float)($expensa['monto'] ?? 0), 2, ',', '.') ?></span>
                </div>
                <div class="expensa-info">Consorcio: <?= htmlspecialchars(strtoupper(trim($nombre_consorcio))) ?></div>
                <div class="expensa-info">Mes liquidado: <?= $ultimo_mes_liq ? htmlspecialchars($ultimo_mes_liq) : 'Desde inicio' ?></div>
                <div class="expensa-info">Fecha: <?= htmlspecialchars($fecha_actual) ?></div>
            </div>

            <div class="expensa-section">
                <h3 class="expensa-row-montos">
                    <span class="expensa-dato-izq">PROPIETARIO: <strong><?= htmlspecialchars($expensa['propietario']) ?></strong></span>
                    <span class="expensa-montos-der">
                        <span class="expensa-monto-alineado expensa-monto-parcela">MONTO A PAGAR EXTRAORDINARIA: $ <?= number_format((float)($expensa['monto_extraordinaria'] ?? 0), 2, ',', '.') ?></span>
                        <?php if (empty($expensa['inquilino'])): ?>
                        <span class="expensa-monto-alineado expensa-monto-parcela">MONTO A PAGAR ORDINARIA: $ <?= number_format((float)($expensa['monto_ordinaria'] ?? 0), 2, ',', '.') ?></span>
                        <?php endif; ?>
                    </span>
                </h3>
            </div>

            <?php if (!empty($expensa['inquilino'])): ?>
            <div class="expensa-section">
                <h3 class="expensa-row-montos">
                    <span class="expensa-dato-izq">INQUILINO: <strong><?= htmlspecialchars($expensa['inquilino']) ?></strong></span>
                    <span class="expensa-monto-alineado expensa-monto-parcela">MONTO A PAGAR ORDINARIA: $ <?= number_format((float)($expensa['monto_ordinaria'] ?? 0), 2, ',', '.') ?></span>
                </h3>
            </div>
            <?php endif; ?>

            <div class="expensa-section expensa-section-movimientos">
                <h3>DETALLE DE MOVIMIENTOS</h3>
                <div class="expensa-tabla-wrap">
                <table class="expensa-tabla-movimientos">
                    <thead>
                        <tr>
                            <th style="width: 12%;">Fecha</th>
                            <th style="width: 40%;">Concepto</th>
                            <th style="width: 15%;">Comprobante</th>
                            <th style="width: 13%;">Referencia</th>
                            <th class="expensa-col-monto-th" style="width: 20%;">Monto</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($movimientos as $mov): ?>
                        <tr>
                            <td><?= date('d/m/Y', strtotime($mov['fecha'])) ?></td>
                            <td><?= htmlspecialchars(expensa_concepto_publico_impresion($mov['concepto'])) ?></td>
                            <td><?= htmlspecialchars($mov['comprobante']) ?></td>
                            <td><?= htmlspecialchars($mov['referencia']) ?></td>
                            <td class="expensa-col-monto">
                                <?= $mov['monto'] >= 0 ? '+' : '' ?>$ <?= number_format($mov['monto'], 2, ',', '.') ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr style="background: #f8f9fa;">
                            <td colspan="4" style="text-align: right; font-weight: bold;">Total Ingresos:</td>
                            <td class="expensa-col-monto">
                                $ <?= number_format($total_ingresos, 2, ',', '.') ?>
                            </td>
                        </tr>
                        <tr style="background: #f8f9fa;">
                            <td colspan="4" style="text-align: right; font-weight: bold;">Gastado en exp ordinarias:</td>
                            <td class="expensa-col-monto">
                                $ <?= number_format($total_egresos_ordinarias, 2, ',', '.') ?>
                            </td>
                        </tr>
                        <tr style="background: #f8f9fa;">
                            <td colspan="4" style="text-align: right; font-weight: bold;">Gastado en extraordinarias:</td>
                            <td class="expensa-col-monto">
                                $ <?= number_format($total_egresos_extraordinarias, 2, ',', '.') ?>
                            </td>
                        </tr>
                        <tr style="background: #fff3cd;">
                            <td colspan="4" style="text-align: right; font-weight: bold;">Total Expensas:</td>
                            <td class="expensa-col-monto expensa-col-monto-total">
                                $ <?= number_format($total_expensas, 2, ',', '.') ?>
                            </td>
                        </tr>
                    </tfoot>
                </table>
                </div>
            </div>
    <?php
    return ob_get_clean();
}

/**
 * CSS base de la hoja (email, PDF HTML, impresión).
 */
function expensa_hoja_propiedad_css_base() {
    return <<<'CSS'
        .expensa-logo { display: block; margin-bottom: 8px; width: 36px; height: 36px; }
        .expensa-container {
            font-family: Arial, Helvetica, sans-serif;
            padding: 12px;
            border: 2px solid #007bff;
            border-radius: 6px;
            background: #fff;
            box-sizing: border-box;
        }
        .expensa-header {
            text-align: center;
            margin-bottom: 10px;
            padding-bottom: 8px;
            border-bottom: 2px solid #007bff;
        }
        .expensa-title {
            font-size: 15px;
            font-weight: bold;
            color: #007bff;
            margin-bottom: 4px;
        }
        .expensa-row-montos {
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            align-items: baseline;
            gap: 6px 10px;
            width: 100%;
            box-sizing: border-box;
        }
        .expensa-header .expensa-title.expensa-row-montos {
            text-align: left;
        }
        .expensa-title-izq {
            flex: 1 1 220px;
            min-width: 0;
            text-align: left;
        }
        .expensa-dato-izq {
            flex: 1 1 180px;
            min-width: 0;
            text-align: left;
        }
        .expensa-monto-alineado {
            font-size: 11px;
            font-weight: bold;
            color: #007bff;
            white-space: nowrap;
            flex: 0 1 auto;
            text-align: right;
        }
        /* MONTO TOTAL A PAGAR (cabecera): negro, negrita, +10 % sobre el monto alineado base */
        .expensa-monto-alineado.expensa-monto-total-header {
            font-size: 12.1px;
            font-weight: 700;
            color: #000;
        }
        /* Extraordinaria / ordinaria: 1 cm a la izquierda, +10 % tamaño, negrita (alineación derecha igual) */
        .expensa-monto-alineado.expensa-monto-parcela {
            font-size: 12.1px;
            font-weight: 700;
            color: #000;
            position: relative;
            left: -1cm;
            display: inline-block;
            vertical-align: baseline;
        }
        .expensa-montos-der {
            display: flex;
            flex-wrap: wrap;
            justify-content: flex-end;
            align-items: baseline;
            gap: 8px 14px;
            flex: 1 1 200px;
        }
        .expensa-section h3.expensa-row-montos {
            font-weight: normal;
            font-size: 12px;
        }
        .expensa-section h3.expensa-row-montos strong {
            font-weight: bold;
        }
        .expensa-info {
            font-size: 11px;
            color: #666;
            margin: 2px 0;
        }
        .expensa-section {
            margin: 8px 0;
        }
        .expensa-section h3 {
            font-size: 12px;
            color: #333;
            margin-bottom: 6px;
            border-bottom: 1px solid #ddd;
            padding-bottom: 3px;
        }
        .expensa-tabla-wrap {
            max-height: 42vh;
            overflow-y: auto;
            border: 1px solid #e9ecef;
            border-radius: 4px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 0;
            font-size: 9px;
        }
        th {
            background: #007bff;
            color: white;
            padding: 4px 6px;
            text-align: left;
            font-weight: bold;
        }
        td {
            padding: 3px 6px;
            border-bottom: 1px solid #eee;
        }
        .expensa-tabla-movimientos th.expensa-col-monto-th {
            text-align: right;
        }
        .expensa-tabla-movimientos td.expensa-col-monto,
        .expensa-tabla-movimientos tfoot td.expensa-col-monto {
            text-align: right;
            color: #000;
            font-weight: bold;
        }
        .expensa-tabla-movimientos tfoot td.expensa-col-monto-total {
            font-size: 14px;
        }
        .total-box {
            background: #e7f3ff;
            padding: 10px;
            border-radius: 4px;
            margin-top: 8px;
            text-align: right;
        }
        .total-box strong {
            font-size: 14px;
            color: #007bff;
            display: inline-block;
            margin: 2px 0;
        }
CSS;
}

/**
 * CSS adicional: módulo A4, una expensa por hoja al imprimir.
 */
function expensa_hoja_propiedad_css_a4_print() {
    return <<<'CSS'
        @page { size: A4 portrait; margin: 12mm; }
        @media print {
            html, body { margin: 0; padding: 0; background: #fff !important; }
            .expensa-page-a4 {
                width: 100%;
                max-width: 186mm;
                margin: 0 auto;
                page-break-after: always;
                page-break-inside: avoid;
                box-sizing: border-box;
            }
            .expensa-page-a4:last-child { page-break-after: auto; }
            .expensa-container {
                margin: 0 !important;
                padding: 8mm !important;
                border: 1px solid #999 !important;
                min-height: 0;
            }
            .expensa-tabla-wrap {
                max-height: none !important;
                overflow: visible !important;
                border: 1px solid #dee2e6;
            }
            table { font-size: 7px !important; }
            th, td { padding: 2px 4px !important; font-size: 7px !important; }
            .expensa-tabla-movimientos td.expensa-col-monto,
            .expensa-tabla-movimientos tfoot td.expensa-col-monto {
                color: #000 !important;
                font-weight: bold !important;
            }
            .expensa-tabla-movimientos tfoot td.expensa-col-monto-total {
                font-size: 8px !important;
            }
            .expensa-title { font-size: 11px !important; }
            .expensa-title-izq, .expensa-dato-izq { font-size: 10px !important; }
            .expensa-monto-alineado { font-size: 8px !important; white-space: normal !important; }
            .expensa-monto-alineado.expensa-monto-total-header {
                font-size: 8.8px !important;
                font-weight: 700 !important;
                color: #000 !important;
            }
            .expensa-monto-alineado.expensa-monto-parcela {
                font-size: 8.8px !important;
                font-weight: 700 !important;
                color: #000 !important;
                left: -1cm !important;
            }
            .expensa-info { font-size: 8px !important; }
            .expensa-section h3 { font-size: 9px !important; }
            .expensa-section h3.expensa-row-montos { font-size: 9px !important; }
            .total-box { padding: 6px !important; }
            .total-box strong { font-size: 10px !important; }
            .expensa-logo { width: 32px !important; height: 32px !important; }
        }
CSS;
}
