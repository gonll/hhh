<?php
/**
 * API JSON: listar / guardar / eliminar registros de servicios y observaciones por usuario.
 */
include 'db.php';
include 'verificar_sesion.php';
require_once __DIR__ . '/helpers_tenant_inmobiliaria.php';
require_once __DIR__ . '/includes_usuario_servicios_observ.php';

tenant_inmob_asegurar_esquema($conexion);
usuario_servicios_observ_asegurar_tabla($conexion);

header('Content-Type: application/json; charset=utf-8');

$nivel = (int) ($_SESSION['acceso_nivel'] ?? 0);
if ($nivel < 1) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Sin permiso']);
    exit;
}

if (!usuario_servicios_observ_sesion_operador_autorizado()) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Sin permiso']);
    exit;
}

$action = isset($_REQUEST['action']) ? trim((string) $_REQUEST['action']) : '';

if ($action === 'list') {
    $usuario_id = isset($_GET['usuario_id']) ? (int) $_GET['usuario_id'] : 0;
    if ($usuario_id <= 0 || !tenant_inmob_usuario_id_visible($conexion, $usuario_id)) {
        http_response_code(403);
        echo json_encode(['ok' => false, 'error' => 'Sin permiso']);
        exit;
    }
    if (!usuario_servicios_observ_cuenta_es_propietario_o_inquilino($conexion, $usuario_id)) {
        http_response_code(403);
        echo json_encode(['ok' => false, 'error' => 'Solo cuentas de propietarios o inquilinos']);
        exit;
    }
    $servicios = usuario_servicios_observ_lista_servicios();
    $ordenServ = array_flip($servicios);
    $sql = "SELECT id, usuario_id, servicio, fecha, detalle, periodo, monto, observacion
            FROM usuario_servicios_observ
            WHERE usuario_id = $usuario_id
            ORDER BY id DESC
            LIMIT 6";
    $r = mysqli_query($conexion, $sql);
    $rows = [];
    if ($r) {
        while ($row = mysqli_fetch_assoc($r)) {
            $rows[] = [
                'id' => (int) $row['id'],
                'usuario_id' => (int) $row['usuario_id'],
                'servicio' => $row['servicio'],
                'fecha' => $row['fecha'],
                'detalle' => $row['detalle'],
                'periodo' => $row['periodo'],
                'monto' => (float) $row['monto'],
                'observacion' => $row['observacion'] ?? '',
            ];
        }
    }
    usort($rows, function ($a, $b) use ($ordenServ) {
        $oa = $ordenServ[$a['servicio']] ?? 99;
        $ob = $ordenServ[$b['servicio']] ?? 99;
        if ($oa !== $ob) {
            return $oa <=> $ob;
        }
        if (($a['fecha'] ?? '') !== ($b['fecha'] ?? '')) {
            return strcmp((string) ($a['fecha'] ?? ''), (string) ($b['fecha'] ?? ''));
        }

        return ($a['id'] ?? 0) <=> ($b['id'] ?? 0);
    });
    echo json_encode(['ok' => true, 'items' => $rows, 'servicios' => $servicios]);
    exit;
}

if ($nivel < 2) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Solo consulta']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Método no permitido']);
    exit;
}

if ($action === 'save') {
    $usuario_id = isset($_POST['usuario_id']) ? (int) $_POST['usuario_id'] : 0;
    if ($usuario_id <= 0 || !tenant_inmob_usuario_id_visible($conexion, $usuario_id)) {
        http_response_code(403);
        echo json_encode(['ok' => false, 'error' => 'Sin permiso']);
        exit;
    }
    if (!usuario_servicios_observ_cuenta_es_propietario_o_inquilino($conexion, $usuario_id)) {
        http_response_code(403);
        echo json_encode(['ok' => false, 'error' => 'Solo cuentas de propietarios o inquilinos']);
        exit;
    }
    $servicio = trim((string) ($_POST['servicio'] ?? ''));
    if (!usuario_servicios_observ_servicio_valido($servicio)) {
        echo json_encode(['ok' => false, 'error' => 'Servicio inválido']);
        exit;
    }
    $fecha_raw = trim((string) ($_POST['fecha'] ?? ''));
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha_raw)) {
        echo json_encode(['ok' => false, 'error' => 'Fecha inválida']);
        exit;
    }
    $d = DateTime::createFromFormat('Y-m-d', $fecha_raw);
    if (!$d || $d->format('Y-m-d') !== $fecha_raw) {
        echo json_encode(['ok' => false, 'error' => 'Fecha no válida']);
        exit;
    }
    $detalle = trim((string) ($_POST['detalle'] ?? ''));
    if ($detalle === '' || (function_exists('mb_strlen') ? mb_strlen($detalle) : strlen($detalle)) > 500) {
        echo json_encode(['ok' => false, 'error' => 'Detalle obligatorio (máx. 500 caracteres)']);
        exit;
    }
    $periodo = trim((string) ($_POST['periodo'] ?? ''));
    if ((function_exists('mb_strlen') ? mb_strlen($periodo) : strlen($periodo)) > 50) {
        echo json_encode(['ok' => false, 'error' => 'Período demasiado largo']);
        exit;
    }
    $monto_raw = isset($_POST['monto']) ? str_replace(',', '.', trim((string) $_POST['monto'])) : '0';
    $monto = is_numeric($monto_raw) ? (float) $monto_raw : null;
    if ($monto === null || !is_finite($monto)) {
        echo json_encode(['ok' => false, 'error' => 'Monto inválido']);
        exit;
    }
    $observacion = trim((string) ($_POST['observacion'] ?? ''));
    $id = isset($_POST['id']) ? (int) $_POST['id'] : 0;

    $detalle_e = mysqli_real_escape_string($conexion, $detalle);
    $periodo_e = mysqli_real_escape_string($conexion, $periodo);
    $servicio_e = mysqli_real_escape_string($conexion, $servicio);
    $fecha_e = mysqli_real_escape_string($conexion, $fecha_raw);
    $obs_e = mysqli_real_escape_string($conexion, $observacion);

    if ($id > 0) {
        $chk = mysqli_query($conexion, "SELECT id FROM usuario_servicios_observ WHERE id = $id AND usuario_id = $usuario_id LIMIT 1");
        if (!$chk || mysqli_num_rows($chk) === 0) {
            echo json_encode(['ok' => false, 'error' => 'Registro no encontrado']);
            exit;
        }
        $sql = "UPDATE usuario_servicios_observ SET servicio = '$servicio_e', fecha = '$fecha_e', detalle = '$detalle_e',
                periodo = '$periodo_e', monto = $monto, observacion = " . ($observacion === '' ? "NULL" : "'$obs_e'") . "
                WHERE id = $id AND usuario_id = $usuario_id";
        if (!mysqli_query($conexion, $sql)) {
            echo json_encode(['ok' => false, 'error' => 'Error al guardar']);
            exit;
        }
        echo json_encode(['ok' => true, 'id' => $id]);
        exit;
    }

    $sql = "INSERT INTO usuario_servicios_observ (usuario_id, servicio, fecha, detalle, periodo, monto, observacion)
            VALUES ($usuario_id, '$servicio_e', '$fecha_e', '$detalle_e', '$periodo_e', $monto, " . ($observacion === '' ? 'NULL' : "'$obs_e'") . ')';
    if (!mysqli_query($conexion, $sql)) {
        echo json_encode(['ok' => false, 'error' => 'Error al guardar']);
        exit;
    }
    echo json_encode(['ok' => true, 'id' => (int) mysqli_insert_id($conexion)]);
    exit;
}

if ($action === 'delete') {
    $usuario_id = isset($_POST['usuario_id']) ? (int) $_POST['usuario_id'] : 0;
    $id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
    if ($usuario_id <= 0 || $id <= 0 || !tenant_inmob_usuario_id_visible($conexion, $usuario_id)) {
        http_response_code(403);
        echo json_encode(['ok' => false, 'error' => 'Sin permiso']);
        exit;
    }
    if (!usuario_servicios_observ_cuenta_es_propietario_o_inquilino($conexion, $usuario_id)) {
        http_response_code(403);
        echo json_encode(['ok' => false, 'error' => 'Solo cuentas de propietarios o inquilinos']);
        exit;
    }
    $id = (int) $id;
    if (!mysqli_query($conexion, "DELETE FROM usuario_servicios_observ WHERE id = $id AND usuario_id = $usuario_id LIMIT 1")) {
        echo json_encode(['ok' => false, 'error' => 'Error al eliminar']);
        exit;
    }
    echo json_encode(['ok' => true]);
    exit;
}

http_response_code(400);
echo json_encode(['ok' => false, 'error' => 'Acción desconocida']);
