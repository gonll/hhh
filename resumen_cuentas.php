<?php
include 'db.php';
include 'verificar_sesion.php';

// Configurar zona horaria de Argentina
date_default_timezone_set('America/Argentina/Buenos_Aires');

// Obtener inquilinos1 con contratos vigentes
$sql_inquilinos = "SELECT DISTINCT u.id, u.apellido 
                   FROM usuarios u
                   INNER JOIN alquileres a ON a.inquilino1_id = u.id
                   WHERE a.estado = 'VIGENTE'";
$res_inquilinos = mysqli_query($conexion, $sql_inquilinos);

// Obtener propietarios
$sql_propietarios = "SELECT DISTINCT u.id, u.apellido 
                     FROM usuarios u
                     INNER JOIN propiedades p ON p.propietario_id = u.id";
$res_propietarios = mysqli_query($conexion, $sql_propietarios);

// Obtener Caja (ID 1)
$sql_caja = "SELECT id, apellido FROM usuarios WHERE id = 1";
$res_caja = mysqli_query($conexion, $sql_caja);

$cuentas = [];
$ids_procesados = [];

// Procesar Caja primero
if ($res_caja && $caja = mysqli_fetch_assoc($res_caja)) {
    $usuario_id = (int)$caja['id'];
    $sql_saldo = "SELECT COALESCE(SUM(monto), 0) AS saldo FROM cuentas WHERE usuario_id = $usuario_id";
    $res_saldo = mysqli_query($conexion, $sql_saldo);
    $row_saldo = mysqli_fetch_assoc($res_saldo);
    $saldo = (float)($row_saldo['saldo'] ?? 0);
    
        if ($saldo != 0) {
        $cuentas[] = [
            'id' => $usuario_id,
            'nombre' => strtoupper(trim($caja['apellido'])),
            'saldo' => $saldo,
            'tipo' => 'CAJA',
            'orden' => 0,
            'propiedades' => ''
        ];
        $ids_procesados[$usuario_id] = true;
    }
}

// Procesar inquilinos
if ($res_inquilinos) {
    while ($usuario = mysqli_fetch_assoc($res_inquilinos)) {
        $usuario_id = (int)$usuario['id'];
        if (isset($ids_procesados[$usuario_id])) continue;
        
        $sql_saldo = "SELECT COALESCE(SUM(monto), 0) AS saldo FROM cuentas WHERE usuario_id = $usuario_id";
        $res_saldo = mysqli_query($conexion, $sql_saldo);
        $row_saldo = mysqli_fetch_assoc($res_saldo);
        $saldo = (float)($row_saldo['saldo'] ?? 0);
        
        // Obtener propiedades que alquila
        $sql_propiedades = "SELECT p.propiedad 
                           FROM alquileres a
                           INNER JOIN propiedades p ON p.propiedad_id = a.propiedad_id
                           WHERE a.inquilino1_id = $usuario_id AND a.estado = 'VIGENTE'
                           ORDER BY p.propiedad ASC";
        $res_propiedades = mysqli_query($conexion, $sql_propiedades);
        $propiedades = [];
        while ($prop = mysqli_fetch_assoc($res_propiedades)) {
            $propiedades[] = strtoupper(trim($prop['propiedad']));
        }
        $propiedades_str = !empty($propiedades) ? implode(', ', $propiedades) : '';
        
        if ($saldo != 0) {
            $cuentas[] = [
                'id' => $usuario_id,
                'nombre' => strtoupper(trim($usuario['apellido'])),
                'saldo' => $saldo,
                'tipo' => 'INQUILINO',
                'orden' => 1,
                'propiedades' => $propiedades_str
            ];
            $ids_procesados[$usuario_id] = true;
        }
    }
}

// Procesar propietarios
if ($res_propietarios) {
    while ($usuario = mysqli_fetch_assoc($res_propietarios)) {
        $usuario_id = (int)$usuario['id'];
        if (isset($ids_procesados[$usuario_id])) continue;
        
        $sql_saldo = "SELECT COALESCE(SUM(monto), 0) AS saldo FROM cuentas WHERE usuario_id = $usuario_id";
        $res_saldo = mysqli_query($conexion, $sql_saldo);
        $row_saldo = mysqli_fetch_assoc($res_saldo);
        $saldo = (float)($row_saldo['saldo'] ?? 0);
        
        if ($saldo != 0) {
            $cuentas[] = [
                'id' => $usuario_id,
                'nombre' => strtoupper(trim($usuario['apellido'])),
                'saldo' => $saldo,
                'tipo' => 'PROPIETARIO',
                'orden' => 2,
                'propiedades' => ''
            ];
            $ids_procesados[$usuario_id] = true;
        }
    }
}

// Ordenar: primero por orden (Caja=0, Inquilino=1, Propietario=2), luego por nombre
usort($cuentas, function($a, $b) {
    if ($a['orden'] !== $b['orden']) {
        return $a['orden'] - $b['orden'];
    }
    return strcmp($a['nombre'], $b['nombre']);
});

// Paginación: máximo 20 cuentas por página
$cuentas_por_pagina = 20;
$pagina_param = $_GET['pagina'] ?? '1';
$imprimir = isset($_GET['imprimir']) && $_GET['imprimir'] == '1';
$mostrar_todas = ($pagina_param === 'todas' || $imprimir);
$pagina_actual = $mostrar_todas ? 1 : max(1, (int)$pagina_param);
$total_cuentas = count($cuentas);
$total_paginas = ceil($total_cuentas / $cuentas_por_pagina);
$inicio = ($pagina_actual - 1) * $cuentas_por_pagina;
$cuentas_pagina = array_slice($cuentas, $inicio, $cuentas_por_pagina);

// Calcular suma de deudas (sin caja)
$suma_deudas = 0;
foreach ($cuentas as $c) {
    // Excluir Caja (ID 1) y solo sumar saldos negativos (deudas)
    if ($c['id'] != 1 && $c['saldo'] < 0) {
        $suma_deudas += $c['saldo'];
    }
}

// Calcular total general: todas las cuentas sin caja
$total_saldo_general = 0;
foreach ($cuentas as $c) {
    // Excluir Caja (ID 1)
    if ($c['id'] != 1) {
        $total_saldo_general += $c['saldo'];
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Resumen de Cuentas</title>
    <style>
        @media print {
            .no-print { display: none !important; }
            body { margin: 0; padding: 10px; }
            .pagina { page-break-after: always; }
            .pagina:last-child { page-break-after: auto; }
        }
        .pagina {
            margin-bottom: 30px;
        }
        body {
            font-family: Arial, sans-serif;
            margin: 20px;
            background: #f5f5f5;
        }
        .container {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            max-width: 1200px;
            margin: 0 auto;
        }
        h1 {
            text-align: center;
            color: #007bff;
            margin-bottom: 20px;
        }
        .fecha {
            text-align: center;
            color: #666;
            margin-bottom: 20px;
        }
        .botones {
            text-align: center;
            margin-bottom: 20px;
        }
        .botones button {
            background: #007bff;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            margin: 0 5px;
        }
        .botones button:hover {
            background: #0056b3;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        th {
            background: #007bff;
            color: white;
            padding: 12px;
            text-align: left;
            font-weight: bold;
        }
        td {
            padding: 10px;
            border-bottom: 1px solid #ddd;
        }
        tr:hover {
            background: #f8f9fa;
        }
        .saldo-positivo {
            color: #28a745;
            font-weight: bold;
        }
        .saldo-negativo {
            color: #dc3545;
            font-weight: bold;
        }
        .saldo-cero {
            color: #666;
        }
        .tipo {
            font-size: 11px;
            color: #666;
            font-style: italic;
        }
        .total {
            font-weight: bold;
            background: #f8f9fa;
            font-size: 14px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>RESUMEN DE CUENTAS</h1>
        <div class="fecha">Fecha: <?= date('d/m/Y H:i:s') ?> (Argentina)</div>
        
        <div class="botones no-print">
            <button onclick="window.location.href='descargar_resumen_cuentas.php'">📥 Descargar Excel (.csv)</button>
            <button onclick="window.location.href='?pagina=todas&imprimir=1'">🖨️ Imprimir</button>
            <button onclick="window.close()">Cerrar</button>
            <?php if ($total_paginas > 1 && !$mostrar_todas): ?>
                <span style="margin: 0 10px;">Página <?= $pagina_actual ?> de <?= $total_paginas ?></span>
                <?php if ($pagina_actual > 1): ?>
                    <button onclick="window.location.href='?pagina=<?= $pagina_actual - 1 ?>'">« Anterior</button>
                <?php endif; ?>
                <?php if ($pagina_actual < $total_paginas): ?>
                    <button onclick="window.location.href='?pagina=<?= $pagina_actual + 1 ?>'">Siguiente »</button>
                <?php endif; ?>
                <button onclick="window.location.href='?pagina=todas'">Ver Todas</button>
            <?php endif; ?>
        </div>
        
        <?php if ($imprimir): ?>
        <script>
        window.onload = function() {
            setTimeout(function() {
                window.print();
            }, 500);
        };
        </script>
        <?php endif; ?>
        
        <?php 
        // Dividir en páginas de 20 cuentas
        $paginas = array_chunk($cuentas, $cuentas_por_pagina);
        
        if ($mostrar_todas) {
            // Mostrar todas las páginas (para impresión o vista completa)
            $paginas_a_mostrar = $paginas;
        } else {
            // Mostrar solo la página actual
            $paginas_a_mostrar = [($pagina_actual - 1) => $cuentas_pagina];
        }
        
        foreach ($paginas_a_mostrar as $num_pagina => $cuentas_pagina_actual):
            $num_pagina_real = $num_pagina + 1;
        ?>
        <div class="pagina">
            <h2 style="text-align: center; color: #666; margin-bottom: 15px; font-size: 14px;">
                Página <?= $num_pagina_real ?> de <?= count($paginas) ?>
            </h2>
            <table>
                <thead>
                    <tr>
                    <th style="width: 5%;">ID</th>
                    <th style="width: 45%;">Nombre</th>
                    <th style="width: 30%;">Propiedades</th>
                    <th style="width: 20%; text-align: right;">Saldo</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $total_saldo_pagina = 0;
                    foreach ($cuentas_pagina_actual as $cuenta): 
                        $total_saldo_pagina += $cuenta['saldo'];
                        
                        $clase_saldo = '';
                        if ($cuenta['saldo'] > 0) {
                            $clase_saldo = 'saldo-positivo';
                        } elseif ($cuenta['saldo'] < 0) {
                            $clase_saldo = 'saldo-negativo';
                        } else {
                            $clase_saldo = 'saldo-cero';
                        }
                    ?>
                    <tr>
                        <td><?= $cuenta['id'] ?></td>
                        <td><?= htmlspecialchars($cuenta['nombre']) ?></td>
                        <td style="font-size: 11px; color: #666;"><?= htmlspecialchars($cuenta['propiedades'] ?? '') ?></td>
                        <td class="<?= $clase_saldo ?>" style="text-align: right;">
                            $ <?= number_format($cuenta['saldo'], 2, ',', '.') ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr class="total">
                        <td colspan="3" style="text-align: right; padding-right: 20px;">
                            Total Página <?= $num_pagina_real ?>:
                        </td>
                        <td style="text-align: right; <?= $total_saldo_pagina >= 0 ? 'color: #28a745;' : 'color: #dc3545;' ?>">
                            $ <?= number_format($total_saldo_pagina, 2, ',', '.') ?>
                        </td>
                    </tr>
                    <?php if ($num_pagina_real === count($paginas)): ?>
                    <tr class="total" style="background: #fff3cd;">
                        <td colspan="3" style="text-align: right; padding-right: 20px; font-size: 15px;">
                            <strong>SUMA DEUDAS (sin Caja):</strong>
                        </td>
                        <td style="text-align: right; font-size: 15px; color: #dc3545;">
                            <strong>$ <?= number_format($suma_deudas, 2, ',', '.') ?></strong>
                        </td>
                    </tr>
                    <tr class="total" style="background: #e7f3ff;">
                        <td colspan="3" style="text-align: right; padding-right: 20px; font-size: 16px;">
                            <strong>TOTAL GENERAL (sin Caja):</strong>
                        </td>
                        <td style="text-align: right; font-size: 16px; <?= $total_saldo_general >= 0 ? 'color: #28a745;' : 'color: #dc3545;' ?>">
                            <strong>$ <?= number_format($total_saldo_general, 2, ',', '.') ?></strong>
                        </td>
                    </tr>
                    <?php endif; ?>
                </tfoot>
            </table>
        </div>
        <?php endforeach; ?>
    </div>
</body>
</html>
