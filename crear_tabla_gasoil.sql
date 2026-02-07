-- Tabla de movimientos de gasoil (cisterna y tractores)
-- cantidad: positivo = carga en cisterna, negativo = consumo tractor
CREATE TABLE IF NOT EXISTS gasoil (
    id INT AUTO_INCREMENT PRIMARY KEY,
    fecha DATE NOT NULL,
    cantidad DECIMAL(10,2) NOT NULL COMMENT '+ carga sisterna, - tractor',
    concepto VARCHAR(255) NULL,
    pdt_id INT NULL,
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_fecha (fecha),
    INDEX idx_pdt (pdt_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;
