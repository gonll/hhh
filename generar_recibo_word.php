<?php
include 'db.php';
include 'crear_tabla_cuentas_arriendo.php';
include 'verificar_sesion.php';
include 'helpers_contrato.php';

if (!isset($_GET['id'])) {
    die("ID no recibido");
}

$movimiento_id = (int)$_GET['id'];

// Obtener datos del movimiento (incluir consorcio del usuario)
$sql_mov = "SELECT c.movimiento_id, c.usuario_id, c.fecha, c.concepto, c.comprobante, c.referencia, c.monto,
                   c.arriendo_id,
                   u.apellido AS usuario_nombre,
                   u.consorcio AS usuario_consorcio
            FROM cuentas c
            INNER JOIN usuarios u ON u.id = c.usuario_id
            WHERE c.movimiento_id = $movimiento_id LIMIT 1";
$res_mov = mysqli_query($conexion, $sql_mov);
if (!$res_mov || mysqli_num_rows($res_mov) == 0) {
    die("Movimiento no encontrado");
}
$mov = mysqli_fetch_assoc($res_mov);

$usuario_id = (int)$mov['usuario_id'];
$es_retiro = ((float)($mov['monto'] ?? 0)) < 0;
$es_usuario_finca = (stripos($mov['usuario_nombre'] ?? '', 'FINCA') !== false);
$firmante_consorcio = null;
$firmante_arriendo = null;  // Para arriendos: RETIRO=firmante propietario, INGRESO=firmante arrendatario

$arriendo_id = isset($mov['arriendo_id']) ? (int)$mov['arriendo_id'] : 0;
$comprobante = strtoupper(trim($mov['comprobante'] ?? ''));

// ARRIENDO: prioridad absoluta. Si concepto indica pago de arriendo, Recibí de = arrendatario (Hugo). Sin mezclar con consorcio.
$recibi_de = strtoupper(trim($mov['usuario_nombre']));
$es_pago_arriendo = ($comprobante === 'PGO ARRIENDO' || $comprobante === 'PRECIO DE LA BOLSA') || (stripos($mov['concepto'] ?? '', 'PAGO') !== false && (stripos($mov['concepto'] ?? '', 'ARRIENDO') !== false || stripos($mov['concepto'] ?? '', 'PRECIO REF') !== false));
if ($es_pago_arriendo && (float)($mov['monto'] ?? 0) > 0) {
    $conc = strtoupper($mov['concepto'] ?? '');
    $where_ar = '';
    if (stripos($conc, 'ESPINILLO') !== false || stripos($conc, 'ESPINILL') !== false || preg_match('/195\.093|96\.909|196\.114/', $conc)) {
        $where_ar = " AND (UPPER(COALESCE(a.descripcion_finca,'')) LIKE '%ESPINILL%' OR UPPER(COALESCE(a.descripcion_finca,'')) LIKE '%195.093%')";
    } else {
        $where_ar = " AND (UPPER(u_prop.apellido) LIKE '%HERRERA%' AND UPPER(u_prop.apellido) LIKE '%BEATRIZ%')";
    }
    $r_arr = mysqli_query($conexion, "SELECT u_arr.apellido AS arrendatario_nombre, u_prop.apellido AS propietario_nombre
        FROM arriendos a
        INNER JOIN usuarios u_prop ON u_prop.id = a.propietario_id
        INNER JOIN usuarios u_arr ON u_arr.id = a.arrendatario_id
        WHERE 1=1 $where_ar ORDER BY a.id DESC LIMIT 1");
    if ($r_arr && $row_arr = mysqli_fetch_assoc($r_arr)) {
        // INGRESO: al revés de RETIRO → Recibí de = propietario, Firmante = arrendatario
        $recibi_de = strtoupper(trim($row_arr['propietario_nombre']));
        $firmante_arriendo = strtoupper(trim($row_arr['arrendatario_nombre']));
    }
}

// Arriendos: si el usuario es PROPIETARIO (arrendador), Recibí de = arrendatario de la tabla arriendos
$arriendo_propietario = null;
$arriendo_arrendatario = null;
$arriendo_usuario_es_propietario_o_apoderado = null;
// SIEMPRE primero: si usuario es propietario O apoderado de un arriendo, obtener arrendatario (arriendos.arrendatario_id)
$r_prop_ar = mysqli_query($conexion, "SELECT u_prop.apellido AS propietario_nombre, u_arr.apellido AS arrendatario_nombre
    FROM arriendos a
    INNER JOIN usuarios u_prop ON u_prop.id = a.propietario_id
    INNER JOIN usuarios u_arr ON u_arr.id = a.arrendatario_id
    WHERE a.propietario_id = $usuario_id LIMIT 1");
if ($r_prop_ar && $row_pa = mysqli_fetch_assoc($r_prop_ar)) {
    $arriendo_propietario = strtoupper(trim($row_pa['propietario_nombre']));
    $arriendo_arrendatario = strtoupper(trim($row_pa['arrendatario_nombre']));
    $arriendo_usuario_es_propietario_o_apoderado = true;
}
if (!$arriendo_propietario || !$arriendo_arrendatario) {
    // Si no es propietario: buscar como APODERADO (ej. Daniel Valdez gestiona cuenta, arrendatario es Hugo Herrera)
    $r_apod_ar = mysqli_query($conexion, "SELECT u_prop.apellido AS propietario_nombre, u_arr.apellido AS arrendatario_nombre
        FROM arriendos a
        INNER JOIN usuarios u_prop ON u_prop.id = a.propietario_id
        INNER JOIN usuarios u_arr ON u_arr.id = a.arrendatario_id
        WHERE a.apoderado_id = $usuario_id LIMIT 1");
    if ($r_apod_ar && $row_aa = mysqli_fetch_assoc($r_apod_ar)) {
        $arriendo_propietario = strtoupper(trim($row_aa['propietario_nombre']));
        $arriendo_arrendatario = strtoupper(trim($row_aa['arrendatario_nombre']));
        $arriendo_usuario_es_propietario_o_apoderado = true;
    }
}
if (!$arriendo_propietario || !$arriendo_arrendatario) {
if ($arriendo_id > 0) {
    $r_ar = mysqli_query($conexion, "SELECT a.propietario_id, a.apoderado_id, a.arrendatario_id, u_prop.apellido AS propietario_nombre, u_arr.apellido AS arrendatario_nombre
        FROM arriendos a
        INNER JOIN usuarios u_prop ON u_prop.id = a.propietario_id
        INNER JOIN usuarios u_arr ON u_arr.id = a.arrendatario_id
        WHERE a.id = $arriendo_id LIMIT 1");
    if ($r_ar && $row = mysqli_fetch_assoc($r_ar)) {
        $arriendo_propietario = strtoupper(trim($row['propietario_nombre']));
        $arriendo_arrendatario = strtoupper(trim($row['arrendatario_nombre']));
        $arriendo_usuario_es_propietario_o_apoderado = ((int)$row['propietario_id']) === $usuario_id || ((int)($row['apoderado_id'] ?? 0)) === $usuario_id;
    }
} elseif ($comprobante === 'PGO ARRIENDO') {
    // Prioridad: si usuario es propietario, usar su arriendo (arrendatario_id de tabla arriendos)
    $r_ar = mysqli_query($conexion, "SELECT a.propietario_id, a.apoderado_id, a.arrendatario_id, u_prop.apellido AS propietario_nombre, u_arr.apellido AS arrendatario_nombre
        FROM arriendos a
        INNER JOIN usuarios u_prop ON u_prop.id = a.propietario_id
        INNER JOIN usuarios u_arr ON u_arr.id = a.arrendatario_id
        WHERE a.propietario_id = $usuario_id OR a.apoderado_id = $usuario_id OR a.arrendatario_id = $usuario_id
        ORDER BY CASE WHEN a.propietario_id = $usuario_id THEN 0 WHEN a.apoderado_id = $usuario_id THEN 1 ELSE 2 END, a.id DESC LIMIT 1");
    if ($r_ar && $row = mysqli_fetch_assoc($r_ar)) {
        $arriendo_propietario = strtoupper(trim($row['propietario_nombre']));
        $arriendo_arrendatario = strtoupper(trim($row['arrendatario_nombre']));
        $arriendo_usuario_es_propietario_o_apoderado = ((int)$row['propietario_id']) === $usuario_id || ((int)($row['apoderado_id'] ?? 0)) === $usuario_id;
    }
} else {
    // Usuario en arriendos sin arriendo_id: PRIMERO buscar donde usuario es PROPIETARIO (arrendatario_id de arriendos)
    $r_prop = mysqli_query($conexion, "SELECT u_prop.apellido AS propietario_nombre, u_arr.apellido AS arrendatario_nombre
        FROM arriendos a
        INNER JOIN usuarios u_prop ON u_prop.id = a.propietario_id
        INNER JOIN usuarios u_arr ON u_arr.id = a.arrendatario_id
        WHERE a.propietario_id = $usuario_id LIMIT 1");
    if ($r_prop && $row_p = mysqli_fetch_assoc($r_prop)) {
        $arriendo_propietario = strtoupper(trim($row_p['propietario_nombre']));
        $arriendo_arrendatario = strtoupper(trim($row_p['arrendatario_nombre']));
        $arriendo_usuario_es_propietario_o_apoderado = true;
    } else {
        // Si no es propietario: buscar como apoderado o arrendatario
        $r_ar = mysqli_query($conexion, "SELECT a.propietario_id, a.apoderado_id, a.arrendatario_id, u_prop.apellido AS propietario_nombre, u_arr.apellido AS arrendatario_nombre
            FROM arriendos a
            INNER JOIN usuarios u_prop ON u_prop.id = a.propietario_id
            INNER JOIN usuarios u_arr ON u_arr.id = a.arrendatario_id
            WHERE a.apoderado_id = $usuario_id OR a.arrendatario_id = $usuario_id
            ORDER BY CASE WHEN a.apoderado_id = $usuario_id THEN 0 ELSE 1 END, a.id DESC LIMIT 1");
        if ($r_ar && $row = mysqli_fetch_assoc($r_ar)) {
            $arriendo_propietario = strtoupper(trim($row['propietario_nombre']));
            $arriendo_arrendatario = strtoupper(trim($row['arrendatario_nombre']));
            $arriendo_usuario_es_propietario_o_apoderado = ((int)($row['apoderado_id'] ?? 0)) === $usuario_id;
        }
    }
}
}  // fin if !arriendo_propietario
// Fallback: cuando el concepto indica pago de arriendo pero no encontramos arriendo por usuario.
// Arriendos: cuenta de Beatriz (propietaria), Recibí de = Hugo (arrendatario). Daniel Valdez NO participa en arriendos.
if (!$arriendo_propietario || !$arriendo_arrendatario) {
    $concepto_upper = strtoupper($mov['concepto'] ?? '');
    if (($comprobante === 'PGO ARRIENDO') || (strpos($concepto_upper, 'PAGO') !== false && (strpos($concepto_upper, 'ARRIENDO') !== false || strpos($concepto_upper, 'PRECIO REF') !== false))) {
        $where_extra = '';
        // BD tiene "el espinillo" (doble L). Concepto puede ser "ESPINILLO" o "ESPINILLO". LIKE %ESPINILL% matchea ambas.
        if (preg_match('/ESPINILL*O/i', $concepto_upper)) {
            $where_extra = " AND UPPER(COALESCE(a.descripcion_finca,'')) LIKE '%ESPINILL%'";
        }
        if ($where_extra === '' && preg_match('/\b(\d{2,}\.?\d{2,})\b/', $concepto_upper, $m_pad)) {
            $esc = mysqli_real_escape_string($conexion, $m_pad[1]);
            $where_extra = " AND (UPPER(COALESCE(a.descripcion_finca,'')) LIKE '%$esc%' OR UPPER(COALESCE(a.padron,'')) LIKE '%$esc%')";
        }
        if ($where_extra === '') {
            $where_extra = " AND (UPPER(u_prop.apellido) LIKE '%HERRERA%' AND UPPER(u_prop.apellido) LIKE '%BEATRIZ%')";
        }
        $r_ar_fb = mysqli_query($conexion, "SELECT u_prop.apellido AS propietario_nombre, u_arr.apellido AS arrendatario_nombre
            FROM arriendos a
            INNER JOIN usuarios u_prop ON u_prop.id = a.propietario_id
            INNER JOIN usuarios u_arr ON u_arr.id = a.arrendatario_id
            WHERE 1=1 $where_extra
            ORDER BY a.id DESC LIMIT 1");
        if ($r_ar_fb && $row_fb = mysqli_fetch_assoc($r_ar_fb)) {
            $arriendo_propietario = strtoupper(trim($row_fb['propietario_nombre']));
            $arriendo_arrendatario = strtoupper(trim($row_fb['arrendatario_nombre']));
            $arriendo_usuario_es_propietario_o_apoderado = true;
        }
    }
}

if ($es_retiro) {
    // RETIRO: firmante = titular; Recibí de = propietario, consorcio o Hector Hugo Herrera según tipo de cuenta
    // Si el retiro es para un consorcio (inquilino/propietario paga expensas), el recibo lo emite el consorcio
    // Solo es consorcio si el nombre COMIENZA con CONSORCIO (no si lo tiene entre paréntesis)
    $es_consorcio = (stripos(trim($mov['usuario_nombre'] ?? ''), 'CONSORCIO') === 0);
    $es_inquilino = false;
    $es_propietario = false;
    $es_finca = false;
    $propietario_de_inquilino = null;
    $consorcio_propietario = null;
    $consorcio_codigo = null;
    $consorcio_emite = null;

    if (!$es_consorcio) {
        // Prioridad: si el nombre incluye "(CONSORCIO XXX)", buscar consorcio correspondiente primero
        if (preg_match('/\(CONSORCIO\s+([^)]+)\)/i', $mov['usuario_nombre'] ?? '', $m_cons)) {
            $consorcio_del_nombre = trim($m_cons[1]);
            if ($consorcio_del_nombre !== '') {
                $cod_esc = mysqli_real_escape_string($conexion, $consorcio_del_nombre);
                $cod_like = str_replace(['%', '_'], ['\\%', '\\_'], $cod_esc);
                $r_cons = mysqli_query($conexion, "SELECT apellido FROM usuarios WHERE UPPER(apellido) LIKE 'CONSORCIO%' AND (UPPER(TRIM(COALESCE(consorcio,''))) = UPPER('$cod_esc') OR UPPER(apellido) LIKE CONCAT('%', UPPER('$cod_esc'), '%')) LIMIT 1");
                if ($r_cons && $row_cons = mysqli_fetch_assoc($r_cons)) {
                    $consorcio_emite = str_replace('EE UU', 'ESTADOS UNIDOS', strtoupper(trim($row_cons['apellido'])));
                } else {
                    // Fallback: construir nombre si no se encuentra en BD (ej. 101 -> CONSORCIO ESTADOS UNIDOS 101)
                    $nombre_fb = ($cod_esc === '101') ? 'CONSORCIO ESTADOS UNIDOS 101' : ('CONSORCIO ' . strtoupper($consorcio_del_nombre));
                    $consorcio_emite = str_replace('EE UU', 'ESTADOS UNIDOS', $nombre_fb);
                }
            }
        }
        if (!$consorcio_emite) {
            $r_inq = mysqli_query($conexion, "SELECT u_prop.apellido AS propietario_nombre, p.consorcio AS consorcio_cod
                FROM alquileres a
                INNER JOIN propiedades p ON p.propiedad_id = a.propiedad_id
                INNER JOIN usuarios u_prop ON u_prop.id = p.propietario_id
                WHERE a.estado = 'VIGENTE' AND (a.inquilino1_id = $usuario_id OR a.inquilino2_id = $usuario_id)
                LIMIT 1");
            if ($r_inq && $row_inq = mysqli_fetch_assoc($r_inq)) {
                $es_inquilino = true;
                $propietario_de_inquilino = strtoupper(trim($row_inq['propietario_nombre']));
                $consorcio_codigo = trim($row_inq['consorcio_cod'] ?? '');
            }
            if (!$es_inquilino) {
                $r_prop = mysqli_query($conexion, "SELECT p.consorcio FROM propiedades p WHERE p.propietario_id = $usuario_id AND p.consorcio IS NOT NULL AND TRIM(p.consorcio) != '' LIMIT 1");
                if ($r_prop && $row_prop = mysqli_fetch_assoc($r_prop)) {
                    $es_propietario = true;
                    $consorcio_propietario = strtoupper(trim($row_prop['consorcio']));
                    $consorcio_codigo = trim($row_prop['consorcio'] ?? '');
                }
                $r_finca = mysqli_query($conexion, "SELECT 1 FROM arriendos WHERE propietario_id = $usuario_id OR arrendatario_id = $usuario_id LIMIT 1");
                if ($r_finca && mysqli_num_rows($r_finca) > 0) $es_finca = true;
            }
            if ($consorcio_codigo !== '' && !$consorcio_emite) {
                $cod_esc = mysqli_real_escape_string($conexion, $consorcio_codigo);
                $r_cons = mysqli_query($conexion, "SELECT apellido FROM usuarios WHERE UPPER(apellido) LIKE '%CONSORCIO%' AND UPPER(TRIM(COALESCE(consorcio,''))) = UPPER('$cod_esc') LIMIT 1");
                if ($r_cons && $row_cons = mysqli_fetch_assoc($r_cons)) {
                    $consorcio_emite = str_replace('EE UU', 'ESTADOS UNIDOS', strtoupper(trim($row_cons['apellido'])));
                }
            }
        }
    }

    // Prioridad: usuario con "(CONSORCIO XXX)" en RETIRO → Recibí de = consorcio, Firmante = usuario
    if ($consorcio_emite && preg_match('/\(CONSORCIO\s+[^)]+\)/i', $mov['usuario_nombre'] ?? '')) {
        $recibi_de = $consorcio_emite;
        $firmante_consorcio = null;  // Firmante = usuario (se usa en lógica posterior)
    } elseif ($arriendo_propietario && $arriendo_arrendatario && $arriendo_usuario_es_propietario_o_apoderado) {
        // Arriendo RETIRO: arrendador retira; Recibí de = arrendatario, Firmante = propietario
        $recibi_de = $arriendo_arrendatario;
        $firmante_arriendo = $arriendo_propietario;
    } elseif ($consorcio_emite) {
        $recibi_de = $consorcio_emite;
        $firmante_consorcio = null;
    } elseif ($arriendo_propietario && $arriendo_arrendatario) {
        // Arriendo RETIRO (usuario arrendatario): Recibí de = propietario
        $recibi_de = $arriendo_propietario;
        $firmante_arriendo = $arriendo_arrendatario;
    } elseif ($es_consorcio) {
        $recibi_de = str_replace('EE UU', 'ESTADOS UNIDOS', strtoupper(trim($mov['usuario_nombre'])));
    } elseif ($es_inquilino && $propietario_de_inquilino) {
        $recibi_de = $propietario_de_inquilino;
    } elseif ($es_usuario_finca || $es_finca) {
        $recibi_de = 'HECTOR HUGO HERRERA';
    } elseif ($es_propietario && $consorcio_codigo !== '') {
        $cod_esc = mysqli_real_escape_string($conexion, $consorcio_codigo);
        $r_cons2 = mysqli_query($conexion, "SELECT apellido FROM usuarios WHERE UPPER(apellido) LIKE '%CONSORCIO%' AND UPPER(TRIM(COALESCE(consorcio,''))) = UPPER('$cod_esc') LIMIT 1");
        if ($r_cons2 && $row_c2 = mysqli_fetch_assoc($r_cons2)) {
            $recibi_de = str_replace('EE UU', 'ESTADOS UNIDOS', strtoupper(trim($row_c2['apellido'])));
        } else {
            $recibi_de = 'CONSORCIO ' . $consorcio_propietario;
        }
    }
} else {
    // INGRESO: si ya se detectó pago arriendo al inicio, no sobrescribir
    if (!$firmante_arriendo) {
    // Prioridad: usuario con "(CONSORCIO XXX)" en INGRESO → Recibí de = usuario, Firmante = consorcio (al revés de RETIRO)
    if (preg_match('/\(CONSORCIO\s+([^)]+)\)/i', $mov['usuario_nombre'] ?? '', $m_cons_ing)) {
        $recibi_de = strtoupper(trim($mov['usuario_nombre']));
        $consorcio_del_nombre = trim($m_cons_ing[1]);
        if ($consorcio_del_nombre !== '') {
            $cod_esc = mysqli_real_escape_string($conexion, $consorcio_del_nombre);
            $r_cons_ing = mysqli_query($conexion, "SELECT apellido FROM usuarios WHERE UPPER(apellido) LIKE 'CONSORCIO%' AND (UPPER(TRIM(COALESCE(consorcio,''))) = UPPER('$cod_esc') OR UPPER(apellido) LIKE CONCAT('%', UPPER('$cod_esc'), '%')) LIMIT 1");
            if ($r_cons_ing && $row_cons_ing = mysqli_fetch_assoc($r_cons_ing)) {
                $firmante_consorcio = str_replace('EE UU', 'ESTADOS UNIDOS', strtoupper(trim($row_cons_ing['apellido'])));
            } else {
                $nombre_fb = ($cod_esc === '101') ? 'CONSORCIO ESTADOS UNIDOS 101' : ('CONSORCIO ' . strtoupper($consorcio_del_nombre));
                $firmante_consorcio = str_replace('EE UU', 'ESTADOS UNIDOS', $nombre_fb);
            }
        }
    } elseif ($arriendo_propietario && $arriendo_arrendatario) {
        if ($arriendo_usuario_es_propietario_o_apoderado) {
            // INGRESO arrendador: al revés de RETIRO → Recibí de = propietario, Firmante = arrendatario
            $recibi_de = $arriendo_propietario;
            $firmante_arriendo = $arriendo_arrendatario;
        } else {
            // Usuario arrendatario recibe del propietario: Recibí de = arrendatario, Firmante = propietario
            $recibi_de = $arriendo_arrendatario;
            $firmante_arriendo = $arriendo_propietario;
        }
    } elseif ((stripos(trim($mov['usuario_nombre'] ?? ''), 'CONSORCIO') === 0)) {
        // Cuenta consorcio: Recibí de = usuario (quien pagó); extraer del concepto "PAGÓ X" si existe
        $es_consorcio_ingreso = true;
        $recibi_de = strtoupper(trim($mov['usuario_nombre']));
        if (preg_match('/PAG[ÓO]\s+([^-]+?)(?:\s*[-–]|$)/ui', $mov['concepto'] ?? '', $m_pago)) {
            $recibi_de = strtoupper(trim($m_pago[1]));
        } elseif (preg_match('/PAG[ÓO]\s+(.+)/ui', $mov['concepto'] ?? '', $m_pago2)) {
            $recibi_de = strtoupper(trim(preg_replace('/\s*-\s*Consorcio.*$/i', '', $m_pago2[1])));
        }
        // Firmante = consorcio (quien recibe)
        $firmante_consorcio = str_replace('EE UU', 'ESTADOS UNIDOS', strtoupper(trim($mov['usuario_nombre'])));
    } elseif (preg_match('/\(CONSORCIO\s+([^)]+)\)/i', $mov['usuario_nombre'] ?? '', $m_cons)) {
        // Usuario con "(CONSORCIO XXX)": para ALQUILERES de propiedades. Arriendo se maneja aparte al inicio.
        $recibi_de = strtoupper(trim($mov['usuario_nombre']));
        $r_inq_ar = mysqli_query($conexion, "SELECT p.propietario_id, u_prop.apellido AS propietario_nombre
            FROM alquileres a
            INNER JOIN propiedades p ON p.propiedad_id = a.propiedad_id
            INNER JOIN usuarios u_prop ON u_prop.id = p.propietario_id
            WHERE a.estado = 'VIGENTE' AND (a.inquilino1_id = $usuario_id OR a.inquilino2_id = $usuario_id)
            LIMIT 1");
        if ($r_inq_ar && $row_inq = mysqli_fetch_assoc($r_inq_ar)) {
            $propietario_id_ar = (int)$row_inq['propietario_id'];
            $r_ar_fb = mysqli_query($conexion, "SELECT u_prop.apellido AS propietario_nombre, u_arr.apellido AS arrendatario_nombre
                FROM arriendos a
                INNER JOIN usuarios u_prop ON u_prop.id = a.propietario_id
                INNER JOIN usuarios u_arr ON u_arr.id = a.arrendatario_id
                WHERE a.propietario_id = $propietario_id_ar LIMIT 1");
            if ($r_ar_fb && $row_ar_fb = mysqli_fetch_assoc($r_ar_fb)) {
                $recibi_de = strtoupper(trim($row_ar_fb['arrendatario_nombre']));
                $firmante_arriendo = strtoupper(trim($row_ar_fb['propietario_nombre']));
            }
        }
        $consorcio_del_nombre = trim($m_cons[1]);
        if ($consorcio_del_nombre !== '') {
            $cod_esc = mysqli_real_escape_string($conexion, $consorcio_del_nombre);
            $r_cons = mysqli_query($conexion, "SELECT apellido FROM usuarios WHERE UPPER(apellido) LIKE 'CONSORCIO%' AND (UPPER(TRIM(COALESCE(consorcio,''))) = UPPER('$cod_esc') OR UPPER(apellido) LIKE CONCAT('%', UPPER('$cod_esc'), '%')) LIMIT 1");
            if ($r_cons && $row_cons = mysqli_fetch_assoc($r_cons)) {
                $firmante_consorcio = str_replace('EE UU', 'ESTADOS UNIDOS', strtoupper(trim($row_cons['apellido'])));
            } else {
                $nombre_fb = ($cod_esc === '101') ? 'CONSORCIO ESTADOS UNIDOS 101' : ('CONSORCIO ' . strtoupper($consorcio_del_nombre));
                $firmante_consorcio = str_replace('EE UU', 'ESTADOS UNIDOS', $nombre_fb);
            }
        }
    } elseif ($es_usuario_finca) {
        $recibi_de = 'HECTOR HUGO HERRERA';
    } elseif ($arriendo_id > 0) {
        $r_ar = mysqli_query($conexion, "SELECT ar.apellido AS arrendatario_nombre
            FROM arriendos a
            INNER JOIN usuarios ar ON ar.id = a.arrendatario_id
            WHERE a.id = $arriendo_id LIMIT 1");
        if ($r_ar && $row_ar = mysqli_fetch_assoc($r_ar)) {
            $recibi_de = strtoupper(trim($row_ar['arrendatario_nombre']));
        }
    } elseif ($comprobante === 'PGO ARRIENDO') {
        $usuario_id_tmp = (int)$mov['usuario_id'];
        $concepto_buscar = strtoupper($mov['concepto'] ?? '');
        $r_ar = mysqli_query($conexion, "SELECT a.id, a.descripcion_finca, a.padron, ar.apellido AS arrendatario_nombre
            FROM arriendos a
            INNER JOIN usuarios ar ON ar.id = a.arrendatario_id
            WHERE a.propietario_id = $usuario_id_tmp");
        $candidato = null;
        $mejor_match = null;
        while ($r_ar && $row_ar = mysqli_fetch_assoc($r_ar)) {
            $txt = strtoupper(($row_ar['descripcion_finca'] ?? '') . ' ' . ($row_ar['padron'] ?? ''));
            $coincidencias = 0;
            if (preg_match_all('/\b(\d{2,}\.?\d*)\b/', $concepto_buscar, $m)) {
                foreach ($m[1] as $num) { if (stripos($txt, $num) !== false) $coincidencias++; }
            }
            $palabras = preg_split('/[\s,]+/', $txt, -1, PREG_SPLIT_NO_EMPTY);
            foreach ($palabras as $p) {
                if (strlen($p) > 4 && stripos($concepto_buscar, $p) !== false) $coincidencias++;
            }
            if ($coincidencias > 0 && ($mejor_match === null || $coincidencias > ($mejor_match['n'] ?? 0))) {
                $mejor_match = ['nombre' => $row_ar['arrendatario_nombre'], 'n' => $coincidencias];
            }
            if ($candidato === null) $candidato = $row_ar['arrendatario_nombre'];
        }
        if ($mejor_match) $recibi_de = strtoupper(trim($mejor_match['nombre']));
        elseif ($candidato) $recibi_de = strtoupper(trim($candidato));
    }
    }
}
$monto = abs((float)$mov['monto']);
$concepto = strtoupper(trim($mov['concepto']));
$referencia = trim($mov['referencia'] ?? '');
$fecha = $mov['fecha'];

// Firmante: en RETIRO es el titular (o consorcio si paga expensas); en INGRESO es propietario o el que corresponda
// Para arriendos: RETIRO=firmante propietario, INGRESO=firmante arrendatario
$propietario_nombre = '';
if ($firmante_arriendo) {
    $propietario_nombre = $firmante_arriendo;
} elseif ($es_retiro) {
    if ($firmante_consorcio) {
        $propietario_nombre = $firmante_consorcio;
    } else {
        $propietario_nombre = strtoupper(trim($mov['usuario_nombre']));
    }
} else {
    // INGRESO: buscar propietario (quien firma); si es consorcio, usar firmante_consorcio
    if ($firmante_consorcio) {
        $propietario_nombre = $firmante_consorcio;
    } else {
        $sql_prop = "SELECT u_prop.apellido AS propietario_nombre
                     FROM alquileres a
                     INNER JOIN propiedades p ON p.propiedad_id = a.propiedad_id
                     INNER JOIN usuarios u_prop ON u_prop.id = p.propietario_id
                     WHERE a.estado = 'VIGENTE' 
                     AND (a.inquilino1_id = $usuario_id OR a.inquilino2_id = $usuario_id)
                     LIMIT 1";
        $res_prop = mysqli_query($conexion, $sql_prop);
        if ($res_prop && mysqli_num_rows($res_prop) > 0) {
            $row_prop = mysqli_fetch_assoc($res_prop);
            $propietario_nombre = strtoupper(trim($row_prop['propietario_nombre']));
        } else {
            $sql_prop2 = "SELECT apellido FROM usuarios WHERE id = $usuario_id LIMIT 1";
            $res_prop2 = mysqli_query($conexion, $sql_prop2);
            if ($res_prop2 && mysqli_num_rows($res_prop2) > 0) {
                $row_prop2 = mysqli_fetch_assoc($res_prop2);
                $propietario_nombre = strtoupper(trim($row_prop2['apellido']));
            } else {
                $propietario_nombre = 'HERRERA Y LLOBETA S. R. L.';
            }
        }
    }
}
if (empty($propietario_nombre)) {
    $propietario_nombre = 'HERRERA Y LLOBETA S. R. L.';
}

// Formatear fecha: "a los [día] de [mes] de [año]"
$ts = strtotime($fecha);
$dia = (int)date('j', $ts);
$mes = mesNombre(date('n', $ts));
$anio = date('Y', $ts);
$fecha_formateada = "San Miguel de Tucumán, a los  $dia de $mes de $anio";

// Convertir monto a letras
$monto_letras = numerosALetras($monto);
$monto_numero = number_format($monto, 0, ',', '.');

// Número de recibo (usar movimiento_id)
$nro_recibo = $movimiento_id;

// Cabeceras para descarga como Word
header("Content-Type: application/vnd.ms-word; charset=UTF-8");
header("Content-Disposition: attachment; filename=\"Recibo_$nro_recibo.doc\"");
?>
<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:w="urn:schemas-microsoft-com:office:word" xmlns="http://www.w3.org/TR/REC-html40">
<head>
<meta charset="UTF-8">
<meta name="ProgId" content="Word.Document">
<meta name="Generator" content="Microsoft Word 15">
<meta name="Originator" content="Microsoft Word 15">
<xml>
<w:WordDocument>
<w:View>Print</w:View>
<w:Zoom>90</w:Zoom>
<w:DoNotOptimizeForBrowser/>
</w:WordDocument>
</xml>
<style>
    body {
        font-family: Arial, sans-serif;
        font-size: 12pt;
        margin: 1cm;
        line-height: 1.5;
    }
    .titulo {
        text-align: center;
        font-size: 14pt;
        font-weight: bold;
        letter-spacing: 2px;
        margin-bottom: 10px;
        line-height: 1.5;
    }
    .fecha {
        margin-bottom: 15px;
        text-align: right;
        line-height: 1.5;
    }
    .texto {
        margin-bottom: 15px;
        text-align: justify;
        line-height: 1.5;
    }
    .son {
        margin-bottom: 20px;
        text-align: justify;
        line-height: 1.5;
    }
    .firma-container {
        margin-top: 18px;
        line-height: 1.5;
    }
</style>
</head>
<body>
    <div class="titulo">R&nbsp;E&nbsp;C&nbsp;I&nbsp;B&nbsp;O&nbsp;&nbsp;&nbsp;Nº&nbsp;<?= $nro_recibo ?></div>
    <p>&nbsp;</p>
    <div class="fecha"><?= $fecha_formateada ?></div>
    <div class="texto">Recibí de <?= $recibi_de ?> la cantidad de pesos <?= $monto_letras ?> . ($<?= $monto_numero ?>-) en concepto de <?= $concepto ?><?= $referencia !== '' ? ' — Período cobrado: ' . htmlspecialchars($referencia) : '' ?>.</div>
    <div class="son"><strong>Son:$<?= $monto_numero ?>-</strong></div>
    <div class="firma-container">
        <table style="width: 100%; border-collapse: collapse; border: 0;">
            <tr>
                <td style="width: 70%; border: 0;">&nbsp;</td>
                <td style="text-align: center; border: 0; white-space: nowrap;">.............................................................................</td>
            </tr>
            <tr>
                <td style="width: 70%; border: 0;">&nbsp;</td>
                <td style="text-align: center; border: 0; padding-top: 5px;"><?= $propietario_nombre ?></td>
            </tr>
        </table>
    </div>
</body>
</html>
