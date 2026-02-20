<?php
include 'db.php';
include 'verificar_sesion.php';
include 'helpers_contrato.php';
include 'config_contrato.php';

if (!isset($_GET['id'])) {
    die("Error: No se especificó la propiedad.");
}

$id_prop = (int)$_GET['id'];

$sql = "SELECT a.*, p.propiedad, p.consorcio, p.padron, p.detalle as prop_detalle, p.propietario_id,
               prop.apellido as prop_nom, prop.cuit as prop_cuit, prop.dni as prop_dni, prop.domicilio as prop_dom,
               u1.apellido as inq1_nom, u1.dni as inq1_dni, u1.domicilio as inq1_dom, u1.email as inq1_email,
               u2.apellido as inq2_nom, u2.dni as inq2_dni, u2.email as inq2_email,
               c1.apellido as cod1_nom, c1.dni as cod1_dni, c1.domicilio as cod1_dom,
               c2.apellido as cod2_nom, c2.dni as cod2_dni, c2.domicilio as cod2_dom
        FROM alquileres a
        INNER JOIN propiedades p ON a.propiedad_id = p.propiedad_id
        LEFT JOIN usuarios prop ON p.propietario_id = prop.id
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

// Locadora: propietario (o HERRERA Y LLOBETA si no hay)
$loc_nom = !empty($c['prop_nom']) ? strtoupper($c['prop_nom']) : 'HERRERA Y LLOBETA SRL';
$loc_dom = !empty($c['prop_dom']) ? strtoupper($c['prop_dom']) : 'LAMADRID 377 PISO 4 OFICINA D, SAN MIGUEL DE TUCUMÁN, TUCUMAN';
$loc_ident = '';
if (!empty($c['prop_cuit']) && trim($c['prop_cuit']) !== '') {
    $loc_ident = "C.U.I.T. N° " . preg_replace('/\D/', '', $c['prop_cuit']);
} elseif (!empty($c['prop_dni']) && trim($c['prop_dni']) !== '') {
    $loc_ident = "D.N.I. N° " . preg_replace('/\D/', '', $c['prop_dni']);
} else {
    $loc_ident = "C.U.I.T. N° 30708875593"; // fallback
}

// Domicilio propiedad (para locatario) = propiedad + consorcio
$dom_propiedad = trim(strtoupper($c['propiedad'] ?? '') . ', ' . ($c['consorcio'] ?? ''), ', ');

// Locatario
$locatario_txt = strtoupper($c['inq1_nom']) . ", D.N.I. N° " . preg_replace('/\D/', '', $c['inq1_dni'] ?? '');
if (!empty($c['inq2_nom'])) {
    $locatario_txt .= " y " . strtoupper($c['inq2_nom']) . ", D.N.I. N° " . preg_replace('/\D/', '', $c['inq2_dni'] ?? '');
}
$inquilino_mail = trim($c['inq1_email'] ?? '');
if (empty($inquilino_mail) && !empty($c['inq2_email'])) {
    $inquilino_mail = trim($c['inq2_email']);
}

// Fechas y meses
$ts_ini = strtotime($c['fecha_inicio']);
$ts_fin = strtotime($c['fecha_fin']);
$ts_firma = strtotime($c['fecha_firma']);
$dia_ini = (int)date('j', $ts_ini);
$mes_ini = mesNombre(date('n', $ts_ini));
$anio_ini = date('Y', $ts_ini);
$dia_fin = (int)date('j', $ts_fin);
$mes_fin = mesNombre(date('n', $ts_fin));
$anio_fin = date('Y', $ts_fin);
$dia_firma = (int)date('j', $ts_firma);
$mes_firma = mesNombre(date('n', $ts_firma));
$anio_firma = date('Y', $ts_firma);

// Montos
$precio = (float)$c['precio_convenido'];
// El depósito de garantía siempre es 1.5 veces el precio del alquiler
$deposito = round($precio * 1.5, 2);
$multa = round($precio * 0.10, 0); // 10% del alquiler
$precio_letras = numerosALetras($precio);
$deposito_letras = numerosALetras($deposito);
$multa_letras = numerosALetras($multa);

// IPC: fórmula actualización bimestral = dos últimos IPC + 1,5%
$ipc_txt = "índice ICL publicado por el Banco Central de la Republica Argentina y en caso de no existir por el indice de precios al consumidor, nivel general para la República Argentina, elaborado y publicado por el INDEC. La actualización bimestral se calcula con los dos últimos meses de IPC disponibles más el 1,5%.";

// Codeudores
$cod_txt = strtoupper($c['cod1_nom']) . ", D.N.I., N° " . preg_replace('/\D/', '', $c['cod1_dni'] ?? '') . 
    ", con domicilio en " . strtoupper($c['cod1_dom'] ?? '');
if (!empty($c['cod2_nom'])) {
    $cod_txt .= " y " . strtoupper($c['cod2_nom']) . ", D.N.I. N° " . preg_replace('/\D/', '', $c['cod2_dni'] ?? '') . 
        " con domicilio en " . strtoupper($c['cod2_dom'] ?? '');
}
$cod_txt .= ", denominado en adelante los Codeudores";

$detalle = strtoupper($c['prop_detalle'] ?? '');
$padron = $c['padron'] ?? '';
$plazo = (int)$c['plazo_meses'];
$destino = strtoupper($c['destino'] ?? 'VIVIENDA');

// Generar contenido en formato Word (HTML compatible con Microsoft Word)
ob_start();
?>
<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:w="urn:schemas-microsoft-com:office:word" xmlns="http://www.w3.org/TR/REC-html40">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
<meta name="ProgId" content="Word.Document">
<meta name="Generator" content="Microsoft Word 15">
<meta name="Originator" content="Microsoft Word 15">
<title>Contrato de Locación</title>
<!--[if gte mso 9]>
<xml>
<w:WordDocument>
<w:View>Print</w:View>
<w:Zoom>100</w:Zoom>
</w:WordDocument>
</xml>
<![endif]-->
<style>
@page { size: 21cm 29.7cm; margin: 2cm; }
body { font-family: 'Times New Roman', Times, serif; font-size: 12pt; text-align: justify; line-height: 1.5; margin: 0; }
p { margin: 0 0 12pt; }
.clausula { font-weight: bold; text-transform: uppercase; }
</style>
</head>
<body>

<p><strong><?= htmlspecialchars($dom_propiedad) ?> <?= htmlspecialchars($locatario_txt) ?></strong></p>

<p><strong>PARTES CONTRATANTES:</strong> Entre, por una parte <?= htmlspecialchars($loc_nom) ?>, <?= htmlspecialchars($loc_ident) ?> con domicilio en <?= htmlspecialchars($loc_dom) ?>, denominada en adelante la Locadora. Y por la otra parte <?= htmlspecialchars($locatario_txt) ?> con domicilio en <?= htmlspecialchars($dom_propiedad) ?><?= $inquilino_mail !== '' ? ', y mail para notificaciones ' . htmlspecialchars($inquilino_mail) : '' ?>, denominado en adelante el Locatario, convienen celebrar el presente Contrato de locación para vivienda el cual se sujetará a los términos y condiciones que se detallan a continuación.</p>

<p><span class="clausula">PRIMERA, objeto:</span> La Locadora, en su carácter de única y exclusiva propietaria, da al Locatario en locación y éste acepta, el inmueble identificado como <?= htmlspecialchars($dom_propiedad) ?>, Padrón provincial Nº <?= htmlspecialchars($padron) ?>, cuyas comodidades y especificaciones son las siguientes: <?= $detalle ?>. El Locatario recibe en este acto el Inmueble de conformidad y en tal carácter en el estado de conservación, obligándose a devolverlo al final del contrato en iguales condiciones de conservación aseo y funcionamiento, enduido y recién pintado con satinol. A tal efecto el locador requerirá el monto del costo de la mano de obra y materiales necesarios para la pintura al locatario, no pudiendo este ultimo realizarlo por su cuenta.</p>

<p><span class="clausula">SEGUNDA, plazo:</span> El contrato tendrá una duración de <?= $plazo ?> meses a partir del <?= $dia_ini ?> de <?= $mes_ini ?> de <?= $anio_ini ?> por lo que vencerá indefectiblemente el día <?= $dia_fin ?> de <?= $mes_fin ?> de <?= $anio_fin ?>, este período se denominará Plazo de Vigencia del contrato. Al término del Plazo de Vigencia, el Locatario deberá restituir el Inmueble a la Locadora totalmente libre de personas, bienes, o efectos personales y en las condiciones convenidas que se detallan más adelante. La obligación de devolver el inmueble libre de ocupantes, por parte del inquilino se operará de pleno derecho, por el mero vencimiento del plazo estipulado y sin necesidad de requerimiento alguno por la parte Locadora, bajo apercibimiento de la pertinente acción judicial de desalojo. La restitución del Inmueble y la firma del acta respectiva de restitución en conformidad por las partes contratantes podrá ser llevada a cabo personalmente por el Locatario, por el Codeudor o por la persona física que el Codeudor designe fehacientemente. La entrega por parte del locatario del inmueble locado, una vez vencido el plazo de locación estipulado, se considerará realizada únicamente mediante un acta de entrega y recepción del inmueble firmada por la locadora una vez cumplimentada todas las obligaciones asumidas en el presente contrato. De no mediar dicha acta, el locador podrá unilateralmente vencido los términos del contrato, disponer del inmueble sin que esto constituya una des obligación de parte del locatario de los términos del presente contrato.</p>

<p><span class="clausula">TERCERA, precio:</span> El precio convenido de la locación se fija en pesos <?= $precio_letras ?>. ($<?= number_format($precio, 0, ',', '.') ?>-) mensuales para el primer y segundo mes de vigencia del presente contrato. El precio convenido se actualizará bimestralmente según el <?= $ipc_txt ?>. Estos importes deberán ser abonados por períodos mensuales adelantado, hasta el día 10 de cada mes, en el domicilio de LAMADRID 377, PISO 4 OFF D, S. M. DE TUCUMAN, TUCUMAN, en horario de 8,30 a 11,30 hs, o donde la locadora lo indique en el futuro. Dicho precio NO incluye importe alguno por I.V.A., en caso en que se instituyese alícuota en el futuro por algún importe, el Locatario deberá sumarlo al precio del alquiler. Tampoco incluye expensas, las cuales serán a cargo exclusivo de la Locataria. El Precio se pacta por período de mes calendario completo aunque el Locatario no lo goce en esa forma, cualquiera fuere la causa de ello. El Precio establecido para el presente Contrato de Locación se computará por período de mes calendario completo aunque la restitución del mismo se realice antes de la finalización del mes que se halle en curso.</p>

<p><span class="clausula">CUARTA, tasas, impuestos, servicios y expensas:</span> Está a cargo del Locador el pago del Impuesto Inmobiliario Provincial que grava a la propiedad locada. Está a cargo de la Locataria, a partir de la fecha, el pago de todas las tasas y contribuciones que graven al Inmueble como así también el pago de todos los servicios, (Luz, Agua, Gas Natural, Teléfono, Video, Seguridad privada, etc.,) que se presten en el Inmueble, como así también el impuesto o servicio Municipal hoy denominado (C.I.S.I) y las expensas comunes ordinarias del edificio afectado al régimen de propiedad horizontal. Está a cargo de la Locataria el pago los recargos que por mora puedan llegar a generarse a partir de estas obligaciones como así también los gastos de una eventual reconexión de algún servicio. El Locatario entregará a la Locadora los comprobantes de pago originales, de cada uno de los Impuestos, tasas y Servicios que se encuentren a su cargo, dentro de los treinta (30) días siguientes al vencimiento del plazo del pago de la obligación que corresponda, debiendo el locatario hacer entrega de la totalidad de los comprobantes de pago como requisito para la firma del acta de restitución del inmueble a la finalización del presente contrato. La Locadora conservará los originales pagados y entregará copia con la constancia de su pago al Locatario. Abonar las expensas habilitara al inquilino a abonar el alquiler correspondiente. El Locador No está Obligado a recibir el importe de la locación, si previamente no se abonó la totalidad del monto de las expensas como así también entregado los comprobantes de pagos de las obligaciones asumidas por el Locatario (Luz, Agua, Gas Natural, Teléfono, Video, Seguridad privada, impuesto Municipal , etc.). Haber abonado el alquiler no implica haber saldado las obligaciones pendientes de expensas, impuestos y/o servicios de períodos anteriores o en curso por lo que la Locadora podrá exigir dichos pagos a los efectos de recibir futuros pagos de alquileres.</p>

<p><span class="clausula">QUINTA, destino, reglamento de copropiedad y administración:</span> Los ámbitos que se dan en locación serán destinados exclusivamente por la parte Locataria para <?= $destino ?>. Los aparatos de acondicionamiento aire, frio, calor o frio calor deberán ser autorizados por el locador. Queda prohibido al Locatario alterar el destino convenido para el Inmueble en la presente cláusula. Asimismo el Locatario declara conocer y respetar el reglamento de copropiedad y administración del consorcio de propiedades al que pertenece la propiedad locada, en el que expresamente se prohíbe la tenencia de animales. El cambio o variación del destino como así también el incumplimiento de las normas establecidas en el reglamento de copropiedad mencionado, o el incumplimiento de las cláusulas pactadas en el presente contrato, será considerado causa de rescisión de este contrato por culpa del locatario, con derecho de la parte Locadora de solicitar el desalojo del inmueble, con los daños y perjuicios pertinentes. En este acto el Locador hace entrega al Locatario las llave de la unidad locada, quien podrá hacer si lo cree conveniente un cambio en la combinación de la cerradura de la puerta de entrada para su mayor seguridad, por lo que el Locador no asume responsabilidad alguna por robo.</p>

<p><span class="clausula">SEXTA, conservación del inmueble:</span> El Locatario recibe el Inmueble en buen estado de funcionamiento, conservación y aseo, obligándose a mantenerlo y restituirlo al vencimiento del Plazo de Vigencia o cuando la devolución del Inmueble sea jurídicamente exigible, en el mismo estado en el que le fuera entregado. Será a exclusivo cargo de la parte Locataria la conservación del inmueble objeto del contrato. No le será permitido efectuar ningún tipo de transformación o modificación, salvo autorización escrita de la parte Locadora, debiendo quedar en beneficio de la propiedad cualquier mejora que se efectúe. La violación de esta prohibición será causal de desalojo. Al vencimiento de la locación el inquilino deberá restituir el inmueble en las mismas condiciones de pintura en que lo recibe, ó sea enduido y recién pintado con satinol blanco.</p>

<p><span class="clausula">SEPTIMA, transferencia:</span> Queda expresamente prohibido a la parte Locataria ceder parcial o totalmente el contrato o subalquilar los ámbitos que se arriendan, quedando incluida en la prohibición la cesión de la mera tenencia precaria del inmueble, haciendo la parte Locataria expresa renuncia a la opción prevista en el Código Civil.</p>

<p><span class="clausula">OCTAVA, acción resolutoria y ejecución de alquileres:</span> La mora del Locatario se producirá en todos los casos en forma automática y de pleno derecho, por el mero vencimiento del plazo establecido para el cumplimiento de la respectiva obligación. La falta de pago de dos mensualidades consecutivas o el incumplimiento del contrato en cualquiera de sus cláusulas, sin necesidad de intimación previa, dará derecho a la parte LOCADORA a dar por rescindido el presente contrato y el desalojo inmediato del inquilino, reservándose el derecho de la acción judicial por los daños y perjuicios pertinentes, como así también por toda otra suma de dinero emergente del presente contrato. Si al vencimiento del contrato no se efectuara la efectiva devolución del inmueble el locatario deberá pagar independientemente de la acción de desalojo y los daños y perjuicios que pudieran corresponder una multa diaria de pesos <?= $multa_letras ?>. ($<?= number_format($multa, 0, ',', '.') ?>), hasta la efectiva devolución del inmueble., más el ultimo valor de alquiler mensual pactado por mes hasta la efectiva devolución del inmueble. Al finalizar el contrato la entrega de las llaves o de la propiedad solo se justificará por escrito emanado del Locador o su representante, no admitiéndose otro medio de prueba. Si el Locatario consigna las llaves, adeudará al LOCADOR el alquiler fijado en ese momento, con mas las actualizaciones previstas hasta el día que el Locador acepte la consignación o se le dé posesión del inmueble sin que esto menoscabe el derecho del Locador de exigir el pago de las penalidades pactadas por esta u otra circunstancia.</p>

<p><span class="clausula">NOVENA, mora en el pago:</span> Queda expresamente pactado entre las partes que los alquileres que no se abonen a su vencimiento, devengarán un interés moratorio equivalente al 1,0 % del alquiler por día de atraso, o sea partir del día 11 de cada mes. Todo gasto de gestión de cobro generado por mora (Cartas documentos, Honorarios profesionales, impuestos, etc.) en el pago de las obligaciones asumidas en el presente contrato estarán a cargo de la locataria y formarán parte de la obligación monetarias del mes que generó dichos gastos. El importe resultante deberá ser abonado en el momento del pago del alquiler del mes en mora reclamado.</p>

<p><span class="clausula">DECIMA, rescisión:</span> La parte Locataria podrá, transcurrido los primeros seis meses de la relación locativa, rescindir la contratación debiendo notificar en forma fehaciente su decisión a la parte Locadora con una antelación mínima de 30 días de la fecha en que se reintegrara lo arrendado, en caso de hacer uso de la opción resolutoria deberá pagar la suma equivalente a un mes y medio de alquiler si la rescisión se produce dentro del primer año de contrato, y de un mes de alquiler si se produce después del primer año, en concepto de indemnización. Para ejercer este derecho la Locataria deberá estar al día con todas las obligaciones emergentes del presente contrato para luego firmar las partes en conformidad el acta de entrega y recepción de la unidad locada.</p>

<p><span class="clausula">DÉCIMA PRIMERA, inspección y reparaciones:</span> Es obligación del locatario permitir al locador las veces que este lo estime conveniente y oportuno, el libre acceso al inmueble, con el fin de realizar inspecciones y/o reparaciones en la unidad locada en beneficio de la misma, del edificio u otra unidad del mismo. Para constatar el fiel cumplimiento de las obligaciones asumida en el presente contrato, etc. Esta inspección se realizará ya sea por si o por interpósita persona, previa notificación al locatario de la hora, el día e identificación de persona autorizada a tal efecto.</p>

<p><span class="clausula">DECIMA SEGUNDA, homologación:</span> Se conviene que el presente contrato podrá ser sometido a Homologación Judicial por cualquiera de las partes, dando a sus disposiciones el carácter de sentencia firme, cuyo cumplimiento podrá ser requerido en los autos en que hubiere sido homologado, por vía de sentencia.</p>

<p><span class="clausula">DECIMO TERCERA, tribunales competentes y domicilios especiales:</span> Los Tribunales Ordinarios de San Miguel de Tucumán serán competentes para entender en cualquier cuestión que surja entre las partes con motivo del presente contrato, renunciando a otro fuero o jurisdicción que por las cosas y/o personas pudiera corresponder. Se fija domicilio en el indicado por las partes contratantes.</p>

<p><span class="clausula">DECIMO CUARTA, depósito de garantía:</span> Para garantizar el pago de servicios, impuestos y demás obligaciones que quedaran pendientes al finalizar la locación el locatario entregará la suma de pesos <?= $deposito_letras ?>. ($<?= number_format($deposito, 0, ',', '.') ?>) en concepto de depósito de garantía conforme a ley, dicho importe no devengará interés ni podrá ser afectado al pago de alquiler y le será devuelto a el Locatario una vez que se entregue el inmueble desocupado y no existan obligaciones pendientes de impuestos, servicios, pintura, reparación e higiene prevista en el presente contrato, caso contrario se afectará la suma necesaria para el pago de las obligaciones pendiente. Por dicho monto se emitirá recibo de recepción. El acta de restitución del inmueble firmada en conformidad entre las partes será suficiente recibo de restitución total o parcial del dinero entregado como garantía.</p>

<p><span class="clausula">DECIMO QUINTA, codeudor:</span> <?= htmlspecialchars($cod_txt) ?>, interiorizado en todo del contenido del presente contrato, se constituyen en codeudores solidario, liso y llano y en principal pagador de todas las obligaciones que contrae el locatario, hasta tanto entregue el inmueble desocupado y no existan obligaciones pendientes, inclusive el pago de honorarios y gastos de juicios en caso de promoverse por cobros de alquileres, desalojo, daños y perjuicios, etc.; quedando establecida la solidaridad de la parte codeudora por lo que las acciones que existiesen podrán interponerse contra uno, cualquiera de ellos, o contra todos conjunta, separada o alternadamente, sin que el hecho de hacerlo contra uno perjudique su accionar para con el otro, no existiendo obligación de comunicarse a la garantía los atrasos incurridos o incumplimientos ocasionados, renunciando al beneficio de exclusión y división y al fuero federal.</p>

<p><span class="clausula">DECIMO SEXTA, gastos y sellados:</span> Los gastos de legalización y sellados del presente contrato serán soportados por la Parte Locataria. Los gastos de comunicaciones e intimaciones por pagos no efectuados en los vencimientos correspondientes serán sumados al precio del alquiler correspondiente y formarán parte del mismo. Los gastos judiciales y honorarios que pudieran originarse por incumplimiento de la parte Locataria, serán a cargo de la misma, aunque no haya condenación judicial de costas. En la ciudad de San Miguel de Tucumán, a los <?= $dia_firma ?> de <?= $mes_firma ?> de <?= $anio_firma ?>, se firman dos ejemplares de Ley de un mismo tenor y a un solo efecto.</p>

</body>
</html>
<?php
$html = ob_get_clean();

// Carpeta desde config_contrato.php (Documents/contratos de alquiler o fallback)
$docs = $RUTA_CONTRATOS;
if (!is_dir($docs)) {
    @mkdir($docs, 0755, true);
}

$nombre_archivo = 'Contrato_' . preg_replace('/[^a-zA-Z0-9_\-]/', '_', $c['inq1_nom']) . '_' . date('Y-m-d_His') . '.doc';
$ruta_completa = $docs . DIRECTORY_SEPARATOR . $nombre_archivo;

$guardado_ok = false;
if (is_dir($docs) && is_writable($docs)) {
    $guardado_ok = (file_put_contents($ruta_completa, "\xEF\xBB\xBF" . $html) !== false);
}

// Encabezados para descarga como Word
header("Content-Type: application/vnd.ms-word; charset=UTF-8");
header("Content-Disposition: attachment; filename=\"" . $nombre_archivo . "\"");
header("Pragma: no-cache");
header("Expires: 0");
header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
echo "\xEF\xBB\xBF" . $html;
?>
