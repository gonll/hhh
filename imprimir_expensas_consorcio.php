<?php
include 'db.php';
include 'verificar_sesion.php';
include 'helpers_contrato.php';

// Configurar zona horaria de Argentina
date_default_timezone_set('America/Argentina/Buenos_Aires');

if (!isset($_GET['id'])) {
    die("ID no recibido");
}

$consorcio_id = (int)$_GET['id'];

// Verificar que el usuario sea Consorcio
$res_u = mysqli_query($conexion, "SELECT id, apellido, consorcio FROM usuarios WHERE id = $consorcio_id LIMIT 1");
$row_u = mysqli_fetch_assoc($res_u);
if (!$row_u || stripos($row_u['apellido'], 'CONSORCIO') !== 0) {
    die("El usuario no es un Consorcio");
}

$nombre_consorcio = trim($row_u['consorcio'] ?? '');
$consorcio_esc = mysqli_real_escape_string($conexion, $nombre_consorcio);

// Obtener las dos últimas liquidaciones (LIQ EXPENSAS con monto 0)
$ultimo_liq_id = null;
$penultimo_liq_id = null;
$ultimo_mes_liq = null;
$liq_ordinarias = null;
$liq_extraordinarias = null;
$res_liq = mysqli_query($conexion, "SELECT movimiento_id, referencia, concepto FROM cuentas 
    WHERE usuario_id = $consorcio_id AND UPPER(TRIM(comprobante)) = 'LIQ EXPENSAS' 
    ORDER BY movimiento_id DESC LIMIT 2");
$liq_rows = [];
while ($res_liq && $row = mysqli_fetch_assoc($res_liq)) {
    $liq_rows[] = $row;
}
if (count($liq_rows) >= 1) {
    $ultimo_liq_id = (int)$liq_rows[0]['movimiento_id'];
    $ultimo_mes_liq = trim($liq_rows[0]['referencia'] ?? '');
    $concepto = trim($liq_rows[0]['concepto'] ?? '');
    if (preg_match('/Ordinarias\s+([\d.,]+)\s*-\s*Extraordinarias\s+([\d.,]+)/i', $concepto, $m)) {
        $toFloat = function ($s) {
            $s = str_replace('.', '', $s);
            $s = str_replace(',', '.', $s);
            return (float)$s;
        };
        $liq_ordinarias = $toFloat($m[1]);
        $liq_extraordinarias = $toFloat($m[2]);
    }
}
if (count($liq_rows) >= 2) {
    $penultimo_liq_id = (int)$liq_rows[1]['movimiento_id'];
}

// Obtener movimientos ENTRE las dos últimas liquidaciones (incluye la última LIQ EXPENSAS)
$movimientos = [];
if ($ultimo_liq_id !== null) {
    $cond_rango = ($penultimo_liq_id !== null)
        ? "movimiento_id > $penultimo_liq_id AND movimiento_id <= $ultimo_liq_id"
        : "movimiento_id <= $ultimo_liq_id";
    $sql_mov = "SELECT fecha, concepto, comprobante, referencia, monto 
                FROM cuentas 
                WHERE usuario_id = $consorcio_id AND $cond_rango
                ORDER BY fecha ASC, movimiento_id ASC";
} else {
    $sql_mov = "SELECT fecha, concepto, comprobante, referencia, monto 
                FROM cuentas 
                WHERE usuario_id = $consorcio_id
                ORDER BY fecha ASC, movimiento_id ASC";
}
$res_mov = mysqli_query($conexion, $sql_mov);
while ($mov = mysqli_fetch_assoc($res_mov)) {
    $movimientos[] = [
        'fecha' => $mov['fecha'],
        'concepto' => strtoupper(trim($mov['concepto'])),
        'comprobante' => strtoupper(trim($mov['comprobante'])),
        'referencia' => strtoupper(trim($mov['referencia'] ?? '')),
        'monto' => (float)$mov['monto']
    ];
}

// Calcular totales: usar valores de la última LIQ EXPENSAS si están disponibles
if ($liq_ordinarias !== null && $liq_extraordinarias !== null) {
    $total_egresos_ordinarias = $liq_ordinarias;
    $total_egresos_extraordinarias = $liq_extraordinarias;
    $total_expensas = $liq_ordinarias + $liq_extraordinarias;
    $total_ingresos = 0;
    foreach ($movimientos as $mov) {
        if ($mov['monto'] > 0) $total_ingresos += $mov['monto'];
    }
} else {
    $total_ingresos = 0;
    $total_egresos = 0;
    $total_egresos_extraordinarias = 0;
    foreach ($movimientos as $mov) {
        if ($mov['monto'] > 0) {
            $total_ingresos += $mov['monto'];
        } else {
            $monto_abs = abs($mov['monto']);
            $total_egresos += $monto_abs;
            if ($mov['comprobante'] === 'EXP EXTRAORDINARIA') {
                $total_egresos_extraordinarias += $monto_abs;
            }
        }
    }
    $total_egresos_ordinarias = $total_egresos - $total_egresos_extraordinarias;
    $total_expensas = $total_egresos;
}

// Obtener propiedades del consorcio con porcentaje
$cond_consorcio = $nombre_consorcio === ''
    ? "AND (p.consorcio IS NULL OR TRIM(p.consorcio) = '')"
    : "AND UPPER(TRIM(p.consorcio)) = UPPER('$consorcio_esc')";
$sql_prop = "SELECT p.propiedad_id, p.propietario_id, p.porcentaje, p.propiedad,
                    u.apellido AS propietario_nombre
             FROM propiedades p
             INNER JOIN usuarios u ON u.id = p.propietario_id
             WHERE p.porcentaje IS NOT NULL AND p.porcentaje > 0 $cond_consorcio
             ORDER BY p.propiedad ASC";
$res_prop = mysqli_query($conexion, $sql_prop);

$expensas = [];
while ($prop = mysqli_fetch_assoc($res_prop)) {
    $propiedad_id = (int)$prop['propiedad_id'];
    $propietario_id = (int)$prop['propietario_id'];
    $porcentaje = (float)$prop['porcentaje'];
    $monto_expensa = round($total_expensas * ($porcentaje / 100), 2);
    
    // Obtener inquilino si existe
    $inquilino_nombre = '';
    $res_inq = mysqli_query($conexion, "SELECT u.apellido 
                                        FROM alquileres a
                                        INNER JOIN usuarios u ON u.id = a.inquilino1_id
                                        WHERE a.propiedad_id = $propiedad_id AND a.estado = 'VIGENTE' 
                                        LIMIT 1");
    if ($res_inq && $row_inq = mysqli_fetch_assoc($res_inq)) {
        $inquilino_nombre = strtoupper(trim($row_inq['apellido']));
    }
    
    $expensas[] = [
        'propiedad' => strtoupper(trim($prop['propiedad'])),
        'propietario' => strtoupper(trim($prop['propietario_nombre'])),
        'inquilino' => $inquilino_nombre,
        'porcentaje' => $porcentaje,
        'monto' => $monto_expensa
    ];
}

$fecha_actual = date('d/m/Y');
$mes_actual_nombre = mesNombre(date('n'));
$anio_actual = date('Y');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Imprimir Expensas - Consorcio</title>
    <style>
        @page { size: A4; margin: 18mm 10mm 10mm 10mm; }
        @media print {
            .no-print { display: none !important; }
            body { margin: 0; padding: 0; background: white; scroll-snap-type: none; }
            .container { max-width: none; box-shadow: none; padding: 0; }
            .expensa-page {
                page-break-after: always;
            }
            .expensa-page:last-child { page-break-after: auto; }
            .expensa-logo { display: block !important; margin-bottom: 6px; width: 36px !important; height: 36px !important; }
            .expensa-container {
                page-break-inside: avoid;
                margin: 0;
                padding: 8mm;
                border: 1px solid #ccc;
                min-height: 0;
                transform-origin: top center;
            }
            .expensa-header { margin-bottom: 4px; padding-bottom: 4px; }
            .expensa-title { font-size: 11px !important; margin-bottom: 2px !important; }
            .expensa-info { font-size: 8px !important; margin: 1px 0 !important; }
            .expensa-section { margin: 4px 0 !important; }
            .expensa-section h3 { font-size: 9px !important; margin-bottom: 2px !important; padding-bottom: 2px !important; }
            table { font-size: 7px !important; margin: 3px 0 !important; }
            th, td { padding: 2px 4px !important; font-size: 7px !important; }
            .total-box { padding: 6px !important; margin-top: 4px !important; }
            .total-box strong { font-size: 10px !important; }
            .expensa-tabla-wrap { max-height: none !important; overflow: visible !important; }
        }
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 10px;
            background: #f5f5f5;
            scroll-snap-type: y mandatory;
        }
        .container {
            background: white;
            padding: 15px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            max-width: 210mm;
            margin: 0 auto;
        }
        h1 {
            text-align: center;
            color: #007bff;
            margin-bottom: 12px;
            font-size: 1.2rem;
        }
        .controles {
            text-align: center;
            margin-bottom: 12px;
            padding: 10px;
            background: #f8f9fa;
            border-radius: 4px;
        }
        .controles button {
            background: #007bff;
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 13px;
            margin: 0 4px;
        }
        .controles button:hover {
            background: #0056b3;
        }
        .expensa-container {
            margin-bottom: 20px;
            padding: 12px;
            border: 2px solid #007bff;
            border-radius: 6px;
            background: white;
            min-height: 100vh;
            box-sizing: border-box;
            scroll-snap-align: start;
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
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 6px 0;
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
        .monto-negativo {
            color: #dc3545;
            font-weight: bold;
        }
        .monto-positivo {
            color: #28a745;
            font-weight: bold;
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
        }
        .expensa-tabla-wrap {
            max-height: 35vh;
            overflow-y: auto;
        }
        @media print {
            .expensa-tabla-wrap { max-height: none; overflow: visible; }
        }
        .expensa-acciones {
            display: flex;
            gap: 12px;
            justify-content: center;
            align-items: center;
            margin-top: 15px;
        }
        .btn-icono-exp {
            width: 40px;
            height: 40px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 18px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            transition: transform 0.15s, box-shadow 0.15s;
        }
        .btn-icono-exp:hover {
            transform: scale(1.08);
            box-shadow: 0 2px 8px rgba(0,0,0,0.2);
        }
        .btn-icono-exp.imprimir {
            background: #007bff;
            color: white;
        }
        .btn-icono-exp.imprimir:hover {
            background: #0056b3;
        }
        .btn-icono-exp.seguir {
            background: #28a745;
            color: white;
        }
        .btn-icono-exp.seguir:hover {
            background: #218838;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>EXPENSAS - <?= htmlspecialchars(strtoupper($row_u['apellido'])) ?></h1>
        
        <?php if (isset($_GET['mail_resultado'])): ?>
            <div style="background: <?= isset($_GET['mail_errores']) ? '#fff3cd' : '#d4edda' ?>; color: <?= isset($_GET['mail_errores']) ? '#856404' : '#155724' ?>; padding: 10px; border-radius: 4px; margin-bottom: 15px; font-size: 12px;">
                <strong><?= isset($_GET['mail_errores']) ? '⚠️' : '✓' ?></strong> <?= htmlspecialchars($_GET['mail_resultado']) ?>
                <?php if (isset($_GET['mail_errores'])): ?>
                    <div style="margin-top: 8px; font-size: 11px;">
                        <strong>Detalles:</strong><br>
                        <?= nl2br(htmlspecialchars($_GET['mail_errores'])) ?>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
        
        <div class="controles no-print">
            <button onclick="imprimirTodas()">🖨️ Imprimir Todas</button>
            <button onclick="enviarExpensasPorMail()">📧 Enviar por mail</button>
            <button onclick="window.close()">Cerrar</button>
        </div>
        
        <?php foreach ($expensas as $idx => $expensa): ?>
        <div class="expensa-page" data-expensa-idx="<?= $idx ?>">
        <div class="expensa-container" id="expensa-<?= $idx ?>">
            <img src="assets/logo.png" alt="Logo" class="expensa-logo" style="width: 36px; height: 36px; margin-bottom: 8px;">
            <div class="expensa-header">
                <div class="expensa-title">EXPENSA - <?= htmlspecialchars($expensa['propiedad']) ?> - Porcentaje: <?= number_format($expensa['porcentaje'], 2, ',', '.') ?>%</div>
                <div class="expensa-info">Mes Liquidado: <?= $ultimo_mes_liq ? htmlspecialchars($ultimo_mes_liq) : 'Desde inicio' ?></div>
                <div class="expensa-info">Fecha: <?= $fecha_actual ?></div>
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
                <div class="expensa-tabla-wrap">
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
            </div>
            
            <div class="expensa-section">
                <h3>MONTO A PAGAR</h3>
                <div class="total-box">
                    <strong>MONTO A PAGAR: $ <?= number_format($expensa['monto'], 2, ',', '.') ?></strong>
                </div>
            </div>
            
            <div class="no-print expensa-acciones">
                <button type="button" class="btn-icono-exp imprimir" title="Enviar a impresora" data-expensa-idx="<?= $idx ?>" onclick="imprimirExpensa(<?= $idx ?>)">🖨️</button>
                <button type="button" class="btn-icono-exp seguir" title="Seguir" onclick="seguirExpensa(<?= $idx ?>)">▶</button>
            </div>
        </div>
        </div>
        <?php endforeach; ?>
    </div>
    
    <script>
    function imprimirExpensa(idx) {
        var expensa = document.getElementById('expensa-' + idx);
        if (!expensa) {
            alert('Error: No se encontró la expensa número ' + idx);
            return;
        }

        // Abrir ventana INMEDIATAMENTE (mismo gesto del usuario) para evitar bloqueo de pop-ups
        var ventana = window.open('', 'VentanaImpresionExpensa', 'width=900,height=700');
        if (!ventana) {
            alert('No se pudo abrir la ventana. Permite ventanas emergentes para este sitio e intenta de nuevo.');
            return;
        }

        var tituloEl = expensa.querySelector('.expensa-title');
        var propiedad = tituloEl ? tituloEl.textContent.replace('EXPENSA - ', '').trim() : 'Propiedad ' + (idx + 1);

        if (!confirm('¿Imprimir expensa de ' + propiedad + '?')) {
            ventana.close();
            return;
        }

        // Crear contenido HTML para la ventana de impresión
        var contenido = expensa.cloneNode(true);

        // Remover botones y elementos no imprimibles
        var elementosNoPrint = contenido.querySelectorAll('.no-print, button');
        for (var i = 0; i < elementosNoPrint.length; i++) {
            var el = elementosNoPrint[i];
            if (el && el.parentNode) el.parentNode.removeChild(el);
        }

        // Crear HTML completo
        var html = '<!DOCTYPE html><html><head>';
        html += '<meta charset="UTF-8">';
        html += '<title>Expensa - ' + propiedad + '</title>';
        html += '<style>';
        html += '@media print { body { margin: 0; padding: 10px; } @page { size: A4; margin: 18mm 10mm 10mm 10mm; } .expensa-container { transform-origin: top center; } }';
        html += 'body { font-family: Arial, sans-serif; margin: 15px; }';
        html += '.expensa-container { padding: 12px; border: 2px solid #007bff; border-radius: 6px; }';
        html += '.expensa-header { text-align: center; margin-bottom: 8px; padding-bottom: 6px; border-bottom: 2px solid #007bff; }';
        html += '.expensa-title { font-size: 14px; font-weight: bold; color: #007bff; margin-bottom: 4px; }';
        html += '.expensa-info { font-size: 10px; color: #666; margin: 2px 0; }';
        html += '.expensa-section { margin: 6px 0; }';
        html += '.expensa-section h3 { font-size: 11px; color: #333; margin-bottom: 4px; border-bottom: 1px solid #ddd; padding-bottom: 2px; }';
        html += 'table { width: 100%; border-collapse: collapse; margin: 6px 0; font-size: 9px; }';
        html += 'th { background: #007bff; color: white; padding: 4px 6px; text-align: left; font-weight: bold; }';
        html += 'td { padding: 3px 6px; border-bottom: 1px solid #eee; }';
        html += '.total-box { background: #e7f3ff; padding: 10px; border-radius: 4px; margin-top: 8px; text-align: right; }';
        html += '.total-box strong { font-size: 12px; color: #007bff; }';
        html += '@media print { .expensa-header { margin-bottom: 4px; padding-bottom: 4px; } .expensa-title { font-size: 11px !important; } .expensa-info { font-size: 8px !important; } .expensa-section { margin: 4px 0 !important; } .expensa-section h3 { font-size: 9px !important; } table { font-size: 7px !important; } th, td { padding: 2px 4px !important; } .total-box { padding: 6px !important; } .total-box strong { font-size: 10px !important; } }';
        html += '</style>';
        html += '</head><body>';
        html += contenido.innerHTML;
        html += '</body></html>';

        ventana.document.open();
        ventana.document.write(html);
        ventana.document.close();

        setTimeout(function() {
            var cont = ventana.document.querySelector('.expensa-container');
            if (cont) {
                var altoPx = cont.scrollHeight;
                var altoMm = altoPx * 0.264583;
                if (altoMm > 270) {
                    var escala = 270 / altoMm;
                    cont.style.zoom = escala;
                    cont.style.transformOrigin = 'top center';
                    if (!('zoom' in cont.style)) cont.style.transform = 'scale(' + escala + ')';
                }
            }
            ventana.focus();
            ventana.print();
        }, 500);
    }

    function seguirExpensa(idx) {
        var siguiente = document.getElementById('expensa-' + (idx + 1));
        if (siguiente) {
            siguiente.scrollIntoView({ behavior: 'smooth', block: 'start' });
        } else {
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }
    }

    function enviarExpensasPorMail() {
        if (!confirm('¿Enviar las expensas por mail a propietarios e inquilinos?')) {
            return;
        }
        var consorcioId = <?= $consorcio_id ?>;
        window.location.href = 'enviar_expensas_mail.php?id=' + consorcioId;
    }
    
    window.imprimirExpensa = imprimirExpensa;
    window.seguirExpensa = seguirExpensa;
    
    window.addEventListener('beforeprint', ajustarExpensasParaImpresion);
    window.addEventListener('afterprint', function() {
        var conts = document.querySelectorAll('.expensa-container');
        for (var i = 0; i < conts.length; i++) { conts[i].style.zoom = ''; conts[i].style.transform = ''; }
    });
    
    window.addEventListener('load', function() {
        var botones = document.querySelectorAll('.btn-icono-exp.imprimir');
        for (var i = 0; i < botones.length; i++) {
            var btn = botones[i];
            var idx = btn.getAttribute('data-expensa-idx');
            if (idx !== null && !btn.onclick) {
                btn.onclick = function() { imprimirExpensa(parseInt(this.getAttribute('data-expensa-idx'))); };
            }
        }
    });
    
    function ajustarExpensasParaImpresion() {
        var altoMaximoMm = 270;
        var paginas = document.querySelectorAll('.expensa-page');
        for (var i = 0; i < paginas.length; i++) {
            var cont = paginas[i].querySelector('.expensa-container');
            if (!cont) continue;
            cont.style.zoom = '';
            cont.style.transform = '';
            var altoPx = cont.scrollHeight;
            var altoMm = altoPx * 0.264583;
            if (altoMm > altoMaximoMm) {
                var escala = altoMaximoMm / altoMm;
                if ('zoom' in cont.style) {
                    cont.style.zoom = escala;
                } else {
                    cont.style.transform = 'scale(' + escala + ')';
                }
            }
        }
    }
    
    function imprimirTodas() {
        if (!confirm('¿Imprimir todas las expensas? Se abrirá el diálogo de impresión.')) {
            return false;
        }
        
        var controles = document.querySelector('.controles');
        if (controles) controles.style.display = 'none';
        
        window.print();
        
        setTimeout(function() {
            if (controles) controles.style.display = '';
            var conts = document.querySelectorAll('.expensa-container');
            for (var i = 0; i < conts.length; i++) conts[i].style.transform = '';
        }, 1000);
        
        return false;
    }

    function enviarExpensasPorMail() {
        if (!confirm('¿Enviar las expensas por mail a propietarios e inquilinos?')) {
            return;
        }
        var consorcioId = <?= $consorcio_id ?>;
        window.location.href = 'enviar_expensas_mail.php?id=' + consorcioId;
    }
    </script>
</body>
</html>
