<?php
include 'db.php';

// Validar que se reciba el ID de la propiedad
if (!isset($_GET['id'])) {
    die("Error: No se especificó la propiedad.");
}

$id_prop = (int)$_GET['id'];

// Consulta detallada para traer datos de la propiedad, contrato vigente y todas las personas involucradas
$sql = "SELECT a.*, p.propiedad, p.consorcio, p.padron, p.detalle as prop_detalle,
               u1.apellido as inq1_nom, u1.dni as inq1_dni,
               u2.apellido as inq2_nom, u2.dni as inq2_dni,
               c1.apellido as cod1_nom, c1.dni as cod1_dni,
               c2.apellido as cod2_nom, c2.dni as cod2_dni
        FROM alquileres a
        INNER JOIN propiedades p ON a.propiedad_id = p.propiedad_id
        INNER JOIN usuarios u1 ON a.inquilino1_id = u1.id
        LEFT JOIN usuarios u2 ON a.inquilino2_id = u2.id
        INNER JOIN usuarios c1 ON a.codeudor1_id = c1.id
        LEFT JOIN usuarios c2 ON a.codeudor2_id = c2.id
        WHERE a.propiedad_id = $id_prop AND a.estado = 'VIGENTE'
        ORDER BY a.alquiler_id DESC LIMIT 1";

$res = mysqli_query($conexion, $sql);
$c = mysqli_fetch_assoc($res);

if (!$c) {
    echo "<script>alert('No se encontró un contrato VIGENTE para esta propiedad.'); window.close();</script>";
    exit;
}

// Configuración de cabeceras para descargar el archivo Word
header("Content-type: application/vnd.ms-word");
header("Content-Disposition: attachment; filename=Contrato_" . str_replace(' ', '_', $c['inq1_nom']) . ".doc");
header("Pragma: no-cache");
header("Expires: 0");

// Formateo de fechas para que se vean bien
$inicio = date('d/m/Y', strtotime($c['fecha_inicio']));
$fin = date('d/m/Y', strtotime($c['fecha_fin']));
$firma = date('d/m/Y', strtotime($c['fecha_firma']));

// Construcción de los textos para múltiples firmantes
$texto_inquilinos = strtoupper($c['inq1_nom']) . " (DNI " . $c['inq1_dni'] . ")";
if ($c['inq2_nom']) $texto_inquilinos .= " y " . strtoupper($c['inq2_nom']) . " (DNI " . $c['inq2_dni'] . ")";

$texto_garantes = strtoupper($c['cod1_nom']) . " (DNI " . $c['cod1_dni'] . ")";
if ($c['cod2_nom']) $texto_garantes .= " y " . strtoupper($c['cod2_nom']) . " (DNI " . $c['cod2_dni'] . ")";

?>
<html xmlns:o='urn:schemas-microsoft-com:office:office' xmlns:w='urn:schemas-microsoft-com:office:word' xmlns='http://www.w3.org/TR/REC-html40'>
<head>
    <meta charset="utf-8">
    <title>Contrato de Locación</title>
    <style>
        body { font-family: 'Arial', sans-serif; font-size: 12pt; text-align: justify; line-height: 1.5; }
        h2 { text-align: center; text-decoration: underline; text-transform: uppercase; }
        .clausula { font-weight: bold; text-transform: uppercase; }
        .firma-espacio { margin-top: 50px; }
    </style>
</head>
<body>

    <h2>Contrato de Locación</h2>

    <p>Entre <b>HERRERA Y LLOBETA SRL</b>, con domicilio en San Martín 854, San Miguel de Tucumán, en adelante denominado el <b>LOCADOR</b> por una parte, y por la otra el Sr/a. <b><?= $texto_inquilinos ?></b>, en adelante denominado el <b>LOCATARIO</b>, se conviene celebrar el presente contrato de locación sujeto a las siguientes cláusulas:</p>

    <p><span class="clausula">PRIMERA:</span> El LOCADOR cede en locación al LOCATARIO, quien acepta, el inmueble ubicado en <b><?= strtoupper($c['propiedad']) ?></b>, identificado con Padrón <b><?= $c['padron'] ?></b> (<?= $c['consorcio'] ?>). El inmueble cuenta con: <?= $c['prop_detalle'] ?>.</p>

    <p><span class="clausula">SEGUNDA (PLAZO):</span> El término de este contrato se fija en <b><?= $c['plazo_meses'] ?> meses</b>, comenzando su vigencia el día <?= $inicio ?> y finalizando de pleno derecho el día <?= $fin ?>, sin necesidad de notificación previa.</p>

    <p><span class="clausula">TERCERA (PRECIO):</span> El precio de la locación se pacta en la suma mensual de <b>$ <?= number_format($c['precio_convenido'], 2, ',', '.') ?></b>. Las partes acuerdan que dicho canon se actualizará periódicamente conforme al <b>Índice IPC</b> publicado por el BCRA, de acuerdo a la normativa vigente al momento de la actualización.</p>

    <p><span class="clausula">CUARTA (GARANTÍA):</span> Se constituye/n como Fiador/es liso/s, llano/s y principal/es pagador/es de todas las obligaciones del locatario al Sr/a. <b><?= $texto_garantes ?></b>, quien/es garantiza/n el fiel cumplimiento de este contrato con todos sus bienes.</p>

    <p><span class="clausula">QUINTA (DEPÓSITO):</span> En este acto el LOCATARIO hace entrega de la suma de <b>$ <?= number_format($c['monto_deposito'], 2, ',', '.') ?></b> en concepto de Depósito de Garantía, importe que el LOCADOR recibe de conformidad.</p>

    <p><span class="clausula">SEXTA:</span> El inmueble será destinado exclusivamente a <b><?= $c['destino'] ?></b>, quedando prohibido cambiar su destino o subarrendar el mismo.</p>

    <p>En prueba de conformidad, se firman dos ejemplares de un mismo tenor y a un solo efecto en la ciudad de San Miguel de Tucumán, a los <?= date('d', strtotime($c['fecha_firma'])) ?> días del mes de <?= date('m', strtotime($c['fecha_firma'])) ?> de <?= date('Y', strtotime($c['fecha_firma'])) ?>.</p>

    <div class="firma-espacio">
        <table width="100%">
            <tr>
                <td align="center">__________________________<br><b>LOCADOR</b><br>HERRERA Y LLOBETA SRL</td>
                <td align="center">__________________________<br><b>LOCATARIO</b><br><?= strtoupper($c['inq1_nom']) ?></td>
            </tr>
            <?php if ($c['inq2_nom']): ?>
            <tr>
                <td colspan="2" align="center" style="padding-top: 30px;">__________________________<br><b>LOCATARIO 2</b><br><?= strtoupper($c['inq2_nom']) ?></td>
            </tr>
            <?php endif; ?>
        </table>
    </div>

</body>
</html>