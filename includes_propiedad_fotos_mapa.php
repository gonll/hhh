<?php
/**
 * Columnas opcionales: mapa_lat, mapa_lng, mapa_enlace, fotos_json (JSON array de rutas relativas).
 * Raíz del proyecto (carpeta donde está este archivo).
 */
if (!defined('HHH_ROOT')) {
    define('HHH_ROOT', dirname(__FILE__));
}

/**
 * URL absoluta desde la raíz del sitio para mostrar uploads (evita rutas rotas en subcarpetas).
 */
function propiedades_url_publica($rel) {
    $rel = str_replace(['..', '\\'], '', (string) $rel);
    $rel = ltrim($rel, '/');
    $script = $_SERVER['SCRIPT_NAME'] ?? '';
    $base = dirname(str_replace('\\', '/', (string) $script));
    if ($base === '/' || $base === '' || $base === '.') {
        return '/' . $rel;
    }
    return rtrim($base, '/') . '/' . $rel;
}

/**
 * Respaldo si no hay columnas en BD: coordenadas y enlace en disco.
 */
function propiedades_guardar_mapa_disco($propiedad_id, $lat, $lng, $enlace, $zoom = null) {
    $propiedad_id = (int) $propiedad_id;
    if ($propiedad_id <= 0) {
        return false;
    }
    $dir = HHH_ROOT . '/uploads/propiedades/' . $propiedad_id;
    if (!is_dir($dir)) {
        if (!@mkdir($dir, 0755, true) && !is_dir($dir)) {
            return false;
        }
    }
    $data = [
        'lat' => $lat,
        'lng' => $lng,
        'enlace' => $enlace,
    ];
    if ($zoom === null && ($enlace !== null && $enlace !== '')) {
        $zoom = propiedades_zoom_desde_enlace_maps($enlace);
    }
    if ($zoom !== null && $zoom !== '') {
        $zi = (int) $zoom;
        if ($zi >= 1 && $zi <= 22) {
            $data['zoom'] = $zi;
        }
    }
    return (bool) @file_put_contents($dir . '/mapa.json', json_encode($data, JSON_UNESCAPED_UNICODE));
}

function propiedades_leer_mapa_disco($propiedad_id) {
    $propiedad_id = (int) $propiedad_id;
    if ($propiedad_id <= 0) {
        return null;
    }
    $f = HHH_ROOT . '/uploads/propiedades/' . $propiedad_id . '/mapa.json';
    if (!is_readable($f)) {
        return null;
    }
    $j = json_decode((string) @file_get_contents($f), true);
    return is_array($j) ? $j : null;
}

/**
 * Intenta leer el nivel de zoom (estilo Google Maps, 1–22) desde el enlace guardado.
 */
function propiedades_zoom_desde_enlace_maps($url) {
    if ($url === null || $url === '') {
        return null;
    }
    $url = (string) $url;
    if (preg_match('/@[-+]?\d+\.?\d*,[-+]?\d+\.?\d*,(\d{1,2})z/i', $url, $m)) {
        return (int) min(22, max(1, (int) $m[1]));
    }
    if (preg_match('/!(\d{1,2})z/i', $url, $m)) {
        return (int) min(22, max(1, (int) $m[1]));
    }
    if (preg_match('/[,/](\d{1,2})z\b/i', $url, $m)) {
        return (int) min(22, max(1, (int) $m[1]));
    }
    if (preg_match('#[?&]z=(\d{1,2})\b#i', $url, $m)) {
        return (int) min(22, max(1, (int) $m[1]));
    }
    return null;
}

/**
 * Semi-extensión del bbox (grados lat/lng desde el centro) para embed OSM según zoom (~como Google).
 * Zoom 15 ≈ el encuadre que usábamos antes con dl 0.012.
 */
function propiedades_mapa_bbox_delta_desde_zoom($zoom) {
    $zoom = (int) $zoom;
    if ($zoom < 1 || $zoom > 22) {
        $zoom = 15;
    }
    $dl = 0.012 * pow(2, 15 - $zoom);

    return max(0.0006, min(0.5, $dl));
}

/**
 * Zoom efectivo: enlace, luego mapa.json, luego 15.
 */
function propiedades_mapa_zoom_efectivo($enlace, $diskMap) {
    $z = propiedades_zoom_desde_enlace_maps($enlace ?? '');
    if ($z !== null) {
        return $z;
    }
    if (is_array($diskMap) && isset($diskMap['zoom']) && $diskMap['zoom'] !== '' && $diskMap['zoom'] !== null) {
        $zi = (int) $diskMap['zoom'];
        if ($zi >= 1 && $zi <= 22) {
            return $zi;
        }
    }

    return 15;
}

/**
 * Lista imágenes en carpeta (respaldo si fotos_json vacío o sin columna).
 */
function propiedades_listar_fotos_disco($propiedad_id) {
    $propiedad_id = (int) $propiedad_id;
    if ($propiedad_id <= 0) {
        return [];
    }
    $dir = HHH_ROOT . '/uploads/propiedades/' . $propiedad_id;
    if (!is_dir($dir)) {
        return [];
    }
    $ext = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    $out = [];
    foreach (scandir($dir) as $fn) {
        if ($fn === '.' || $fn === '..' || $fn === 'mapa.json') {
            continue;
        }
        $low = strtolower(pathinfo($fn, PATHINFO_EXTENSION));
        if (!in_array($low, $ext, true)) {
            continue;
        }
        $out[] = 'uploads/propiedades/' . $propiedad_id . '/' . $fn;
    }
    sort($out);
    return $out;
}

/**
 * Combina rutas en BD con archivos en disco (sin duplicar).
 */
function propiedades_fotos_unificadas($propiedad_id, $fotos_json) {
    $desde_db = propiedades_fotos_desde_json($fotos_json);
    $desde_disco = propiedades_listar_fotos_disco($propiedad_id);
    $seen = [];
    $merged = [];
    foreach (array_merge($desde_db, $desde_disco) as $p) {
        $p = str_replace(['..', '\\'], '', (string) $p);
        if ($p === '' || isset($seen[$p])) {
            continue;
        }
        $seen[$p] = true;
        $merged[] = $p;
    }
    return $merged;
}

function propiedades_columna_existe($conexion, $columna) {
    $columna = preg_replace('/[^a-z0-9_]/i', '', $columna);
    if ($columna === '') {
        return false;
    }
    $col = mysqli_real_escape_string($conexion, $columna);
    $q = "SELECT 1 FROM information_schema.COLUMNS 
          WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'propiedades' AND COLUMN_NAME = '" . $col . "' LIMIT 1";
    $r = mysqli_query($conexion, $q);
    if ($r && mysqli_num_rows($r) > 0) {
        return true;
    }
    $q2 = "SHOW COLUMNS FROM propiedades LIKE '" . $col . "'";
    $r2 = mysqli_query($conexion, $q2);
    return $r2 && mysqli_num_rows($r2) > 0;
}

/**
 * Crea columnas si faltan. En servidores sin permiso ALTER, ver error_log o ejecutar SQL manualmente.
 * @return bool true si mapa_lat existe al final (o ya existía)
 */
function propiedades_asegurar_columnas($conexion) {
    static $done = false;
    if ($done) {
        return propiedades_columna_existe($conexion, 'mapa_lat');
    }
    if (propiedades_columna_existe($conexion, 'mapa_lat')
        && propiedades_columna_existe($conexion, 'fotos_json')) {
        $done = true;
        return true;
    }
    $sql = "ALTER TABLE propiedades 
            ADD COLUMN mapa_lat DECIMAL(10,7) DEFAULT NULL,
            ADD COLUMN mapa_lng DECIMAL(10,7) DEFAULT NULL,
            ADD COLUMN mapa_enlace VARCHAR(768) DEFAULT NULL,
            ADD COLUMN fotos_json TEXT DEFAULT NULL";
    if (!mysqli_query($conexion, $sql)) {
        $err = mysqli_error($conexion);
        error_log('propiedades_asegurar_columnas ALTER batch: ' . $err);
        $alters = [
            "ADD COLUMN mapa_lat DECIMAL(10,7) DEFAULT NULL",
            "ADD COLUMN mapa_lng DECIMAL(10,7) DEFAULT NULL",
            "ADD COLUMN mapa_enlace VARCHAR(768) DEFAULT NULL",
            "ADD COLUMN fotos_json TEXT DEFAULT NULL",
        ];
        foreach ($alters as $fragment) {
            if (!mysqli_query($conexion, 'ALTER TABLE propiedades ' . $fragment)) {
                $e2 = mysqli_error($conexion);
                if (stripos($e2, 'Duplicate column') === false) {
                    error_log('propiedades_asegurar_columnas ALTER parcial: ' . $e2);
                }
            }
        }
    }
    $ok = propiedades_columna_existe($conexion, 'mapa_lat');
    if (!$ok) {
        error_log('propiedades_asegurar_columnas: las columnas mapa_lat/fotos_json no existen. Ejecute ALTER en MySQL con un usuario con permisos.');
    }
    $done = true;
    return $ok;
}

/**
 * @return string[] rutas relativas tipo uploads/propiedades/{id}/archivo.jpg
 */
function propiedades_fotos_desde_json($json) {
    if ($json === null || $json === '') {
        return [];
    }
    $a = json_decode($json, true);
    return is_array($a) ? $a : [];
}

/**
 * Mensaje legible para error de subida PHP.
 */
function propiedades_upload_err_str($code) {
    switch ((int) $code) {
        case UPLOAD_ERR_INI_SIZE:
        case UPLOAD_ERR_FORM_SIZE:
            return 'archivo demasiado grande (límite del servidor o 5 MB en la app)';
        case UPLOAD_ERR_PARTIAL:
            return 'subida interrumpida';
        case UPLOAD_ERR_NO_FILE:
            return 'ningún archivo';
        case UPLOAD_ERR_NO_TMP_DIR:
            return 'falta carpeta temporal en el servidor';
        case UPLOAD_ERR_CANT_WRITE:
        case UPLOAD_ERR_EXTENSION:
            return 'no se pudo escribir el archivo en el servidor';
        default:
            return 'error de subida (código ' . (int) $code . ')';
    }
}

/**
 * @param array|null $diag Si se pasa una variable, se rellena con avisos (errores o formato no reconocido).
 * @return string[] nuevas rutas añadidas
 */
function propiedades_procesar_subida_fotos($propiedad_id, $field = 'fotos', &$diag = null) {
    $propiedad_id = (int) $propiedad_id;
    if ($diag !== null) {
        $diag = [];
    }
    if ($propiedad_id <= 0 || !isset($_FILES[$field])) {
        return [];
    }
    $base = HHH_ROOT . '/uploads/propiedades/' . $propiedad_id;
    if (!is_dir($base)) {
        if (!@mkdir($base, 0755, true) && !is_dir($base)) {
            error_log('propiedades_procesar_subida_fotos: no se pudo crear carpeta ' . $base);
            return [];
        }
    }
    $mime_ok = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/gif' => 'gif',
        'image/webp' => 'webp',
    ];
    $maxBytes = 5 * 1024 * 1024;
    $nuevas = [];
    $f = $_FILES[$field];
    if (!is_array($f['name'])) {
        $f = [
            'name' => [$f['name']],
            'type' => [$f['type'] ?? ''],
            'tmp_name' => [$f['tmp_name']],
            'error' => [$f['error']],
            'size' => [$f['size'] ?? 0],
        ];
    }
    $indices = is_array($f['name']) ? array_keys($f['name']) : [0];
    foreach ($indices as $idx) {
        $err = is_array($f['error']) ? ($f['error'][$idx] ?? UPLOAD_ERR_NO_FILE) : $f['error'];
        $tm = is_array($f['tmp_name']) ? ($f['tmp_name'][$idx] ?? '') : $f['tmp_name'];
        $sz = is_array($f['size']) ? (int) ($f['size'][$idx] ?? 0) : (int) $f['size'];
        $ty = is_array($f['type'] ?? null) ? ($f['type'][$idx] ?? '') : ($f['type'] ?? '');
        $nombreArchivo = is_array($f['name']) ? ($f['name'][$idx] ?? '') : ($f['name'] ?? '');
        if ($err !== UPLOAD_ERR_OK) {
            if ($diag !== null && $err !== UPLOAD_ERR_NO_FILE) {
                $diag[] = (string) $nombreArchivo . ': ' . propiedades_upload_err_str($err);
            }
            continue;
        }
        if ($tm === '' || !is_uploaded_file($tm)) {
            if ($diag !== null) {
                $diag[] = (string) $nombreArchivo . ': archivo temporal inválido';
            }
            continue;
        }
        if ($sz > $maxBytes) {
            if ($diag !== null) {
                $diag[] = (string) $nombreArchivo . ': supera 5 MB';
            }
            continue;
        }
        $detect = $ty;
        if (function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            if ($finfo) {
                $detect = finfo_file($finfo, $tm) ?: $ty;
                finfo_close($finfo);
            }
        }
        if (!isset($mime_ok[$detect])) {
            $gi = @getimagesize($tm);
            if ($gi !== false && isset($gi[2])) {
                $imap = [
                    IMAGETYPE_JPEG => 'image/jpeg',
                    IMAGETYPE_PNG => 'image/png',
                    IMAGETYPE_GIF => 'image/gif',
                ];
                if (defined('IMAGETYPE_WEBP')) {
                    $imap[IMAGETYPE_WEBP] = 'image/webp';
                }
                $it = (int)$gi[2];
                if (isset($imap[$it])) {
                    $detect = $imap[$it];
                }
            }
        }
        if (!isset($mime_ok[$detect])) {
            $extFromName = strtolower((string) pathinfo($nombreArchivo, PATHINFO_EXTENSION));
            $extToMime = ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'gif' => 'image/gif', 'webp' => 'image/webp'];
            if (isset($extToMime[$extFromName])) {
                $detect = $extToMime[$extFromName];
            }
        }
        if (!isset($mime_ok[$detect])) {
            if ($diag !== null) {
                $diag[] = (string) $nombreArchivo . ': formato no reconocido (use JPG, PNG, GIF o WEBP)';
            }
            continue;
        }
        $ext = $mime_ok[$detect];
        $fname = 'prop_' . $propiedad_id . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
        $dest = $base . '/' . $fname;
        if (!move_uploaded_file($tm, $dest)) {
            error_log('propiedades_procesar_subida_fotos: move_uploaded_file falló propiedad_id=' . $propiedad_id . ' dest=' . $dest);
            continue;
        }
        $rel = 'uploads/propiedades/' . $propiedad_id . '/' . $fname;
        $nuevas[] = $rel;
    }
    return $nuevas;
}

function propiedades_guardar_json_fotos($conexion, $propiedad_id, array $rutas) {
    $propiedad_id = (int) $propiedad_id;
    if ($propiedad_id <= 0) {
        return false;
    }
    $json = json_encode(array_values($rutas), JSON_UNESCAPED_UNICODE);
    $stmt = mysqli_prepare($conexion, 'UPDATE propiedades SET fotos_json = ? WHERE propiedad_id = ?');
    if (!$stmt) {
        return false;
    }
    mysqli_stmt_bind_param($stmt, 'si', $json, $propiedad_id);
    $ok = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    return $ok;
}
