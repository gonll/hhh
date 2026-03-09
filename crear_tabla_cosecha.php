<?php
/**
 * Crea la tabla cosecha_hojas_ruta si no existe.
 * Agrega columnas lat/lng para GPS si no existen.
 */
if (!isset($conexion)) return;
$sql = "CREATE TABLE IF NOT EXISTS cosecha_hojas_ruta (
    id INT AUTO_INCREMENT PRIMARY KEY,
    anio_zafra INT NOT NULL,
    fecha DATE NOT NULL,
    hora TIME DEFAULT NULL,
    tickets VARCHAR(100) DEFAULT NULL,
    remito VARCHAR(100) DEFAULT NULL,
    viaje VARCHAR(50) DEFAULT NULL,
    camion VARCHAR(100) DEFAULT NULL,
    finca VARCHAR(200) DEFAULT NULL,
    variedad VARCHAR(100) DEFAULT NULL,
    lat DECIMAL(10, 8) DEFAULT NULL,
    lng DECIMAL(11, 8) DEFAULT NULL,
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_anio (anio_zafra),
    INDEX idx_fecha (fecha)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci";
mysqli_query($conexion, $sql);

// Agregar columnas GPS si la tabla ya existía sin ellas
$r = @mysqli_query($conexion, "SHOW COLUMNS FROM cosecha_hojas_ruta LIKE 'lat'");
if ($r && mysqli_num_rows($r) == 0) {
    @mysqli_query($conexion, "ALTER TABLE cosecha_hojas_ruta ADD COLUMN lat DECIMAL(10, 8) DEFAULT NULL, ADD COLUMN lng DECIMAL(11, 8) DEFAULT NULL");
}
