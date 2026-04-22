<?php
/**
 * Detección de navegador móvil para redirección (CtaCel, Partes desde cel o escritorio según usuario).
 * Usa Client Hints (Sec-CH-UA-Mobile) cuando el navegador los envía y refuerza con User-Agent.
 */
function hh_es_user_agent_movil(): bool
{
    $chMobile = $_SERVER['HTTP_SEC_CH_UA_MOBILE'] ?? '';
    if ($chMobile === '?1' || strcasecmp(trim($chMobile, '"'), '?1') === 0) {
        return true;
    }

    $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
    if ($ua === '') {
        return false;
    }

    return (bool) preg_match(
        '/Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini|Mobile|Tablet|' .
        'CriOS|FxiOS|EdgA\/|EdgiOS|OPR\/|SamsungBrowser|UCBrowser|MiuiBrowser|' .
        'wv\)|\bwv\b|Mobile\/|MQQBrowser|QQBrowser|FBSV|Instagram|Line\/|KAKAOTALK/i',
        $ua
    );
}

/**
 * Acceso que desde celular debe abrir el index principal (escritorio), no CtaCel.
 * - adminhugo: escritorio completo.
 * - silvana: índice principal (sistema Hugo), celular o PC.
 * - sofia (exacto, no sofiacel): mismo índice principal con límites de ámbito Sofía (tenant_inmob_*).
 * - sofiacel: false → va a CtaCel con datos de Sofía.
 * El resto de cuentas móviles (p. ej. "celular") van a CtaCel.
 */
function hh_movil_ir_escritorio_desde_acceso(?string $usuario_acceso = null): bool
{
    $u = trim((string) ($usuario_acceso ?? ($_SESSION['acceso_usuario'] ?? '')));

    if (strcasecmp($u, 'adminhugo') === 0) {
        return true;
    }
    if (strcasecmp($u, 'silvana') === 0) {
        return true;
    }

    return strcasecmp($u, 'sofia') === 0;
}

/** Acceso que desde celular debe abrir Partes desde cel (gestión finca móvil), no CtaCel. */
function hh_movil_ir_partes_desde_cel(?string $usuario_acceso = null): bool
{
    $u = trim((string) ($usuario_acceso ?? ($_SESSION['acceso_usuario'] ?? '')));

    return strcasecmp($u, 'enrique') === 0;
}
