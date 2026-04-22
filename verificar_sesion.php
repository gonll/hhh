<?php
session_start();

/** Scripts que responden JSON por fetch (evitar redirect HTML que rompe r.json()). */
$scriptApiJson = basename($_SERVER['SCRIPT_NAME'] ?? '');
$esApiJson = in_array($scriptApiJson, [
    'eliminar_liq_expensas_periodo.php',
    'borrar_todos_liq_expensas.php',
    'fotos_propiedad_json.php',
    'obtener_movimientos_ctacel.php',
    'obtener_vencimiento_inquilino_ctacel.php',
], true);

if (empty($_SESSION['acceso_id']) || !isset($_SESSION['acceso_nivel'])) {
    if ($esApiJson) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => false, 'msg' => 'Sesión expirada. Inicie sesión de nuevo.']);
        exit;
    }
    header('Location: login.php');
    exit;
}
// Nivel 0: usuario zafra (nombre contiene "zafra") → solo Cosecha; silvana → index + resto móvil; resto → solo Partes desde cel
if ((int)($_SESSION['acceso_nivel'] ?? -1) === 0) {
    $script = basename($_SERVER['SCRIPT_NAME'] ?? '');
    $usuario = (string)($_SESSION['acceso_usuario'] ?? '');
    $es_usuario_zafra = (stripos($usuario, 'zafra') !== false);
    $es_silvana = strcasecmp(trim($usuario), 'silvana') === 0;
    if ($es_usuario_zafra) {
        $permitidos_nivel0 = array('cosecha.php', 'ctacel.php', 'obtener_movimientos_ctacel.php', 'obtener_vencimiento_inquilino_ctacel.php', 'resumen_cuentas.php', 'descargar_resumen_cuentas.php', 'logout.php');
    } else {
        $permitidos_nivel0 = array('partes_desde_cel.php', 'ctacel.php', 'obtener_movimientos_ctacel.php', 'obtener_vencimiento_inquilino_ctacel.php', 'resumen_cuentas.php', 'descargar_resumen_cuentas.php', 'logout.php');
        if ($es_silvana) {
            $permitidos_nivel0[] = 'index.php';
        }
    }
    if (!in_array($script, $permitidos_nivel0)) {
        if ($esApiJson) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['ok' => false, 'msg' => 'Sin permiso para esta acción.']);
            exit;
        }
        $redirigir_restringido = true;
        if (!$es_usuario_zafra && $script === 'index.php') {
            require_once __DIR__ . '/helpers_movil.php';
            if (empty($_SESSION['vista_escritorio_movil']) && hh_es_user_agent_movil()) {
                $redirigir_restringido = false;
            }
        }
        if ($redirigir_restringido) {
            header('Location: ' . ($es_usuario_zafra ? 'cosecha.php' : 'partes_desde_cel.php'));
            exit;
        }
    }
}

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers_tenant_inmobiliaria.php';
tenant_inmob_detectar_sofia_sesion($conexion);
tenant_inmob_aplicar_restriccion_sofia();
