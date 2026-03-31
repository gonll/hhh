<?php
include 'db.php';
include 'verificar_sesion.php';
include 'helpers_contrato.php';
include __DIR__ . '/includes/expensa_extraordinaria.php';
include_once __DIR__ . '/includes/expensa_hoja_propiedad.php';

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

$toFloatLiq = function ($s) {
    $s = str_replace('.', '', $s);
    $s = str_replace(',', '.', $s);
    return (float)$s;
};

$ultimo_liq_id = null;
$penultimo_liq_id = null;
$ultimo_mes_liq = null;
$liq_ordinarias = null;
$liq_extraordinarias = null;

// Período explícito (?mes=1-12&anio=AAAA): liquidación de ese mes/año
$periodo_solicitado = null;
if (isset($_GET['mes'], $_GET['anio'])) {
    $mes_sel = (int)$_GET['mes'];
    $anio_sel = (int)$_GET['anio'];
    if ($mes_sel >= 1 && $mes_sel <= 12 && $anio_sel >= 2000 && $anio_sel <= 2100) {
        $periodo_solicitado = str_pad((string)$mes_sel, 2, '0', STR_PAD_LEFT) . '/' . $anio_sel;
    }
}

if ($periodo_solicitado !== null) {
    $ref_esc = mysqli_real_escape_string($conexion, $periodo_solicitado);
    $res_target = mysqli_query($conexion, "SELECT movimiento_id, referencia, concepto FROM cuentas 
        WHERE usuario_id = $consorcio_id AND UPPER(TRIM(comprobante)) = 'LIQ EXPENSAS' 
        AND TRIM(referencia) = '$ref_esc'
        ORDER BY movimiento_id DESC LIMIT 1");
    if (!$res_target || !($row_target = mysqli_fetch_assoc($res_target))) {
        die('<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8"><title>Expensas</title></head><body style="font-family:sans-serif;padding:20px;">'
            . '<p>No hay liquidación (LIQ EXPENSAS) para el período <strong>' . htmlspecialchars($periodo_solicitado) . '</strong>.</p>'
            . '<p><a href="javascript:history.back()">Volver</a></p></body></html>');
    }
    $ultimo_liq_id = (int)$row_target['movimiento_id'];
    $ultimo_mes_liq = trim($row_target['referencia'] ?? '');
    $concepto = trim($row_target['concepto'] ?? '');
    if (preg_match('/Ordinarias\s+([\d.,]+)\s*-\s*Extraordinarias\s+([\d.,]+)/i', $concepto, $m)) {
        $liq_ordinarias = $toFloatLiq($m[1]);
        $liq_extraordinarias = $toFloatLiq($m[2]);
    }
    $res_prev = mysqli_query($conexion, "SELECT movimiento_id FROM cuentas 
        WHERE usuario_id = $consorcio_id AND UPPER(TRIM(comprobante)) = 'LIQ EXPENSAS' 
        AND movimiento_id < $ultimo_liq_id 
        ORDER BY movimiento_id DESC LIMIT 1");
    if ($res_prev && $row_prev = mysqli_fetch_assoc($res_prev)) {
        $penultimo_liq_id = (int)$row_prev['movimiento_id'];
    }
} else {
    // Sin período: las dos últimas liquidaciones (comportamiento anterior)
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
            $liq_ordinarias = $toFloatLiq($m[1]);
            $liq_extraordinarias = $toFloatLiq($m[2]);
        }
    }
    if (count($liq_rows) >= 2) {
        $penultimo_liq_id = (int)$liq_rows[1]['movimiento_id'];
    }
}

// Movimientos entre liquidación anterior y la elegida (incluye la LIQ EXPENSAS de ese período)
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
            if (es_movimiento_expensa_extraordinaria($mov['comprobante'], $mov['concepto'])) {
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
$sql_prop = "SELECT 
                p.propiedad_id,
                p.propietario_id,
                p.porcentaje,
                p.propiedad,
                u.apellido AS propietario_nombre,
                inq.apellido AS inquilino_nombre
             FROM propiedades p
             INNER JOIN usuarios u ON u.id = p.propietario_id
             LEFT JOIN alquileres a ON a.propiedad_id = p.propiedad_id AND a.estado = 'VIGENTE'
             LEFT JOIN usuarios inq ON inq.id = a.inquilino1_id
             WHERE p.porcentaje IS NOT NULL AND p.porcentaje > 0 $cond_consorcio
             ORDER BY p.propiedad ASC";
$res_prop = mysqli_query($conexion, $sql_prop);

$expensas = [];
while ($prop = mysqli_fetch_assoc($res_prop)) {
    $propiedad_id = (int)$prop['propiedad_id'];
    $propietario_id = (int)$prop['propietario_id'];
    $porcentaje = (float)$prop['porcentaje'];
    $monto_extraordinaria = round($total_egresos_extraordinarias * ($porcentaje / 100), 2);
    $monto_ordinaria = round($total_egresos_ordinarias * ($porcentaje / 100), 2);
    $monto_expensa = round($total_expensas * ($porcentaje / 100), 2);

    // Obtener inquilino si existe (ya viene en la consulta principal)
    $inquilino_nombre = '';
    if (!empty($prop['inquilino_nombre'])) {
        $inquilino_nombre = strtoupper(trim($prop['inquilino_nombre']));
    }
    
    $expensas[] = [
        'propiedad' => strtoupper(trim($prop['propiedad'])),
        'propietario' => strtoupper(trim($prop['propietario_nombre'])),
        'inquilino' => $inquilino_nombre,
        'porcentaje' => $porcentaje,
        'monto_extraordinaria' => $monto_extraordinaria,
        'monto_ordinaria' => $monto_ordinaria,
        'monto' => $monto_expensa
    ];
}

$fecha_actual = date('d/m/Y');
$mes_actual_nombre = mesNombre(date('n'));
$anio_actual = date('Y');

// Base para <base href> en ventana/iframe de impresión (rutas relativas del logo, etc.)
$script_name = $_SERVER['SCRIPT_NAME'] ?? '/';
$dir_web = rtrim(str_replace('\\', '/', dirname($script_name)), '/');
if ($dir_web === '') {
    $dir_web = '/';
}
$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$base_href_impresion = $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . $dir_web . '/';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Imprimir Expensas - Consorcio</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 10px;
            background: #f5f5f5;
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
        @media print {
            .no-print { display: none !important; }
            body { margin: 0; padding: 0; background: white; }
            .container {
                max-width: none;
                box-shadow: none;
                padding: 0;
                background: transparent;
            }
            /* Solo el contenido del rectángulo azul (.expensa-container); el resto ya está en .no-print */
            .expensa-page-a4 {
                margin-bottom: 0;
            }
        }
        <?= expensa_hoja_propiedad_css_base() ?>
        <?= expensa_hoja_propiedad_css_a4_print() ?>
        @media screen {
            .expensa-page-a4 { margin-bottom: 18px; }
            .expensa-page-a4 .expensa-container {
                min-height: 280mm;
                max-width: 210mm;
                margin-left: auto;
                margin-right: auto;
            }
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
        <h1 class="no-print">EXPENSAS - <?= htmlspecialchars(strtoupper($row_u['apellido'])) ?></h1>
        
        <?php if (isset($_GET['mail_resultado'])): ?>
            <div class="no-print" style="background: <?= isset($_GET['mail_errores']) ? '#fff3cd' : '#d4edda' ?>; color: <?= isset($_GET['mail_errores']) ? '#856404' : '#155724' ?>; padding: 10px; border-radius: 4px; margin-bottom: 15px; font-size: 12px;">
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
        <div class="expensa-page expensa-page-a4" data-expensa-idx="<?= $idx ?>">
        <div class="expensa-container" id="expensa-<?= $idx ?>">
            <?= expensa_hoja_propiedad_fragmento_html([
                'expensa' => $expensa,
                'movimientos' => $movimientos,
                'total_ingresos' => $total_ingresos,
                'total_egresos_ordinarias' => $total_egresos_ordinarias,
                'total_egresos_extraordinarias' => $total_egresos_extraordinarias,
                'total_expensas' => $total_expensas,
                'ultimo_mes_liq' => $ultimo_mes_liq,
                'fecha_actual' => $fecha_actual,
                'nombre_consorcio' => $nombre_consorcio,
                'logo_src' => 'assets/logo.png',
            ]) ?>
            <div class="no-print expensa-acciones">
                <button type="button" class="btn-icono-exp imprimir" title="Enviar a impresora" data-expensa-idx="<?= $idx ?>" onclick="imprimirExpensa(<?= $idx ?>)">🖨️</button>
                <button type="button" class="btn-icono-exp seguir" title="Seguir" onclick="seguirExpensa(<?= $idx ?>)">▶</button>
            </div>
        </div>
        </div>
        <?php endforeach; ?>
    </div>
    
    <script>
    var BASE_HREF_IMPRESION = <?= json_encode($base_href_impresion, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE) ?>;

    function imprimirExpensa(idx) {
        var expensa = document.getElementById('expensa-' + idx);
        if (!expensa) {
            alert('Error: No se encontró la expensa número ' + idx);
            return;
        }

        var tituloEl = expensa.querySelector('.expensa-title');
        var propiedad = 'Propiedad ' + (idx + 1);
        if (tituloEl) {
            var tm = tituloEl.textContent.match(/^EXPENSA\s*-\s*(.+?)\s*—\s*Porcentaje:/);
            propiedad = tm ? tm[1].trim() : tituloEl.textContent.replace(/^EXPENSA\s*-\s*/i, '').split('—')[0].trim();
        }

        if (!confirm('¿Imprimir expensa de ' + propiedad + '?')) {
            return;
        }

        var contenido = expensa.cloneNode(true);
        var elementosNoPrint = contenido.querySelectorAll('.no-print, button');
        for (var i = 0; i < elementosNoPrint.length; i++) {
            var el = elementosNoPrint[i];
            if (el && el.parentNode) el.parentNode.removeChild(el);
        }

        var safeTitle = String(propiedad).replace(/</g, '').replace(/>/g, '');
        var html = '<!DOCTYPE html><html><head>';
        html += '<meta charset="UTF-8">';
        html += '<base href="' + String(BASE_HREF_IMPRESION).replace(/"/g, '&quot;') + '">';
        html += '<title>Expensa - ' + safeTitle + '</title>';
        html += '<style>';
        html += '@media print { body { margin: 0; padding: 10px; } @page { size: A4; margin: 18mm 10mm 10mm 10mm; } .expensa-container { transform-origin: top center; } }';
        html += 'body { font-family: Arial, sans-serif; margin: 15px; }';
        html += '.expensa-container { padding: 12px; border: 2px solid #007bff; border-radius: 6px; }';
        html += '.expensa-header { text-align: center; margin-bottom: 8px; padding-bottom: 6px; border-bottom: 2px solid #007bff; }';
        html += '.expensa-title { font-size: 14px; font-weight: bold; color: #007bff; margin-bottom: 4px; }';
        html += '.expensa-row-montos { display: flex; flex-wrap: wrap; justify-content: space-between; align-items: baseline; gap: 6px 10px; width: 100%; box-sizing: border-box; }';
        html += '.expensa-header .expensa-title.expensa-row-montos { text-align: left; }';
        html += '.expensa-title-izq, .expensa-dato-izq { flex: 1 1 180px; min-width: 0; text-align: left; }';
        html += '.expensa-monto-alineado { font-size: 11px; font-weight: bold; color: #007bff; white-space: nowrap; text-align: right; }';
        html += '.expensa-montos-der { display: flex; flex-wrap: wrap; justify-content: flex-end; gap: 8px 14px; flex: 1 1 200px; }';
        html += '.expensa-section h3.expensa-row-montos { font-weight: normal; font-size: 11px; }';
        html += '.expensa-info { font-size: 10px; color: #666; margin: 2px 0; }';
        html += '.expensa-section { margin: 6px 0; }';
        html += '.expensa-section h3 { font-size: 11px; color: #333; margin-bottom: 4px; border-bottom: 1px solid #ddd; padding-bottom: 2px; }';
        html += 'table { width: 100%; border-collapse: collapse; margin: 6px 0; font-size: 9px; }';
        html += 'th { background: #007bff; color: white; padding: 4px 6px; text-align: left; font-weight: bold; }';
        html += 'td { padding: 3px 6px; border-bottom: 1px solid #eee; }';
        html += 'th.expensa-col-monto-th{text-align:right;}';
        html += '.expensa-tabla-movimientos td.expensa-col-monto,.expensa-tabla-movimientos tfoot td.expensa-col-monto{text-align:right;color:#000;font-weight:bold;}';
        html += '@media print { .expensa-header { margin-bottom: 4px; padding-bottom: 4px; } .expensa-title { font-size: 11px !important; } .expensa-title-izq, .expensa-dato-izq { font-size: 10px !important; } .expensa-monto-alineado { font-size: 8px !important; white-space: normal !important; } .expensa-info { font-size: 8px !important; } .expensa-section { margin: 4px 0 !important; } .expensa-section h3 { font-size: 9px !important; } .expensa-section h3.expensa-row-montos { font-size: 9px !important; } table { font-size: 7px !important; } th, td { padding: 2px 4px !important; } .expensa-tabla-movimientos td.expensa-col-monto,.expensa-tabla-movimientos tfoot td.expensa-col-monto{color:#000!important;font-weight:bold!important;} }';
        html += '</style>';
        html += '</head><body>';
        html += contenido.innerHTML;
        html += '</body></html>';

        // iframe en la misma página: evita pop-ups y mantiene el diálogo de impresión (user activation)
        var iframe = document.createElement('iframe');
        iframe.setAttribute('aria-hidden', 'true');
        iframe.setAttribute('style', 'position:fixed;right:0;bottom:0;width:0;height:0;border:0;visibility:hidden');
        document.body.appendChild(iframe);

        var win = iframe.contentWindow;
        var doc = win.document;
        doc.open();
        doc.write(html);
        doc.close();

        function aplicarEscalaYImprimir() {
            var cont = doc.querySelector('.expensa-container');
            if (cont) {
                var altoPx = cont.scrollHeight;
                var altoMm = altoPx * 0.264583;
                if (altoMm > 270) {
                    var escala = 270 / altoMm;
                    cont.style.transformOrigin = 'top center';
                    if ('zoom' in cont.style) {
                        cont.style.zoom = escala;
                    } else {
                        cont.style.transform = 'scale(' + escala + ')';
                    }
                }
            }
            try {
                win.focus();
                win.print();
            } finally {
                setTimeout(function() {
                    if (iframe.parentNode) {
                        iframe.parentNode.removeChild(iframe);
                    }
                }, 1000);
            }
        }

        // Un microtask + rAF basta para layout; evita setTimeout largo que rompe la impresión en algunos navegadores
        queueMicrotask(function() {
            requestAnimationFrame(function() {
                requestAnimationFrame(aplicarEscalaYImprimir);
            });
        });
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
