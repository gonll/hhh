<?php
include 'db.php';
include 'verificar_sesion.php';

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    header('Content-Type: application/json');
    echo json_encode([]);
    exit;
}

// Propiedades donde es propietario O donde es inquilino (alquiler vigente)
$sql = "SELECT DISTINCT p.propiedad_id, p.propiedad, p.consorcio, 
        CASE WHEN p.propietario_id = $id THEN 'PROPIETARIO' 
             ELSE 'INQUILINO' END AS rol
        FROM propiedades p
        LEFT JOIN alquileres a ON a.propiedad_id = p.propiedad_id AND a.estado = 'VIGENTE'
        WHERE p.propietario_id = $id 
           OR a.inquilino1_id = $id OR a.inquilino2_id = $id
        ORDER BY p.propiedad ASC";
$res = mysqli_query($conexion, $sql);
$lista = [];
$vistos = [];
while ($row = mysqli_fetch_assoc($res)) {
    $pid = (int)$row['propiedad_id'];
    if (isset($vistos[$pid])) continue;
    $vistos[$pid] = true;
    $lista[] = [
        'propiedad_id' => $pid,
        'propiedad' => strtoupper($row['propiedad'] ?? ''),
        'consorcio' => strtoupper($row['consorcio'] ?? ''),
        'rol' => $row['rol'] ?? 'PROPIETARIO'
    ];
}
header('Content-Type: application/json');
echo json_encode($lista);
?>
