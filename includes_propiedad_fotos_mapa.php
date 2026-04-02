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
    $base = dirname($_SERVER['SCRIPT_NAME'] ?? '');
    $base = str_replace('\\', '/', (string) $base);
    if ($base === '/' || $base === '' || $base === '.') {
        return '/' . $rel;
    }
    return rtrim($base, '/') . '/' . $rel;
}

function propiedades_columna_existe($conexion, $columna) {
    $columna = preg_replace('/[^a-z0-9_]/i', '', $columna);
    if ($columna === '') {
        return false;
    }
    $q = "SHOW COLUMNS FROM propiedades LIKE '" . mysqli_real_escape_string($conexion, $columna) . "'";
    $r = mysqli_query($conexion, $q);
    return $r && mysqli_num_rows($r) > 0;
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
 * @return string[] nuevas rutas añadidas
 */
function propiedades_procesar_subida_fotos($propiedad_id, $field = 'fotos') {
    $propiedad_id = (int) $propiedad_id;
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
        if ($err !== UPLOAD_ERR_OK || $tm === '' || !is_uploaded_file($tm)) {
            continue;
        }
        if ($sz > $maxBytes) {
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
