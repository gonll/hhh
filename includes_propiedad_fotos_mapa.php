<?php
/**
 * Columnas opcionales: mapa_lat, mapa_lng, mapa_enlace, fotos_json (JSON array de rutas relativas).
 */
function propiedades_asegurar_columnas($conexion) {
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    $r = @mysqli_query($conexion, "SHOW COLUMNS FROM propiedades LIKE 'mapa_lat'");
    if ($r && mysqli_num_rows($r) > 0) {
        return;
    }
    $sql = "ALTER TABLE propiedades 
            ADD COLUMN mapa_lat DECIMAL(10,7) DEFAULT NULL,
            ADD COLUMN mapa_lng DECIMAL(10,7) DEFAULT NULL,
            ADD COLUMN mapa_enlace VARCHAR(768) DEFAULT NULL,
            ADD COLUMN fotos_json TEXT DEFAULT NULL";
    @mysqli_query($conexion, $sql);
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
    $base = __DIR__ . '/uploads/propiedades/' . $propiedad_id;
    if (!is_dir($base)) {
        if (!@mkdir($base, 0755, true) && !is_dir($base)) {
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
