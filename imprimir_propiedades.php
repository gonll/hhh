<?php
include 'db.php';
include 'verificar_sesion.php';

$sql = "SELECT p.propiedad, p.consorcio,
        prop.apellido AS nombre_propietario,
        u.apellido AS nombre_inquilino,
        a.fecha_inicio AS inicio,
        a.fecha_fin AS vencimiento,
        a.precio_convenido AS monto_alquiler
        FROM propiedades p
        LEFT JOIN usuarios prop ON p.propietario_id = prop.id
        LEFT JOIN alquileres a ON a.propiedad_id = p.propiedad_id AND a.estado = 'VIGENTE'
        LEFT JOIN usuarios u ON a.inquilino1_id = u.id
        ORDER BY p.consorcio ASC, p.propiedad ASC";
$resultado = mysqli_query($conexion, $sql);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Imprimir - Propiedades</title>
    <style>
        body { font-family: 'Segoe UI', sans-serif; margin: 15px; font-size: 12px; }
        .no-print { margin-bottom: 15px; }
        h1 { color: #007bff; font-size: 16px; margin: 0 0 12px 0; text-transform: uppercase; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #333; padding: 6px 8px; text-align: left; }
        th { background: #007bff; color: white; font-size: 11px; text-transform: uppercase; }
        tr:nth-child(even) { background: #f5f5f5; }
        .disponible { color: #666; font-style: italic; }
        @media print {
            .no-print { display: none !important; }
            body { margin: 4mm 6mm; font-size: 18px; line-height: 1.15; }
            h1 { font-size: 22px; margin: 0 0 6px 0; padding: 0; }
            table { font-size: 16px; }
            th, td { padding: 4px 8px; line-height: 1.2; }
            th { font-size: 14px; }
            tr { height: auto; }
            tbody tr { page-break-inside: avoid; }
        }
    </style>
</head>
<body>
    <div class="no-print">
        <button type="button" onclick="window.print();" style="background:#007bff; color:white; border:none; padding:8px 16px; border-radius:4px; cursor:pointer; font-weight:bold;">🖨️ Imprimir</button>
        <a href="propiedades.php" style="margin-left:10px; color:#007bff;">← Volver a Gestión de Propiedades</a>
    </div>
    <h1>Propiedades – Consorcio, Propiedad, Propietario, Inquilino, Inicio y Fin de alquiler</h1>
    <table>
        <thead>
            <tr>
                <th>Consorcio</th>
                <th>Propiedad</th>
                <th>Propietario</th>
                <th>Inquilino</th>
                <th>Inicio alquiler</th>
                <th>Fin alquiler</th>
                <th>Monto alquiler actual</th>
            </tr>
        </thead>
        <tbody>
            <?php while ($f = mysqli_fetch_assoc($resultado)): 
                $inicio = !empty($f['inicio']) ? date('d/m/Y', strtotime($f['inicio'])) : '-';
                $venc  = !empty($f['vencimiento']) ? date('d/m/Y', strtotime($f['vencimiento'])) : '-';
                $inquilino = !empty($f['nombre_inquilino']) ? htmlspecialchars($f['nombre_inquilino']) : '<span class="disponible">Disponible</span>';
                $propietario = !empty($f['nombre_propietario']) ? htmlspecialchars($f['nombre_propietario']) : '-';
                $monto = (isset($f['monto_alquiler']) && $f['monto_alquiler'] !== null && $f['monto_alquiler'] !== '') ? number_format((float)$f['monto_alquiler'], 2, ',', '.') : '-';
            ?>
            <tr>
                <td><?= htmlspecialchars($f['consorcio']) ?></td>
                <td><?= htmlspecialchars($f['propiedad']) ?></td>
                <td><?= $propietario ?></td>
                <td><?= $inquilino ?></td>
                <td><?= $inicio ?></td>
                <td><?= $venc ?></td>
                <td style="text-align:right;"><?= $monto ?></td>
            </tr>
            <?php endwhile; ?>
        </tbody>
    </table>
    <script>
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' || e.keyCode === 27 || e.which === 27) {
            e.preventDefault();
            if (history.length > 1) history.back();
            else window.location.href = 'propiedades.php';
        }
    });
    </script>
</body>
</html>
