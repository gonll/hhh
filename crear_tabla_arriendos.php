<?php
/**
 * Crea la tabla arriendos si no existe. Incluir desde arriendos.php o arriendo_form.php.
 */
if (!isset($conexion)) return;
$sql = "CREATE TABLE IF NOT EXISTS arriendos (
    id INT(11) NOT NULL AUTO_INCREMENT,
    propietario_id INT(11) NOT NULL,
    apoderado_id INT(11) NOT NULL,
    arrendatario_id INT(11) NOT NULL,
    padron VARCHAR(20) DEFAULT NULL,
    descripcion_finca TEXT DEFAULT NULL,
    fecha_cobro_1 DATE DEFAULT NULL,
    fecha_cobro_2 DATE DEFAULT NULL,
    kilos_fecha_1 DECIMAL(12,2) DEFAULT NULL,
    kilos_fecha_2 DECIMAL(12,2) DEFAULT NULL,
    descontar_iva TINYINT(1) NOT NULL DEFAULT 0,
    monto_descuentos DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    paga_comunal TINYINT(1) NOT NULL DEFAULT 0,
    paga_provincial TINYINT(1) NOT NULL DEFAULT 0,
    fecha_creacion TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    fecha_modificacion TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY fk_arriendo_propietario (propietario_id),
    KEY fk_arriendo_apoderado (apoderado_id),
    KEY fk_arriendo_arrendatario (arrendatario_id),
    CONSTRAINT fk_arriendo_propietario FOREIGN KEY (propietario_id) REFERENCES usuarios (id) ON UPDATE CASCADE,
    CONSTRAINT fk_arriendo_apoderado FOREIGN KEY (apoderado_id) REFERENCES usuarios (id) ON UPDATE CASCADE,
    CONSTRAINT fk_arriendo_arrendatario FOREIGN KEY (arrendatario_id) REFERENCES usuarios (id) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci";
mysqli_query($conexion, $sql);

// Añadir columna fecha_vencimiento_contrato si no existe
$r = mysqli_query($conexion, "SHOW COLUMNS FROM arriendos LIKE 'fecha_vencimiento_contrato'");
if ($r && mysqli_num_rows($r) == 0) {
    mysqli_query($conexion, "ALTER TABLE arriendos ADD COLUMN fecha_vencimiento_contrato DATE DEFAULT NULL AFTER paga_provincial");
}
?>
