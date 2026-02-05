<?php
/**
 * Ejecutar UNA vez para agregar la columna ciudad a la tabla propiedades.
 */
include 'db.php';

$sql = "ALTER TABLE propiedades ADD COLUMN ciudad VARCHAR(100) DEFAULT NULL AFTER propiedad";

if (mysqli_query($conexion, $sql)) {
    echo "OK: Columna 'ciudad' agregada a la tabla propiedades.";
} else {
    if (mysqli_errno($conexion) == 1060) {
        echo "La columna 'ciudad' ya existe.";
    } else {
        echo "Error: " . mysqli_error($conexion);
    }
}
