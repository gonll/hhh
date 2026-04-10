<?php
/**
 * Genera el HTML de una expensa (misma información que la vista de impresión, hoja A4 modular).
 */
if (!function_exists('generar_expensa_html_logo_src')) {
    function generar_expensa_html_logo_src() {
        require_once __DIR__ . '/helpers_tenant_inmobiliaria.php';

        return tenant_inmob_logo_src_expensa();
    }
}

if (!function_exists('generarExpensaHTML')) {
function generarExpensaHTML($expensa, $movimientos, $total_ingresos, $total_egresos_ordinarias, $total_egresos_extraordinarias, $total_expensas, $ultimo_mes_liq, $fecha_actual, $nombre_consorcio) {
    include_once __DIR__ . '/includes/expensa_hoja_propiedad.php';
    $logo_src = generar_expensa_html_logo_src();
    $fragmento = expensa_hoja_propiedad_fragmento_html([
        'expensa' => $expensa,
        'movimientos' => $movimientos,
        'total_ingresos' => $total_ingresos,
        'total_egresos_ordinarias' => $total_egresos_ordinarias,
        'total_egresos_extraordinarias' => $total_egresos_extraordinarias,
        'total_expensas' => $total_expensas,
        'ultimo_mes_liq' => $ultimo_mes_liq,
        'fecha_actual' => $fecha_actual,
        'nombre_consorcio' => $nombre_consorcio,
        'logo_src' => $logo_src,
    ]);
    ob_start();
    ?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 12px;
            background: #f5f5f5;
        }
        .expensa-mail-wrap {
            max-width: 210mm;
            margin: 0 auto;
        }
        .expensa-page-a4 {
            margin-bottom: 16px;
        }
        <?= expensa_hoja_propiedad_css_base() ?>
        <?= expensa_hoja_propiedad_css_a4_print() ?>
        @media screen {
            .expensa-page-a4 .expensa-container {
                min-height: 260mm;
                box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            }
        }
    </style>
</head>
<body>
    <div class="expensa-mail-wrap">
        <div class="expensa-page-a4">
            <div class="expensa-container">
                <?= $fragmento ?>
            </div>
        </div>
    </div>
</body>
</html>
    <?php
    return ob_get_clean();
}
}
