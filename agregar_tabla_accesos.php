<?php
/**
 * Ejecutar UNA vez: crea la tabla accesos y opcionalmente el primer usuario nivel 3.
 * Luego podés borrar o no ejecutar de nuevo este archivo.
 */
include 'db.php';

$sql_tabla = "CREATE TABLE IF NOT EXISTS accesos (
  id INT AUTO_INCREMENT PRIMARY KEY,
  usuario VARCHAR(80) NOT NULL UNIQUE,
  clave VARCHAR(255) NOT NULL,
  nivel_acceso TINYINT NOT NULL DEFAULT 2,
  creado_por_id INT NULL,
  fecha_creacion DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_usuario (usuario),
  INDEX idx_nivel (nivel_acceso)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

if (mysqli_query($conexion, $sql_tabla)) {
    echo "Tabla 'accesos' creada o ya existía.<br>";
} else {
    echo "Error creando tabla: " . mysqli_error($conexion) . "<br>";
    exit;
}

// Solo si no hay ningún usuario, crear el primero (nivel 3)
$r = mysqli_query($conexion, "SELECT id FROM accesos LIMIT 1");
if (mysqli_num_rows($r) == 0) {
    $usuario = 'admin';
    $clave_plana = 'admin123'; // Cambiar por tu clave deseada
    $hash = password_hash($clave_plana, PASSWORD_DEFAULT);
    $usuario_esc = mysqli_real_escape_string($conexion, $usuario);
    $hash_esc = mysqli_real_escape_string($conexion, $hash);
    $ins = "INSERT INTO accesos (usuario, clave, nivel_acceso) VALUES ('$usuario_esc', '$hash_esc', 3)";
    if (mysqli_query($conexion, $ins)) {
        echo "Primer usuario creado: $usuario (nivel 3). Cambiá la clave desde 'Gestionar accesos' una vez dentro.";
    } else {
        echo "Error creando primer usuario: " . mysqli_error($conexion);
    }
} else {
    echo "Ya existen usuarios de acceso. No se creó ninguno nuevo.";
}
?>
