-- ============================================================
-- Tabla para usuarios de acceso al sistema (login).
-- Nivel 3 = máximo (administrador), 2 = estándar, 1 = restringido.
-- El nombre de usuario aquí NO tiene que coincidir con el
-- "usuario" (apellido) que se usa dentro del sistema.
-- ============================================================

CREATE TABLE IF NOT EXISTS accesos (
  id INT AUTO_INCREMENT PRIMARY KEY,
  usuario VARCHAR(80) NOT NULL UNIQUE,
  clave VARCHAR(255) NOT NULL,
  nivel_acceso TINYINT NOT NULL DEFAULT 2,
  creado_por_id INT NULL,
  fecha_creacion DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_usuario (usuario),
  INDEX idx_nivel (nivel_acceso)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- La clave se guarda con password_hash() en PHP (no en texto plano).
-- El primer usuario se crea desde login.php la primera vez (formulario
-- "Primera vez: crear administrador") o ejecutando agregar_tabla_accesos.php.
