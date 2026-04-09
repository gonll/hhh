<?php
/**
 * Ámbito inmobiliario (columna acceso_creador_id en usuarios/propiedades; índices IPC por ámbito).
 *
 * Regla de negocio:
 * - Con sesión "sofia": solo registros cargados en su ámbito → acceso_creador_id = id de la fila
 *   `accesos` donde usuario = 'sofia'. No se listan ni cuentas/propiedades del sistema principal (NULL).
 * - Con cualquier otro usuario de acceso: solo datos del sistema principal → acceso_creador_id IS NULL.
 *   No se ven los registros del ámbito Sofía (acceso_creador_id = id de sofia).
 *
 * Los movimientos (cuentas) van ligados a usuarios: al filtrar personas por ámbito, los saldos quedan aislados.
 * Si no existe usuario 'sofia' en `accesos`, no se aplica filtro (compatibilidad con bases antiguas).
 */

if (!function_exists('tenant_inmob_es_sofia')) {
    /**
     * Marca si el acceso actual es ámbito Sofía (fila accesos por id de sesión; más fiable que solo el nombre en sesión).
     * Debe llamarse tras conectar BD (p. ej. desde verificar_sesion.php).
     */
    function tenant_inmob_detectar_sofia_sesion($conexion): void
    {
        static $hecho = false;
        if ($hecho) {
            return;
        }
        $hecho = true;
        $aid = (int) ($_SESSION['acceso_id'] ?? 0);
        if ($aid <= 0) {
            $_SESSION['tenant_es_sofia'] = 0;

            return;
        }
        $r = mysqli_query($conexion, 'SELECT usuario FROM accesos WHERE id = ' . $aid . ' LIMIT 1');
        if (!$r || !($row = mysqli_fetch_assoc($r))) {
            $_SESSION['tenant_es_sofia'] = 0;

            return;
        }
        $u = trim((string) ($row['usuario'] ?? ''));
        $_SESSION['tenant_es_sofia'] = (strcasecmp($u, 'sofia') === 0) ? 1 : 0;
    }

    function tenant_inmob_es_sofia(): bool
    {
        if (isset($_SESSION['tenant_es_sofia'])) {
            return (int) $_SESSION['tenant_es_sofia'] === 1;
        }
        $u = trim((string) ($_SESSION['acceso_usuario'] ?? ''));

        return strcasecmp($u, 'sofia') === 0;
    }

    /**
     * Texto de la pestaña del navegador según ámbito (Sofía vs sistema principal).
     *
     * @param string $seccion Título corto de la pantalla; vacío = pantalla de inicio.
     */
    function tenant_inmob_html_title(string $seccion = ''): string
    {
        if (!tenant_inmob_es_sofia()) {
            return $seccion === '' ? 'Sistema HHH 2026' : $seccion . ' - HHH';
        }
        if ($seccion === '') {
            return 'BGH Inmobiliarias, Sofia';
        }
        return $seccion . ' — BGH Inmobiliarias, Sofia';
    }

    function tenant_inmob_tabla_columna_existe($conexion, string $tabla, string $columna): bool
    {
        $t = preg_replace('/[^a-z0-9_]/i', '', $tabla);
        $c = preg_replace('/[^a-z0-9_]/i', '', $columna);
        if ($t === '' || $c === '') {
            return false;
        }
        $c_esc = mysqli_real_escape_string($conexion, $c);
        $r = mysqli_query($conexion, "SHOW COLUMNS FROM `$t` LIKE '$c_esc'");
        return $r && mysqli_num_rows($r) > 0;
    }

    /**
     * Asegura columnas de ámbito (ALTER si el usuario MySQL tiene permiso).
     */
    function tenant_inmob_asegurar_esquema($conexion): void
    {
        static $done = false;
        if ($done) {
            return;
        }
        if (!tenant_inmob_tabla_columna_existe($conexion, 'usuarios', 'acceso_creador_id')) {
            @mysqli_query($conexion, "ALTER TABLE usuarios ADD COLUMN acceso_creador_id INT(11) DEFAULT NULL COMMENT 'NULL=principal' AFTER id");
            @mysqli_query($conexion, 'ALTER TABLE usuarios ADD KEY idx_acceso_creador (acceso_creador_id)');
        }
        if (!tenant_inmob_tabla_columna_existe($conexion, 'propiedades', 'acceso_creador_id')) {
            @mysqli_query($conexion, "ALTER TABLE propiedades ADD COLUMN acceso_creador_id INT(11) DEFAULT NULL COMMENT 'NULL=principal' AFTER propiedad_id");
            @mysqli_query($conexion, 'ALTER TABLE propiedades ADD KEY idx_prop_acceso_creador (acceso_creador_id)');
        }
        if (!tenant_inmob_tabla_columna_existe($conexion, 'indices', 'acceso_creador_id')) {
            @mysqli_query($conexion, "ALTER TABLE indices ADD COLUMN acceso_creador_id INT(11) NOT NULL DEFAULT 0 COMMENT '0=principal'");
        }
        $done = true;
        tenant_inmob_reparar_acceso_creador_huerfanos($conexion);
    }

    function tenant_inmob_tabla_existe($conexion, string $tabla): bool
    {
        $t = preg_replace('/[^a-z0-9_]/i', '', $tabla);
        if ($t === '') {
            return false;
        }
        $r = mysqli_query($conexion, "SHOW TABLES LIKE '" . mysqli_real_escape_string($conexion, $t) . "'");

        return $r && mysqli_num_rows($r) > 0;
    }

    /**
     * Tras importar desde otro servidor, acceso_creador_id puede apuntar a un id que no existe en accesos.
     * Reasigna esas filas al id actual del usuario 'sofia' para que el filtro de ámbito las encuentre.
     * También propaga el ámbito a personas (NULL) ligadas a propiedades/alquileres ya marcados como Sofía (export BGH).
     */
    function tenant_inmob_reparar_acceso_creador_huerfanos($conexion): void
    {
        static $reparado = false;
        if ($reparado) {
            return;
        }
        $reparado = true;
        $sid = tenant_inmob_id_acceso_sofia_efectivo($conexion);
        if ($sid <= 0) {
            return;
        }
        $sid = (int) $sid;
        $tUsu = tenant_inmob_tabla_columna_existe($conexion, 'usuarios', 'acceso_creador_id');
        $tProp = tenant_inmob_tabla_columna_existe($conexion, 'propiedades', 'acceso_creador_id');
        $tIdx = tenant_inmob_tabla_columna_existe($conexion, 'indices', 'acceso_creador_id');
        if ($tUsu) {
            @mysqli_query($conexion, "UPDATE usuarios u LEFT JOIN accesos a ON a.id = u.acceso_creador_id SET u.acceso_creador_id = $sid WHERE u.acceso_creador_id IS NOT NULL AND a.id IS NULL");
        }
        if ($tProp) {
            @mysqli_query($conexion, "UPDATE propiedades p LEFT JOIN accesos a ON a.id = p.acceso_creador_id SET p.acceso_creador_id = $sid WHERE p.acceso_creador_id IS NOT NULL AND a.id IS NULL");
        }
        if ($tIdx) {
            @mysqli_query($conexion, "UPDATE indices i LEFT JOIN accesos a ON a.id = i.acceso_creador_id SET i.acceso_creador_id = $sid WHERE i.acceso_creador_id IS NOT NULL AND i.acceso_creador_id <> 0 AND a.id IS NULL");
        }
        // Propietarios con acceso_creador_id NULL pero propiedad ya en ámbito Sofía (típico export BGH)
        if ($tUsu && $tProp) {
            @mysqli_query($conexion, "UPDATE usuarios u INNER JOIN propiedades p ON p.propietario_id = u.id AND p.acceso_creador_id = $sid SET u.acceso_creador_id = $sid WHERE u.id <> 1 AND (u.acceso_creador_id IS NULL OR u.acceso_creador_id <> $sid)");
        }
        // Inquilinos/codeudores en alquileres de propiedades del ámbito Sofía (personas aún NULL en export)
        $tAlq = tenant_inmob_tabla_existe($conexion, 'alquileres');
        if ($tUsu && $tProp && $tAlq) {
            @mysqli_query($conexion, "UPDATE usuarios u SET u.acceso_creador_id = $sid WHERE u.id <> 1 AND u.acceso_creador_id IS NULL AND EXISTS (
                SELECT 1 FROM alquileres a
                INNER JOIN propiedades p ON p.propiedad_id = a.propiedad_id AND p.acceso_creador_id = $sid
                WHERE a.inquilino1_id = u.id OR (a.inquilino2_id IS NOT NULL AND a.inquilino2_id = u.id)
                   OR a.codeudor1_id = u.id OR (a.codeudor2_id IS NOT NULL AND a.codeudor2_id = u.id)
            )");
        }
        // Etiquetas antiguas de otro servidor apuntaban a otro id numérico en accesos: alinear al id de sesión actual (Sofía)
        if ($tUsu) {
            @mysqli_query($conexion, "UPDATE usuarios u INNER JOIN accesos a ON a.id = u.acceso_creador_id SET u.acceso_creador_id = $sid WHERE LOWER(TRIM(a.usuario)) = 'sofia' AND a.id <> $sid");
        }
        if ($tProp) {
            @mysqli_query($conexion, "UPDATE propiedades p INNER JOIN accesos a ON a.id = p.acceso_creador_id SET p.acceso_creador_id = $sid WHERE LOWER(TRIM(a.usuario)) = 'sofia' AND a.id <> $sid");
        }
        if ($tIdx) {
            @mysqli_query($conexion, "UPDATE indices i INNER JOIN accesos a ON a.id = i.acceso_creador_id SET i.acceso_creador_id = $sid WHERE i.acceso_creador_id <> 0 AND LOWER(TRIM(a.usuario)) = 'sofia' AND a.id <> $sid");
        }
        // Opcional .env: marcar import BGH con usuarios/propiedades aún en NULL (id mínimo = primer id del respaldo)
        $env = $GLOBALS['HHH_ENV_CACHE'] ?? [];
        if (is_array($env)) {
            $minU = (int) ($env['SOFIA_AMBITO_MIN_USUARIO_ID'] ?? 0);
            $minP = (int) ($env['SOFIA_AMBITO_MIN_PROPIEDAD_ID'] ?? 0);
            if ($minU > 0 && $tUsu) {
                @mysqli_query($conexion, "UPDATE usuarios SET acceso_creador_id = $sid WHERE id >= $minU AND id <> 1 AND acceso_creador_id IS NULL");
            }
            if ($minP > 0 && $tProp) {
                @mysqli_query($conexion, "UPDATE propiedades SET acceso_creador_id = $sid WHERE propiedad_id >= $minP AND acceso_creador_id IS NULL");
            }
        }
    }

    function tenant_inmob_id_acceso_sofia_bd($conexion): int
    {
        static $id = null;
        if ($id !== null) {
            return $id;
        }
        $r = mysqli_query($conexion, "SELECT id FROM accesos WHERE LOWER(TRIM(usuario)) = 'sofia' LIMIT 1");
        if ($r && $row = mysqli_fetch_assoc($r)) {
            $id = (int) $row['id'];
        } else {
            $id = 0;
        }

        return $id;
    }

    /**
     * Id numérico del ámbito Sofía para filtrar acceso_creador_id: el id de la fila accesos con la que se inició sesión.
     * Así coincide con los datos aunque el export traiga otro id antiguo (la reparación lo alinea a este id).
     */
    function tenant_inmob_id_acceso_sofia_efectivo($conexion): int
    {
        if (tenant_inmob_es_sofia()) {
            $sess = (int) ($_SESSION['acceso_id'] ?? 0);
            if ($sess > 0) {
                return $sess;
            }
        }
        $sid = tenant_inmob_id_acceso_sofia_bd($conexion);

        return $sid > 0 ? $sid : 0;
    }

    /** Fragmento SQL para filas de usuarios visibles según sesión (sin alias de tabla). */
    function tenant_inmob_sql_usuarios_sin_alias($conexion): string
    {
        tenant_inmob_asegurar_esquema($conexion);
        if (tenant_inmob_es_sofia()) {
            $sid = tenant_inmob_id_acceso_sofia_efectivo($conexion);
            if ($sid <= 0) {
                return '1=0';
            }

            return 'acceso_creador_id = ' . (int) $sid;
        }
        $sid = tenant_inmob_id_acceso_sofia_bd($conexion);
        if ($sid <= 0) {
            return '1=1';
        }
        // Sistema principal (p. ej. adminhugo): solo personas sin ámbito (no mezclar con datos de Sofía).
        return 'acceso_creador_id IS NULL';
    }

    /** Con prefijo de alias (ej. u.acceso_creador_id). */
    function tenant_inmob_sql_usuarios($conexion, string $alias = 'u'): string
    {
        $base = tenant_inmob_sql_usuarios_sin_alias($conexion);
        if ($alias === '') {
            return $base;
        }
        return preg_replace('/\bacceso_creador_id\b/', $alias . '.acceso_creador_id', $base);
    }

    function tenant_inmob_sql_propiedades($conexion, string $alias = 'p'): string
    {
        tenant_inmob_asegurar_esquema($conexion);
        if (tenant_inmob_es_sofia()) {
            $sid = tenant_inmob_id_acceso_sofia_efectivo($conexion);
            if ($sid <= 0) {
                return '1=0';
            }

            return "$alias.acceso_creador_id = " . (int) $sid;
        }
        $sid = tenant_inmob_id_acceso_sofia_bd($conexion);
        if ($sid <= 0) {
            return '1=1';
        }
        return "$alias.acceso_creador_id IS NULL";
    }

    function tenant_inmob_indices_acceso_creador_valor($conexion): int
    {
        if (!tenant_inmob_es_sofia()) {
            return 0;
        }

        return tenant_inmob_id_acceso_sofia_efectivo($conexion);
    }

    function tenant_inmob_usuario_id_visible($conexion, int $usuario_id): bool
    {
        if ($usuario_id <= 0) {
            return false;
        }
        if ($usuario_id === -99) {
            return !tenant_inmob_es_sofia();
        }
        $w = tenant_inmob_sql_usuarios($conexion, 'u');
        $r = mysqli_query($conexion, "SELECT 1 FROM usuarios u WHERE u.id = $usuario_id AND ($w) LIMIT 1");
        return $r && mysqli_num_rows($r) > 0;
    }

    function tenant_inmob_propiedad_id_visible($conexion, int $propiedad_id): bool
    {
        if ($propiedad_id <= 0) {
            return false;
        }
        $w = tenant_inmob_sql_propiedades($conexion, 'p');
        $r = mysqli_query($conexion, "SELECT 1 FROM propiedades p WHERE p.propiedad_id = $propiedad_id AND ($w) LIMIT 1");
        return $r && mysqli_num_rows($r) > 0;
    }

    /** Valor a guardar en propiedades.acceso_creador_id al insertar (NULL = principal). */
    function tenant_inmob_propiedad_acceso_creador_insert_sql($conexion): string
    {
        if (tenant_inmob_es_sofia()) {
            $sid = tenant_inmob_id_acceso_sofia_efectivo($conexion);
            if ($sid <= 0) {
                return 'NULL';
            }

            return (string) (int) $sid;
        }
        return 'NULL';
    }

    /** Valor a guardar en usuarios.acceso_creador_id al insertar persona. */
    function tenant_inmob_usuario_acceso_creador_insert_sql($conexion): string
    {
        if (tenant_inmob_es_sofia()) {
            $sid = tenant_inmob_id_acceso_sofia_efectivo($conexion);
            if ($sid <= 0) {
                return 'NULL';
            }

            return (string) (int) $sid;
        }
        return 'NULL';
    }

    /** Lista de scripts permitidos para el usuario Sofía (resto del sistema bloqueado por URL). */
    function tenant_inmob_sofia_scripts_permitidos(): array
    {
        return [
            'index.php',
            'logout.php',
            'respaldar_al_salir.php',
            'registro.php',
            'procesar.php',
            'editar_usuario.php',
            'actualizar_usuario.php',
            'propiedades.php',
            'nueva_propiedad.php',
            'editar_propiedad.php',
            'ver_propiedad.php',
            'guardar_propiedad.php',
            'actualizar_propiedad.php',
            'eliminar_propiedad.php',
            'imprimir_propiedades.php',
            'fotos_propiedad_json.php',
            'contrato_alquiler.php',
            'guardar_contrato.php',
            'generar_word_contrato.php',
            'finalizar_contrato.php',
            'abm_indices.php',
            'obtener_movimientos.php',
            'obtener_saldo_usuario.php',
            'obtener_propiedades_propietario.php',
            'obtener_resumen_consorcio.php',
            'obtener_siguiente_mes_liq_exp.php',
            'guardar_movimiento.php',
            'eliminar_movimiento.php',
            'verificar_clave_borrado.php',
            'imprimir_movimientos.php',
            'actualizar_fecha_movimiento.php',
            'generar_recibo_word.php',
            'generar_recibo_cobro_caja.php',
            'guardar_cobro_caja.php',
            'liquidar_expensas_consorcio.php',
            'guardar_cobro_expensa.php',
            'imprimir_expensas_consorcio.php',
            'eliminar_liq_expensas_periodo.php',
            'borrar_todos_liq_expensas.php',
            'buscar_personas.php',
        ];
    }

    function tenant_inmob_aplicar_restriccion_sofia(): void
    {
        if (!tenant_inmob_es_sofia()) {
            return;
        }
        $script = basename($_SERVER['SCRIPT_NAME'] ?? '');
        if ($script === '') {
            return;
        }
        $permitidos = tenant_inmob_sofia_scripts_permitidos();
        if (in_array($script, $permitidos, true)) {
            return;
        }
        header('Location: index.php?msg=sin_permiso');
        exit;
    }
}
