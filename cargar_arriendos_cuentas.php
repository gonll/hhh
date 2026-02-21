<?php
/**
 * Evalúa el cronograma de arriendos y crea en cuentas del propietario los movimientos
 * correspondientes a cada fecha de cobro. Se ejecuta al cargar arriendos.php.
 * Crea entradas con comprobante PRECIO AZUCAR, referencia A DEFINIR, monto 0.
 */
if (!isset($conexion)) return;
include_once __DIR__ . '/crear_tabla_arriendos.php';
include_once __DIR__ . '/crear_tabla_cuentas_arriendo.php';

$anio_actual = (int)date('Y');
$hoy = date('Y-m-d');

$arriendos = mysqli_query($conexion, "
    SELECT a.id, a.propietario_id, a.descripcion_finca, a.fecha_cobro_1, a.fecha_cobro_2,
           a.kilos_fecha_1, a.kilos_fecha_2, a.iva_porcentaje, a.porcentaje_otros
    FROM arriendos a
    WHERE a.fecha_vencimiento_contrato IS NULL OR a.fecha_vencimiento_contrato >= '$hoy'
");
if (!$arriendos) return;

while ($ar = mysqli_fetch_assoc($arriendos)) {
    $propietario_id = (int)$ar['propietario_id'];
    $arriendo_id = (int)$ar['id'];
    $finca = mysqli_real_escape_string($conexion, trim($ar['descripcion_finca'] ?? ''));
    if (strlen($finca) > 80) $finca = substr($finca, 0, 77) . '...';
    $iva = isset($ar['iva_porcentaje']) && $ar['iva_porcentaje'] !== null ? (float)$ar['iva_porcentaje'] : 21;
    $dto = isset($ar['porcentaje_otros']) && $ar['porcentaje_otros'] !== null ? (float)$ar['porcentaje_otros'] : 0;

    foreach ([1 => ['fecha' => $ar['fecha_cobro_1'], 'kilos' => $ar['kilos_fecha_1']], 2 => ['fecha' => $ar['fecha_cobro_2'], 'kilos' => $ar['kilos_fecha_2']]] as $fn => $datos) {
        if (empty($datos['fecha']) || $datos['kilos'] === null || (float)$datos['kilos'] <= 0) continue;
        $parts = date_parse($datos['fecha']);
        if (!$parts || !$parts['month'] || !$parts['day']) continue;
        $fecha_cobro = sprintf('%04d-%02d-%02d', $anio_actual, $parts['month'], $parts['day']);

        $kilos = (float)$datos['kilos'];
        $concepto = "ARRIENDO - $finca - $kilos KILOS - IVA " . number_format($iva, 0) . "% - DTO " . number_format($dto, 0) . "%";
        $concepto = mysqli_real_escape_string($conexion, strtoupper($concepto));

        /* No crear si ya existe (pendiente PRECIO AZUCAR o ya liquidado PRECIO DE LA BOLSA) */
        $existe = mysqli_query($conexion, "SELECT 1 FROM cuentas WHERE usuario_id = $propietario_id AND arriendo_id = $arriendo_id AND arriendo_fecha = $fn LIMIT 1");
        if ($existe && mysqli_num_rows($existe) > 0) continue;

        mysqli_query($conexion, "INSERT INTO cuentas (usuario_id, fecha, concepto, comprobante, referencia, monto, arriendo_id, arriendo_fecha) 
            VALUES ($propietario_id, '$fecha_cobro', '$concepto', 'PRECIO AZUCAR', 'A DEFINIR', 0, $arriendo_id, $fn)");
    }
}
