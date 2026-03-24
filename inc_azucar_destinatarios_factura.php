<?php
/**
 * Destinatarios del mail de factura / PDF liq prod (gestión azúcares).
 */

function ensure_azucar_factura_mail_table($conexion) {
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    mysqli_query($conexion, "CREATE TABLE IF NOT EXISTS azucar_factura_mail_destinatarios (
        id INT AUTO_INCREMENT PRIMARY KEY,
        email VARCHAR(255) NOT NULL,
        UNIQUE KEY uq_email (email)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci");
    $c = mysqli_fetch_assoc(mysqli_query($conexion, "SELECT COUNT(*) AS n FROM azucar_factura_mail_destinatarios"));
    if ((int)($c['n'] ?? 0) === 0) {
        $d = mysqli_real_escape_string($conexion, 'hectorhugoherrera@gmail.com');
        mysqli_query($conexion, "INSERT INTO azucar_factura_mail_destinatarios (email) VALUES ('$d')");
    }
}

/**
 * @return list<array{id:int,email:string}>
 */
function get_azucar_factura_mail_rows($conexion) {
    ensure_azucar_factura_mail_table($conexion);
    $rows = [];
    $r = mysqli_query($conexion, "SELECT id, email FROM azucar_factura_mail_destinatarios ORDER BY id ASC");
    if ($r) {
        while ($x = mysqli_fetch_assoc($r)) {
            $rows[] = ['id' => (int)$x['id'], 'email' => (string)$x['email']];
        }
    }
    return $rows;
}
