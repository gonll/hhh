<?php
include 'db.php';
include 'verificar_sesion.php';

if (!defined('DESDE_CEL')) define('DESDE_CEL', false);
$desde_cel = DESDE_CEL;
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

// Verificar si la tabla existe, si no crearla
$res_check = mysqli_query($conexion, "SHOW TABLES LIKE 'pdt'");
if (!$res_check || mysqli_num_rows($res_check) == 0) {
    // Crear tabla
    $sql_create = "CREATE TABLE IF NOT EXISTS pdt (
        id INT AUTO_INCREMENT PRIMARY KEY,
        usuario_id INT NOT NULL,
        tipo_horas ENUM('Horas tractos', 'Horas Comunes') NOT NULL DEFAULT 'Horas Comunes',
        tractor VARCHAR(100) NULL,
        fecha DATE NOT NULL,
        horas DECIMAL(5,2) DEFAULT 0.00,
        observaciones TEXT NULL,
        fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        fecha_modificacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
        INDEX idx_usuario (usuario_id),
        INDEX idx_fecha (fecha)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci";
    mysqli_query($conexion, $sql_create);
}

// Asegurar columnas en pdt (migración: servidor puede tener tabla antigua sin cant_gasoil, cambio_aceite, en_cc, tipo_horas, tractor)
$res_cols = mysqli_query($conexion, "SHOW COLUMNS FROM pdt LIKE 'cant_gasoil'");
if ($res_cols && mysqli_num_rows($res_cols) == 0) {
    mysqli_query($conexion, "ALTER TABLE pdt ADD COLUMN cant_gasoil DECIMAL(6,2) NULL AFTER horas");
}
$res_cols2 = mysqli_query($conexion, "SHOW COLUMNS FROM pdt LIKE 'cambio_aceite'");
if ($res_cols2 && mysqli_num_rows($res_cols2) == 0) {
    mysqli_query($conexion, "ALTER TABLE pdt ADD COLUMN cambio_aceite TINYINT(1) DEFAULT 0 AFTER cant_gasoil");
}
$res_cols3 = mysqli_query($conexion, "SHOW COLUMNS FROM pdt LIKE 'en_cc'");
if ($res_cols3 && mysqli_num_rows($res_cols3) == 0) {
    mysqli_query($conexion, "ALTER TABLE pdt ADD COLUMN en_cc TINYINT(1) DEFAULT 0 AFTER cambio_aceite");
}
$res_cols4 = mysqli_query($conexion, "SHOW COLUMNS FROM pdt LIKE 'tipo_horas'");
if ($res_cols4 && mysqli_num_rows($res_cols4) == 0) {
    mysqli_query($conexion, "ALTER TABLE pdt ADD COLUMN tipo_horas VARCHAR(50) NOT NULL DEFAULT 'Horas Comunes' AFTER usuario_id");
}
$res_cols5 = mysqli_query($conexion, "SHOW COLUMNS FROM pdt LIKE 'tractor'");
if ($res_cols5 && mysqli_num_rows($res_cols5) == 0) {
    mysqli_query($conexion, "ALTER TABLE pdt ADD COLUMN tractor VARCHAR(100) NULL AFTER tipo_horas");
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

// Procesar acciones
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Carga de gasoil en cisterna (+)
    if (isset($_POST['carga_gasoil_sisterna']) && isset($_POST['fecha_carga']) && isset($_POST['cantidad_carga'])) {
        $fecha_c = mysqli_real_escape_string($conexion, trim($_POST['fecha_carga']));
        $cant_c = (float)($_POST['cantidad_carga'] ?? 0);
        if ($fecha_c !== '' && $cant_c > 0) {
            $sql_ins = "INSERT INTO gasoil (fecha, cantidad, concepto) VALUES ('$fecha_c', $cant_c, 'Carga sisterna')";
            if (mysqli_query($conexion, $sql_ins)) {
                $mensaje = 'Carga de gasoil en cisterna registrada.';
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
        $tipo_horas = mysqli_real_escape_string($conexion, $_POST['tipo_horas'] ?? 'Horas Comunes');
        $tractor = ($tipo_horas === 'Horas tractos' && !empty($_POST['tractor']))
            ? mysqli_real_escape_string($conexion, $_POST['tractor']) : NULL;
        $fecha = trim($_POST['fecha'] ?? '');
        $horas = isset($_POST['horas']) && $_POST['horas'] !== '' ? (int)$_POST['horas'] : null;
        $cant_gasoil = ($tipo_horas === 'Horas tractos' && isset($_POST['cant_gasoil']) && $_POST['cant_gasoil'] !== '')
            ? (float)$_POST['cant_gasoil'] : NULL;
        $cambio_aceite = ($tipo_horas === 'Horas tractos' && isset($_POST['cambio_aceite'])) ? 1 : 0;
        $observaciones = mysqli_real_escape_string($conexion, trim($_POST['observaciones'] ?? ''));

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
                   "cant_gasoil = " . ($cant_gasoil !== NULL ? $cant_gasoil : "NULL") . ", cambio_aceite = $cambio_aceite, en_cc = $en_cc_actual, observaciones = '$observaciones' WHERE id = $pdt_id";
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
            } else {
                $mensaje = 'Falta dato o corregir.';
            }
        } else {
            // Alta - en_cc siempre empieza en 0
            $sql = "INSERT INTO pdt (usuario_id, tipo_horas, tractor, fecha, horas, cant_gasoil, cambio_aceite, en_cc, observaciones) 
                    VALUES ($usuario_id, '$tipo_horas', " . ($tractor ? "'$tractor'" : "NULL") . ", '$fecha', $horas, " .
                    ($cant_gasoil !== NULL ? $cant_gasoil : "NULL") . ", $cambio_aceite, 0, '$observaciones')";
            if (mysqli_query($conexion, $sql)) {
                $pdt_id_nuevo = (int)mysqli_insert_id($conexion);
                if ($tipo_horas === 'Horas tractos' && $cant_gasoil !== NULL && $cant_gasoil > 0) {
                    $cant_neg = - (float)$cant_gasoil;
                    $concepto_tractor = 'Tractor' . ($tractor ? ' ' . mysqli_real_escape_string($conexion, $tractor) : '');
                    mysqli_query($conexion, "INSERT INTO gasoil (fecha, cantidad, concepto, pdt_id) VALUES ('$fecha', $cant_neg, '$concepto_tractor', $pdt_id_nuevo)");
                }
                $mensaje = 'Parte guardado.';
                $preseleccionar_usuario_id = $usuario_id;
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
        mysqli_query($conexion, "DELETE FROM gasoil WHERE pdt_id IS NOT NULL");
        if (mysqli_query($conexion, "DELETE FROM pdt")) {
            $mensaje = 'Todos los registros PDT han sido eliminados.';
        } else {
            $mensaje = 'Falta dato o corregir.';
        }
    } elseif (isset($_POST['cargar_cc'])) {
        $pdt_id = (int)$_POST['pdt_id'];
        $sql = "UPDATE pdt SET en_cc = 1 WHERE id = $pdt_id";
        if (mysqli_query($conexion, $sql)) {
            $mensaje = 'PDT marcado como cargado en cuenta corriente.';
        } else {
            $mensaje = 'Falta dato o corregir.';
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

// Obtener lista de PDTs (vista completa: 200 registros; móvil nivel 0: solo 3)
// SELECT explícito para compatibilidad con servidores que puedan tener columnas en distinto orden
$lista_pdt = [];
$sql_lista = $mostrar_vista_completa
    ? "SELECT p.id, p.usuario_id, p.tipo_horas, p.tractor, p.fecha, p.horas, p.cant_gasoil, p.cambio_aceite, p.en_cc, p.observaciones, u.apellido AS usuario_nombre FROM pdt p INNER JOIN usuarios u ON u.id = p.usuario_id ORDER BY p.fecha DESC, p.id DESC LIMIT 200"
    : "SELECT p.id, p.usuario_id, p.tipo_horas, p.tractor, p.fecha, p.horas, p.cant_gasoil, p.cambio_aceite, p.en_cc, p.observaciones, u.apellido AS usuario_nombre FROM pdt p INNER JOIN usuarios u ON u.id = p.usuario_id ORDER BY p.fecha DESC, p.id DESC LIMIT 3";
$res_lista = mysqli_query($conexion, $sql_lista);
if ($res_lista) {
    while ($row = mysqli_fetch_assoc($res_lista)) {
        // Normalizar claves a minúsculas para compatibilidad con servidores que devuelven mayúsculas
        $lista_pdt[] = array_combine(array_map('strtolower', array_keys($row)), array_values($row));
    }
}

// Obtener usuarios para el buscador
$sql_usuarios = "SELECT id, apellido FROM usuarios ORDER BY apellido ASC";
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
$tractores_lista = array('John Deere 200 hp', 'John Deere 110 hp', 'New Holland TM150', 'New Holland 7630', 'Massey Ferguson 1165');
$tractor_desde_cambio = array();
foreach ($tractores_lista as $tr) {
    $tr_esc = mysqli_real_escape_string($conexion, $tr);
    $ultimo = mysqli_query($conexion, "SELECT id, fecha FROM pdt WHERE tractor = '$tr_esc' AND cambio_aceite = 1 ORDER BY fecha DESC, id DESC LIMIT 1");
    $horas = 0;
    $gasoil = 0;
    if ($ultimo && $row_ult = mysqli_fetch_assoc($ultimo)) {
        $id_ult = (int)$row_ult['id'];
        $fecha_ult = mysqli_real_escape_string($conexion, $row_ult['fecha']);
        $suma = mysqli_query($conexion, "SELECT COALESCE(SUM(horas),0) AS h, COALESCE(SUM(cant_gasoil),0) AS g FROM pdt WHERE tractor = '$tr_esc' AND (fecha > '$fecha_ult' OR (fecha = '$fecha_ult' AND id > $id_ult))");
        if ($suma && $s = mysqli_fetch_assoc($suma)) {
            $horas = (float)$s['h'];
            $gasoil = (float)$s['g'];
        }
    } else {
        $suma = mysqli_query($conexion, "SELECT COALESCE(SUM(horas),0) AS h, COALESCE(SUM(cant_gasoil),0) AS g FROM pdt WHERE tractor = '$tr_esc'");
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
        .form-row { display: flex; gap: 8px; margin-bottom: 11px; }
        .form-row .form-group { flex: 1; margin-bottom: 0; }
        .form-row .form-group.checkbox-group { flex: 0 0 auto; display: flex; align-items: flex-end; padding-bottom: 0; }
        /* Campos compactos: tipo trabajo, fecha, cantidad - mitad de ancho */
        .form-row .form-group.form-group-compact { flex: 0 0 auto; max-width: 110px; min-width: 90px; }
        /* Observaciones: misma línea que cantidad, 80% ancho */
        .form-group-observaciones { flex: 1 1 auto; min-width: 120px; max-width: 80%; }
        .form-group-observaciones textarea { width: 100%; font-size: 10px; padding: 4px 6px; min-height: 28px; resize: vertical; box-sizing: border-box; }
        .form-row .form-group.form-group-compact select,
        .form-row .form-group.form-group-compact input { width: 100%; font-size: 10px; padding: 4px 6px; }
        label { display: block; margin-bottom: 4px; font-weight: bold; color: #333; font-size: 10px; }
        input[type="text"], input[type="number"], input[type="date"], select, textarea {
            width: 100%; padding: 5px; border: 1px solid #ddd; border-radius: 3px; box-sizing: border-box; font-size: 11px;
        }
        .buscador-usuario { position: relative; }
        #buscadorUsuario { width: 100%; padding: 5px; border: 1px solid #ddd; border-radius: 3px; font-size: 11px; }
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
        th { background: #007bff; color: white; padding: 6px 4px; text-align: left; font-size: 10px; }
        td { padding: 5px 4px; border-bottom: 1px solid #eee; font-size: 10px; }
        tr:hover { background: #f8f9fa; }
        .tabla-listado-pdt { table-layout: auto; width: 100%; }
        .tabla-listado-pdt th, .tabla-listado-pdt td { text-align: left; }
        /* Columnas de datos: mostrar contenido; ellipsis solo si es muy largo */
        .tabla-listado-pdt td.col-personal, .tabla-listado-pdt td.col-tipo, .tabla-listado-pdt td.col-tractor { min-width: 80px; overflow: visible; }
        .tabla-listado-pdt td.col-acciones, .tabla-listado-pdt th.col-acciones { overflow: visible; position: relative; z-index: 2; background: #fff !important; }
        .tabla-listado-pdt th.col-acciones { background: #007bff !important; }
        .tabla-listado-pdt td.col-acciones { background: #fff !important; }
        .tabla-listado-pdt tr:hover td.col-acciones { background: #f8f9fa !important; }
        .tabla-listado-pdt tr.fila-con-observaciones td { background: #ffebee; color: #b71c1c; }
        .tabla-listado-pdt tr.fila-con-observaciones:hover td { background: #ffcdd2; }
        .tabla-listado-pdt tr.fila-con-observaciones td.col-acciones { background: #ffebee !important; }
        .tabla-listado-pdt tr.fila-con-observaciones:hover td.col-acciones { background: #ffcdd2 !important; }
        .wrap-tabla-pdt { width: 100%; max-width: 100%; overflow-x: auto; overflow-y: visible; }
        /* grid1: tamaño reducido por defecto; .tamano-original restaura el tamaño completo */
        #grid1 { min-height: 200px; max-height: 35vh; overflow-y: auto; overflow-x: auto; -webkit-overflow-scrolling: touch; display: block; }
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
        .tabla-listado-pdt th.col-tipo, .tabla-listado-pdt td.col-tipo { min-width: 80px; }
        .tabla-listado-pdt th.col-tractor, .tabla-listado-pdt td.col-tractor { min-width: 90px; }
        .tabla-listado-pdt th.col-fecha, .tabla-listado-pdt td.col-fecha { min-width: 85px; }
        .tabla-listado-pdt th.col-cantidad, .tabla-listado-pdt td.col-cantidad { min-width: 55px; }
        .tabla-listado-pdt th.col-gasoil, .tabla-listado-pdt td.col-gasoil { min-width: 55px; }
        .tabla-listado-pdt th.col-cambio, .tabla-listado-pdt td.col-cambio { min-width: 50px; text-align: center; }
        .tabla-listado-pdt th.col-cc, .tabla-listado-pdt td.col-cc { min-width: 40px; text-align: center; }
        .tabla-listado-pdt th.col-acciones, .tabla-listado-pdt td.col-acciones { min-width: 220px; white-space: nowrap; overflow: visible; }
        .icono-tractor { width: 15px; height: 15px; display: inline-block; margin-right: 3px; vertical-align: middle; }
        #tractorGroup { display: none; }
        /* Iconos tractores por marca: John Deere verde, New Holland azul, Massey Ferguson rojo */
        #tractor option.tractor-jd { color: #367c2b; font-weight: bold; }
        #tractor option.tractor-nh { color: #0066b3; font-weight: bold; }
        #tractor option.tractor-mf { color: #c41e3a; font-weight: bold; }
        #tractor.tractor-jd { color: #367c2b; font-weight: bold; }
        #tractor.tractor-nh { color: #0066b3; font-weight: bold; }
        #tractor.tractor-mf { color: #c41e3a; font-weight: bold; }
        .checkbox-label { display: flex; align-items: center; margin-top: 0; padding-top: 15px; font-size: 11px; }
        input[type="checkbox"] { width: 15px; height: 15px; margin-right: 4px; }
        .buscador-usuario-container { width: 25%; }
        .acciones-botones { display: inline-flex; gap: 6px; flex-wrap: nowrap; white-space: nowrap; }
        .acciones-botones form { display: inline-block; margin: 0; }
        .acciones-botones .btn { padding: 4px 8px; font-size: 10px; flex-shrink: 0; }
        textarea { font-size: 11px; padding: 5px; }
        #usuarioSeleccionado { font-size: 10px; padding: 3px 5px !important; }
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
            <div style="text-align: right; flex-shrink: 0;">
                <div style="margin-bottom: 6px;">
                    <button type="button" id="btnCargaGasoilSisterna" class="btn btn-secondary" style="font-size: 12px;" onclick="var f=document.getElementById('formCargaGasoilSisterna');if(f){f.style.display=(f.style.display==='block')?'none':'block';}" ontouchend="var f=document.getElementById('formCargaGasoilSisterna');if(f){f.style.display=(f.style.display==='block')?'none':'block';}event.preventDefault();">Carga gasoil en cisterna</button>
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
                    <form method="post" action="<?= htmlspecialchars($form_action_url) ?>" style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap;">
                        <input type="hidden" name="carga_gasoil_sisterna" value="1">
                        <label style="margin: 0;">Fecha</label>
                        <input type="date" name="fecha_carga" value="<?= date('Y-m-d') ?>" required>
                        <label style="margin: 0;">Cantidad (L)</label>
                        <input type="number" name="cantidad_carga" step="0.01" min="0.01" required placeholder="Ej. 500">
                        <button type="submit" class="btn btn-primary" style="font-size: 12px;">Registrar carga (+)</button>
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
            <div class="mensaje <?= (strpos($mensaje, 'Error') !== false || strpos($mensaje, 'Falta dato') !== false) ? 'error' : 'ok' ?>">
                <?= htmlspecialchars($mensaje) ?>
            </div>
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
        <script>
        (function(){
            function setupTab(){
                var f=document.getElementById('formPDT'),t=document.getElementById('tipo_horas'),tr=document.getElementById('tractor'),fe=document.getElementById('fecha'),h=document.getElementById('horas'),cg=document.getElementById('cant_gasoil'),ca=document.getElementById('cambio_aceite'),o=document.getElementById('observaciones'),g=document.getElementById('btnGuardar');
                if(!f||!t||!fe||!h||!o||!g)return;
                var campos=[t,tr,fe,h,cg,ca,o,g].filter(function(x){return x;});
                function siguiente(i,dir){var j=i+dir;while(j>=0&&j<campos.length){var c=campos[j];if(c&&c.offsetParent!==null&&c.style.display!=='none')return c;j+=dir;}return null;}
                f.addEventListener('keydown',function(e){
                    var k=(e.key==='Tab')?9:(e.keyCode||e.which||0);
                    if(k!==9)return;
                    var a=document.activeElement,idx=-1;
                    for(var i=0;i<campos.length;i++){if(campos[i]===a){idx=i;break;}}
                    var next=(e.shiftKey)?siguiente(idx,-1):siguiente(idx,1);
                    if(next){e.preventDefault();next.focus();if(next.select)next.select();}
                },true);
            }
            if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',setupTab);
            else setupTab();
        })();
        </script>
            <?php if ($pdt_edit): ?>
                <input type="hidden" name="pdt_id" value="<?= $pdt_edit['id'] ?>">
            <?php endif; ?>
            
            <div style="display: flex; align-items: flex-start; gap: 14px; flex-wrap: wrap; margin-bottom: 11px;">
                <div class="form-group buscador-usuario-container">
                    <label>Personal *</label>
                    <div class="buscador-usuario">
                        <input type="text" id="buscadorUsuario" tabindex="-1" placeholder="Buscar..." autocomplete="off" value="<?= htmlspecialchars($nombre_personal_mostrar) ?>" oninput="if(window.buscarPersonalPdt)window.buscarPersonalPdt();" onkeyup="if(window.buscarPersonalPdt)window.buscarPersonalPdt();">
                        <input type="hidden" name="usuario_id" id="usuario_id" value="<?= $pdt_edit ? $pdt_edit['usuario_id'] : ($preseleccionar_usuario_id ?? '') ?>" required>
                        <div id="resultadosUsuario"></div>
                        <div id="usuarioSeleccionado" style="margin-top: 5px; padding: 3px 5px; background: #e7f3ff; border-radius: 4px; font-size: 11px; <?= ($nombre_personal_mostrar !== '') ? '' : 'display: none;' ?>">
                            <strong>Sel:</strong> <span id="nombreUsuarioSel"><?= htmlspecialchars($nombre_personal_mostrar) ?></span>
                        </div>
                    </div>
                </div>
                <script>
                (function(){
                    var u = <?= json_encode(array_values($usuarios), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) ?>;
                    var b = document.getElementById('buscadorUsuario');
                    var r = document.getElementById('resultadosUsuario');
                    var uid = document.getElementById('usuario_id');
                    var nomSel = document.getElementById('nombreUsuarioSel');
                    var divSel = document.getElementById('usuarioSeleccionado');
                    if(!b||!r||!uid) return;
                    var minC = 2;
                    function buscar(){
                        var t = (b.value||'').toLowerCase().trim();
                        if(t.length < minC){ r.style.display='none'; if(!t){ uid.value=''; if(divSel) divSel.style.display='none'; } return; }
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
                    r.onclick = function(e){
                        var el = e.target;
                        if(!el || !el.getAttribute('data-id')) return;
                        if(el.className && el.className.indexOf('usuario-item')===-1) return;
                        var id = el.getAttribute('data-id');
                        var nom = el.getAttribute('data-nombre')||'';
                        uid.value = id;
                        b.value = nom;
                        if(nomSel) nomSel.textContent = nom;
                        if(divSel) divSel.style.display = 'block';
                        r.style.display = 'none';
                    };
                    r.ontouchend = function(e){
                        var el = e.target;
                        if(el && el.getAttribute('data-id')){ e.preventDefault(); var id=el.getAttribute('data-id'); var nom=el.getAttribute('data-nombre')||''; uid.value=id; b.value=nom; if(nomSel) nomSel.textContent=nom; if(divSel) divSel.style.display='block'; r.style.display='none'; }
                    };
                })();
                </script>
                <div style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap; padding-top: 20px;">
                    <?php if ($mostrar_vista_completa): ?>
                    <a href="gestionar_tabla_salarial.php" class="btn btn-secondary" style="font-size: 11px; padding: 5px 10px;">ABM Tabla salarial</a>
                    <span id="valoresSalarialesFinca" style="font-size: 11px; color: #333; padding: 4px 8px; background: #e8f4e8; border-radius: 4px; border: 1px solid #c8e6c9;">
                        Hora común: $ <?= number_format($ultima_tabla_salarial['valor_hora_comun'], 2, ',', '.') ?> &nbsp;|&nbsp; Hora tractor: $ <?= number_format($ultima_tabla_salarial['valor_hora_tractor'], 2, ',', '.') ?>
                    </span>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group form-group-compact">
                    <label>Tipo de trabajo *</label>
                    <select name="tipo_horas" id="tipo_horas" tabindex="1" required>
                        <optgroup label="Horas tractos">
                            <option value="Horas tractos" <?= ($pdt_edit && $pdt_edit['tipo_horas'] === 'Horas tractos') ? 'selected' : '' ?>>Horas tractos</option>
                        </optgroup>
                        <optgroup label="Horas Comunes">
                            <option value="Horas Comunes" <?= ($pdt_edit && $pdt_edit['tipo_horas'] === 'Horas Comunes') ? 'selected' : (!isset($pdt_edit) ? 'selected' : '') ?>>Horas Comunes</option>
                        </optgroup>
                    </select>
                </div>
                
                <div class="form-group" id="tractorGroup">
                    <label>Tractor *</label>
                    <select name="tractor" id="tractor" tabindex="-1">
                        <optgroup label="John Deere">
                            <option class="tractor-jd" value="John Deere 200 hp" <?= ($pdt_edit && $pdt_edit['tractor'] === 'John Deere 200 hp') ? 'selected' : '' ?>>
                                🚜 John Deere 200 hp
                            </option>
                            <option class="tractor-jd" value="John Deere 110 hp" <?= ($pdt_edit && $pdt_edit['tractor'] === 'John Deere 110 hp') ? 'selected' : '' ?>>
                                🚜 John Deere 110 hp
                            </option>
                        </optgroup>
                        <optgroup label="New Holland">
                            <option class="tractor-nh" value="New Holland TM150" <?= ($pdt_edit && $pdt_edit['tractor'] === 'New Holland TM150') ? 'selected' : '' ?>>
                                🚜 New Holland TM150
                            </option>
                            <option class="tractor-nh" value="New Holland 7630" <?= ($pdt_edit && $pdt_edit['tractor'] === 'New Holland 7630') ? 'selected' : '' ?>>
                                🚜 New Holland 7630
                            </option>
                        </optgroup>
                        <optgroup label="Massey Ferguson">
                            <option class="tractor-mf" value="Massey Ferguson 1165" <?= ($pdt_edit && $pdt_edit['tractor'] === 'Massey Ferguson 1165') ? 'selected' : '' ?>>
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
                    <input type="date" name="fecha" id="fecha" tabindex="2" value="<?= $pdt_edit ? $pdt_edit['fecha'] : date('Y-m-d') ?>" required>
                </div>
                
                <div class="form-group form-group-compact" id="cantidadHorasGroup">
                    <label id="labelCantidad">Cantidad *</label>
                    <input type="text" inputmode="numeric" pattern="[0-9]*" name="horas" id="horas" tabindex="3" value="<?= $pdt_edit ? (int)$pdt_edit['horas'] : '' ?>" placeholder="0" required autocomplete="off">
                </div>
                
                <div class="form-group form-group-observaciones">
                    <label>Observaciones</label>
                    <textarea name="observaciones" id="observaciones" rows="1" tabindex="4" style="resize: vertical; min-height: 28px;" onkeydown="if((event||window.event).key==='Enter'&&!(event||window.event).shiftKey){(event||window.event).preventDefault();var g=document.getElementById('btnGuardar');if(g)g.focus();}"><?= htmlspecialchars($pdt_edit['observaciones'] ?? '') ?></textarea>
                </div>
                
                <div class="form-group" id="gasoilGroup" style="display: none;">
                    <label>Cant Gasoil *</label>
                    <input type="number" name="cant_gasoil" id="cant_gasoil" tabindex="-1" step="0.01" min="0" value="<?= $pdt_edit ? ($pdt_edit['cant_gasoil'] ?? '0') : '0' ?>">
                </div>
                
                <div class="form-group checkbox-group" id="cambioAceiteGroup" style="display: none;">
                    <label class="checkbox-label">
                        <input type="checkbox" name="cambio_aceite" id="cambio_aceite" tabindex="-1" value="1" <?= ($pdt_edit && isset($pdt_edit['cambio_aceite']) && $pdt_edit['cambio_aceite'] == 1) ? 'checked' : '' ?>>
                        Cambio de aceite
                    </label>
                </div>
            </div>
            
            <div style="display: flex; align-items: center; gap: 12px; flex-wrap: wrap;">
                <button type="submit" name="guardar" id="btnGuardar" class="btn btn-primary" tabindex="5">Guardar</button>
                <?php if ($mensaje === 'Parte guardado.'): ?>
                    <div id="cartelMensaje" class="cartel-parte-guardado parte-guardado">Parte guardado.</div>
                    <script>
                    (function(){
                        var c=document.getElementById('cartelMensaje');
                        if(c){setTimeout(function(){c.style.transition='opacity 0.3s';c.style.opacity='0';setTimeout(function(){c.remove();var f=document.getElementById('fecha');if(f)f.focus();},300);},2000);}
                    })();
                    </script>
                <?php endif; ?>
                <?php if ($mostrar_vista_completa): ?>
                <span id="resumenHorasUsuario" style="font-size: 11px; color: #555; padding: 4px 8px; background: #f0f0f0; border-radius: 4px; min-height: 24px; display: inline-flex; align-items: center;">Seleccione un usuario para ver el resumen (horas con CC=NO)</span>
                <?php endif; ?>
                <?php if ($pdt_edit && $mostrar_vista_completa): ?>
                    <a href="<?= htmlspecialchars($form_action_url) ?>" class="btn btn-secondary">Cancelar</a>
                <?php endif; ?>
                <?php if ($mostrar_vista_completa): ?>
                <button type="button" id="btnCargarCC" class="btn btn-success" style="font-size: 11px; padding: 5px 10px;" disabled title="Seleccione un usuario">Cargar en cuenta corriente</button>
                <?php endif; ?>
            </div>
        </form>
        
        <?php if ($mostrar_vista_completa): ?>
        <form method="POST" action="<?= htmlspecialchars($form_action_url) ?>" style="display:inline; margin-right:10px;" onsubmit="return confirm('¿Eliminar TODOS los registros PDT? Esta acción no se puede deshacer.');">
            <input type="hidden" name="eliminar_todos" value="1">
            <button type="submit" class="btn btn-danger" style="font-size: 11px; padding: 5px 10px;">Eliminar todos los PDT</button>
        </form>
        <?php endif; ?>
        
        <h3 style="margin-top: 30px;">Listado de PDTs</h3>
        <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 8px;">
            <button type="button" id="btnRestaurarGrid1" class="btn btn-secondary" style="font-size: 11px;" title="Restaurar tamaño original de grid1">Restaurar tamaño original</button>
        </div>
        <div id="grid1" class="wrap-tabla-pdt">
        <table class="tabla-listado-pdt">
            <thead>
                <tr>
                    <th class="col-id">ID</th>
                    <th class="col-acciones">Acciones</th>
                    <th class="col-personal">Personal</th>
                    <th class="col-tipo">Tipo</th>
                    <th class="col-tractor">Tractor</th>
                    <th class="col-fecha">Fecha</th>
                    <th class="col-cantidad">Cant.</th>
                    <th class="col-gasoil">Gasoil</th>
                    <th class="col-cambio">C.aceite</th>
                    <th class="col-cc">CC</th>
                </tr>
            </thead>
            <tbody>
                <?php $tiene_datos = count($lista_pdt) > 0; ?>
                <?php if ($tiene_datos): ?>
                    <?php foreach ($lista_pdt as $pdt): ?>
                        <?php $tiene_obs = !empty(trim($pdt['observaciones'] ?? '')); ?>
                        <tr class="fila-pdt<?= $tiene_obs ? ' fila-con-observaciones' : '' ?>" data-usuario-id="<?= (int)$pdt['usuario_id'] ?>"<?= $tiene_obs ? ' title="Clic para ver observaciones"' : '' ?>>
                            <td class="col-id" title="<?= (int)$pdt['id'] ?>"><?php $id = (string)$pdt['id']; echo strlen($id) > 6 ? substr($id, 0, 6) . '…' : $id; ?><?php if ($tiene_obs): ?><span class="obs-text-hidden" style="display:none"><?= htmlspecialchars(trim($pdt['observaciones'])) ?></span><?php endif; ?></td>
                            <td class="col-acciones">
                                <div class="acciones-botones">
                                    <form method="POST" action="<?= htmlspecialchars($form_action_url) ?>" style="display:inline;">
                                        <input type="hidden" name="pdt_id" value="<?= $pdt['id'] ?>">
                                        <button type="submit" name="editar" class="btn btn-secondary">Modificar</button>
                                    </form>
                                    <form method="POST" action="<?= htmlspecialchars($form_action_url) ?>" style="display:inline;">
                                        <input type="hidden" name="pdt_id" value="<?= $pdt['id'] ?>">
                                        <button type="submit" name="eliminar" class="btn btn-danger" onclick="return confirm('¿Eliminar este PDT?')">Eliminar</button>
                                    </form>
                                    <?php if ((!isset($pdt['en_cc']) || $pdt['en_cc'] == 0)): ?>
                                    <form method="POST" action="<?= htmlspecialchars($form_action_url) ?>" style="display:inline;">
                                        <input type="hidden" name="pdt_id" value="<?= $pdt['id'] ?>">
                                        <button type="submit" name="cargar_cc" class="btn btn-success">Cargar en CC</button>
                                    </form>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td class="col-personal" title="<?= htmlspecialchars($pdt['usuario_nombre']) ?>"><?= htmlspecialchars($pdt['usuario_nombre']) ?></td>
                            <td class="col-tipo" title="<?= htmlspecialchars($pdt['tipo_horas']) ?>"><?php $t = htmlspecialchars($pdt['tipo_horas']); echo mb_strlen($t) > 20 ? mb_substr($t, 0, 20) . '…' : $t; ?></td>
                            <td class="col-tractor"><?= htmlspecialchars($pdt['tractor'] ?? '-') ?></td>
                            <td class="col-fecha" title="<?= date('d/m/Y', strtotime($pdt['fecha'])) ?>"><?php $f = date('d/m/Y', strtotime($pdt['fecha'])); echo mb_strlen($f) > 20 ? mb_substr($f, 0, 20) . '…' : $f; ?></td>
                            <td class="col-cantidad"><?= number_format($pdt['horas'], 2, ',', '.') ?></td>
                            <td class="col-gasoil"><?= isset($pdt['cant_gasoil']) && $pdt['cant_gasoil'] !== null ? number_format($pdt['cant_gasoil'], 2, ',', '.') : '-' ?></td>
                            <td class="col-cambio"><?= (isset($pdt['cambio_aceite']) && $pdt['cambio_aceite'] == 1) ? '✓' : '-' ?></td>
                            <td class="col-cc" style="font-weight: bold; <?= (isset($pdt['en_cc']) && $pdt['en_cc'] == 1) ? 'color: #28a745;' : 'color: #dc3545;' ?>">
                                <?= (isset($pdt['en_cc']) && $pdt['en_cc'] == 1) ? 'SI' : 'NO' ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="10" style="text-align: center; padding: 15px; color: #666; font-size: 11px;">No hay partes diarios de trabajo registrados.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
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
            <a href="partes_desde_cel.php" class="btn btn-secondary">Partes desde cel</a>
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
        function initGrid1Toggle() {
            var grid1 = document.getElementById('grid1');
            var btn = document.getElementById('btnRestaurarGrid1');
            if (!grid1 || !btn) return;
            btn.addEventListener('click', function() {
                if (grid1.classList.contains('tamano-original')) {
                    grid1.classList.remove('tamano-original');
                    btn.textContent = 'Restaurar tamaño original';
                } else {
                    grid1.classList.add('tamano-original');
                    btn.textContent = 'Reducir tamaño';
                }
            });
        }
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', initGrid1Toggle);
        } else {
            initGrid1Toggle();
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
                const tipoHoras = document.getElementById('tipo_horas');
                const tractorGroup = document.getElementById('tractorGroup');
                const tractorSelect = document.getElementById('tractor');
                
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
                if (tractorGroup && tractorSelect && '<?= $pdt_edit['tipo_horas'] ?>' === 'Horas tractos') {
                    tractorGroup.style.display = 'block';
                    tractorSelect.required = true;
                    if (gasoilGroupEdit) gasoilGroupEdit.style.display = 'block';
                    if (cambioAceiteGroupEdit) cambioAceiteGroupEdit.style.display = 'block';
                    if (labelCantidadEdit) labelCantidadEdit.textContent = 'Cantidad horas *';
                    if (cantGasoilInputEdit) cantGasoilInputEdit.required = true;
                } else if (tractorGroup) {
                    tractorGroup.style.display = 'none';
                    if (gasoilGroupEdit) gasoilGroupEdit.style.display = 'none';
                    if (cambioAceiteGroupEdit) cambioAceiteGroupEdit.style.display = 'none';
                    if (cantGasoilInputEdit) cantGasoilInputEdit.required = false;
                }
                <?php endif; ?>
    
                // Buscador de usuarios (mínimo 2 caracteres)
                var minCaracteresBusqueda = 2;
                function ejecutarBusquedaUsuarios() {
                    if (!buscador || !resultados || !usuarioIdInput || !Array.isArray(usuarios)) return;
                    const termino = buscador.value.toLowerCase().trim();
                    if (termino.length < minCaracteresBusqueda) {
                        resultados.style.display = 'none';
                        if (termino === '') {
                            usuarioIdInput.value = '';
                            if (usuarioSeleccionado) usuarioSeleccionado.style.display = 'none';
                            filtrarGridPorUsuario('');
                            actualizarResumenHorasUsuario();
                        }
                        return;
                    }
                    const filtrados = usuarios.filter(u =>
                        u.apellido && u.apellido.toLowerCase().includes(termino)
                    );
                    if (filtrados.length === 0) {
                        resultados.innerHTML = '<div class="usuario-item">No se encontraron usuarios</div>';
                        resultados.style.display = 'block';
                        return;
                    }
                    resultados.innerHTML = filtrados.slice(0, 10).map(u =>
                        '<div class="usuario-item" data-id="' + u.id + '" data-nombre="' + (u.apellido || '') + '">' + (u.apellido || '') + '</div>'
                    ).join('');
                    resultados.style.display = 'block';
                }
                // Solo agregar listeners si los elementos existen
                // Ejecutar de forma independiente para asegurar que siempre funcione
                (function() {
                    try {
                        if (buscador && resultados && Array.isArray(usuarios) && usuarios.length > 0) {
                            buscador.addEventListener('input', ejecutarBusquedaUsuarios);
                            buscador.addEventListener('keyup', ejecutarBusquedaUsuarios);
                        } else {
                            console.warn('Buscador no inicializado:', {
                                buscador: !!buscador,
                                resultados: !!resultados,
                                usuarios: Array.isArray(usuarios) ? usuarios.length : 'no es array'
                            });
                        }
                    } catch (err) {
                        console.error('Error al inicializar buscador:', err);
                    }
                })();
    
                function filtrarGridPorUsuario(usuarioId) {
                    const filas = document.querySelectorAll('tr.fila-pdt');
                    const id = usuarioId ? String(usuarioId).trim() : '';
                    filas.forEach(function(tr) {
                        tr.style.display = (id === '' || tr.getAttribute('data-usuario-id') === id) ? '' : 'none';
                    });
                }

                function actualizarResumenHorasUsuario() {
                    if (!usuarioIdInput) return;
                    var id = usuarioIdInput.value;
                    var el = document.getElementById('resumenHorasUsuario');
                    var btnCC = document.getElementById('btnCargarCC');
                    if (!el) return;
                    if (btnCC) btnCC.disabled = !id;
                    if (!id) {
                        el.textContent = 'Seleccione un usuario para ver el resumen (horas con CC=NO)';
                        return;
                    }
                    var r = resumenHorasCCNo[id] || { horas_comunes: 0, horas_tractor: 0 };
                    var fmt = function(n) { return Number(n).toLocaleString('es-AR', { minimumFractionDigits: 0, maximumFractionDigits: 2 }); };
                    el.textContent = 'Horas Comunes (CC=NO): ' + fmt(r.horas_comunes) + ' | Horas tractor (CC=NO): ' + fmt(r.horas_tractor);
                }

                function seleccionarUsuarioDesdeItem(item) {
                    if (!item || !item.classList.contains('usuario-item')) return;
                    if (!usuarioIdInput || !buscador || !nombreUsuarioSel || !usuarioSeleccionado || !resultados) return;
                    const id = item.getAttribute('data-id');
                    const nombre = item.getAttribute('data-nombre');
                    if (!id) return;
                    usuarioIdInput.value = id;
                    buscador.value = nombre;
                    nombreUsuarioSel.textContent = nombre;
                    usuarioSeleccionado.style.display = 'block';
                    resultados.style.display = 'none';
                    filtrarGridPorUsuario(id);
                    actualizarResumenHorasUsuario();
                }
                if (resultados) {
                    resultados.addEventListener('click', function(e) {
                        if (e.target.classList.contains('usuario-item')) {
                            e.preventDefault();
                            seleccionarUsuarioDesdeItem(e.target);
                        }
                    });
                    resultados.addEventListener('touchend', function(e) {
                        var item = e.target.closest && e.target.closest('.usuario-item') || (e.target.classList && e.target.classList.contains('usuario-item') ? e.target : null);
                        if (item) {
                            e.preventDefault();
                            seleccionarUsuarioDesdeItem(item);
                        }
                    }, { passive: false });
                }

                // Botón Cargar en cuenta corriente: enviar POST a cargar_pdt_cc.php (no se puede anidar otro form)
                var btnCargarCC = document.getElementById('btnCargarCC');
                if (btnCargarCC && usuarioIdInput) {
                    btnCargarCC.addEventListener('click', function() {
                        var uid = usuarioIdInput.value;
                        if (!uid) return;
                        if (!confirm('¿Cargar en cuenta corriente las horas con CC=NO de este usuario? Se generarán los movimientos con fecha de hoy y comprobante Trabajo.')) return;
                        var f = document.createElement('form');
                        f.method = 'POST';
                        f.action = 'cargar_pdt_cc.php';
                        f.style.display = 'none';
                        var inp = document.createElement('input');
                        inp.type = 'hidden';
                        inp.name = 'usuario_id';
                        inp.value = uid;
                        f.appendChild(inp);
                        document.body.appendChild(f);
                        f.submit();
                    });
                }

                // Al cargar, si ya hay un usuario seleccionado (ej. edición o tras guardar), filtrar la grid y actualizar resumen
                if (usuarioIdInput && usuarioIdInput.value) {
                    filtrarGridPorUsuario(usuarioIdInput.value);
                    actualizarResumenHorasUsuario();
                }

                // Modal observaciones: clic en fila con observaciones (no en botones)
                var modalObs = document.getElementById('modalObservaciones');
                var modalObsTexto = document.getElementById('modalObservacionesTexto');
                var modalObsCerrar = document.getElementById('modalObservacionesCerrar');
                if (modalObs && modalObsTexto) {
                    document.addEventListener('click', function(e) {
                        var tbody = e.target.closest('.wrap-tabla-pdt tbody');
                        if (!tbody) return;
                            var tr = e.target.closest('tr.fila-con-observaciones');
                            if (!tr) return;
                            if (e.target.closest('form') || e.target.tagName === 'BUTTON' || e.target.tagName === 'INPUT') return;
                            var span = tr.querySelector('.obs-text-hidden');
                            if (!span) return;
                            modalObsTexto.textContent = span.textContent;
                            modalObs.classList.add('activo');
                        });
                    }
                    function cerrarModalObs() { if (modalObs) modalObs.classList.remove('activo'); }
                    if (modalObsCerrar) modalObsCerrar.addEventListener('click', cerrarModalObs);
                    if (modalObs) {
                        modalObs.addEventListener('click', function(e) { if (e.target === modalObs) cerrarModalObs(); });
                    }
                }

                // Ocultar resultados al hacer clic fuera
                document.addEventListener('click', function(e) {
                    if (buscador && resultados && !buscador.contains(e.target) && !resultados.contains(e.target)) {
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
                
                // Cargar valores guardados previamente (si no estamos editando)
                <?php if (!$pdt_edit): ?>
                if (tipoHoras && fechaInput) {
                    const valoresGuardados = JSON.parse(localStorage.getItem('pdt_ultimos_valores') || '{}');
                    if (valoresGuardados.tipo_horas && tipoHoras) {
                        tipoHoras.value = valoresGuardados.tipo_horas;
                    }
                    if (valoresGuardados.tractor && tipoHoras && tipoHoras.value === 'Horas tractos' && tractorSelect) {
                        tractorSelect.value = valoresGuardados.tractor;
                    }
                    if (valoresGuardados.fecha && fechaInput) {
                        fechaInput.value = valoresGuardados.fecha;
                    }
                }
                // Cant gasoil y cambio de aceite: siempre por defecto 0 y destildado (no restaurar desde localStorage)
                <?php endif; ?>
                
                // Guardar valores cuando cambian
                function guardarValores() {
                    if (!tipoHoras || !fechaInput || !cantGasoilInput || !cambioAceiteInput) return;
                    const valores = {
                        tipo_horas: tipoHoras.value,
                        tractor: tractorSelect ? tractorSelect.value : '',
                        fecha: fechaInput.value,
                        cant_gasoil: cantGasoilInput.value,
                        cambio_aceite: cambioAceiteInput.checked ? '1' : '0'
                    };
                    localStorage.setItem('pdt_ultimos_valores', JSON.stringify(valores));
                }
                
                // Función para manejar cambio de tipo de horas
                function manejarCambioTipoHoras() {
                    if (!tipoHoras || !tractorGroup || !tractorSelect || !gasoilGroup || !cambioAceiteGroup || !labelCantidad || !cantGasoilInput) return;
                    guardarValores();
                    if (tipoHoras.value === 'Horas tractos') {
                        tractorGroup.style.display = 'block';
                        tractorSelect.required = true;
                        gasoilGroup.style.display = 'block';
                        cambioAceiteGroup.style.display = 'block';
                        cantGasoilInput.required = true;
                        labelCantidad.textContent = 'Cantidad horas *';
                        // Cargar valores guardados si existen (solo al cambiar, no al cargar inicial)
                        <?php if (!$pdt_edit): ?>
                        const valoresGuardados = JSON.parse(localStorage.getItem('pdt_ultimos_valores') || '{}');
                        if (valoresGuardados.tractor && !tractorSelect.value) {
                            tractorSelect.value = valoresGuardados.tractor;
                        }
                        <?php endif; ?>
                        actualizarTractorDesdeCambioAceite();
                    } else {
                        tractorGroup.style.display = 'none';
                        tractorSelect.required = false;
                        gasoilGroup.style.display = 'none';
                        cambioAceiteGroup.style.display = 'none';
                        cantGasoilInput.required = false;
                        labelCantidad.textContent = 'Cantidad *';
                    }
                }
                
                if (tipoHoras) {
                    tipoHoras.addEventListener('change', manejarCambioTipoHoras);
                }
    
                
                // Guardar valores cuando cambian los campos
                function actualizarTractorDesdeCambioAceite() {
                    if (!tractorSelect || !tipoHoras) return;
                    var div = document.getElementById('tractorDesdeCambioAceite');
                    if (!div) return;
                    var tractor = tractorSelect.value;
                    if (!tractor || tipoHoras.value !== 'Horas tractos') {
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
                
                // Guardar valores al enviar el formulario
                var formPDT = document.getElementById('formPDT');
                if (formPDT) {
                    formPDT.addEventListener('submit', function() {
                        guardarValores();
                    });
                }
                
                // Navegación con Enter entre campos hasta el botón Guardar
                if (btnGuardar && buscador && tipoHoras) {
                    const camposOrden = [buscador, tipoHoras, tractorSelect, fechaInput, horasInput, cantGasoilInput, cambioAceiteInput, observacionesTextarea, btnGuardar].filter(c => c !== null);
                    
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
                                        setTimeout(() => { if (tipoHoras) tipoHoras.focus(); }, 100);
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
                if (resultados && tipoHoras) {
                    resultados.addEventListener('keydown', function(e) {
                        if (e.key === 'Enter' && e.target.classList.contains('usuario-item')) {
                            e.target.click();
                            setTimeout(() => { if (tipoHoras) tipoHoras.focus(); }, 100);
                        }
                    });
                }
                
                // Trigger inicial para cargar valores guardados y mostrar campos según tipo
                if (typeof manejarCambioTipoHoras === 'function') {
                    manejarCambioTipoHoras();
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
                if (enfocarTipoTrabajo && tipoHoras) {
                    tipoHoras.focus();
                }

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
                
                // Listener para click
                btnCargaGasoilSisterna.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    window.toggleCargaGasoilForm();
                });
                
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
<?php include 'nav_enter_form_inc.php'; ?>
</body>
</html>
