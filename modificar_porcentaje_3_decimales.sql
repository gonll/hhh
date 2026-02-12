-- Modificar la columna porcentaje en la tabla propiedades para permitir 3 decimales.
-- Ejecutar UNA vez en la base de datos (phpMyAdmin o consola MySQL).

ALTER TABLE propiedades
MODIFY COLUMN porcentaje DECIMAL(6,3) DEFAULT NULL;

-- DECIMAL(6,3) = hasta 999.999 (3 decimales). Para % entre 0 y 100 alcanza (ej: 100.000, 5.255).
