<?php

declare(strict_types=1);

/**
 * Asegura columna alquileres.modelo_contrato (HYLL/BGH) y default HYLL.
 */
function alquileres_asegurar_columna_modelo_contrato(mysqli $conexion): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $r = @mysqli_query($conexion, "SHOW COLUMNS FROM alquileres LIKE 'modelo_contrato'");
    if ($r && mysqli_num_rows($r) === 0) {
        @mysqli_query(
            $conexion,
            "ALTER TABLE alquileres ADD COLUMN modelo_contrato VARCHAR(16) NOT NULL DEFAULT 'HYLL' COMMENT 'HYLL plantilla estandar H&L; BGH oficinas' AFTER destino"
        );
    }
    $done = true;
}
