<?php
include 'db.php';
include 'verificar_sesion.php';

date_default_timezone_set('America/Argentina/Buenos_Aires');

// Obtener todas las propiedades con porcentaje, inquilino/propietario y saldos
$sql = "SELECT p.propiedad_id, p.propiedad, p.consorcio, p.propietario_id, COALESCE(p.porcentaje, 0) AS porcentaje,
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

// Agrupar por usuario: para cada usuario, guardar la propiedad de mayor %
$por_usuario = []; // usuario_id => ['consorcio','propiedad','nombre','saldo','porcentaje']

while ($f = mysqli_fetch_assoc($resultado)) {
    $porcentaje = (float)($f['porcentaje'] ?? 0);
    $consorcio = $f['consorcio'] ?? '';
    $propiedad = $f['propiedad'] ?? '';

    if (!empty($f['inquilino1_id'])) {
        // Alquilada: inquilino(s)
        $inq1_id = (int)$f['inquilino1_id'];
        $saldo1 = (float)($f['saldo_inq1'] ?? 0);
        $nombre1 = trim($f['nombre_inquilino'] ?? '');
        if (!empty($inq1_id) && $inq1_id != 1) {
            $key = 'inq_' . $inq1_id;
            if (!isset($por_usuario[$key]) || $porcentaje > ($por_usuario[$key]['porcentaje'] ?? 0)) {
                $por_usuario[$key] = ['consorcio' => $consorcio, 'propiedad' => $propiedad, 'nombre' => $nombre1 ?: '-', 'saldo' => $saldo1, 'porcentaje' => $porcentaje];
            }
        }
        if (!empty($f['inquilino2_id'])) {
            $inq2_id = (int)$f['inquilino2_id'];
            $saldo2 = (float)($f['saldo_inq2'] ?? 0);
            $nombre2 = trim($f['nombre_inquilino2'] ?? '');
            if ($inq2_id != 1) {
                $key = 'inq_' . $inq2_id;
                if (!isset($por_usuario[$key]) || $porcentaje > ($por_usuario[$key]['porcentaje'] ?? 0)) {
                    $por_usuario[$key] = ['consorcio' => $consorcio, 'propiedad' => $propiedad, 'nombre' => $nombre2 ?: '-', 'saldo' => $saldo2, 'porcentaje' => $porcentaje];
                }
            }
        }
    } else {
        // No alquilada: propietario
        $prop_id = (int)($f['propietario_id'] ?? 0);
        if ($prop_id > 0 && $prop_id != 1) {
            $nombre = trim($f['nombre_propietario'] ?? '');
            $saldo = (float)($f['saldo_prop'] ?? 0);
            $key = 'prop_' . $prop_id;
            if (!isset($por_usuario[$key]) || $porcentaje > ($por_usuario[$key]['porcentaje'] ?? 0)) {
                $por_usuario[$key] = ['consorcio' => $consorcio, 'propiedad' => $propiedad, 'nombre' => $nombre ?: '-', 'saldo' => $saldo, 'porcentaje' => $porcentaje];
            }
        }
    }
}

// Ordenar por consorcio, propiedad
$filas = array_values($por_usuario);
usort($filas, function($a, $b) {
    $c = strcmp($a['consorcio'], $b['consorcio']);
    return $c !== 0 ? $c : strcmp($a['propiedad'], $b['propiedad']);
});

// Total general (sin Caja)
$res_total = mysqli_query($conexion, "SELECT COALESCE(SUM(monto), 0) AS total FROM cuentas WHERE usuario_id != 1");
$total_general = ($res_total && $r = mysqli_fetch_assoc($res_total)) ? (float)$r['total'] : 0;

$imprimir = isset($_GET['imprimir']) && $_GET['imprimir'] == '1';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Resumen de Cuentas</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; font-size: 14px; }
        .container { background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); max-width: 1000px; margin: 0 auto; }
        h1 { text-align: center; color: #007bff; margin-bottom: 8px; }
        .fecha { text-align: center; color: #666; margin-bottom: 16px; font-size: 12px; }
        .botones { text-align: center; margin-bottom: 20px; }
        .botones button, .botones a { background: #007bff; color: white; border: none; padding: 10px 20px; border-radius: 4px; cursor: pointer; font-size: 14px; margin: 0 5px; text-decoration: none; display: inline-block; }
        .botones button:hover, .botones a:hover { background: #0056b3; color: white; }
        table { width: 100%; border-collapse: collapse; }
        th { background: #007bff; color: white; padding: 10px 8px; text-align: left; font-weight: bold; font-size: 12px; }
        td { padding: 8px; border-bottom: 1px solid #ddd; font-size: 13px; white-space: nowrap; }
        tr:nth-child(even) { background: #f9f9f9; }
        .saldo-pos { color: #28a745; font-weight: bold; text-align: right; }
        .saldo-neg { color: #dc3545; font-weight: bold; text-align: right; }
        .saldo-cero { color: #666; text-align: right; }
        .saldo-cell { white-space: nowrap; }
        .total-final { font-weight: bold; background: #e7f3ff; font-size: 15px; padding: 12px; margin-top: 16px; border: 1px solid #007bff; border-radius: 4px; text-align: right; }
        .simbolo-pantalla { display: inline; }
        @media print {
            .no-print { display: none !important; }
            body { margin: 0; padding: 10px; font-size: 75%; background: white; }
            .container { box-shadow: none; }
            table { font-size: 11px; }
            th, td { padding: 4px 6px; }
            .total-final { font-size: 13px; }
            .simbolo-pantalla { display: none !important; }
            td { white-space: nowrap; }
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>RESUMEN DE CUENTAS</h1>
        <div class="fecha">Fecha: <?= date('d/m/Y H:i:s') ?> (Argentina)</div>
        
        <div class="botones no-print">
            <button onclick="window.location.href='descargar_resumen_cuentas.php'">📥 Descargar Excel (.csv)</button>
            <button onclick="window.location.href='?imprimir=1'">🖨️ Imprimir</button>
            <button onclick="window.close()">Cerrar</button>
        </div>
        
        <?php if ($imprimir): ?>
        <script>window.onload = function() { setTimeout(function() { window.print(); }, 100); };</script>
        <?php endif; ?>
        
        <table>
            <thead>
                <tr>
                    <th>Consorcio</th>
                    <th>Propiedad</th>
                    <th>Inquilino / Propietario</th>
                    <th style="text-align:right;">Saldo</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($filas as $f): 
                    $clase = $f['saldo'] > 0 ? 'saldo-pos' : ($f['saldo'] < 0 ? 'saldo-neg' : 'saldo-cero');
                    $saldo_fmt = number_format($f['saldo'], 2, ',', '.');
                ?>
                <tr>
                    <td><?= htmlspecialchars($f['consorcio']) ?></td>
                    <td><?= htmlspecialchars($f['propiedad']) ?></td>
                    <td><?= htmlspecialchars($f['nombre']) ?></td>
                    <td class="<?= $clase ?> saldo-cell"><span class="simbolo-pantalla">$ </span><?= $saldo_fmt ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        
        <div class="total-final">
            <span class="simbolo-pantalla">$ </span><?= number_format($total_general, 2, ',', '.') ?> — TOTAL GENERAL (sin Caja)
        </div>
    </div>
</body>
</html>
