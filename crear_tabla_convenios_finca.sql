-- Tabla de convenios finca: cantidades a liquidar el día 1 de cada mes (× valor tabla salarial = ingreso)
CREATE TABLE IF NOT EXISTS convenios_finca (
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
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    INDEX idx_usuario (usuario_id),
    INDEX idx_anio (anio),
    UNIQUE KEY uk_usuario_tipo_anio (usuario_id, tipo_trabajo, anio)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;

-- Control de meses ya liquidados (evitar doble aplicación)
CREATE TABLE IF NOT EXISTS convenios_finca_liquidado (
    convenio_id INT NOT NULL,
    mes TINYINT NOT NULL,
    PRIMARY KEY (convenio_id, mes),
    FOREIGN KEY (convenio_id) REFERENCES convenios_finca(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;
