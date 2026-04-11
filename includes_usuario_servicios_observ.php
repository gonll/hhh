<?php
/**
 * Servicios / observaciones por persona (Edet, Sat, Cisi, Gas, Expensas, Observaciones).
 */
require_once __DIR__ . '/helpers_tenant_inmobiliaria.php';

/** Orden de listado y valores válidos de servicio. */
function usuario_servicios_observ_lista_servicios(): array
{
    return ['Edet', 'Sat', 'Cisi', 'Gas', 'Expensas', 'Observaciones'];
}

function usuario_servicios_observ_servicio_valido(string $s): bool
{
    return in_array($s, usuario_servicios_observ_lista_servicios(), true);
}

function usuario_servicios_observ_asegurar_tabla($conexion): void
{
    static $hecho = false;
    if ($hecho) {
        return;
    }
    $hecho = true;
    tenant_inmob_asegurar_esquema($conexion);
    if (tenant_inmob_tabla_existe($conexion, 'usuario_servicios_observ')) {
        return;
    }
    $sql = "CREATE TABLE IF NOT EXISTS `usuario_servicios_observ` (
        `id` int NOT NULL AUTO_INCREMENT,
        `usuario_id` int NOT NULL,
        `servicio` varchar(32) COLLATE utf8mb4_spanish_ci NOT NULL,
        `fecha` date NOT NULL,
        `detalle` varchar(500) COLLATE utf8mb4_spanish_ci NOT NULL DEFAULT '',
        `periodo` varchar(50) COLLATE utf8mb4_spanish_ci NOT NULL DEFAULT '',
        `monto` decimal(15,2) NOT NULL DEFAULT '0.00',
        `observacion` text COLLATE utf8mb4_spanish_ci,
        PRIMARY KEY (`id`),
        KEY `idx_uso_usuario` (`usuario_id`),
        CONSTRAINT `fk_uso_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci";
    @mysqli_query($conexion, $sql);
}

/**
 * Operadores que pueden usar Servicios/Observaciones (nombre de usuario de acceso).
 */
function usuario_servicios_observ_sesion_operador_autorizado(): bool
{
    $acc = trim((string) ($_SESSION['acceso_usuario'] ?? ''));

    return $acc !== '' && (bool) preg_match('/silvana|hugo|sof[ií]a/ui', $acc);
}

/**
 * La persona es propietario de alguna propiedad o inquilino en alquiler vigente (mismo criterio que cobro expensa).
 */
function usuario_servicios_observ_cuenta_es_propietario_o_inquilino($conexion, int $usuario_id): bool
{
    if ($usuario_id <= 0) {
        return false;
    }
    tenant_inmob_asegurar_esquema($conexion);
    $wp = tenant_inmob_sql_propiedades($conexion, 'p');
    $uid = (int) $usuario_id;
    $sql = "SELECT 1 FROM propiedades p
        LEFT JOIN alquileres a ON a.propiedad_id = p.propiedad_id AND a.estado = 'VIGENTE'
        WHERE ($wp) AND (p.propietario_id = $uid OR a.inquilino1_id = $uid OR a.inquilino2_id = $uid)
        LIMIT 1";
    $r = mysqli_query($conexion, $sql);

    return $r && mysqli_num_rows($r) > 0;
}
