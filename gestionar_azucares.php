<?php
include 'db.php';
include_once __DIR__ . '/inc_azucar_destinatarios_factura.php';
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
$res_col_liq_email = @mysqli_query($conexion, "SHOW COLUMNS FROM stock LIKE 'liq_prod_pdf_email_enviado'");
if ($res_col_liq_email && mysqli_num_rows($res_col_liq_email) == 0) {
    mysqli_query($conexion, "ALTER TABLE stock ADD liq_prod_pdf_email_enviado TINYINT(1) NOT NULL DEFAULT 0 AFTER n_remt");
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
$pendiente_por_orden = [];
$res_pend = mysqli_query($conexion, "SELECT orden, linea, MAX(cantidad) - SUM(IFNULL(cant_vta, 0)) AS pend FROM stock GROUP BY orden, linea");
if ($res_pend) {
    while ($p = mysqli_fetch_assoc($res_pend)) {
        $key = (int)$p['orden'] . '_' . (int)$p['linea'];
        $pendiente_por_orden[$key] = max(0, (int)$p['pend']);
    }
}
$mostrar_link_orden = [];
$res_multi = mysqli_query($conexion, "SELECT orden, linea, COUNT(*) AS n, SUM(IFNULL(cant_vta, 0)) AS tot_vta, MAX(cantidad) - SUM(IFNULL(cant_vta, 0)) AS pend FROM stock GROUP BY orden, linea");
if ($res_multi) {
    while ($m = mysqli_fetch_assoc($res_multi)) {
        $key = (int)$m['orden'] . '_' . (int)$m['linea'];
        $pend = max(0, (int)$m['pend']);
        $totVta = (int)$m['tot_vta'];
        $n = (int)$m['n'];
        $mostrar_link_orden[$key] = ($pend > 0 && $totVta > 0) || ($n > 1);
    }
}

$res_faltan = mysqli_query($conexion, "SELECT COALESCE(SUM(CASE WHEN pend > 0 THEN 1 ELSE 0 END), 0) AS n, COALESCE(SUM(pend), 0) AS total FROM (SELECT orden, linea, MAX(cantidad) AS tot_cant, SUM(IFNULL(cant_vta, 0)) AS tot_vta, MAX(cantidad) - SUM(IFNULL(cant_vta, 0)) AS pend FROM stock GROUP BY orden, linea HAVING pend > 0) AS agrupado");
$faltan_vender = 0;
$faltan_vender_cantidad = 0;
if ($res_faltan && $r = mysqli_fetch_assoc($res_faltan)) {
    $faltan_vender = (int)$r['n'];
    $faltan_vender_cantidad = (float)$r['total'];
}

$mensaje_stock = '';
$mensaje_stock_link = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['agregar_dest_factura_mail'])) {
        ensure_azucar_factura_mail_table($conexion);
        $email = strtolower(trim($_POST['email_dest_factura'] ?? ''));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $mensaje_stock = 'Email no válido.';
        } else {
            $esc = mysqli_real_escape_string($conexion, $email);
            $dup = mysqli_query($conexion, "SELECT id FROM azucar_factura_mail_destinatarios WHERE email = '$esc' LIMIT 1");
            if ($dup && mysqli_num_rows($dup) > 0) {
                $mensaje_stock = 'Ese correo ya está en la lista.';
            } else {
                mysqli_query($conexion, "INSERT INTO azucar_factura_mail_destinatarios (email) VALUES ('$esc')");
                header('Location: gestionar_azucares.php?dest_mail=ok');
                exit;
            }
        }
    } elseif (isset($_POST['quitar_dest_factura_mail'])) {
        ensure_azucar_factura_mail_table($conexion);
        $id_quit = (int)($_POST['id'] ?? 0);
        if ($id_quit > 0) {
            mysqli_query($conexion, "DELETE FROM azucar_factura_mail_destinatarios WHERE id = $id_quit LIMIT 1");
            header('Location: gestionar_azucares.php?dest_mail=quit');
            exit;
        }
    } elseif (isset($_POST['eliminar_venta_azucar'])) {
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
        $es_venta_adicional = isset($_POST['venta_adicional']) && $_POST['venta_adicional'] === '1';
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
                        $operacion = ($op_val > 0) ? $op_val : $old_operacion;
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
                    $operacion = ($op_val > 0) ? $op_val : siguienteOperacion($conexion, $stock_id);
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
            } elseif ($es_venta_adicional) {
                $r_actual = mysqli_fetch_assoc(mysqli_query($conexion, "SELECT s.*, v.apellido AS vendida_a_apellido, op.apellido AS operador_apellido FROM stock s LEFT JOIN usuarios v ON v.id = s.vendida_a_id LEFT JOIN usuarios op ON op.id = s.operador_id WHERE s.id = $stock_id LIMIT 1"));
                $res_pend_ord = mysqli_query($conexion, "SELECT MAX(cantidad) - SUM(IFNULL(cant_vta, 0)) AS pend FROM stock WHERE orden = $orden AND linea = $linea");
                $pendiente = 0;
                if ($res_pend_ord && $rp = mysqli_fetch_assoc($res_pend_ord)) {
                    $pendiente = max(0, (int)$rp['pend']);
                }
                if ($cant_vendida > $pendiente || $pendiente <= 0) {
                    $mensaje_stock = 'La cantidad vendida no puede superar las ' . $pendiente . ' unidades pendientes.';
                } else {
                    $operacion = siguienteOperacion($conexion, 0);
                    $refer = "OP N° $operacion";
                    $ins = "INSERT INTO cuentas (usuario_id, fecha, concepto, comprobante, referencia, monto) VALUES ($usuario_id, '$fecha_vta_esc', '$concepto', '$compro', '$refer', $monto)";
                    if (mysqli_query($conexion, $ins)) {
                        $mov_id = (int)mysqli_insert_id($conexion);
                        $deposito = mysqli_real_escape_string($conexion, $r_actual['deposito'] ?? '');
                        $fecha_base = mysqli_real_escape_string($conexion, $r_actual['fecha'] ?? $fecha_vta_esc);
                        $dep_sql = ($deposito === '') ? "NULL" : "'$deposito'";
                        $ins_venta = "INSERT INTO stock (fecha, linea, articulo, orden, cantidad, deposito, fecha_vta, cant_vta, vendida_a_id, operador_id, precio_vta, operacion, venta_movimiento_id) VALUES ('$fecha_base', $linea, '$articulo', $orden, $pendiente, $dep_sql, '$fecha_vta_esc', $cant_vendida, $usuario_id, " . ($operador_id > 0 ? $operador_id : "NULL") . ", $precio_vta, $operacion, $mov_id)";
                        mysqli_query($conexion, $ins_venta);
                        $saldo = $pendiente - $cant_vendida;
                        if ($saldo > 0) {
                            $ins_saldo = "INSERT INTO stock (fecha, linea, articulo, orden, cantidad, deposito) VALUES ('$fecha_base', $linea, '$articulo', $orden, $saldo, $dep_sql)";
                            mysqli_query($conexion, $ins_saldo);
                        }
                    } else {
                        $mensaje_stock = 'Error al grabar la venta adicional.';
                        $operacion = null;
                    }
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
                            $cant_nuevo = (int)$r_nuevo['cantidad'];
                            $cantvta_nuevo = (int)($r_nuevo['cant_vta'] ?? 0);
                            $res_pend_nuevo = mysqli_query($conexion, "SELECT COUNT(*) AS n, SUM(IFNULL(cant_vta, 0)) AS tot_vta, MAX(cantidad) - SUM(IFNULL(cant_vta, 0)) AS pend FROM stock WHERE orden = " . (int)$r_nuevo['orden'] . " AND linea = " . (int)$r_nuevo['linea']);
                            $pend_nuevo = 0;
                            $mostrar_link_nuevo = false;
                            if ($res_pend_nuevo && $rp = mysqli_fetch_assoc($res_pend_nuevo)) {
                                $pend_nuevo = max(0, (int)$rp['pend']);
                                $tot_vta_nuevo = (int)$rp['tot_vta'];
                                $n_nuevo = (int)$rp['n'];
                                $mostrar_link_nuevo = ($pend_nuevo > 0 && $tot_vta_nuevo > 0) || ($n_nuevo > 1);
                            }
                            $clase_cant_diff = ($cantvta_nuevo > 0 && $pend_nuevo > 0) ? 'fila-cant-diferente' : '';
                            $fila_html = '<tr' . ($clase_cant_diff ? ' class="' . $clase_cant_diff . '"' : '') . ' data-id="' . (int)$r_nuevo['id'] . '"'
                                . ' data-fecha="' . htmlspecialchars($fechaRaw, ENT_QUOTES) . '"'
                                . ' data-linea="' . (int)$r_nuevo['linea'] . '"'
                                . ' data-articulo="' . htmlspecialchars($r_nuevo['articulo'] ?? '', ENT_QUOTES) . '"'
                                . ' data-orden="' . (int)$r_nuevo['orden'] . '"'
                                . ' data-linea="' . (int)$r_nuevo['linea'] . '"'
                                . ' data-orden-pendiente="' . (int)$pend_nuevo . '"'
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
                                . '<td class="col-orden">' . ($mostrar_link_nuevo ? '<a href="#" class="link-orden" data-orden="' . (int)$r_nuevo['orden'] . '" data-linea="' . (int)$r_nuevo['linea'] . '" onclick="event.stopPropagation(); abrirModalMovimientosOrden(' . (int)$r_nuevo['orden'] . ', ' . (int)$r_nuevo['linea'] . '); return false;" style="text-decoration:underline; color:inherit;">' . (int)$r_nuevo['orden'] . '</a>' : (int)$r_nuevo['orden']) . '</td>'
                                . '<td class="col-cantidad">' . (int)$r_nuevo['cantidad'] . '</td>'
                                . '<td class="col-deposito">' . htmlspecialchars($r_nuevo['deposito'] ?? '') . '</td>'
                                . '<td class="col-operacion" data-operacion="' . (int)($r_nuevo['operacion'] ?? 0) . '">'
                                . (($opNum = (int)($r_nuevo['operacion'] ?? 0)) > 0
                                    ? $opNum . ' <button type="button" class="btn-mov-cobro btn btn-secondary" onclick="event.stopPropagation(); abrirModalMovimientosOperacion(\'' . $opNum . '\');" title="Movimientos de cobro">Mov-Cobro</button>'
                                    : '')
                                . '</td>'
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
                                . '<td class="col-nremt ' . (empty($r_nuevo['n_remt']) ? 'sin-dato' : '') . '">'
                                . (($r_nuevo['n_remt'] ?? '') !== ''
                                    ? '<a href="imprimir_remito.php?id=' . (int)$r_nuevo['id'] . '" target="_blank" class="link-remito" onclick="event.stopPropagation();" style="color:#0066cc;text-decoration:underline;">' . htmlspecialchars($r_nuevo['n_remt'] ?? '') . '</a> <button type="button" class="btn-imprimir-remito btn btn-secondary" onclick="event.stopPropagation(); window.open(\'imprimir_remito.php?id=' . (int)$r_nuevo['id'] . '\', \'_blank\');" title="Imprimir remito">Imprimir</button>'
                                    : htmlspecialchars($r_nuevo['n_remt'] ?? ''))
                                . '</td>'
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
if (isset($_GET['dest_mail']) && $_GET['dest_mail'] === 'ok') {
    $mensaje_stock = 'Destinatario de correo agregado.';
}
if (isset($_GET['dest_mail']) && $_GET['dest_mail'] === 'quit') {
    $mensaje_stock = 'Destinatario de correo quitado.';
}

$dest_factura_mails = get_azucar_factura_mail_rows($conexion);

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
        .contenedor-grilla-con-botones { overflow-x: hidden; margin-top: 10px; }
        .contenedor-grilla-con-botones .fila-botones-stock-toolbar,
        .contenedor-grilla-con-botones #cartelSaldoOrden { min-width: 1410px; }
        /* Alto ≈ 2 filas de cabecera (títulos + filtros) + 12 filas de registros; scroll vertical siempre visible */
        .grid-azucar-wrap {
            overflow-x: auto;
            overflow-y: scroll;
            max-height: calc(116px + 12 * 24px);
            border: 1px solid #ddd;
            outline: none;
            scrollbar-gutter: stable;
        }
        .grid-azucar-wrap:focus { outline: none; }
        .tabla-azucar { width: 100%; border-collapse: collapse; font-size: 11px; table-layout: fixed; min-width: 1270px; line-height: 1.2; font-weight: bold; }
        .tabla-azucar thead tr:first-child th { background: #007bff; color: white; padding: 4px 3px; position: sticky; top: 0; z-index: 12; font-weight: bold; text-align: center; white-space: nowrap; border: 1px solid #0056b3; }
        .tabla-azucar thead tr.fila-filtros-azucar th { background: #e7f1ff; color: #1a3a5c; padding: 3px 2px; position: sticky; top: 28px; z-index: 11; font-weight: normal; text-align: center; border: 1px solid #9ec5fe; vertical-align: middle; }
        .filtro-azucar-cell { width: 100%; min-width: 0; max-width: 100%; box-sizing: border-box; font-size: 9px; padding: 2px 3px; border: 1px solid #90c5ff; border-radius: 2px; font-weight: normal; color: #212529; background: #fff; }
        .filtro-azucar-cell::placeholder { color: #6c757d; font-size: 9px; }
        .btn-limpiar-filtros-azucar { font-size: 9px; padding: 2px 6px; cursor: pointer; border: 1px solid #6c757d; border-radius: 3px; background: #fff; color: #495057; font-weight: bold; white-space: nowrap; }
        .btn-limpiar-filtros-azucar:hover { background: #f8f9fa; }
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
        .tabla-azucar .col-operacion { width: 75px; min-width: 75px; cursor: pointer; }
        .tabla-azucar .btn-mov-cobro { font-size: 8px; padding: 1px 3px; margin-left: 2px; vertical-align: middle; }
        .tabla-azucar .col-operacion:hover { text-decoration: underline; color: #007bff; }
        .tabla-azucar tbody tr .col-operacion:hover { background-color: #e7f3ff; }
        .tabla-azucar .col-vendida, .tabla-azucar .col-facturada, .tabla-azucar .col-operador { width: 140px; }
        .tabla-azucar .col-vendida .link-comprador { color: #007bff; text-decoration: underline; cursor: pointer; }
        .tabla-azucar .col-vendida .link-comprador:hover { color: #0056b3; }
        .tabla-azucar tbody tr.fila-seleccionada .col-vendida .link-comprador { color: white; }
        .tabla-azucar tbody tr.fila-seleccionada .col-vendida .link-comprador:hover { color: #e7f3ff; }
        .tabla-azucar .col-operador .link-operador { color: #007bff; text-decoration: underline; cursor: pointer; }
        .tabla-azucar .col-operador .link-operador:hover { color: #0056b3; }
        .tabla-azucar tbody tr.fila-seleccionada .col-operador .link-operador { color: white; }
        .tabla-azucar tbody tr.fila-seleccionada .col-operador .link-operador:hover { color: #e7f3ff; }
        .tabla-azucar .col-preciovta, .tabla-azucar .col-preciofac { width: 75px; }
        .tabla-azucar .col-nfact { width: 155px; min-width: 155px; }
        .tabla-azucar .btn-email-liq-pdf { font-size: 9px; padding: 2px 5px; margin-left: 4px; vertical-align: middle; border: none; border-radius: 4px; cursor: pointer; font-weight: bold; color: #fff; }
        .tabla-azucar .btn-email-liq-rojo { background: #dc3545; }
        .tabla-azucar .btn-email-liq-rojo:hover { background: #c82333; }
        .tabla-azucar .btn-email-liq-verde { background: #28a745; }
        .tabla-azucar .btn-email-liq-verde:hover { background: #218838; }
        .tabla-azucar .col-nremt { width: 100px; }
        .tabla-azucar .col-nremt .link-remito { color: #0066cc !important; text-decoration: underline !important; cursor: pointer; }
        .tabla-azucar .col-nremt .link-remito:hover { color: #004499 !important; }
        .tabla-azucar .col-nremt .btn-imprimir-remito { font-size: 9px; padding: 2px 4px; margin-left: 4px; vertical-align: middle; }
        .tabla-azucar tbody tr.fila-seleccionada .col-nremt .link-remito { color: #aaddff !important; }
        .tabla-azucar tbody tr.fila-seleccionada .col-nremt .link-remito:hover { color: #fff !important; }
        .tabla-azucar .sin-dato { color: #999; }
        .tabla-azucar .col-articulo, .tabla-azucar .col-deposito { text-transform: uppercase; }
        /* Cantidad distinta de Cant Vta y Cant Vta #0: resaltar Articulo, Orden, Cantidad, Deposito, Op en rojo */
        .tabla-azucar tbody tr.fila-cant-diferente .col-articulo,
        .tabla-azucar tbody tr.fila-cant-diferente .col-orden,
        .tabla-azucar tbody tr.fila-cant-diferente .col-cantidad,
        .tabla-azucar tbody tr.fila-cant-diferente .col-deposito,
        .tabla-azucar tbody tr.fila-cant-diferente .col-operacion { color: #dc3545 !important; }
        .tabla-azucar tbody tr.fila-cant-diferente.fila-seleccionada .col-articulo,
        .tabla-azucar tbody tr.fila-cant-diferente.fila-seleccionada .col-orden,
        .tabla-azucar tbody tr.fila-cant-diferente.fila-seleccionada .col-cantidad,
        .tabla-azucar tbody tr.fila-cant-diferente.fila-seleccionada .col-deposito,
        .tabla-azucar tbody tr.fila-cant-diferente.fila-seleccionada .col-operacion { color: #ffb3b3 !important; }
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
        .cabecera-azucar-con-mails { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 8px 16px; margin-bottom: 10px; }
        .cabecera-azucar-titulo-row { display: flex; align-items: center; gap: 12px; flex-wrap: wrap; flex: 1 1 auto; min-width: 0; }
        .cabecera-azucar-titulo-row h2 { margin: 0; }
        .btn-export-excel-azucar {
            padding: 6px 14px;
            font-size: 12px;
            font-weight: bold;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            background: #217346;
            color: #fff;
            white-space: nowrap;
            flex-shrink: 0;
        }
        .btn-export-excel-azucar:hover { background: #1e6b40; }
        .cabecera-azucar-con-mails .fila-destinatarios-factura-mail { flex: 1 1 260px; max-width: min(100%, 720px); align-self: center; }
        /* Alineación con columna FechaVta + ancho del botón Venta (--ancho-btn-venta lo fija JS) */
        .fila-botones-stock-toolbar { position: relative; min-height: 26px; margin-bottom: 10px; --ancho-btn-venta: 0px; }
        .toolbar-grupo-stock { display: inline-flex; align-items: center; gap: 8px; flex-wrap: nowrap; vertical-align: middle; }
        .toolbar-venta-y-factura {
            position: absolute;
            left: calc(1px + 75px + 28px + 200px + 55px + 55px + 160px + 75px + var(--ancho-btn-venta));
            top: 0;
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            gap: 10px 18px;
            max-width: calc(100% - 660px - var(--ancho-btn-venta));
            box-sizing: border-box;
        }
        .toolbar-grupo { display: flex; align-items: center; gap: 8px; flex-wrap: nowrap; }
        .toolbar-grupo form { display: inline-flex; align-items: center; margin: 0; padding: 0; vertical-align: middle; }
        .btn-accion-toolbar { min-height: 24px; padding: 4px 12px; font-size: 12px; line-height: 1.2; border: none; border-radius: 3px; cursor: pointer; font-weight: bold; box-sizing: border-box; display: inline-flex; align-items: center; justify-content: center; }
        .fila-destinatarios-factura-mail { display: flex; flex-wrap: wrap; align-items: center; gap: 4px; font-size: 8px; font-weight: bold; background: #e7f3ff; padding: 4px 6px; border-radius: 3px; border: 1px solid #90c5ff; max-width: 100%; box-sizing: border-box; line-height: 1.15; }
        .fila-destinatarios-factura-mail .lbl-envia { color: #004085; margin-right: 2px; font-size: 8px; }
        .tag-dest-factura-mail { display: inline-flex; align-items: center; gap: 2px; background: #fff; padding: 1px 4px 1px 5px; border-radius: 2px; border: 1px solid #ced4da; font-weight: normal; font-size: 8px; }
        .tag-dest-factura-mail .email-txt { color: #333; font-size: 8px; }
        .btn-dest-factura-mas, .btn-dest-factura-menos { display: inline-flex; align-items: center; justify-content: center; min-width: 16px; height: 16px; padding: 0 3px; border: none; border-radius: 2px; cursor: pointer; font-weight: bold; font-size: 11px; line-height: 1; vertical-align: middle; box-sizing: border-box; }
        .btn-dest-factura-mas { background: #28a745; color: #fff; }
        .btn-dest-factura-mas:hover { background: #218838; }
        .btn-dest-factura-menos { background: #dc3545; color: #fff; min-width: 15px; height: 15px; font-size: 10px; }
        .btn-dest-factura-menos:hover { background: #c82333; }
        .form-quitar-dest-factura { display: inline; margin: 0; padding: 0; }
        .btn-alta-stock { background: #28a745; color: white; }
        .btn-alta-stock:hover { background: #218838; }
        .btn-editar-stock { background: #007bff; color: white; }
        .btn-editar-stock:hover { background: #0056b3; }
        .btn-eliminar-stock { background: #dc3545; color: white; }
        .btn-eliminar-stock:hover { background: #c82333; }
        .btn-venta-vta { background: #17a2b8; color: white; }
        .btn-venta-vta:hover { background: #138496; }
        .btn-editar-vta { background: #6f42c1; color: white; }
        .btn-editar-vta:hover { background: #5a32a3; }
        .btn-eliminar-vta { background: #fd7e14; color: white; }
        .btn-eliminar-vta:hover { background: #e96f0a; }
        .btn-facturar { background: #20c997; color: white; }
        .btn-facturar:hover { background: #1aa179; }
        .btn-editar-fact { background: #6f42c1; color: white; }
        .btn-editar-fact:hover { background: #5a32a3; }
        .btn-eliminar-fact { background: #dc3545; color: white; }
        .btn-eliminar-fact:hover { background: #c82333; }
        .volver { margin-top: 15px; }
        .volver .btn-volver-panel {
            display: inline-block;
            padding: 8px 16px;
            background: #28a745;
            color: #fff !important;
            text-decoration: none;
            border: none;
            border-radius: 4px;
            font-weight: bold;
            cursor: pointer;
            font-size: 14px;
        }
        .volver .btn-volver-panel:hover { background: #218838; color: #fff !important; }
        #btnFotoPago:disabled { opacity: 0.6; cursor: not-allowed; }
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
    <script src="https://cdn.jsdelivr.net/npm/tesseract.js@5/dist/tesseract.min.js"></script>
    <script src="informe_azucar.js"></script>
</head>
<body onkeydown="var e=event||window.event;if((e.keyCode||e.which)===27){var mf=document.getElementById('modalFotoPago');if(mf&&mf.classList.contains('activo')){if(typeof cancelarModalFotoPago==='function')cancelarModalFotoPago();e.preventDefault();return false;}var mp=document.getElementById('modalPegarPago');if(mp&&mp.classList.contains('activo')){if(typeof cerrarModalPegarPago==='function')cerrarModalPegarPago();e.preventDefault();return false;}var infop=document.getElementById('modalInformeOperador');if(infop&&infop.classList.contains('activo')){if(typeof cerrarModalInformeOperador==='function')cerrarModalInformeOperador();e.preventDefault();return false;}var o=document.getElementById('modalOperacionesOperador');if(o&&o.classList.contains('activo')){if(typeof cerrarModalOperacionesOperador==='function')cerrarModalOperacionesOperador();e.preventDefault();return false;}var mo=document.getElementById('modalMovimientosOrden');if(mo&&mo.classList.contains('activo')){if(typeof cerrarModalMovimientosOrden==='function')cerrarModalMovimientosOrden();e.preventDefault();return false;}var m=document.getElementById('modalMovimientosOperacion');if(m&&m.classList.contains('activo')){if(typeof cerrarModalMovimientosOperacion==='function')cerrarModalMovimientosOperacion();e.preventDefault();return false;}var v=document.getElementById('modalVenta');if(v&&v.classList.contains('activo')){if(typeof cerrarModalVenta==='function')cerrarModalVenta();e.preventDefault();return false;}var f=document.getElementById('modalFactura');if(f&&f.classList.contains('activo')){if(typeof cerrarModalFactura==='function')cerrarModalFactura();e.preventDefault();return false;}var mel=document.getElementById('modalEmailLiqProd');if(mel&&mel.classList.contains('activo')){if(typeof cerrarModalEmailLiqProd==='function')cerrarModalEmailLiqProd();e.preventDefault();return false;}var a=document.getElementById('modalAltaStock');if(a&&a.classList.contains('activo')){if(typeof cerrarModalAltaStock==='function')cerrarModalAltaStock();e.preventDefault();return false;}if(history.length>1){history.back();e.preventDefault();return false;}location.href='index.php';e.preventDefault();return false;}">
    <div class="container">
        <div class="cabecera-azucar-con-mails">
            <div class="cabecera-azucar-titulo-row">
                <h2>Gestión de azúcares <span style="font-size:14px; color:#856404; font-weight:normal;">(Faltan vender: <?= $faltan_vender ?> órdenes, <?= number_format($faltan_vender_cantidad, 0, ',', '.') ?> cantidad)</span></h2>
                <button type="button" class="btn-export-excel-azucar" id="btnExportarExcelAzucar" title="Descargar la grilla en Excel (CSV)">Exportar Excel</button>
            </div>
            <div class="fila-destinatarios-factura-mail" title="Destinatarios del correo del PDF de liquidación (factura)">
                <span class="lbl-envia">Se envía a:</span>
                <form id="formAgregarDestFacturaMail" method="post" action="gestionar_azucares.php" style="display:inline;margin:0;padding:0;">
                    <input type="hidden" name="agregar_dest_factura_mail" value="1">
                    <input type="hidden" name="email_dest_factura" id="hiddenEmailDestFactura" value="">
                </form>
                <button type="button" class="btn-dest-factura-mas" id="btnAddDestFacturaMail" title="Agregar destinatario">+</button>
                <?php foreach ($dest_factura_mails as $dm): ?>
                <span class="tag-dest-factura-mail">
                    <span class="email-txt"><?= htmlspecialchars($dm['email']) ?></span>
                    <form method="post" action="gestionar_azucares.php" class="form-quitar-dest-factura" onsubmit="return confirm('¿Quitar este destinatario?');">
                        <input type="hidden" name="quitar_dest_factura_mail" value="1">
                        <input type="hidden" name="id" value="<?= (int)$dm['id'] ?>">
                        <button type="submit" class="btn-dest-factura-menos" title="Quitar destinatario">−</button>
                    </form>
                </span>
                <?php endforeach; ?>
                <?php if (empty($dest_factura_mails)): ?>
                <span style="color:#856404;font-weight:normal;">(ninguno — agregá al menos uno con +)</span>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($mensaje_stock): ?>
        <p class="msg-interpretar ok" style="display: block;"><?= htmlspecialchars($mensaje_stock) ?></p>
        <?php endif; ?>

        <div class="contenedor-grilla-con-botones">
        <div class="fila-botones-stock-toolbar">
            <div class="toolbar-grupo toolbar-grupo-stock">
                <button type="button" class="btn-accion-toolbar btn-alta-stock" id="btnAltaStock">Alta stock</button>
                <button type="button" class="btn-accion-toolbar btn-editar-stock" id="btnEditarStock">Editar</button>
                <form id="formEliminarStock" method="post" action="gestionar_azucares.php">
                    <input type="hidden" name="eliminar_stock" value="1">
                    <input type="hidden" name="id" id="eliminar_id" value="">
                    <button type="button" class="btn-accion-toolbar btn-eliminar-stock" id="btnEliminarStock">Eliminar</button>
                </form>
            </div>
            <div class="toolbar-venta-y-factura">
                <div class="toolbar-grupo toolbar-grupo-venta">
                    <button type="button" class="btn-accion-toolbar btn-venta-vta" id="btnVenta">Venta</button>
                    <button type="button" class="btn-accion-toolbar btn-editar-vta" id="btnEditarVta">Editar Vta</button>
                    <form id="formEliminarVta" method="post" action="gestionar_azucares.php">
                        <input type="hidden" name="eliminar_venta_azucar" value="1">
                        <input type="hidden" name="id" id="eliminar_vta_id" value="">
                        <button type="button" class="btn-accion-toolbar btn-eliminar-vta" id="btnEliminarVta">Eliminar Vta</button>
                    </form>
                </div>
                <div class="toolbar-grupo toolbar-grupo-factura">
                    <button type="button" class="btn-accion-toolbar btn-facturar" id="btnFacturar">Facturar</button>
                    <button type="button" class="btn-accion-toolbar btn-editar-fact" id="btnEditarFact">Editar Factura</button>
                    <form id="formEliminarFactura" method="post" action="gestionar_azucares.php">
                        <input type="hidden" name="eliminar_factura_azucar" value="1">
                        <input type="hidden" name="id" id="eliminar_factura_id" value="">
                        <button type="button" class="btn-accion-toolbar btn-eliminar-fact" id="btnEliminarFact">Eliminar Factura</button>
                    </form>
                </div>
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
                    <tr class="fila-filtros-azucar">
                        <th class="col-fecha">
                            <button type="button" class="btn-limpiar-filtros-azucar" id="btnLimpiarFiltrosAzucar" title="Quitar todos los filtros" style="width:100%;margin-bottom:4px;">Limpiar filtros</button>
                            <input type="text" class="filtro-azucar-cell" placeholder="Filtrar…" title="Contiene texto" autocomplete="off">
                        </th>
                        <th class="col-l"><input type="text" class="filtro-azucar-cell" placeholder="Filtrar…" title="Contiene texto" autocomplete="off"></th>
                        <th class="col-articulo"><input type="text" class="filtro-azucar-cell" placeholder="Filtrar…" title="Contiene texto" autocomplete="off"></th>
                        <th class="col-orden"><input type="text" class="filtro-azucar-cell" placeholder="Filtrar…" title="Contiene texto" autocomplete="off"></th>
                        <th class="col-cantidad"><input type="text" class="filtro-azucar-cell" placeholder="Filtrar…" title="Contiene texto" autocomplete="off"></th>
                        <th class="col-deposito"><input type="text" class="filtro-azucar-cell" placeholder="Filtrar…" title="Contiene texto" autocomplete="off"></th>
                        <th class="col-operacion"><input type="text" class="filtro-azucar-cell" placeholder="Filtrar…" title="Contiene texto" autocomplete="off"></th>
                        <th class="col-fechavta"><input type="text" class="filtro-azucar-cell" placeholder="Filtrar…" title="Contiene texto" autocomplete="off"></th>
                        <th class="col-cantvta"><input type="text" class="filtro-azucar-cell" placeholder="Filtrar…" title="Contiene texto" autocomplete="off"></th>
                        <th class="col-vendida"><input type="text" class="filtro-azucar-cell" placeholder="Filtrar…" title="Contiene texto" autocomplete="off"></th>
                        <th class="col-operador"><input type="text" class="filtro-azucar-cell" placeholder="Filtrar…" title="Contiene texto" autocomplete="off"></th>
                        <th class="col-preciovta"><input type="text" class="filtro-azucar-cell" placeholder="Filtrar…" title="Contiene texto" autocomplete="off"></th>
                        <th class="col-fechafact"><input type="text" class="filtro-azucar-cell" placeholder="Filtrar…" title="Contiene texto" autocomplete="off"></th>
                        <th class="col-cantfact"><input type="text" class="filtro-azucar-cell" placeholder="Filtrar…" title="Contiene texto" autocomplete="off"></th>
                        <th class="col-facturada"><input type="text" class="filtro-azucar-cell" placeholder="Filtrar…" title="Contiene texto" autocomplete="off"></th>
                        <th class="col-preciofac"><input type="text" class="filtro-azucar-cell" placeholder="Filtrar…" title="Contiene texto" autocomplete="off"></th>
                        <th class="col-nfact"><input type="text" class="filtro-azucar-cell" placeholder="Filtrar…" title="Contiene texto" autocomplete="off"></th>
                        <th class="col-nremt"><input type="text" class="filtro-azucar-cell" placeholder="Filtrar…" title="Contiene texto" autocomplete="off"></th>
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
                        $cantidad = (int)$r['cantidad'];
                        $cantVta = (int)($r['cant_vta'] ?? 0);
                        $keyOrden = (int)$r['orden'] . '_' . (int)$r['linea'];
                        $pendOrden = $pendiente_por_orden[$keyOrden] ?? 0;
                        $esCantDiferente = ($pendOrden > 0 && $cantVta > 0);
                        $mostrarLinkOrden = !empty($mostrar_link_orden[$keyOrden]);
                        $clasesFila = ($i === 0 ? 'fila-seleccionada' : '') . ($esCantDiferente ? ' fila-cant-diferente' : '');
                        ?>
                        <tr class="<?= trim($clasesFila) ?>" data-id="<?= (int)$r['id'] ?>"
                            data-fecha="<?= htmlspecialchars($fechaRaw) ?>"
                            data-linea="<?= (int)$r['linea'] ?>"
                            data-articulo="<?= htmlspecialchars($r['articulo'] ?? '') ?>"
                            data-orden="<?= (int)$r['orden'] ?>"
                            data-linea="<?= (int)$r['linea'] ?>"
                            data-orden-pendiente="<?= (int)($pendiente_por_orden[$keyOrden] ?? 0) ?>"
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
                            data-liq-email-enviado="<?= !empty($r['liq_prod_pdf_email_enviado']) ? '1' : '0' ?>"
                            data-nremt="<?= htmlspecialchars($r['n_remt'] ?? '') ?>"
                            data-operador-id="<?= (int)($r['operador_id'] ?? 0) ?>"
                            data-operador-apellido="<?= htmlspecialchars($r['operador_apellido'] ?? '') ?>">
                            <td class="col-fecha"><?= htmlspecialchars(fmtFecha($r['fecha'])) ?></td>
                            <td class="col-l"><?= (int)$r['linea'] ?></td>
                            <td class="col-articulo"><?= htmlspecialchars($r['articulo']) ?></td>
                            <td class="col-orden"><?php if ($mostrarLinkOrden): ?><a href="#" class="link-orden" data-orden="<?= (int)$r['orden'] ?>" data-linea="<?= (int)$r['linea'] ?>" onclick="event.stopPropagation(); abrirModalMovimientosOrden(<?= (int)$r['orden'] ?>, <?= (int)$r['linea'] ?>); return false;" style="text-decoration:underline; color:inherit;"><?= (int)$r['orden'] ?></a><?php else: ?><?= (int)$r['orden'] ?><?php endif; ?></td>
                            <td class="col-cantidad"><?= (int)$r['cantidad'] ?></td>
                            <td class="col-deposito"><?= htmlspecialchars($r['deposito'] ?? '') ?></td>
                            <td class="col-operacion" data-operacion="<?= (int)($r['operacion'] ?? 0) ?>"><?php
                                $op = (int)($r['operacion'] ?? 0);
                                if ($op > 0) {
                                    echo $op . ' <button type="button" class="btn-mov-cobro btn btn-secondary" onclick="event.stopPropagation(); abrirModalMovimientosOperacion(\'' . $op . '\');" title="Movimientos de cobro">Mov-Cobro</button>';
                                } else {
                                    echo '';
                                }
                            ?></td>
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
                            <td class="col-operador <?= empty($r['operador_apellido']) ? 'sin-dato' : '' ?>"><?php
                                $oid = (int)($r['operador_id'] ?? 0);
                                $onom = htmlspecialchars($r['operador_apellido'] ?? '');
                                if ($oid > 0 && $onom !== '') {
                                    echo '<a href="#" class="link-operador" data-operador-id="' . $oid . '" data-operador-apellido="' . htmlspecialchars($r['operador_apellido'] ?? '', ENT_QUOTES, 'UTF-8') . '">' . $onom . '</a>';
                                } else {
                                    echo $onom;
                                }
                            ?></td>
                            <td class="col-preciovta"><?= fmtNum($r['precio_vta']) ?></td>
                            <td class="col-fechafact"><?= htmlspecialchars(fmtFecha($r['fecha_fact'])) ?></td>
                            <td class="col-cantfact"><?= (int)$r['cant_fact'] ?></td>
                            <td class="col-facturada <?= empty($r['facturada_a_apellido']) ? 'sin-dato' : '' ?>"><?= htmlspecialchars($r['facturada_a_apellido'] ?? '') ?></td>
                            <td class="col-preciofac"><?= fmtNum($r['precio_fac']) ?></td>
                            <td class="col-nfact <?= empty($r['n_fact']) ? 'sin-dato' : '' ?>"><?php
                                $nf = trim((string)($r['n_fact'] ?? ''));
                                echo htmlspecialchars($nf);
                                if ($nf !== '') {
                                    $env_ok = !empty($r['liq_prod_pdf_email_enviado']);
                                    $cls = $env_ok ? 'btn-email-liq-verde' : 'btn-email-liq-rojo';
                                    echo ' <button type="button" class="btn-email-liq-pdf ' . $cls . '" data-stock-id="' . (int)$r['id'] . '" onclick="event.stopPropagation(); abrirModalEmailLiqProd(this);" title="' . ($env_ok ? 'PDF enviado por email' : 'Enviar PDF por email') . '">Email PDF</button>';
                                }
                            ?></td>
                            <td class="col-nremt <?= empty($r['n_remt']) ? 'sin-dato' : '' ?>"><?php
                                $nremt = $r['n_remt'] ?? '';
                                if ($nremt !== '') {
                                    echo '<a href="imprimir_remito.php?id=' . (int)$r['id'] . '" target="_blank" class="link-remito" onclick="event.stopPropagation();" style="color:#0066cc;text-decoration:underline;">' . htmlspecialchars($nremt) . '</a>';
                                    echo ' <button type="button" class="btn-imprimir-remito btn btn-secondary" onclick="event.stopPropagation(); window.open(\'imprimir_remito.php?id=' . (int)$r['id'] . '\', \'_blank\');" title="Imprimir remito">Imprimir</button>';
                                } else {
                                    echo htmlspecialchars($nremt);
                                }
                            ?></td>
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
                    <input type="hidden" name="venta_adicional" id="venta_adicional" value="0">
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

        <div id="modalEmailLiqProd" class="modal-venta-overlay" onclick="if(event.target===this) cerrarModalEmailLiqProd()">
            <div class="modal-venta" onclick="event.stopPropagation()" style="max-width: 480px;">
                <h3>Enviar PDF por email</h3>
                <p style="margin:0 0 10px 0; font-size:11px; color:#555;">Se envía a los correos listados arriba (<strong>Se envía a</strong>, junto a Facturar). Asunto: <strong>Liquido Producto</strong> y el nombre del archivo.</p>
                <div id="msgEmailLiqProd" style="display:none; margin-bottom:10px; padding:8px; border-radius:4px; font-size:11px;"></div>
                <input type="hidden" id="emailLiqStockId" value="">
                <div style="margin-bottom:10px;">
                    <label for="emailLiqFile" style="display:block; margin-bottom:4px; font-weight:bold; font-size:10px;">Archivo PDF</label>
                    <input type="file" id="emailLiqFile" accept=".pdf,application/pdf" style="width:100%; font-size:12px; box-sizing:border-box;">
                </div>
                <div style="margin-bottom:12px;">
                    <label for="emailLiqNombre" style="display:block; margin-bottom:4px; font-weight:bold; font-size:10px;">Nombre del archivo (sin .pdf; se adjunta como .pdf)</label>
                    <input type="text" id="emailLiqNombre" maxlength="200" placeholder="Ej: Emisor_Liq_2025-03-24" style="width:100%; padding:6px; box-sizing:border-box; border:1px solid #ced4da; border-radius:4px;">
                </div>
                <div class="botones" style="margin-top:0;">
                    <button type="button" class="btn-guardar-venta" id="emailLiqBtnEnviar">Enviar</button>
                    <button type="button" class="btn-cerrar-venta" onclick="cerrarModalEmailLiqProd()">Cancelar</button>
                </div>
            </div>
        </div>

        <!-- Modal movimientos por operación -->
        <div id="modalMovimientosOperacion" class="modal-venta-overlay" onclick="if(event.target===this) cerrarModalMovimientosOperacion()">
            <div class="modal-venta" onclick="event.stopPropagation()" style="max-width: 90%; max-height: 90vh; overflow: auto;">
                <h3 id="modalMovimientosOperacionTitulo">Movimientos de pago - Operación N° <span id="modalOpNumero"></span></h3>
                <div id="wrapImpresionMovOp" data-informe-tipo="mov_pago_op" data-operacion="" style="margin-bottom: 15px;">
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
                <div style="margin-bottom: 12px;">
                    <button type="button" class="btn-guardar-venta" id="btnGenerarInformeMovOp" onclick="togglePanelInformeMovOp()">Informe</button>
                    <div id="panelInformeAccionesMovOp" style="display: none; margin-top: 10px; padding: 12px 14px; background: #f0f4f8; border-radius: 6px; border: 1px solid #c3d0e0;">
                        <div style="display: flex; flex-wrap: wrap; gap: 8px; align-items: center;">
                            <button type="button" class="btn-guardar-venta" onclick="azucarImprimirInforme('wrapImpresionMovOp', azucarTituloModalMovOp())" title="Imprimir">🖨️ Imprimir</button>
                            <button type="button" class="btn-guardar-venta" onclick="azucarPdfInforme('wrapImpresionMovOp', azucarTituloModalMovOp(), 'mov_pago_operacion')" title="Descargar PDF">📄 Descargar PDF</button>
                            <button type="button" class="btn-guardar-venta" onclick="azucarWhatsappInforme('wrapImpresionMovOp', azucarTituloModalMovOp(), 'mov_pago_operacion')" title="Enviar por WhatsApp (PDF)">Enviar</button>
                        </div>
                    </div>
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
                            <input type="text" id="cobro_concepto" value="COBRO VTA AZUCAR: " required style="width: 100%; padding: 6px; border: 1px solid #ced4da; border-radius: 4px; font-size: 12px; box-sizing: border-box;" tabindex="1">
                            <span class="hint-concepto-cobro" style="display: block; margin-top: 4px; font-size: 10px; color: #999;">pegar emisor cheq, N° echeq, y fecha cobro</span>
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
                            <input type="number" id="cobro_monto" step="0.01" min="0" required placeholder="0,00" style="width: 100%; padding: 6px; border: 1px solid #ced4da; border-radius: 4px; font-size: 12px; box-sizing: border-box;" tabindex="2">
                        </div>
                        <div class="form-g" style="grid-column: 1 / -1; display: flex; gap: 10px; margin-top: 5px;">
                            <button type="button" class="btn-guardar-venta" id="btnGuardarCobroOperacion" style="flex: 1;" tabindex="3">Guardar</button>
                            <button type="button" class="btn-cerrar-venta" id="btnCancelarCobroOperacion" style="flex: 1;">Cancelar</button>
                        </div>
                    </form>
                </div>
                <div class="botones">
                    <button type="button" class="btn-guardar-venta" id="btnNuevoCobroOperacion" style="display:none;">Nuevo cobro</button>
                    <button type="button" class="btn-guardar-venta" id="btnLeerPdfEcheq" style="display:none;">Leer PDF ECheq</button>
                    <button type="button" class="btn-guardar-venta" id="btnPegarPago" style="display:none;">Pegar Pago</button>
                    <input type="file" id="inputPdfEcheq" accept=".pdf,application/pdf" style="display:none;">
                    <button type="button" class="btn-cerrar-venta" onclick="cerrarModalMovimientosOperacion()">Cerrar</button>
                </div>
            </div>
        </div>
        <!-- Modal Pegar Pago: zona para pegar imagen del comprobante -->
        <div id="modalPegarPago" class="modal-venta-overlay" onclick="if(event.target===this) cerrarModalPegarPago()">
            <div class="modal-venta" onclick="event.stopPropagation()" style="max-width: 600px; max-height: 90vh;">
                <h4 style="margin: 0 0 12px 0; color: #007bff;">Pegar imagen del comprobante</h4>
                <p style="font-size: 11px; color: #666; margin: 0 0 10px 0;">Haga clic en el recuadro y pegue (Ctrl+V) una captura o foto del PDF del echeq.</p>
                <div id="zonaPegarPago" contenteditable="true" style="min-height: 280px; max-height: 400px; overflow: auto; border: 2px dashed #007bff; border-radius: 6px; padding: 15px; background: #f8f9fa; font-size: 12px; cursor: text;" title="Pegue aquí (Ctrl+V)">Pegue aquí la imagen…</div>
                <div id="msgPegarPago" style="display:none; margin-top: 8px; padding: 8px; border-radius: 4px; font-size: 11px;"></div>
                <div style="margin-top: 12px; display: flex; gap: 8px;">
                    <button type="button" class="btn-guardar-venta" id="btnProcesarPegarPago">Procesar</button>
                    <button type="button" class="btn-cerrar-venta" onclick="cerrarModalPegarPago()">Cerrar</button>
                </div>
            </div>
        </div>

        <!-- Modal operaciones del operador -->
        <div id="modalOperacionesOperador" class="modal-venta-overlay" onclick="if(event.target===this) cerrarModalOperacionesOperador()">
            <div class="modal-venta" onclick="event.stopPropagation()" style="max-width: 90%; max-height: 90vh; overflow: auto;">
                <h3 id="modalOperacionesOperadorTitulo">Operaciones del operador: <span id="modalOperadorNombre"></span></h3>
                <div id="wrapImpresionOpsOperador" style="margin-bottom: 15px;">
                    <table style="width: 100%; border-collapse: collapse; font-size: 11px;">
                        <thead>
                            <tr style="background: #007bff; color: white;">
                                <th class="al-cen" style="padding: 6px; border: 1px solid #0056b3;">Op</th>
                                <th class="al-izq" style="padding: 6px; border: 1px solid #0056b3;">Vendida a</th>
                                <th class="al-der" style="padding: 6px; border: 1px solid #0056b3; width: 150px;">Saldo</th>
                                <th style="padding: 6px; border: 1px solid #0056b3; width: 80px;"></th>
                            </tr>
                        </thead>
                        <tbody id="tablaOperacionesOperador">
                            <tr><td colspan="4" style="text-align:center; padding:30px; color:gray;">Cargando...</td></tr>
                        </tbody>
                    </table>
                </div>
                <div class="botones">
                    <button type="button" class="btn-guardar-venta" onclick="abrirInformeOperacionesOperador()">Informes</button>
                    <button type="button" class="btn-cerrar-venta" onclick="cerrarModalOperacionesOperador()">Cerrar</button>
                </div>
            </div>
        </div>

        <!-- Informe operaciones del operador (operador, Op, Vendida a, Saldo) -->
        <div id="modalInformeOperador" class="modal-venta-overlay" style="z-index: 10060;" onclick="if(event.target===this) cerrarModalInformeOperador()">
            <div class="modal-venta" onclick="event.stopPropagation()" style="max-width: 92%; max-height: 88vh; overflow: auto;">
                <h3 style="margin-top:0;">Informe — Operaciones del operador</h3>
                <div id="wrapInformeOperadorExport" data-informe-tipo="operador" data-operador-id="">
                    <p class="informe-linea-operador" style="font-size: 13px; margin: 10px 0 14px;"><strong>Operador:</strong> <span id="informeOperadorNombreMostrar">—</span></p>
                    <div id="wrapInformeOperadorContenido" style="margin-bottom: 12px;"></div>
                </div>
                <div class="botones">
                    <button type="button" class="btn-guardar-venta" onclick="azucarImprimirInforme('wrapInformeOperadorExport', tituloInformeOperador())" title="Imprimir informe">🖨️ Imprimir</button>
                    <button type="button" class="btn-guardar-venta" onclick="azucarWhatsappInforme('wrapInformeOperadorExport', tituloInformeOperador(), 'operaciones_operador')" title="Enviar por WhatsApp (PDF)">Enviar informe</button>
                    <button type="button" class="btn-guardar-venta" onclick="azucarPdfInforme('wrapInformeOperadorExport', tituloInformeOperador(), 'operaciones_operador')" title="Descargar PDF">📄 Descargar PDF</button>
                    <button type="button" class="btn-cerrar-venta" onclick="cerrarModalInformeOperador()">Cerrar</button>
                </div>
            </div>
        </div>

        <!-- Modal movimientos por orden -->
        <div id="modalMovimientosOrden" class="modal-venta-overlay" onclick="if(event.target===this) cerrarModalMovimientosOrden()">
            <div class="modal-venta" onclick="event.stopPropagation()" style="max-width: 90%; max-height: 90vh; overflow: auto;">
                <h3 id="modalMovimientosOrdenTitulo">Movimientos de la orden N° <span id="modalOrdenNumero"></span></h3>
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
                        <tbody id="tablaMovimientosOrden">
                            <tr><td colspan="7" style="text-align:center; padding:30px; color:gray;">Cargando...</td></tr>
                        </tbody>
                    </table>
                </div>
                <div class="botones">
                    <button type="button" class="btn-cerrar-venta" onclick="cerrarModalMovimientosOrden()">Cerrar</button>
                </div>
            </div>
        </div>

        <p class="volver">
            <a href="index.php" class="btn-volver-panel">← Volver al panel</a>
            <button type="button" class="btn btn-secondary" id="btnFotoPago" disabled title="Inhabilitado">Foto Pago (Inhabilitado)</button>
            <input type="file" id="inputFotoPago" accept="image/*" capture="environment" style="display:none;">
        </p>
        <!-- Modal resultado foto -->
        <div id="modalFotoPago" class="modal-venta-overlay" onclick="if(event.target===this) cancelarModalFotoPago()">
            <div class="modal-venta" onclick="event.stopPropagation()" style="max-width: 520px;">
                <h4 style="margin: 0 0 12px 0; color: #007bff;">Datos extraídos de la foto</h4>
                <div id="resumenFotoPago" style="padding: 12px; background: #f8f9fa; border-radius: 4px; font-size: 12px; margin-bottom: 12px; max-height: 280px; overflow-y: auto;"></div>
                <p style="font-size: 11px; color: #666; margin: 0 0 10px 0;">Abra una operación (clic en Mov-Cobro), luego Nuevo cobro: se completará automáticamente.</p>
                <div style="display: flex; gap: 8px;">
                    <button type="button" class="btn-guardar-venta" onclick="cerrarModalFotoPago()">Cerrar</button>
                    <button type="button" class="btn-cerrar-venta" onclick="cancelarModalFotoPago()">Cancelar</button>
                </div>
            </div>
        </div>
    </div>

    <script>
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' || e.keyCode === 27 || e.which === 27) {
            var modalVenta = document.getElementById('modalVenta');
            var modalFactura = document.getElementById('modalFactura');
            var modalMovOp = document.getElementById('modalMovimientosOperacion');
            var modalInfOp = document.getElementById('modalInformeOperador');
            var modalOpsOp = document.getElementById('modalOperacionesOperador');
            var modalAlta = document.getElementById('modalAltaStock');
            if (modalInfOp && modalInfOp.classList.contains('activo')) {
                cerrarModalInformeOperador();
                e.preventDefault();
                return;
            }
            if (modalOpsOp && modalOpsOp.classList.contains('activo')) {
                cerrarModalOperacionesOperador();
                e.preventDefault();
                return;
            }
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
    function aplicarFiltrosAzucar() {
        var inputs = document.querySelectorAll('.fila-filtros-azucar .filtro-azucar-cell');
        var rows = document.querySelectorAll('.tabla-azucar tbody tr[data-id]');
        rows.forEach(function(tr) {
            var tds = tr.querySelectorAll('td');
            var show = true;
            for (var i = 0; i < inputs.length; i++) {
                var q = (inputs[i].value || '').trim().toLowerCase();
                if (!q) continue;
                var cellText = (tds[i] && tds[i].innerText) ? tds[i].innerText.toLowerCase() : '';
                if (cellText.indexOf(q) === -1) { show = false; break; }
            }
            tr.style.display = show ? '' : 'none';
        });
        var sel = document.querySelector('.tabla-azucar tbody tr.fila-seleccionada[data-id]');
        if (sel && sel.style.display === 'none') {
            sel.classList.remove('fila-seleccionada');
            for (var j = 0; j < rows.length; j++) {
                if (rows[j].style.display !== 'none') {
                    rows[j].classList.add('fila-seleccionada');
                    break;
                }
            }
        }
        if (typeof actualizarCartelSaldoOrden === 'function') actualizarCartelSaldoOrden();
    }
    function exportarGrillaAzucarExcel() {
        var table = document.querySelector('.tabla-azucar');
        if (!table) return;
        var clone = table.cloneNode(true);
        clone.querySelectorAll('.fila-filtros-azucar').forEach(function(r) { r.remove(); });
        clone.querySelectorAll('tbody tr[data-id]').forEach(function(tr) {
            if (tr.style.display === 'none') tr.remove();
        });
        clone.querySelectorAll('button, .btn-mov-cobro, .btn-imprimir-remito').forEach(function(el) { el.remove(); });
        clone.querySelectorAll('a').forEach(function(a) {
            var t = document.createTextNode(a.textContent);
            a.parentNode.replaceChild(t, a);
        });
        var lines = [];
        var trs = clone.querySelectorAll('thead tr, tbody tr');
        for (var i = 0; i < trs.length; i++) {
            var tr = trs[i];
            if (tr.querySelector('td[colspan]')) continue;
            var cells = tr.querySelectorAll('th, td');
            if (!cells.length) continue;
            var row = [];
            for (var j = 0; j < cells.length; j++) {
                var txt = (cells[j].innerText || '').replace(/\r\n/g, ' ').replace(/\n/g, ' ').replace(/"/g, '""').trim();
                row.push('"' + txt + '"');
            }
            lines.push(row.join(';'));
        }
        if (!lines.length) {
            alert('No hay datos para exportar.');
            return;
        }
        var bom = '\ufeff';
        var blob = new Blob([bom + lines.join('\r\n')], { type: 'text/csv;charset=utf-8;' });
        var url = URL.createObjectURL(blob);
        var a = document.createElement('a');
        a.href = url;
        a.download = 'gestion_azucares_' + new Date().toISOString().slice(0, 10) + '.csv';
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        URL.revokeObjectURL(url);
    }
    var btnExportarExcelAzucar = document.getElementById('btnExportarExcelAzucar');
    if (btnExportarExcelAzucar) {
        btnExportarExcelAzucar.addEventListener('click', exportarGrillaAzucarExcel);
    }
    var filaFiltrosAzucar = document.querySelector('.fila-filtros-azucar');
    if (filaFiltrosAzucar) {
        filaFiltrosAzucar.addEventListener('input', function(e) {
            if (e.target && e.target.classList && e.target.classList.contains('filtro-azucar-cell')) aplicarFiltrosAzucar();
        });
        filaFiltrosAzucar.addEventListener('click', function(e) { e.stopPropagation(); });
    }
    var btnLimpiarFiltrosAzucar = document.getElementById('btnLimpiarFiltrosAzucar');
    if (btnLimpiarFiltrosAzucar) {
        btnLimpiarFiltrosAzucar.addEventListener('click', function(e) {
            e.stopPropagation();
            document.querySelectorAll('.fila-filtros-azucar .filtro-azucar-cell').forEach(function(inp) { inp.value = ''; });
            aplicarFiltrosAzucar();
        });
    }
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
        var ventaAdicionalEl = document.getElementById('venta_adicional');
        var cantTotal = parseInt(tr.dataset.cantidad || 0, 10);
        var cantVendida = parseInt(tr.dataset.cantvta || 0, 10);
        var pendienteOrden = parseInt(tr.dataset.ordenPendiente || 0, 10);
        var restante = (pendienteOrden > 0) ? pendienteOrden : (cantTotal - cantVendida);
        var esVentaAdicional = !esEdicion && cantVendida > 0 && restante > 0;
        if (ventaAdicionalEl) ventaAdicionalEl.value = esVentaAdicional ? '1' : '0';
        var inpCantVendida = document.getElementById('venta_cant_vendida');
        if (esVentaAdicional) {
            cartelParcial.textContent = 'Venta adicional — Quedan ' + restante + ' unidades. Se grabará como nueva operación (mismo N° Orden).';
            cartelParcial.style.display = 'block';
            inpCantVendida.value = restante;
            inpCantVendida.max = restante;
            inpCantVendida.min = 1;
        } else {
            cartelParcial.style.display = 'none';
            inpCantVendida.removeAttribute('max');
            if (!esEdicion) inpCantVendida.value = tr.dataset.cantidad || '1';
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
            document.getElementById('modalVentaTitulo').textContent = esVentaAdicional ? 'Venta adicional (mismo N° Orden)' : 'Registrar venta (cuenta, no caja)';
            document.getElementById('venta_btn_guardar').textContent = 'Guardar venta';
            document.getElementById('venta_fecha').value = '<?= date('Y-m-d') ?>';
            document.getElementById('venta_precio').value = '';
            document.getElementById('venta_operacion').value = '';
            document.getElementById('venta_operacion').placeholder = esVentaAdicional ? 'Auto (nueva op.)' : 'Auto';
            document.getElementById('venta_usuario_id').value = '';
            document.getElementById('venta_usuario_nombre').textContent = '—';
            document.getElementById('venta_buscar_usuario').value = '';
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
    var emailLiqBtnOrigen = null;
    function cerrarModalEmailLiqProd() {
        var m = document.getElementById('modalEmailLiqProd');
        if (m) m.classList.remove('activo');
        var msg = document.getElementById('msgEmailLiqProd');
        if (msg) { msg.style.display = 'none'; msg.textContent = ''; msg.style.background = ''; msg.style.color = ''; }
    }
    function abrirModalEmailLiqProd(btn) {
        emailLiqBtnOrigen = btn || null;
        var sid = (btn && btn.getAttribute('data-stock-id')) ? String(btn.getAttribute('data-stock-id')).trim() : '';
        document.getElementById('emailLiqStockId').value = sid;
        document.getElementById('emailLiqFile').value = '';
        document.getElementById('emailLiqNombre').value = '';
        var msg = document.getElementById('msgEmailLiqProd');
        if (msg) { msg.style.display = 'none'; msg.textContent = ''; }
        document.getElementById('modalEmailLiqProd').classList.add('activo');
    }
    function parseJsonEmailLiqProd(text) {
        var t = (text || '').replace(/^\uFEFF/, '').trim();
        if (!t) return null;
        try {
            return JSON.parse(t);
        } catch (e1) {
            var i = t.indexOf('{');
            var k = t.lastIndexOf('}');
            if (i >= 0 && k > i) {
                try {
                    return JSON.parse(t.slice(i, k + 1));
                } catch (e2) {}
            }
            return null;
        }
    }
    function resolverBtnEmailLiqPdf(sid, btnOrigen) {
        var sidNorm = String(parseInt(sid, 10) || 0);
        var b = btnOrigen;
        if (!b || !b.classList || !b.classList.contains('btn-email-liq-pdf')) {
            b = null;
        } else if (String(b.getAttribute('data-stock-id') || '').trim() !== sidNorm) {
            b = null;
        }
        if (!b && sidNorm !== '0') {
            b = document.querySelector('.tabla-azucar .btn-email-liq-pdf[data-stock-id="' + sidNorm + '"]');
        }
        return b;
    }
    function marcarBtnEmailLiqProdEnviado(sid, btnOrigen) {
        var b = resolverBtnEmailLiqPdf(sid, btnOrigen);
        if (b) {
            b.classList.remove('btn-email-liq-rojo');
            b.classList.add('btn-email-liq-verde');
            b.title = 'PDF enviado por email';
        }
    }
    function marcarBtnEmailLiqProdFallo(sid, btnOrigen) {
        var b = resolverBtnEmailLiqPdf(sid, btnOrigen);
        if (b) {
            b.classList.remove('btn-email-liq-verde');
            b.classList.add('btn-email-liq-rojo');
            b.title = 'Enviar PDF por email';
        }
    }
    (function() {
        var fileEl = document.getElementById('emailLiqFile');
        var nomEl = document.getElementById('emailLiqNombre');
        if (fileEl && nomEl) {
            fileEl.addEventListener('change', function() {
                if (this.files && this.files.length > 0 && !nomEl.value) {
                    var base = this.files[0].name.replace(/\.pdf$/i, '');
                    nomEl.value = base;
                    nomEl.focus();
                }
            });
        }
        var btnEnviar = document.getElementById('emailLiqBtnEnviar');
        if (btnEnviar) {
            btnEnviar.addEventListener('click', function() {
                var sid = String(document.getElementById('emailLiqStockId').value || '').trim();
                var btnOrigenEnvio = emailLiqBtnOrigen;
                var msg = document.getElementById('msgEmailLiqProd');
                var f = document.getElementById('emailLiqFile');
                if (!sid || !f || !f.files || f.files.length === 0) {
                    if (msg) {
                        msg.style.display = 'block';
                        msg.style.background = '#f8d7da';
                        msg.style.color = '#721c24';
                        msg.textContent = 'Seleccioná un archivo PDF.';
                    }
                    return;
                }
                var fd = new FormData();
                fd.append('accion', 'enviar');
                fd.append('stock_id', sid);
                fd.append('nombre_archivo', document.getElementById('emailLiqNombre').value || '');
                fd.append('pdf_liq', f.files[0]);
                var sidNum = String(parseInt(sid, 10) || 0);
                f.value = '';
                document.getElementById('emailLiqNombre').value = '';
                document.getElementById('emailLiqStockId').value = '';
                emailLiqBtnOrigen = null;
                cerrarModalEmailLiqProd();
                fetch('enviar_email_liq_prod.php', { method: 'POST', body: fd, credentials: 'same-origin' })
                    .then(function(r) {
                        return r.text().then(function(text) {
                            var j = parseJsonEmailLiqProd(text);
                            if (!j || typeof j !== 'object') {
                                return { j: { ok: false, error: 'Respuesta inválida del servidor.' } };
                            }
                            return { j: j };
                        });
                    })
                    .catch(function() {
                        return { j: { ok: false, error: 'Error de red.' } };
                    })
                    .then(function(res) {
                        if (res.j && res.j.ok) {
                            var tr = document.querySelector('.tabla-azucar tbody tr[data-id="' + sidNum + '"]');
                            if (tr) tr.setAttribute('data-liq-email-enviado', '1');
                            marcarBtnEmailLiqProdEnviado(sidNum, btnOrigenEnvio);
                        } else {
                            marcarBtnEmailLiqProdFallo(sidNum, btnOrigenEnvio);
                        }
                    });
            });
        }
    })();
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
    var btnAddDest = document.getElementById('btnAddDestFacturaMail');
    if (btnAddDest) {
        btnAddDest.addEventListener('click', function() {
            var e = prompt('Email del destinatario:');
            if (!e) return;
            e = e.trim();
            if (!e) return;
            var hid = document.getElementById('hiddenEmailDestFactura');
            var frm = document.getElementById('formAgregarDestFacturaMail');
            if (hid && frm) {
                hid.value = e;
                frm.submit();
            }
        });
    }
    function aplicarDespExtraToolbarVenta() {
        var bar = document.querySelector('.fila-botones-stock-toolbar');
        var btn = document.getElementById('btnVenta');
        if (!bar || !btn) return;
        bar.style.setProperty('--ancho-btn-venta', btn.offsetWidth + 'px');
    }
    aplicarDespExtraToolbarVenta();
    window.addEventListener('resize', aplicarDespExtraToolbarVenta);
    window.addEventListener('load', aplicarDespExtraToolbarVenta);
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

    function azucarTituloModalMovOp() {
        var h = document.getElementById('modalMovimientosOperacionTitulo');
        return h ? h.innerText.replace(/\s+/g, ' ').trim() : 'Movimientos de pago';
    }
    function togglePanelInformeMovOp() {
        var p = document.getElementById('panelInformeAccionesMovOp');
        if (!p) return;
        p.style.display = (p.style.display === 'none' || !p.style.display) ? 'block' : 'none';
    }

    // Abrir modal de movimientos por operación al hacer click en columna OP
    function abrirModalMovimientosOperacion(operacion) {
        if (!operacion || operacion === '0' || operacion === '') {
            alert('No hay operación asociada.');
            return;
        }
        document.getElementById('modalOpNumero').textContent = operacion;
        var wrapMovOp = document.getElementById('wrapImpresionMovOp');
        if (wrapMovOp) wrapMovOp.setAttribute('data-operacion', String(parseInt(operacion, 10) || 0));
        document.getElementById('modalMovimientosOperacion').classList.add('activo');
        var panelInf = document.getElementById('panelInformeAccionesMovOp');
        if (panelInf) panelInf.style.display = 'none';
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
                var btnLeerPdf = document.getElementById('btnLeerPdfEcheq');
                var formCobro = document.getElementById('formNuevoCobroOperacion');
                if (btnNuevoCobro && usuarioId) {
                    btnNuevoCobro.style.display = 'inline-block';
                    if (btnLeerPdf) btnLeerPdf.style.display = 'inline-block';
                    var btnPegarPago = document.getElementById('btnPegarPago');
                    if (btnPegarPago) btnPegarPago.style.display = 'inline-block';
                    // Guardar datos de la operación para usar en el formulario
                    btnNuevoCobro.dataset.usuarioId = usuarioId;
                    btnNuevoCobro.dataset.operacion = operacion;
                    btnNuevoCobro.onclick = function() {
                        if (formCobro) {
                            formCobro.style.display = 'block';
                            document.getElementById('cobro_usuario_id').value = usuarioId;
                            document.getElementById('cobro_operacion').value = operacion;
                            document.getElementById('cobro_referencia').value = 'OP N° ' + operacion;
                            document.getElementById('cobro_comprobante').value = 'CHEQUE/ECHEQ';
                            var datosFoto = window.datosFotoPagoPendientes;
                            if (datosFoto) {
                                document.getElementById('cobro_fecha').value = datosFoto.fecha_pago || new Date().toISOString().split('T')[0];
                                document.getElementById('cobro_concepto').value = datosFoto.concepto_sugerido || 'COBRO VTA AZUCAR: ';
                                document.getElementById('cobro_monto').value = datosFoto.monto || '';
                                window.datosFotoPagoPendientes = null;
                            } else {
                                document.getElementById('cobro_fecha').value = new Date().toISOString().split('T')[0];
                                document.getElementById('cobro_concepto').value = 'COBRO VTA AZUCAR: ';
                                document.getElementById('cobro_monto').value = '';
                            }
                            document.getElementById('msgCobroOperacion').style.display = 'none';
                            formCobro.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                            setTimeout(function() {
                                var conceptoEl = document.getElementById('cobro_concepto');
                                conceptoEl.focus();
                                conceptoEl.setSelectionRange(conceptoEl.value.length, conceptoEl.value.length);
                            }, 50);
                        }
                    };
                } else {
                    if (btnNuevoCobro) btnNuevoCobro.style.display = 'none';
                    if (btnLeerPdf) btnLeerPdf.style.display = 'none';
                    var btnPegarP = document.getElementById('btnPegarPago');
                    if (btnPegarP) btnPegarP.style.display = 'none';
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

    function abrirModalMovimientosOrden(orden, linea) {
        orden = parseInt(orden, 10) || 0;
        linea = parseInt(linea, 10) || 1;
        if (orden < 1) {
            alert('Orden inválida.');
            return;
        }
        document.getElementById('modalOrdenNumero').textContent = orden + (linea === 2 ? ' (Línea 2)' : '');
        document.getElementById('modalMovimientosOrden').classList.add('activo');
        var tbody = document.getElementById('tablaMovimientosOrden');
        tbody.innerHTML = '<tr><td colspan="7" style="text-align:center; padding:30px; color:gray;">Cargando...</td></tr>';
        var url = 'obtener_movimientos_orden.php?orden=' + encodeURIComponent(orden) + '&linea=' + encodeURIComponent(linea);
        fetch(url)
            .then(function(response) {
                if (!response.ok) throw new Error('Error al cargar movimientos');
                return response.text();
            })
            .then(function(html) {
                tbody.innerHTML = html;
            })
            .catch(function(error) {
                tbody.innerHTML = '<tr><td colspan="7" style="text-align:center; padding:30px; color:red;">Error al cargar movimientos: ' + esc(error.message) + '</td></tr>';
            });
    }

    function cerrarModalMovimientosOrden() {
        document.getElementById('modalMovimientosOrden').classList.remove('activo');
    }

    function abrirInformeOperacionesOperador() {
        var nombreOper = '';
        var nomEl = document.getElementById('modalOperadorNombre');
        if (nomEl) nombreOper = nomEl.textContent.replace(/\s+/g, ' ').trim();
        var wrap = document.getElementById('wrapImpresionOpsOperador');
        if (!wrap) return;
        var tbl = wrap.querySelector('table');
        if (!tbl) {
            alert('No hay tabla de operaciones.');
            return;
        }
        var tbody0 = tbl.querySelector('tbody');
        if (tbody0 && (tbody0.textContent || '').indexOf('Cargando') !== -1) {
            alert('Espere a que carguen las operaciones.');
            return;
        }
        var copy = tbl.cloneNode(true);
        copy.querySelectorAll('thead tr').forEach(function(tr) {
            var ths = tr.querySelectorAll('th');
            if (ths.length >= 4) ths[ths.length - 1].remove();
        });
        copy.querySelectorAll('tbody tr').forEach(function(tr) {
            var tds = tr.querySelectorAll('td');
            if (tds.length >= 4) tds[tds.length - 1].remove();
        });
        var holder = document.getElementById('wrapInformeOperadorContenido');
        if (holder) holder.innerHTML = copy.outerHTML;
        var spanNom = document.getElementById('informeOperadorNombreMostrar');
        if (spanNom) spanNom.textContent = nombreOper || '—';
        document.getElementById('modalInformeOperador').classList.add('activo');
    }

    function cerrarModalInformeOperador() {
        var m = document.getElementById('modalInformeOperador');
        if (m) m.classList.remove('activo');
    }

    function tituloInformeOperador() {
        return 'Informe — Operaciones del operador';
    }

    function abrirModalOperacionesOperador(operadorId, operadorNombre) {
        cerrarModalInformeOperador();
        document.getElementById('modalOperadorNombre').textContent = operadorNombre || 'Operador';
        var wrapInf = document.getElementById('wrapInformeOperadorExport');
        if (wrapInf) wrapInf.setAttribute('data-operador-id', String(parseInt(operadorId, 10) || 0));
        document.getElementById('modalOperacionesOperador').classList.add('activo');
        var tbody = document.getElementById('tablaOperacionesOperador');
        tbody.innerHTML = '<tr><td colspan="4" style="text-align:center; padding:30px; color:gray;">Cargando...</td></tr>';
        fetch('obtener_operaciones_operador.php?operador_id=' + encodeURIComponent(operadorId))
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (!data.ok) {
                    tbody.innerHTML = '<tr><td colspan="4" style="text-align:center; padding:30px; color:red;">' + esc(data.error || 'Error') + '</td></tr>';
                    return;
                }
                var ops = data.operaciones || [];
                if (ops.length === 0) {
                    tbody.innerHTML = '<tr><td colspan="4" style="text-align:center; padding:30px; color:gray;">No hay operaciones para este operador.</td></tr>';
                    return;
                }
                var html = '';
                var totalSaldo = 0;
                ops.forEach(function(op) {
                    var saldo = parseFloat(op.saldo) || 0;
                    totalSaldo += saldo;
                    var colorSaldo = saldo >= 0 ? '#28a745' : '#dc3545';
                    var saldoStr = '$ ' + saldo.toLocaleString('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                    html += '<tr><td class="al-cen">' + esc(op.operacion) + '</td><td class="al-izq">' + esc(op.vendida_a || '—') + '</td><td class="al-der" style="color:' + colorSaldo + '; font-weight:bold;">' + saldoStr + '</td><td><a href="#" class="link-operador" onclick="event.preventDefault(); cerrarModalOperacionesOperador(); abrirModalMovimientosOperacion(' + op.operacion + '); return false;">Ver movimientos</a></td></tr>';
                });
                var colorTotal = totalSaldo >= 0 ? '#28a745' : '#dc3545';
                var totalStr = totalSaldo.toLocaleString('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                html += '<tr style="background:#f8f9fa; font-weight:bold; border-top:2px solid #007bff;"><td colspan="2" class="al-der" style="padding:8px;">TOTAL:</td><td class="al-der" style="padding:8px; color:' + colorTotal + ';">$ ' + totalStr + '</td><td></td></tr>';
                tbody.innerHTML = html;
            })
            .catch(function(err) {
                tbody.innerHTML = '<tr><td colspan="4" style="text-align:center; padding:30px; color:red;">Error al cargar: ' + esc(err.message) + '</td></tr>';
            });
    }
    function cerrarModalOperacionesOperador() {
        document.getElementById('modalOperacionesOperador').classList.remove('activo');
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
        
        // Flujo con Enter: concepto -> monto -> guardar
        var campoConcepto = document.getElementById('cobro_concepto');
        if (campoConcepto) {
            campoConcepto.addEventListener('keydown', function(e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    document.getElementById('cobro_monto').focus();
                }
            });
        }
        var campoMonto = document.getElementById('cobro_monto');
        if (campoMonto) {
            campoMonto.addEventListener('keydown', function(e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    document.getElementById('btnGuardarCobroOperacion').focus();
                }
            });
        }
        var btnGuardarCobro = document.getElementById('btnGuardarCobroOperacion');
        if (btnGuardarCobro) {
            btnGuardarCobro.addEventListener('keydown', function(e) {
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
        // Botón Leer PDF ECheq
        var btnLeerPdfEcheq = document.getElementById('btnLeerPdfEcheq');
        var inputPdfEcheq = document.getElementById('inputPdfEcheq');
        if (btnLeerPdfEcheq && inputPdfEcheq) {
            btnLeerPdfEcheq.addEventListener('click', function() {
                inputPdfEcheq.value = '';
                inputPdfEcheq.click();
            });
            inputPdfEcheq.addEventListener('change', function() {
                var file = this.files[0];
                if (!file || file.type !== 'application/pdf') {
                    alert('Seleccione un archivo PDF.');
                    return;
                }
                var formData = new FormData();
                formData.append('pdf', file);
                btnLeerPdfEcheq.disabled = true;
                btnLeerPdfEcheq.textContent = 'Leyendo...';
                fetch('extraer_echeq_pdf.php', { method: 'POST', body: formData })
                    .then(function(r) { return r.json(); })
                    .then(function(data) {
                        btnLeerPdfEcheq.disabled = false;
                        btnLeerPdfEcheq.textContent = 'Leer PDF ECheq';
                        if (!data.ok) {
                            alert(data.error || 'Error al leer el PDF');
                            return;
                        }
                        var formCobroEl = document.getElementById('formNuevoCobroOperacion');
                        var btnNuevo = document.getElementById('btnNuevoCobroOperacion');
                        if (formCobroEl && btnNuevo && btnNuevo.dataset.usuarioId && btnNuevo.dataset.operacion) {
                            formCobroEl.style.display = 'block';
                            document.getElementById('cobro_usuario_id').value = btnNuevo.dataset.usuarioId;
                            document.getElementById('cobro_operacion').value = btnNuevo.dataset.operacion;
                            document.getElementById('cobro_fecha').value = data.fecha_pago || new Date().toISOString().split('T')[0];
                            document.getElementById('cobro_concepto').value = data.concepto_sugerido || 'COBRO VTA AZUCAR: ';
                            document.getElementById('cobro_comprobante').value = 'CHEQUE/ECHEQ';
                            document.getElementById('cobro_referencia').value = 'OP N° ' + btnNuevo.dataset.operacion;
                            document.getElementById('cobro_monto').value = data.monto || '';
                            document.getElementById('msgCobroOperacion').style.display = 'none';
                            formCobroEl.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                            setTimeout(function() {
                                var montoEl = document.getElementById('cobro_monto');
                                if (data.monto) montoEl.focus();
                                else document.getElementById('cobro_concepto').focus();
                            }, 50);
                        } else {
                            alert('Abra primero una operación con movimientos.');
                        }
                    })
                    .catch(function(err) {
                        btnLeerPdfEcheq.disabled = false;
                        btnLeerPdfEcheq.textContent = 'Leer PDF ECheq';
                        alert('Error: ' + (err.message || 'No se pudo procesar el PDF'));
                    });
            });
        }
        // Botón Pegar Pago
        var btnPegarPago = document.getElementById('btnPegarPago');
        var modalPegarPago = document.getElementById('modalPegarPago');
        var zonaPegarPago = document.getElementById('zonaPegarPago');
        var msgPegarPago = document.getElementById('msgPegarPago');
        var btnProcesarPegar = document.getElementById('btnProcesarPegarPago');
        function cerrarModalPegarPago() {
            if (modalPegarPago) modalPegarPago.classList.remove('activo');
            if (zonaPegarPago) { zonaPegarPago.innerHTML = 'Pegue aquí la imagen…'; zonaPegarPago.dataset.imageData = ''; }
            if (msgPegarPago) { msgPegarPago.style.display = 'none'; msgPegarPago.textContent = ''; }
        }
        window.cerrarModalPegarPago = cerrarModalPegarPago;
        function aplicarDatosPagoACobro(data) {
            var formCobroEl = document.getElementById('formNuevoCobroOperacion');
            var btnNuevo = document.getElementById('btnNuevoCobroOperacion');
            if (!formCobroEl || !btnNuevo || !btnNuevo.dataset.usuarioId || !btnNuevo.dataset.operacion) {
                alert('Abra primero una operación con movimientos.');
                return;
            }
            formCobroEl.style.display = 'block';
            document.getElementById('cobro_usuario_id').value = btnNuevo.dataset.usuarioId;
            document.getElementById('cobro_operacion').value = btnNuevo.dataset.operacion;
            document.getElementById('cobro_fecha').value = data.fecha_pago || new Date().toISOString().split('T')[0];
            document.getElementById('cobro_concepto').value = data.concepto_sugerido || 'COBRO VTA AZUCAR: ';
            document.getElementById('cobro_comprobante').value = 'CHEQUE/ECHEQ';
            document.getElementById('cobro_referencia').value = 'OP N° ' + btnNuevo.dataset.operacion;
            document.getElementById('cobro_monto').value = data.monto || '';
            document.getElementById('msgCobroOperacion').style.display = 'none';
            formCobroEl.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            setTimeout(function() {
                if (data.monto) document.getElementById('cobro_monto').focus();
                else document.getElementById('cobro_concepto').focus();
            }, 50);
        }
        if (btnPegarPago && modalPegarPago && zonaPegarPago) {
            btnPegarPago.addEventListener('click', function() {
                cerrarModalPegarPago();
                modalPegarPago.classList.add('activo');
                zonaPegarPago.focus();
            });
            zonaPegarPago.addEventListener('paste', function(e) {
                var items = e.clipboardData && e.clipboardData.items;
                if (!items) return;
                for (var i = 0; i < items.length; i++) {
                    if (items[i].type.indexOf('image') !== -1) {
                        e.preventDefault();
                        var file = items[i].getAsFile();
                        if (!file) return;
                        zonaPegarPago.innerHTML = '<span style="color:#666;">Procesando OCR…</span>';
                        if (msgPegarPago) { msgPegarPago.style.display = 'none'; msgPegarPago.textContent = ''; }
                        if (typeof Tesseract !== 'undefined') {
                            Tesseract.recognize(file, 'spa+eng', { logger: function(m) {} }).then(function(result) {
                                var texto = (result && result.data && result.data.text) ? result.data.text : '';
                                if (!texto || texto.trim().length < 10) {
                                    zonaPegarPago.innerHTML = 'Pegue aquí la imagen…';
                                    if (msgPegarPago) { msgPegarPago.textContent = 'No se pudo extraer texto de la imagen.'; msgPegarPago.style.background = '#f8d7da'; msgPegarPago.style.display = 'block'; }
                                    return;
                                }
                                var fd = new FormData();
                                fd.append('texto', texto);
                                fetch('extraer_echeq_texto.php', { method: 'POST', body: fd })
                                    .then(function(r) { return r.json(); })
                                    .then(function(data) {
                                        cerrarModalPegarPago();
                                        if (data.ok) aplicarDatosPagoACobro(data);
                                        else alert(data.error || 'Error al procesar');
                                    })
                                    .catch(function() { alert('Error de conexión'); cerrarModalPegarPago(); });
                            }).catch(function() {
                                zonaPegarPago.innerHTML = 'Pegue aquí la imagen…';
                                if (msgPegarPago) { msgPegarPago.textContent = 'Error en OCR.'; msgPegarPago.style.display = 'block'; }
                            });
                        } else {
                            zonaPegarPago.innerHTML = 'Pegue aquí la imagen…';
                            if (msgPegarPago) { msgPegarPago.textContent = 'Tesseract.js no cargó.'; msgPegarPago.style.display = 'block'; }
                        }
                        return;
                    }
                }
            });
            zonaPegarPago.addEventListener('focus', function() {
                if (this.textContent.trim() === 'Pegue aquí la imagen…') this.innerHTML = '';
            });
            zonaPegarPago.addEventListener('blur', function() {
                if (this.textContent.trim() === '') this.innerHTML = 'Pegue aquí la imagen…';
            });
        }
        if (btnProcesarPegar && zonaPegarPago) {
            btnProcesarPegar.addEventListener('click', function() {
                var textoPegado = zonaPegarPago.innerText.replace(/Pegue aquí la imagen…/g, '').trim();
                if (textoPegado.length >= 10) {
                    var fd = new FormData();
                    fd.append('texto', textoPegado);
                    fetch('extraer_echeq_texto.php', { method: 'POST', body: fd })
                        .then(function(r) { return r.json(); })
                        .then(function(data) {
                            cerrarModalPegarPago();
                            if (data.ok) aplicarDatosPagoACobro(data);
                            else alert(data.error || 'Error');
                        })
                        .catch(function() { alert('Error de conexión'); });
                } else {
                    if (msgPegarPago) { msgPegarPago.textContent = 'Pegue una imagen (Ctrl+V) o pegue texto del comprobante y pulse Procesar.'; msgPegarPago.style.background = '#fff3cd'; msgPegarPago.style.display = 'block'; }
                }
            });
        }
    })();

    // Agregar event listeners a las celdas de operación (event delegation para funcionar con filas dinámicas)
    (function() {
        function manejarClickOP(e) {
            var celdaOp = e.target.closest('.col-operacion');
            if (celdaOp) {
                var tr = celdaOp.closest('tr');
                var operacion = (tr && tr.dataset.operacion) ? tr.dataset.operacion : celdaOp.textContent.replace(/Mov-Cobro/g, '').trim();
                if (operacion && operacion !== '' && operacion !== '0') {
                    e.stopPropagation();
                    e.preventDefault();
                    abrirModalMovimientosOperacion(operacion);
                }
                return;
            }
            var celdaOperador = e.target.closest('.col-operador');
            if (celdaOperador) {
                var link = celdaOperador.querySelector('.link-operador');
                if (link) {
                    var oid = link.getAttribute('data-operador-id');
                    var onom = link.getAttribute('data-operador-apellido') || '';
                    if (oid && oid !== '0') {
                        e.stopPropagation();
                        e.preventDefault();
                        abrirModalOperacionesOperador(parseInt(oid, 10), onom);
                    }
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

    // Botón Foto Pago: en celular abre cámara, captura imagen, OCR y extrae datos
    (function() {
        var btnFotoPago = document.getElementById('btnFotoPago');
        var inputFotoPago = document.getElementById('inputFotoPago');
        var modalFotoPago = document.getElementById('modalFotoPago');
        var resumenFotoPago = document.getElementById('resumenFotoPago');
        function cerrarModalFotoPago() {
            if (modalFotoPago) modalFotoPago.classList.remove('activo');
        }
        function cancelarModalFotoPago() {
            window.datosFotoPagoPendientes = null;
            if (resumenFotoPago) resumenFotoPago.innerHTML = '';
            if (modalFotoPago) modalFotoPago.classList.remove('activo');
        }
        if (btnFotoPago && inputFotoPago) {
            btnFotoPago.addEventListener('click', function() {
                inputFotoPago.value = '';
                inputFotoPago.click();
            });
            inputFotoPago.addEventListener('change', function() {
                var file = this.files[0];
                if (!file || !file.type.startsWith('image/')) {
                    alert('Seleccione una imagen.');
                    return;
                }
                btnFotoPago.disabled = true;
                btnFotoPago.textContent = 'Procesando…';
                if (typeof Tesseract !== 'undefined') {
                    Tesseract.recognize(file, 'spa+eng', { logger: function() {} }).then(function(result) {
                        var texto = (result && result.data && result.data.text) ? result.data.text : '';
                        btnFotoPago.disabled = false;
                        btnFotoPago.textContent = 'Foto Pago';
                        if (!texto || texto.trim().length < 10) {
                            alert('No se pudo extraer texto de la imagen.');
                            return;
                        }
                        var fd = new FormData();
                        fd.append('texto', texto);
                        fetch('extraer_echeq_texto.php', { method: 'POST', body: fd })
                            .then(function(r) { return r.json(); })
                            .then(function(data) {
                                if (data.ok) {
                                    window.datosFotoPagoPendientes = data;
                                    var html = '';
                                    if (data.monto != null) html += '<strong>Monto:</strong> ' + data.monto + '<br>';
                                    if (data.emisor) html += '<strong>Emisor:</strong> ' + data.emisor + '<br>';
                                    if (data.cuit) html += '<strong>CUIT:</strong> ' + data.cuit + '<br>';
                                    if (data.concepto_sugerido) html += '<strong>Concepto:</strong> ' + data.concepto_sugerido + '<br>';
                                    if (data.fecha_pago) html += '<strong>Fecha pago:</strong> ' + data.fecha_pago + '<br>';
                                    if (data.nro_echeq) html += '<strong>N° Echeq:</strong> ' + data.nro_echeq + '<br>';
                                    if (resumenFotoPago) resumenFotoPago.innerHTML = html || 'Datos extraídos.';
                                    if (modalFotoPago) modalFotoPago.classList.add('activo');
                                } else {
                                    alert(data.error || 'Error al extraer datos.');
                                }
                            })
                            .catch(function() {
                                btnFotoPago.disabled = false;
                                btnFotoPago.textContent = 'Foto Pago';
                                alert('Error de conexión.');
                            });
                    }).catch(function() {
                        btnFotoPago.disabled = false;
                        btnFotoPago.textContent = 'Foto Pago';
                        alert('Error en OCR.');
                    });
                } else {
                    btnFotoPago.disabled = false;
                    btnFotoPago.textContent = 'Foto Pago';
                    alert('Tesseract.js no cargó.');
                }
            });
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

                        var ordenLineaCount = {};
                        datos.forEach(function(r) {
                            var o = parseInt(r.orden, 10) || 0;
                            var l = parseInt(r.linea, 10) || 1;
                            var k = o + '_' + l;
                            ordenLineaCount[k] = (ordenLineaCount[k] || 0) + 1;
                        });

                        var tbody = document.querySelector('.tabla-azucar tbody');
                        tbody.innerHTML = '';
                        if (datos.length === 0) {
                            tbody.innerHTML = '<tr><td colspan="18" style="text-align:center;padding:15px;color:#666;">No se extrajeron registros.</td></tr>';
                        } else {
                            datos.forEach(function(r, i) {
                                var tr = document.createElement('tr');
                                tr.className = i === 0 ? 'fila-seleccionada' : '';
                                var v = function(k, def) { return (r[k] != null && r[k] !== '') ? r[k] : (def || ''); };
                                var ordenVal = parseInt(v('orden', '0'), 10) || 0;
                                var lineaVal = parseInt(v('linea', '1'), 10) || 1;
                                var cantVal = parseInt(v('cantidad', '0'), 10) || 0;
                                var cantVtaVal = parseInt(v('cant_vta', '0'), 10) || 0;
                                var parcial = cantVtaVal > 0 && cantVal !== cantVtaVal;
                                var multiOp = (ordenLineaCount[ordenVal + '_' + lineaVal] || 0) > 1;
                                var mostrarLinkOrd = parcial || multiOp;
                                var tdOrden = mostrarLinkOrd
                                    ? '<a href="#" class="link-orden" data-orden="' + ordenVal + '" data-linea="' + lineaVal + '" onclick="event.stopPropagation(); abrirModalMovimientosOrden(' + ordenVal + ', ' + lineaVal + '); return false;" style="text-decoration:underline; color:inherit;">' + ordenVal + '</a>'
                                    : String(ordenVal);
                                tr.innerHTML =
                                    '<td class="col-fecha">' + esc(v('fecha')) + '</td>' +
                                    '<td class="col-l">' + esc(v('linea', '0')) + '</td>' +
                                    '<td class="col-articulo">' + esc(v('articulo')) + '</td>' +
                                    '<td class="col-orden">' + tdOrden + '</td>' +
                                    '<td class="col-cantidad">' + (parseInt(v('cantidad'), 10) || 0) + '</td>' +
                                    '<td class="col-deposito">' + esc(v('deposito')) + '</td>' +
                                    '<td class="col-operacion" data-operacion="' + (v('operacion') || '') + '">' + (function(){ var op = parseInt(v('operacion'), 10) || 0; return op ? op + ' <button type="button" class="btn-mov-cobro btn btn-secondary" onclick="event.stopPropagation(); abrirModalMovimientosOperacion(\'' + op + '\');" title="Movimientos de cobro">Mov-Cobro</button>' : ''; })() + '</td>' +
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
                                    '<td class="col-nremt ' + (v('n_remt') ? '' : 'sin-dato') + '">' + (v('n_remt') && r.id ? '<a href="imprimir_remito.php?id=' + r.id + '" target="_blank" class="link-remito" onclick="event.stopPropagation();" style="color:#0066cc;text-decoration:underline;">' + esc(v('n_remt')) + '</a> <button type="button" class="btn-imprimir-remito btn btn-secondary" onclick="event.stopPropagation(); window.open(\'imprimir_remito.php?id=' + r.id + '\', \'_blank\');" title="Imprimir remito">Imprimir</button>' : esc(v('n_remt'))) + '</td>';
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
            var rows = Array.prototype.filter.call(document.querySelectorAll('.tabla-azucar tbody tr[data-id]'), function(tr) {
                return tr.style.display !== 'none';
            });
            var current = document.querySelector('.tabla-azucar tbody tr.fila-seleccionada');
            if (!rows.length) return;
            var idx = current ? rows.indexOf(current) : -1;
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
