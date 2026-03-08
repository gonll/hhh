<?php
/**
 * Crea la tabla cosecha_hojas_ruta si no existe.
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
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_anio (anio_zafra),
    INDEX idx_fecha (fecha)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci";
mysqli_query($conexion, $sql);
