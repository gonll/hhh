<?php
include 'db.php';
include 'verificar_sesion.php';
require_once __DIR__ . '/helpers_tenant_inmobiliaria.php';
tenant_inmob_asegurar_esquema($conexion);

$q = mysqli_real_escape_string($conexion, $_GET['q'] ?? '');
$excluir = isset($_GET['excluir']) ? (int)$_GET['excluir'] : 0;

// Buscamos personas que coincidan con lo escrito
$tw = tenant_inmob_sql_usuarios($conexion, 'u');
$where = "u.apellido LIKE '%$q%' AND ($tw)";
if ($excluir > 0) {
    $where .= " AND u.id != $excluir";
}
$sql = "SELECT u.id, u.apellido FROM usuarios u WHERE $where ORDER BY u.apellido ASC LIMIT 15";
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