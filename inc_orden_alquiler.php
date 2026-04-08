<?php
/**
 * Datos del formulario "Orden de alquiler" por propiedad (JSON en disco).
 */
if (!defined('HHH_ROOT')) {
    define('HHH_ROOT', dirname(__FILE__));
}

function orden_alquiler_json_file($propiedad_id) {
    $propiedad_id = (int) $propiedad_id;
    if ($propiedad_id <= 0) {
        return null;
    }
    return HHH_ROOT . '/uploads/propiedades/' . $propiedad_id . '/orden_alquiler_datos.json';
}

function orden_alquiler_persona_vacia() {
    return ['nombre' => '', 'dni' => '', 'cuit' => '', 'email' => '', 'celular' => ''];
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

function orden_alquiler_cargar_datos($propiedad_id) {
    $def = orden_alquiler_defaults();
    $f = orden_alquiler_json_file($propiedad_id);
    if (!$f || !is_readable($f)) {
        return $def;
    }
    $j = json_decode((string) @file_get_contents($f), true);
    if (!is_array($j)) {
        return $def;
    }
    foreach (['solicitante', 'garante1', 'garante2'] as $k) {
        if (!isset($j[$k]) || !is_array($j[$k])) {
            $j[$k] = orden_alquiler_persona_vacia();
        } else {
            $j[$k] = array_merge(orden_alquiler_persona_vacia(), $j[$k]);
        }
    }
    return array_merge($def, $j);
}

function orden_alquiler_guardar_datos($propiedad_id, array $datos) {
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
    $datos['updated_at'] = date('c');
    $f = orden_alquiler_json_file($propiedad_id);
    if (!$f) {
        return false;
    }
    $json = json_encode($datos, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    return $json !== false && (bool) @file_put_contents($f, $json);
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
