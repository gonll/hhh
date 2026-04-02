-- Ejecutar UNA VEZ en el servidor (phpMyAdmin o mysql) si la app no puede hacer ALTER automático.
-- Usuario con permisos ALTER en la base del sistema.

ALTER TABLE propiedades
  ADD COLUMN mapa_lat DECIMAL(10,7) DEFAULT NULL,
  ADD COLUMN mapa_lng DECIMAL(10,7) DEFAULT NULL,
  ADD COLUMN mapa_enlace VARCHAR(768) DEFAULT NULL,
  ADD COLUMN fotos_json TEXT DEFAULT NULL;
