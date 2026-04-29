<?php
/**
 * Plantilla Word/HTML: locacion comercial BGH1050 — OFICINAS Y LOCALES COMERCIALES.
 * Variables esperadas desde generar_word_contrato.php.
 */
if (!isset($c)) {
    return;
}
$padron_txt = trim((string)($padron ?? ''));
// Evita duplicar "padron origen" cuando ese texto ya viene desde la BD.
$padron_txt = preg_replace('/^\s*padr[oó]n(\s+de)?\s+origen\s*/iu', '', $padron_txt);
if ($padron_txt === null) {
    $padron_txt = trim((string)($padron ?? ''));
}
?>
<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:w="urn:schemas-microsoft-com:office:word" xmlns="http://www.w3.org/TR/REC-html40">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
<meta name="ProgId" content="Word.Document">
<title>Contrato de Locacion BGH1050</title>
<style>
@page { size: 21cm 29.7cm; margin: 2cm; }
body, p, div { font-family: Arial, sans-serif; font-size: 10pt; text-align: justify; line-height: 1.45; margin: 0; }
p { margin: 0 0 10pt; }
.clausula { font-weight: bold; text-transform: uppercase; }
.titulo { text-align: center; font-weight: bold; font-size: 11pt; margin-bottom: 8pt; }
.subtit { text-align: center; font-weight: bold; font-size: 10pt; margin-bottom: 16pt; }
</style>
</head>
<body>

<p class="titulo">CONTRATO DE LOCACION</p>
<p class="subtit">TIPOLOGIA: OFICINAS Y LOCALES COMERCIALES</p>

<p><strong>PARTES CONTRATANTES:</strong> Entre, FEDERICO GARCIA HAMILTON, DNI N&deg; 16.691.840 en su caracter de fiduciario de Fideicomiso FEISIN, con domicilio en Viamonte 395, San Miguel de Tucuman, Tucuman, denominado en adelante EL LOCADOR, por una parte; y por otra parte, <?= htmlspecialchars($locatario_partes) ?>, CUIT <?= htmlspecialchars($inq1_cuit_txt) ?>, con domicilio en calle <?= htmlspecialchars($dom_propiedad) ?> de la Provincia de Tucuman, en adelante denominada LA LOCATARIA; convienen en celebrar el presente contrato de locacion comercial, el que se regira por las clausulas que se detallan a continuacion.</p>

<p><span class="clausula">PRIMERA. OBJETO:</span> 1.- El Locador, en su caracter de unico y exclusivo propietario da en locacion a La Locataria, esta acepta y la recibe en tal caracter, la oficina N&ordm; <?= htmlspecialchars($detalle !== '' ? $detalle : '…………………………………….') ?>, unidad perteneciente a las Galerias comercialmente denominadas "MIL50", ubicada en calle Salas y Valdez 1050 de la ciudad de Yerba Buena, Provincia de Tucuman, dicha oficina cuenta con una superficie propia de ……………, con padron de origen N&ordm; <?= htmlspecialchars($padron_txt) ?>; la unidad locada representa un ……….% del total de la superficie de la mayor extension. 2.- El Locador expresa que el inmueble locado se encuentra en estado de servir al uso y goce convenido, con sus carpinterias nuevas, carpeta lisa para colocacion de piso flotante, cielorraso, instalacion electrica y revoque fino, encontrandose a estrenar, debiendo la locataria restituir el inmueble en perfecto estado, lo cual es aceptado de conformidad.</p>

<p><span class="clausula">SEGUNDA. DESTINO:</span> El Establecimiento que se da en locacion sera destinado por La Locataria para destino comercial. Queda expresamente prohibido destinar el inmueble a cualquier actividad comercial que implique cocina, o actividades de gastronomia, manipulacion de hornos y artefactos de cocina, sin contar con una autorizacion expresa del LOCADOR. La gestion y la obtencion de las respectivas habilitaciones municipales segun rubro comercial, seran por exclusiva cuenta, riesgo y responsabilidad de La Locataria, quedando La Locadora exonerada de toda responsabilidad al respecto. El cambio o variacion de este destino, sin autorizacion expresa, fehaciente y por escrito de La Locadora, sera causa inapelable de rescision imputable a La Locataria. Asimismo el locador expresamente deja aclarado que no garantiza exclusividad comercial de ningun rubro dentro de las Galerias.</p>

<p><span class="clausula">TERCERA. DURACION:</span> El contrato tendra una duracion de <?= (int) $plazo ?> (<?= htmlspecialchars($plazo_letras) ?>) meses comenzando a regir a partir del dia <?= (int) $dia_ini ?> de <?= htmlspecialchars($mes_ini) ?> de <?= htmlspecialchars($anio_ini) ?>, o sea que vencera indefectiblemente el dia <?= (int) $dia_fin ?> de <?= htmlspecialchars($mes_fin) ?> de <?= htmlspecialchars($anio_fin) ?>. <strong>RESTITUCION:</strong> La obligacion de restituir el inmueble libre de ocupantes por parte de La Locataria, se operara de pleno derecho por el mero vencimiento del plazo estipulado, y sin necesidad de requerimiento alguno por parte de La Locadora, bajo apercibimiento de iniciar la pertinente accion judicial de desalojo. Al termino del Plazo de Vigencia, el Locatario debera restituir el Inmueble a la Locadora totalmente libre de bienes, personas o efectos y en las condiciones convenidas que se detallan mas adelante. La restitucion del Inmueble y la firma del acta respectiva de restitucion en conformidad por las partes contratantes podra ser llevada a cabo personalmente por el Locatario, co-locatario, por el Codeudor o por la persona fisica que el Codeudor designe fehacientemente.</p>

<p><span class="clausula">CUARTA. PRECIO DEL ALQUILER:</span> El precio de esta locacion se fija para todo el contrato en la suma total de PESOS <?= htmlspecialchars($precio_letras) ?> ($<?= number_format($precio, 0, ',', '.') ?>) mensuales, mas expensas. Las partes manifiestan que realizan este acuerdo de absoluta buena fe y conforme lo dispuesto por el Art. 1061 del Codigo Civil y Comercial de la Nacion, comprometiendose a mantener actualizado el precio del alquiler durante todo el plazo de duracion de la relacion locativa. El alquiler se actualizara trimestralmente, segun la variacion trimestral que arroje el indice de precios al consumidor (IPC), de no existir dicho indice, podra tomarse indice ICL o similar vigente.</p>

<p><span class="clausula">QUINTA. LUGAR Y TIEMPO DE PAGO:</span> Los alquileres se abonaran por adelantado desde el dia 1 al 10 de cada periodo mensual en el domicilio que el Locador indique fehacientemente. Queda establecido que para el caso en que La Locadora designe un ADMINISTRADOR para percibir los alquileres, este tendra facultades para cursar recibos y todas las comunicaciones que hagan al cumplimiento del contrato, incluso las intimaciones que por ley correspondieran formularse, aceptando La Locataria la validez de las mismas.</p>

<p><span class="clausula">SEXTA. MORA:</span> El alquiler que no fuera abonado por La Locataria hasta el dia 10 de cada mes, devengara un interes a favor de La Locadora de 2% (dos por ciento) por cada dia de demora sobre el monto del alquiler, sera exigible desde el dia 11 y computara sobre el precio del alquiler del mes completo. En caso que La Locataria adeude dos periodos de alquiler del establecimiento locado, La Locadora queda habilitada a iniciar accion judicial de desalojo.</p>

<p><span class="clausula">SEPTIMA. CONSERVACION:</span> La Locataria puede usar y gozar del establecimiento locado conforme a derecho y exclusivamente para el destino correspondiente. Debe mantener el establecimiento y conservarlo en el estado en que lo recibio, respondiendo por cualquier deterioro causado al establecimiento locado, incluso por visitantes ocasionales, pero no por accion de La Locadora.</p>

<p><span class="clausula">OCTAVA. MODIFICACIONES. MEJORAS:</span> Salvo expresa autorizacion del locador, se acuerda la prohibicion de perforar suelo o puerta del inmueble locado como asi tambien queda vedado plotear el frente de la propiedad. Toda modificacion de fachada, ornamentacion, carteleria, o mejora al establecimiento locado requerira autorizacion previa y expresa del locador.</p>

<p><span class="clausula">NOVENA. FACHADA DEL INMUEBLE LOCADO:</span> La locataria presta expresa conformidad y se compromete a dar cumplimiento con los lineamientos esteticos, de diseno y de fachada que comprometan a la unidad locada, a su entorno y galerias, conforme indique el locador o la persona que este designe.</p>

<p><span class="clausula">DECIMA. REPARACIONES - USO Y GOCE DEL ESTABLECIMIENTO LOCADO:</span> En caso de reparaciones no imputables a La Locataria, La Locadora podra efectuar la reparacion que exija el deterioro y la locataria no podra negarle el acceso al inmueble si el mismo fuera requerido a tal efecto. Las reparaciones urgentes deberan informarse de forma comprobable y detallada.</p>

<p><span class="clausula">DECIMA PRIMERA. CARGAS:</span> Sera a cargo de La Locataria el pago de servicios, impuestos inherentes a su actividad, impuesto municipal (CISI) y expensas ordinarias. La Locadora se hara cargo del impuesto inmobiliario (DGR) y expensas extraordinarias. El locatario debera hacer entrega de la totalidad de los comprobantes de pago como requisito para la firma del acta de restitucion del inmueble.</p>

<p><span class="clausula">DECIMA SEGUNDA. TRASMISIONES:</span> Queda expresamente PROHIBIDO cualquier sublocacion y/o trasmision, parcial o total, transitoria o permanente, gratuita u onerosa y en general, a todo titulo y el cambio del destino.</p>

<p><span class="clausula">DECIMA TERCERA. FACULTAD DE VISITA Y VIGILANCIA:</span> La Locadora tiene derecho a inspeccionar el estado del establecimiento locado y puede para ello, por si o por las personas que designe, visitar el inmueble con aviso previo minimo de 24 horas.</p>

<p><span class="clausula">DECIMA CUARTA. RESTITUCION DE LA COSA:</span> La obligacion de restituir el establecimiento locado al termino de la presente locacion se operara de pleno derecho por el mero vencimiento del plazo, debiendo La Locataria estar al dia con todas sus obligaciones y entregar el inmueble en condiciones convenidas.</p>

<p><span class="clausula">DECIMA QUINTA. SEGUROS:</span> La Locataria se obliga a contratar dentro de los 30 dias de firma del presente contrato seguros contra incendio, responsabilidad civil y accidentes de trabajo de su personal, entregando copias y constancias de pago a La Locadora.</p>

<p><span class="clausula">DECIMA SEXTA. RESOLUCION DE CONTRATO:</span> La falta de pago de dos mensualidades consecutivas o el incumplimiento de cualquiera de las clausulas producira la resolucion del contrato, facultando a La Locadora para promover accion judicial de desalojo y cobro de sumas adeudadas.</p>

<p><span class="clausula">DECIMA SEPTIMA. CLAUSULA DE INDEMNIDAD:</span> La locataria se compromete a mantener indemne al locador frente a cualquier reclamo administrativo, judicial o de responsabilidad que tenga origen en la actividad de la locataria.</p>

<p><span class="clausula">DECIMA OCTAVA. GARANTES. FIANZA:</span> Afianzando las obligaciones emergentes del contrato, asume como fiadora y principal pagadora <?= htmlspecialchars($cod1_fianza_txt) ?>.</p>

<p><span class="clausula">DECIMA NOVENA. DEPOSITO DINERARIO EN GARANTIA:</span> Para garantizar obligaciones accesorias pendientes, La Locataria entrega en este acto la suma en pesos de <?= htmlspecialchars($deposito_letras) ?> ($<?= number_format($deposito, 0, ',', '.') ?>), la cual no devengara intereses y sera devuelta al termino de la locacion, verificado el cumplimiento integral del contrato.</p>

<p><span class="clausula">VIGESIMA. ACTOS EXCLUSIVAMENTE ESCRITOS:</span> Las partes declaran que todos los actos juridicos vinculados a esta locacion se perfeccionaran unicamente por escrito, no admitiendose pactos verbales.</p>

<p><span class="clausula">VIGESIMA PRIMERA. TRIBUTOS:</span> El pago de impuesto de sellos, timbrado o de otra indole que gravare este contrato sera asumido en su totalidad por La Locataria.</p>

<p><span class="clausula">VIGESIMA SEGUNDA. LITIGIOS - TRIBUNAL COMPETENTE:</span> Para litigios, ambas partes y la fiadora se someten a la competencia de los tribunales ordinarios de San Miguel de Tucuman, renunciando a cualquier otro fuero.</p>

<p><span class="clausula">VIGESIMA TERCERA. DOMICILIOS CONSTITUIDOS - REAL:</span> Las partes constituyen sus domicilios reales en los mencionados en este contrato y se obligan a notificar cualquier cambio de domicilio en forma fehaciente.</p>

<p>Previa lectura y ratificacion, se suscriben de conformidad dos (2) ejemplares de igual tenor y a un mismo efecto.</p>
<p style="margin-top: 24pt;">En la ciudad de San Miguel de Tucuman, a los <?= (int) $dia_firma ?> de <?= htmlspecialchars($mes_firma) ?> de <?= htmlspecialchars($anio_firma) ?>.</p>

</body>
</html>
