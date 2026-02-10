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
                $mensaje = 'Error al registrar carga: ' . mysqli_error($conexion);
            }
        } else {
            $mensaje = 'Error: fecha y cantidad (mayor a 0) son obligatorios para la carga.';
        }
    }
    if (isset($_POST['guardar'])) {
        $usuario_id = (int)($_POST['usuario_id'] ?? 0);
        if ($usuario_id < 1) {
            $mensaje = 'Error: debe seleccionar un usuario en Personal (busque y elija de la lista).';
        } else {
            // Comprobar que el usuario existe (evitar error de clave foránea)
            $check = mysqli_query($conexion, "SELECT id FROM usuarios WHERE id = $usuario_id LIMIT 1");
            if (!$check || mysqli_num_rows($check) === 0) {
                $mensaje = 'Error: el usuario seleccionado no existe en el sistema. Busque y elija otro en Personal.';
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
            $mensaje = 'Error: ' . $error_campo;
        } else {
        $fecha = mysqli_real_escape_string($conexion, $fecha);
        $horas = (int)$horas;
        
        // Verificar si las columnas existen, si no agregarlas
        $res_cols = mysqli_query($conexion, "SHOW COLUMNS FROM pdt LIKE 'cant_gasoil'");
        if (!$res_cols || mysqli_num_rows($res_cols) == 0) {
            mysqli_query($conexion, "ALTER TABLE pdt ADD COLUMN cant_gasoil DECIMAL(6,2) NULL AFTER horas");
        }
        $res_cols2 = mysqli_query($conexion, "SHOW COLUMNS FROM pdt LIKE 'cambio_aceite'");
        if (!$res_cols2 || mysqli_num_rows($res_cols2) == 0) {
            mysqli_query($conexion, "ALTER TABLE pdt ADD COLUMN cambio_aceite TINYINT(1) DEFAULT 0 AFTER cant_gasoil");
        }
        $res_cols3 = mysqli_query($conexion, "SHOW COLUMNS FROM pdt LIKE 'en_cc'");
        if (!$res_cols3 || mysqli_num_rows($res_cols3) == 0) {
            mysqli_query($conexion, "ALTER TABLE pdt ADD COLUMN en_cc TINYINT(1) DEFAULT 0 AFTER cambio_aceite");
        }
        
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
                $mensaje = 'Error al modificar: ' . mysqli_error($conexion);
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
                $mensaje = 'Error al guardar: ' . mysqli_error($conexion);
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
            $mensaje = 'Error al eliminar: ' . mysqli_error($conexion);
        }
    } elseif (isset($_POST['cargar_cc'])) {
        $pdt_id = (int)$_POST['pdt_id'];
        $sql = "UPDATE pdt SET en_cc = 1 WHERE id = $pdt_id";
        if (mysqli_query($conexion, $sql)) {
            $mensaje = 'PDT marcado como cargado en cuenta corriente.';
        } else {
            $mensaje = 'Error al marcar en CC: ' . mysqli_error($conexion);
        }
    } elseif (isset($_POST['editar'])) {
        $pdt_id = (int)$_POST['pdt_id'];
        $res = mysqli_query($conexion, "SELECT * FROM pdt WHERE id = $pdt_id LIMIT 1");
        if ($res && $row = mysqli_fetch_assoc($res)) {
            $pdt_edit = $row;
        }
    }
}

// Obtener lista de PDTs (desde_cel: solo 3 últimos con CC=NO)
if (!empty($desde_cel)) {
    $sql_lista = "SELECT p.*, u.apellido AS usuario_nombre FROM pdt p INNER JOIN usuarios u ON u.id = p.usuario_id WHERE COALESCE(p.en_cc, 0) = 0 ORDER BY p.fecha DESC, p.id DESC LIMIT 3";
} else {
    $sql_lista = "SELECT p.*, u.apellido AS usuario_nombre FROM pdt p INNER JOIN usuarios u ON u.id = p.usuario_id ORDER BY p.fecha DESC, p.id DESC LIMIT 200";
}
$res_lista = mysqli_query($conexion, $sql_lista);

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
        .container { max-width: 1400px; margin: 0 auto; background: white; padding: 15px; border-radius: 6px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); box-sizing: border-box; overflow-x: auto; }
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
        .tabla-listado-pdt { table-layout: fixed; }
        .tabla-listado-pdt th, .tabla-listado-pdt td { white-space: nowrap; overflow: hidden; text-overflow: ellipsis; text-align: left; }
        .tabla-listado-pdt td.col-acciones, .tabla-listado-pdt th.col-acciones { overflow: visible; }
        .tabla-listado-pdt th.col-acciones { background: #007bff; }
        .tabla-listado-pdt td.col-acciones { background: #fff; }
        .tabla-listado-pdt tr:hover td.col-acciones { background: #f8f9fa; }
        .tabla-listado-pdt tr.fila-con-observaciones td { background: #ffebee; color: #b71c1c; }
        .tabla-listado-pdt tr.fila-con-observaciones:hover td { background: #ffcdd2; }
        .tabla-listado-pdt tr.fila-con-observaciones td.col-acciones { background: #ffebee; }
        .tabla-listado-pdt tr.fila-con-observaciones:hover td.col-acciones { background: #ffcdd2; }
        .wrap-tabla-pdt { width: 100%; max-width: 100%; overflow: visible; }
        .tabla-listado-pdt tr.fila-con-observaciones { cursor: pointer; }
        #modalObservaciones { display: none; position: fixed; z-index: 9999; left: 0; top: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.4); align-items: center; justify-content: center; }
        #modalObservaciones.activo { display: flex; }
        #modalObservaciones .modal-caja { background: #fff; border-radius: 8px; padding: 20px; max-width: 90%; max-height: 80vh; overflow: auto; box-shadow: 0 4px 20px rgba(0,0,0,0.2); }
        #modalObservaciones .modal-caja h4 { margin: 0 0 12px 0; color: #333; font-size: 16px; }
        #modalObservaciones .modal-caja .obs-contenido { white-space: pre-wrap; color: #333; margin-bottom: 16px; }
        #modalObservaciones .modal-caja .btn { cursor: pointer; }
        .tabla-listado-pdt { width: max-content; min-width: 100%; }
        .tabla-listado-pdt th.col-id, .tabla-listado-pdt td.col-id { width: 38px; max-width: 38px; }
        .tabla-listado-pdt th.col-personal, .tabla-listado-pdt td.col-personal { width: 90px; }
        .tabla-listado-pdt th.col-tipo, .tabla-listado-pdt td.col-tipo { width: 55px; }
        .tabla-listado-pdt th.col-tractor, .tabla-listado-pdt td.col-tractor { width: 75px; }
        .tabla-listado-pdt th.col-fecha, .tabla-listado-pdt td.col-fecha { width: 52px; }
        .tabla-listado-pdt th.col-cantidad, .tabla-listado-pdt td.col-cantidad { width: 42px; }
        .tabla-listado-pdt th.col-gasoil, .tabla-listado-pdt td.col-gasoil { width: 42px; }
        .tabla-listado-pdt th.col-cambio, .tabla-listado-pdt td.col-cambio { width: 38px; text-align: center; }
        .tabla-listado-pdt th.col-cc, .tabla-listado-pdt td.col-cc { width: 28px; text-align: center; }
        .tabla-listado-pdt th.col-acciones, .tabla-listado-pdt td.col-acciones { width: 1%; min-width: 200px; white-space: nowrap; }
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
        <?php if ($desde_cel): ?>
        /* Responsive Partes desde cel */
        .vista-partes-cel .container { max-width: 100%; padding: 10px; box-sizing: border-box; }
        .vista-partes-cel .form-row { flex-direction: column; }
        .vista-partes-cel .form-row .form-group { width: 100%; max-width: 100%; }
        .vista-partes-cel .buscador-usuario-container { width: 100% !important; }
        .vista-partes-cel .form-group input, .vista-partes-cel .form-group select, .vista-partes-cel .form-group textarea { font-size: 16px; min-height: 44px; }
        .vista-partes-cel .btn { padding: 10px 16px; min-height: 44px; font-size: 14px; touch-action: manipulation; -webkit-tap-highlight-color: rgba(0,0,0,0.1); cursor: pointer; }
        .vista-partes-cel #btnCargaGasoilSisterna { min-height: 48px; padding: 12px 20px; font-size: 14px; display: block; width: 100%; max-width: 320px; margin: 0 auto; box-sizing: border-box; position: relative; z-index: 5; }
        .vista-partes-cel .wrap-tabla-pdt { overflow-x: auto; -webkit-overflow-scrolling: touch; }
        .vista-partes-cel .tabla-listado-pdt { display: table; width: 100%; }
        .vista-partes-cel .tabla-listado-pdt thead, .vista-partes-cel .tabla-listado-pdt tbody, .vista-partes-cel .tabla-listado-pdt tr { display: table-row; }
        .vista-partes-cel .tabla-listado-pdt th, .vista-partes-cel .tabla-listado-pdt td { display: table-cell; white-space: nowrap; padding: 8px 6px; font-size: 12px; }
        .vista-partes-cel h2 { font-size: 1.2rem; }
        .vista-partes-cel h3 { margin-top: 20px; margin-bottom: 10px; font-size: 1rem; }
        .vista-partes-cel .wrap-tabla-pdt { margin-bottom: 20px; }
        /* Botones Guardar, Cancelar y Salir: mismo tamaño y estilo en Partes desde cel */
        .vista-partes-cel .botones-form-partes .btn,
        .vista-partes-cel .botones-form-partes a.btn {
            padding: 12px 20px;
            min-height: 48px;
            font-size: 14px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            text-decoration: none;
            border: 1px solid transparent;
            border-radius: 4px;
            box-sizing: border-box;
        }
        /* Buscador y dropdown en cel: por encima de todo y área táctil */
        .vista-partes-cel .buscador-usuario { position: relative; z-index: 100; }
        .vista-partes-cel #resultadosUsuario { z-index: 9998; max-height: 220px; -webkit-overflow-scrolling: touch; }
        .vista-partes-cel #resultadosUsuario .usuario-item { min-height: 44px; display: flex; align-items: center; padding: 10px 8px; cursor: pointer; -webkit-tap-highlight-color: rgba(0,0,0,0.08); }
        /* Bloque gasoil en cel: asegurar que el formulario se muestre encima */
        .vista-partes-cel #formCargaGasoilSisterna { position: relative; z-index: 50; }
        @media (max-width: 480px) {
            .vista-partes-cel .form-row { gap: 12px; }
        }
        <?php endif; ?>
    </style>
</head>
<body<?= $desde_cel ? ' class="vista-partes-cel"' : '' ?>>
    <div class="container">
        <div style="display: flex; align-items: flex-start; justify-content: space-between; gap: 20px; flex-wrap: wrap; margin-bottom: 15px;">
            <h2 style="margin: 0;"><?= htmlspecialchars($titulo_pagina) ?></h2>
            <div style="text-align: right; flex-shrink: 0;">
                <div style="margin-bottom: 6px;">
                    <button type="button" id="btnCargaGasoilSisterna" class="btn btn-secondary" style="font-size: 12px;">Carga gasoil en cisterna</button>
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
            elseif ($_GET['cc'] === 'error') $mensaje = 'Error al cargar en CC: ' . (isset($_GET['msg']) ? $_GET['msg'] : 'consulte con el administrador.');
        }
        ?>
        <?php if ($mensaje && $mensaje !== 'Parte guardado.'): ?>
            <div class="mensaje <?= strpos($mensaje, 'Error') !== false ? 'error' : 'ok' ?>">
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
        <form method="POST" id="formPDT" action="<?= htmlspecialchars($form_action_url) ?>">
            <?php if ($pdt_edit): ?>
                <input type="hidden" name="pdt_id" value="<?= $pdt_edit['id'] ?>">
            <?php endif; ?>
            
            <div style="display: flex; align-items: flex-start; gap: 14px; flex-wrap: wrap; margin-bottom: 11px;">
                <div class="form-group buscador-usuario-container">
                    <label>Personal *</label>
                    <div class="buscador-usuario">
                        <input type="text" id="buscadorUsuario" placeholder="Buscar..." autocomplete="off" value="<?= htmlspecialchars($nombre_personal_mostrar) ?>">
                        <input type="hidden" name="usuario_id" id="usuario_id" value="<?= $pdt_edit ? $pdt_edit['usuario_id'] : ($preseleccionar_usuario_id ?? '') ?>" required>
                        <div id="resultadosUsuario"></div>
                        <div id="usuarioSeleccionado" style="margin-top: 5px; padding: 3px 5px; background: #e7f3ff; border-radius: 4px; font-size: 11px; <?= ($nombre_personal_mostrar !== '') ? '' : 'display: none;' ?>">
                            <strong>Sel:</strong> <span id="nombreUsuarioSel"><?= htmlspecialchars($nombre_personal_mostrar) ?></span>
                        </div>
                    </div>
                </div>
                <div style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap; padding-top: 20px;">
                    <?php if (!$desde_cel): ?>
                    <a href="gestionar_tabla_salarial.php" class="btn btn-secondary" style="font-size: 11px; padding: 5px 10px;">ABM Tabla salarial</a>
                    <span id="valoresSalarialesFinca" style="font-size: 11px; color: #333; padding: 4px 8px; background: #e8f4e8; border-radius: 4px; border: 1px solid #c8e6c9;">
                        Hora común: $ <?= number_format($ultima_tabla_salarial['valor_hora_comun'], 2, ',', '.') ?> &nbsp;|&nbsp; Hora tractor: $ <?= number_format($ultima_tabla_salarial['valor_hora_tractor'], 2, ',', '.') ?>
                    </span>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label>Tipo de trabajo *</label>
                    <select name="tipo_horas" id="tipo_horas" required>
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
                    <select name="tractor" id="tractor">
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
                
                <div class="form-group">
                    <label>Fecha *</label>
                    <input type="date" name="fecha" id="fecha" value="<?= $pdt_edit ? $pdt_edit['fecha'] : date('Y-m-d') ?>" required>
                </div>
                
                <div class="form-group" id="cantidadHorasGroup">
                    <label id="labelCantidad">Cantidad *</label>
                    <input type="number" name="horas" id="horas" step="1" min="0" value="<?= $pdt_edit ? (int)$pdt_edit['horas'] : '0' ?>" required>
                </div>
                
                <div class="form-group" id="gasoilGroup" style="display: none;">
                    <label>Cant Gasoil *</label>
                    <input type="number" name="cant_gasoil" id="cant_gasoil" step="0.01" min="0" value="<?= $pdt_edit ? ($pdt_edit['cant_gasoil'] ?? '0') : '0' ?>">
                </div>
                
                <div class="form-group checkbox-group" id="cambioAceiteGroup" style="display: none;">
                    <label class="checkbox-label">
                        <input type="checkbox" name="cambio_aceite" id="cambio_aceite" value="1" <?= ($pdt_edit && isset($pdt_edit['cambio_aceite']) && $pdt_edit['cambio_aceite'] == 1) ? 'checked' : '' ?>>
                        Cambio de aceite
                    </label>
                </div>
            </div>
            
            <div class="form-group">
                <label>Observaciones</label>
                <textarea name="observaciones" rows="1" style="resize: vertical; min-height: 20px;"><?= htmlspecialchars($pdt_edit['observaciones'] ?? '') ?></textarea>
            </div>
            
            <div class="<?= $desde_cel ? 'botones-form-partes' : '' ?>" style="display: flex; align-items: center; gap: 12px; flex-wrap: wrap;">
                <button type="submit" name="guardar" id="btnGuardar" class="btn btn-primary">Guardar</button>
                <?php if ($desde_cel): ?>
                <a href="<?= htmlspecialchars($form_action_url) ?>" class="btn btn-secondary">Cancelar</a>
                <a href="<?= $es_nivel_0 ? 'logout.php' : 'gestionar_finca.php' ?>" class="btn btn-secondary">Salir</a>
                <?php endif; ?>
                <?php if ($mensaje === 'Parte guardado.'): ?>
                    <div id="cartelMensaje" class="cartel-parte-guardado parte-guardado">Parte guardado.</div>
                <?php endif; ?>
                <?php if (!$desde_cel): ?>
                <span id="resumenHorasUsuario" style="font-size: 11px; color: #555; padding: 4px 8px; background: #f0f0f0; border-radius: 4px; min-height: 24px; display: inline-flex; align-items: center;">Seleccione un usuario para ver el resumen (horas con CC=NO)</span>
                <?php endif; ?>
                <?php if ($pdt_edit && !$desde_cel): ?>
                    <a href="<?= htmlspecialchars($form_action_url) ?>" class="btn btn-secondary">Cancelar</a>
                <?php endif; ?>
                <?php if (!$desde_cel): ?>
                <button type="button" id="btnCargarCC" class="btn btn-success" style="font-size: 11px; padding: 5px 10px;" disabled title="Seleccione un usuario">Cargar en cuenta corriente</button>
                <?php endif; ?>
            </div>
        </form>
        
        <h3 style="margin-top: 30px;"><?= $desde_cel ? 'Últimos 3 partes (sin cargar en CC)' : 'Listado de PDTs' ?></h3>
        <div class="wrap-tabla-pdt">
        <table class="tabla-listado-pdt">
            <thead>
                <tr>
                    <th class="col-id">ID</th>
                    <th class="col-personal">Personal</th>
                    <th class="col-tipo">Tipo</th>
                    <th class="col-tractor">Tractor</th>
                    <th class="col-fecha">Fecha</th>
                    <th class="col-cantidad">Cant.</th>
                    <th class="col-gasoil">Gasoil</th>
                    <th class="col-cambio">C.aceite</th>
                    <th class="col-cc">CC</th>
                    <th class="col-acciones">Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($res_lista && mysqli_num_rows($res_lista) > 0): ?>
                    <?php while ($pdt = mysqli_fetch_assoc($res_lista)): ?>
                        <?php $tiene_obs = !empty(trim($pdt['observaciones'] ?? '')); ?>
<tr class="fila-pdt<?= $tiene_obs ? ' fila-con-observaciones' : '' ?>" data-usuario-id="<?= (int)$pdt['usuario_id'] ?>"<?= $tiene_obs ? ' title="Clic para ver observaciones"' : '' ?>>
                                                            <td class="col-id" title="<?= (int)$pdt['id'] ?>"><?php $id = (string)$pdt['id']; echo strlen($id) > 6 ? substr($id, 0, 6) . '…' : $id; ?><?php if ($tiene_obs): ?><span class="obs-text-hidden" style="display:none"><?= htmlspecialchars(trim($pdt['observaciones'])) ?></span><?php endif; ?></td>
                            <td class="col-personal"><?= htmlspecialchars($pdt['usuario_nombre']) ?></td>
                            <td class="col-tipo" title="<?= htmlspecialchars($pdt['tipo_horas']) ?>"><?php $t = htmlspecialchars($pdt['tipo_horas']); echo mb_strlen($t) > 20 ? mb_substr($t, 0, 20) . '…' : $t; ?></td>
                            <td class="col-tractor"><?= htmlspecialchars($pdt['tractor'] ?? '-') ?></td>
                            <td class="col-fecha" title="<?= date('d/m/Y', strtotime($pdt['fecha'])) ?>"><?php $f = date('d/m/Y', strtotime($pdt['fecha'])); echo mb_strlen($f) > 20 ? mb_substr($f, 0, 20) . '…' : $f; ?></td>
                            <td class="col-cantidad"><?= number_format($pdt['horas'], 2, ',', '.') ?></td>
                            <td class="col-gasoil"><?= isset($pdt['cant_gasoil']) && $pdt['cant_gasoil'] !== null ? number_format($pdt['cant_gasoil'], 2, ',', '.') : '-' ?></td>
                            <td class="col-cambio"><?= (isset($pdt['cambio_aceite']) && $pdt['cambio_aceite'] == 1) ? '✓' : '-' ?></td>
                            <td class="col-cc" style="font-weight: bold; <?= (isset($pdt['en_cc']) && $pdt['en_cc'] == 1) ? 'color: #28a745;' : 'color: #dc3545;' ?>">
                                <?= (isset($pdt['en_cc']) && $pdt['en_cc'] == 1) ? 'SI' : 'NO' ?>
                            </td>
                            <td class="col-acciones">
                                <div class="acciones-botones">
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="pdt_id" value="<?= $pdt['id'] ?>">
                                        <button type="submit" name="editar" class="btn btn-secondary">Modificar</button>
                                    </form>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="pdt_id" value="<?= $pdt['id'] ?>">
                                        <button type="submit" name="eliminar" class="btn btn-danger" onclick="return confirm('¿Eliminar este PDT?')">Eliminar</button>
                                    </form>
                                    <?php if (!$desde_cel && (!isset($pdt['en_cc']) || $pdt['en_cc'] == 0)): ?>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="pdt_id" value="<?= $pdt['id'] ?>">
                                        <button type="submit" name="cargar_cc" class="btn btn-success">Cargar en CC</button>
                                    </form>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endwhile; ?>
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
                    <a href="gestionar_finca.php" class="btn btn-secondary">← Volver a Gestión Finca</a>
                <?php endif; ?>
            <?php else: ?>
                <a href="index.php" class="btn btn-secondary">← Volver al panel</a>
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
    const usuarios = <?= json_encode($usuarios) ?>;
    const resumenHorasCCNo = <?= json_encode($resumen_horas_cc_no) ?>;
    const tractorDesdeCambio = <?= json_encode($tractor_desde_cambio) ?>;
    const buscador = document.getElementById('buscadorUsuario');
    const resultados = document.getElementById('resultadosUsuario');
    const usuarioIdInput = document.getElementById('usuario_id');
    const usuarioSeleccionado = document.getElementById('usuarioSeleccionado');
    const nombreUsuarioSel = document.getElementById('nombreUsuarioSel');
    const tipoHoras = document.getElementById('tipo_horas');
    const tractorGroup = document.getElementById('tractorGroup');
    const tractorSelect = document.getElementById('tractor');
    
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            e.preventDefault();
            <?php if ($desde_cel): ?>
            // Partes desde cel: volver al formulario que lo llamó (página anterior)
            if (window.history.length > 1) {
                window.history.back();
            } else {
                window.location.href = <?= $es_nivel_0 ? "'logout.php'" : "'gestionar_finca.php'" ?>;
            }
            <?php else: ?>
            window.location.href = 'index.php';
            <?php endif; ?>
        }
    });
    
    <?php if ($pdt_edit): ?>
        // Si estamos editando, mostrar el usuario seleccionado
        const usuarioEdit = usuarios.find(u => u.id == <?= $pdt_edit['usuario_id'] ?>);
        if (usuarioEdit) {
            buscador.value = usuarioEdit.apellido;
            nombreUsuarioSel.textContent = usuarioEdit.apellido;
            usuarioSeleccionado.style.display = 'block';
        }
        // Mostrar grupo de tractor y campos adicionales si corresponde
        var gasoilGroupEdit = document.getElementById('gasoilGroup');
        var cambioAceiteGroupEdit = document.getElementById('cambioAceiteGroup');
        var labelCantidadEdit = document.getElementById('labelCantidad');
        var cantGasoilInputEdit = document.getElementById('cant_gasoil');
        if ('<?= $pdt_edit['tipo_horas'] ?>' === 'Horas tractos') {
            tractorGroup.style.display = 'block';
            tractorSelect.required = true;
            if (gasoilGroupEdit) gasoilGroupEdit.style.display = 'block';
            if (cambioAceiteGroupEdit) cambioAceiteGroupEdit.style.display = 'block';
            if (labelCantidadEdit) labelCantidadEdit.textContent = 'Cantidad horas *';
            if (cantGasoilInputEdit) cantGasoilInputEdit.required = true;
        } else {
            tractorGroup.style.display = 'none';
            if (gasoilGroupEdit) gasoilGroupEdit.style.display = 'none';
            if (cambioAceiteGroupEdit) cambioAceiteGroupEdit.style.display = 'none';
            if (cantGasoilInputEdit) cantGasoilInputEdit.required = false;
        }
    <?php endif; ?>
    
    // Buscador de usuarios (mínimo 1 carácter en vista cel para mejor uso en móvil)
    var esVistaCel = document.body.classList.contains('vista-partes-cel');
    var minCaracteresBusqueda = esVistaCel ? 1 : 2;
    function ejecutarBusquedaUsuarios() {
        const termino = buscador.value.toLowerCase().trim();
        if (termino.length < minCaracteresBusqueda) {
            resultados.style.display = 'none';
            if (termino === '') {
                usuarioIdInput.value = '';
                usuarioSeleccionado.style.display = 'none';
                filtrarGridPorUsuario('');
                actualizarResumenHorasUsuario();
            }
            return;
        }
        const filtrados = usuarios.filter(u =>
            u.apellido.toLowerCase().includes(termino)
        );
        if (filtrados.length === 0) {
            resultados.innerHTML = '<div class="usuario-item">No se encontraron usuarios</div>';
            resultados.style.display = 'block';
            return;
        }
        resultados.innerHTML = filtrados.slice(0, 10).map(u =>
            '<div class="usuario-item" data-id="' + u.id + '" data-nombre="' + u.apellido + '">' + u.apellido + '</div>'
        ).join('');
        resultados.style.display = 'block';
    }
    buscador.addEventListener('input', ejecutarBusquedaUsuarios);
    buscador.addEventListener('keyup', function() { if (esVistaCel) ejecutarBusquedaUsuarios(); });
    
    function filtrarGridPorUsuario(usuarioId) {
        const filas = document.querySelectorAll('tr.fila-pdt');
        const id = usuarioId ? String(usuarioId).trim() : '';
        filas.forEach(function(tr) {
            tr.style.display = (id === '' || tr.getAttribute('data-usuario-id') === id) ? '' : 'none';
        });
    }

    function actualizarResumenHorasUsuario() {
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

    // Botón Cargar en cuenta corriente: enviar POST a cargar_pdt_cc.php (no se puede anidar otro form)
    var btnCargarCC = document.getElementById('btnCargarCC');
    if (btnCargarCC) {
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
    if (usuarioIdInput.value) {
        filtrarGridPorUsuario(usuarioIdInput.value);
        actualizarResumenHorasUsuario();
    }
    var enfocarTipoTrabajo = <?= (!empty($preseleccionar_usuario_id) && !$pdt_edit) ? 'true' : 'false' ?>;

    // Modal observaciones: clic en fila con observaciones (no en botones)
    var modalObs = document.getElementById('modalObservaciones');
    var modalObsTexto = document.getElementById('modalObservacionesTexto');
    var modalObsCerrar = document.getElementById('modalObservacionesCerrar');
    if (modalObs && modalObsTexto) {
        document.querySelector('.wrap-tabla-pdt tbody') && document.querySelector('.wrap-tabla-pdt tbody').addEventListener('click', function(e) {
            var tr = e.target.closest('tr.fila-con-observaciones');
            if (!tr) return;
            if (e.target.closest('form') || e.target.tagName === 'BUTTON' || e.target.tagName === 'INPUT') return;
            var span = tr.querySelector('.obs-text-hidden');
            if (!span) return;
            modalObsTexto.textContent = span.textContent;
            modalObs.classList.add('activo');
        });
        function cerrarModalObs() { if (modalObs) modalObs.classList.remove('activo'); }
        if (modalObsCerrar) modalObsCerrar.addEventListener('click', cerrarModalObs);
        modalObs && modalObs.addEventListener('click', function(e) { if (e.target === modalObs) cerrarModalObs(); });
    }

    // Ocultar resultados al hacer clic fuera
    document.addEventListener('click', function(e) {
        if (!buscador.contains(e.target) && !resultados.contains(e.target)) {
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
    const valoresGuardados = JSON.parse(localStorage.getItem('pdt_ultimos_valores') || '{}');
    if (valoresGuardados.tipo_horas) {
        tipoHoras.value = valoresGuardados.tipo_horas;
    }
    if (valoresGuardados.tractor && tipoHoras.value === 'Horas tractos') {
        tractorSelect.value = valoresGuardados.tractor;
    }
    if (valoresGuardados.fecha) {
        fechaInput.value = valoresGuardados.fecha;
    }
    // Cant gasoil y cambio de aceite: siempre por defecto 0 y destildado (no restaurar desde localStorage)
    <?php endif; ?>
    
    // Guardar valores cuando cambian
    function guardarValores() {
        const valores = {
            tipo_horas: tipoHoras.value,
            tractor: tractorSelect.value,
            fecha: fechaInput.value,
            cant_gasoil: cantGasoilInput.value,
            cambio_aceite: cambioAceiteInput.checked ? '1' : '0'
        };
        localStorage.setItem('pdt_ultimos_valores', JSON.stringify(valores));
    }
    
    // Función para manejar cambio de tipo de horas
    function manejarCambioTipoHoras() {
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
    
    tipoHoras.addEventListener('change', manejarCambioTipoHoras);
    
    // Toggle formulario carga gasoil en cisterna (touch + click para móvil y desktop)
    const formCargaGasoilSisterna = document.getElementById('formCargaGasoilSisterna');
    window.toggleCargaGasoilForm = function() {
        if (formCargaGasoilSisterna) formCargaGasoilSisterna.style.display = formCargaGasoilSisterna.style.display === 'none' ? 'block' : 'none';
    };
    var btnCargaGasoilSisterna = document.getElementById('btnCargaGasoilSisterna');
    if (btnCargaGasoilSisterna && formCargaGasoilSisterna) {
        var ultimoTouchGasoil = 0;
        btnCargaGasoilSisterna.addEventListener('touchend', function(e) {
            e.preventDefault();
            e.stopPropagation();
            ultimoTouchGasoil = Date.now();
            toggleCargaGasoilForm();
        }, { passive: false });
        btnCargaGasoilSisterna.addEventListener('click', function(e) {
            e.preventDefault();
            if (Date.now() - ultimoTouchGasoil < 400) return;
            toggleCargaGasoilForm();
        });
    }
    
    // Guardar valores cuando cambian los campos
    function actualizarTractorDesdeCambioAceite() {
        var div = document.getElementById('tractorDesdeCambioAceite');
        if (!div) return;
        var tractor = tractorSelect.value;
        if (!tractor || tipoHoras.value !== 'Horas tractos') {
            div.style.display = 'none';
            return;
        }
        var datos = tractorDesdeCambio[tractor];
        if (datos) {
            document.getElementById('tractorHorasDesdeCambio').textContent = Number(datos.horas).toLocaleString('es-AR', { minimumFractionDigits: 0, maximumFractionDigits: 1 });
            document.getElementById('tractorGasoilDesdeCambio').textContent = Number(datos.gasoil).toLocaleString('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            div.style.display = 'block';
        } else {
            div.style.display = 'none';
        }
    }
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
    fechaInput.addEventListener('change', guardarValores);
    horasInput.addEventListener('blur', guardarValores);
    horasInput.addEventListener('focus', function() { this.select(); });
    cantGasoilInput.addEventListener('blur', guardarValores);
    cambioAceiteInput.addEventListener('change', guardarValores);
    
    // Guardar valores al enviar el formulario
    document.getElementById('formPDT').addEventListener('submit', function() {
        guardarValores();
    });
    
    // Navegación con Enter entre campos hasta el botón Guardar
    const btnGuardar = document.getElementById('btnGuardar');
    const camposOrden = [buscador, tipoHoras, tractorSelect, fechaInput, horasInput, cantGasoilInput, cambioAceiteInput, observacionesTextarea, btnGuardar];
    
    camposOrden.forEach((campo, index) => {
        if (!campo) return;
        campo.addEventListener('keydown', function(e) {
            if (e.key === 'Enter') {
                // En el botón Guardar, no interceptar: que Enter envíe el formulario
                if (campo === btnGuardar) return;
                e.preventDefault();
                // Si es el buscador y hay resultados, seleccionar el primero
                if (campo === buscador && resultados.style.display === 'block' && resultados.children.length > 0) {
                    const primerItem = resultados.querySelector('.usuario-item');
                    if (primerItem) {
                        primerItem.click();
                        setTimeout(() => tipoHoras.focus(), 100);
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
    
    // También permitir Enter en resultados del buscador
    resultados.addEventListener('keydown', function(e) {
        if (e.key === 'Enter' && e.target.classList.contains('usuario-item')) {
            e.target.click();
            setTimeout(() => tipoHoras.focus(), 100);
        }
    });
    
    // Trigger inicial para cargar valores guardados y mostrar campos según tipo
    manejarCambioTipoHoras();
    // Aplicar color del tractor seleccionado al cargar
    (function() {
        var opt = tractorSelect.options[tractorSelect.selectedIndex];
        if (opt) {
            tractorSelect.classList.remove('tractor-jd', 'tractor-nh', 'tractor-mf');
            if (opt.classList.contains('tractor-jd')) tractorSelect.classList.add('tractor-jd');
            else if (opt.classList.contains('tractor-nh')) tractorSelect.classList.add('tractor-nh');
            else if (opt.classList.contains('tractor-mf')) tractorSelect.classList.add('tractor-mf');
        }
    })();

    if (enfocarTipoTrabajo) {
        tipoHoras.focus();
    }

    // Cartel "Parte guardado": mostrarlo 1 segundo y luego ocultarlo
    (function() {
        var cartel = document.getElementById('cartelMensaje');
        if (cartel && cartel.classList.contains('parte-guardado')) {
            setTimeout(function() {
                cartel.style.transition = 'opacity 0.3s';
                cartel.style.opacity = '0';
                setTimeout(function() { cartel.remove(); }, 300);
            }, 1000);
        }
    })();
    </script>
</body>
</html>
