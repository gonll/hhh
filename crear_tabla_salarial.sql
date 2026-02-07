-- Tabla de valores salariales (hora común y hora tractor)
CREATE TABLE IF NOT EXISTS tabla_salarial (
    id INT AUTO_INCREMENT PRIMARY KEY,
    valor_hora_comun DECIMAL(10,2) NOT NULL DEFAULT 0,
    valor_hora_tractor DECIMAL(10,2) NOT NULL DEFAULT 0,
    vigencia_desde DATE NULL,
    observaciones VARCHAR(255) NULL,
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_vigencia (vigencia_desde)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;
