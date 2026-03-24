<?php
/**
 * Envía por correo un PDF de liquidación de producto (gestión azúcares).
 * POST (multipart): accion=enviar, stock_id, nombre_archivo, pdf_liq (archivo)
 *
 * Usa el mismo Gmail SMTP que expensas y el resto del sistema: mail_config.php + smtp_enviar.php
 */
session_start();
header('Content-Type: application/json; charset=utf-8');

if (empty($_SESSION['acceso_id']) || !isset($_SESSION['acceso_nivel'])) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Sesión vencida. Iniciá sesión de nuevo.']);
    exit;
}
if ((int)$_SESSION['acceso_nivel'] < 2) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Sin permiso.']);
    exit;
}

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/smtp_enviar.php';
require_once __DIR__ . '/inc_azucar_destinatarios_factura.php';

$resCol = @mysqli_query($conexion, "SHOW COLUMNS FROM stock LIKE 'liq_prod_pdf_email_enviado'");
if ($resCol && mysqli_num_rows($resCol) === 0) {
    @mysqli_query($conexion, "ALTER TABLE stock ADD liq_prod_pdf_email_enviado TINYINT(1) NOT NULL DEFAULT 0 AFTER n_remt");
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || ($_POST['accion'] ?? '') !== 'enviar') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Solicitud no válida.']);
    exit;
}

$stock_id = (int)($_POST['stock_id'] ?? 0);
$nombre_usuario = trim((string)($_POST['nombre_archivo'] ?? ''));

if ($stock_id < 1) {
    echo json_encode(['ok' => false, 'error' => 'Registro inválido.']);
    exit;
}

$r = mysqli_fetch_assoc(mysqli_query($conexion, "SELECT id, n_fact FROM stock WHERE id = $stock_id LIMIT 1"));
if (!$r || trim((string)($r['n_fact'] ?? '')) === '') {
    echo json_encode(['ok' => false, 'error' => 'El registro no tiene N° factura.']);
    exit;
}

if (!isset($_FILES['pdf_liq']) || (int)$_FILES['pdf_liq']['error'] !== UPLOAD_ERR_OK) {
    $err = (int)($_FILES['pdf_liq']['error'] ?? UPLOAD_ERR_NO_FILE);
    echo json_encode(['ok' => false, 'error' => 'No se recibió el PDF (código ' . $err . ').']);
    exit;
}

$f = $_FILES['pdf_liq'];
$ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
if ($ext !== 'pdf') {
    echo json_encode(['ok' => false, 'error' => 'Solo se admiten archivos PDF.']);
    exit;
}

$tmp = $f['tmp_name'];
if (!is_uploaded_file($tmp)) {
    echo json_encode(['ok' => false, 'error' => 'Subida inválida.']);
    exit;
}

if (class_exists('finfo')) {
    $fi = new finfo(FILEINFO_MIME_TYPE);
    $mime = $fi->file($tmp);
    if ($mime && stripos($mime, 'pdf') === false && $mime !== 'application/octet-stream') {
        echo json_encode(['ok' => false, 'error' => 'El archivo no parece ser un PDF.']);
        exit;
    }
}

$base = $nombre_usuario !== '' ? $nombre_usuario : pathinfo($f['name'], PATHINFO_FILENAME);
$base = preg_replace('/[\\\\\/:*?"<>|]/', '_', $base);
$base = preg_replace('/\.pdf$/i', '', $base);
$base = substr(trim($base), 0, 180);
if ($base === '') {
    $base = 'liquido_producto';
}
$nombre_adjunto = $base . '.pdf';
$safeNombreAdjunto = preg_replace('/[^\pL\pN._\- ]/u', '_', $nombre_adjunto);
$safeNombreAdjunto = str_replace(["\r", "\n", '"'], '', $safeNombreAdjunto);
if ($safeNombreAdjunto === '' || !preg_match('/\.pdf$/i', $safeNombreAdjunto)) {
    $safeNombreAdjunto = 'liquido_producto.pdf';
}

$rowsDest = get_azucar_factura_mail_rows($conexion);
$destinatarios = [];
foreach ($rowsDest as $row) {
    if (filter_var($row['email'], FILTER_VALIDATE_EMAIL)) {
        $destinatarios[] = $row['email'];
    }
}
if ($destinatarios === []) {
    echo json_encode([
        'ok' => false,
        'error' => 'No hay destinatarios configurados. Usá «Se envía a» y el botón + arriba de Facturar.',
    ]);
    exit;
}

$subject = 'Liquido Producto – ' . $safeNombreAdjunto;
$n_fact_txt = trim((string)$r['n_fact']);
$body = "Liquidación de producto adjunta.\r\n\r\n";
$body .= "Archivo: $safeNombreAdjunto\r\n";
$body .= "Stock ID: $stock_id\r\n";
$body .= "N° factura: $n_fact_txt\r\n";

$cuerpo_html = '<!DOCTYPE html><html><head><meta charset="utf-8"></head><body><pre style="font-family:Arial,sans-serif;font-size:14px;white-space:pre-wrap;">'
    . htmlspecialchars($body, ENT_QUOTES, 'UTF-8')
    . '</pre></body></html>';

$lastErr = '';
$allOk = true;
foreach ($destinatarios as $to) {
    if (!enviar_mail_smtp_con_adjunto($to, $subject, $cuerpo_html, $tmp, 'application/pdf', $safeNombreAdjunto)) {
        $allOk = false;
        $lastErr = smtp_ultimo_error();
    }
}

if (!$allOk) {
    echo json_encode([
        'ok' => false,
        'error' => $lastErr !== '' ? $lastErr : 'Falló el envío a algún destinatario. Revisá mail_config.php.',
    ]);
    exit;
}

mysqli_query($conexion, "UPDATE stock SET liq_prod_pdf_email_enviado = 1 WHERE id = $stock_id");
echo json_encode(['ok' => true]);
