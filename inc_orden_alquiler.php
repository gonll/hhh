<?php
/**
 * Datos del formulario "Orden de alquiler" por propiedad (JSON en disco).
 */
if (!defined('HHH_ROOT')) {
    define('HHH_ROOT', dirname(__FILE__));
}

function orden_alquiler_asegurar_tabla($conexion) {
    if (!$conexion) {
        return false;
    }
    // No cachear fallos: en algunos servidores el primer CREATE puede fallar por permisos
    // aunque la tabla ya exista; SHOW TABLES confirma si podemos usar la BD.
    $sql = "CREATE TABLE IF NOT EXISTS orden_alquiler_datos (
        propiedad_id INT NOT NULL PRIMARY KEY,
        datos_json LONGTEXT NOT NULL,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci";
    @mysqli_query($conexion, $sql);
    $chk = @mysqli_query($conexion, "SHOW TABLES LIKE 'orden_alquiler_datos'");
    return ($chk && mysqli_num_rows($chk) > 0);
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
        'modo_operacion' => 'alquiler',
        'precio_alquiler_pedido' => '',
        'precio_venta_pedido' => '',
        'actualizacion' => '',
        'condiciones_venta' => '',
        'monto_garantia' => '',
        'solicitante' => orden_alquiler_persona_vacia(),
        'garante1' => orden_alquiler_persona_vacia(),
        'garante2' => orden_alquiler_persona_vacia(),
        'historial' => [],
        'updated_at' => '',
    ];
}

function orden_alquiler_normalizar_historial($h): array
{
    if (!is_array($h)) {
        return [];
    }
    $tiposOk = ['visita' => true, 'guardado' => true, 'cambio_modo' => true];
    $out = [];
    foreach ($h as $row) {
        if (!is_array($row)) {
            continue;
        }
        $tipo = (string) ($row['tipo'] ?? '');
        if (!isset($tiposOk[$tipo])) {
            $tipo = 'visita';
        }
        $out[] = [
            'fecha' => mb_substr(trim((string) ($row['fecha'] ?? '')), 0, 40),
            'tipo' => $tipo,
            'modo' => (($row['modo'] ?? '') === 'venta') ? 'venta' : 'alquiler',
            'cliente' => mb_substr(trim((string) ($row['cliente'] ?? '')), 0, 500),
            'mostrador' => mb_substr(trim((string) ($row['mostrador'] ?? '')), 0, 120),
            'nota' => mb_substr(trim((string) ($row['nota'] ?? '')), 0, 500),
        ];
    }
    if (count($out) > 200) {
        $out = array_slice($out, -200);
    }

    return $out;
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
    $j['historial'] = orden_alquiler_normalizar_historial($j['historial'] ?? []);
    $j['modo_operacion'] = (($j['modo_operacion'] ?? '') === 'venta') ? 'venta' : 'alquiler';

    return array_merge($def, $j);
}

function orden_alquiler_agregar_evento_historial(array &$datos, string $tipo, string $modo, string $cliente, string $mostrador, string $nota = ''): void
{
    if (!isset($datos['historial']) || !is_array($datos['historial'])) {
        $datos['historial'] = [];
    }
    $datos['historial'][] = [
        'fecha' => date('c'),
        'tipo' => $tipo,
        'modo' => $modo === 'venta' ? 'venta' : 'alquiler',
        'cliente' => $cliente,
        'mostrador' => $mostrador,
        'nota' => $nota,
    ];
    if (count($datos['historial']) > 200) {
        $datos['historial'] = array_slice($datos['historial'], -200);
    }
}

function orden_alquiler_ts($val) {
    $s = trim((string) $val);
    if ($s === '') return 0;
    $ts = strtotime($s);
    return $ts !== false ? (int) $ts : 0;
}

function orden_alquiler_score_datos(array $d) {
    $score = 0;
    foreach (['precio_alquiler_pedido', 'precio_venta_pedido', 'actualizacion', 'condiciones_venta', 'monto_garantia'] as $k) {
        if (trim((string)($d[$k] ?? '')) !== '') $score++;
    }
    foreach (['solicitante', 'garante1', 'garante2'] as $p) {
        $persona = isset($d[$p]) && is_array($d[$p]) ? $d[$p] : [];
        foreach (['nombre', 'dni', 'cuit', 'domicilio', 'email', 'celular'] as $k) {
            if (trim((string)($persona[$k] ?? '')) !== '') $score++;
        }
    }
    return $score;
}

function orden_alquiler_sanitizar_utf8($v) {
    if (is_array($v)) {
        $out = [];
        foreach ($v as $k => $val) $out[$k] = orden_alquiler_sanitizar_utf8($val);
        return $out;
    }
    if (!is_string($v)) return $v;
    if (mb_check_encoding($v, 'UTF-8')) return $v;
    return mb_convert_encoding($v, 'UTF-8', 'UTF-8, ISO-8859-1, Windows-1252');
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
        $tsFile = orden_alquiler_ts($fileData['updated_at'] ?? '');
        $tsDb = orden_alquiler_ts($dbData['updated_at'] ?? '');
        if ($tsFile > $tsDb) return $fileData;
        if ($tsDb > $tsFile) return $dbData;
        // Si timestamps empatan o faltan, quedarse con la versión que tenga más datos cargados.
        return (orden_alquiler_score_datos($fileData) > orden_alquiler_score_datos($dbData)) ? $fileData : $dbData;
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

    $datos = orden_alquiler_sanitizar_utf8($datos);
    $datos['updated_at'] = date('c');
    $json = json_encode($datos, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_INVALID_UTF8_SUBSTITUTE);
    $guardadoDb = false;

    // Guardado principal en base de datos (prepared: evita fallos con comillas y JSON largo)
    if ($json !== false && isset($conexion) && orden_alquiler_asegurar_tabla($conexion)) {
        $pid = (int) $propiedad_id;
        $sql = 'INSERT INTO orden_alquiler_datos (propiedad_id, datos_json, updated_at)
                VALUES (?, ?, NOW())
                ON DUPLICATE KEY UPDATE datos_json = VALUES(datos_json), updated_at = NOW()';
        $stmt = @mysqli_prepare($conexion, $sql);
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, 'is', $pid, $json);
            $guardadoDb = (bool) mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
        }
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
    $guardadoFile = $json !== false && (bool) @file_put_contents($f, $json, LOCK_EX);
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
    $modo = ($p('modo_operacion') === 'venta') ? 'venta' : 'alquiler';

    return [
        'modo_operacion' => $modo,
        'precio_alquiler_pedido' => $p('precio_alquiler_pedido'),
        'precio_venta_pedido' => $p('precio_venta_pedido'),
        'actualizacion' => $p('actualizacion'),
        'condiciones_venta' => $p('condiciones_venta'),
        'monto_garantia' => $p('monto_garantia'),
        'solicitante' => $persona('solicitante'),
        'garante1' => $persona('garante1'),
        'garante2' => $persona('garante2'),
    ];
}
