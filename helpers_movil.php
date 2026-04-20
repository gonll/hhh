<?php
/**
 * Detección de navegador móvil para redirección a ctacel.php
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
