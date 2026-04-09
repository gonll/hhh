<?php
/**
 * Datos del formulario "Orden de alquiler" por propiedad (JSON en disco).
 */
if (!defined('HHH_ROOT')) {
    define('HHH_ROOT', dirname(__FILE__));
}

function orden_alquiler_asegurar_tabla($conexion) {
    if (!$conexion) return false;
    static $ok = null;
    if ($ok !== null) return $ok;
    $sql = "CREATE TABLE IF NOT EXISTS orden_alquiler_datos (
        propiedad_id INT NOT NULL PRIMARY KEY,
        datos_json LONGTEXT NOT NULL,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci";
    $ok = (bool) @mysqli_query($conexion, $sql);
    return $ok;
}

function orden_alquiler_json_file($propiedad_id) {
    $propiedad_id = (int) $propiedad_id;
    if ($propiedad_id <= 0) {
        return null;
    }
    return HHH_ROOT . '/uploads/propiedades/' . $propiedad_id . '/orden_alquiler_datos.json';
}

function orden_alquiler_persona_vacia() {
    return ['nombre' => '', 'dni' => '', 'cuit' => '', 'domicilio' => '', 'email' => '', 'celular' => ''];
}

function orden_alquiler_defaults() {
    return [
        'precio_alquiler_pedido' => '',
        'actualizacion' => '',
        'monto_garantia' => '',
        'solicitante' => orden_alquiler_persona_vacia(),
        'garante1' => orden_alquiler_persona_vacia(),
        'garante2' => orden_alquiler_persona_vacia(),
        'updated_at' => '',
    ];
}

function orden_alquiler_normalizar_datos(array $j) {
    $def = orden_alquiler_defaults();
    foreach (['solicitante', 'garante1', 'garante2'] as $k) {
        if (!isset($j[$k]) || !is_array($j[$k])) {
            $j[$k] = orden_alquiler_persona_vacia();
        } else {
            $j[$k] = array_merge(orden_alquiler_persona_vacia(), $j[$k]);
        }
    }
    return array_merge($def, $j);
}

function orden_alquiler_ts($val) {
    $s = trim((string) $val);
    if ($s === '') return 0;
    $ts = strtotime($s);
    return $ts !== false ? (int) $ts : 0;
}

function orden_alquiler_cargar_datos($propiedad_id) {
    $def = orden_alquiler_defaults();
    global $conexion;
    $dbData = null;
    $fileData = null;

    // Cargar desde base de datos (si está disponible)
    if (isset($conexion) && orden_alquiler_asegurar_tabla($conexion)) {
        $pid = (int) $propiedad_id;
        $res = @mysqli_query($conexion, "SELECT datos_json FROM orden_alquiler_datos WHERE propiedad_id = $pid LIMIT 1");
        if ($res && ($row = mysqli_fetch_assoc($res)) && !empty($row['datos_json'])) {
            $j = json_decode((string) $row['datos_json'], true);
            if (is_array($j)) {
                $dbData = orden_alquiler_normalizar_datos($j);
            }
        }
    }

    // Cargar desde archivo JSON en disco (respaldo)
    $f = orden_alquiler_json_file($propiedad_id);
    if ($f && is_readable($f)) {
        $j = json_decode((string) @file_get_contents($f), true);
        if (is_array($j)) {
            $fileData = orden_alquiler_normalizar_datos($j);
        }
    }

    // Elegir la versión más reciente por updated_at.
    if ($dbData && $fileData) {
        return (orden_alquiler_ts($fileData['updated_at'] ?? '') > orden_alquiler_ts($dbData['updated_at'] ?? ''))
            ? $fileData
            : $dbData;
    }
    if ($dbData) return $dbData;
    if ($fileData) return $fileData;

    return $def;
}

function orden_alquiler_guardar_datos($propiedad_id, array $datos) {
    $propiedad_id = (int) $propiedad_id;
    if ($propiedad_id <= 0) {
        return false;
    }
    global $conexion;

    $datos['updated_at'] = date('c');
    $json = json_encode($datos, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    $guardadoDb = false;

    // Guardado principal en base de datos
    if ($json !== false && isset($conexion) && orden_alquiler_asegurar_tabla($conexion)) {
        $pid = (int) $propiedad_id;
        $jsonEsc = mysqli_real_escape_string($conexion, $json);
        $sql = "INSERT INTO orden_alquiler_datos (propiedad_id, datos_json, updated_at)
                VALUES ($pid, '$jsonEsc', NOW())
                ON DUPLICATE KEY UPDATE datos_json = VALUES(datos_json), updated_at = NOW()";
        $guardadoDb = (bool) @mysqli_query($conexion, $sql);
    }

    // Mantener también archivo JSON como respaldo/compatibilidad
    $dir = HHH_ROOT . '/uploads/propiedades/' . $propiedad_id;
    if (!is_dir($dir)) {
        if (!@mkdir($dir, 0755, true) && !is_dir($dir)) {
            return $guardadoDb;
        }
    }
    $f = orden_alquiler_json_file($propiedad_id);
    if (!$f) {
        return $guardadoDb;
    }
    $guardadoFile = $json !== false && (bool) @file_put_contents($f, $json);
    return $guardadoDb || $guardadoFile;
}

function orden_alquiler_post_a_datos() {
    $p = function ($key) {
        return isset($_POST[$key]) ? trim((string) $_POST[$key]) : '';
    };
    $persona = function ($pref) use ($p) {
        return [
            'nombre' => $p($pref . '_nombre'),
            'dni' => $p($pref . '_dni'),
            'cuit' => $p($pref . '_cuit'),
            'domicilio' => $p($pref . '_domicilio'),
            'email' => $p($pref . '_email'),
            'celular' => $p($pref . '_celular'),
        ];
    };
    return [
        'precio_alquiler_pedido' => $p('precio_alquiler_pedido'),
        'actualizacion' => $p('actualizacion'),
        'monto_garantia' => $p('monto_garantia'),
        'solicitante' => $persona('solicitante'),
        'garante1' => $persona('garante1'),
        'garante2' => $persona('garante2'),
    ];
}
