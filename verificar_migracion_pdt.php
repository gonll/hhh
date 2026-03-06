<?php
/**
 * Verifica y sincroniza la estructura de la tabla pdt entre localhost y servidor.
 * Ejecutar en ambos entornos para asegurar que la tabla sea idéntica.
 * Requiere sesión activa (incluye verificar_sesion).
 */
include 'db.php';
include 'verificar_sesion.php';

header('Content-Type: text/html; charset=utf-8');

$errores = [];
$cambios = [];

// Estructura esperada de la tabla pdt (orden: nombre => definición para ADD COLUMN)
$columnas_esperadas = [
    'id' => 'INT AUTO_INCREMENT PRIMARY KEY',
    'usuario_id' => 'INT NOT NULL',
    'tipo_horas' => "VARCHAR(50) NOT NULL DEFAULT 'Horas Comunes'",
    'tractor' => 'VARCHAR(100) NULL',
    'fecha' => 'DATE NOT NULL',
    'horas' => 'DECIMAL(5,2) DEFAULT 0.00',
    'cant_gasoil' => 'DECIMAL(6,2) NULL',
    'cambio_aceite' => 'TINYINT(1) DEFAULT 0',
    'en_cc' => 'TINYINT(1) DEFAULT 0',
    'observaciones' => 'TEXT NULL',
    'fecha_creacion' => 'TIMESTAMP DEFAULT CURRENT_TIMESTAMP',
    'fecha_modificacion' => 'TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP',
];

// Verificar si la tabla existe
$res = mysqli_query($conexion, "SHOW TABLES LIKE 'pdt'");
if (!$res || mysqli_num_rows($res) == 0) {
    $sql_create = "CREATE TABLE IF NOT EXISTS pdt (
        id INT AUTO_INCREMENT PRIMARY KEY,
        usuario_id INT NOT NULL,
        tipo_horas VARCHAR(50) NOT NULL DEFAULT 'Horas Comunes',
        tractor VARCHAR(100) NULL,
        fecha DATE NOT NULL,
        horas DECIMAL(5,2) DEFAULT 0.00,
        cant_gasoil DECIMAL(6,2) NULL,
        cambio_aceite TINYINT(1) DEFAULT 0,
        en_cc TINYINT(1) DEFAULT 0,
        observaciones TEXT NULL,
        fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        fecha_modificacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
        INDEX idx_usuario (usuario_id),
        INDEX idx_fecha (fecha)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci";
    if (mysqli_query($conexion, $sql_create)) {
        $cambios[] = 'Tabla pdt creada correctamente.';
    } else {
        $errores[] = 'Error al crear tabla: ' . mysqli_error($conexion);
    }
} else {
    // Obtener columnas actuales
    $columnas_actuales = [];
    $res_cols = mysqli_query($conexion, "SHOW COLUMNS FROM pdt");
    if ($res_cols) {
        while ($row = mysqli_fetch_assoc($res_cols)) {
            $columnas_actuales[strtolower($row['Field'])] = $row;
        }
    }

    // Orden de columnas para ALTER (después de cuál va cada una)
    $orden_after = [
        'usuario_id' => null,
        'tipo_horas' => 'usuario_id',
        'tractor' => 'tipo_horas',
        'fecha' => 'tractor',
        'horas' => 'fecha',
        'cant_gasoil' => 'horas',
        'cambio_aceite' => 'cant_gasoil',
        'en_cc' => 'cambio_aceite',
        'observaciones' => 'en_cc',
        'fecha_creacion' => 'observaciones',
        'fecha_modificacion' => 'fecha_creacion',
    ];

    foreach ($columnas_esperadas as $col => $def) {
        if ($col === 'id') continue; // id es PK, no se altera
        $col_lower = strtolower($col);
        if (!isset($columnas_actuales[$col_lower])) {
            $after = isset($orden_after[$col]) && $orden_after[$col] ? " AFTER " . $orden_after[$col] : "";
            $sql = "ALTER TABLE pdt ADD COLUMN `$col` $def$after";
            if (mysqli_query($conexion, $sql)) {
                $cambios[] = "Columna '$col' agregada.";
            } else {
                $errores[] = "Error al agregar '$col': " . mysqli_error($conexion);
            }
        }
    }
}

// Tabla gasoil: verificar pdt_id
$res_g = mysqli_query($conexion, "SHOW TABLES LIKE 'gasoil'");
if ($res_g && mysqli_num_rows($res_g) > 0) {
    $res_col = mysqli_query($conexion, "SHOW COLUMNS FROM gasoil LIKE 'pdt_id'");
    if (!$res_col || mysqli_num_rows($res_col) == 0) {
        if (mysqli_query($conexion, "ALTER TABLE gasoil ADD COLUMN pdt_id INT NULL AFTER concepto")) {
            $cambios[] = "Columna gasoil.pdt_id agregada.";
        }
    }
}

// Mostrar resultado
$env = parse_ini_file(__DIR__ . '/.env');
$host = $env['DB_HOST'] ?? '?';
$db_host = strtolower($host);
$es_local = in_array($db_host, ['localhost', '127.0.0.1', '::1']) || strpos($db_host, 'localhost') !== false;

echo "<!DOCTYPE html><html><head><meta charset='utf-8'><title>Verificar migración PDT</title>";
echo "<style>table.estructura { border-collapse:collapse; margin:10px 0; } table.estructura th, table.estructura td { border:1px solid #ccc; padding:6px 10px; text-align:left; } table.estructura th { background:#007bff; color:white; } .badge-local { background:#28a745; color:white; padding:4px 8px; border-radius:4px; } .badge-servidor { background:#dc3545; color:white; padding:4px 8px; border-radius:4px; }</style></head><body>";
echo "<h2>Verificación tabla PDT</h2>";
echo "<p><strong>Origen:</strong> <span class='" . ($es_local ? "badge-local" : "badge-servidor") . "'>" . ($es_local ? "LOCAL" : "SERVIDOR") . "</span></p>";
echo "<p><strong>Host BD:</strong> " . htmlspecialchars($host) . "</p>";
echo "<p><strong>Base de datos:</strong> " . htmlspecialchars($env['DB_NAME'] ?? '?') . "</p>";
echo "<p><strong>Nombre de la tabla:</strong> <code>pdt</code></p>";

if (!empty($errores)) {
    echo "<h3 style='color:red'>Errores:</h3><ul>";
    foreach ($errores as $e) echo "<li>" . htmlspecialchars($e) . "</li>";
    echo "</ul>";
}
if (!empty($cambios)) {
    echo "<h3 style='color:green'>Cambios aplicados:</h3><ul>";
    foreach ($cambios as $c) echo "<li>" . htmlspecialchars($c) . "</li>";
    echo "</ul>";
}
if (empty($errores) && empty($cambios)) {
    echo "<p style='color:green'>La tabla pdt ya tiene la estructura correcta.</p>";
}

// Mostrar estructura actual (columnas en tabla)
echo "<h3>Estructura de la tabla <code>pdt</code> (" . ($es_local ? "LOCAL" : "SERVIDOR") . "):</h3>";
$res_cols = mysqli_query($conexion, "SHOW COLUMNS FROM pdt");
if ($res_cols) {
    echo "<table class='estructura'><thead><tr><th>Campo</th><th>Tipo</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr></thead><tbody>";
    while ($row = mysqli_fetch_assoc($res_cols)) {
        echo "<tr><td><strong>" . htmlspecialchars($row['Field']) . "</strong></td>";
        echo "<td>" . htmlspecialchars($row['Type']) . "</td>";
        echo "<td>" . htmlspecialchars($row['Null']) . "</td>";
        echo "<td>" . htmlspecialchars($row['Key']) . "</td>";
        echo "<td>" . htmlspecialchars($row['Default'] ?? 'NULL') . "</td>";
        echo "<td>" . htmlspecialchars($row['Extra']) . "</td></tr>";
    }
    echo "</tbody></table>";
} else {
    echo "<p>No se pudo obtener la estructura.</p>";
}

// CREATE TABLE completo
echo "<h4>CREATE TABLE completo:</h4>";
$res = mysqli_query($conexion, "SHOW CREATE TABLE pdt");
if ($res && $row = mysqli_fetch_assoc($res)) {
    echo "<pre style='background:#f5f5f5;padding:10px;overflow:auto;font-size:11px'>";
    echo htmlspecialchars($row['Create Table']);
    echo "</pre>";
} else {
    echo "<p>No se pudo obtener el CREATE TABLE.</p>";
}

// Contar registros
$res_cnt = mysqli_query($conexion, "SELECT COUNT(*) as n FROM pdt");
$cnt = $res_cnt && $r = mysqli_fetch_assoc($res_cnt) ? (int)$r['n'] : 0;
echo "<p><strong>Registros en pdt:</strong> $cnt</p>";

echo "<p><a href='verificar_migracion_pdt.php'>Ejecutar de nuevo</a> | <a href='gestionar_finca.php'>Ir a Gestión Finca</a> | <a href='index.php'>Panel</a></p>";
echo "</body></html>";
