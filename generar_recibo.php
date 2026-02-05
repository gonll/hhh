<?php
// Recibir datos del ingreso (puedes enviarlos desde el JS tras guardar)
$nombre  = isset($_GET['nombre']) ? strtoupper($_GET['nombre']) : "CLIENTE";
$monto   = isset($_GET['monto']) ? $_GET['monto'] : "0.00";
$concepto = isset($_GET['concepto']) ? strtoupper($_GET['concepto']) : "INGRESO A CUENTA";
$fecha   = date('d/m/Y');
$nro_recibo = rand(1000, 9999);

// Cabeceras para forzar la descarga en Word
header("Content-type: application/vnd.ms-word");
header("Content-Disposition: attachment; title='Recibo_$nombre.doc'; filename='Recibo_$nro_recibo.doc'");
?>

<html>
<head>
    <meta charset="UTF-8">
    <style>
        .recibo { border: 2px solid #000; padding: 20px; width: 600px; font-family: Arial, sans-serif; }
        .cabecera { text-align: center; border-bottom: 1px solid #000; margin-bottom: 20px; }
        .fila { margin-bottom: 15px; font-size: 14pt; }
        .monto-box { float: right; border: 1px solid #000; padding: 5px 15px; font-weight: bold; }
    </style>
</head>
<body>
    <div class="recibo">
        <div class="cabecera">
            <h1>RECIBO DE CAJA</h1>
            <p>Sistema HHH - Tucumán</p>
        </div>
        
        <div class="fila">
            <span class="monto-box">SUMA DE: $ <?php echo $monto; ?></span>
            FECHA: <?php echo $fecha; ?>
        </div>

        <div class="fila">
            RECIBÍ DE: <b><?php echo $nombre; ?></b>
        </div>

        <div class="fila">
            LA CANTIDAD DE: _________________________________________________
        </div>

        <div class="fila">
            EN CONCEPTO DE: <b><?php echo $concepto; ?></b>
        </div>

        <div style="margin-top: 50px; text-align: right;">
            __________________________<br>
            Firma y Aclaración
        </div>
    </div>
</body>
</html>