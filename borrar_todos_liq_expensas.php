<?php
include 'db.php';
include 'verificar_sesion.php';

if (isset($_SESSION['acceso_nivel']) && $_SESSION['acceso_nivel'] < 3) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => false, 'msg' => 'Sin permiso (requiere nivel 3).']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['consorcio_id'])) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => false, 'msg' => 'Falta consorcio.']);
    exit;
}

$consorcio_id = (int)$_POST['consorcio_id'];
if ($consorcio_id <= 0) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => false, 'msg' => 'Consorcio inválido.']);
    exit;
}

$res_u = mysqli_query($conexion, "SELECT id, apellido, consorcio FROM usuarios WHERE id = $consorcio_id LIMIT 1");
$row_u = mysqli_fetch_assoc($res_u);
if (!$row_u || stripos($row_u['apellido'], 'CONSORCIO') !== 0) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => false, 'msg' => 'El usuario no es un Consorcio.']);
    exit;
}

$nombre_consorcio = trim($row_u['consorcio'] ?? '');
$apellido_consorcio = trim($row_u['apellido'] ?? '');
$consorcio_esc = mysqli_real_escape_string($conexion, $nombre_consorcio);

// Identificador para filtrar por concepto (ej: "LAPRIDA 430" en "EXPENSAS, DPTO 5° PISO C, LAPRIDA 430")
$consorcio_en_concepto = trim(preg_replace('/^CONSORCIO\s+/i', '', $apellido_consorcio));
if ($consorcio_en_concepto === '') {
    $consorcio_en_concepto = $nombre_consorcio;
}
$consorcio_concepto_esc = mysqli_real_escape_string($conexion, strtoupper($consorcio_en_concepto));

$eliminados = 0;

// 1. Eliminar TODOS los LIQ EXPENSAS y Honorarios en cuenta del consorcio
$sql1 = "DELETE FROM cuentas 
    WHERE usuario_id = $consorcio_id 
    AND (UPPER(TRIM(comprobante)) = 'LIQ EXPENSAS' OR UPPER(TRIM(comprobante)) = 'HONORARIOS')";
if (mysqli_query($conexion, $sql1)) {
    $eliminados += mysqli_affected_rows($conexion);
}

// 2. Eliminar TODOS los movimientos de liquidación en cuentas de usuarios.
//    Buscar por concepto que contenga el identificador del consorcio (ej. "LAPRIDA 430")
//    para no depender de propiedades.consorcio que puede no coincidir.
//    Movimientos: LIQ EXP, LIQ EXP EXT, o concepto "EXPENSAS,..." / "EXPENSAS EXTRAORDINARIAS,..." con monto < 0
$sql2 = "DELETE FROM cuentas 
    WHERE usuario_id != $consorcio_id 
    AND UPPER(TRIM(concepto)) LIKE '%$consorcio_concepto_esc%'
    AND (
        UPPER(TRIM(comprobante)) = 'LIQ EXP' 
        OR UPPER(TRIM(comprobante)) = 'LIQ EXP EXT'
        OR (monto < 0 AND (
            UPPER(TRIM(concepto)) LIKE 'EXPENSAS,%' 
            OR UPPER(TRIM(concepto)) LIKE 'EXPENSAS EXTRAORDINARIAS,%'
        ))
    )";
if (mysqli_query($conexion, $sql2)) {
    $eliminados += mysqli_affected_rows($conexion);
}

header('Content-Type: application/json; charset=utf-8');
echo json_encode([
    'ok' => true,
    'msg' => "Se eliminaron $eliminados movimientos de liquidación de expensas (LIQ EXPENSAS, Honorarios, LIQ EXP, LIQ EXP EXT) del consorcio.",
    'eliminados' => $eliminados
]);
