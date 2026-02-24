<?php
include 'db.php';
include 'verificar_sesion.php';

date_default_timezone_set('America/Argentina/Buenos_Aires');

$sql = "SELECT p.propiedad, p.consorcio, p.propietario_id, COALESCE(p.porcentaje, 0) AS porcentaje,
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

$por_usuario = [];
while ($f = mysqli_fetch_assoc($resultado)) {
    $porcentaje = (float)($f['porcentaje'] ?? 0);
    $consorcio = $f['consorcio'] ?? '';
    $propiedad = $f['propiedad'] ?? '';

    if (!empty($f['inquilino1_id'])) {
        $inq1_id = (int)$f['inquilino1_id'];
        $saldo1 = (float)($f['saldo_inq1'] ?? 0);
        $nombre1 = trim($f['nombre_inquilino'] ?? '');
        if (!empty($inq1_id) && $inq1_id != 1) {
            if (!isset($por_usuario[$inq1_id]) || $porcentaje > ($por_usuario[$inq1_id]['porcentaje'] ?? 0)) {
                $por_usuario[$inq1_id] = ['consorcio' => $consorcio, 'propiedad' => $propiedad, 'nombre' => $nombre1 ?: '-', 'saldo' => $saldo1, 'porcentaje' => $porcentaje];
            }
        }
        if (!empty($f['inquilino2_id'])) {
            $inq2_id = (int)$f['inquilino2_id'];
            $saldo2 = (float)($f['saldo_inq2'] ?? 0);
            $nombre2 = trim($f['nombre_inquilino2'] ?? '');
            if ($inq2_id != 1) {
                if (!isset($por_usuario[$inq2_id]) || $porcentaje > ($por_usuario[$inq2_id]['porcentaje'] ?? 0)) {
                    $por_usuario[$inq2_id] = ['consorcio' => $consorcio, 'propiedad' => $propiedad, 'nombre' => $nombre2 ?: '-', 'saldo' => $saldo2, 'porcentaje' => $porcentaje];
                }
            }
        }
    } else {
        $prop_id = (int)($f['propietario_id'] ?? 0);
        if ($prop_id > 0 && $prop_id != 1) {
            $nombre = trim($f['nombre_propietario'] ?? '');
            $saldo = (float)($f['saldo_prop'] ?? 0);
            if (!isset($por_usuario[$prop_id]) || $porcentaje > ($por_usuario[$prop_id]['porcentaje'] ?? 0)) {
                $por_usuario[$prop_id] = ['consorcio' => $consorcio, 'propiedad' => $propiedad, 'nombre' => $nombre ?: '-', 'saldo' => $saldo, 'porcentaje' => $porcentaje];
            }
        }
    }
}

$filas = array_values($por_usuario);
usort($filas, function($a, $b) {
    $c = strcmp($a['consorcio'], $b['consorcio']);
    return $c !== 0 ? $c : strcmp($a['propiedad'], $b['propiedad']);
});

$total_general = 0;
foreach ($filas as $f) {
    $total_general += $f['saldo'];
}

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
