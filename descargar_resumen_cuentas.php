<?php
include 'db.php';
include 'verificar_sesion.php';

date_default_timezone_set('America/Argentina/Buenos_Aires');

// Misma consulta que resumen_cuentas.php
$sql = "SELECT p.propiedad, p.consorcio, p.propietario_id,
        prop.apellido AS nombre_propietario,
        u.apellido AS nombre_inquilino,
        u2.apellido AS nombre_inquilino2,
        a.inquilino1_id,
        a.inquilino2_id,
        (SELECT COALESCE(SUM(monto), 0) FROM cuentas WHERE usuario_id = a.inquilino1_id) AS saldo_inq1,
        (SELECT COALESCE(SUM(monto), 0) FROM cuentas WHERE usuario_id = a.inquilino2_id) AS saldo_inq2,
        (SELECT COALESCE(SUM(monto), 0) FROM cuentas WHERE usuario_id = p.propietario_id) AS saldo_prop
        FROM propiedades p
        LEFT JOIN usuarios prop ON p.propietario_id = prop.id
        LEFT JOIN alquileres a ON a.propiedad_id = p.propiedad_id AND a.estado = 'VIGENTE'
        LEFT JOIN usuarios u ON a.inquilino1_id = u.id
        LEFT JOIN usuarios u2 ON a.inquilino2_id = u2.id
        ORDER BY p.consorcio ASC, p.propiedad ASC";
$resultado = mysqli_query($conexion, $sql);

$filas = [];
while ($f = mysqli_fetch_assoc($resultado)) {
    $esta_alquilada = !empty($f['inquilino1_id']);
    if ($esta_alquilada) {
        $nombre = trim($f['nombre_inquilino'] ?? '');
        if (!empty($f['nombre_inquilino2'])) $nombre .= ' / ' . trim($f['nombre_inquilino2']);
        $saldo = (float)($f['saldo_inq1'] ?? 0);
        if (!empty($f['inquilino2_id'])) $saldo += (float)($f['saldo_inq2'] ?? 0);
    } else {
        $nombre = trim($f['nombre_propietario'] ?? '');
        $saldo = (float)($f['saldo_prop'] ?? 0);
    }
    $filas[] = [
        'consorcio' => $f['consorcio'] ?? '',
        'propiedad' => $f['propiedad'] ?? '',
        'nombre' => $nombre ?: '-',
        'saldo' => $saldo
    ];
}

$res_total = mysqli_query($conexion, "SELECT COALESCE(SUM(monto), 0) AS total FROM cuentas WHERE usuario_id != 1");
$total_general = ($res_total && $r = mysqli_fetch_assoc($res_total)) ? (float)$r['total'] : 0;

$fecha_archivo = date('Y-m-d_His');
$nombre_archivo = "resumen_cuentas_$fecha_archivo.csv";

header('Content-Type: text/csv; charset=utf-8');
header("Content-Disposition: attachment; filename=\"$nombre_archivo\"");
header('Pragma: no-cache');
header('Expires: 0');

echo "\xEF\xBB\xBF";

$sep = ';';

function escaparCsv($texto) {
    $texto = str_replace('"', '""', $texto);
    if (strpos($texto, ';') !== false || strpos($texto, ',') !== false || strpos($texto, '"') !== false || strpos($texto, "\n") !== false) {
        return '"' . $texto . '"';
    }
    return $texto;
}

echo escaparCsv('RESUMEN DE CUENTAS') . "\n";
echo escaparCsv('Fecha: ' . date('d/m/Y H:i:s') . ' (Argentina)') . "\n";
echo "\n";

echo escaparCsv('Consorcio') . $sep . escaparCsv('Propiedad') . $sep . escaparCsv('Inquilino / Propietario') . $sep . escaparCsv('Saldo') . "\n";

foreach ($filas as $f) {
    echo escaparCsv($f['consorcio']) . $sep . 
         escaparCsv($f['propiedad']) . $sep . 
         escaparCsv($f['nombre']) . $sep . 
         escaparCsv(number_format($f['saldo'], 2, ',', '.')) . "\n";
}

echo "\n";
echo $sep . $sep . escaparCsv('TOTAL GENERAL (sin Caja)') . $sep . escaparCsv(number_format($total_general, 2, ',', '.')) . "\n";

exit;
