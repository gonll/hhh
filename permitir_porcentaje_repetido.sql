-- Permitir que los valores de % (porcentaje) puedan repetirse en la tabla propiedades.
-- Ejecutar SOLO si en la base de datos existe un índice UNIQUE sobre la columna porcentaje.
-- En phpMyAdmin: tabla propiedades -> pestaña Estructura -> ver Índices.
-- Si existe un índice UNIQUE llamado "porcentaje" (o similar), ejecutar:

-- ALTER TABLE propiedades DROP INDEX porcentaje;

-- Si el índice tiene otro nombre, reemplazar "porcentaje" por el nombre correcto.
-- Después de quitarlo, varios registros podrán tener el mismo valor de %.
