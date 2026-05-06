<?php
if (!isset($conexion)) {
    return;
}

// Tablas mínimas para convenios (idempotente).
mysqli_query($conexion, "CREATE TABLE IF NOT EXISTS convenios_finca (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    tipo_trabajo VARCHAR(50) NOT NULL DEFAULT 'Horas Comunes',
    anio INT NOT NULL,
    monto_mes_1 DECIMAL(12,2) DEFAULT 0,
    monto_mes_2 DECIMAL(12,2) DEFAULT 0,
    monto_mes_3 DECIMAL(12,2) DEFAULT 0,
    monto_mes_4 DECIMAL(12,2) DEFAULT 0,
    monto_mes_5 DECIMAL(12,2) DEFAULT 0,
    monto_mes_6 DECIMAL(12,2) DEFAULT 0,
    monto_mes_7 DECIMAL(12,2) DEFAULT 0,
    monto_mes_8 DECIMAL(12,2) DEFAULT 0,
    monto_mes_9 DECIMAL(12,2) DEFAULT 0,
    monto_mes_10 DECIMAL(12,2) DEFAULT 0,
    monto_mes_11 DECIMAL(12,2) DEFAULT 0,
    monto_mes_12 DECIMAL(12,2) DEFAULT 0,
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_usuario (usuario_id),
    INDEX idx_anio (anio),
    UNIQUE KEY uk_usuario_tipo_anio (usuario_id, tipo_trabajo, anio)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci");

mysqli_query($conexion, "CREATE TABLE IF NOT EXISTS convenios_finca_liquidado (
    convenio_id INT NOT NULL,
    mes TINYINT NOT NULL,
    PRIMARY KEY (convenio_id, mes),
    FOREIGN KEY (convenio_id) REFERENCES convenios_finca(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci");

// Control mensual de ejecución automática: una sola vez por mes.
mysqli_query($conexion, "CREATE TABLE IF NOT EXISTS convenios_finca_ejecucion_mensual (
    periodo CHAR(7) NOT NULL PRIMARY KEY, -- YYYY-MM
    ejecutado_en DATETIME NOT NULL,
    observacion VARCHAR(255) NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci");

$periodo_actual = date('Y-m');
$periodo_actual_esc = mysqli_real_escape_string($conexion, $periodo_actual);
$ya = mysqli_query($conexion, "SELECT 1 FROM convenios_finca_ejecucion_mensual WHERE periodo = '$periodo_actual_esc' LIMIT 1");
if ($ya && mysqli_num_rows($ya) > 0) {
    return; // Ya ejecutado este mes.
}

$mes_actual = (int)date('n');
$anio_actual = (int)date('Y');
$mes_obj = $mes_actual - 1;
$anio_obj = $anio_actual;
if ($mes_obj < 1) {
    $mes_obj = 12;
    $anio_obj--;
}

$ref = sprintf('%02d/%04d', $mes_obj, $anio_obj);
$ref_esc = mysqli_real_escape_string($conexion, $ref);
$fecha_asiento = sprintf('%04d-%02d-01', $anio_actual, $mes_actual);
$comprobante = mysqli_real_escape_string($conexion, 'trabajo');
$nombres_mes = ['Enero','Febrero','Marzo','Abril','Mayo','Junio','Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'];

$res_ts = mysqli_query($conexion, "SELECT valor_hora_comun, valor_hora_tractor FROM tabla_salarial ORDER BY id DESC LIMIT 1");
$vh_comun = 0.0;
$vh_tractor = 0.0;
if ($res_ts && ($ts = mysqli_fetch_assoc($res_ts))) {
    $vh_comun = (float)($ts['valor_hora_comun'] ?? 0);
    $vh_tractor = (float)($ts['valor_hora_tractor'] ?? 0);
}

$insertados = 0;
$convenios = mysqli_query($conexion, "SELECT * FROM convenios_finca WHERE anio = $anio_obj");
if ($convenios) {
    while ($c = mysqli_fetch_assoc($convenios)) {
        $convenio_id = (int)($c['id'] ?? 0);
        $usuario_id = (int)($c['usuario_id'] ?? 0);
        $tipo_trabajo = (string)($c['tipo_trabajo'] ?? 'Horas Comunes');
        $jornales = (float)($c['monto_mes_' . $mes_obj] ?? 0);
        if ($convenio_id <= 0 || $usuario_id <= 0 || $jornales <= 0) {
            continue;
        }

        $es_tractor = (stripos($tipo_trabajo, 'tract') !== false);
        $valor_hora = $es_tractor ? $vh_tractor : $vh_comun;
        if ($valor_hora <= 0) {
            continue;
        }
        $detalle_tipo = $es_tractor ? 'Horas tractos' : 'Horas Comunes';
        $tipo_like = mysqli_real_escape_string($conexion, $es_tractor ? '%Horas tractos%' : '%Horas Comunes%');

        $existe = mysqli_query($conexion,
            "SELECT 1 FROM cuentas
             WHERE usuario_id = $usuario_id
               AND comprobante = 'trabajo'
               AND referencia = '$ref_esc'
               AND (concepto LIKE 'Fijo de mes%' OR concepto LIKE 'FIJO DE MES%')
               AND concepto LIKE '$tipo_like'
             LIMIT 1"
        );
        if ($existe && mysqli_num_rows($existe) > 0) {
            mysqli_query($conexion, "INSERT IGNORE INTO convenios_finca_liquidado (convenio_id, mes) VALUES ($convenio_id, $mes_obj)");
            continue;
        }

        $monto = round(8 * $jornales * $valor_hora, 2);
        $jornales_fmt = number_format($jornales, 2, ',', '.');
        $valor_fmt = number_format($valor_hora, 2, ',', '.');
        $concepto = "Fijo de mes {$nombres_mes[$mes_obj - 1]}, cantidad $jornales_fmt jornales (8 h c/u) $detalle_tipo, valor $valor_fmt por hora";
        $concepto_esc = mysqli_real_escape_string($conexion, $concepto);
        $sql_ins = "INSERT INTO cuentas (usuario_id, fecha, concepto, comprobante, referencia, monto)
                    VALUES ($usuario_id, '$fecha_asiento', '$concepto_esc', '$comprobante', '$ref_esc', $monto)";
        if (mysqli_query($conexion, $sql_ins)) {
            mysqli_query($conexion, "INSERT IGNORE INTO convenios_finca_liquidado (convenio_id, mes) VALUES ($convenio_id, $mes_obj)");
            $insertados++;
        }
    }
}

$obs = mysqli_real_escape_string($conexion, 'insertados=' . $insertados . '; ref=' . $ref);
mysqli_query($conexion,
    "INSERT INTO convenios_finca_ejecucion_mensual (periodo, ejecutado_en, observacion)
     VALUES ('$periodo_actual_esc', NOW(), '$obs')"
);
