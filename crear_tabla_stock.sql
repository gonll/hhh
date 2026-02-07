-- Tabla stock: compatible con la grilla de Gestión de azúcares.
-- Vendida a y Facturada a guardan id de usuario (usuarios.id); en pantalla se muestra apellido.

CREATE TABLE IF NOT EXISTS stock (
    id INT AUTO_INCREMENT PRIMARY KEY,
    fecha DATE NOT NULL,
    linea TINYINT NOT NULL DEFAULT 1,
    articulo VARCHAR(255) NOT NULL DEFAULT '',
    orden INT NOT NULL DEFAULT 0,
    cantidad DECIMAL(12,2) NOT NULL DEFAULT 0,
    deposito VARCHAR(255) NULL,
    fecha_vta DATE NULL,
    cant_vta DECIMAL(12,2) NULL DEFAULT 0,
    vendida_a_id INT NULL,
    precio_vta DECIMAL(12,2) NULL DEFAULT 0,
    fecha_fact DATE NULL,
    cant_fact DECIMAL(12,2) NULL DEFAULT 0,
    facturada_a_id INT NULL,
    precio_fac DECIMAL(12,2) NULL DEFAULT 0,
    n_fact VARCHAR(50) NULL,
    n_remt VARCHAR(50) NULL,
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_fecha (fecha),
    INDEX idx_orden (orden),
    FOREIGN KEY (vendida_a_id) REFERENCES usuarios(id) ON DELETE SET NULL,
    FOREIGN KEY (facturada_a_id) REFERENCES usuarios(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci;
