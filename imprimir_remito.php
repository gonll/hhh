<?php
/**
 * Imprime el remito según el modelo preimpreso.
 * Hoja: 19.3 cm ancho x 30 cm alto.
 * Los datos dinámicos se obtienen del registro de stock seleccionado.
 */
include 'db.php';
include 'verificar_sesion.php';

if (isset($_SESSION['acceso_nivel']) && $_SESSION['acceso_nivel'] < 2) {
    header('Location: index.php?msg=sin_permiso');
    exit;
}

$stock_id = (int)($_GET['id'] ?? 0);
if ($stock_id < 1) {
    die('Falta o es inválido el ID del remito.');
}

$sql = "SELECT s.*,
    v.apellido AS vendida_a_apellido, v.domicilio AS vendida_a_domicilio, v.cuit AS vendida_a_cuit,
    f.apellido AS facturada_a_apellido, f.domicilio AS facturada_a_domicilio, f.cuit AS facturada_a_cuit
FROM stock s
LEFT JOIN usuarios v ON v.id = s.vendida_a_id
LEFT JOIN usuarios f ON f.id = s.facturada_a_id
WHERE s.id = $stock_id LIMIT 1";
$res = mysqli_query($conexion, $sql);
$r = $res ? mysqli_fetch_assoc($res) : null;
if (!$r) {
    die('No se encontró el registro.');
}

// Destinatario: preferir facturada_a, si no vendida_a
$dest_razon = trim($r['facturada_a_apellido'] ?? '') ?: trim($r['vendida_a_apellido'] ?? '');
$dest_domicilio = trim($r['facturada_a_domicilio'] ?? '') ?: trim($r['vendida_a_domicilio'] ?? '');
$dest_cuit = trim($r['facturada_a_cuit'] ?? '') ?: trim($r['vendida_a_cuit'] ?? '');
$dest_cuit = preg_replace('/\D/', '', $dest_cuit);
if (strlen($dest_cuit) === 11) {
    $dest_cuit = substr($dest_cuit, 0, 2) . '-' . substr($dest_cuit, 2, 8) . '-' . substr($dest_cuit, 10, 1);
}

// Datos del emisor (preimpreso en el modelo - Héctor Hugo Herrera)
$emisor_nombre = 'Héctor Hugo Herrera';
$emisor_domicilio = 'Estados Unidos 96';
$emisor_ciudad = '4000 - San Miguel de Tucumán';
$emisor_cuit = '20-14480618-3';
$emisor_ing_brutos = '20-14480618-3';
$emisor_inicio_act = '11/2013';

// Datos dinámicos del registro
$n_remito = htmlspecialchars($r['n_remt'] ?? '');
$fecha_raw = !empty($r['fecha_fact']) && $r['fecha_fact'] !== '0000-00-00' ? $r['fecha_fact'] : ($r['fecha'] ?? '0000-00-00');
$fecha_partes = explode('-', $fecha_raw);
$fecha_dia = isset($fecha_partes[2]) ? (int)$fecha_partes[2] : '';
$fecha_mes = isset($fecha_partes[1]) ? (int)$fecha_partes[1] : '';
$fecha_anio = isset($fecha_partes[0]) ? (int)$fecha_partes[0] : '';
$fecha_imp = $fecha_dia && $fecha_mes && $fecha_anio ? sprintf('%d    %d    %d', $fecha_dia, $fecha_mes, $fecha_anio) : '';

$cantidad = (int)($r['cant_fact'] ?? $r['cant_vta'] ?? $r['cantidad'] ?? 0);
$articulo = htmlspecialchars($r['articulo'] ?? '');
$orden = (int)($r['orden'] ?? 0);
$n_fact = htmlspecialchars($r['n_fact'] ?? '');

// Fecha impresión y CAI (para pie - opcional)
$fecha_impresion = date('d/m/Y');
$cai = ''; // Si tenés CAI asignado, configurarlo aquí
$fecha_vto_cai = '';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Remito <?= $n_remito ?></title>
    <style>
        body { margin: 0; padding: 0; font-family: Arial, sans-serif; font-size: 10px; }
        .no-print { margin: 10px; }
        @page { size: 19.3cm 30cm; margin: 0; }
        .remito-page { width: 19.3cm; min-height: 30cm; position: relative; box-sizing: border-box; }
        /* Siempre alinear con formulario: impresion-datos visible en pantalla */
        .impresion-datos { display: block; background: #f9f9f9; border: 1px solid #ccc; }
        @media print {
            .no-print { display: none !important; }
            body, html { margin: 0; padding: 0; background: transparent !important; }
            .impresion-datos { background: transparent !important; border: none !important; }
        }
        .impresion-datos {
            position: relative;
            width: 19.3cm;
            height: 30cm;
            font-size: 10px;
        }
        .impresion-datos .campo {
            position: absolute;
            color: #000;
            white-space: pre-wrap;
            word-wrap: break-word;
            font-weight: bold;
            font-size: 12px;
        }
    </style>
</head>
<body>
<div class="no-print">
    <button onclick="window.print()">Imprimir remito</button>
    <a href="gestionar_azucares.php">Volver a gestión de azúcar</a>
</div>

<?php $detalle_texto = $articulo . ($orden > 0 ? " correspondiente a la orden de maquila N° $orden" : '') . ", endosada y entregada en este acto"; ?>
<div class="remito-page">
    <!-- Siempre alinear con formulario -->
    <div class="impresion-datos">
        <span class="campo" style="left: 154mm; top: 27mm; font-size: 14.4px;"><?= $fecha_imp ?></span>
        <span class="campo" style="left: 22mm; top: 53mm; max-width: 155mm;"><?= $dest_razon ?></span>
        <span class="campo" style="left: 24mm; top: 61mm; max-width: 155mm;"><?= strtoupper($dest_domicilio) ?></span>
        <span class="campo" style="left: 29mm; top: 78.5mm;">X</span>
        <span class="campo" style="left: 131mm; top: 75mm;"><?= $dest_cuit ?></span>
        <span class="campo" style="left: 32mm; top: 90mm;">X</span>
        <span class="campo" style="left: 123mm; top: 88mm;"><?= $n_fact ?></span>
        <span class="campo" style="left: 20mm; top: 101mm;"><?= $cantidad ?></span>
        <span class="campo" style="left: 36mm; top: 101mm; max-width: 135mm; line-height: 1.9;"><?= $detalle_texto ?></span>
    </div>
</div>

<script>
window.onload = function() {
    if (window.location.search.indexOf('auto=1') !== -1) {
        window.print();
    }
};
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        window.close();
        if (!window.closed) window.location.href = 'gestionar_azucares.php';
    }
});
</script>
</body>
</html>
