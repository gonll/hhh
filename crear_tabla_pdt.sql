-- Crear tabla PDT (Partes Diarios de Trabajo)
CREATE TABLE IF NOT EXISTS pdt (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    tipo_horas ENUM('Horas tractos', 'Horas Comunes') NOT NULL DEFAULT 'Horas Comunes',
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
    INDEX idx_fecha (fecha),
    INDEX idx_en_cc (en_cc)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;
