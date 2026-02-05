<?php
include 'db.php';
include 'verificar_sesion.php';

$q = mysqli_real_escape_string($conexion, $_GET['q']);

// Buscamos personas que coincidan con lo escrito
$sql = "SELECT id, apellido FROM usuarios WHERE apellido LIKE '%$q%' LIMIT 10";
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