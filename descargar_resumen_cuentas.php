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

// Calcular suma de deudas (sin caja)
$suma_deudas = 0;
foreach ($cuentas as $c) {
    if ($c['id'] != 1 && $c['saldo'] < 0) {
        $suma_deudas += $c['saldo'];
    }
}

// Calcular total general: todas las cuentas sin caja
$total_saldo_general = 0;
foreach ($cuentas as $c) {
    if ($c['id'] != 1) {
        $total_saldo_general += $c['saldo'];
    }
}

// Generar CSV compatible con Excel (usando punto y coma como separador)
$fecha_archivo = date('Y-m-d_His');
$nombre_archivo = "resumen_cuentas_$fecha_archivo.csv";

// Headers para que Excel lo reconozca correctamente
header('Content-Type: text/csv; charset=utf-8');
header("Content-Disposition: attachment; filename=\"$nombre_archivo\"");
header('Pragma: no-cache');
header('Expires: 0');

// BOM UTF-8 para Excel
echo "\xEF\xBB\xBF";

// Separador: punto y coma (;) para Excel en español
$sep = ';';

// Función para escapar CSV
function escaparCsv($texto) {
    $texto = str_replace('"', '""', $texto);
    if (strpos($texto, ';') !== false || strpos($texto, ',') !== false || strpos($texto, '"') !== false || strpos($texto, "\n") !== false) {
        return '"' . $texto . '"';
    }
    return $texto;
}

// Título
echo escaparCsv('RESUMEN DE CUENTAS') . "\n";
echo escaparCsv('Fecha: ' . date('d/m/Y H:i:s') . ' (Argentina)') . "\n";
echo "\n";

// Encabezados
echo escaparCsv('ID') . $sep . 
     escaparCsv('Nombre') . $sep . 
     escaparCsv('Tipo') . $sep . 
     escaparCsv('Propiedades') . $sep . 
     escaparCsv('Saldo') . "\n";

// Datos
foreach ($cuentas as $cuenta) {
    echo $cuenta['id'] . $sep . 
         escaparCsv($cuenta['nombre']) . $sep . 
         escaparCsv($cuenta['tipo']) . $sep . 
         escaparCsv($cuenta['propiedades']) . $sep . 
         escaparCsv(number_format($cuenta['saldo'], 2, ',', '.')) . "\n";
}

// Línea vacía
echo "\n";

// Suma de deudas
echo $sep . $sep . escaparCsv('SUMA DEUDAS (sin Caja)') . $sep . escaparCsv(number_format($suma_deudas, 2, ',', '.')) . "\n";

// Total general
echo $sep . $sep . escaparCsv('TOTAL GENERAL (sin Caja)') . $sep . escaparCsv(number_format($total_saldo_general, 2, ',', '.')) . "\n";

exit;
?>
