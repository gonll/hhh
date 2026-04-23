<?php
include 'db.php';
include 'verificar_sesion.php';

// Debug: activar en servidor con ?debug=1 para ver errores PHP (desactivar en producción)
if (!empty($_GET['debug']) && $_GET['debug'] === '1') {
    ini_set('display_errors', 1);
    error_reporting(E_ALL);
}

if (!defined('DESDE_CEL')) define('DESDE_CEL', false);
$desde_cel = DESDE_CEL;
if ($desde_cel) {
    require_once __DIR__ . '/helpers_movil.php';
    if (empty($_SESSION['vista_escritorio_movil']) && hh_es_user_agent_movil()) {
        if (!hh_movil_ir_partes_desde_cel()) {
            header('Location: ctacel.php');
            exit;
        }
    }
}
if (!$desde_cel && isset($_SESSION['acceso_nivel']) && $_SESSION['acceso_nivel'] < 2) {
    header('Location: index.php?msg=sin_permiso');
    exit;
}
if ($desde_cel) {
    $form_action_url = 'partes_desde_cel.php';
    $titulo_pagina = 'Partes desde cel';
} else {
    $form_action_url = 'gestionar_finca.php';
    $titulo_pagina = 'Gestión Finca - Partes Diarios de Trabajo (PDT)';
}
$es_nivel_0 = (isset($_SESSION['acceso_nivel']) && (int)$_SESSION['acceso_nivel'] === 0);
// Siempre mostrar vista completa (botones Modificar, Eliminar, Cargar CC) - igual en servidor y PC
$mostrar_vista_completa = true;

// Verificar y migrar tabla pdt (misma estructura en localhost y servidor)
$res_check = mysqli_query($conexion, "SHOW TABLES LIKE 'pdt'");
if (!$res_check || mysqli_num_rows($res_check) == 0) {
    $sql_create = "CREATE TABLE IF NOT EXISTS pdt (
        id INT AUTO_INCREMENT PRIMARY KEY,
        usuario_id INT NOT NULL,
        tipo_horas VARCHAR(50) NOT NULL DEFAULT 'Horas Comunes',
        tractor VARCHAR(100) NULL,
        fecha DATE NOT NULL,
        horas DECIMAL(5,2) DEFAULT 0.00,
        cant_gasoil DECIMAL(6,2) NULL,
        cambio_aceite TINYINT(1) DEFAULT 0,
        en_cc TINYINT(1) DEFAULT 0,
        observaciones TEXT NULL,
        finca VARCHAR(200) NULL,
        fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        fecha_modificacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
        INDEX idx_usuario (usuario_id),
        INDEX idx_fecha (fecha)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci";
    mysqli_query($conexion, $sql_create);
} else {
    // Migración: agregar columnas faltantes (servidor puede tener tabla antigua)
    $cols_check = ['cant_gasoil'=>'horas','cambio_aceite'=>'cant_gasoil','en_cc'=>'cambio_aceite','tipo_horas'=>'usuario_id','tractor'=>'tipo_horas'];
    $cols_def = [
        'cant_gasoil'=>'DECIMAL(6,2) NULL',
        'cambio_aceite'=>'TINYINT(1) DEFAULT 0',
        'en_cc'=>'TINYINT(1) DEFAULT 0',
        'tipo_horas'=>"VARCHAR(50) NOT NULL DEFAULT 'Horas Comunes'",
        'tractor'=>'VARCHAR(100) NULL'
    ];
    foreach ($cols_check as $col => $after) {
        $r = mysqli_query($conexion, "SHOW COLUMNS FROM pdt LIKE '$col'");
        if (!$r || mysqli_num_rows($r) == 0) {
            mysqli_query($conexion, "ALTER TABLE pdt ADD COLUMN `$col` {$cols_def[$col]} AFTER `$after`");
        }
    }
}
$r_finca_col = mysqli_query($conexion, "SHOW COLUMNS FROM pdt LIKE 'finca'");
if (!$r_finca_col || mysqli_num_rows($r_finca_col) == 0) {
    mysqli_query($conexion, "ALTER TABLE pdt ADD COLUMN finca VARCHAR(200) NULL");
}

// Tabla gasoil: + carga sisterna, - consumo tractor
$res_gasoil = mysqli_query($conexion, "SHOW TABLES LIKE 'gasoil'");
if (!$res_gasoil || mysqli_num_rows($res_gasoil) == 0) {
    $sql_gasoil = "CREATE TABLE IF NOT EXISTS gasoil (
        id INT AUTO_INCREMENT PRIMARY KEY,
        fecha DATE NOT NULL,
        cantidad DECIMAL(10,2) NOT NULL,
        concepto VARCHAR(255) NULL,
        pdt_id INT NULL,
        fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_fecha (fecha),
        INDEX idx_pdt (pdt_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci";
    mysqli_query($conexion, $sql_gasoil);
}

$mensaje = '';
$pdt_edit = null;
$preseleccionar_usuario_id = null;

// Si llegamos por GET con guardado=1 (redirect tras POST), mostrar mensaje y preservar usuario
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['guardado']) && $_GET['guardado'] == '1') {
    $mensaje = 'Parte guardado.';
    if (!empty($_GET['usuario'])) {
        $preseleccionar_usuario_id = (int)$_GET['usuario'];
    }
}
// Redirect desde cargar_pdt_cc.php
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['cc'])) {
    if ($_GET['cc'] === 'ok') $mensaje = 'Trabajos CC=NO cargados en cuenta corriente correctamente.';
    elseif ($_GET['cc'] === 'sin_usuario') $mensaje = 'Seleccione un usuario.';
    elseif ($_GET['cc'] === 'sin_datos' || $_GET['cc'] === 'sin_horas') $mensaje = 'No hay trabajos con CC=NO para cargar.';
    elseif ($_GET['cc'] === 'sin_tabla_salarial') $mensaje = 'Falta configurar tabla salarial.';
    elseif ($_GET['cc'] === 'error') $mensaje = 'Error: ' . ($_GET['msg'] ?? '');
    if (!empty($_GET['usuario'])) $preseleccionar_usuario_id = (int)$_GET['usuario'];
}
// Mensaje tras carga gasoil (redirect GET)
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['gasoil_ok']) && $_GET['gasoil_ok'] === '1') {
    $mensaje = 'Carga de gasoil en cisterna registrada.';
}

// Procesar acciones
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Carga de gasoil en cisterna (+)
    if (isset($_POST['carga_gasoil_sisterna']) && isset($_POST['fecha_carga']) && isset($_POST['cantidad_carga'])) {
        $fecha_c = mysqli_real_escape_string($conexion, trim($_POST['fecha_carga']));
        $cant_c = (float)($_POST['cantidad_carga'] ?? 0);
        if ($fecha_c !== '' && $cant_c > 0) {
            $sql_ins = "INSERT INTO gasoil (fecha, cantidad, concepto) VALUES ('$fecha_c', $cant_c, 'Carga sisterna')";
            if (mysqli_query($conexion, $sql_ins)) {
                header('Location: ' . $form_action_url . '?gasoil_ok=1');
                exit;
            } else {
                $mensaje = 'Falta dato o corregir.';
            }
        } else {
            $mensaje = 'Falta dato o corregir.';
        }
    }
    if (isset($_POST['guardar'])) {
        $usuario_id = (int)($_POST['usuario_id'] ?? 0);
        if ($usuario_id < 1) {
            $mensaje = 'Falta dato o corregir.';
        } else {
            // Comprobar que el usuario existe (evitar error de clave foránea)
            $check = mysqli_query($conexion, "SELECT id FROM usuarios WHERE id = $usuario_id LIMIT 1");
            if (!$check || mysqli_num_rows($check) === 0) {
                $mensaje = 'Falta dato o corregir.';
            } else {
        $tractor_post = trim($_POST['tractor'] ?? '');
        $tipo_horas = ($tractor_post !== '' && $tractor_post !== 'Horas Comunes') ? 'Horas tractos' : 'Horas Comunes';
        $tractor = ($tipo_horas === 'Horas tractos') ? mysqli_real_escape_string($conexion, $tractor_post) : NULL;
        $fecha = trim($_POST['fecha'] ?? '');
        $horas = isset($_POST['horas']) && $_POST['horas'] !== '' ? (int)$_POST['horas'] : null;
        $cant_gasoil = ($tipo_horas === 'Horas tractos' && isset($_POST['cant_gasoil']) && $_POST['cant_gasoil'] !== '')
            ? (float)$_POST['cant_gasoil'] : NULL;
        $cambio_aceite = ($tipo_horas === 'Horas tractos' && isset($_POST['cambio_aceite'])) ? 1 : 0;
        $observaciones = mysqli_real_escape_string($conexion, trim($_POST['observaciones'] ?? ''));
        $finca_sel = trim($_POST['finca_sel'] ?? '');
        $finca_nueva = trim($_POST['finca_nueva'] ?? '');
        $finca_raw = ($finca_sel === '__otro__') ? $finca_nueva : $finca_sel;
        $finca_esc = $finca_raw !== '' ? mysqli_real_escape_string($conexion, $finca_raw) : '';

        // Validar campos obligatorios (todos menos observaciones)
        $error_campo = '';
        if ($fecha === '') {
            $error_campo = 'Fecha es obligatoria.';
        } elseif ($horas === null) {
            $error_campo = 'Cantidad / Cantidad horas es obligatoria.';
        } elseif ($horas < 0) {
            $error_campo = 'Cantidad no puede ser negativa.';
        } elseif ($tipo_horas === 'Horas tractos') {
            if (empty($tractor)) {
                $error_campo = 'Tractor es obligatorio cuando el tipo de trabajo es Horas tractos.';
            } elseif ($cant_gasoil === NULL) {
                $error_campo = 'Cant Gasoil es obligatorio cuando el tipo de trabajo es Horas tractos.';
            }
        }
        if ($error_campo === '' && $finca_sel === '__otro__' && $finca_nueva === '') {
            $error_campo = 'Escriba el nombre de la nueva finca o elija una de la lista.';
        }
        if ($error_campo !== '') {
            $mensaje = 'Falta dato o corregir.';
        } else {
        $fecha = mysqli_real_escape_string($conexion, $fecha);
        $horas = (int)$horas;
        
        if (isset($_POST['pdt_id']) && $_POST['pdt_id'] > 0) {
            // Modificar - mantener en_cc actual si no se envía
            $pdt_id = (int)$_POST['pdt_id'];
            $res_actual = mysqli_query($conexion, "SELECT en_cc FROM pdt WHERE id = $pdt_id LIMIT 1");
            $en_cc_actual = 0;
            if ($res_actual && $row_actual = mysqli_fetch_assoc($res_actual)) {
                $en_cc_actual = (int)$row_actual['en_cc'];
            }
            $sql = "UPDATE pdt SET usuario_id = $usuario_id, tipo_horas = '$tipo_horas', tractor = " . 
                   ($tractor ? "'$tractor'" : "NULL") . ", fecha = '$fecha', horas = $horas, " .
                   "cant_gasoil = " . ($cant_gasoil !== NULL ? $cant_gasoil : "NULL") . ", cambio_aceite = $cambio_aceite, en_cc = $en_cc_actual, observaciones = '$observaciones', finca = " .
                   ($finca_esc !== '' ? "'$finca_esc'" : "NULL") . " WHERE id = $pdt_id";
            if (mysqli_query($conexion, $sql)) {
                // Sincronizar gasoil cisterna: al modificar el parte, actualizar el movimiento en gasoil
                if ($tipo_horas !== 'Horas tractos' || $cant_gasoil === NULL || $cant_gasoil <= 0) {
                    mysqli_query($conexion, "DELETE FROM gasoil WHERE pdt_id = $pdt_id");
                } else {
                    $cant_neg = - (float)$cant_gasoil;
                    $concepto_tractor = 'Tractor' . ($tractor ? ' ' . mysqli_real_escape_string($conexion, $tractor) : '');
                    $existe = mysqli_query($conexion, "SELECT id FROM gasoil WHERE pdt_id = $pdt_id LIMIT 1");
                    if ($existe && mysqli_num_rows($existe) > 0) {
                        mysqli_query($conexion, "UPDATE gasoil SET fecha = '$fecha', cantidad = $cant_neg, concepto = '$concepto_tractor' WHERE pdt_id = $pdt_id");
                    } else {
                        mysqli_query($conexion, "INSERT INTO gasoil (fecha, cantidad, concepto, pdt_id) VALUES ('$fecha', $cant_neg, '$concepto_tractor', $pdt_id)");
                    }
                }
                $mensaje = 'Parte guardado.';
                $preseleccionar_usuario_id = $usuario_id;
                $_SESSION['pdt_ultimo_tractor'] = $tipo_horas === 'Horas tractos' ? $tractor_post : 'Horas Comunes';
                if ($finca_esc !== '') {
                    $_SESSION['pdt_ultima_finca'] = $finca_raw;
                }
            } else {
                $mensaje = 'Falta dato o corregir.';
            }
        } else {
            // Alta - en_cc siempre empieza en 0
            $sql = "INSERT INTO pdt (usuario_id, tipo_horas, tractor, fecha, horas, cant_gasoil, cambio_aceite, en_cc, observaciones, finca) 
                    VALUES ($usuario_id, '$tipo_horas', " . ($tractor ? "'$tractor'" : "NULL") . ", '$fecha', $horas, " .
                    ($cant_gasoil !== NULL ? $cant_gasoil : "NULL") . ", $cambio_aceite, 0, '$observaciones', " .
                    ($finca_esc !== '' ? "'$finca_esc'" : "NULL") . ")";
            if (mysqli_query($conexion, $sql)) {
                $pdt_id_nuevo = (int)mysqli_insert_id($conexion);
                if ($tipo_horas === 'Horas tractos' && $cant_gasoil !== NULL && $cant_gasoil > 0) {
                    $cant_neg = - (float)$cant_gasoil;
                    $concepto_tractor = 'Tractor' . ($tractor ? ' ' . mysqli_real_escape_string($conexion, $tractor) : '');
                    mysqli_query($conexion, "INSERT INTO gasoil (fecha, cantidad, concepto, pdt_id) VALUES ('$fecha', $cant_neg, '$concepto_tractor', $pdt_id_nuevo)");
                }
                $mensaje = 'Parte guardado.';
                $preseleccionar_usuario_id = $usuario_id;
                $_SESSION['pdt_ultimo_tractor'] = $tipo_horas === 'Horas tractos' ? $tractor_post : 'Horas Comunes';
                if ($finca_esc !== '') {
                    $_SESSION['pdt_ultima_finca'] = $finca_raw;
                }
            } else {
                $mensaje = 'Falta dato o corregir.';
            }
        }
        }
            }
        }
    } elseif (isset($_POST['eliminar'])) {
        $pdt_id = (int)$_POST['pdt_id'];
        mysqli_query($conexion, "DELETE FROM gasoil WHERE pdt_id = $pdt_id");
        $sql = "DELETE FROM pdt WHERE id = $pdt_id";
        if (mysqli_query($conexion, $sql)) {
            $mensaje = 'Parte diario de trabajo eliminado correctamente.';
        } else {
            $mensaje = 'Falta dato o corregir.';
        }
    } elseif (isset($_POST['eliminar_todos'])) {
        $where = "COALESCE(en_cc, 0) = 0";
        if ($desde_cel) {
            $where .= " AND fecha >= DATE_SUB(CURDATE(), INTERVAL 2 DAY)";
        }
        mysqli_query($conexion, "DELETE FROM gasoil WHERE pdt_id IN (SELECT id FROM pdt WHERE $where)");
        $res_del = mysqli_query($conexion, "DELETE FROM pdt WHERE $where");
        if ($res_del) {
            $n = mysqli_affected_rows($conexion);
            $mensaje = $n > 0 ? "Se eliminaron $n parte(s) no cargado(s) en CC." : 'No hay partes elegibles para eliminar (solo se eliminan los no cargados en CC' . ($desde_cel ? ', de los últimos 2 días' : '') . ').';
        } else {
            $mensaje = 'Falta dato o corregir.';
        }
    } elseif (isset($_POST['cargar_cc'])) {
        $pdt_id = (int)$_POST['pdt_id'];
        $res_pdt = mysqli_query($conexion, "SELECT usuario_id, tipo_horas, tractor, fecha, horas FROM pdt WHERE id = $pdt_id LIMIT 1");
        if (!$res_pdt || !$pdt_row = mysqli_fetch_assoc($res_pdt)) {
            $mensaje = 'PDT no encontrado.';
        } else {
            $usuario_id = (int)$pdt_row['usuario_id'];
            $tipo_horas = trim($pdt_row['tipo_horas'] ?? 'Horas Comunes');
            $tractor = trim($pdt_row['tractor'] ?? '');
            $fecha_pdt = $pdt_row['fecha'];
            $horas = (float)($pdt_row['horas'] ?? 0);
            $es_tractor = ($tipo_horas === 'Horas tractos' || stripos($tipo_horas, 'tracto') !== false);
            $res_ts = mysqli_query($conexion, "SELECT valor_hora_comun, valor_hora_tractor FROM tabla_salarial ORDER BY id DESC LIMIT 1");
            $precio = 0;
            if ($res_ts && $ts = mysqli_fetch_assoc($res_ts)) {
                $precio = $es_tractor ? (float)$ts['valor_hora_tractor'] : (float)$ts['valor_hora_comun'];
            }
            if ($precio <= 0) {
                $mensaje = 'Configure la tabla salarial antes de cargar en CC.';
            } elseif ($horas <= 0) {
                $mensaje = 'El PDT no tiene horas válidas.';
            } else {
                $sql_up = "UPDATE pdt SET en_cc = 1 WHERE id = $pdt_id";
                if (!mysqli_query($conexion, $sql_up)) {
                    $mensaje = 'Error al actualizar PDT.';
                } else {
                    $tipo_trabajo = $es_tractor ? ('Horas tractos' . ($tractor ? ' ' . $tractor : '')) : 'Horas Comunes';
                    $cantidad_str = number_format($horas, 2, ',', '.');
                    $precio_str = number_format($precio, 2, ',', '.');
                    $mes_anio = date('m/Y', strtotime($fecha_pdt));
                    $concepto = 'Trabajo: ' . $tipo_trabajo . ', Cantidad ' . $cantidad_str . ', y valor ' . $precio_str;
                    $concepto = mysqli_real_escape_string($conexion, $concepto);
                    $comprobante = mysqli_real_escape_string($conexion, 'trabajo');
                    $referencia = mysqli_real_escape_string($conexion, $mes_anio);
                    $monto = round($horas * $precio, 2);
                    $sql_cuenta = "INSERT INTO cuentas (usuario_id, fecha, concepto, comprobante, referencia, monto) 
                                   VALUES ($usuario_id, '$fecha_pdt', '$concepto', '$comprobante', '$referencia', $monto)";
                    if (mysqli_query($conexion, $sql_cuenta)) {
                        $mensaje = 'PDT cargado en CC e ingreso registrado en cuenta.';
                    } else {
                        $mensaje = 'PDT marcado en CC pero error al cargar ingreso: ' . mysqli_error($conexion);
                    }
                }
            }
        }
    } elseif (isset($_POST['editar'])) {
        $pdt_id = (int)$_POST['pdt_id'];
        $res = mysqli_query($conexion, "SELECT * FROM pdt WHERE id = $pdt_id LIMIT 1");
        if ($res && $row = mysqli_fetch_assoc($res)) {
            $pdt_edit = $row;
        }
    }
    // Redirect POST->GET tras guardar: permite que flecha atrás del navegador vuelva al principal
    if ($mensaje === 'Parte guardado.') {
        $redir = $form_action_url . '?guardado=1';
        if (!empty($preseleccionar_usuario_id)) {
            $redir .= '&usuario=' . (int)$preseleccionar_usuario_id;
        }
        header('Location: ' . $redir);
        exit;
    }
}

// Valor por defecto para Horas Comunes / Tractor (último elegido)
$tractor_default = 'Horas Comunes';
if (!empty($_SESSION['pdt_ultimo_tractor'])) {
    $tr = $_SESSION['pdt_ultimo_tractor'];
    if ($tr === 'New Holland TM150') {
        $tr = 'T7.195/4CPAT';
    }
    if ($tr === 'Horas Comunes' || in_array($tr, ['John Deere 200 hp', 'John Deere 110 hp', 'T7.195/4CPAT', 'New Holland 7630', 'Massey Ferguson 1165'], true)) {
        $tractor_default = $tr;
    }
}

// Fincas: mismas opciones que hoja de ruta cosecha + las que ya figuran en PDT
$fincas_fijas = ['Finca 6', 'Finca 4', 'Finca 7', 'Finca 5', 'Finca 2', 'Finca 10', 'Finca 11', 'Finca 47', 'Finca 29', 'Galpon'];
$fincas_lista = $fincas_fijas;
$r_fincas_c = mysqli_query($conexion, "SELECT DISTINCT finca FROM cosecha_hojas_ruta WHERE finca != '' AND finca IS NOT NULL ORDER BY finca");
if ($r_fincas_c) {
    while ($row = mysqli_fetch_assoc($r_fincas_c)) {
        $fn = trim($row['finca'] ?? '');
        if ($fn !== '' && !in_array($fn, $fincas_lista, true)) {
            $fincas_lista[] = $fn;
        }
    }
}
$r_fincas_p = mysqli_query($conexion, "SELECT DISTINCT finca FROM pdt WHERE finca IS NOT NULL AND TRIM(finca) != '' ORDER BY finca");
if ($r_fincas_p) {
    while ($row = mysqli_fetch_assoc($r_fincas_p)) {
        $fn = trim($row['finca'] ?? '');
        if ($fn !== '' && !in_array($fn, $fincas_lista, true)) {
            $fincas_lista[] = $fn;
        }
    }
}

$finca_default = '';
if (!empty($_SESSION['pdt_ultima_finca'])) {
    $finca_default = trim((string)$_SESSION['pdt_ultima_finca']);
}
if ($finca_default === '') {
    $r_uf = mysqli_query($conexion, "SELECT finca FROM pdt WHERE finca IS NOT NULL AND TRIM(finca) != '' ORDER BY id DESC LIMIT 1");
    if ($r_uf && $rw = mysqli_fetch_assoc($r_uf)) {
        $finca_default = trim((string)($rw['finca'] ?? ''));
    }
}
if ($finca_default === '' && count($fincas_lista) > 0) {
    $finca_default = $fincas_lista[0];
}

$finca_actual = $pdt_edit ? trim((string)($pdt_edit['finca'] ?? '')) : $finca_default;
if ($finca_actual === '' && count($fincas_lista) > 0) {
    $finca_actual = $fincas_lista[0];
}
if ($finca_actual !== '' && !in_array($finca_actual, $fincas_lista, true)) {
    $fincas_lista[] = $finca_actual;
}
$es_otra_finca = ($finca_actual !== '' && !in_array($finca_actual, $fincas_fijas, true));
$finca_sel_val = $es_otra_finca ? '__otro__' : $finca_actual;
$finca_nueva_val = $es_otra_finca ? $finca_actual : '';

// Obtener lista de PDTs (siempre 200 registros, igual en servidor y PC)
// IMPORTANTE: subir esta versión al servidor para que muestre todos los registros
$lista_pdt = [];
$sql_lista = "SELECT p.id, p.usuario_id, p.tipo_horas, p.tractor, p.fecha, p.horas, p.cant_gasoil, p.cambio_aceite, p.en_cc, p.observaciones, p.finca, u.apellido AS usuario_nombre FROM pdt p INNER JOIN usuarios u ON u.id = p.usuario_id ORDER BY p.fecha DESC, p.id DESC LIMIT 200";
$res_lista = mysqli_query($conexion, $sql_lista);
if ($res_lista) {
    while ($row = mysqli_fetch_assoc($res_lista)) {
        $keys = array_map('strtolower', array_keys($row));
        $combined = @array_combine($keys, array_values($row));
        $lista_pdt[] = (is_array($combined) ? $combined : $row);
    }
}

// Obtener usuarios para el buscador (solo los que tienen "(FINCA)" en el apellido)
$sql_usuarios = "SELECT id, apellido FROM usuarios WHERE apellido LIKE '%(FINCA)%' ORDER BY apellido ASC";
$res_usuarios = mysqli_query($conexion, $sql_usuarios);
$usuarios = [];
while ($u = mysqli_fetch_assoc($res_usuarios)) {
    $usuarios[] = $u;
}

// Resumen de horas por usuario con CC=NO (para mostrar junto al botón Guardar)
$resumen_horas_cc_no = [];
$sql_resumen = "SELECT usuario_id,
    SUM(CASE WHEN tipo_horas = 'Horas Comunes' AND COALESCE(en_cc,0) = 0 THEN horas ELSE 0 END) AS horas_comunes,
    SUM(CASE WHEN tipo_horas = 'Horas tractos' AND COALESCE(en_cc,0) = 0 THEN horas ELSE 0 END) AS horas_tractor
    FROM pdt GROUP BY usuario_id";
$res_resumen = mysqli_query($conexion, $sql_resumen);
if ($res_resumen) {
    while ($r = mysqli_fetch_assoc($res_resumen)) {
        $resumen_horas_cc_no[(int)$r['usuario_id']] = [
            'horas_comunes' => (float)$r['horas_comunes'],
            'horas_tractor' => (float)$r['horas_tractor']
        ];
    }
}

// Gasoil en cisterna (suma de movimientos: + carga, - tractor)
$gasoil_en_sisterna = 0;
$r_g = mysqli_query($conexion, "SELECT COALESCE(SUM(cantidad), 0) AS total FROM gasoil");
if ($r_g && $f_g = mysqli_fetch_assoc($r_g)) {
    $gasoil_en_sisterna = (float)$f_g['total'];
}

// Últimas 2 cargas en cisterna (solo cantidad > 0, no tractores)
$ultimas_cargas_gasoil = [];
$r_cargas = mysqli_query($conexion, "SELECT fecha, cantidad FROM gasoil WHERE cantidad > 0 ORDER BY fecha DESC, id DESC LIMIT 2");
if ($r_cargas) {
    while ($c = mysqli_fetch_assoc($r_cargas)) {
        $ultimas_cargas_gasoil[] = ['fecha' => $c['fecha'], 'cantidad' => (float)$c['cantidad']];
    }
}

// Por tractor: horas y gasoil desde último cambio de aceite
$tractores_lista = array('John Deere 200 hp', 'John Deere 110 hp', 'T7.195/4CPAT', 'New Holland 7630', 'Massey Ferguson 1165');
$tractor_desde_cambio = array();
$nh150_esc = mysqli_real_escape_string($conexion, 'New Holland TM150');
foreach ($tractores_lista as $tr) {
    if ($tr === 'T7.195/4CPAT') {
        $t7_esc = mysqli_real_escape_string($conexion, 'T7.195/4CPAT');
        $where_tr = "(tractor = '$t7_esc' OR tractor = '$nh150_esc')";
    } else {
        $tr_esc = mysqli_real_escape_string($conexion, $tr);
        $where_tr = "tractor = '$tr_esc'";
    }
    $ultimo = mysqli_query($conexion, "SELECT id, fecha FROM pdt WHERE $where_tr AND cambio_aceite = 1 ORDER BY fecha DESC, id DESC LIMIT 1");
    $horas = 0;
    $gasoil = 0;
    if ($ultimo && $row_ult = mysqli_fetch_assoc($ultimo)) {
        $id_ult = (int)$row_ult['id'];
        $fecha_ult = mysqli_real_escape_string($conexion, $row_ult['fecha']);
        $suma = mysqli_query($conexion, "SELECT COALESCE(SUM(horas),0) AS h, COALESCE(SUM(cant_gasoil),0) AS g FROM pdt WHERE $where_tr AND (fecha > '$fecha_ult' OR (fecha = '$fecha_ult' AND id > $id_ult))");
        if ($suma && $s = mysqli_fetch_assoc($suma)) {
            $horas = (float)$s['h'];
            $gasoil = (float)$s['g'];
        }
    } else {
        $suma = mysqli_query($conexion, "SELECT COALESCE(SUM(horas),0) AS h, COALESCE(SUM(cant_gasoil),0) AS g FROM pdt WHERE $where_tr");
        if ($suma && $s = mysqli_fetch_assoc($suma)) {
            $horas = (float)$s['h'];
            $gasoil = (float)$s['g'];
        }
    }
    $tractor_desde_cambio[$tr] = array('horas' => $horas, 'gasoil' => $gasoil);
}

// Tabla salarial: crear si no existe y obtener últimos valores (hora común / hora tractor)
$res_ts = mysqli_query($conexion, "SHOW TABLES LIKE 'tabla_salarial'");
if (!$res_ts || mysqli_num_rows($res_ts) == 0) {
    $sql_ts = "CREATE TABLE IF NOT EXISTS tabla_salarial (
        id INT AUTO_INCREMENT PRIMARY KEY,
        valor_hora_comun DECIMAL(10,2) NOT NULL DEFAULT 0,
        valor_hora_tractor DECIMAL(10,2) NOT NULL DEFAULT 0,
        vigencia_desde DATE NULL,
        observaciones VARCHAR(255) NULL,
        fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_vigencia (vigencia_desde)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci";
    mysqli_query($conexion, $sql_ts);
}
$ultima_tabla_salarial = ['valor_hora_comun' => 0, 'valor_hora_tractor' => 0];
$res_ult = mysqli_query($conexion, "SELECT valor_hora_comun, valor_hora_tractor FROM tabla_salarial ORDER BY id DESC LIMIT 1");
if ($res_ult && $row_ult = mysqli_fetch_assoc($res_ult)) {
    $ultima_tabla_salarial['valor_hora_comun'] = (float)$row_ult['valor_hora_comun'];
    $ultima_tabla_salarial['valor_hora_tractor'] = (float)$row_ult['valor_hora_tractor'];
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($titulo_pagina) ?></title>
    <style>
        body { font-family: Arial, sans-serif; margin: 15px; background: #f5f5f5; font-size: 11px; }
        .container { max-width: 1400px; margin: 0 auto; background: white; padding: 15px; border-radius: 6px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); box-sizing: border-box; overflow-x: auto; overflow-y: visible; }
        h2 { color: #007bff; margin-top: 0; margin-bottom: 12px; font-size: 18px; }
        h3 { font-size: 14px; margin-top: 15px; margin-bottom: 10px; }
        .mensaje { padding: 8px; margin-bottom: 12px; border-radius: 3px; font-size: 11px; }
        .mensaje.ok { background: #d4edda; color: #155724; }
        .mensaje.ok.parte-guardado { font-size: 14px; font-weight: bold; padding: 10px 12px; }
        .cartel-parte-guardado { background: #c9a0a0; color: #4a3030; font-size: 14px; font-weight: bold; padding: 8px 14px; border-radius: 6px; border: 1px solid #b08080; }
        .mensaje.error { background: #f8d7da; color: #721c24; }
        .form-group { margin-bottom: 11px; }
        .form-row { display: flex; gap: 8px; margin-bottom: 12px; flex-wrap: nowrap; overflow-x: auto; overflow-y: visible; align-items: flex-end; }
        .form-row .form-group { flex: 1; margin-bottom: 0; }
        .form-row .form-group.checkbox-group { flex: 0 0 auto; display: flex; align-items: flex-end; padding-bottom: 0; }
        /* Campos compactos: tipo trabajo, fecha, cantidad - mitad de ancho */
        .form-row .form-group.form-group-compact { flex: 0 0 auto; max-width: 110px; min-width: 80px; }
        /* Observaciones: expandir ancho hasta el botón Guardar */
        .form-group-observaciones { flex: 1 1 0; min-width: 300px; align-self: flex-end; display: flex; flex-direction: column; justify-content: flex-end; }
        .form-group-observaciones textarea { width: 100%; font-size: 10px; padding: 4px 6px; height: 28px; min-height: 28px; resize: vertical; box-sizing: border-box; line-height: 1.2; }
        .form-row .form-group.form-group-compact select,
        .form-row .form-group.form-group-compact input { width: 100%; font-size: 10px; padding: 4px 6px; height: 28px; box-sizing: border-box; }
        label { display: block; margin-bottom: 4px; font-weight: bold; color: #333; font-size: 10px; }
        .form-row .form-group > label { min-height: 18px; }
        input[type="text"], input[type="number"], input[type="date"], select, textarea {
            width: 100%; padding: 5px; border: 1px solid #ddd; border-radius: 3px; box-sizing: border-box; font-size: 11px;
        }
        .buscador-usuario { position: relative; overflow: visible; }
        .buscador-usuario input[type="text"] { padding-right: 36px; }
        .buscador-usuario .ico-lupa { position: absolute; right: 10px; top: 50%; transform: translateY(-50%); pointer-events: none; color: #6c757d; font-size: 1rem; }
        #buscadorUsuario { width: 100%; padding: 5px 36px 5px 5px; border: 1px solid #ddd; border-radius: 3px; font-size: 11px; }
        #resultadosUsuario { position: absolute; top: 100%; left: 0; right: 0; background: white; border: 1px solid #ddd; border-top: none; max-height: 150px; overflow-y: auto; z-index: 1000; display: none; font-size: 11px; }
        #resultadosUsuario .usuario-item { padding: 6px; cursor: pointer; border-bottom: 1px solid #eee; }
        #resultadosUsuario .usuario-item:hover { background: #f0f0f0; }
        #resultadosUsuario .usuario-item.selected { background: #e7f3ff; }
        .btn { padding: 6px 12px; border: none; border-radius: 3px; cursor: pointer; font-weight: bold; font-size: 11px; }
        .btn-primary { background: #007bff; color: white; }
        .btn-primary:hover { background: #0056b3; }
        .btn-danger { background: #dc3545; color: white; }
        .btn-danger:hover { background: #c82333; }
        .btn-secondary { background: #6c757d; color: white; }
        .btn-secondary:hover { background: #5a6268; }
        .btn-success { background: #28a745; color: white; }
        .btn-success:hover { background: #218838; }
        table { width: 100%; border-collapse: collapse; margin-top: 15px; font-size: 10px; }
        th { background: #007bff; color: white; padding: 6px 6px; text-align: left; font-size: 11px; }
        td { padding: 5px 6px; border-bottom: 1px solid #eee; font-size: 11px; }
        tr:hover { background: #f8f9fa; }
        .tabla-listado-pdt { table-layout: fixed; width: 100%; min-width: 820px; }
        .tabla-listado-pdt th, .tabla-listado-pdt td { text-align: left; }
        /* Columnas de datos: ellipsis en personal/tractor para evitar que nombres largos rompan el layout */
        .tabla-listado-pdt td.col-personal, .tabla-listado-pdt td.col-tractor { min-width: 80px; max-width: 140px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .tabla-listado-pdt td.col-apellido, .tabla-listado-pdt th.col-apellido { width: 120px; max-width: 120px; min-width: 120px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .tabla-listado-pdt td.col-fecha, .tabla-listado-pdt th.col-fecha { width: 85px; min-width: 85px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .tabla-listado-pdt td.col-tipo, .tabla-listado-pdt th.col-tipo { width: 100px; min-width: 100px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .tabla-listado-pdt td.col-tractor, .tabla-listado-pdt th.col-tractor { width: 80px; min-width: 80px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .tabla-listado-pdt td.col-horas, .tabla-listado-pdt th.col-horas { width: 55px; min-width: 55px; text-align: right; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .tabla-listado-pdt td.col-finca, .tabla-listado-pdt th.col-finca { width: 88px; min-width: 88px; max-width: 88px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .tabla-listado-pdt td.col-observaciones, .tabla-listado-pdt th.col-observaciones { width: 120px; min-width: 120px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .form-group-finca { flex: 0 0 auto; min-width: 130px; max-width: 190px; }
        .form-group-finca select { font-size: 10px; height: 28px; padding: 2px 4px; }
        .form-group-finca #fincaOtroWrap input { font-size: 10px; height: 26px; }
        .tabla-listado-pdt td.col-acciones, .tabla-listado-pdt th.col-acciones { overflow: visible; width: 220px; min-width: 220px; background: #fff !important; }
        .tabla-listado-pdt th.col-acciones { background: #007bff !important; }
        .tabla-listado-pdt td.col-acciones { background: #fff !important; }
        .tabla-listado-pdt tr:hover td.col-acciones { background: #f8f9fa !important; }
        .tabla-listado-pdt tr.fila-con-observaciones td { background: #ffebee; color: #b71c1c; }
        .tabla-listado-pdt tr.fila-con-observaciones:hover td { background: #ffcdd2; }
        .tabla-listado-pdt tr.fila-con-observaciones td.col-acciones { background: #ffebee !important; }
        .tabla-listado-pdt tr.fila-con-observaciones:hover td.col-acciones { background: #ffcdd2 !important; }
        .wrap-tabla-pdt { width: 100%; max-width: 100%; overflow-x: auto; overflow-y: visible; position: relative; z-index: 2; -webkit-overflow-scrolling: touch; }
        #grid1 { min-height: 200px; max-height: 35vh; overflow-y: auto; overflow-x: auto; -webkit-overflow-scrolling: touch; display: block; position: relative; }
        #grid1.tamano-original { min-height: 400px; max-height: 70vh; }
        .tabla-listado-pdt tr.fila-con-observaciones { cursor: pointer; }
        #modalObservaciones { display: none; position: fixed; z-index: 9999; left: 0; top: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.4); align-items: center; justify-content: center; }
        #modalObservaciones.activo { display: flex; }
        #modalObservaciones .modal-caja { background: #fff; border-radius: 8px; padding: 20px; max-width: 90%; max-height: 80vh; overflow: auto; box-shadow: 0 4px 20px rgba(0,0,0,0.2); }
        #modalObservaciones .modal-caja h4 { margin: 0 0 12px 0; color: #333; font-size: 16px; }
        #modalObservaciones .modal-caja .obs-contenido { white-space: pre-wrap; color: #333; margin-bottom: 16px; }
        #modalObservaciones .modal-caja .btn { cursor: pointer; }
        .tabla-listado-pdt th.col-id, .tabla-listado-pdt td.col-id { min-width: 40px; }
        .tabla-listado-pdt th.col-personal, .tabla-listado-pdt td.col-personal { min-width: 80px; }
        .tabla-listado-pdt th.col-tractor, .tabla-listado-pdt td.col-tractor { min-width: 90px; }
        .tabla-listado-pdt th.col-fecha, .tabla-listado-pdt td.col-fecha { min-width: 85px; }
        .tabla-listado-pdt th.col-cantidad, .tabla-listado-pdt td.col-cantidad { min-width: 55px; }
        .tabla-listado-pdt th.col-gasoil, .tabla-listado-pdt td.col-gasoil { min-width: 55px; }
        .tabla-listado-pdt th.col-cambio, .tabla-listado-pdt td.col-cambio { min-width: 50px; text-align: center; }
        .tabla-listado-pdt th.col-cc, .tabla-listado-pdt td.col-cc { min-width: 40px; text-align: center; }
        .tabla-listado-pdt th.col-acciones, .tabla-listado-pdt td.col-acciones { min-width: 220px !important; white-space: nowrap; overflow: visible; text-align: right !important; }
        .tabla-listado-pdt td.col-acciones .acciones-botones { margin-left: auto; display: block; width: fit-content; }
        .icono-tractor { width: 15px; height: 15px; display: inline-block; margin-right: 3px; vertical-align: middle; }
        #tractorGroup { min-width: 150px; flex: 0 0 auto; }
        .form-row { flex-wrap: wrap; }
        /* Iconos tractores por marca: John Deere verde, New Holland azul, Massey Ferguson rojo */
        #tractor option.tractor-jd { color: #367c2b; font-weight: bold; }
        #tractor option.tractor-nh { color: #0066b3; font-weight: bold; }
        #tractor option.tractor-mf { color: #c41e3a; font-weight: bold; }
        #tractor.tractor-jd { color: #367c2b; font-weight: bold; }
        #tractor.tractor-nh { color: #0066b3; font-weight: bold; }
        #tractor.tractor-mf { color: #c41e3a; font-weight: bold; }
        .checkbox-label { display: flex; align-items: center; margin-top: 0; padding-top: 15px; font-size: 11px; }
        input[type="checkbox"] { width: 15px; height: 15px; margin-right: 4px; }
        .buscador-usuario-container { width: 25%; max-width: 320px; min-width: 0; overflow: visible; flex-shrink: 0; }
        .acciones-botones { display: inline-flex; gap: 6px; flex-wrap: nowrap; white-space: nowrap; }
        .acciones-botones form { display: inline-block; margin: 0; }
        .acciones-botones .btn { padding: 4px 8px; font-size: 10px; flex-shrink: 0; }
        textarea { font-size: 11px; padding: 5px; }
        #usuarioSeleccionado { font-size: 10px; padding: 3px 5px !important; }
        .badge-horas-ccno { display: inline-block; padding: 3px 8px; border-radius: 4px; font-size: 10px; font-weight: 600; }
        .badge-horas-ccno.badge-comunes { background: #fff3cd; color: #856404; border: 1px solid #ffc107; }
        .badge-horas-ccno.badge-tractor { background: #d1ecf1; color: #0c5460; border: 1px solid #17a2b8; }
        
        /* Sección carga gasoil: siempre visible (incl. partes desde cel) */
        .seccion-carga-gasoil { display: flex !important; flex-direction: column; }
        /* Responsive */
        @media (max-width: 768px) {
            body { margin: 8px; font-size: 12px; }
            .container { padding: 10px; margin: 0; max-width: 100%; }
            h2 { font-size: 16px; }
            h2 a { display: block; margin-top: 4px; }
            .container > div:first-of-type { flex-direction: column; align-items: stretch; gap: 12px; }
            .container > div:first-of-type > div:last-child { text-align: left; }
            .buscador-usuario-container { width: 100% !important; }
            .form-row { flex-direction: column; }
            .form-row .form-group.form-group-compact { max-width: 100%; min-width: 100%; }
            .form-group-observaciones { max-width: 100% !important; }
            .form-row .form-group.checkbox-group { flex: 1 1 auto; }
            #grid1 { min-height: 250px; max-height: 50vh; }
            .tabla-listado-pdt th.col-apellido, .tabla-listado-pdt td.col-apellido { width: 110px; max-width: 110px; min-width: 110px; }
            .tabla-listado-pdt th.col-fecha, .tabla-listado-pdt td.col-fecha { width: 75px; min-width: 75px; }
            .tabla-listado-pdt th.col-tipo, .tabla-listado-pdt td.col-tipo { width: 90px; min-width: 90px; }
            .tabla-listado-pdt th.col-tractor, .tabla-listado-pdt td.col-tractor { width: 75px; min-width: 75px; }
            .tabla-listado-pdt th.col-horas, .tabla-listado-pdt td.col-horas { width: 50px; min-width: 50px; }
            .tabla-listado-pdt th.col-finca, .tabla-listado-pdt td.col-finca { width: 80px; min-width: 80px; max-width: 80px; }
            .tabla-listado-pdt th.col-observaciones, .tabla-listado-pdt td.col-observaciones { width: 110px; min-width: 110px; }
            .acciones-botones { flex-wrap: wrap !important; }
            .acciones-botones .btn { font-size: 11px; padding: 6px 10px; }
            .tabla-listado-pdt th.col-acciones, .tabla-listado-pdt td.col-acciones { min-width: 180px; }
            table { font-size: 11px; }
            th, td { padding: 6px 4px; font-size: 11px; }
        }
        @media (max-width: 480px) {
            body { margin: 5px; }
            .container { padding: 8px; }
            h2 { font-size: 14px; }
            .btn { padding: 8px 10px; font-size: 12px; }
            .acciones-botones .btn { padding: 5px 8px; font-size: 10px; }
            .tabla-listado-pdt th.col-apellido, .tabla-listado-pdt td.col-apellido { width: 100px; max-width: 100px; min-width: 100px; }
            .tabla-listado-pdt th.col-fecha, .tabla-listado-pdt td.col-fecha { width: 70px; min-width: 70px; }
            .tabla-listado-pdt th.col-tipo, .tabla-listado-pdt td.col-tipo { width: 80px; min-width: 80px; }
            .tabla-listado-pdt th.col-tractor, .tabla-listado-pdt td.col-tractor { width: 70px; min-width: 70px; }
            .tabla-listado-pdt th.col-horas, .tabla-listado-pdt td.col-horas { width: 45px; min-width: 45px; }
            .tabla-listado-pdt th.col-finca, .tabla-listado-pdt td.col-finca { width: 72px; min-width: 72px; max-width: 72px; }
            .tabla-listado-pdt th.col-observaciones, .tabla-listado-pdt td.col-observaciones { width: 95px; min-width: 95px; }
            .tabla-listado-pdt th.col-acciones, .tabla-listado-pdt td.col-acciones { min-width: 150px; }
            #formCargaGasoilSisterna form { flex-direction: column; align-items: stretch; }
        }
    </style>
</head>
<?php
        $url_esc_volver = $desde_cel ? ($es_nivel_0 ? 'logout.php' : 'gestionar_finca.php') : 'index.php';
        $js_esc = "if((e.keyCode||e.which)==27){var f=document.getElementById('formCargaGasoilSisterna');if(f&&f.style.display!='none'){f.style.display='none';return false;}";
        if ($desde_cel) {
            $js_esc .= "if(history.length>1){history.back();return false;}";
        }
        $js_esc .= "location.href='".addslashes($url_esc_volver)."';return false;}";
        ?>
<body onkeydown="var e=event||window.event;<?= $js_esc ?>">
    <div class="container">
        <div style="display: flex; align-items: flex-start; justify-content: space-between; gap: 20px; flex-wrap: wrap; margin-bottom: 15px;">
            <h2 style="margin: 0;"><span style="font-size: 1.2em;" title="PDT">🚩</span> <?= htmlspecialchars($titulo_pagina) ?> <a href="<?= $desde_cel ? ($es_nivel_0 ? 'logout.php' : 'gestionar_finca.php') : 'index.php' ?>" id="linkVolverEsc" style="font-size: 14px; color: #007bff;" title="Volver (ESC)">Volver pantalla principal</a></h2>
            <?php if ($desde_cel && !$es_nivel_0 && isset($_SESSION['acceso_nivel']) && $_SESSION['acceso_nivel'] >= 2): ?>
            <a href="gestionar_finca.php?modo=completo" style="font-size: 12px; color: #28a745; font-weight: bold; margin-left: 8px;">Ver gestión completa (modo PC)</a>
            <?php endif; ?>
            <div style="text-align: right; flex-shrink: 0; display: flex; align-items: flex-start; gap: 12px; flex-wrap: wrap;">
                <?php if ($mostrar_vista_completa && !$desde_cel): ?>
                <a href="gestionar_tabla_salarial.php" class="btn btn-secondary" style="font-size: 11px; padding: 5px 10px;">ABM Tabla salarial</a>
                <span id="valoresSalarialesFinca" style="font-size: 11px; color: #333; padding: 4px 8px; background: #e8f4e8; border-radius: 4px; border: 1px solid #c8e6c9;">
                    Hora común: $ <?= number_format($ultima_tabla_salarial['valor_hora_comun'], 2, ',', '.') ?> | Hora tractor: $ <?= number_format($ultima_tabla_salarial['valor_hora_tractor'], 2, ',', '.') ?>
                </span>
                <?php if (isset($_SESSION['acceso_nivel']) && $_SESSION['acceso_nivel'] >= 3): ?>
                <a href="gestionar_convenios.php" class="btn btn-secondary" style="font-size: 11px; padding: 5px 10px;">Convenios</a>
                <?php endif; ?>
                <?php endif; ?>
                <?php /* Sección carga gasoil: habilitada también en partes desde cel */ ?>
                <div class="seccion-carga-gasoil" style="display: flex; flex-direction: column; gap: 6px;">
                    <div style="display: flex; align-items: center; gap: 12px;">
                        <button type="button" id="btnCargaGasoilSisterna" class="btn btn-secondary" style="font-size: 11px; padding: 5px 10px;">Carga gasoil en cisterna</button>
                    </div>
                <div style="font-size: 13px;">
                    <strong>Gestión de gasoil</strong><br>
                    Gasoil en cisterna: <span id="gasoilEnSisternaVal"><?= number_format($gasoil_en_sisterna, 2, ',', '') ?></span> L
                    <?php if (!empty($ultimas_cargas_gasoil)): ?>
                    <div style="margin-top: 6px; font-size: 11px; color: #555;">
                        Últimas 2 cargas:<br>
                        <?php foreach ($ultimas_cargas_gasoil as $c): ?>
                        <?= date('d/m/Y', strtotime($c['fecha'])) ?>: <?= number_format($c['cantidad'], 2, ',', '') ?> L<br>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
                <div id="formCargaGasoilSisterna" style="display: none; margin-top: 8px; padding: 10px; background: #f5f5f5; border-radius: 6px; text-align: left;">
                    <form id="formGasoilCisterna" method="post" action="<?= htmlspecialchars($form_action_url) ?>" style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap;">
                        <input type="hidden" name="carga_gasoil_sisterna" value="1">
                        <label style="margin: 0;">Fecha</label>
                        <input type="date" name="fecha_carga" id="fecha_carga_gasoil" value="<?= date('Y-m-d') ?>" required>
                        <label style="margin: 0;">Cantidad (L)</label>
                        <input type="number" name="cantidad_carga" id="cantidad_carga_gasoil" step="0.01" min="0.01" required placeholder="Ej. 500">
                        <button type="button" id="btnRegistrarCargaGasoil" class="btn btn-primary" style="font-size: 12px;">Registrar carga (+)</button>
                    </form>
                </div>
            </div>
        </div>
        
        <?php
        if (isset($_GET['cc'])) {
            if ($_GET['cc'] === 'ok') $mensaje = 'Horas cargadas en cuenta corriente correctamente.';
            elseif ($_GET['cc'] === 'sin_horas') $mensaje = 'No hay horas con CC=NO para cargar para este usuario.';
            elseif ($_GET['cc'] === 'sin_usuario') $mensaje = 'Seleccione un usuario.';
            elseif ($_GET['cc'] === 'sin_datos') $mensaje = 'No hay datos para cargar.';
            elseif ($_GET['cc'] === 'sin_tabla_salarial') $mensaje = 'Configure la tabla salarial (valores hora común y hora tractor) antes de cargar.';
            elseif ($_GET['cc'] === 'error') $mensaje = 'Falta dato o corregir.';
        }
        ?>
        <?php if ($mensaje && $mensaje !== 'Parte guardado.'): ?>
            <div id="mensajeCC" class="mensaje <?= (strpos($mensaje, 'Error') !== false || strpos($mensaje, 'Falta dato') !== false) ? 'error' : 'ok' ?>">
                <?= htmlspecialchars($mensaje) ?>
            </div>
            <?php if ((isset($_GET['cc']) && $_GET['cc'] === 'ok') || isset($_GET['gasoil_ok']) || $mensaje === 'Parte diario de trabajo eliminado correctamente.' || $mensaje === 'Todos los registros PDT han sido eliminados.'): ?>
            <script>
            (function(){ var m=document.getElementById('mensajeCC'); if(m) setTimeout(function(){ m.style.display='none'; }, 2000); })();
            </script>
            <?php endif; ?>
        <?php endif; ?>
        
        <?php
        $preseleccionar_apellido = '';
        $nombre_personal_mostrar = '';
        if ($pdt_edit) {
            foreach ($usuarios as $u) {
                if ((int)$u['id'] === (int)$pdt_edit['usuario_id']) {
                    $nombre_personal_mostrar = $u['apellido'];
                    break;
                }
            }
        } elseif (!empty($preseleccionar_usuario_id)) {
            foreach ($usuarios as $u) {
                if ((int)$u['id'] === (int)$preseleccionar_usuario_id) {
                    $preseleccionar_apellido = $u['apellido'];
                    $nombre_personal_mostrar = $u['apellido'];
                    break;
                }
            }
        }
        ?>
        <form method="POST" id="formPDT" class="form-nav-enter" action="<?= htmlspecialchars($form_action_url) ?>">
            <?php if ($pdt_edit): ?>
                <input type="hidden" name="pdt_id" value="<?= $pdt_edit['id'] ?>">
            <?php endif; ?>
            
            <div style="display: flex; align-items: flex-start; gap: 14px; flex-wrap: wrap; margin-bottom: 4px; position: relative; z-index: 1;">
                <div class="form-group buscador-usuario-container" style="flex: 0 0 auto; min-width: 200px;">
                    <label>Personal *</label>
                    <div style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap;">
                        <div class="buscador-usuario" style="flex: 0 0 180px; min-width: 140px;">
                            <input type="text" id="buscadorUsuario" tabindex="-1" placeholder="Buscar..." autocomplete="off" value="<?= htmlspecialchars($nombre_personal_mostrar) ?>" oninput="if(window.buscarPersonalPdt)window.buscarPersonalPdt();" onkeyup="if(window.buscarPersonalPdt)window.buscarPersonalPdt();">
                            <span class="ico-lupa" aria-hidden="true">🔍</span>
                            <input type="hidden" name="usuario_id" id="usuario_id" value="<?= $pdt_edit ? $pdt_edit['usuario_id'] : ($preseleccionar_usuario_id ?? '') ?>" required>
                            <div id="resultadosUsuario"></div>
                        </div>
                        <div id="usuarioSeleccionado" style="padding: 3px 5px; background: #e7f3ff; border-radius: 4px; font-size: 11px; flex-shrink: 0; <?= ($nombre_personal_mostrar !== '') ? '' : 'display: none;' ?>">
                            <strong>Sel:</strong> <span id="nombreUsuarioSel"><?= htmlspecialchars($nombre_personal_mostrar) ?></span>
                        </div>
                        <div id="etiquetasHorasCCNo" style="display: flex; flex-wrap: wrap; gap: 6px; align-items: center;"></div>
                        <?php if ($mostrar_vista_completa && !$desde_cel): ?>
                        <div id="btnCargarCCContainer" style="display: none; align-items: center; margin-left: auto;">
                            <button type="button" id="btnCargarCC" class="btn btn-success" style="font-size: 10px; padding: 5px 8px; flex-shrink: 0; white-space: nowrap;" title="Cargar en cuenta corriente">Cargar en cuenta corriente trabajos CC=NO</button>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                <script>
                (function(){
                    var u = <?= json_encode(array_values($usuarios), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) ?>;
                    var resumenCCNo = <?= json_encode($resumen_horas_cc_no, JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) ?> || {};
                    var b = document.getElementById('buscadorUsuario');
                    var r = document.getElementById('resultadosUsuario');
                    var uid = document.getElementById('usuario_id');
                    var nomSel = document.getElementById('nombreUsuarioSel');
                    var divSel = document.getElementById('usuarioSeleccionado');
                    if(!b||!r||!uid) return;
                    var minC = 2;
                    function buscar(){
                        var t = (b.value||'').toLowerCase().trim();
                        if(t.length < minC){ r.style.display='none'; r.innerHTML=''; if(!t){ uid.value=''; if(divSel) divSel.style.display='none'; actualizarResumenCCNo(''); filtrarGrillaPorUsuario(''); if(window.filtrarGridPorUsuarioPdt) window.filtrarGridPorUsuarioPdt(''); if(window.actualizarResumenHorasUsuarioPdt) window.actualizarResumenHorasUsuarioPdt(); } return; }
                        if(!u.length) return;
                        var list = [];
                        for(var i=0;i<u.length;i++){ var ap = (u[i].apellido||'').toLowerCase(); if(ap.indexOf(t)!==-1) list.push(u[i]); }
                        if(!list.length){ r.innerHTML='<div class="usuario-item">No se encontraron usuarios</div>'; r.style.display='block'; return; }
                        var html = '';
                        for(var j=0;j<Math.min(10,list.length);j++){
                            var x=list[j];
                            var apEsc = (x.apellido||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/"/g,'&quot;');
                            html += '<div class="usuario-item" data-id="'+x.id+'" data-nombre="'+apEsc+'">'+apEsc+'</div>';
                        }
                        r.innerHTML = html;
                        r.style.display = 'block';
                    }
                    window.buscarPersonalPdt = buscar;
                    b.oninput = buscar;
                    b.onkeyup = buscar;
                    b.onfocus = function(){ if((b.value||'').trim().length>=minC) buscar(); };
                    function findItem(tgt, cont){ var n=(tgt&&tgt.nodeType===1)?tgt:(tgt?tgt.parentElement:null); while(n&&n!==cont){ if(n.classList&&n.classList.contains('usuario-item')) return n; n=n.parentElement||n.parentNode; } return null; }
                    function filtrarGrillaPorUsuario(idStr){
                        var filas = document.querySelectorAll('tr.fila-pdt');
                        var id = (idStr||'').toString().trim();
                        for(var i=0;i<filas.length;i++){ var tr=filas[i]; tr.style.display = (id==='' || (tr.getAttribute('data-usuario-id')||'')===id) ? '' : 'none'; }
                    }
                    function actualizarResumenCCNo(idStr){
                        var cont = document.getElementById('etiquetasHorasCCNo');
                        var btnCont = document.getElementById('btnCargarCCContainer');
                        var btnCC = document.getElementById('btnCargarCC');
                        if(!cont) return;
                        var id = (idStr||'').toString().trim();
                        if(!id){ cont.innerHTML=''; cont.style.display='none'; if(btnCont) btnCont.style.display='none'; if(btnCC) btnCC.disabled=true; return; }
                        var d = resumenCCNo[id] || {};
                        var hc = parseFloat(d.horas_comunes)||0, ht = parseFloat(d.horas_tractor)||0;
                        var html = '';
                        if(hc>0) html += '<span class="badge-horas-ccno badge-comunes">Horas Comunes: '+hc+' h (CC=NO)</span>';
                        if(ht>0) html += '<span class="badge-horas-ccno badge-tractor">Horas tractos: '+ht+' h (CC=NO)</span>';
                        cont.innerHTML = html;
                        var tieneCCNo = (hc>0||ht>0);
                        cont.style.display = tieneCCNo ? 'flex' : 'none';
                        if(btnCont) btnCont.style.display = (id && tieneCCNo) ? 'flex' : 'none';
                        if(btnCC) btnCC.disabled = !(id && tieneCCNo);
                    }
                    function onSelect(id, nom){
                        uid.value = id;
                        b.value = nom;
                        if(nomSel) nomSel.textContent = nom;
                        if(divSel) divSel.style.display = 'block';
                        r.style.display = 'none';
                        filtrarGrillaPorUsuario(id);
                        actualizarResumenCCNo(id);
                        if(window.filtrarGridPorUsuarioPdt) window.filtrarGridPorUsuarioPdt(id);
                        if(window.actualizarResumenHorasUsuarioPdt) window.actualizarResumenHorasUsuarioPdt();
                        var sF = document.getElementById('selFinca');
                        if (sF) { try { sF.focus(); } catch (e) {} }
                    }
                    r.onclick = function(e){
                        var el = findItem(e.target, r);
                        if(!el||!el.getAttribute('data-id')) return;
                        onSelect(el.getAttribute('data-id'), el.getAttribute('data-nombre')||'');
                    };
                    r.ontouchend = function(e){
                        var el = findItem(e.target, r);
                        if(el&&el.getAttribute('data-id')){ e.preventDefault(); onSelect(el.getAttribute('data-id'), el.getAttribute('data-nombre')||''); }
                    };
                    var btnCC = document.getElementById('btnCargarCC');
                    if(btnCC){
                        btnCC.onclick = function(){
                            var id = uid ? (uid.value||'').trim() : '';
                            if(!id){ alert('Seleccione un usuario.'); return; }
                            if(!confirm('¿Cargar en cuenta corriente los trabajos CC=NO de este usuario? Se generarán uno o dos movimientos (Horas Comunes / Horas tractos) con fecha de hoy.')) return;
                            var f = document.createElement('form');
                            f.method = 'POST';
                            f.action = 'cargar_pdt_cc.php';
                            f.style.display = 'none';
                            var inp = document.createElement('input');
                            inp.type = 'hidden';
                            inp.name = 'usuario_id';
                            inp.value = id;
                            f.appendChild(inp);
                            document.body.appendChild(f);
                            f.submit();
                        };
                    }
                    if(uid&&uid.value){ actualizarResumenCCNo(uid.value); }
                })();
                </script>
            </div>
            
            <div class="form-row">
                <div class="form-group form-group-finca">
                    <label>Finca</label>
                    <select name="finca_sel" id="selFinca" tabindex="0" onchange="var w=document.getElementById('fincaOtroWrap');if(w)w.style.display=(this.value==='__otro__')?'block':'none';">
                        <?php foreach ($fincas_lista as $fn): ?>
                        <option value="<?= htmlspecialchars($fn) ?>" <?= (!$es_otra_finca && $finca_actual === $fn) ? 'selected' : '' ?>><?= htmlspecialchars($fn) ?></option>
                        <?php endforeach; ?>
                        <option value="__otro__" <?= $es_otra_finca ? 'selected' : '' ?>>+ Nueva finca</option>
                    </select>
                    <div id="fincaOtroWrap" style="display:<?= $es_otra_finca ? 'block' : 'none' ?>; margin-top: 4px;">
                        <input type="text" name="finca_nueva" id="fincaNueva" placeholder="Nueva finca" value="<?= htmlspecialchars($finca_nueva_val) ?>" style="width:100%; box-sizing:border-box;">
                    </div>
                </div>
                <div class="form-group" id="tractorGroup">
                    <label>Horas Comunes / Tractor *</label>
                    <select name="tractor" id="tractor" tabindex="-1" required>
                        <optgroup label="Horas Comunes">
                            <option value="Horas Comunes" <?= (($pdt_edit && ($pdt_edit['tipo_horas'] ?? '') === 'Horas Comunes') || (!$pdt_edit && $tractor_default === 'Horas Comunes')) ? 'selected' : '' ?>>Horas Comunes</option>
                        </optgroup>
                        <optgroup label="John Deere">
                            <option class="tractor-jd" value="John Deere 200 hp" <?= (($pdt_edit && ($pdt_edit['tractor'] ?? '') === 'John Deere 200 hp') || (!$pdt_edit && $tractor_default === 'John Deere 200 hp')) ? 'selected' : '' ?>>
                                🚜 John Deere 200 hp
                            </option>
                            <option class="tractor-jd" value="John Deere 110 hp" <?= (($pdt_edit && ($pdt_edit['tractor'] ?? '') === 'John Deere 110 hp') || (!$pdt_edit && $tractor_default === 'John Deere 110 hp')) ? 'selected' : '' ?>>
                                🚜 John Deere 110 hp
                            </option>
                        </optgroup>
                        <optgroup label="New Holland">
                            <option class="tractor-nh" value="T7.195/4CPAT" <?= (($pdt_edit && in_array(($pdt_edit['tractor'] ?? ''), ['T7.195/4CPAT', 'New Holland TM150'], true)) || (!$pdt_edit && $tractor_default === 'T7.195/4CPAT')) ? 'selected' : '' ?>>
                                🚜 T7.195/4CPAT
                            </option>
                            <option class="tractor-nh" value="New Holland 7630" <?= (($pdt_edit && ($pdt_edit['tractor'] ?? '') === 'New Holland 7630') || (!$pdt_edit && $tractor_default === 'New Holland 7630')) ? 'selected' : '' ?>>
                                🚜 New Holland 7630
                            </option>
                        </optgroup>
                        <optgroup label="Massey Ferguson">
                            <option class="tractor-mf" value="Massey Ferguson 1165" <?= (($pdt_edit && ($pdt_edit['tractor'] ?? '') === 'Massey Ferguson 1165') || (!$pdt_edit && $tractor_default === 'Massey Ferguson 1165')) ? 'selected' : '' ?>>
                                🚜 Massey Ferguson 1165
                            </option>
                        </optgroup>
                    </select>
                    <div id="tractorDesdeCambioAceite" style="margin-top: 6px; font-size: 12px; color: #555; padding: 6px 8px; background: #f9f9f9; border-radius: 4px; border: 1px solid #eee; display: none;">
                        <strong>Desde último cambio de aceite:</strong><br>
                        Horas trabajadas: <span id="tractorHorasDesdeCambio">0</span><br>
                        Gasoil: <span id="tractorGasoilDesdeCambio">0</span> L
                    </div>
                </div>
                
                <div class="form-group form-group-compact">
                    <label>Fecha *</label>
                    <input type="date" name="fecha" id="fecha" tabindex="1" value="<?= $pdt_edit ? $pdt_edit['fecha'] : date('Y-m-d') ?>" required>
                </div>
                
                <div class="form-group form-group-compact" id="cantidadHorasGroup">
                    <label id="labelCantidad">Cantidad *</label>
                    <input type="text" inputmode="numeric" pattern="[0-9]*" name="horas" id="horas" tabindex="2" value="<?= $pdt_edit ? (int)$pdt_edit['horas'] : '' ?>" placeholder="0" required autocomplete="off">
                </div>
                
                <div class="form-group form-group-observaciones">
                    <label>Observaciones</label>
                    <textarea name="observaciones" id="observaciones" rows="1" tabindex="3" style="resize: vertical; min-height: 28px;" onkeydown="if((event||window.event).key==='Enter'&&!(event||window.event).shiftKey){(event||window.event).preventDefault();var g=document.getElementById('btnGuardar');if(g)g.focus();}"><?= htmlspecialchars($pdt_edit['observaciones'] ?? '') ?></textarea>
                </div>
                
                <div class="form-group" id="gasoilGroup" style="display: none;">
                    <label>Cant Gasoil *</label>
                    <input type="number" name="cant_gasoil" id="cant_gasoil" tabindex="4" step="0.01" min="0" value="<?= $pdt_edit ? ($pdt_edit['cant_gasoil'] ?? '0') : '0' ?>">
                </div>
                
                <div class="form-group checkbox-group" id="cambioAceiteGroup" style="display: none;">
                    <label class="checkbox-label">
                        <input type="checkbox" name="cambio_aceite" id="cambio_aceite" tabindex="5" value="1" <?= ($pdt_edit && isset($pdt_edit['cambio_aceite']) && $pdt_edit['cambio_aceite'] == 1) ? 'checked' : '' ?>>
                        Cambio de aceite
                    </label>
                </div>
                
                <div class="form-group" style="flex: 0 0 auto; flex-shrink: 0; align-self: flex-end;">
                    <label>&nbsp;</label>
                    <button type="submit" name="guardar" id="btnGuardar" class="btn btn-primary" tabindex="6" style="min-width: 140px; padding: 6px 24px;">Guardar</button>
                </div>
            </div>
            
            <div style="display: flex; align-items: center; gap: 12px; flex-wrap: wrap; margin-bottom: 4px;">
                <?php if ($mensaje === 'Parte guardado.'): ?>
                    <div id="cartelMensaje" class="cartel-parte-guardado parte-guardado">Parte guardado.</div>
                    <script>
                    (function(){
                        var c=document.getElementById('cartelMensaje');
                        if(c){setTimeout(function(){c.style.transition='opacity 0.3s';c.style.opacity='0';setTimeout(function(){c.remove();var f=document.getElementById('fecha');if(f)f.focus();},300);},2000);}
                    })();
                    </script>
                <?php endif; ?>
                <?php if ($pdt_edit && $mostrar_vista_completa): ?>
                    <a href="<?= htmlspecialchars($form_action_url) ?>" class="btn btn-secondary">Cancelar</a>
                <?php endif; ?>
            </div>
        </form>
        
        
        <div style="margin-top: 24px; padding-top: 8px; position: relative; z-index: 2; clear: both; width: 100%; border-top: 1px solid #eee;">
            <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 8px; margin-bottom: 8px;">
                <h3 style="margin: 0;">Listado de PDTs</h3>
                <?php if ($mostrar_vista_completa && count($lista_pdt) > 0 && !$desde_cel): ?>
                <form method="POST" action="<?= htmlspecialchars($form_action_url) ?>" style="display: inline;" onsubmit="return confirm('¿Eliminar los partes NO cargados en CC? Esta acción no se puede deshacer.');">
                    <input type="hidden" name="eliminar_todos" value="1">
                    <button type="submit" class="btn btn-danger" style="font-size: 10px; padding: 4px 10px;">Eliminar partes no cargados en CC</button>
                </form>
                <?php endif; ?>
            </div>
            <div id="grid1" class="wrap-tabla-pdt">
        <table class="tabla-listado-pdt">
            <colgroup>
                <col style="width:120px">
                <col style="width:85px">
                <col style="width:100px">
                <col style="width:80px">
                <col style="width:55px">
                <col style="width:88px">
                <col style="width:120px">
                <col style="width:220px">
            </colgroup>
            <thead>
                <tr>
                    <th class="col-apellido">Apellido</th>
                    <th class="col-fecha">Fecha</th>
                    <th class="col-tipo">Tipo</th>
                    <th class="col-tractor">Tractor</th>
                    <th class="col-horas">Horas</th>
                    <th class="col-finca">Finca</th>
                    <th class="col-observaciones">Observaciones</th>
                    <th class="col-acciones">Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php $tiene_datos = count($lista_pdt) > 0; ?>
                <?php if ($tiene_datos): ?>
                    <?php foreach ($lista_pdt as $pdt): ?>
                        <?php
                        $p = function($k, $def = '') use ($pdt) {
                            $v = $pdt[$k] ?? $pdt[strtoupper($k)] ?? $pdt[ucfirst($k)] ?? $def;
                            return $v !== null && $v !== '' ? $v : $def;
                        };
                        $tiene_obs = !empty(trim((string)$p('observaciones', '')));
                        $pid = (int)$p('id', 0);
                        $uid = (int)$p('usuario_id', 0);
                        $nom = htmlspecialchars((string)$p('usuario_nombre', '-'));
                        $tipo = htmlspecialchars((string)$p('tipo_horas', '-'));
                        $tractor = htmlspecialchars((string)($p('tractor') ?: '-'));
                        $fechaRaw = $p('fecha', '');
                        $fechaFmt = $fechaRaw ? @date('d/m/Y', strtotime($fechaRaw)) : '-';
                        $horas = (float)$p('horas', 0);
                        $gasoil = $p('cant_gasoil');
                        $gasoilFmt = ($gasoil !== null && $gasoil !== '' && $gasoil !== '-') ? number_format((float)$gasoil, 2, ',', '.') : '-';
                        $cambio = (isset($pdt['cambio_aceite']) ? (int)$pdt['cambio_aceite'] : (int)($pdt['cambio_aceite'] ?? 0));
                        $encc = (isset($pdt['en_cc']) ? (int)$pdt['en_cc'] : (int)($pdt['en_cc'] ?? 0));
                        $obs_raw = trim((string)$p('observaciones', ''));
                        $nom_show = (string)$nom; if (function_exists('mb_strlen') && function_exists('mb_substr') && mb_strlen($nom_show) > 20) { $nom_show = mb_substr($nom_show, 0, 20) . '…'; } elseif (strlen($nom_show) > 20) { $nom_show = substr($nom_show, 0, 20) . '…'; }
                        $tipo_show = (string)$tipo; if (function_exists('mb_strlen') && function_exists('mb_substr') && mb_strlen($tipo_show) > 15) { $tipo_show = mb_substr($tipo_show, 0, 15) . '…'; } elseif (strlen($tipo_show) > 15) { $tipo_show = substr($tipo_show, 0, 15) . '…'; }
                        $tractor_show = (string)$tractor; if (function_exists('mb_strlen') && function_exists('mb_substr') && mb_strlen($tractor_show) > 10) { $tractor_show = mb_substr($tractor_show, 0, 10) . '…'; } elseif (strlen($tractor_show) > 10) { $tractor_show = substr($tractor_show, 0, 10) . '…'; }
                        $finca_raw = trim((string)$p('finca', ''));
                        if ($finca_raw === '') {
                            $finca_show = '—';
                        } elseif (function_exists('mb_strlen') && function_exists('mb_substr') && mb_strlen($finca_raw) > 12) {
                            $finca_show = htmlspecialchars(mb_substr($finca_raw, 0, 12)) . '…';
                        } elseif (strlen($finca_raw) > 12) {
                            $finca_show = htmlspecialchars(substr($finca_raw, 0, 12)) . '…';
                        } else {
                            $finca_show = htmlspecialchars($finca_raw);
                        }
                        $obs_show = (string)$obs_raw; if (function_exists('mb_strlen') && function_exists('mb_substr') && mb_strlen($obs_show) > 22) { $obs_show = mb_substr($obs_show, 0, 22) . '…'; } elseif (strlen($obs_show) > 22) { $obs_show = substr($obs_show, 0, 22) . '…'; }
                        $fecha_pdt = $fechaRaw ? substr($fechaRaw, 0, 10) : '';
                        $hoy = date('Y-m-d');
                        $ayer = date('Y-m-d', strtotime('-1 day'));
                        $mostrar_mod_elim = !$desde_cel || ($fecha_pdt === $hoy || $fecha_pdt === $ayer);
                        ?>
                        <tr class="fila-pdt<?= $tiene_obs ? ' fila-con-observaciones' : '' ?>" data-usuario-id="<?= $uid ?>"<?= $tiene_obs ? ' title="Clic para ver observaciones"' : '' ?>>
                            <?php $horas_show = number_format($horas, 2, ',', '.'); if (strlen($horas_show) > 6) { $horas_show = substr($horas_show, 0, 6) . '…'; } ?>
                            <td class="col-apellido" title="<?= $nom ?>"><?= $nom_show ?></td>
                            <td class="col-fecha" title="<?= $fechaFmt ?>"><?= $fechaFmt ?></td>
                            <td class="col-tipo" title="<?= $tipo ?>"><?= $tipo_show ?></td>
                            <td class="col-tractor" title="<?= $tractor ?>"><?= $tractor_show ?></td>
                            <td class="col-horas" title="<?= number_format($horas, 2, ',', '.') ?>"><?= $horas_show ?></td>
                            <td class="col-finca" title="<?= htmlspecialchars($finca_raw) ?>"><?= $finca_show ?></td>
                            <td class="col-observaciones" title="<?= htmlspecialchars($obs_raw) ?>"><?php if ($tiene_obs): ?><span class="obs-text-hidden" style="display:none"><?= htmlspecialchars($obs_raw) ?></span><?php endif; ?><?= htmlspecialchars($obs_show) ?></td>
                            <td class="col-acciones">
                                <div style="display: flex; justify-content: flex-end; width: 100%;">
                                <div class="acciones-botones">
                                    <?php if ($mostrar_mod_elim): ?>
                                    <form method="POST" action="<?= htmlspecialchars($form_action_url) ?>" style="display:inline;">
                                        <input type="hidden" name="pdt_id" value="<?= $pid ?>">
                                        <button type="submit" name="editar" class="btn btn-secondary">Modificar</button>
                                    </form>
                                    <form method="POST" action="<?= htmlspecialchars($form_action_url) ?>" style="display:inline;">
                                        <input type="hidden" name="pdt_id" value="<?= $pid ?>">
                                        <button type="submit" name="eliminar" class="btn btn-danger" onclick="return confirm('¿Eliminar este PDT?')">Eliminar</button>
                                    </form>
                                    <?php endif; ?>
                                    <?php if ($encc == 0 && !$desde_cel): ?>
                                    <form method="POST" action="<?= htmlspecialchars($form_action_url) ?>" style="display:inline;">
                                        <input type="hidden" name="pdt_id" value="<?= $pid ?>">
                                        <button type="submit" name="cargar_cc" class="btn btn-success">Cargar en CC</button>
                                    </form>
                                    <?php endif; ?>
                                </div>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="8" style="text-align: center; padding: 15px; color: #666; font-size: 11px;">No hay partes diarios de trabajo registrados.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
            </div>
        </div>
        
        <div style="margin-top: 20px; display: flex; gap: 10px; flex-wrap: wrap;">
            <?php if ($desde_cel): ?>
                <?php if ($es_nivel_0): ?>
                    <a href="logout.php" class="btn btn-secondary">Salir</a>
                <?php else: ?>
                    <a href="gestionar_finca.php?modo=completo" class="btn btn-primary" style="font-weight: bold;">Ver gestión completa (modo PC)</a>
                    <a href="gestionar_finca.php?modo=completo" class="btn btn-secondary">← Volver a Gestión Finca</a>
                <?php endif; ?>
            <?php endif; ?>
            <?php if ($mostrar_vista_completa): ?>
            <a href="index.php" class="btn btn-secondary">← Volver al panel</a>
            <?php endif; ?>
            <?php if (!$desde_cel): ?>
            <a href="partes_desde_cel.php" class="btn btn-secondary">Partes desde cel</a>
            <?php endif; ?>
        </div>

        <div id="modalObservaciones" aria-hidden="true">
            <div class="modal-caja">
                <h4>Observaciones</h4>
                <div class="obs-contenido" id="modalObservacionesTexto"></div>
                <button type="button" class="btn btn-primary" id="modalObservacionesCerrar">Cerrar</button>
            </div>
        </div>
    </div>
    
    <script>
    // Polyfill Element.prototype.closest para navegadores antiguos (IE11)
    if (!Element.prototype.closest) {
        Element.prototype.closest = function(s) {
            var el = this; do { if (el.matches && el.matches(s)) return el; el = el.parentElement || el.parentNode; } while (el && el.nodeType === 1); return null;
        };
    }
    if (!Element.prototype.matches) {
        Element.prototype.matches = Element.prototype.msMatchesSelector || Element.prototype.webkitMatchesSelector || function(s) { var m = (this.document || this.ownerDocument).querySelectorAll(s), i = m.length; while (--i >= 0 && m.item(i) !== this) {} return i > -1; };
    }
    // ESC: volver al formulario principal. Funciona en servidor y local.
    (function() {
        var urlVolver = '<?= $desde_cel ? ($es_nivel_0 ? "logout.php" : "gestionar_finca.php") : "index.php" ?>';
        var desdeCel = <?= $desde_cel ? 'true' : 'false' ?>;
        
        function esTeclaEsc(ev) {
            if (!ev) return false;
            var k = ev.key;
            var c = ev.keyCode;
            return k === 'Escape' || k === 'Esc' || c === 27;
        }
        
        function manejarEsc(ev) {
            if (!esTeclaEsc(ev)) return;
            ev.preventDefault();
            ev.stopPropagation();
            var formGasoil = document.getElementById('formCargaGasoilSisterna');
            if (formGasoil && formGasoil.style && formGasoil.style.display !== 'none') {
                formGasoil.style.display = 'none';
                return;
            }
            if (desdeCel && window.history && window.history.length > 1) {
                window.history.back();
            } else {
                window.location.href = urlVolver;
            }
        }
        
        function registrarEsc() {
            if (document.addEventListener) {
                document.addEventListener('keydown', manejarEsc, true);
                document.addEventListener('keyup', manejarEsc, true);
                window.addEventListener('keydown', manejarEsc, true);
                window.addEventListener('keyup', manejarEsc, true);
                if (document.body) {
                    document.body.addEventListener('keydown', manejarEsc, true);
                    document.body.addEventListener('keyup', manejarEsc, true);
                }
            } else if (document.attachEvent) {
                document.attachEvent('onkeydown', manejarEsc);
                document.attachEvent('onkeyup', manejarEsc);
                window.attachEvent('onkeydown', manejarEsc);
                window.attachEvent('onkeyup', manejarEsc);
            }
        }
        
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', registrarEsc);
        } else {
            registrarEsc();
        }
        
        // Flecha atrás del navegador: volver siempre al principal
        window.addEventListener('popstate', function() {
            window.location.href = urlVolver;
        });
        // Añadir estado para que popstate se dispare al hacer clic en atrás (evita ir al formulario POST anterior)
        if (window.history && window.history.pushState) {
            window.history.pushState({gestionar: 1}, '', window.location.href);
        }
        
        // Clic en banderita = mismo efecto que ESC (ejecutar cuando el DOM esté listo)
        function initBanderita() {
            var linkVolver = document.getElementById('linkVolverEsc');
            if (linkVolver && linkVolver.addEventListener) {
                linkVolver.addEventListener('click', function(e) {
                    e.preventDefault();
                    if (desdeCel && window.history && window.history.length > 1) {
                        window.history.back();
                    } else {
                        window.location.href = urlVolver;
                    }
                });
            }
        }
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', initBanderita);
        } else {
            initBanderita();
        }
    })();
    
    (function() {
        // Esperar a que el DOM esté completamente cargado
        function init() {
            try {
                // Intentar obtener datos de PHP de forma segura
                var usuarios = [];
                var resumenHorasCCNo = {};
                var tractorDesdeCambio = {};
                try {
                    usuarios = <?= json_encode($usuarios, JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) ?> || [];
                    resumenHorasCCNo = <?= json_encode($resumen_horas_cc_no, JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) ?> || {};
                    tractorDesdeCambio = <?= json_encode($tractor_desde_cambio, JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) ?> || {};
                } catch (e) {
                    console.error('Error al parsear datos de PHP:', e);
                }
                
                // Asegurar que usuarios sea un array
                if (!Array.isArray(usuarios)) {
                    console.warn('usuarios no es un array, convirtiendo...', typeof usuarios);
                    usuarios = [];
                }
                
                const buscador = document.getElementById('buscadorUsuario');
                const resultados = document.getElementById('resultadosUsuario');
                const usuarioIdInput = document.getElementById('usuario_id');
                const usuarioSeleccionado = document.getElementById('usuarioSeleccionado');
                const nombreUsuarioSel = document.getElementById('nombreUsuarioSel');
                const tractorGroup = document.getElementById('tractorGroup');
                const tractorSelect = document.getElementById('tractor');
                const selFinca = document.getElementById('selFinca');
                function esHorasTractor() { return tractorSelect && tractorSelect.value !== '' && tractorSelect.value !== 'Horas Comunes'; }
                
                // Verificar que los elementos críticos existan (no retornar, solo registrar error)
                if (!buscador || !resultados || !usuarioIdInput) {
                    console.error('Error: No se encontraron elementos del buscador de usuarios', {
                        buscador: !!buscador,
                        resultados: !!resultados,
                        usuarioIdInput: !!usuarioIdInput
                    });
                }
    
                
                <?php if ($pdt_edit): ?>
                // Si estamos editando, mostrar el usuario seleccionado
                const usuarioEdit = usuarios.find(u => u.id == <?= $pdt_edit['usuario_id'] ?>);
                if (usuarioEdit && buscador && nombreUsuarioSel && usuarioSeleccionado) {
                    buscador.value = usuarioEdit.apellido;
                    nombreUsuarioSel.textContent = usuarioEdit.apellido;
                    usuarioSeleccionado.style.display = 'block';
                }
                // Mostrar grupo de tractor y campos adicionales si corresponde
                var gasoilGroupEdit = document.getElementById('gasoilGroup');
                var cambioAceiteGroupEdit = document.getElementById('cambioAceiteGroup');
                var labelCantidadEdit = document.getElementById('labelCantidad');
                var cantGasoilInputEdit = document.getElementById('cant_gasoil');
                if (tractorGroup && tractorSelect) {
                    tractorGroup.style.display = 'block';
                    var esTractorEdit = '<?= ($pdt_edit['tipo_horas'] ?? '') === 'Horas tractos' ? '1' : '0' ?>' === '1';
                    if (esTractorEdit) {
                        tractorSelect.required = true;
                        if (gasoilGroupEdit) gasoilGroupEdit.style.display = 'block';
                        if (cambioAceiteGroupEdit) cambioAceiteGroupEdit.style.display = 'block';
                        if (labelCantidadEdit) labelCantidadEdit.textContent = 'Cantidad horas *';
                        if (cantGasoilInputEdit) cantGasoilInputEdit.required = true;
                    } else {
                        tractorSelect.required = false;
                        if (gasoilGroupEdit) gasoilGroupEdit.style.display = 'none';
                        if (cambioAceiteGroupEdit) cambioAceiteGroupEdit.style.display = 'none';
                        if (cantGasoilInputEdit) cantGasoilInputEdit.required = false;
                    }
                }
                <?php endif; ?>
    
                function filtrarGridPorUsuario(usuarioId) {
                    const filas = document.querySelectorAll('tr.fila-pdt');
                    const id = usuarioId ? String(usuarioId).trim() : '';
                    filas.forEach(function(tr) {
                        tr.style.display = (id === '' || tr.getAttribute('data-usuario-id') === id) ? '' : 'none';
                    });
                }
                window.filtrarGridPorUsuarioPdt = filtrarGridPorUsuario;

                function actualizarResumenHorasUsuario(idOverride) {
                    var id = (idOverride !== undefined && idOverride !== null) ? String(idOverride).trim() : (usuarioIdInput ? String(usuarioIdInput.value || '').trim() : '');
                    var contBtnCC = document.getElementById('btnCargarCCContainer');
                    var btnCC = document.getElementById('btnCargarCC');
                    if (contBtnCC) contBtnCC.style.display = id ? 'flex' : 'none';
                    if (btnCC) btnCC.disabled = !id;
                    var contEtiquetas = document.getElementById('etiquetasHorasCCNo');
                    if (contEtiquetas) {
                        if (!id) {
                            contEtiquetas.innerHTML = '';
                            contEtiquetas.style.display = 'none';
                            return;
                        }
                        var datos = resumenHorasCCNo[id] || {};
                        var hc = parseFloat(datos.horas_comunes) || 0;
                        var ht = parseFloat(datos.horas_tractor) || 0;
                        var html = '';
                        if (hc > 0) html += '<span class="badge-horas-ccno badge-comunes">Comunes: ' + hc + ' h</span>';
                        if (ht > 0) html += '<span class="badge-horas-ccno badge-tractor">Tractor: ' + ht + ' h</span>';
                        contEtiquetas.innerHTML = html;
                        contEtiquetas.style.display = (hc > 0 || ht > 0) ? 'flex' : 'none';
                    }
                }
                window.actualizarResumenHorasUsuarioPdt = actualizarResumenHorasUsuario;

                // Botón Cargar en cuenta corriente: manejado en el script inline del buscador Personal

                // Al cargar: mostrar siempre todos los registros (no filtrar). Resumen de horas si hay usuario.
                if (usuarioIdInput && usuarioIdInput.value) {
                    actualizarResumenHorasUsuario();
                }

                // Clic en fila de la grilla: seleccionar ese usuario en el buscador Personal
                document.addEventListener('click', function(e) {
                    if (!e.target || !e.target.closest) return;
                    var tr = e.target.closest('tr.fila-pdt');
                    if (!tr || e.target.closest('form') || e.target.closest('button') || e.target.tagName === 'BUTTON' || e.target.tagName === 'INPUT') return;
                    var uid = tr.getAttribute('data-usuario-id');
                    if (!uid || !usuarioIdInput) return;
                    var tdApellido = tr.querySelector('.col-apellido');
                    var u = usuarios.find(function(x) { return String(x.id) === uid; });
                    var nombre = tdApellido ? (tdApellido.getAttribute('title') || tdApellido.textContent || '').trim() : ((u && u.apellido) ? u.apellido : '');
                    usuarioIdInput.value = uid;
                    if (buscador) buscador.value = nombre;
                    if (nombreUsuarioSel) nombreUsuarioSel.textContent = nombre;
                    if (usuarioSeleccionado) usuarioSeleccionado.style.display = 'block';
                    filtrarGridPorUsuario(uid);
                    actualizarResumenHorasUsuario();
                });

                // Modal observaciones: clic en fila con observaciones (no en botones)
                var modalObs = document.getElementById('modalObservaciones');
                var modalObsTexto = document.getElementById('modalObservacionesTexto');
                var modalObsCerrar = document.getElementById('modalObservacionesCerrar');
                if (modalObs && modalObsTexto) {
                    document.addEventListener('click', function(e) {
                        var tgt = e && e.target;
                        if (!tgt || !tgt.closest) return;
                        var tbody = tgt.closest('.wrap-tabla-pdt tbody');
                        if (!tbody) return;
                        var tr = tgt.closest('tr.fila-con-observaciones');
                        if (!tr) return;
                        if (tgt.closest('form') || tgt.tagName === 'BUTTON' || tgt.tagName === 'INPUT') return;
                        var span = tr.querySelector('.obs-text-hidden');
                        if (!span) return;
                        modalObsTexto.textContent = span.textContent;
                        modalObs.classList.add('activo');
                    });
                    function cerrarModalObs() { if (modalObs) modalObs.classList.remove('activo'); }
                    if (modalObsCerrar) modalObsCerrar.addEventListener('click', cerrarModalObs);
                    if (modalObs) {
                        modalObs.addEventListener('click', function(e) { if (e && e.target === modalObs) cerrarModalObs(); });
                    }
                }

                // Ocultar resultados al hacer clic fuera
                document.addEventListener('click', function(e) {
                    var tgt = e && e.target;
                    if (!buscador || !resultados || !tgt) return;
                    if (!buscador.contains(tgt) && !resultados.contains(tgt)) {
                        resultados.style.display = 'none';
                    }
                });
    
                const gasoilGroup = document.getElementById('gasoilGroup');
                const cambioAceiteGroup = document.getElementById('cambioAceiteGroup');
                const labelCantidad = document.getElementById('labelCantidad');
                const fechaInput = document.getElementById('fecha');
                const horasInput = document.getElementById('horas');
                const cantGasoilInput = document.getElementById('cant_gasoil');
                const cambioAceiteInput = document.getElementById('cambio_aceite');
                const observacionesTextarea = document.querySelector('textarea[name="observaciones"]');
                const btnGuardar = document.getElementById('btnGuardar');
                
                // Cargar último elegido en Horas Comunes / Tractor (si no estamos editando)
                <?php if (!$pdt_edit): ?>
                if (tractorSelect) {
                    try {
                        const serverTractor = <?= json_encode($tractor_default) ?>;
                        const v = JSON.parse(localStorage.getItem('pdt_ultimos_valores') || '{}');
                        // Priorizar valor del servidor (sesión) sobre localStorage para que tras guardar se mantenga
                        let trSel = serverTractor || (v.tractor || 'Horas Comunes');
                        if (trSel === 'New Holland TM150') trSel = 'T7.195/4CPAT';
                        tractorSelect.value = trSel;
                        if (fechaInput && v.fecha) fechaInput.value = v.fecha;
                    } catch (e) {}
                }
                // Cant gasoil y cambio de aceite: siempre por defecto 0 y destildado (no restaurar desde localStorage)
                <?php endif; ?>
                
                // Guardar valores cuando cambian (tractor siempre para persistir último elegido)
                function guardarValores() {
                    if (!tractorSelect || !fechaInput) return;
                    const fincaNuevaEl = document.getElementById('fincaNueva');
                    const valores = {
                        tractor: tractorSelect.value || 'Horas Comunes',
                        fecha: fechaInput.value,
                        finca_sel: selFinca ? selFinca.value : '',
                        finca_nueva: fincaNuevaEl ? fincaNuevaEl.value : '',
                        cant_gasoil: cantGasoilInput ? cantGasoilInput.value : '',
                        cambio_aceite: cambioAceiteInput ? (cambioAceiteInput.checked ? '1' : '0') : '0'
                    };
                    try { localStorage.setItem('pdt_ultimos_valores', JSON.stringify(valores)); } catch (e) {}
                }
                
                // Función para manejar cambio (Horas Comunes / Tractor)
                function manejarCambioTractor() {
                    if (!tractorGroup || !tractorSelect) return;
                    if (typeof guardarValores === 'function') guardarValores();
                    if (esHorasTractor()) {
                        tractorGroup.style.display = 'block';
                        tractorSelect.required = true;
                        if (gasoilGroup) gasoilGroup.style.display = 'block';
                        if (cambioAceiteGroup) cambioAceiteGroup.style.display = 'block';
                        if (cantGasoilInput) cantGasoilInput.required = true;
                        if (labelCantidad) labelCantidad.textContent = 'Cantidad horas *';
                        // Cargar valores guardados si existen (solo al cambiar, no al cargar inicial)
                        <?php if (!$pdt_edit): ?>
                        const valoresGuardados = JSON.parse(localStorage.getItem('pdt_ultimos_valores') || '{}');
                        let tgv = valoresGuardados.tractor;
                        if (tgv === 'New Holland TM150') tgv = 'T7.195/4CPAT';
                        if (tgv && !tractorSelect.value) {
                            tractorSelect.value = tgv;
                        }
                        <?php endif; ?>
                        if (typeof actualizarTractorDesdeCambioAceite === 'function') actualizarTractorDesdeCambioAceite();
                    } else {
                        tractorSelect.required = false;
                        if (gasoilGroup) gasoilGroup.style.display = 'none';
                        if (cambioAceiteGroup) cambioAceiteGroup.style.display = 'none';
                        if (cantGasoilInput) cantGasoilInput.required = false;
                        if (labelCantidad) labelCantidad.textContent = 'Cantidad *';
                    }
                }
                
                if (tractorSelect) {
                    tractorSelect.addEventListener('change', manejarCambioTractor);
                    manejarCambioTractor();
                }
    
                
                // Guardar valores cuando cambian los campos
                function actualizarTractorDesdeCambioAceite() {
                    if (!tractorSelect) return;
                    var div = document.getElementById('tractorDesdeCambioAceite');
                    if (!div) return;
                    var tractor = tractorSelect.value;
                    if (!tractor || !esHorasTractor()) {
                        div.style.display = 'none';
                        return;
                    }
                    var datos = tractorDesdeCambio[tractor];
                    if (datos) {
                        var horasEl = document.getElementById('tractorHorasDesdeCambio');
                        var gasoilEl = document.getElementById('tractorGasoilDesdeCambio');
                        if (horasEl) horasEl.textContent = Number(datos.horas).toLocaleString('es-AR', { minimumFractionDigits: 0, maximumFractionDigits: 1 });
                        if (gasoilEl) gasoilEl.textContent = Number(datos.gasoil).toLocaleString('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                        div.style.display = 'block';
                    } else {
                        div.style.display = 'none';
                    }
                }
                if (tractorSelect) {
                    tractorSelect.addEventListener('change', function() {
                        guardarValores();
                        actualizarTractorDesdeCambioAceite();
                        // Aplicar color del tractor seleccionado al select
                        var opt = tractorSelect.options[tractorSelect.selectedIndex];
                        tractorSelect.classList.remove('tractor-jd', 'tractor-nh', 'tractor-mf');
                        if (opt && opt.classList.contains('tractor-jd')) tractorSelect.classList.add('tractor-jd');
                        else if (opt && opt.classList.contains('tractor-nh')) tractorSelect.classList.add('tractor-nh');
                        else if (opt && opt.classList.contains('tractor-mf')) tractorSelect.classList.add('tractor-mf');
                    });
                }
                if (fechaInput) fechaInput.addEventListener('change', guardarValores);
                if (horasInput) {
                    horasInput.addEventListener('blur', guardarValores);
                    function seleccionarCantidad() {
                        var el = horasInput;
                        el.select();
                        if (el.setSelectionRange) el.setSelectionRange(0, (el.value || '').length);
                    }
                    horasInput.addEventListener('focus', function() { setTimeout(seleccionarCantidad, 50); });
                    horasInput.addEventListener('click', function() { setTimeout(seleccionarCantidad, 50); });
                    horasInput.addEventListener('keydown', function(e) {
                        if (horasInput.value === '0' && /^[0-9]$/.test(e.key)) {
                            horasInput.value = '';
                        }
                    });
                    horasInput.addEventListener('input', function() {
                        this.value = this.value.replace(/[^0-9]/g, '');
                    });
                }
                if (cantGasoilInput) cantGasoilInput.addEventListener('blur', guardarValores);
                if (cambioAceiteInput) cambioAceiteInput.addEventListener('change', guardarValores);
                if (selFinca) selFinca.addEventListener('change', guardarValores);
                var fincaNuevaInp = document.getElementById('fincaNueva');
                if (fincaNuevaInp) fincaNuevaInp.addEventListener('blur', guardarValores);
                
                // Guardar valores al enviar el formulario
                var formPDT = document.getElementById('formPDT');
                if (formPDT) {
                    formPDT.addEventListener('submit', function() {
                        guardarValores();
                    });
                }
                
                // Navegación con Enter entre campos hasta el botón Guardar (secuencia: fecha, cantidad, observaciones, cant gasoil, cambio aceite, guardar)
                if (btnGuardar && buscador && tractorSelect) {
                    const camposOrden = [buscador, selFinca, tractorSelect, fechaInput, horasInput, observacionesTextarea, cantGasoilInput, cambioAceiteInput, btnGuardar].filter(c => c !== null);
                    
                    camposOrden.forEach((campo, index) => {
                        if (!campo) return;
                        campo.addEventListener('keydown', function(e) {
                            if (e.key === 'Enter') {
                                // En el botón Guardar, no interceptar: que Enter envíe el formulario
                                if (campo === btnGuardar) return;
                                e.preventDefault();
                                // Si es el buscador y hay resultados, seleccionar el primero
                                if (campo === buscador && resultados && resultados.style.display === 'block' && resultados.children.length > 0) {
                                    const primerItem = resultados.querySelector('.usuario-item');
                                    if (primerItem) {
                                        primerItem.click();
                                        setTimeout(() => { if (selFinca) selFinca.focus(); else if (tractorSelect) tractorSelect.focus(); }, 100);
                                        return;
                                    }
                                }
                                // Buscar el siguiente campo visible (o el botón Guardar)
                                for (let i = index + 1; i < camposOrden.length; i++) {
                                    const siguienteCampo = camposOrden[i];
                                    if (siguienteCampo && (siguienteCampo === btnGuardar || (siguienteCampo.offsetParent !== null && siguienteCampo.style.display !== 'none'))) {
                                        siguienteCampo.focus();
                                        if (siguienteCampo.select) siguienteCampo.select();
                                        break;
                                    }
                                }
                            }
                        });
                    });
                }
                
                // También permitir Enter en resultados del buscador
                if (resultados && tractorSelect) {
                    resultados.addEventListener('keydown', function(e) {
                        if (e.key === 'Enter' && e.target.classList.contains('usuario-item')) {
                            e.target.click();
                            setTimeout(() => { if (selFinca) selFinca.focus(); else if (tractorSelect) tractorSelect.focus(); }, 100);
                        }
                    });
                }
                
                // Trigger inicial para cargar valores guardados y mostrar campos según tipo
                if (typeof manejarCambioTractor === 'function') {
                    manejarCambioTractor();
                }
                // Aplicar color del tractor seleccionado al cargar
                if (tractorSelect) {
                    (function() {
                        var opt = tractorSelect.options[tractorSelect.selectedIndex];
                        if (opt) {
                            tractorSelect.classList.remove('tractor-jd', 'tractor-nh', 'tractor-mf');
                            if (opt.classList.contains('tractor-jd')) tractorSelect.classList.add('tractor-jd');
                            else if (opt.classList.contains('tractor-nh')) tractorSelect.classList.add('tractor-nh');
                            else if (opt.classList.contains('tractor-mf')) tractorSelect.classList.add('tractor-mf');
                        }
                    })();
                }

                var enfocarTipoTrabajo = <?= (!empty($preseleccionar_usuario_id) && !$pdt_edit) ? 'true' : 'false' ?>;
                if (enfocarTipoTrabajo) {
                    if (selFinca) selFinca.focus();
                    else if (tractorSelect) tractorSelect.focus();
                }

                // Mostrar etiquetas CC=NO del usuario seleccionado (si hay)
                actualizarResumenHorasUsuario();

                // Debug: confirmar que el código se ejecutó
                console.log('Gestión finca inicializada correctamente');
            } catch (e) {
                console.error('Error al inicializar gestión finca:', e);
                console.error('Stack:', e.stack);
            }
        }
        
        // Ejecutar cuando el DOM esté listo
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', init);
        } else {
            init();
        }
    })();
    
    // Inicializar botón carga gasoil de forma completamente independiente
    // Esto asegura que funcione incluso si hay errores en otras partes del código
    (function initCargaGasoil() {
        var formCargaGasoilSisterna = null;
        var btnCargaGasoilSisterna = null;
        var intentos = 0;
        var maxIntentos = 50; // 5 segundos máximo
        
        function initGasoil() {
            try {
                formCargaGasoilSisterna = document.getElementById('formCargaGasoilSisterna');
                btnCargaGasoilSisterna = document.getElementById('btnCargaGasoilSisterna');
                
                if (!formCargaGasoilSisterna || !btnCargaGasoilSisterna) {
                    intentos++;
                    if (intentos < maxIntentos) {
                        setTimeout(initGasoil, 100);
                        return;
                    } else {
                        console.error('No se encontraron elementos de carga gasoil después de ' + maxIntentos + ' intentos');
                        return;
                    }
                }
                
                // Función global para toggle del formulario
                window.toggleCargaGasoilForm = function() {
                    if (!formCargaGasoilSisterna) {
                        formCargaGasoilSisterna = document.getElementById('formCargaGasoilSisterna');
                        if (!formCargaGasoilSisterna) return;
                    }
                    var estilo = window.getComputedStyle(formCargaGasoilSisterna);
                    var estaVisible = estilo.display !== 'none' && formCargaGasoilSisterna.style.display !== 'none';
                    formCargaGasoilSisterna.style.display = estaVisible ? 'none' : 'block';
                };
                
                // Remover listeners anteriores si existen (por si se ejecuta múltiples veces)
                var nuevoBtn = btnCargaGasoilSisterna.cloneNode(true);
                btnCargaGasoilSisterna.parentNode.replaceChild(nuevoBtn, btnCargaGasoilSisterna);
                btnCargaGasoilSisterna = nuevoBtn;
                
                // Listener para click y touchend (móviles)
                btnCargaGasoilSisterna.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    window.toggleCargaGasoilForm();
                });
                btnCargaGasoilSisterna.addEventListener('touchend', function(e) {
                    e.preventDefault();
                    window.toggleCargaGasoilForm();
                });
                
                // Envío explícito del form gasoil (evita problemas con form dentro de display:none en móviles)
                var formGasoil = document.getElementById('formGasoilCisterna');
                var btnRegistrar = document.getElementById('btnRegistrarCargaGasoil');
                var gasoilEnviando = false;
                function enviarFormGasoil() {
                    if (!formGasoil || gasoilEnviando) return;
                    var fecha = document.getElementById('fecha_carga_gasoil');
                    var cant = document.getElementById('cantidad_carga_gasoil');
                    if (fecha && !fecha.value) { alert('Ingrese la fecha.'); fecha.focus(); return; }
                    if (cant && (!cant.value || parseFloat(cant.value) <= 0)) { alert('Ingrese la cantidad (L).'); if (cant) cant.focus(); return; }
                    gasoilEnviando = true;
                    formGasoil.submit();
                }
                if (formGasoil && btnRegistrar) {
                    btnRegistrar.addEventListener('click', function(e) { e.preventDefault(); enviarFormGasoil(); });
                    btnRegistrar.addEventListener('touchend', function(e) { e.preventDefault(); enviarFormGasoil(); });
                    formGasoil.addEventListener('keydown', function(e) {
                        if (e.key === 'Enter' || e.keyCode === 13) { e.preventDefault(); enviarFormGasoil(); }
                    });
                }
                
                console.log('Botón carga gasoil inicializado correctamente');
            } catch (err) {
                console.error('Error al inicializar botón carga gasoil:', err);
                console.error('Stack:', err.stack);
            }
        }
        
        // Ejecutar cuando el DOM esté listo
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', initGasoil);
        } else {
            initGasoil();
        }
    })();
    
    </script>
    <script>
    (function(){
        function setupTabPDT(){
            var f=document.getElementById('formPDT'),fe=document.getElementById('fecha'),h=document.getElementById('horas'),o=document.getElementById('observaciones'),cg=document.getElementById('cant_gasoil'),ca=document.getElementById('cambio_aceite'),g=document.getElementById('btnGuardar');
            if(!f||!fe||!h||!o||!g)return false;
            if(f.dataset.tabInit)return true;
            f.dataset.tabInit='1';
            var campos=[fe,h,o,cg,ca,g].filter(function(x){return x;});
            function siguiente(i,dir){var j=i+dir;while(j>=0&&j<campos.length){var c=campos[j];if(c&&c.offsetParent!==null&&c.style.display!=='none')return c;j+=dir;}return null;}
            f.addEventListener('keydown',function(e){
                var k=(e.key==='Tab')?9:(e.keyCode||e.which||0);
                if(k!==9)return;
                var a=document.activeElement,idx=-1;
                for(var i=0;i<campos.length;i++){if(campos[i]===a){idx=i;break;}}
                var next=(e.shiftKey)?siguiente(idx,-1):siguiente(idx,1);
                if(next){e.preventDefault();next.focus();if(next.select)next.select();}
            },true);
            return true;
        }
        function runSetupTab(){
            var ok=setupTabPDT();
            if(!ok){var n=(window._tabRetry=(window._tabRetry||0)+1);if(n<=20)setTimeout(runSetupTab,100);}
        }
        if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',runSetupTab);
        else runSetupTab();
    })();
    </script>
<?php include 'nav_enter_form_inc.php'; ?>
</body>
</html>
