<?php
include 'db.php';
include 'verificar_sesion.php';
include 'helpers_contrato.php';

if (!isset($_GET['id'])) {
    die("ID no recibido");
}

$movimiento_id = (int)$_GET['id'];

// Obtener datos del movimiento
$sql_mov = "SELECT c.movimiento_id, c.usuario_id, c.fecha, c.concepto, c.comprobante, c.referencia, c.monto,
                   u.apellido AS usuario_nombre
            FROM cuentas c
            INNER JOIN usuarios u ON u.id = c.usuario_id
            WHERE c.movimiento_id = $movimiento_id LIMIT 1";
$res_mov = mysqli_query($conexion, $sql_mov);
if (!$res_mov || mysqli_num_rows($res_mov) == 0) {
    die("Movimiento no encontrado");
}
$mov = mysqli_fetch_assoc($res_mov);

// Usuario inquilino (el que pagó)
$usuario_inquilino = strtoupper(trim($mov['usuario_nombre']));
$usuario_id = (int)$mov['usuario_id'];
$monto = abs((float)$mov['monto']);
$concepto = strtoupper(trim($mov['concepto']));
$fecha = $mov['fecha'];

// Obtener propietario: buscar si el usuario es inquilino de alguna propiedad
$propietario_nombre = '';
$sql_prop = "SELECT u_prop.apellido AS propietario_nombre
             FROM alquileres a
             INNER JOIN propiedades p ON p.propiedad_id = a.propiedad_id
             INNER JOIN usuarios u_prop ON u_prop.id = p.propietario_id
             WHERE a.estado = 'VIGENTE' 
             AND (a.inquilino1_id = $usuario_id OR a.inquilino2_id = $usuario_id)
             LIMIT 1";
$res_prop = mysqli_query($conexion, $sql_prop);
if ($res_prop && mysqli_num_rows($res_prop) > 0) {
    $row_prop = mysqli_fetch_assoc($res_prop);
    $propietario_nombre = strtoupper(trim($row_prop['propietario_nombre']));
} else {
    // Si no es inquilino, buscar si es propietario y obtener su nombre (firmará él mismo)
    $sql_prop2 = "SELECT apellido FROM usuarios WHERE id = $usuario_id LIMIT 1";
    $res_prop2 = mysqli_query($conexion, $sql_prop2);
    if ($res_prop2 && mysqli_num_rows($res_prop2) > 0) {
        $row_prop2 = mysqli_fetch_assoc($res_prop2);
        $propietario_nombre = strtoupper(trim($row_prop2['apellido']));
    } else {
        $propietario_nombre = 'HERRERA Y LLOBETA S. R. L.';
    }
}

// Si aún no hay propietario, usar el nombre por defecto
if (empty($propietario_nombre)) {
    $propietario_nombre = 'HERRERA Y LLOBETA S. R. L.';
}

// Formatear fecha: "a los [día] de [mes] de [año]"
$ts = strtotime($fecha);
$dia = (int)date('j', $ts);
$mes = mesNombre(date('n', $ts));
$anio = date('Y', $ts);
$fecha_formateada = "San Miguel de Tucumán, a los  $dia de $mes de $anio";

// Convertir monto a letras
$monto_letras = numerosALetras($monto);
$monto_numero = number_format($monto, 0, ',', '.');

// Número de recibo (usar movimiento_id)
$nro_recibo = $movimiento_id;

// Cabeceras para descarga como Word
header("Content-Type: application/vnd.ms-word; charset=UTF-8");
header("Content-Disposition: attachment; filename=\"Recibo_$nro_recibo.doc\"");
?>
<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:w="urn:schemas-microsoft-com:office:word" xmlns="http://www.w3.org/TR/REC-html40">
<head>
<meta charset="UTF-8">
<meta name="ProgId" content="Word.Document">
<meta name="Generator" content="Microsoft Word 15">
<meta name="Originator" content="Microsoft Word 15">
<xml>
<w:WordDocument>
<w:View>Print</w:View>
<w:Zoom>90</w:Zoom>
<w:DoNotOptimizeForBrowser/>
</w:WordDocument>
</xml>
<style>
    body {
        font-family: Arial, sans-serif;
        font-size: 12pt;
        margin: 1cm;
        line-height: 1.5;
    }
    .titulo {
        text-align: center;
        font-size: 14pt;
        font-weight: bold;
        letter-spacing: 2px;
        margin-bottom: 10px;
        line-height: 1.5;
    }
    .fecha {
        margin-bottom: 15px;
        text-align: right;
        line-height: 1.5;
    }
    .texto {
        margin-bottom: 15px;
        text-align: justify;
        line-height: 1.5;
    }
    .son {
        margin-bottom: 20px;
        text-align: justify;
        line-height: 1.5;
    }
    .firma-container {
        margin-top: 18px;
        line-height: 1.5;
    }
</style>
</head>
<body>
    <div class="titulo">R&nbsp;E&nbsp;C&nbsp;I&nbsp;B&nbsp;O&nbsp;&nbsp;&nbsp;Nº&nbsp;<?= $nro_recibo ?></div>
    <p>&nbsp;</p>
    <div class="fecha"><?= $fecha_formateada ?></div>
    <div class="texto">Recibí de <?= $usuario_inquilino ?> la cantidad de pesos <?= $monto_letras ?> . ($<?= $monto_numero ?>-) en concepto de <?= $concepto ?>.</div>
    <div class="son"><strong>Son:$<?= $monto_numero ?>-</strong></div>
    <div class="firma-container">
        <table style="width: 100%; border-collapse: collapse; border: 0;">
            <tr>
                <td style="width: 70%; border: 0;">&nbsp;</td>
                <td style="text-align: center; border: 0; white-space: nowrap;">.............................................................................</td>
            </tr>
            <tr>
                <td style="width: 70%; border: 0;">&nbsp;</td>
                <td style="text-align: center; border: 0; padding-top: 5px;"><?= $propietario_nombre ?></td>
            </tr>
        </table>
    </div>
</body>
</html>
