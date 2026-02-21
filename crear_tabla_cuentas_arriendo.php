<?php
/**
 * Añade columnas arriendo_id y arriendo_fecha a cuentas si no existen.
 * Para identificar movimientos de arriendo (precio azúcar pendiente).
 */
if (!isset($conexion)) return;
$r = mysqli_query($conexion, "SHOW COLUMNS FROM cuentas LIKE 'arriendo_id'");
if ($r && mysqli_num_rows($r) == 0) {
    mysqli_query($conexion, "ALTER TABLE cuentas ADD COLUMN arriendo_id INT NULL AFTER monto");
    mysqli_query($conexion, "ALTER TABLE cuentas ADD COLUMN arriendo_fecha TINYINT NULL AFTER arriendo_id");
}
