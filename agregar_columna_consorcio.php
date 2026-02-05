<?php
/**
 * Ejecutar UNA vez para agregar la columna consorcio a la tabla usuarios.
 * Luego podés borrar este archivo.
 */
include 'db.php';

$sql = "ALTER TABLE usuarios ADD COLUMN consorcio VARCHAR(100) DEFAULT NULL AFTER celular";

if (mysqli_query($conexion, $sql)) {
    echo "OK: Columna 'consorcio' agregada a la tabla usuarios.";
} else {
    if (strpos(mysqli_error($conexion), 'Duplicate column') !== false) {
        echo "La columna 'consorcio' ya existe.";
    } else {
        echo "Error: " . mysqli_error($conexion);
    }
}
?>
