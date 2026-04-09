<?php
/**
 * Ámbito inmobiliario: usuario de acceso "sofia" (case-insensitive) vs sistema principal.
 * - Sofía: solo filas con acceso_creador_id = id de su fila en `accesos`.
 * - Resto (adminhugo, etc.): solo filas con acceso_creador_id IS NULL (datos HHH; no ven el ámbito de Sofía).
 * Si no existe usuario "sofia" en `accesos`, no se aplica filtro (compatibilidad).
 */

if (!function_exists('tenant_inmob_es_sofia')) {
    function tenant_inmob_es_sofia(): bool
    {
        return strcasecmp((string)($_SESSION['acceso_usuario'] ?? ''), 'sofia') === 0;
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
    }

    function tenant_inmob_id_acceso_sofia_bd($conexion): int
    {
        static $id = null;
        if ($id !== null) {
            return $id;
        }
        $r = mysqli_query($conexion, "SELECT id FROM accesos WHERE LOWER(TRIM(usuario)) = 'sofia' LIMIT 1");
        if ($r && $row = mysqli_fetch_assoc($r)) {
            $id = (int)$row['id'];
        } else {
            $id = 0;
        }
        return $id;
    }

    /** Fragmento SQL para filas de usuarios visibles según sesión (sin alias de tabla). */
    function tenant_inmob_sql_usuarios_sin_alias($conexion): string
    {
        tenant_inmob_asegurar_esquema($conexion);
        if (tenant_inmob_es_sofia()) {
            $aid = (int)($_SESSION['acceso_id'] ?? 0);
            return "acceso_creador_id = $aid";
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
            $aid = (int)($_SESSION['acceso_id'] ?? 0);
            return "$alias.acceso_creador_id = $aid";
        }
        $sid = tenant_inmob_id_acceso_sofia_bd($conexion);
        if ($sid <= 0) {
            return '1=1';
        }
        return "$alias.acceso_creador_id IS NULL";
    }

    function tenant_inmob_indices_acceso_creador_valor($conexion): int
    {
        return tenant_inmob_es_sofia() ? (int)($_SESSION['acceso_id'] ?? 0) : 0;
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
            return (string)(int)($_SESSION['acceso_id'] ?? 0);
        }
        return 'NULL';
    }

    /** Valor a guardar en usuarios.acceso_creador_id al insertar persona. */
    function tenant_inmob_usuario_acceso_creador_insert_sql($conexion): string
    {
        if (tenant_inmob_es_sofia()) {
            return (string)(int)($_SESSION['acceso_id'] ?? 0);
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
