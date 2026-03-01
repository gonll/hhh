<?php
include 'db.php';
include 'verificar_sesion.php';

if (isset($_SESSION['acceso_nivel']) && $_SESSION['acceso_nivel'] < 2) {
    header('Location: index.php?msg=sin_permiso');
    exit;
}

// Crear tabla stock si no existe (compatible con la grilla; vendida_a_id y facturada_a_id = usuarios.id)
$res_check = mysqli_query($conexion, "SHOW TABLES LIKE 'stock'");
if (!$res_check || mysqli_num_rows($res_check) == 0) {
    $sql_create = "CREATE TABLE IF NOT EXISTS stock (
        id INT AUTO_INCREMENT PRIMARY KEY,
        fecha DATE NOT NULL,
        linea TINYINT NOT NULL DEFAULT 1,
        articulo VARCHAR(255) NOT NULL DEFAULT '',
        orden INT NOT NULL DEFAULT 0,
        cantidad INT NOT NULL DEFAULT 0,
        deposito VARCHAR(255) NULL,
        fecha_vta DATE NULL,
        cant_vta INT NULL DEFAULT 0,
        vendida_a_id INT NULL,
        operador_id INT NULL,
        precio_vta DECIMAL(12,2) NULL DEFAULT 0,
        fecha_fact DATE NULL,
        cant_fact INT NULL DEFAULT 0,
        facturada_a_id INT NULL,
        precio_fac DECIMAL(12,2) NULL DEFAULT 0,
        n_fact VARCHAR(50) NULL,
        n_remt VARCHAR(50) NULL,
        operacion INT NULL,
        venta_movimiento_id INT NULL,
        fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_fecha (fecha),
        INDEX idx_orden (orden),
        INDEX idx_operacion (operacion)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci";
    mysqli_query($conexion, $sql_create);
}
// Añadir columnas operacion y venta_movimiento_id si no existen (tabla ya creada)
$res_col = @mysqli_query($conexion, "SHOW COLUMNS FROM stock LIKE 'operacion'");
if ($res_col && mysqli_num_rows($res_col) == 0) {
    mysqli_query($conexion, "ALTER TABLE stock ADD operacion INT NULL AFTER n_remt");
}
$res_col2 = @mysqli_query($conexion, "SHOW COLUMNS FROM stock LIKE 'venta_movimiento_id'");
if ($res_col2 && mysqli_num_rows($res_col2) == 0) {
    mysqli_query($conexion, "ALTER TABLE stock ADD venta_movimiento_id INT NULL AFTER operacion");
}
$res_col3 = @mysqli_query($conexion, "SHOW COLUMNS FROM stock LIKE 'operador_id'");
if ($res_col3 && mysqli_num_rows($res_col3) == 0) {
    mysqli_query($conexion, "ALTER TABLE stock ADD operador_id INT NULL AFTER vendida_a_id");
}
// Permitir operación duplicada (varias órdenes en la misma operación): quitar UNIQUE si existe
$r_idx = @mysqli_query($conexion, "SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'stock' AND COLUMN_NAME = 'operacion' AND NON_UNIQUE = 0 LIMIT 1");
if ($r_idx && mysqli_num_rows($r_idx) > 0) {
    @mysqli_query($conexion, "ALTER TABLE stock DROP INDEX idx_operacion");
    @mysqli_query($conexion, "ALTER TABLE stock ADD INDEX idx_operacion (operacion)");
}

// Cargar stock con apellido de Vendida a, Operador y Facturada a (por id de usuario)
$sql_stock = "SELECT s.*,
    v.apellido AS vendida_a_apellido,
    op.apellido AS operador_apellido,
    f.apellido AS facturada_a_apellido
FROM stock s
LEFT JOIN usuarios v ON v.id = s.vendida_a_id
LEFT JOIN usuarios op ON op.id = s.operador_id
LEFT JOIN usuarios f ON f.id = s.facturada_a_id
ORDER BY s.orden ASC, s.fecha DESC, s.id DESC";
$res_stock = mysqli_query($conexion, $sql_stock);
$filas_stock = [];
if ($res_stock) {
    while ($f = mysqli_fetch_assoc($res_stock)) {
        $filas_stock[] = $f;
    }
}

$res_faltan = mysqli_query($conexion, "SELECT COUNT(*) AS n, COALESCE(SUM(cantidad), 0) AS total FROM stock WHERE vendida_a_id IS NULL");
$faltan_vender = 0;
$faltan_vender_cantidad = 0;
if ($res_faltan && $r = mysqli_fetch_assoc($res_faltan)) {
    $faltan_vender = (int)$r['n'];
    $faltan_vender_cantidad = (float)$r['total'];
}

$mensaje_stock = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['eliminar_venta_azucar'])) {
        $stock_id = (int)($_POST['id'] ?? 0);
        if ($stock_id > 0) {
            $r = mysqli_fetch_assoc(mysqli_query($conexion, "SELECT venta_movimiento_id, vendida_a_id, orden, operacion, linea FROM stock WHERE id = $stock_id LIMIT 1"));
            $mov_id = (int)($r['venta_movimiento_id'] ?? 0);
            if ($mov_id <= 0 && !empty($r['vendida_a_id']) && !empty($r['operacion'])) {
                $mov_id = (int)buscarMovimientoVentaPorOrdenOperacion($conexion, (int)$r['vendida_a_id'], (int)$r['orden'], (int)$r['operacion'], (int)($r['linea'] ?? 1));
            }
            if ($mov_id > 0) {
                $ref_elim_esc = mysqli_real_escape_string($conexion, 'Vta eliminada');
                mysqli_query($conexion, "UPDATE cuentas SET referencia = '$ref_elim_esc', monto = 0 WHERE movimiento_id = $mov_id");
            }
            mysqli_query($conexion, "UPDATE stock SET fecha_vta = NULL, cant_vta = 0, vendida_a_id = NULL, operador_id = NULL, precio_vta = NULL, operacion = NULL, venta_movimiento_id = NULL WHERE id = $stock_id");
            header('Location: gestionar_azucares.php?venta=elim');
            exit;
        }
    } elseif (isset($_POST['eliminar_factura_azucar'])) {
        $stock_id = (int)($_POST['id'] ?? 0);
        if ($stock_id > 0) {
            mysqli_query($conexion, "UPDATE stock SET fecha_fact = NULL, cant_fact = 0, facturada_a_id = NULL, precio_fac = NULL, n_fact = NULL, n_remt = NULL WHERE id = $stock_id");
            header('Location: gestionar_azucares.php?factura=elim');
            exit;
        }
    } elseif (isset($_POST['guardar_venta_azucar']) || isset($_POST['editar_venta_azucar'])) {
        $stock_id = (int)($_POST['stock_id'] ?? 0);
        $usuario_id = (int)($_POST['usuario_id'] ?? 0);
        $operador_id = (int)($_POST['operador_id'] ?? 0);
        $fecha_vta = trim($_POST['fecha_vta'] ?? '');
        $precio_venta_raw = str_replace(',', '.', trim($_POST['precio_venta'] ?? ''));
        $precio_vta = (float)$precio_venta_raw;
        $cant_vendida = (int)($_POST['cantidad_vendida'] ?? 0);
        $operacion_input = trim($_POST['operacion'] ?? '');
        $fecha_pago = trim($_POST['fecha_pago'] ?? '');
        $es_edicion = isset($_POST['editar_venta_azucar']);
        if ($stock_id < 1 || $usuario_id < 1 || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha_vta) || $cant_vendida < 1) {
            $mensaje_stock = 'Faltan datos o son inválidos (stock, usuario, fecha, cantidad vendida).';
        } else {
            $fecha_vta_esc = mysqli_real_escape_string($conexion, $fecha_vta);
            $row = mysqli_fetch_assoc(mysqli_query($conexion, "SELECT articulo, linea, orden FROM stock WHERE id = $stock_id LIMIT 1"));
            $articulo = mysqli_real_escape_string($conexion, $row['articulo'] ?? '');
            $linea = (int)($row['linea'] ?? 1);
            $orden = (int)($row['orden'] ?? 0);

            if ($es_edicion) {
                $r_old = mysqli_fetch_assoc(mysqli_query($conexion, "SELECT venta_movimiento_id, vendida_a_id, orden AS old_orden, operacion AS old_operacion, linea AS old_linea, precio_vta, cant_vta FROM stock WHERE id = $stock_id LIMIT 1"));
                $old_vendida_a_id = (int)($r_old['vendida_a_id'] ?? 0);
                $old_operacion = (int)($r_old['old_operacion'] ?? 0);
                $old_precio = (float)($r_old['precio_vta'] ?? 0);
                $old_cant = (int)($r_old['cant_vta'] ?? 0);
                $mismo_comprador = ($old_vendida_a_id > 0 && $usuario_id == $old_vendida_a_id);
                if ($mismo_comprador) {
                    if ($operacion_input !== '') {
                        $op_val = (int)$operacion_input;
                        $existe = mysqli_fetch_row(mysqli_query($conexion, "SELECT 1 FROM stock WHERE operacion = $op_val AND id != $stock_id LIMIT 1"));
                        $operacion = ($op_val > 0 && !$existe) ? $op_val : $old_operacion;
                    } else {
                        $operacion = $old_operacion > 0 ? $old_operacion : siguienteOperacion($conexion, $stock_id);
                    }
                } else {
                    $operacion = siguienteOperacion($conexion, $stock_id);
                }
            } else {
                $operacion = null;
                if ($operacion_input !== '') {
                    $op_val = (int)$operacion_input;
                    if ($op_val > 0) {
                        $existe = mysqli_fetch_row(mysqli_query($conexion, "SELECT 1 FROM stock WHERE operacion = $op_val AND id != $stock_id LIMIT 1"));
                        $operacion = $existe ? siguienteOperacion($conexion, $stock_id) : $op_val;
                    } else {
                        $operacion = siguienteOperacion($conexion, $stock_id);
                    }
                } else {
                    $operacion = siguienteOperacion($conexion, $stock_id);
                }
            }

            $concepto_base = ($linea === 2) ? 'VENTA AZUCAR LINEA2' : 'VENTA AZUCAR';
            $precio_vta_txt = number_format((float)$precio_vta, 2, ',', '');
            $concepto = $concepto_base . ' - ' . $articulo . ' - ' . $cant_vendida . ' UNIDADES a $ ' . $precio_vta_txt;
            $compro = ($linea === 2) ? "N° ORDEN $orden LINEA2" : "N° ORDEN $orden";
            $refer = "OP N° $operacion";
            $monto = -($precio_vta * $cant_vendida);

            if ($es_edicion) {
                if ($mismo_comprador) {
                    // Mismo comprador: no agregar nuevo registro en cuenta. Si solo cambia precio o cantidad (o ambos), solo actualizar el existente: monto y concepto (unidades y "a $ precio").
                    $mov_id = (int)($r_old['venta_movimiento_id'] ?? 0);
                    if ($mov_id <= 0 && $old_operacion > 0) {
                        $mov_id = (int)buscarMovimientoVentaPorOrdenOperacion($conexion, $old_vendida_a_id, $orden, $old_operacion, $linea);
                    }
                    if ($mov_id > 0) {
                        mysqli_query($conexion, "UPDATE cuentas SET fecha = '$fecha_vta_esc', concepto = '$concepto', comprobante = '$compro', referencia = '$refer', monto = $monto WHERE movimiento_id = $mov_id");
                    } else {
                        $ins = "INSERT INTO cuentas (usuario_id, fecha, concepto, comprobante, referencia, monto) VALUES ($usuario_id, '$fecha_vta_esc', '$concepto', '$compro', '$refer', $monto)";
                        mysqli_query($conexion, $ins);
                        $mov_id = (int)mysqli_insert_id($conexion);
                    }
                    $fecha_fact_sql = '';
                    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha_pago)) {
                        $r_check = mysqli_fetch_assoc(mysqli_query($conexion, "SELECT facturada_a_id FROM stock WHERE id = $stock_id LIMIT 1"));
                        if (empty($r_check['facturada_a_id'])) {
                            $fecha_pago_esc = mysqli_real_escape_string($conexion, $fecha_pago);
                            $fecha_fact_sql = ", fecha_fact = '$fecha_pago_esc'";
                        }
                    }
                    mysqli_query($conexion, "UPDATE stock SET fecha_vta = '$fecha_vta_esc', cant_vta = $cant_vendida, vendida_a_id = $usuario_id, operador_id = " . ($operador_id > 0 ? $operador_id : "NULL") . ", precio_vta = $precio_vta, operacion = $operacion, venta_movimiento_id = $mov_id $fecha_fact_sql WHERE id = $stock_id");
                } else {
                    // Cambio de comprador: en el registro del usuario viejo solo actualizar referencia a "Vta corregida" y monto a 0 (no nuevo asiento).
                    $mov_id_old = (int)($r_old['venta_movimiento_id'] ?? 0);
                    if ($mov_id_old <= 0 && $old_operacion > 0) {
                        $mov_id_old = (int)buscarMovimientoVentaPorOrdenOperacion($conexion, $old_vendida_a_id, $orden, $old_operacion, $linea);
                    }
                    if ($mov_id_old > 0) {
                        $ref_corregida_esc = mysqli_real_escape_string($conexion, 'Vta corregida');
                        mysqli_query($conexion, "UPDATE cuentas SET referencia = '$ref_corregida_esc', monto = 0 WHERE movimiento_id = $mov_id_old");
                    }
                    $fecha_fact_sql = '';
                    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha_pago)) {
                        $r_check = mysqli_fetch_assoc(mysqli_query($conexion, "SELECT facturada_a_id FROM stock WHERE id = $stock_id LIMIT 1"));
                        if (empty($r_check['facturada_a_id'])) {
                            $fecha_pago_esc = mysqli_real_escape_string($conexion, $fecha_pago);
                            $fecha_fact_sql = ", fecha_fact = '$fecha_pago_esc'";
                        }
                    }
                    $ins = "INSERT INTO cuentas (usuario_id, fecha, concepto, comprobante, referencia, monto) VALUES ($usuario_id, '$fecha_vta_esc', '$concepto', '$compro', '$refer', $monto)";
                    mysqli_query($conexion, $ins);
                    $mov_id = (int)mysqli_insert_id($conexion);
                    mysqli_query($conexion, "UPDATE stock SET fecha_vta = '$fecha_vta_esc', cant_vta = $cant_vendida, vendida_a_id = $usuario_id, operador_id = " . ($operador_id > 0 ? $operador_id : "NULL") . ", precio_vta = $precio_vta, operacion = $operacion, venta_movimiento_id = $mov_id $fecha_fact_sql WHERE id = $stock_id");
                }
            } else {
                $fecha_fact_sql = '';
                if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha_pago)) {
                    $fecha_pago_esc = mysqli_real_escape_string($conexion, $fecha_pago);
                    $fecha_fact_sql = ", fecha_fact = '$fecha_pago_esc'";
                }
                $ins = "INSERT INTO cuentas (usuario_id, fecha, concepto, comprobante, referencia, monto) VALUES ($usuario_id, '$fecha_vta_esc', '$concepto', '$compro', '$refer', $monto)";
                if (mysqli_query($conexion, $ins)) {
                    $mov_id = (int)mysqli_insert_id($conexion);
                    mysqli_query($conexion, "UPDATE stock SET fecha_vta = '$fecha_vta_esc', cant_vta = $cant_vendida, vendida_a_id = $usuario_id, operador_id = " . ($operador_id > 0 ? $operador_id : "NULL") . ", precio_vta = $precio_vta, operacion = $operacion, venta_movimiento_id = $mov_id $fecha_fact_sql WHERE id = $stock_id");
                } else {
                    $mensaje_stock = 'Falta dato o corregir.';
                    $operacion = null;
                }
            }
            if ($operacion !== null && !$mensaje_stock) {
                header('Location: gestionar_azucares.php?venta=ok');
                exit;
            }
        }
    } elseif (isset($_POST['guardar_factura_azucar']) || isset($_POST['editar_factura_azucar'])) {
        $stock_id = (int)($_POST['stock_id'] ?? 0);
        $usuario_id = (int)($_POST['usuario_id'] ?? 0);
        $fecha_fact = trim($_POST['fecha_fact'] ?? '');
        $cant_fact = (int)($_POST['cantidad_facturada'] ?? 0);
        $precio_fac_raw = str_replace(',', '.', trim($_POST['precio_fac'] ?? ''));
        $precio_fac = (float)$precio_fac_raw;
        $n_fact = mysqli_real_escape_string($conexion, trim($_POST['n_fact'] ?? ''));
        $n_remt = mysqli_real_escape_string($conexion, trim($_POST['n_remt'] ?? ''));
        if ($stock_id < 1 || $usuario_id < 1 || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha_fact) || $cant_fact < 1) {
            $mensaje_stock = 'Faltan datos o son inválidos (stock, usuario, fecha factura, cantidad facturada).';
        } else {
            $fecha_fact_esc = mysqli_real_escape_string($conexion, $fecha_fact);
            $up = "UPDATE stock SET fecha_fact = '$fecha_fact_esc', cant_fact = $cant_fact, facturada_a_id = $usuario_id, precio_fac = $precio_fac, n_fact = " . ($n_fact === '' ? "NULL" : "'$n_fact'") . ", n_remt = " . ($n_remt === '' ? "NULL" : "'$n_remt'") . " WHERE id = $stock_id";
            if (mysqli_query($conexion, $up)) {
                header('Location: gestionar_azucares.php?factura=ok');
                exit;
            }
            $mensaje_stock = 'Falta dato o corregir.';
        }
    } elseif (isset($_POST['eliminar_stock'])) {
        $id_borrar = (int)($_POST['id'] ?? 0);
        if ($id_borrar > 0 && mysqli_query($conexion, "DELETE FROM stock WHERE id = $id_borrar")) {
            header('Location: gestionar_azucares.php?borrado=ok');
            exit;
        } else {
            $mensaje_stock = 'Falta dato o corregir.';
        }
    } elseif (isset($_POST['alta_stock'])) {
        $es_ajax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') || !empty($_POST['alta_ajax']);
        $editar_id = (int)($_POST['id'] ?? 0);
        $fecha = isset($_POST['fecha']) ? trim($_POST['fecha']) : '';
        $linea = (int)($_POST['linea'] ?? 1);
        if ($linea !== 1 && $linea !== 2) $linea = 1;
        $articulo = mysqli_real_escape_string($conexion, strtoupper(trim($_POST['articulo'] ?? '')));
        $orden = (int)($_POST['orden'] ?? 0);
        $deposito = mysqli_real_escape_string($conexion, strtoupper(trim($_POST['deposito'] ?? '')));
        $cantidad = (int)($_POST['cantidad'] ?? 0);
        $articulo_raw = trim($_POST['articulo'] ?? '');
        $deposito_raw = trim($_POST['deposito'] ?? '');

        if ($orden < 1 || $cantidad < 1) {
            $mensaje_stock = 'Orden y Cantidad deben ser mayor a 0 (no se acepta 0 como dato válido).';
            if ($es_ajax && $editar_id === 0) {
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['ok' => false, 'mensaje' => $mensaje_stock]);
                exit;
            }
        } elseif (preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) {
            if ($editar_id > 0) {
                $sql_up = "UPDATE stock SET fecha = '$fecha', linea = $linea, articulo = '$articulo', orden = $orden, cantidad = $cantidad, deposito = " . ($deposito === '' ? "NULL" : "'$deposito'") . " WHERE id = $editar_id";
                if (mysqli_query($conexion, $sql_up)) {
                    $mensaje_stock = 'Registro actualizado.';
                    header('Location: gestionar_azucares.php?alta=ok');
                    exit;
                } else {
                    $mensaje_stock = 'Falta dato o corregir.';
                }
            } else {
                $sql_ins = "INSERT INTO stock (fecha, linea, articulo, orden, cantidad, deposito) VALUES ('$fecha', $linea, '$articulo', $orden, $cantidad, " . ($deposito === '' ? "NULL" : "'$deposito'") . ")";
                if (mysqli_query($conexion, $sql_ins)) {
                    $mensaje_stock = 'Registro agregado.';
                    if ($es_ajax) {
                        $nuevo_id = mysqli_insert_id($conexion);
                        $sql_nuevo = "SELECT s.*,
                            v.apellido AS vendida_a_apellido,
                            op.apellido AS operador_apellido,
                            f.apellido AS facturada_a_apellido
                        FROM stock s
                        LEFT JOIN usuarios v ON v.id = s.vendida_a_id
                        LEFT JOIN usuarios op ON op.id = s.operador_id
                        LEFT JOIN usuarios f ON f.id = s.facturada_a_id
                        WHERE s.id = $nuevo_id LIMIT 1";
                        $res_nuevo = mysqli_query($conexion, $sql_nuevo);
                        $r_nuevo = $res_nuevo ? mysqli_fetch_assoc($res_nuevo) : null;
                        $fila_html = '';
                        if ($r_nuevo) {
                            $fechaRaw = !empty($r_nuevo['fecha']) && $r_nuevo['fecha'] !== '0000-00-00' ? $r_nuevo['fecha'] : '';
                            $fila_html = '<tr data-id="' . (int)$r_nuevo['id'] . '"'
                                . ' data-fecha="' . htmlspecialchars($fechaRaw, ENT_QUOTES) . '"'
                                . ' data-linea="' . (int)$r_nuevo['linea'] . '"'
                                . ' data-articulo="' . htmlspecialchars($r_nuevo['articulo'] ?? '', ENT_QUOTES) . '"'
                                . ' data-orden="' . (int)$r_nuevo['orden'] . '"'
                                . ' data-cantidad="' . (int)$r_nuevo['cantidad'] . '"'
                                . ' data-deposito="' . htmlspecialchars($r_nuevo['deposito'] ?? '', ENT_QUOTES) . '"'
                                . ' data-vendida-a-id="' . (int)($r_nuevo['vendida_a_id'] ?? 0) . '"'
                                . ' data-vendida-a-apellido="' . htmlspecialchars($r_nuevo['vendida_a_apellido'] ?? '', ENT_QUOTES) . '"'
                                . ' data-fechavta="' . (!empty($r_nuevo['fecha_vta']) && $r_nuevo['fecha_vta'] !== '0000-00-00' ? $r_nuevo['fecha_vta'] : '') . '"'
                                . ' data-preciovta="' . ($r_nuevo['precio_vta'] !== null && $r_nuevo['precio_vta'] !== '' ? (float)$r_nuevo['precio_vta'] : '') . '"'
                                . ' data-cantvta="' . (int)($r_nuevo['cant_vta'] ?? 0) . '"'
                                . ' data-operacion="' . (int)($r_nuevo['operacion'] ?? 0) . '"'
                                . ' data-fechafact="' . (!empty($r_nuevo['fecha_fact']) && $r_nuevo['fecha_fact'] !== '0000-00-00' ? $r_nuevo['fecha_fact'] : '') . '"'
                                . ' data-cantfact="' . (int)($r_nuevo['cant_fact'] ?? 0) . '"'
                                . ' data-facturada-a-id="' . (int)($r_nuevo['facturada_a_id'] ?? 0) . '"'
                                . ' data-facturada-a-apellido="' . htmlspecialchars($r_nuevo['facturada_a_apellido'] ?? '', ENT_QUOTES) . '"'
                                . ' data-preciofac="' . ($r_nuevo['precio_fac'] !== null && $r_nuevo['precio_fac'] !== '' ? (float)$r_nuevo['precio_fac'] : '') . '"'
                                . ' data-nfact="' . htmlspecialchars($r_nuevo['n_fact'] ?? '', ENT_QUOTES) . '"'
                                . ' data-nremt="' . htmlspecialchars($r_nuevo['n_remt'] ?? '', ENT_QUOTES) . '">'
                                . '<td class="col-fecha">' . htmlspecialchars(fmtFecha($r_nuevo['fecha'])) . '</td>'
                                . '<td class="col-l">' . (int)$r_nuevo['linea'] . '</td>'
                                . '<td class="col-articulo">' . htmlspecialchars($r_nuevo['articulo']) . '</td>'
                                . '<td class="col-orden">' . (int)$r_nuevo['orden'] . '</td>'
                                . '<td class="col-cantidad">' . (int)$r_nuevo['cantidad'] . '</td>'
                                . '<td class="col-deposito">' . htmlspecialchars($r_nuevo['deposito'] ?? '') . '</td>'
                                . '<td class="col-operacion">' . ((int)($r_nuevo['operacion'] ?? 0) ?: '') . '</td>'
                                . '<td class="col-fechavta">' . htmlspecialchars(fmtFecha($r_nuevo['fecha_vta'])) . '</td>'
                                . '<td class="col-cantvta">' . (int)$r_nuevo['cant_vta'] . '</td>'
                                . '<td class="col-vendida ' . (empty($r_nuevo['vendida_a_apellido']) ? 'sin-dato' : '') . '">'
                                . ((int)($r_nuevo['vendida_a_id'] ?? 0) > 0 && !empty($r_nuevo['vendida_a_apellido'])
                                    ? '<a href="index.php?usuario_id=' . (int)$r_nuevo['vendida_a_id'] . '" class="link-comprador" onclick="event.stopPropagation();">' . htmlspecialchars($r_nuevo['vendida_a_apellido']) . '</a>'
                                    : htmlspecialchars($r_nuevo['vendida_a_apellido'] ?? ''))
                                . '</td>'
                                . '<td class="col-operador ' . (empty($r_nuevo['operador_apellido']) ? 'sin-dato' : '') . '">' . htmlspecialchars($r_nuevo['operador_apellido'] ?? '') . '</td>'
                                . '<td class="col-preciovta">' . fmtNum($r_nuevo['precio_vta']) . '</td>'
                                . '<td class="col-fechafact">' . htmlspecialchars(fmtFecha($r_nuevo['fecha_fact'])) . '</td>'
                                . '<td class="col-cantfact">' . (int)$r_nuevo['cant_fact'] . '</td>'
                                . '<td class="col-facturada ' . (empty($r_nuevo['facturada_a_apellido']) ? 'sin-dato' : '') . '">' . htmlspecialchars($r_nuevo['facturada_a_apellido'] ?? '') . '</td>'
                                . '<td class="col-preciofac">' . fmtNum($r_nuevo['precio_fac']) . '</td>'
                                . '<td class="col-nfact ' . (empty($r_nuevo['n_fact']) ? 'sin-dato' : '') . '">' . htmlspecialchars($r_nuevo['n_fact'] ?? '') . '</td>'
                                . '<td class="col-nremt ' . (empty($r_nuevo['n_remt']) ? 'sin-dato' : '') . '">' . htmlspecialchars($r_nuevo['n_remt'] ?? '') . '</td>'
                                . '</tr>';
                        }
                        header('Content-Type: application/json; charset=utf-8');
                        echo json_encode([
                            'ok' => true,
                            'mensaje' => $mensaje_stock,
                            'fecha' => $fecha,
                            'linea' => $linea,
                            'articulo' => $articulo_raw,
                            'orden' => $orden,
                            'cantidad' => $cantidad,
                            'deposito' => $deposito_raw,
                            'fila_html' => $fila_html
                        ]);
                        exit;
                    }
                    header('Location: gestionar_azucares.php?alta=ok');
                    exit;
                } else {
                    $mensaje_stock = 'Falta dato o corregir.';
                    if ($es_ajax) {
                        header('Content-Type: application/json; charset=utf-8');
                        echo json_encode(['ok' => false, 'mensaje' => $mensaje_stock]);
                        exit;
                    }
                }
            }
        } else {
            $mensaje_stock = 'Fecha inválida (formato AAAA-MM-DD).';
            if ($es_ajax && $editar_id === 0) {
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['ok' => false, 'mensaje' => $mensaje_stock]);
                exit;
            }
        }
    }
}

// Último articulo, deposito y cantidad cargados (para default); listas únicas para datalist
$ultimo_articulo = '';
$ultimo_deposito = '';
$ultima_cantidad = '';
$articulos_unicos = [];
$depositos_unicos = [];
foreach ($filas_stock as $f) {
    if (!empty($f['articulo']) && !in_array($f['articulo'], $articulos_unicos)) $articulos_unicos[] = $f['articulo'];
    if (isset($f['deposito']) && $f['deposito'] !== '' && $f['deposito'] !== null && !in_array($f['deposito'], $depositos_unicos)) $depositos_unicos[] = $f['deposito'];
}
// Buscar el último registro que tenga depósito con valor (el más reciente con depósito)
if (!empty($filas_stock)) {
    $ultimo_articulo = $filas_stock[0]['articulo'] ?? '';
    $ultima_cantidad = (int)($filas_stock[0]['cantidad'] ?? 0);
    // Buscar el último depósito con valor (recorrer desde el inicio, que es el más reciente)
    foreach ($filas_stock as $f) {
        if (isset($f['deposito']) && trim($f['deposito']) !== '' && $f['deposito'] !== null) {
            $ultimo_deposito = trim($f['deposito']);
            break; // Tomar el primero que encuentre (ya está ordenado por fecha DESC, es el más reciente)
        }
    }
}
if (isset($_GET['alta']) && $_GET['alta'] === 'ok') {
    $mensaje_stock = 'Registro guardado.';
}
if (isset($_GET['borrado']) && $_GET['borrado'] === 'ok') {
    $mensaje_stock = 'Registro eliminado.';
}
if (isset($_GET['venta']) && $_GET['venta'] === 'ok') {
    $mensaje_stock = 'Venta registrada (cuenta actualizada, no caja).';
}
if (isset($_GET['venta']) && $_GET['venta'] === 'elim') {
    $mensaje_stock = 'Venta eliminada (registro de stock y cuenta actualizados).';
}
if (isset($_GET['factura']) && $_GET['factura'] === 'ok') {
    $mensaje_stock = 'Factura registrada.';
}
if (isset($_GET['factura']) && $_GET['factura'] === 'elim') {
    $mensaje_stock = 'Factura eliminada del registro.';
}

function siguienteOperacion($conexion, $excluir_id = 0) {
    $excluir_id = (int)$excluir_id;
    $res = mysqli_query($conexion, "SELECT operacion FROM stock WHERE operacion IS NOT NULL AND operacion > 0 AND id != $excluir_id");
    $usados = [];
    if ($res) while ($row = mysqli_fetch_assoc($res)) $usados[(int)$row['operacion']] = true;
    $n = 1;
    while (isset($usados[$n])) $n++;
    return $n;
}

/** Busca movimiento_id en cuentas por usuario, orden y operación (identificación por N° Orden y OP N°). */
function buscarMovimientoVentaPorOrdenOperacion($conexion, $usuario_id, $orden, $operacion, $linea = 1) {
    $usuario_id = (int)$usuario_id;
    $orden = (int)$orden;
    $operacion = (int)$operacion;
    if ($usuario_id < 1 || $operacion < 1) return null;
    $compro = ($linea === 2) ? "N° ORDEN $orden LINEA2" : "N° ORDEN $orden";
    $refer = "OP N° $operacion";
    $res = mysqli_query($conexion, "SELECT movimiento_id FROM cuentas WHERE usuario_id = $usuario_id AND comprobante = '" . mysqli_real_escape_string($conexion, $compro) . "' AND referencia = '" . mysqli_real_escape_string($conexion, $refer) . "' AND concepto LIKE 'VENTA AZUCAR%' LIMIT 1");
    if (!$res || !$row = mysqli_fetch_assoc($res)) return null;
    return (int)$row['movimiento_id'];
}

function fmtFecha($f) {
    if (empty($f) || $f === '0000-00-00') return '';
    return date('d/m/Y', strtotime($f));
}
function fmtNum($n) {
    return $n !== null && $n !== '' ? number_format((float)$n, 2, ',', '.') : '0';
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de azúcares</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 15px; background: #f5f5f5; font-size: 12px; }
        .container { max-width: 98%; margin: 0 auto; background: white; padding: 15px; border-radius: 6px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h2 { color: #007bff; margin-top: 0; margin-bottom: 12px; font-size: 18px; }
        .btn { padding: 6px 12px; border: none; border-radius: 4px; cursor: pointer; font-size: 11px; text-decoration: none; display: inline-block; }
        .btn-secondary { background: #6c757d; color: white; }
        .volver { margin-top: 15px; }
        .contenedor-grilla-con-botones { overflow-x: hidden; margin-top: 10px; }
        .contenedor-grilla-con-botones .fila-botones-stock,
        .contenedor-grilla-con-botones #cartelSaldoOrden { min-width: 1410px; }
        .grid-azucar-wrap { overflow-x: visible; overflow-y: scroll; max-height: 185px; border: 1px solid #ddd; outline: none; }
        .grid-azucar-wrap:focus { outline: none; }
        .tabla-azucar { width: 100%; border-collapse: collapse; font-size: 11px; table-layout: fixed; min-width: 1270px; line-height: 1.2; font-weight: bold; }
        .tabla-azucar th { background: #007bff; color: white; padding: 4px 3px; position: sticky; top: 0; z-index: 10; font-weight: bold; text-align: center; white-space: nowrap; border: 1px solid #0056b3; }
        .tabla-azucar td { padding: 2px 3px; border: 1px solid #ddd; text-align: center; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; font-weight: bold; }
        .tabla-azucar tbody tr { cursor: pointer; }
        .tabla-azucar tbody tr:hover { background: #e7f3ff; }
        .tabla-azucar tbody tr.fila-seleccionada { background: #007bff; color: white; }
        .cartel-saldo-orden { background: #fff3cd; border: 1px solid #ffc107; color: #856404; padding: 10px 14px; border-radius: 6px; margin-bottom: 10px; font-size: 14px; font-weight: bold; }
        .tabla-azucar .col-fecha, .tabla-azucar .col-fechavta, .tabla-azucar .col-fechafact { width: 75px; }
        .tabla-azucar .col-l { width: 28px; }
        .tabla-azucar .col-articulo { width: 200px; }
        .tabla-azucar .col-orden { width: 55px; }
        .tabla-azucar .col-cantidad, .tabla-azucar .col-cantvta, .tabla-azucar .col-cantfact { width: 55px; }
        .tabla-azucar .col-deposito { width: 160px; }
        .tabla-azucar .col-operacion { width: 45px; cursor: pointer; }
        .tabla-azucar .col-operacion:hover { text-decoration: underline; color: #007bff; }
        .tabla-azucar tbody tr .col-operacion:hover { background-color: #e7f3ff; }
        .tabla-azucar .col-vendida, .tabla-azucar .col-facturada, .tabla-azucar .col-operador { width: 140px; }
        .tabla-azucar .col-vendida .link-comprador { color: #007bff; text-decoration: underline; cursor: pointer; }
        .tabla-azucar .col-vendida .link-comprador:hover { color: #0056b3; }
        .tabla-azucar tbody tr.fila-seleccionada .col-vendida .link-comprador { color: white; }
        .tabla-azucar tbody tr.fila-seleccionada .col-vendida .link-comprador:hover { color: #e7f3ff; }
        .tabla-azucar .col-preciovta, .tabla-azucar .col-preciofac { width: 75px; }
        .tabla-azucar .col-nfact, .tabla-azucar .col-nremt { width: 70px; }
        .tabla-azucar .sin-dato { color: #999; }
        .tabla-azucar .col-articulo, .tabla-azucar .col-deposito { text-transform: uppercase; }
        .caja-interpretar { margin-bottom: 15px; padding: 12px; background: #f8f9fa; border: 1px solid #dee2e6; border-radius: 6px; }
        .caja-interpretar label { display: block; margin-bottom: 4px; font-weight: bold; font-size: 11px; }
        .caja-interpretar textarea { width: 100%; min-height: 80px; padding: 8px; border: 1px solid #ced4da; border-radius: 4px; font-size: 12px; box-sizing: border-box; resize: vertical; }
        .caja-interpretar .fila-boton { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; margin-top: 8px; }
        .caja-interpretar input[type="file"] { font-size: 11px; }
        .btn-interpretar { background: #007bff; color: white; padding: 8px 16px; border: none; border-radius: 4px; cursor: pointer; font-weight: bold; }
        .btn-interpretar:hover { background: #0056b3; }
        .btn-interpretar:disabled { opacity: 0.6; cursor: not-allowed; }
        .msg-interpretar { margin-top: 8px; font-size: 11px; }
        .msg-interpretar.error { color: #dc3545; }
        .msg-interpretar.ok { color: #28a745; }
        .fila-botones-stock { display: flex; align-items: stretch; gap: 8px; margin-bottom: 10px; flex-wrap: wrap; }
        .fila-botones-venta { margin-left: calc(303px + 2cm); display: flex; align-items: stretch; gap: 8px; }
        .fila-botones-facturacion { margin-left: 3cm; display: flex; align-items: stretch; gap: 8px; }
        .btn-alta-stock, .btn-editar-stock, .btn-eliminar-stock { padding: 5px 12px; font-size: 12px; line-height: 1.25; border: none; border-radius: 4px; cursor: pointer; font-weight: bold; box-sizing: border-box; }
        .btn-alta-stock { background: #28a745; color: white; }
        .btn-alta-stock:hover { background: #218838; }
        .btn-editar-stock { background: #007bff; color: white; }
        .btn-editar-stock:hover { background: #0056b3; }
        .btn-eliminar-stock { background: #dc3545; color: white; }
        .btn-eliminar-stock:hover { background: #c82333; }
        .btn-venta-vta { background: #17a2b8; color: white; padding: 5px 12px; font-size: 12px; line-height: 1.25; border: none; border-radius: 4px; cursor: pointer; font-weight: bold; }
        .btn-venta-vta:hover { background: #138496; }
        .btn-editar-vta { background: #6f42c1; color: white; padding: 5px 12px; font-size: 12px; line-height: 1.25; border: none; border-radius: 4px; cursor: pointer; font-weight: bold; }
        .btn-editar-vta:hover { background: #5a32a3; }
        .btn-eliminar-vta { background: #fd7e14; color: white; padding: 5px 12px; font-size: 12px; line-height: 1.25; border: none; border-radius: 4px; cursor: pointer; font-weight: bold; }
        .btn-eliminar-vta:hover { background: #e96f0a; }
        .btn-facturar { background: #20c997; color: white; padding: 5px 12px; font-size: 12px; line-height: 1.25; border: none; border-radius: 4px; cursor: pointer; font-weight: bold; }
        .btn-facturar:hover { background: #1aa179; }
        .btn-editar-fact { background: #6f42c1; color: white; padding: 5px 12px; font-size: 12px; line-height: 1.25; border: none; border-radius: 4px; cursor: pointer; font-weight: bold; }
        .btn-editar-fact:hover { background: #5a32a3; }
        .btn-eliminar-fact { background: #dc3545; color: white; padding: 5px 12px; font-size: 12px; line-height: 1.25; border: none; border-radius: 4px; cursor: pointer; font-weight: bold; }
        .btn-eliminar-fact:hover { background: #c82333; }
        .modal-venta-overlay { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 9999; align-items: center; justify-content: center; padding: 8px; box-sizing: border-box; overflow-y: auto; }
        .modal-venta-overlay.activo { display: flex; }
        .modal-venta { background: white; border-radius: 8px; padding: 12px 16px; max-width: 520px; width: 100%; max-height: calc(100vh - 20px); overflow-y: auto; box-shadow: 0 4px 20px rgba(0,0,0,0.2); }
        .modal-venta h3 { margin: 0 0 8px 0; font-size: 0.95rem; color: #007bff; }
        .modal-venta label { display: block; margin-bottom: 2px; font-weight: bold; font-size: 10px; }
        .modal-venta input, .modal-venta select { width: 100%; padding: 6px 8px; border: 1px solid #ced4da; border-radius: 4px; box-sizing: border-box; font-size: 12px; margin-bottom: 0; }
        .modal-venta .fila-buscar { display: flex; gap: 6px; align-items: flex-end; margin-bottom: 6px; }
        .modal-venta .fila-buscar input { margin-bottom: 0; flex: 1; }
        .modal-venta .fila-buscar .btn-alta-usuario { padding: 6px 10px; font-size: 10px; white-space: nowrap; background: #28a745; color: white; border: none; border-radius: 4px; cursor: pointer; font-weight: bold; }
        .buscador-venta-resultados { position: absolute; top: 100%; left: 0; right: 0; background: white; border: 1px solid #ced4da; border-top: none; max-height: 120px; overflow-y: auto; z-index: 100; box-shadow: 0 4px 12px rgba(0,0,0,0.1); display: none; font-size: 11px; }
        .buscador-venta-resultados .item-venta { padding: 6px 8px; cursor: pointer; border-bottom: 1px solid #eee; }
        .buscador-venta-resultados .item-venta:hover { background: #e7f3ff; }
        .modal-venta .campo-ro { background: #f0f0f0; padding: 4px 6px; border-radius: 4px; margin-bottom: 6px; font-size: 11px; }
        .modal-venta .fila-venta-uno { display: flex; gap: 6px; align-items: flex-end; flex-wrap: wrap; margin-bottom: 6px; }
        .modal-venta .fila-venta-uno .campo-fecha-vta { flex: 0 0 20%; min-width: 80px; }
        .modal-venta .fila-venta-uno .campo-linea { flex: 0 0 auto; width: 3.2em; }
        .modal-venta .fila-venta-uno .campo-orden { flex: 0 0 4.5em; min-width: 60px; }
        .modal-venta .fila-venta-uno .campo-cantidad { flex: 0 0 4.5em; min-width: 60px; }
        .modal-venta .fila-venta-uno .campo-deposito { flex: 1; min-width: 80px; }
        .modal-venta .fila-venta-uno input { margin-bottom: 0; }
        .modal-venta .fila-venta-uno .campo-ro { margin-bottom: 0; }
        .modal-venta .fila-venta-dos { display: flex; gap: 8px; align-items: flex-end; flex-wrap: wrap; margin-bottom: 6px; }
        .modal-venta .fila-venta-dos .form-g { margin-bottom: 0; }
        .modal-venta .botones { display: flex; gap: 8px; margin-top: 10px; }
        .modal-venta .botones button { padding: 6px 14px; border: none; border-radius: 4px; font-weight: bold; cursor: pointer; font-size: 12px; }
        .modal-venta .btn-guardar-venta { background: #28a745; color: white; }
        .modal-venta .btn-cerrar-venta { background: #6c757d; color: white; }
        .modal-venta .campo-precio-venta { width: 25%; min-width: 70px; }
        .modal-venta .campo-precio-venta input { width: 100%; position: relative; z-index: 2; }
        .modal-venta .fila-factura-precios { display: flex; gap: 8px; align-items: flex-end; flex-wrap: wrap; margin-bottom: 6px; }
        .modal-venta .fila-factura-precios .campo-precio-venta { flex: 0 0 auto; min-width: 90px; }
        .modal-venta .fila-factura-precios .campo-fact-sin-iva { flex: 0 0 auto; min-width: 85px; }
        .modal-venta .fila-factura-precios .campo-fact-sin-iva .campo-ro { margin-bottom: 0; }
        .modal-venta .fila-factura-precios .campo-fact-cant { flex: 0 0 auto; min-width: 70px; }
        .modal-venta .fila-factura-precios .campo-fact-cant input { margin-bottom: 0; }
        .modal-venta .fila-factura-precios .campo-fact-monto { flex: 0 0 auto; min-width: 100px; }
        .modal-venta .fila-factura-precios .campo-fact-monto .campo-ro { margin-bottom: 0; }
        .modal-venta #venta_cant_vendida::-webkit-outer-spin-button,
        .modal-venta #venta_cant_vendida::-webkit-inner-spin-button,
        .modal-venta #venta_operacion::-webkit-outer-spin-button,
        .modal-venta #venta_operacion::-webkit-inner-spin-button { -webkit-appearance: none; margin: 0; }
        .modal-venta #venta_cant_vendida,
        .modal-venta #venta_operacion { -moz-appearance: textfield; appearance: textfield; }
        .modal-venta .campo-ro { text-transform: uppercase; }
        .modal-venta .cartel-parcial-venta { background: #fff3cd; border: 1px solid #ffc107; color: #856404; padding: 6px 10px; border-radius: 4px; margin-bottom: 6px; font-size: 12px; font-weight: bold; }
        .modal-venta .fila-usuario-operador { display: flex; gap: 12px; margin-bottom: 6px; }
        .modal-venta .fila-usuario-operador > div { flex: 1; min-width: 0; }
        .modal-alta #alta_articulo,
        .modal-alta #alta_deposito { text-transform: uppercase; }
        .modal-alta-overlay { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 9999; align-items: center; justify-content: center; padding: 15px; box-sizing: border-box; }
        .modal-alta-overlay.activo { display: flex; }
        .modal-alta { background: white; border-radius: 8px; padding: 20px; max-width: 480px; width: 100%; box-shadow: 0 4px 20px rgba(0,0,0,0.2); }
        .modal-alta h3 { margin: 0 0 15px 0; font-size: 1rem; color: #007bff; }
        .modal-alta label { display: block; margin-bottom: 4px; font-weight: bold; font-size: 11px; }
        .modal-alta input, .modal-alta select { width: 100%; padding: 8px; border: 1px solid #ced4da; border-radius: 4px; box-sizing: border-box; font-size: 12px; margin-bottom: 12px; }
        .modal-alta .fila-modal { display: flex; gap: 12px; align-items: flex-end; }
        .modal-alta .fila-modal .campo { flex: 1; min-width: 0; }
        .modal-alta .fila-modal .campo-fecha { flex: 0 0 25%; max-width: 25%; }
        .modal-alta .fila-modal .campo-l { flex: 0 0 42px; max-width: 42px; }
        .modal-alta .fila-modal .campo-orden, .modal-alta .fila-modal .campo-cantidad { flex: 0 0 20%; max-width: 20%; }
        .modal-alta .botones { display: flex; gap: 10px; margin-top: 15px; }
        .modal-alta .botones button { padding: 8px 16px; border: none; border-radius: 4px; font-weight: bold; cursor: pointer; }
        .modal-alta .btn-guardar { background: #28a745; color: white; }
        .modal-alta .btn-cerrar { background: #6c757d; color: white; }
        .modal-alta #alta_orden::-webkit-outer-spin-button,
        .modal-alta #alta_orden::-webkit-inner-spin-button,
        .modal-alta #alta_cantidad::-webkit-outer-spin-button,
        .modal-alta #alta_cantidad::-webkit-inner-spin-button { -webkit-appearance: none; margin: 0; }
        .modal-alta #alta_orden,
        .modal-alta #alta_cantidad { -moz-appearance: textfield; appearance: textfield; }
    </style>
</head>
<body onkeydown="var e=event||window.event;if((e.keyCode||e.which)===27){var m=document.getElementById('modalMovimientosOperacion');if(m&&m.classList.contains('activo')){if(typeof cerrarModalMovimientosOperacion==='function')cerrarModalMovimientosOperacion();e.preventDefault();return false;}var v=document.getElementById('modalVenta');if(v&&v.classList.contains('activo')){if(typeof cerrarModalVenta==='function')cerrarModalVenta();e.preventDefault();return false;}var f=document.getElementById('modalFactura');if(f&&f.classList.contains('activo')){if(typeof cerrarModalFactura==='function')cerrarModalFactura();e.preventDefault();return false;}var a=document.getElementById('modalAltaStock');if(a&&a.classList.contains('activo')){if(typeof cerrarModalAltaStock==='function')cerrarModalAltaStock();e.preventDefault();return false;}if(history.length>1){history.back();e.preventDefault();return false;}location.href='index.php';e.preventDefault();return false;}">
    <div class="container">
        <h2>Gestión de azúcares <span style="font-size:14px; color:#856404; font-weight:normal;">(Faltan vender: <?= $faltan_vender ?> órdenes, <?= number_format($faltan_vender_cantidad, 0, ',', '.') ?> cantidad)</span></h2>

        <?php if ($mensaje_stock): ?>
        <p class="msg-interpretar ok" style="display: block;"><?= htmlspecialchars($mensaje_stock) ?></p>
        <?php endif; ?>

        <div class="contenedor-grilla-con-botones">
        <div class="fila-botones-stock">
            <button type="button" class="btn-alta-stock" id="btnAltaStock">Alta stock</button>
            <button type="button" class="btn-editar-stock" id="btnEditarStock">Editar</button>
            <form id="formEliminarStock" method="post" action="gestionar_azucares.php" style="display: inline;">
                <input type="hidden" name="eliminar_stock" value="1">
                <input type="hidden" name="id" id="eliminar_id" value="">
                <button type="button" class="btn-eliminar-stock" id="btnEliminarStock">Eliminar</button>
            </form>
            <div class="fila-botones-venta">
                <button type="button" class="btn-venta-vta" id="btnVenta">Venta</button>
                <button type="button" class="btn-editar-vta" id="btnEditarVta">Editar Vta</button>
                <form id="formEliminarVta" method="post" action="gestionar_azucares.php" style="display: inline;">
                    <input type="hidden" name="eliminar_venta_azucar" value="1">
                    <input type="hidden" name="id" id="eliminar_vta_id" value="">
                    <button type="button" class="btn-eliminar-vta" id="btnEliminarVta">Eliminar Vta</button>
                </form>
            </div>
            <div class="fila-botones-facturacion">
                <button type="button" class="btn-facturar" id="btnFacturar">Facturar</button>
                <button type="button" class="btn-editar-fact" id="btnEditarFact">Editar Factura</button>
                <form id="formEliminarFactura" method="post" action="gestionar_azucares.php" style="display: inline;">
                    <input type="hidden" name="eliminar_factura_azucar" value="1">
                    <input type="hidden" name="id" id="eliminar_factura_id" value="">
                    <button type="button" class="btn-eliminar-fact" id="btnEliminarFact">Eliminar Factura</button>
                </form>
            </div>
        </div>

        <div id="cartelSaldoOrden" class="cartel-saldo-orden" style="display: none;"></div>
        <div class="grid-azucar-wrap" id="gridAzucarWrap" tabindex="0" title="Clic acá y usá flechas ↑↓ o la ruedita para moverte">
            <table class="tabla-azucar">
                <thead>
                    <tr>
                        <th class="col-fecha">Fecha</th>
                        <th class="col-l">L</th>
                        <th class="col-articulo">Articulo</th>
                        <th class="col-orden">Orden</th>
                        <th class="col-cantidad">Cantidad</th>
                        <th class="col-deposito">Deposito</th>
                        <th class="col-operacion">Op</th>
                        <th class="col-fechavta">FechaVta</th>
                        <th class="col-cantvta">Cant Vta</th>
                        <th class="col-vendida">Vendida a</th>
                        <th class="col-operador">Operador</th>
                        <th class="col-preciovta">Precio vta</th>
                        <th class="col-fechafact">Fechafact</th>
                        <th class="col-cantfact">Cant Fact</th>
                        <th class="col-facturada">Facturada a</th>
                        <th class="col-preciofac">Precio Fac</th>
                        <th class="col-nfact">N° Fact</th>
                        <th class="col-nremt">N° Remt</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($filas_stock)): ?>
                        <tr>
                            <td colspan="18" style="text-align: center; padding: 15px; color: #666;">No hay registros en stock. La tabla ya está creada; podés cargar datos cuando agreguemos el alta/edición.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($filas_stock as $i => $r): ?>
                        <?php
                        $fechaRaw = !empty($r['fecha']) && $r['fecha'] !== '0000-00-00' ? $r['fecha'] : '';
                        ?>
                        <tr class="<?= $i === 0 ? 'fila-seleccionada' : '' ?>" data-id="<?= (int)$r['id'] ?>"
                            data-fecha="<?= htmlspecialchars($fechaRaw) ?>"
                            data-linea="<?= (int)$r['linea'] ?>"
                            data-articulo="<?= htmlspecialchars($r['articulo'] ?? '') ?>"
                            data-orden="<?= (int)$r['orden'] ?>"
                            data-cantidad="<?= (int)$r['cantidad'] ?>"
                            data-deposito="<?= htmlspecialchars($r['deposito'] ?? '') ?>"
                            data-vendida-a-id="<?= (int)($r['vendida_a_id'] ?? 0) ?>"
                            data-vendida-a-apellido="<?= htmlspecialchars($r['vendida_a_apellido'] ?? '') ?>"
                            data-fechavta="<?= !empty($r['fecha_vta']) && $r['fecha_vta'] !== '0000-00-00' ? $r['fecha_vta'] : '' ?>"
                            data-preciovta="<?= $r['precio_vta'] !== null && $r['precio_vta'] !== '' ? (float)$r['precio_vta'] : '' ?>"
                            data-cantvta="<?= (int)($r['cant_vta'] ?? 0) ?>"
                            data-operacion="<?= (int)($r['operacion'] ?? 0) ?>"
                            data-fechafact="<?= !empty($r['fecha_fact']) && $r['fecha_fact'] !== '0000-00-00' ? $r['fecha_fact'] : '' ?>"
                            data-cantfact="<?= (int)($r['cant_fact'] ?? 0) ?>"
                            data-facturada-a-id="<?= (int)($r['facturada_a_id'] ?? 0) ?>"
                            data-facturada-a-apellido="<?= htmlspecialchars($r['facturada_a_apellido'] ?? '') ?>"
                            data-preciofac="<?= $r['precio_fac'] !== null && $r['precio_fac'] !== '' ? (float)$r['precio_fac'] : '' ?>"
                            data-nfact="<?= htmlspecialchars($r['n_fact'] ?? '') ?>"
                            data-nremt="<?= htmlspecialchars($r['n_remt'] ?? '') ?>">
                            <td class="col-fecha"><?= htmlspecialchars(fmtFecha($r['fecha'])) ?></td>
                            <td class="col-l"><?= (int)$r['linea'] ?></td>
                            <td class="col-articulo"><?= htmlspecialchars($r['articulo']) ?></td>
                            <td class="col-orden"><?= (int)$r['orden'] ?></td>
                            <td class="col-cantidad"><?= (int)$r['cantidad'] ?></td>
                            <td class="col-deposito"><?= htmlspecialchars($r['deposito'] ?? '') ?></td>
                            <td class="col-operacion"><?= (int)($r['operacion'] ?? 0) ?: '' ?></td>
                            <td class="col-fechavta"><?= htmlspecialchars(fmtFecha($r['fecha_vta'])) ?></td>
                            <td class="col-cantvta"><?= (int)$r['cant_vta'] ?></td>
                            <td class="col-vendida <?= empty($r['vendida_a_apellido']) ? 'sin-dato' : '' ?>"><?php
                                $vid = (int)($r['vendida_a_id'] ?? 0);
                                $vnom = htmlspecialchars($r['vendida_a_apellido'] ?? '');
                                if ($vid > 0 && $vnom !== '') {
                                    echo '<a href="index.php?usuario_id=' . $vid . '" class="link-comprador" onclick="event.stopPropagation();">' . $vnom . '</a>';
                                } else {
                                    echo $vnom;
                                }
                            ?></td>
                            <td class="col-operador <?= empty($r['operador_apellido']) ? 'sin-dato' : '' ?>"><?= htmlspecialchars($r['operador_apellido'] ?? '') ?></td>
                            <td class="col-preciovta"><?= fmtNum($r['precio_vta']) ?></td>
                            <td class="col-fechafact"><?= htmlspecialchars(fmtFecha($r['fecha_fact'])) ?></td>
                            <td class="col-cantfact"><?= (int)$r['cant_fact'] ?></td>
                            <td class="col-facturada <?= empty($r['facturada_a_apellido']) ? 'sin-dato' : '' ?>"><?= htmlspecialchars($r['facturada_a_apellido'] ?? '') ?></td>
                            <td class="col-preciofac"><?= fmtNum($r['precio_fac']) ?></td>
                            <td class="col-nfact <?= empty($r['n_fact']) ? 'sin-dato' : '' ?>"><?= htmlspecialchars($r['n_fact'] ?? '') ?></td>
                            <td class="col-nremt <?= empty($r['n_remt']) ? 'sin-dato' : '' ?>"><?= htmlspecialchars($r['n_remt'] ?? '') ?></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        </div>

        <div id="modalAltaStock" class="modal-alta-overlay" onclick="if(event.target===this) cerrarModalAltaStock()">
            <div class="modal-alta" onclick="event.stopPropagation()">
                <h3 id="modalAltaTitulo">Alta stock – Nuevo registro</h3>
                <form method="post" action="gestionar_azucares.php" id="formAltaStock">
                    <input type="hidden" name="alta_stock" value="1">
                    <input type="hidden" name="id" id="alta_id" value="">
                    <div class="fila-modal">
                        <div class="campo campo-fecha">
                            <label>Fecha</label>
                            <input type="date" name="fecha" id="alta_fecha" required value="<?= date('Y-m-d') ?>">
                        </div>
                        <div class="campo campo-l">
                            <label>L</label>
                            <select name="linea" id="alta_linea">
                                <option value="1" selected>1</option>
                                <option value="2">2</option>
                            </select>
                        </div>
                        <div class="campo campo-orden">
                            <label>Orden</label>
                            <input type="number" name="orden" id="alta_orden" min="1" value="" placeholder="Orden" required>
                        </div>
                        <div class="campo campo-cantidad">
                            <label>Cantidad</label>
                            <input type="number" name="cantidad" id="alta_cantidad" min="1" step="1" value="" placeholder="Cant." required>
                        </div>
                    </div>
                    <div>
                        <label>Articulo (último cargado por defecto; elegir de la grilla o escribir)</label>
                        <input type="text" name="articulo" id="alta_articulo" list="list_articulos" value="<?= htmlspecialchars($ultimo_articulo) ?>" placeholder="Texto o elegir de la lista">
                        <datalist id="list_articulos">
                            <?php foreach ($articulos_unicos as $a): ?>
                            <option value="<?= htmlspecialchars($a) ?>">
                            <?php endforeach; ?>
                        </datalist>
                    </div>
                    <div>
                        <label>Deposito (último cargado por defecto; elegir de la grilla o escribir)</label>
                        <input type="text" name="deposito" id="alta_deposito" list="list_depositos" value="<?= htmlspecialchars($ultimo_deposito) ?>" placeholder="Texto o elegir de la lista">
                        <datalist id="list_depositos">
                            <?php foreach ($depositos_unicos as $d): ?>
                            <option value="<?= htmlspecialchars($d) ?>">
                            <?php endforeach; ?>
                        </datalist>
                    </div>
                    <div id="alta_mensaje_ok" class="msg-ok-modal" style="display: none; margin-bottom: 8px; padding: 8px; background: #d4edda; color: #155724; border-radius: 4px;"></div>
                    <div class="botones">
                        <button type="button" class="btn-guardar" id="alta_btn_guardar">Guardar</button>
                        <button type="button" class="btn-cerrar" onclick="cerrarModalAltaStock()">Cancelar</button>
                    </div>
                </form>
            </div>
        </div>

        <div id="modalVenta" class="modal-venta-overlay" onclick="if(event.target===this) cerrarModalVenta()">
            <div class="modal-venta" onclick="event.stopPropagation()">
                <h3 id="modalVentaTitulo">Registrar venta (cuenta, no caja)</h3>
                <form method="post" action="gestionar_azucares.php" id="formVenta">
                    <input type="hidden" name="guardar_venta_azucar" value="1">
                    <input type="hidden" name="stock_id" id="venta_stock_id" value="">
                    <input type="hidden" name="usuario_id" id="venta_usuario_id" value="">
                    <input type="hidden" name="operador_id" id="venta_operador_id" value="">
                    <div class="fila-buscar" style="position: relative; margin-bottom: 6px;">
                        <div style="flex: 1; position: relative;">
                            <label>Buscar usuario (cliente)</label>
                            <input type="text" id="venta_buscar_usuario" placeholder="Escribí apellido..." autocomplete="off">
                            <div id="venta_resultados_usuario" class="buscador-venta-resultados"></div>
                        </div>
                        <a href="registro.php?volver=<?= urlencode('gestionar_azucares.php?abrir_venta=1') ?>" target="_blank" class="btn-alta-usuario">Alta usuario</a>
                    </div>
                    <div class="fila-usuario-operador">
                        <div><label>Usuario seleccionado</label><div id="venta_usuario_nombre" class="campo-ro">—</div></div>
                        <div><label>Operador (intermediario)</label><div id="venta_operador_nombre" class="campo-ro">—</div></div>
                    </div>
                    <div class="fila-buscar fila-buscar-operador" style="position: relative; margin-bottom: 6px;">
                        <div style="flex: 1; position: relative;">
                            <label>Buscar operador</label>
                            <div style="display: flex; align-items: center; gap: 4px;">
                                <span style="font-size: 1em;" title="Buscar">🔍</span>
                                <input type="text" id="venta_buscar_operador" placeholder="Apellido..." autocomplete="off" style="flex: 1;">
                            </div>
                            <div id="venta_resultados_operador" class="buscador-venta-resultados"></div>
                        </div>
                    </div>
                    <div class="fila-venta-uno">
                        <div class="campo-fecha-vta"><label>Fecha venta</label><input type="date" name="fecha_vta" id="venta_fecha" required></div>
                        <div class="campo-linea"><label>Línea</label><div id="venta_linea" class="campo-ro">—</div></div>
                        <div class="campo-orden"><label>N° Orden</label><div id="venta_orden" class="campo-ro">—</div></div>
                        <div class="campo-cantidad"><label>Cantidad</label><div id="venta_cantidad" class="campo-ro">—</div></div>
                        <div class="campo-deposito"><label>Depósito</label><div id="venta_deposito" class="campo-ro">—</div></div>
                    </div>
                    <div style="margin-bottom: 6px;"><label>Artículo</label><div id="venta_articulo" class="campo-ro">—</div></div>
                    <div id="venta_cartel_parcial" class="cartel-parcial-venta" style="display: none;"></div>
                    <div class="fila-venta-dos">
                        <div class="form-g campo-precio-venta" style="flex: 0 0 auto;"><label for="venta_precio">Precio venta</label><input type="text" name="precio_venta" id="venta_precio" placeholder="0,00" autocomplete="off" required></div>
                        <div class="form-g" style="flex: 0 0 5em;"><label>Cant. vendida</label><input type="number" name="cantidad_vendida" id="venta_cant_vendida" min="1" step="1" required></div>
                        <div class="form-g" style="flex: 0 0 5em;"><label>Operación</label><input type="number" name="operacion" id="venta_operacion" min="1" step="1" placeholder="Auto"></div>
                        <div class="form-g campo-fecha-vta" style="flex: 0 0 auto;"><label>Fecha de Pago</label><input type="date" name="fecha_pago" id="venta_fecha_pago" title="Provisorio en fecha fact.; se sobrescribe al facturar"></div>
                    </div>
                    <div class="botones">
                        <button type="submit" class="btn-guardar-venta" id="venta_btn_guardar">Guardar venta</button>
                        <button type="button" class="btn-cerrar-venta" onclick="cerrarModalVenta()">Cancelar</button>
                    </div>
                </form>
            </div>
        </div>

        <div id="modalFactura" class="modal-venta-overlay" onclick="if(event.target===this) cerrarModalFactura()">
            <div class="modal-venta modal-factura" onclick="event.stopPropagation()">
                <h3 id="modalFacturaTitulo">Registrar factura</h3>
                <form method="post" action="gestionar_azucares.php" id="formFactura">
                    <input type="hidden" name="guardar_factura_azucar" value="1">
                    <input type="hidden" name="stock_id" id="factura_stock_id" value="">
                    <input type="hidden" name="usuario_id" id="factura_usuario_id" value="">
                    <div class="fila-buscar" style="position: relative;">
                        <div style="flex: 1; position: relative;">
                            <label>Buscar usuario (facturada a)</label>
                            <input type="text" id="factura_buscar_usuario" placeholder="Escribí apellido..." autocomplete="off">
                            <div id="factura_resultados_usuario" class="buscador-venta-resultados"></div>
                        </div>
                        <a href="registro.php?volver=<?= urlencode('gestionar_azucares.php') ?>" target="_blank" class="btn-alta-usuario">Alta usuario</a>
                    </div>
                    <div><label>Usuario seleccionado</label><div id="factura_usuario_nombre" class="campo-ro">—</div></div>
                    <div class="fila-venta-uno">
                        <div class="campo-fecha-vta"><label>Fecha factura</label><input type="date" name="fecha_fact" id="factura_fecha" required></div>
                        <div class="campo-linea"><label>Línea</label><div id="factura_linea" class="campo-ro">—</div></div>
                        <div class="campo-orden"><label>N° Orden</label><div id="factura_orden" class="campo-ro">—</div></div>
                        <div class="campo-cantidad"><label>Cantidad</label><div id="factura_cantidad" class="campo-ro">—</div></div>
                    </div>
                    <div><label>Artículo</label><div id="factura_articulo" class="campo-ro">—</div></div>
                    <div class="fila-factura-precios">
                        <div class="campo-precio-venta"><label for="factura_precio">Precio factura (con IVA)</label><input type="text" name="precio_fac" id="factura_precio" placeholder="0,00" autocomplete="off"></div>
                        <div class="campo-fact-sin-iva"><label>Precio sin IVA</label><div id="factura_precio_sin_iva" class="campo-ro">—</div></div>
                        <div class="campo-fact-cant"><label>Cantidad facturada</label><input type="number" name="cantidad_facturada" id="factura_cant_fact" min="1" step="1" required></div>
                        <div class="campo-fact-monto"><label>Monto final factura</label><div id="factura_monto_final" class="campo-ro">—</div></div>
                    </div>
                    <div><label>N° Factura</label><input type="text" name="n_fact" id="factura_n_fact" placeholder="Opcional"></div>
                    <div><label>N° Remito</label><input type="text" name="n_remt" id="factura_n_remt" placeholder="Opcional"></div>
                    <div class="botones">
                        <button type="submit" class="btn-guardar-venta" id="factura_btn_guardar">Guardar factura</button>
                        <button type="button" class="btn-cerrar-venta" onclick="cerrarModalFactura()">Cancelar</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Modal movimientos por operación -->
        <div id="modalMovimientosOperacion" class="modal-venta-overlay" onclick="if(event.target===this) cerrarModalMovimientosOperacion()">
            <div class="modal-venta" onclick="event.stopPropagation()" style="max-width: 90%; max-height: 90vh; overflow: auto;">
                <h3 id="modalMovimientosOperacionTitulo">Movimientos de pago - Operación N° <span id="modalOpNumero"></span></h3>
                <div style="margin-bottom: 15px;">
                    <table style="width: 100%; border-collapse: collapse; font-size: 11px;">
                        <thead>
                            <tr style="background: #007bff; color: white;">
                                <th class="al-cen" style="padding: 6px; border: 1px solid #0056b3;">Fecha</th>
                                <th class="al-izq" style="padding: 6px; border: 1px solid #0056b3;">Concepto</th>
                                <th class="al-cen" style="padding: 6px; border: 1px solid #0056b3;">Comprobante</th>
                                <th class="al-cen" style="padding: 6px; border: 1px solid #0056b3;">Referencia</th>
                                <th class="al-izq" style="padding: 6px; border: 1px solid #0056b3;">Usuario</th>
                                <th class="al-der" style="padding: 6px; border: 1px solid #0056b3; width: 150px; min-width: 150px;">Monto</th>
                                <th class="al-der" style="padding: 6px; border: 1px solid #0056b3; width: 150px; min-width: 150px;">Saldo</th>
                            </tr>
                        </thead>
                        <tbody id="tablaMovimientosOperacion">
                            <tr><td colspan="7" style="text-align:center; padding:30px; color:gray;">Cargando...</td></tr>
                        </tbody>
                    </table>
                </div>
                <!-- Formulario de nuevo cobro (oculto por defecto) -->
                <div id="formNuevoCobroOperacion" style="display:none; margin-top: 20px; padding: 15px; background: #f8f9fa; border-radius: 4px; border: 1px solid #dee2e6;">
                    <h4 style="margin: 0 0 15px 0; color: #007bff; font-size: 14px;">Nuevo cobro</h4>
                    <div id="msgCobroOperacion" style="display:none; margin-bottom: 10px; padding: 8px; border-radius: 4px;"></div>
                    <form id="formCobroOperacion" style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                        <input type="hidden" id="cobro_usuario_id" value="">
                        <input type="hidden" id="cobro_operacion" value="">
                        <div class="form-g">
                            <label for="cobro_fecha" style="display: block; margin-bottom: 4px; font-weight: bold; font-size: 11px;">Fecha</label>
                            <input type="date" id="cobro_fecha" required style="width: 100%; padding: 6px; border: 1px solid #ced4da; border-radius: 4px; font-size: 12px; box-sizing: border-box;">
                        </div>
                        <div class="form-g">
                            <label for="cobro_concepto" style="display: block; margin-bottom: 4px; font-weight: bold; font-size: 11px;">Concepto</label>
                            <input type="text" id="cobro_concepto" value="COBRO VTA AZUCAR" required style="width: 100%; padding: 6px; border: 1px solid #ced4da; border-radius: 4px; font-size: 12px; box-sizing: border-box;">
                        </div>
                        <div class="form-g">
                            <label for="cobro_comprobante" style="display: block; margin-bottom: 4px; font-weight: bold; font-size: 11px;">Comprobante</label>
                            <select id="cobro_comprobante" required style="width: 100%; padding: 6px; border: 1px solid #ced4da; border-radius: 4px; font-size: 12px; box-sizing: border-box;">
                                <option value="CHEQUE/ECHEQ" selected>CHEQUE/ECHEQ</option>
                                <option value="EFVO">EFVO</option>
                                <option value="BOLETA">BOLETA</option>
                                <option value="TRANSFERENCIA">TRANSFERENCIA</option>
                            </select>
                        </div>
                        <div class="form-g">
                            <label for="cobro_referencia" style="display: block; margin-bottom: 4px; font-weight: bold; font-size: 11px;">Referencia</label>
                            <input type="text" id="cobro_referencia" required style="width: 100%; padding: 6px; border: 1px solid #ced4da; border-radius: 4px; font-size: 12px; box-sizing: border-box;">
                        </div>
                        <div class="form-g" style="grid-column: 1 / -1;">
                            <label for="cobro_monto" style="display: block; margin-bottom: 4px; font-weight: bold; font-size: 11px;">Monto</label>
                            <input type="number" id="cobro_monto" step="0.01" min="0" required placeholder="0,00" style="width: 100%; padding: 6px; border: 1px solid #ced4da; border-radius: 4px; font-size: 12px; box-sizing: border-box;">
                        </div>
                        <div class="form-g" style="grid-column: 1 / -1; display: flex; gap: 10px; margin-top: 5px;">
                            <button type="button" class="btn-guardar-venta" id="btnGuardarCobroOperacion" style="flex: 1;">Guardar</button>
                            <button type="button" class="btn-cerrar-venta" id="btnCancelarCobroOperacion" style="flex: 1;">Cancelar</button>
                        </div>
                    </form>
                </div>
                <div class="botones">
                    <button type="button" class="btn-guardar-venta" id="btnNuevoCobroOperacion" style="display:none;">Nuevo cobro</button>
                    <button type="button" class="btn-cerrar-venta" onclick="cerrarModalMovimientosOperacion()">Cerrar</button>
                </div>
            </div>
        </div>

        <p class="volver">
            <a href="index.php" class="btn btn-secondary">← Volver al panel</a>
        </p>
    </div>

    <script>
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' || e.keyCode === 27 || e.which === 27) {
            var modalVenta = document.getElementById('modalVenta');
            var modalFactura = document.getElementById('modalFactura');
            var modalMovOp = document.getElementById('modalMovimientosOperacion');
            var modalAlta = document.getElementById('modalAltaStock');
            if (modalMovOp && modalMovOp.classList.contains('activo')) {
                cerrarModalMovimientosOperacion();
                e.preventDefault();
                return;
            }
            if (modalVenta && modalVenta.classList.contains('activo')) {
                cerrarModalVenta();
                e.preventDefault();
                return;
            }
            if (modalFactura && modalFactura.classList.contains('activo')) {
                cerrarModalFactura();
                e.preventDefault();
                return;
            }
            if (modalAlta && modalAlta.classList.contains('activo')) {
                cerrarModalAltaStock();
                e.preventDefault();
                return;
            }
            if (history.length > 1) {
                history.back();
            } else {
                window.location.href = 'index.php';
            }
            e.preventDefault();
        }
    });
    document.getElementById('btnAltaStock').addEventListener('click', function() {
        document.getElementById('alta_id').value = '';
        document.getElementById('modalAltaTitulo').textContent = 'Alta stock – Nuevo registro';
        document.getElementById('alta_fecha').value = '<?= date('Y-m-d') ?>';
        document.getElementById('alta_linea').value = '1';
        document.getElementById('alta_orden').value = '';
        document.getElementById('alta_cantidad').value = '<?= $ultima_cantidad ?>';
        document.getElementById('alta_articulo').value = '<?= htmlspecialchars($ultimo_articulo, ENT_QUOTES, 'UTF-8') ?>';
        document.getElementById('alta_deposito').value = '<?= htmlspecialchars($ultimo_deposito, ENT_QUOTES, 'UTF-8') ?>';
        var m = document.getElementById('alta_mensaje_ok');
        if (m) m.style.display = 'none';
        document.getElementById('modalAltaStock').classList.add('activo');
    });
    function abrirFormularioEditarStock(tr) {
        if (!tr || !tr.dataset.id) return false;
        document.getElementById('alta_id').value = tr.dataset.id;
        document.getElementById('modalAltaTitulo').textContent = 'Editar registro de stock';
        document.getElementById('alta_fecha').value = tr.dataset.fecha || '';
        document.getElementById('alta_linea').value = (tr.dataset.linea === '2') ? '2' : '1';
        document.getElementById('alta_articulo').value = tr.dataset.articulo || '';
        document.getElementById('alta_orden').value = tr.dataset.orden || '';
        document.getElementById('alta_cantidad').value = tr.dataset.cantidad || '';
        document.getElementById('alta_deposito').value = tr.dataset.deposito || '';
        document.getElementById('modalAltaStock').classList.add('activo');
        return true;
    }
    document.getElementById('btnEditarStock').addEventListener('click', function() {
        var tr = document.querySelector('.tabla-azucar tbody tr.fila-seleccionada[data-id]');
        if (!tr) {
            alert('Seleccioná un registro de la grilla para editar (solo se editan los datos de carga de stock).');
            return;
        }
        abrirFormularioEditarStock(tr);
    });
    document.getElementById('btnEliminarStock').addEventListener('click', function() {
        var tr = document.querySelector('.tabla-azucar tbody tr.fila-seleccionada[data-id]');
        if (!tr) {
            alert('Seleccioná un registro de la grilla para eliminar.');
            return;
        }
        if (!confirm('¿Eliminar este registro de carga de stock?')) return;
        document.getElementById('eliminar_id').value = tr.dataset.id;
        document.getElementById('formEliminarStock').submit();
    });

    function cerrarModalVenta() {
        document.getElementById('modalVenta').classList.remove('activo');
    }
    function abrirModalVenta(esEdicion) {
        var tr = document.querySelector('.tabla-azucar tbody tr.fila-seleccionada[data-id]');
        if (!tr) {
            alert('Seleccioná un registro de la grilla.');
            return;
        }
        var form = document.getElementById('formVenta');
        var hGuardar = form.querySelector('input[name="guardar_venta_azucar"]');
        var hEditar = form.querySelector('input[name="editar_venta_azucar"]');
        if (hEditar) hEditar.remove();
        if (!hGuardar) {
            var hi = document.createElement('input');
            hi.type = 'hidden';
            hi.name = 'guardar_venta_azucar';
            hi.value = '1';
            hi.id = 'venta_h_guardar';
            form.insertBefore(hi, form.firstChild);
        }
        document.getElementById('venta_stock_id').value = tr.dataset.id;
        document.getElementById('venta_articulo').textContent = tr.dataset.articulo || '—';
        document.getElementById('venta_linea').textContent = tr.dataset.linea || '—';
        document.getElementById('venta_orden').textContent = tr.dataset.orden || '—';
        document.getElementById('venta_cantidad').textContent = tr.dataset.cantidad || '—';
        document.getElementById('venta_deposito').textContent = tr.dataset.deposito || '—';
        var cartelParcial = document.getElementById('venta_cartel_parcial');
        var cantTotal = parseInt(tr.dataset.cantidad || 0, 10);
        var cantVendida = parseInt(tr.dataset.cantvta || 0, 10);
        var restante = cantTotal - cantVendida;
        if (!esEdicion && cantVendida > 0 && restante > 0) {
            cartelParcial.textContent = 'Quedan ' + restante + ' unidades.';
            cartelParcial.style.display = 'block';
            document.getElementById('venta_cant_vendida').value = restante;
        } else {
            cartelParcial.style.display = 'none';
            if (!esEdicion) document.getElementById('venta_cant_vendida').value = tr.dataset.cantidad || '1';
        }
        if (esEdicion && tr.dataset.fechavta) {
            document.getElementById('modalVentaTitulo').textContent = 'Editar venta';
            document.getElementById('venta_btn_guardar').textContent = 'Actualizar venta';
            document.getElementById('venta_fecha').value = tr.dataset.fechavta || '';
            document.getElementById('venta_precio').value = tr.dataset.preciovta || '';
            document.getElementById('venta_cant_vendida').value = tr.dataset.cantvta || '1';
            document.getElementById('venta_usuario_id').value = tr.dataset.vendidaAId || '';
            document.getElementById('venta_usuario_nombre').textContent = tr.dataset.vendidaAApellido || '—';
            document.getElementById('venta_buscar_usuario').value = tr.dataset.vendidaAApellido || '';
            document.getElementById('venta_operador_id').value = tr.dataset.operadorId || '';
            document.getElementById('venta_operador_nombre').textContent = tr.dataset.operadorApellido || '—';
            document.getElementById('venta_buscar_operador').value = tr.dataset.operadorApellido || '';
            document.getElementById('venta_operacion').value = tr.dataset.operacion || '';
            var facturadaId = parseInt(tr.dataset.facturadaAId || '0', 10);
            document.getElementById('venta_fecha_pago').value = (facturadaId === 0 && tr.dataset.fechafact) ? tr.dataset.fechafact : '';
            if (hGuardar) hGuardar.remove();
            var he = document.createElement('input');
            he.type = 'hidden';
            he.name = 'editar_venta_azucar';
            he.value = '1';
            form.insertBefore(he, form.firstChild);
        } else {
            document.getElementById('modalVentaTitulo').textContent = 'Registrar venta (cuenta, no caja)';
            document.getElementById('venta_btn_guardar').textContent = 'Guardar venta';
            document.getElementById('venta_fecha').value = '<?= date('Y-m-d') ?>';
            document.getElementById('venta_precio').value = '';
            document.getElementById('venta_cant_vendida').value = (cantVendida > 0 && restante > 0) ? restante : (tr.dataset.cantidad || '1');
            document.getElementById('venta_usuario_id').value = '';
            document.getElementById('venta_usuario_nombre').textContent = '—';
            document.getElementById('venta_buscar_usuario').value = '';
            document.getElementById('venta_operacion').value = '';
            document.getElementById('venta_fecha_pago').value = '';
        }
        document.getElementById('modalVenta').classList.add('activo');
    }
    document.getElementById('btnVenta').addEventListener('click', function() { abrirModalVenta(false); });
    document.getElementById('btnEditarVta').addEventListener('click', function() { abrirModalVenta(true); });
    document.getElementById('btnEliminarVta').addEventListener('click', function() {
        var tr = document.querySelector('.tabla-azucar tbody tr.fila-seleccionada[data-id]');
        if (!tr) { alert('Seleccioná un registro de la grilla.'); return; }
        if (!confirm('¿Eliminar la venta de este registro? Se quitará el movimiento de la cuenta.')) return;
        document.getElementById('eliminar_vta_id').value = tr.dataset.id;
        document.getElementById('formEliminarVta').submit();
    });

    function cerrarModalFactura() {
        document.getElementById('modalFactura').classList.remove('activo');
    }
    function abrirModalFactura(esEdicion) {
        var tr = document.querySelector('.tabla-azucar tbody tr.fila-seleccionada[data-id]');
        if (!tr) {
            alert('Seleccioná un registro de la grilla.');
            return;
        }
        var form = document.getElementById('formFactura');
        var hGuardar = form.querySelector('input[name="guardar_factura_azucar"]');
        var hEditar = form.querySelector('input[name="editar_factura_azucar"]');
        if (hEditar) hEditar.remove();
        if (!hGuardar) {
            var hi = document.createElement('input');
            hi.type = 'hidden';
            hi.name = 'guardar_factura_azucar';
            hi.value = '1';
            form.insertBefore(hi, form.firstChild);
        }
        document.getElementById('factura_stock_id').value = tr.dataset.id;
        document.getElementById('factura_articulo').textContent = tr.dataset.articulo || '—';
        document.getElementById('factura_linea').textContent = tr.dataset.linea || '—';
        document.getElementById('factura_orden').textContent = tr.dataset.orden || '—';
        document.getElementById('factura_cantidad').textContent = tr.dataset.cantidad || '—';
        if (esEdicion && tr.dataset.fechafact) {
            document.getElementById('modalFacturaTitulo').textContent = 'Editar factura';
            document.getElementById('factura_btn_guardar').textContent = 'Actualizar factura';
            document.getElementById('factura_fecha').value = tr.dataset.fechafact || '';
            document.getElementById('factura_precio').value = tr.dataset.preciofac || '';
            document.getElementById('factura_cant_fact').value = tr.dataset.cantfact || '1';
            document.getElementById('factura_usuario_id').value = tr.dataset.facturadaAId || '';
            document.getElementById('factura_usuario_nombre').textContent = tr.dataset.facturadaAApellido || '—';
            document.getElementById('factura_buscar_usuario').value = tr.dataset.facturadaAApellido || '';
            document.getElementById('factura_n_fact').value = tr.dataset.nfact || '';
            document.getElementById('factura_n_remt').value = tr.dataset.nremt || '';
            if (hGuardar) hGuardar.remove();
            var he = document.createElement('input');
            he.type = 'hidden';
            he.name = 'editar_factura_azucar';
            he.value = '1';
            form.insertBefore(he, form.firstChild);
        } else {
            document.getElementById('modalFacturaTitulo').textContent = 'Registrar factura';
            document.getElementById('factura_btn_guardar').textContent = 'Guardar factura';
            document.getElementById('factura_fecha').value = '<?= date('Y-m-d') ?>';
            document.getElementById('factura_precio').value = (tr.dataset.preciovta !== undefined && tr.dataset.preciovta !== '') ? String(tr.dataset.preciovta).replace('.', ',') : '';
            document.getElementById('factura_cant_fact').value = tr.dataset.cantvta || tr.dataset.cantidad || '1';
            document.getElementById('factura_usuario_id').value = tr.dataset.vendidaAId || '';
            document.getElementById('factura_usuario_nombre').textContent = tr.dataset.vendidaAApellido || '—';
            document.getElementById('factura_buscar_usuario').value = tr.dataset.vendidaAApellido || '';
            document.getElementById('factura_n_fact').value = '';
            document.getElementById('factura_n_remt').value = '';
        }
        actualizarFacturaPrecios();
        document.getElementById('modalFactura').classList.add('activo');
    }
    function actualizarFacturaPrecios() {
        var precioStr = (document.getElementById('factura_precio').value || '').replace(',', '.').trim();
        var precio = parseFloat(precioStr) || 0;
        var cant = parseInt(document.getElementById('factura_cant_fact').value, 10) || 0;
        var sinIva = precio / 1.21;
        var montoFinal = precio * cant;
        var fmt = function(n) { return (typeof n === 'number' && !isNaN(n)) ? n.toLocaleString('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) : '—'; };
        document.getElementById('factura_precio_sin_iva').textContent = fmt(sinIva);
        document.getElementById('factura_monto_final').textContent = fmt(montoFinal);
    }
    document.getElementById('btnFacturar').addEventListener('click', function() { abrirModalFactura(false); });
    document.getElementById('btnEditarFact').addEventListener('click', function() { abrirModalFactura(true); });
    document.getElementById('btnEliminarFact').addEventListener('click', function() {
        var tr = document.querySelector('.tabla-azucar tbody tr.fila-seleccionada[data-id]');
        if (!tr) { alert('Seleccioná un registro de la grilla.'); return; }
        if (!confirm('¿Eliminar la factura de este registro?')) return;
        document.getElementById('eliminar_factura_id').value = tr.dataset.id;
        document.getElementById('formEliminarFactura').submit();
    });

    var ventaBuscarTimer;
    document.getElementById('venta_buscar_usuario').addEventListener('input', function() {
        var q = this.value.trim();
        var resEl = document.getElementById('venta_resultados_usuario');
        resEl.style.display = 'none';
        resEl.innerHTML = '';
        if (q.length < 2) return;
        clearTimeout(ventaBuscarTimer);
        ventaBuscarTimer = setTimeout(function() {
            fetch('buscar_personas.php?q=' + encodeURIComponent(q))
                .then(function(r) { return r.json(); })
                .then(function(arr) {
                    resEl.innerHTML = '';
                    if (arr.length === 0) { resEl.innerHTML = '<div style="padding:8px;color:#666;">Sin resultados</div>'; }
                    else arr.forEach(function(u) {
                        var div = document.createElement('div');
                        div.className = 'item-venta';
                        div.textContent = u.apellido;
                        div.dataset.id = u.id;
                        div.dataset.apellido = u.apellido;
                        div.addEventListener('click', function() {
                            document.getElementById('venta_usuario_id').value = this.dataset.id;
                            document.getElementById('venta_usuario_nombre').textContent = this.dataset.apellido;
                            document.getElementById('venta_buscar_usuario').value = this.dataset.apellido;
                            resEl.style.display = 'none';
                            resEl.innerHTML = '';
                        });
                        resEl.appendChild(div);
                    });
                    resEl.style.display = 'block';
                });
        }, 200);
    });
    document.getElementById('venta_buscar_usuario').addEventListener('focus', function() {
        if (this.value.trim().length >= 2 && document.getElementById('venta_resultados_usuario').children.length) document.getElementById('venta_resultados_usuario').style.display = 'block';
    });
    document.addEventListener('click', function() {
        document.getElementById('venta_resultados_usuario').style.display = 'none';
        document.getElementById('venta_resultados_operador').style.display = 'none';
        var fel = document.getElementById('factura_resultados_usuario');
        if (fel) fel.style.display = 'none';
    });
    document.getElementById('venta_resultados_usuario').addEventListener('click', function(e) { e.stopPropagation(); });
    document.getElementById('venta_resultados_operador').addEventListener('click', function(e) { e.stopPropagation(); });

    var ventaBuscarOperadorTimer;
    document.getElementById('venta_buscar_operador').addEventListener('input', function() {
        var q = this.value.trim();
        var resEl = document.getElementById('venta_resultados_operador');
        resEl.style.display = 'none';
        resEl.innerHTML = '';
        if (q.length < 2) return;
        clearTimeout(ventaBuscarOperadorTimer);
        ventaBuscarOperadorTimer = setTimeout(function() {
            fetch('buscar_personas.php?q=' + encodeURIComponent(q))
                .then(function(r) { return r.json(); })
                .then(function(arr) {
                    resEl.innerHTML = '';
                    if (arr.length === 0) { resEl.innerHTML = '<div style="padding:8px;color:#666;">Sin resultados</div>'; }
                    else arr.forEach(function(u) {
                        var div = document.createElement('div');
                        div.className = 'item-venta';
                        div.textContent = u.apellido;
                        div.dataset.id = u.id;
                        div.dataset.apellido = u.apellido;
                        div.addEventListener('click', function() {
                            document.getElementById('venta_operador_id').value = this.dataset.id;
                            document.getElementById('venta_operador_nombre').textContent = this.dataset.apellido;
                            document.getElementById('venta_buscar_operador').value = this.dataset.apellido;
                            resEl.style.display = 'none';
                            resEl.innerHTML = '';
                        });
                        resEl.appendChild(div);
                    });
                    resEl.style.display = 'block';
                });
        }, 200);
    });
    document.getElementById('venta_buscar_operador').addEventListener('focus', function() {
        if (this.value.trim().length >= 2 && document.getElementById('venta_resultados_operador').children.length) document.getElementById('venta_resultados_operador').style.display = 'block';
    });

    var facturaBuscarTimer;
    document.getElementById('factura_buscar_usuario').addEventListener('input', function() {
        var q = this.value.trim();
        var resEl = document.getElementById('factura_resultados_usuario');
        resEl.style.display = 'none';
        resEl.innerHTML = '';
        if (q.length < 2) return;
        clearTimeout(facturaBuscarTimer);
        facturaBuscarTimer = setTimeout(function() {
            fetch('buscar_personas.php?q=' + encodeURIComponent(q))
                .then(function(r) { return r.json(); })
                .then(function(arr) {
                    resEl.innerHTML = '';
                    if (arr.length === 0) { resEl.innerHTML = '<div style="padding:8px;color:#666;">Sin resultados</div>'; }
                    else arr.forEach(function(u) {
                        var div = document.createElement('div');
                        div.className = 'item-venta';
                        div.textContent = u.apellido;
                        div.dataset.id = u.id;
                        div.dataset.apellido = u.apellido;
                        div.addEventListener('click', function() {
                            document.getElementById('factura_usuario_id').value = this.dataset.id;
                            document.getElementById('factura_usuario_nombre').textContent = this.dataset.apellido;
                            document.getElementById('factura_buscar_usuario').value = this.dataset.apellido;
                            resEl.style.display = 'none';
                            resEl.innerHTML = '';
                        });
                        resEl.appendChild(div);
                    });
                    resEl.style.display = 'block';
                });
        }, 200);
    });
    document.getElementById('factura_buscar_usuario').addEventListener('focus', function() {
        if (this.value.trim().length >= 2 && document.getElementById('factura_resultados_usuario').children.length) document.getElementById('factura_resultados_usuario').style.display = 'block';
    });
    var facturaResEl = document.getElementById('factura_resultados_usuario');
    if (facturaResEl) facturaResEl.addEventListener('click', function(e) { e.stopPropagation(); });

    (function() {
        var precioEl = document.getElementById('venta_precio');
        if (!precioEl) return;
        precioEl.removeAttribute('readonly');
        precioEl.removeAttribute('disabled');
        precioEl.addEventListener('focus', function() {
            this.removeAttribute('readonly');
            this.removeAttribute('disabled');
        });
        precioEl.addEventListener('input', function() {
            var v = this.value.replace(/[^\d,.]/g, '');
            var idxSep = v.search(/[,.]/);
            if (idxSep >= 0) {
                var sep = v.charAt(idxSep);
                var after = v.substring(idxSep + 1).replace(/[^0-9]/g, '').substring(0, 2);
                v = v.substring(0, idxSep) + sep + after;
            }
            this.value = v;
        });
    })();
    (function() {
        var precioEl = document.getElementById('factura_precio');
        if (!precioEl) return;
        precioEl.addEventListener('input', function() {
            var v = this.value.replace(/[^\d,.]/g, '');
            var idxSep = v.search(/[,.]/);
            if (idxSep >= 0) {
                var sep = v.charAt(idxSep);
                var after = v.substring(idxSep + 1).replace(/[^0-9]/g, '').substring(0, 2);
                v = v.substring(0, idxSep) + sep + after;
            }
            this.value = v;
            if (typeof actualizarFacturaPrecios === 'function') actualizarFacturaPrecios();
        });
        var cantFactEl = document.getElementById('factura_cant_fact');
        if (cantFactEl) {
            cantFactEl.addEventListener('input', function() { if (typeof actualizarFacturaPrecios === 'function') actualizarFacturaPrecios(); });
            cantFactEl.addEventListener('change', function() { if (typeof actualizarFacturaPrecios === 'function') actualizarFacturaPrecios(); });
        }
    })();

    var camposVentaOrden = ['venta_buscar_usuario', 'venta_fecha', 'venta_precio', 'venta_cant_vendida', 'venta_operacion', 'venta_fecha_pago', 'venta_btn_guardar'];
    document.getElementById('modalVenta').addEventListener('keydown', function(e) {
        if (e.key !== 'Enter') return;
        var form = document.getElementById('formVenta');
        if (!form || !form.contains(document.activeElement)) return;
        e.preventDefault();
        var id = document.activeElement.id;
        var idx = camposVentaOrden.indexOf(id);
        if (idx === -1) return;
        if (idx < camposVentaOrden.length - 1) {
            document.getElementById(camposVentaOrden[idx + 1]).focus();
        } else {
            if (id === 'venta_btn_guardar' && form.checkValidity()) form.submit();
        }
    });

    function cerrarModalAltaStock() {
        document.getElementById('modalAltaStock').classList.remove('activo');
    }
    function seleccionarAlFoco(input) {
        if (!input) return;
        input.addEventListener('focus', function() {
            var el = this;
            setTimeout(function() {
                el.select();
            }, 0);
        });
    }
    seleccionarAlFoco(document.getElementById('alta_orden'));
    seleccionarAlFoco(document.getElementById('alta_cantidad'));

    var camposAltaOrden = ['alta_fecha', 'alta_linea', 'alta_orden', 'alta_cantidad', 'alta_articulo', 'alta_deposito', 'alta_btn_guardar'];
    document.getElementById('modalAltaStock').addEventListener('keydown', function(e) {
        if (e.key !== 'Enter') return;
        var form = document.querySelector('.modal-alta form');
        if (!form || !form.contains(document.activeElement)) return;
        e.preventDefault();
        var id = document.activeElement.id;
        var idx = camposAltaOrden.indexOf(id);
        if (idx === -1) return;
        if (idx < camposAltaOrden.length - 1) {
            document.getElementById(camposAltaOrden[idx + 1]).focus();
        } else {
            if (id === 'alta_btn_guardar') {
                if (form.checkValidity() && typeof ejecutarGuardarAltaStock === 'function') ejecutarGuardarAltaStock();
            }
        }
    });

    // Alta stock: Guardar por AJAX si es nuevo (no se cierra el formulario); si es edición, submit normal
    (function() {
        var formAlta = document.getElementById('formAltaStock');
        var altaId = document.getElementById('alta_id');
        var msgOk = document.getElementById('alta_mensaje_ok');
        if (!formAlta || !altaId) return;

        function mostrarMensaje(texto, esError) {
            msgOk.textContent = texto;
            msgOk.style.background = esError ? '#f8d7da' : '#d4edda';
            msgOk.style.color = esError ? '#721c24' : '#155724';
            msgOk.style.display = 'block';
        }
        function ocultarMensaje() {
            msgOk.style.display = 'none';
        }

        window.ejecutarGuardarAltaStock = function() {
            var esNuevo = !altaId.value || altaId.value === '0';
            if (esNuevo) {
                if (!formAlta.checkValidity()) {
                    formAlta.reportValidity();
                    return;
                }
                ocultarMensaje();
                var formData = new FormData(formAlta);
                formData.append('alta_ajax', '1');
                var xhr = new XMLHttpRequest();
                xhr.open('POST', 'gestionar_azucares.php');
                xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
                xhr.onload = function() {
                    try {
                        var data = JSON.parse(xhr.responseText);
                        if (data.ok) {
                            mostrarMensaje('Se guardó con éxito.', false);
                            // Agregar nueva fila a la grilla si viene en la respuesta
                            if (data.fila_html) {
                                var tbody = document.querySelector('.tabla-azucar tbody');
                                if (!tbody) {
                                    console.error('No se encontró tbody de tabla-azucar');
                                } else {
                                    // Si hay mensaje de "no hay registros", quitarlo
                                    var trs = tbody.querySelectorAll('tr');
                                    if (trs.length === 1) {
                                        var trVacio = trs[0];
                                        if (trVacio.cells && trVacio.cells.length === 1 && trVacio.cells[0].colSpan > 1) {
                                            tbody.innerHTML = '';
                                        }
                                    }
                                    // Crear elemento temporal para parsear el HTML
                                    var temp = document.createElement('table');
                                    temp.innerHTML = '<tbody>' + data.fila_html + '</tbody>';
                                    var nuevaFila = temp.querySelector('tbody tr');
                                    if (nuevaFila) {
                                        var nuevoId = nuevaFila.getAttribute('data-id');
                                        // Quitar selección de otras filas
                                        document.querySelectorAll('.tabla-azucar tbody tr.fila-seleccionada').forEach(function(r) {
                                            r.classList.remove('fila-seleccionada');
                                        });
                                        // Agregar nueva fila al tbody
                                        tbody.appendChild(nuevaFila);
                                        // Reordenar TODA la tabla según: orden ASC, fecha DESC, id DESC
                                        var filas = Array.from(tbody.querySelectorAll('tr'));
                                        filas.sort(function(a, b) {
                                            var ordenA = parseInt(a.getAttribute('data-orden') || '0');
                                            var ordenB = parseInt(b.getAttribute('data-orden') || '0');
                                            if (ordenA !== ordenB) {
                                                return ordenA - ordenB; // ASC
                                            }
                                            var fechaA = a.getAttribute('data-fecha') || '';
                                            var fechaB = b.getAttribute('data-fecha') || '';
                                            if (fechaA !== fechaB) {
                                                return fechaB.localeCompare(fechaA); // DESC (más reciente primero)
                                            }
                                            var idA = parseInt(a.getAttribute('data-id') || '0');
                                            var idB = parseInt(b.getAttribute('data-id') || '0');
                                            return idB - idA; // DESC (mayor id primero)
                                        });
                                        // Reinsertar filas ordenadas
                                        filas.forEach(function(fila) {
                                            tbody.appendChild(fila);
                                        });
                                        // Seleccionar la nueva fila y hacer scroll
                                        nuevaFila.classList.add('fila-seleccionada');
                                        setTimeout(function() {
                                            nuevaFila.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                                        }, 100);
                                    } else {
                                        console.error('No se pudo parsear la fila HTML');
                                    }
                                }
                            }
                            altaId.value = '';
                            document.getElementById('alta_fecha').value = data.fecha || '';
                            document.getElementById('alta_linea').value = String(data.linea || 1);
                            document.getElementById('alta_articulo').value = data.articulo || '';
                            document.getElementById('alta_orden').value = String((data.orden || 0) + 1);
                            document.getElementById('alta_cantidad').value = String(data.cantidad || '');
                            document.getElementById('alta_deposito').value = data.deposito || '';
                            document.getElementById('alta_orden').focus();
                            setTimeout(ocultarMensaje, 3000);
                        } else {
                            mostrarMensaje(data.mensaje || 'Falta dato o corregir.', true);
                        }
                    } catch (err) {
                        mostrarMensaje('Error al guardar.', true);
                    }
                };
                xhr.onerror = function() {
                    mostrarMensaje('Error de conexión.', true);
                };
                xhr.send(formData);
            } else {
                formAlta.submit();
            }
        };

        document.getElementById('alta_btn_guardar').addEventListener('click', function() {
            if (formAlta.checkValidity() && typeof ejecutarGuardarAltaStock === 'function') ejecutarGuardarAltaStock();
        });
    })();

    function fmtNum(n) {
        if (n === null || n === undefined || n === '') return '0';
        var x = parseFloat(n);
        return isNaN(x) ? '0' : x.toLocaleString('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }
    function esc(s) {
        var d = document.createElement('div');
        d.textContent = s == null ? '' : String(s);
        return d.innerHTML;
    }

    // Abrir modal de movimientos por operación al hacer click en columna OP
    function abrirModalMovimientosOperacion(operacion) {
        if (!operacion || operacion === '0' || operacion === '') {
            alert('No hay operación asociada.');
            return;
        }
        document.getElementById('modalOpNumero').textContent = operacion;
        document.getElementById('modalMovimientosOperacion').classList.add('activo');
        // Ocultar formulario de cobro al abrir el modal
        var formCobro = document.getElementById('formNuevoCobroOperacion');
        if (formCobro) {
            formCobro.style.display = 'none';
        }
        var tbody = document.getElementById('tablaMovimientosOperacion');
        tbody.innerHTML = '<tr><td colspan="7" style="text-align:center; padding:30px; color:gray;">Cargando...</td></tr>';
        
        fetch('obtener_movimientos_operacion.php?operacion=' + encodeURIComponent(operacion))
            .then(function(response) {
                if (!response.ok) throw new Error('Error al cargar movimientos');
                return response.text();
            })
            .then(function(html) {
                tbody.innerHTML = html;
                // Extraer usuario_id del comentario HTML si existe
                var match = html.match(/<!-- DATA:usuario_id=(\d+) -->/);
                var usuarioId = match ? parseInt(match[1]) : null;
                var btnNuevoCobro = document.getElementById('btnNuevoCobroOperacion');
                var formCobro = document.getElementById('formNuevoCobroOperacion');
                if (btnNuevoCobro && usuarioId) {
                    btnNuevoCobro.style.display = 'inline-block';
                    // Guardar datos de la operación para usar en el formulario
                    btnNuevoCobro.dataset.usuarioId = usuarioId;
                    btnNuevoCobro.dataset.operacion = operacion;
                    btnNuevoCobro.onclick = function() {
                        // Mostrar formulario y prellenar campos
                        if (formCobro) {
                            formCobro.style.display = 'block';
                            document.getElementById('cobro_usuario_id').value = usuarioId;
                            document.getElementById('cobro_operacion').value = operacion;
                            document.getElementById('cobro_fecha').value = new Date().toISOString().split('T')[0];
                            document.getElementById('cobro_concepto').value = 'COBRO VTA AZUCAR';
                            document.getElementById('cobro_comprobante').value = 'CHEQUE/ECHEQ';
                            document.getElementById('cobro_referencia').value = 'OP N° ' + operacion;
                            document.getElementById('cobro_monto').value = '';
                            document.getElementById('msgCobroOperacion').style.display = 'none';
                            // Scroll al formulario
                            formCobro.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                            setTimeout(function() { document.getElementById('cobro_monto').focus(); }, 100);
                        }
                    };
                } else if (btnNuevoCobro) {
                    btnNuevoCobro.style.display = 'none';
                }
            })
            .catch(function(error) {
                tbody.innerHTML = '<tr><td colspan="7" style="text-align:center; padding:30px; color:red;">Error al cargar movimientos: ' + esc(error.message) + '</td></tr>';
            });
    }

    function cerrarModalMovimientosOperacion() {
        document.getElementById('modalMovimientosOperacion').classList.remove('activo');
        // Ocultar formulario de cobro si está visible
        var formCobro = document.getElementById('formNuevoCobroOperacion');
        if (formCobro) {
            formCobro.style.display = 'none';
        }
    }

    // Función para guardar nuevo cobro desde el modal de operación
    function guardarCobroOperacion() {
        var usuarioId = document.getElementById('cobro_usuario_id').value;
        var fecha = document.getElementById('cobro_fecha').value;
        var concepto = document.getElementById('cobro_concepto').value.trim();
        var comprobante = document.getElementById('cobro_comprobante').value;
        var referencia = document.getElementById('cobro_referencia').value.trim();
        var monto = parseFloat(document.getElementById('cobro_monto').value) || 0;
        var operacion = document.getElementById('cobro_operacion').value;
        var msgEl = document.getElementById('msgCobroOperacion');

        // Validaciones
        if (!fecha || !concepto || !comprobante || !referencia || monto <= 0) {
            msgEl.textContent = 'Por favor complete todos los campos correctamente.';
            msgEl.style.display = 'block';
            msgEl.style.background = '#f8d7da';
            msgEl.style.color = '#721c24';
            return;
        }

        // Deshabilitar botón mientras se guarda
        var btnGuardar = document.getElementById('btnGuardarCobroOperacion');
        btnGuardar.disabled = true;
        btnGuardar.textContent = 'Guardando...';

        // Preparar datos para enviar
        var formData = new FormData();
        formData.append('id', usuarioId);
        formData.append('fecha', fecha);
        formData.append('concepto', concepto);
        formData.append('compro', comprobante);
        formData.append('refer', referencia);
        formData.append('monto', monto);

        // Enviar vía AJAX
        fetch('guardar_movimiento.php', {
            method: 'POST',
            body: formData
        })
        .then(function(response) {
            return response.text();
        })
        .then(function(result) {
            btnGuardar.disabled = false;
            btnGuardar.textContent = 'Guardar';
            
            if (result === 'OK' || result === 'OK_CAJA') {
                // Mostrar mensaje de éxito
                msgEl.textContent = 'Cobro guardado con éxito.';
                msgEl.style.display = 'block';
                msgEl.style.background = '#d4edda';
                msgEl.style.color = '#155724';
                
                // Limpiar formulario
                document.getElementById('cobro_monto').value = '';
                
                // Ocultar formulario después de 1 segundo
                setTimeout(function() {
                    document.getElementById('formNuevoCobroOperacion').style.display = 'none';
                    msgEl.style.display = 'none';
                }, 1000);
                
                // Recargar movimientos de la operación
                abrirModalMovimientosOperacion(operacion);
            } else {
                // Mostrar error
                msgEl.textContent = 'Error al guardar: ' + esc(result);
                msgEl.style.display = 'block';
                msgEl.style.background = '#f8d7da';
                msgEl.style.color = '#721c24';
            }
        })
        .catch(function(error) {
            btnGuardar.disabled = false;
            btnGuardar.textContent = 'Guardar';
            msgEl.textContent = 'Error de conexión: ' + esc(error.message);
            msgEl.style.display = 'block';
            msgEl.style.background = '#f8d7da';
            msgEl.style.color = '#721c24';
        });
    }

    // Event listeners para el formulario de cobro
    (function() {
        var btnGuardarCobro = document.getElementById('btnGuardarCobroOperacion');
        var btnCancelarCobro = document.getElementById('btnCancelarCobroOperacion');
        var formCobro = document.getElementById('formCobroOperacion');
        
        if (btnGuardarCobro) {
            btnGuardarCobro.addEventListener('click', function() {
                if (formCobro && formCobro.checkValidity()) {
                    guardarCobroOperacion();
                } else if (formCobro) {
                    formCobro.reportValidity();
                }
            });
        }
        
        if (btnCancelarCobro) {
            btnCancelarCobro.addEventListener('click', function() {
                var formCobroEl = document.getElementById('formNuevoCobroOperacion');
                if (formCobroEl) {
                    formCobroEl.style.display = 'none';
                    document.getElementById('msgCobroOperacion').style.display = 'none';
                }
            });
        }
        
        // Permitir guardar con Enter en el campo monto
        var campoMonto = document.getElementById('cobro_monto');
        if (campoMonto) {
            campoMonto.addEventListener('keydown', function(e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    if (formCobro && formCobro.checkValidity()) {
                        guardarCobroOperacion();
                    } else if (formCobro) {
                        formCobro.reportValidity();
                    }
                }
            });
        }
    })();

    // Agregar event listeners a las celdas de operación (event delegation para funcionar con filas dinámicas)
    (function() {
        function manejarClickOP(e) {
            var celda = e.target;
            if (celda.classList.contains('col-operacion')) {
                var operacion = celda.textContent.trim();
                if (operacion && operacion !== '' && operacion !== '0') {
                    e.stopPropagation();
                    e.preventDefault();
                    abrirModalMovimientosOperacion(operacion);
                }
            }
        }
        // Usar event delegation en el documento para capturar clicks en cualquier momento
        document.addEventListener('click', function(e) {
            if (e.target.closest && e.target.closest('.tabla-azucar')) {
                manejarClickOP(e);
            }
        });
        // También agregar directamente al tbody si existe
        var tabla = document.querySelector('.tabla-azucar tbody');
        if (tabla) {
            tabla.addEventListener('click', manejarClickOP);
        }
    })();

    var btnInterpretar = document.getElementById('btnInterpretar');
    if (btnInterpretar) {
        btnInterpretar.addEventListener('click', function() {
            var texto = document.getElementById('textoInterpretar').value.trim();
            var fileInput = document.getElementById('imagenInterpretar');
            var msgEl = document.getElementById('msgInterpretar');
            msgEl.style.display = 'none';

            if (!texto && (!fileInput.files || fileInput.files.length === 0)) {
                msgEl.textContent = 'Escribí algo o subí una imagen.';
                msgEl.className = 'msg-interpretar error';
                msgEl.style.display = 'block';
                return;
            }

            this.disabled = true;
            msgEl.textContent = 'Interpretando...';
            msgEl.className = 'msg-interpretar';
            msgEl.style.display = 'block';

            var fd = new FormData();
            fd.append('texto', texto);

            function enviar(imagenB64) {
                if (imagenB64) fd.append('imagen', imagenB64);
                fetch('interpretar_azucar.php', { method: 'POST', body: fd })
                    .then(function(r) { return r.json(); })
                    .then(function(res) {
                        document.getElementById('btnInterpretar').disabled = false;
                        if (!res.ok) {
                            msgEl.textContent = res.error || 'Error al interpretar.';
                            msgEl.className = 'msg-interpretar error';
                            return;
                        }
                        var datos = res.datos || [];
                        msgEl.textContent = 'Se cargaron ' + datos.length + ' registro(s) en la grilla.';
                        msgEl.className = 'msg-interpretar ok';

                        var tbody = document.querySelector('.tabla-azucar tbody');
                        tbody.innerHTML = '';
                        if (datos.length === 0) {
                            tbody.innerHTML = '<tr><td colspan="18" style="text-align:center;padding:15px;color:#666;">No se extrajeron registros.</td></tr>';
                        } else {
                            datos.forEach(function(r, i) {
                                var tr = document.createElement('tr');
                                tr.className = i === 0 ? 'fila-seleccionada' : '';
                                var v = function(k, def) { return (r[k] != null && r[k] !== '') ? r[k] : (def || ''); };
                                tr.innerHTML =
                                    '<td class="col-fecha">' + esc(v('fecha')) + '</td>' +
                                    '<td class="col-l">' + esc(v('linea', '0')) + '</td>' +
                                    '<td class="col-articulo">' + esc(v('articulo')) + '</td>' +
                                    '<td class="col-orden">' + esc(v('orden', '0')) + '</td>' +
                                    '<td class="col-cantidad">' + (parseInt(v('cantidad'), 10) || 0) + '</td>' +
                                    '<td class="col-deposito">' + esc(v('deposito')) + '</td>' +
                                    '<td class="col-operacion">' + (parseInt(v('operacion'), 10) || '') + '</td>' +
                                    '<td class="col-fechavta">' + esc(v('fecha_vta')) + '</td>' +
                                    '<td class="col-cantvta">' + (parseInt(v('cant_vta'), 10) || 0) + '</td>' +
                                    '<td class="col-vendida ' + (v('vendida_a') ? '' : 'sin-dato') + '">' + esc(v('vendida_a')) + '</td>' +
                                    '<td class="col-operador ' + (v('operador') ? '' : 'sin-dato') + '">' + esc(v('operador')) + '</td>' +
                                    '<td class="col-preciovta">' + fmtNum(v('precio_vta')) + '</td>' +
                                    '<td class="col-fechafact">' + esc(v('fecha_fact')) + '</td>' +
                                    '<td class="col-cantfact">' + (parseInt(v('cant_fact'), 10) || 0) + '</td>' +
                                    '<td class="col-facturada ' + (v('facturada_a') ? '' : 'sin-dato') + '">' + esc(v('facturada_a')) + '</td>' +
                                    '<td class="col-preciofac">' + fmtNum(v('precio_fac')) + '</td>' +
                                    '<td class="col-nfact ' + (v('n_fact') ? '' : 'sin-dato') + '">' + esc(v('n_fact')) + '</td>' +
                                    '<td class="col-nremt ' + (v('n_remt') ? '' : 'sin-dato') + '">' + esc(v('n_remt')) + '</td>';
                                tbody.appendChild(tr);
                            });
                            document.querySelectorAll('.tabla-azucar tbody tr').forEach(function(tr) {
                                tr.addEventListener('click', function() {
                                    document.querySelectorAll('.tabla-azucar tbody tr.fila-seleccionada').forEach(function(r) { r.classList.remove('fila-seleccionada'); });
                                    this.classList.add('fila-seleccionada');
                                    if (typeof actualizarCartelSaldoOrden === 'function') actualizarCartelSaldoOrden();
                                });
                            });
                        }
                    })
                    .catch(function() {
                        document.getElementById('btnInterpretar').disabled = false;
                        msgEl.textContent = 'Error de red o servidor.';
                        msgEl.className = 'msg-interpretar error';
                    });
            }

            if (fileInput.files && fileInput.files[0]) {
                var fr = new FileReader();
                fr.onload = function() {
                    var b64 = fr.result.replace(/^data:image\/\w+;base64,/, '');
                    enviar(b64);
                };
                fr.readAsDataURL(fileInput.files[0]);
            } else {
                enviar(null);
            }
        });
    }

    function actualizarCartelSaldoOrden() {
        var tr = document.querySelector('.tabla-azucar tbody tr.fila-seleccionada[data-id]');
        var cartel = document.getElementById('cartelSaldoOrden');
        if (!cartel) return;
        if (!tr || !tr.dataset.cantidad) {
            cartel.style.display = 'none';
            return;
        }
        var cantTotal = parseInt(tr.dataset.cantidad || 0, 10);
        var cantVendida = parseInt(tr.dataset.cantvta || 0, 10);
        var saldo = cantTotal - cantVendida;
        if (cantVendida > 0 && saldo > 0) {
            cartel.textContent = 'Saldo de esta orden: ' + saldo + ' unidades.';
            cartel.style.display = 'block';
        } else {
            cartel.style.display = 'none';
        }
    }
    document.querySelectorAll('.tabla-azucar tbody tr').forEach(function(tr) {
        tr.addEventListener('click', function() {
            document.querySelectorAll('.tabla-azucar tbody tr.fila-seleccionada').forEach(function(r) { r.classList.remove('fila-seleccionada'); });
            this.classList.add('fila-seleccionada');
            actualizarCartelSaldoOrden();
            var wrap = document.getElementById('gridAzucarWrap');
            if (wrap) wrap.focus();
        });
        tr.addEventListener('dblclick', function() {
            if (this.dataset.id) {
                document.querySelectorAll('.tabla-azucar tbody tr.fila-seleccionada').forEach(function(r) { r.classList.remove('fila-seleccionada'); });
                this.classList.add('fila-seleccionada');
                abrirFormularioEditarStock(this);
            }
        });
    });

    var gridWrap = document.getElementById('gridAzucarWrap');
    if (gridWrap) {
        gridWrap.addEventListener('keydown', function(e) {
            if (e.key !== 'ArrowDown' && e.key !== 'ArrowUp') return;
            var target = e.target;
            if (target.tagName === 'INPUT' || target.tagName === 'TEXTAREA' || target.tagName === 'SELECT' || target.tagName === 'BUTTON') return;
            e.preventDefault();
            var rows = document.querySelectorAll('.tabla-azucar tbody tr[data-id]');
            var current = document.querySelector('.tabla-azucar tbody tr.fila-seleccionada');
            if (!rows.length) return;
            var idx = current ? Array.prototype.indexOf.call(rows, current) : -1;
            if (e.key === 'ArrowDown' && idx < rows.length - 1) idx++;
            else if (e.key === 'ArrowUp' && idx > 0) idx--;
            else return;
            document.querySelectorAll('.tabla-azucar tbody tr.fila-seleccionada').forEach(function(r) { r.classList.remove('fila-seleccionada'); });
            rows[idx].classList.add('fila-seleccionada');
            rows[idx].scrollIntoView({ block: 'nearest', behavior: 'smooth' });
            actualizarCartelSaldoOrden();
        });
    }
    actualizarCartelSaldoOrden();
    if (window.location.search.indexOf('abrir_venta=1') !== -1) {
        history.replaceState({}, '', 'gestionar_azucares.php');
        var tr = document.querySelector('.tabla-azucar tbody tr.fila-seleccionada[data-id]');
        if (tr) document.getElementById('btnVenta').click();
        else document.getElementById('modalVenta').classList.add('activo');
    }
    </script>
</body>
</html>
