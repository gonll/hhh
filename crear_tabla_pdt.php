<?php
include 'db.php';
include 'verificar_sesion.php';

if (isset($_SESSION['acceso_nivel']) && $_SESSION['acceso_nivel'] < 3) {
    die('Sin permiso');
}

// Crear tabla PDT (Partes Diarios de Trabajo)
$sql = "CREATE TABLE IF NOT EXISTS pdt (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    tipo_horas ENUM('Horas tractos', 'Horas Comunes') NOT NULL DEFAULT 'Horas Comunes',
    tractor VARCHAR(100) NULL,
    fecha DATE NOT NULL,
    horas DECIMAL(5,2) DEFAULT 0.00,
    observaciones TEXT NULL,
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    fecha_modificacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    INDEX idx_usuario (usuario_id),
    INDEX idx_fecha (fecha)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci";

if (mysqli_query($conexion, $sql)) {
    echo "Tabla PDT creada correctamente.";
} else {
    echo "Error al crear tabla: " . mysqli_error($conexion);
}
?>
