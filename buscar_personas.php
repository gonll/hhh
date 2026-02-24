<?php
include 'db.php';
include 'verificar_sesion.php';

$q = mysqli_real_escape_string($conexion, $_GET['q'] ?? '');
$excluir = isset($_GET['excluir']) ? (int)$_GET['excluir'] : 0;

// Buscamos personas que coincidan con lo escrito
$where = "apellido LIKE '%$q%'";
if ($excluir > 0) {
    $where .= " AND id != $excluir";
}
$sql = "SELECT id, apellido FROM usuarios WHERE $where ORDER BY apellido ASC LIMIT 15";
$res = mysqli_query($conexion, $sql);

$personas = [];
while ($row = mysqli_fetch_assoc($res)) {
    $personas[] = [
        'id' => $row['id'],
        'apellido' => strtoupper($row['apellido'])
    ];
}

header('Content-Type: application/json');
echo json_encode($personas);
?>