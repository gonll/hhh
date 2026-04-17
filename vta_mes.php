<?php
include 'db.php';
include 'verificar_sesion.php';

date_default_timezone_set('America/Argentina/Buenos_Aires');

$imprimir = isset($_GET['imprimir']) && $_GET['imprimir'] === '1';

$sql = "
SELECT
    p.propiedad,
    p.consorcio,
    TRIM(CONCAT(
        COALESCE(ui1.apellido, ''),
        CASE
            WHEN ui1.apellido IS NOT NULL AND ui2.apellido IS NOT NULL THEN ' / '
            ELSE ''
        END,
        COALESCE(ui2.apellido, '')
    )) AS inquilino_actual,
    COALESCE(ABS(uc.ultimo_monto), a.precio_convenido, 0) AS alquiler_actual
FROM propiedades p
INNER JOIN usuarios up ON up.id = p.propietario_id
LEFT JOIN alquileres a
    ON a.propiedad_id = p.propiedad_id
   AND a.estado = 'VIGENTE'
LEFT JOIN usuarios ui1 ON ui1.id = a.inquilino1_id
LEFT JOIN usuarios ui2 ON ui2.id = a.inquilino2_id
LEFT JOIN (
    SELECT c.usuario_id, c.monto AS ultimo_monto
    FROM cuentas c
    INNER JOIN (
        SELECT usuario_id, MAX(movimiento_id) AS max_mov
        FROM cuentas
        WHERE comprobante = 'ALQUILER'
        GROUP BY usuario_id
    ) t ON t.max_mov = c.movimiento_id
) uc ON uc.usuario_id = a.inquilino1_id
WHERE
    UPPER(TRIM(COALESCE(up.apellido, ''))) = 'HERRERA Y LLOBETA SRL'
ORDER BY p.consorcio ASC, p.propiedad ASC
";

$res = mysqli_query($conexion, $sql);
$filas = [];
$total = 0.0;

if ($res) {
    while ($row = mysqli_fetch_assoc($res)) {
        $importe = (float)($row['alquiler_actual'] ?? 0);
        $filas[] = [
            'consorcio' => (string)($row['consorcio'] ?? ''),
            'propiedad' => (string)($row['propiedad'] ?? ''),
            'inquilino_actual' => (string)($row['inquilino_actual'] ?? ''),
            'alquiler_actual' => $importe
        ];
        $total += $importe;
    }
}

$lineasTexto = [];
$lineasTexto[] = 'VTA DEL MES - HERRERA Y LLOBETA SRL';
$lineasTexto[] = 'Fecha: ' . date('d/m/Y H:i');
foreach ($filas as $f) {
    $inquilino = trim((string)($f['inquilino_actual'] ?? ''));
    if ($inquilino === '') {
        $inquilino = '-';
    }
    $lineasTexto[] = trim($f['consorcio']) . ' | ' . trim($f['propiedad']) . ' | Inquilino: ' . $inquilino . ' | $ ' . number_format($f['alquiler_actual'], 2, ',', '.');
}
$lineasTexto[] = 'TOTAL: $ ' . number_format($total, 2, ',', '.');
$textoEnvio = implode("\n", $lineasTexto);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vta del Mes</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
        .container { background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); max-width: 980px; margin: 0 auto; }
        h1 { text-align: center; color: #6f42c1; margin: 0 0 8px; }
        .fecha { text-align: center; color: #666; margin-bottom: 16px; font-size: 12px; }
        .botones { text-align: center; margin-bottom: 20px; display: flex; flex-wrap: wrap; gap: 8px; justify-content: center; }
        .botones button { background: #6f42c1; color: #fff; border: none; padding: 10px 16px; border-radius: 4px; cursor: pointer; font-size: 13px; }
        .botones button:hover { background: #5a32a3; }
        table { width: 100%; border-collapse: collapse; }
        th { background: #6f42c1; color: #fff; padding: 10px 8px; text-align: left; font-size: 12px; }
        td { padding: 8px; border-bottom: 1px solid #ddd; font-size: 13px; }
        tr:nth-child(even) { background: #f9f9f9; }
        .monto { text-align: right; font-weight: bold; color: #2f4f4f; }
        .sin-datos { text-align: center; color: #666; padding: 20px; border: 1px dashed #ccc; border-radius: 4px; }
        .total { margin-top: 14px; text-align: right; font-weight: bold; font-size: 16px; background: #f2ebff; border: 1px solid #d7c5ff; padding: 10px; border-radius: 4px; }
        @media print {
            .no-print { display: none !important; }
            body { margin: 0; background: #fff; }
            .container { box-shadow: none; }
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>VTA DEL MES</h1>
        <div class="fecha">Resumen de propiedades de HERRERA Y LLOBETA SRL - <?= date('d/m/Y H:i:s') ?> (Argentina)</div>

        <div class="botones no-print">
            <button type="button" onclick="window.location.href='?imprimir=1'">Imprimir</button>
            <button type="button" onclick="enviarWhatsapp()">Enviar WhatsApp</button>
            <button type="button" onclick="enviarEmail()">Enviar Email</button>
            <button type="button" onclick="copiarResumen()">Copiar Resumen</button>
            <button type="button" onclick="window.close()">Cerrar</button>
        </div>

        <?php if ($imprimir): ?>
        <script>window.onload = function() { setTimeout(function() { window.print(); }, 100); };</script>
        <?php endif; ?>

        <?php if (count($filas) > 0): ?>
        <table>
            <thead>
                <tr>
                    <th>Consorcio</th>
                    <th>Propiedad</th>
                    <th>Inquilino actual</th>
                    <th style="text-align:right;">Valor alquiler actual</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($filas as $f): ?>
                <tr>
                    <td><?= htmlspecialchars($f['consorcio']) ?></td>
                    <td><?= htmlspecialchars($f['propiedad']) ?></td>
                    <td><?= htmlspecialchars(trim((string)$f['inquilino_actual']) !== '' ? $f['inquilino_actual'] : '-') ?></td>
                    <td class="monto">$ <?= number_format($f['alquiler_actual'], 2, ',', '.') ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php else: ?>
        <div class="sin-datos">No hay propiedades para HERRERA Y LLOBETA SRL.</div>
        <?php endif; ?>

        <div class="total">TOTAL: $ <?= number_format($total, 2, ',', '.') ?></div>
    </div>

    <script>
        var textoResumen = <?php echo json_encode($textoEnvio, JSON_UNESCAPED_UNICODE); ?>;

        function enviarWhatsapp() {
            var url = 'https://wa.me/?text=' + encodeURIComponent(textoResumen);
            window.open(url, '_blank');
        }

        function enviarEmail() {
            var asunto = 'Vta del mes - HERRERA Y LLOBETA SRL';
            var url = 'mailto:?subject=' + encodeURIComponent(asunto) + '&body=' + encodeURIComponent(textoResumen);
            window.location.href = url;
        }

        function copiarResumen() {
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(textoResumen).then(function() {
                    alert('Resumen copiado.');
                }).catch(function() {
                    prompt('Copie el resumen:', textoResumen);
                });
                return;
            }
            prompt('Copie el resumen:', textoResumen);
        }
    </script>
</body>
</html>
