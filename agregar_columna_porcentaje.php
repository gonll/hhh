<?php
/**
 * Ejecutar UNA vez para agregar la columna porcentaje a la tabla propiedades.
 * Luego podés borrar este archivo.
 */
include 'db.php';

$sql = "ALTER TABLE propiedades ADD COLUMN porcentaje DECIMAL(5,2) DEFAULT NULL AFTER consorcio";

if (mysqli_query($conexion, $sql)) {
    echo "OK: Columna 'porcentaje' agregada a la tabla propiedades.";
} else {
    if (strpos(mysqli_error($conexion), 'Duplicate column') !== false) {
        echo "La columna 'porcentaje' ya existe.";
    } else {
        echo "Error: " . mysqli_error($conexion);
    }
}
?>
