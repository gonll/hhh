<?php
include 'db.php';

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    header('Content-Type: application/json');
    echo json_encode([]);
    exit;
}

$sql = "SELECT propiedad_id, propiedad, consorcio FROM propiedades 
        WHERE propietario_id = $id ORDER BY propiedad ASC";
$res = mysqli_query($conexion, $sql);
$lista = [];
while ($row = mysqli_fetch_assoc($res)) {
    $lista[] = [
        'propiedad_id' => (int)$row['propiedad_id'],
        'propiedad' => strtoupper($row['propiedad'] ?? ''),
        'consorcio' => strtoupper($row['consorcio'] ?? '')
    ];
}
header('Content-Type: application/json');
echo json_encode($lista);
?>
