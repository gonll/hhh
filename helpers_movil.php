<?php
/**
 * Detección simple de navegador móvil por User-Agent (entrada al sistema vía index).
 */
function hh_es_user_agent_movil(): bool
{
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
    if ($ua === '') {
        return false;
    }
    return (bool) preg_match(
        '/Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini|Mobile|Tablet/i',
        $ua
    );
}
