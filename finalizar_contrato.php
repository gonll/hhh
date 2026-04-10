<?php
ob_start();
if (isset($_GET['debug']) && $_GET['debug'] === '1') {
    ini_set('display_errors', 1);
    error_reporting(E_ALL);
}
ob_end_clean();
header('Content-Type: text/plain; charset=utf-8');
include 'db.php';
include 'verificar_sesion.php';
require_once __DIR__ . '/helpers_propiedad.php';

if (isset($_SESSION['acceso_nivel']) && $_SESSION['acceso_nivel'] < 2) {
    echo 'Sin permiso';
    exit;
}
$id_prop = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($id_prop <= 0) {
    echo 'ID inválido';
    exit;
}

require_once __DIR__ . '/helpers_tenant_inmobiliaria.php';
tenant_inmob_asegurar_esquema($conexion);
if (!tenant_inmob_propiedad_id_visible($conexion, $id_prop)) {
    echo 'Sin permiso';
    exit;
}

$resAlq = mysqli_query(
    $conexion,
    "SELECT inquilino1_id, inquilino2_id FROM alquileres WHERE propiedad_id = $id_prop AND estado = 'VIGENTE' LIMIT 1"
);
$alq = $resAlq ? mysqli_fetch_assoc($resAlq) : null;

$resProp = mysqli_query($conexion, "SELECT propiedad FROM propiedades WHERE propiedad_id = $id_prop LIMIT 1");
$rowProp = $resProp ? mysqli_fetch_assoc($resProp) : null;
$nombreProp = trim((string) ($rowProp['propiedad'] ?? ''));
$frag = strtoupper(omitir_ciudad_provincia($nombreProp));
$frag_esc = mysqli_real_escape_string($conexion, str_replace(['%', '_'], ['\\%', '\\_'], $frag));

mysqli_begin_transaction($conexion);

try {
    if ($alq && $frag_esc !== '') {
        $uids = [];
        $i1 = (int) ($alq['inquilino1_id'] ?? 0);
        $i2 = (int) ($alq['inquilino2_id'] ?? 0);
        if ($i1 > 0) {
            $uids[] = $i1;
        }
        if ($i2 > 0) {
            $uids[] = $i2;
        }
        $uids = array_values(array_unique($uids));

        foreach ($uids as $uid) {
            $uid = (int) $uid;
            if ($uid <= 0) {
                continue;
            }
            // Liquidación mensual y prorrateo: mismos criterios que liquidar_alquileres_mes / guardar_contrato
            $sqlDel = "DELETE FROM cuentas WHERE usuario_id = $uid AND (
                (comprobante = 'ALQUILER' AND concepto LIKE '%$frag_esc%')
                OR (comprobante = 'LIQ ALQUILER' AND concepto LIKE '%$frag_esc%')
                OR (
                    comprobante = 'DEPÓSITO'
                    AND UPPER(TRIM(referencia)) = 'GARANTÍA'
                    AND UPPER(concepto) LIKE '%INICIO DEL CONTRATO%'
                    AND concepto LIKE '%$frag_esc%'
                )
            )";
            if (!mysqli_query($conexion, $sqlDel)) {
                throw new Exception('Error al limpiar movimientos de liquidación: ' . mysqli_error($conexion));
            }
        }
    }

    $sql1 = "UPDATE alquileres SET estado = 'BAJA' WHERE propiedad_id = $id_prop AND estado = 'VIGENTE'";
    if (!mysqli_query($conexion, $sql1)) {
        throw new Exception('Error alquileres: ' . mysqli_error($conexion));
    }

    $sql2 = "UPDATE propiedades SET alquiler = 0 WHERE propiedad_id = $id_prop";
    if (!mysqli_query($conexion, $sql2)) {
        throw new Exception('Error propiedades: ' . mysqli_error($conexion));
    }

    mysqli_commit($conexion);
} catch (Exception $e) {
    mysqli_rollback($conexion);
    echo $e->getMessage();
    exit;
}

echo 'OK';
