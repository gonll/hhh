<?php
include 'verificar_sesion.php';
require_once __DIR__ . '/helpers_tenant_inmobiliaria.php';
tenant_inmob_asegurar_esquema($conexion);
$id_caja_central = tenant_inmob_id_usuario_caja_central($conexion);
$ap_excl_libro_transf = mysqli_real_escape_string($conexion, 'TRANSFERENCIAS (LIBRO)');
$tw_usuarios = tenant_inmob_sql_usuarios($conexion, 'u');
$sqlPersonas = "SELECT u.id, u.apellido FROM usuarios u
    WHERE u.apellido <> '$ap_excl_libro_transf' AND ($tw_usuarios)
    ORDER BY (u.id = " . (int) $id_caja_central . ") DESC, u.apellido ASC";
$resPersonas = mysqli_query($conexion, $sqlPersonas);
$lista_personas_movil = [];
if ($resPersonas) {
    while ($row = mysqli_fetch_assoc($resPersonas)) {
        $lista_personas_movil[] = [
            'id' => (int) $row['id'],
            'apellido' => (string) ($row['apellido'] ?? ''),
        ];
    }
}
$json_personas_movil = json_encode($lista_personas_movil, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title>Estados de cuentas</title>
    <style>
        :root {
            --fondo: #f0f4f8;
            --tarjeta: #fff;
            --borde: #d8e0ea;
            --acento: #0d6efd;
            --texto: #1a1d21;
            --muted: #5c6b7a;
            --salir-bg: #b91c1c;
            --salir-hover: #991b1b;
        }
        * { box-sizing: border-box; }
        html { -webkit-text-size-adjust: 100%; }
        body {
            margin: 0;
            min-height: 100dvh;
            font-family: system-ui, -apple-system, "Segoe UI", Roboto, sans-serif;
            font-size: 16px;
            color: var(--texto);
            background: var(--fondo);
            padding: 12px 12px 88px;
            padding-bottom: max(88px, env(safe-area-inset-bottom, 0px) + 72px);
        }
        .wrap {
            max-width: 560px;
            margin: 0 auto;
            display: flex;
            flex-direction: column;
            gap: 14px;
        }
        h1 {
            font-size: 1.15rem;
            font-weight: 700;
            margin: 0 0 12px;
            text-align: center;
            color: var(--texto);
        }
        .parte {
            background: var(--tarjeta);
            border: 1px solid var(--borde);
            border-radius: 12px;
            padding: 16px 14px;
            min-height: 72px;
            box-shadow: 0 1px 3px rgba(0,0,0,.06);
        }
        .parte h2 {
            margin: 0 0 10px;
            font-size: 0.95rem;
            font-weight: 600;
            color: var(--acento);
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .parte h2 span.num {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 26px;
            height: 26px;
            border-radius: 8px;
            background: #e7f1ff;
            color: var(--acento);
            font-size: 0.8rem;
            font-weight: 700;
        }
        .parte .placeholder {
            margin: 0;
            font-size: 0.88rem;
            color: var(--muted);
            line-height: 1.45;
        }
        .bloque-busq-o-titulo {
            margin-top: 4px;
        }
        .bloque-busq-o-titulo .busq-personas-wrap {
            margin-top: 0;
        }
        .busq-personas-wrap.oculto-busq {
            display: none !important;
        }
        .busq-personas-wrap {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 10px 12px;
            background: #f8fafc;
            border: 1px solid var(--borde);
            border-radius: 10px;
            cursor: text;
        }
        .busq-personas-wrap .lupa {
            font-size: calc(1.1rem * 0.65);
            line-height: 1;
            flex-shrink: 0;
            user-select: none;
        }
        .busq-personas-wrap input[type="search"] {
            flex: 1;
            min-width: 0;
            border: none;
            background: transparent;
            font-size: calc(0.95rem * 0.65);
            outline: none;
        }
        .busq-personas-wrap input::placeholder {
            color: #94a3b8;
            font-size: calc(0.85rem * 0.65);
        }
        .lineas-personas-movil {
            margin-top: 12px;
            display: flex;
            flex-direction: column;
            gap: 6px;
        }
        .lineas-personas-movil.lineas-ocultas {
            display: none !important;
        }
        .lineas-personas-movil.lineas-inactivas {
            display: none !important;
        }
        .linea-persona-movil {
            padding: 10px 12px;
            background: #f1f5f9;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            font-size: calc(0.92rem * 0.65);
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.02em;
            min-height: 42px;
            display: flex;
            align-items: center;
            color: var(--texto);
        }
        .linea-persona-movil.vacia {
            color: #94a3b8;
            font-weight: 500;
        }
        .linea-persona-movil:not(.vacia) {
            cursor: pointer;
        }
        .linea-persona-movil.seleccionada {
            background: #dbeafe;
            border-color: var(--acento);
            box-shadow: inset 0 0 0 1px var(--acento);
        }
        .titulo-estado-cuenta.titulo-en-slot-busq {
            margin-top: 0;
        }
        .titulo-estado-cuenta {
            margin-top: 12px;
            padding: 10px 12px;
            min-height: 42px;
            height: 42px;
            display: flex;
            flex-direction: row;
            align-items: center;
            justify-content: center;
            font-size: calc(0.88rem * 0.65);
            font-weight: 700;
            text-align: center;
            text-transform: uppercase;
            letter-spacing: 0.02em;
            line-height: 1.2;
            color: #0c4a6e;
            background: linear-gradient(180deg, #e0f2fe 0%, #bae6fd 100%);
            border: 1px solid #7dd3fc;
            border-radius: 8px;
            box-sizing: border-box;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .titulo-estado-cuenta:not([hidden]) {
            cursor: pointer;
        }
        .titulo-estado-cuenta[hidden] {
            display: none !important;
        }
        .parte2-movimientos-wrap {
            margin-top: 8px;
            font-size: calc(0.92rem * 0.65 * 0.75);
            line-height: 1.2;
        }
        .parte2-movimientos-wrap.parte2-vacia {
            color: var(--muted);
            font-size: calc(0.88rem * 0.65 * 0.75);
            padding: 8px 0;
        }
        .parte3-inquilino-wrap {
            margin-top: 6px;
            font-size: calc(0.9rem * 0.65);
            line-height: 1.45;
            color: var(--texto);
        }
        .parte3-inquilino-wrap.parte3-vacia {
            color: var(--muted);
            font-size: calc(0.85rem * 0.65);
        }
        .parte3-inquilino-wrap .parte3-lista {
            display: flex;
            flex-direction: column;
            gap: 0;
        }
        .parte3-inquilino-wrap .parte3-item {
            padding: 10px 0;
            border-bottom: 1px solid #e2e8f0;
        }
        .parte3-inquilino-wrap .parte3-item:last-child {
            border-bottom: none;
            padding-bottom: 2px;
        }
        .parte3-inquilino-wrap .parte3-prop {
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.02em;
            color: #1e3a5f;
            line-height: 1.3;
            word-break: break-word;
            white-space: normal;
        }
        .parte3-inquilino-wrap .parte3-venc-label {
            margin-top: 6px;
            font-size: 0.92em;
            color: var(--muted);
            font-weight: 600;
        }
        .parte3-inquilino-wrap .parte3-fecha {
            display: inline-block;
            margin-top: 2px;
            font-weight: 800;
            font-size: 1.08em;
            color: #0c4a6e;
        }
        .parte3-inquilino-wrap .parte3-sin-fecha {
            display: inline-block;
            margin-top: 2px;
            font-style: italic;
            color: #94a3b8;
            font-weight: 600;
        }
        .movil-mov-fila {
            display: grid;
            grid-template-columns: 4.5em minmax(0, 1fr) minmax(5.2rem, max-content) minmax(5.2rem, max-content);
            gap: 4px 6px;
            align-items: center;
            padding: 6px 4px;
            border-bottom: 1px solid #e2e8f0;
            white-space: nowrap;
            overflow: hidden;
            min-height: 1.35em;
        }
        .movil-mov-fila:last-child {
            border-bottom: none;
        }
        .movil-mov-fila .m-fecha,
        .movil-mov-fila .m-concepto {
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .movil-mov-fila .m-fecha {
            font-weight: 600;
            color: #334155;
        }
        .movil-mov-fila .m-concepto {
            text-transform: uppercase;
            font-weight: 500;
        }
        .movil-mov-fila .m-monto, .movil-mov-fila .m-saldo {
            text-align: right;
            font-variant-numeric: tabular-nums;
            font-weight: 600;
            overflow: visible;
            text-overflow: clip;
            white-space: nowrap;
        }
        .movil-mov-fila .m-monto-pos { color: #198754; }
        .movil-mov-fila .m-monto-neg { color: #c82333; }
        .movil-mov-fila .m-saldo-pos { color: #0d6efd; }
        .movil-mov-fila .m-saldo-neg { color: #c82333; }
        .movil-mov-fila .m-saldo-ultimo {
            font-size: 1.2em;
            font-weight: 900;
            letter-spacing: -0.02em;
        }
        .movil-mov-fila .m-saldo-ultimo.m-saldo-pos {
            color: #004aad;
            text-shadow: 0 0 0.06em rgba(13, 110, 253, 0.55);
        }
        .movil-mov-fila .m-saldo-ultimo.m-saldo-neg {
            color: #8b1010;
            text-shadow: 0 0 0.06em rgba(200, 35, 51, 0.45);
        }
        .movil-mov-encab {
            display: grid;
            grid-template-columns: 4.5em minmax(0, 1fr) minmax(5.2rem, max-content) minmax(5.2rem, max-content);
            gap: 4px 6px;
            padding: 0 4px 4px;
            font-weight: 700;
            font-size: 0.92em;
            color: var(--muted);
            text-transform: uppercase;
            border-bottom: 1px solid var(--borde);
        }
        .movil-mov-encab span:nth-child(1),
        .movil-mov-encab span:nth-child(2) {
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .movil-mov-encab span:nth-child(3),
        .movil-mov-encab span:nth-child(4) {
            overflow: visible;
            text-overflow: clip;
            text-align: right;
            white-space: nowrap;
        }
        .bar-inferior-cta {
            position: fixed;
            left: 12px;
            right: 12px;
            bottom: max(12px, env(safe-area-inset-bottom, 12px));
            display: flex;
            flex-direction: row;
            justify-content: space-between;
            align-items: center;
            gap: 10px;
            pointer-events: none;
            z-index: 50;
        }
        .bar-inferior-cta > * {
            pointer-events: auto;
        }
        .bar-inferior-cta .btn-resumen-cta {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 12px 14px;
            min-height: 44px;
            max-width: 52%;
            background: var(--acento);
            color: #fff;
            border: none;
            border-radius: 10px;
            font-weight: 600;
            font-size: 0.82rem;
            line-height: 1.15;
            cursor: pointer;
            box-shadow: 0 4px 14px rgba(13, 110, 253, 0.28);
            font-family: inherit;
            text-align: center;
        }
        .bar-inferior-cta .btn-resumen-cta:hover, .bar-inferior-cta .btn-resumen-cta:active {
            background: #0b5ed7;
        }
        .bar-inferior-cta a.btn-salir-cta {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 12px 20px;
            min-height: 44px;
            background: var(--salir-bg);
            color: #fff;
            text-decoration: none;
            font-weight: 600;
            font-size: 0.95rem;
            border-radius: 10px;
            box-shadow: 0 4px 14px rgba(0,0,0,.22);
        }
        .bar-inferior-cta a.btn-salir-cta:hover, .bar-inferior-cta a.btn-salir-cta:active {
            background: var(--salir-hover);
        }
        .backdrop-resumen-cta {
            display: flex;
            position: fixed;
            inset: 0;
            z-index: 300;
            background: rgba(15, 23, 42, 0.48);
            align-items: stretch;
            justify-content: center;
            padding: 0;
        }
        .backdrop-resumen-cta.hidden {
            display: none !important;
        }
        .backdrop-resumen-cta .modal-resumen-sheet {
            display: flex;
            flex-direction: column;
            width: 100%;
            max-width: 720px;
            height: 100%;
            max-height: 100dvh;
            margin: 0 auto;
            background: #fff;
            box-shadow: 0 -8px 32px rgba(0,0,0,0.18);
        }
        @media (min-width: 600px) {
            .backdrop-resumen-cta {
                align-items: center;
                padding: 16px;
            }
            .backdrop-resumen-cta .modal-resumen-sheet {
                height: min(92dvh, 900px);
                border-radius: 14px;
                overflow: hidden;
            }
        }
        .modal-resumen-toolbar {
            flex-shrink: 0;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            padding: 12px 14px;
            padding-top: max(12px, env(safe-area-inset-top, 12px));
            border-bottom: 1px solid var(--borde);
            background: #f8fafc;
        }
        .modal-resumen-toolbar h2 {
            margin: 0;
            font-size: 1rem;
            font-weight: 700;
            color: var(--texto);
        }
        .modal-resumen-toolbar button {
            flex-shrink: 0;
            padding: 10px 16px;
            min-height: 44px;
            border: 1px solid var(--borde);
            border-radius: 10px;
            background: #fff;
            font-weight: 600;
            font-size: 0.9rem;
            cursor: pointer;
            font-family: inherit;
        }
        .modal-resumen-toolbar button:active {
            background: #f1f5f9;
        }
        .iframe-resumen-cta {
            flex: 1;
            min-height: 0;
            width: 100%;
            border: 0;
            background: #fff;
        }
        @media (min-width: 768px) {
            body { padding: 24px 24px 96px; }
            .wrap { max-width: 640px; gap: 18px; }
            h1 { font-size: 1.35rem; }
            .parte { padding: 20px 18px; min-height: 88px; }
        }
    </style>
</head>
<body>
    <div class="wrap">
        <header>
            <h1>Estados de cuentas</h1>
        </header>

        <form class="form-movil" action="#" method="post" onsubmit="return false;">
            <section class="parte" aria-labelledby="p1">
                <h2 id="p1"><span class="num">1</span> Personas</h2>
                <div class="bloque-busq-o-titulo">
                    <div class="busq-personas-wrap" id="busqPersonasMovilWrap" role="search" aria-label="Buscar personas">
                        <span class="lupa" aria-hidden="true">🔍</span>
                        <input type="search" id="buscPersonasMovil" placeholder="BUSCAR (EJ: HERR HU)…" autocomplete="off" inputmode="search" aria-label="Buscar personas">
                    </div>
                    <div id="tituloEstadoCuentaMovil" class="titulo-estado-cuenta titulo-en-slot-busq" hidden role="button" tabindex="0" title="Tocar para buscar otra persona"></div>
                </div>
                <div class="lineas-personas-movil lineas-inactivas" id="lineasPersonasMovil" role="list" aria-live="polite">
                    <div class="linea-persona-movil vacia" role="listitem" data-id="" tabindex="-1">—</div>
                    <div class="linea-persona-movil vacia" role="listitem" data-id="" tabindex="-1">—</div>
                    <div class="linea-persona-movil vacia" role="listitem" data-id="" tabindex="-1">—</div>
                </div>
            </section>
            <section class="parte" aria-labelledby="p2">
                <h2 id="p2"><span class="num">2</span> Últimos 10 movimientos</h2>
                <div id="parte2Movimientos" class="parte2-movimientos-wrap parte2-vacia" aria-live="polite">
                    <p class="parte2-placeholder" style="margin:0;">Elegí una persona arriba.</p>
                </div>
            </section>
            <section class="parte" aria-labelledby="p3">
                <h2 id="p3"><span class="num">3</span> Propiedades alquiladas y vencimiento</h2>
                <div id="parte3Inquilino" class="parte3-inquilino-wrap parte3-vacia" aria-live="polite">
                    <p class="parte3-placeholder" style="margin:0;">Elegí una persona arriba.</p>
                </div>
            </section>
        </form>
    </div>

    <div class="bar-inferior-cta">
        <button type="button" class="btn-resumen-cta" id="btnResumenCta" aria-haspopup="dialog" aria-controls="dialogResumenCta">Resumen de Cta.</button>
        <a href="logout.php" class="btn-salir-cta">Salir</a>
    </div>

    <div id="backdropResumenCta" class="backdrop-resumen-cta hidden" aria-hidden="true" role="dialog" aria-modal="true" aria-labelledby="tituloDialogResumen">
        <div class="modal-resumen-sheet" id="dialogResumenCta" role="document">
            <div class="modal-resumen-toolbar">
                <h2 id="tituloDialogResumen">Resumen de cuentas</h2>
                <button type="button" id="cerrarResumenCta">Cerrar</button>
            </div>
            <iframe class="iframe-resumen-cta" id="iframeResumenCta" title="Resumen de cuentas" src="about:blank"></iframe>
        </div>
    </div>
    <script>
    (function() {
        var PERSONAS_MOVIL = <?php echo $json_personas_movil; ?>;
        var idPersonaSeleccionadaMovil = null;

        function mostrarLineasPorLupaActivada() {
            var cont = document.getElementById('lineasPersonasMovil');
            var tit = document.getElementById('tituloEstadoCuentaMovil');
            if (!cont) {
                return;
            }
            if (tit && !tit.hidden) {
                return;
            }
            cont.classList.remove('lineas-inactivas');
        }

        function ocultarLineasSiLupaInactiva() {
            var cont = document.getElementById('lineasPersonasMovil');
            var busc = document.getElementById('buscPersonasMovil');
            var wrap = document.getElementById('busqPersonasMovilWrap');
            if (!cont || !busc) {
                return;
            }
            if (cont.classList.contains('lineas-ocultas')) {
                return;
            }
            var ae = document.activeElement;
            if (ae === busc || (wrap && wrap.contains(ae))) {
                return;
            }
            cont.classList.add('lineas-inactivas');
        }

        function actualizarTituloEstadoCuenta(nombreVisible) {
            var tit = document.getElementById('tituloEstadoCuentaMovil');
            var lineasCont = document.getElementById('lineasPersonasMovil');
            var busc = document.getElementById('buscPersonasMovil');
            var wrapBusq = document.getElementById('busqPersonasMovilWrap');
            if (!tit || !lineasCont) {
                return;
            }
            if (!nombreVisible) {
                tit.innerHTML = '';
                tit.textContent = '';
                tit.hidden = true;
                if (wrapBusq) {
                    wrapBusq.classList.remove('oculto-busq');
                }
                lineasCont.classList.remove('lineas-ocultas');
                if (busc && document.activeElement === busc) {
                    lineasCont.classList.remove('lineas-inactivas');
                } else {
                    lineasCont.classList.add('lineas-inactivas');
                }
                limpiarParte2();
                limpiarParte3();
                return;
            }
            tit.textContent = 'Estado de cuenta de : ' + nombreVisible;
            tit.hidden = false;
            if (wrapBusq) {
                wrapBusq.classList.add('oculto-busq');
            }
            lineasCont.classList.add('lineas-ocultas');
            lineasCont.classList.add('lineas-inactivas');
            if (idPersonaSeleccionadaMovil) {
                cargarMovimientosParte2(idPersonaSeleccionadaMovil);
                cargarParte3Inquilino(idPersonaSeleccionadaMovil);
            }
        }

        function quitarSeleccionLineas() {
            var cont = document.getElementById('lineasPersonasMovil');
            if (!cont) {
                return;
            }
            cont.querySelectorAll('.linea-persona-movil').forEach(function(l) {
                l.classList.remove('seleccionada');
                if (l.classList.contains('vacia')) {
                    l.setAttribute('tabindex', '-1');
                }
            });
        }

        function escHtml(s) {
            var d = document.createElement('div');
            d.textContent = s == null ? '' : String(s);
            return d.innerHTML;
        }

        function fmtMontoCtaCel(n) {
            var x = Number(n);
            if (isNaN(x)) {
                x = 0;
            }
            return x.toLocaleString('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        }

        function parseJsonResiliente(textoPlano) {
            var t = String(textoPlano == null ? '' : textoPlano).replace(/^\uFEFF/, '').trim();
            if (!t) {
                throw new Error('respuesta-vacia');
            }
            try {
                return JSON.parse(t);
            } catch (e1) {}
            var iniObj = t.indexOf('{');
            var finObj = t.lastIndexOf('}');
            if (iniObj >= 0 && finObj > iniObj) {
                try {
                    return JSON.parse(t.slice(iniObj, finObj + 1));
                } catch (e2) {}
            }
            var muestra = t.slice(0, 120).replace(/\s+/g, ' ');
            throw new Error('no-json: ' + muestra);
        }

        function limpiarParte2() {
            var el = document.getElementById('parte2Movimientos');
            if (!el) {
                return;
            }
            el.classList.add('parte2-vacia');
            el.style.fontSize = '';
            el.innerHTML = '<p class="parte2-placeholder" style="margin:0;">Elegí una persona arriba.</p>';
        }

        function limpiarParte3() {
            var el = document.getElementById('parte3Inquilino');
            if (!el) {
                return;
            }
            el.classList.add('parte3-vacia');
            el.innerHTML = '<p class="parte3-placeholder" style="margin:0;">Elegí una persona arriba.</p>';
        }

        function cargarParte3Inquilino(usuarioId) {
            var el = document.getElementById('parte3Inquilino');
            if (!el) {
                return;
            }
            if (!usuarioId) {
                limpiarParte3();
                return;
            }
            el.classList.remove('parte3-vacia');
            el.innerHTML = '<p style="margin:0;color:#64748b;">Cargando…</p>';
            var urlP3 = new URL('obtener_vencimiento_inquilino_ctacel.php', window.location.href);
            urlP3.searchParams.set('id', String(usuarioId));
            fetch(urlP3.href, { credentials: 'same-origin' })
                .then(function(r) {
                    return r.text().then(function(text) {
                        if (!r.ok) {
                            throw new Error('HTTP ' + r.status);
                        }
                        return parseJsonResiliente(text);
                    });
                })
                .then(function(data) {
                    if (!data || !data.ok) {
                        el.classList.add('parte3-vacia');
                        el.innerHTML = '<p style="margin:0;">' + escHtml((data && data.msg) || 'Sin datos') + '</p>';
                        return;
                    }
                    var txt = (data.texto != null) ? String(data.texto) : '';
                    var filas = data.filas;
                    el.classList.toggle('parte3-vacia', !data.es_inquilino);
                    if (data.es_inquilino && filas && filas.length) {
                        var h = '<div class="parte3-lista">';
                        filas.forEach(function(f) {
                            var nom = (f.propiedad != null) ? String(f.propiedad) : '';
                            var ven = f.vencimiento ? String(f.vencimiento) : '';
                            h += '<div class="parte3-item">';
                            h += '<div class="parte3-prop">' + escHtml(nom) + '</div>';
                            h += '<div class="parte3-venc-label">Vencimiento</div>';
                            if (ven) {
                                h += '<span class="parte3-fecha" aria-label="Fecha de vencimiento">' + escHtml(ven) + '</span>';
                            } else {
                                h += '<span class="parte3-sin-fecha">Sin fecha cargada</span>';
                            }
                            h += '</div>';
                        });
                        h += '</div>';
                        el.innerHTML = h;
                    } else {
                        el.innerHTML = '<p style="margin:0;">' + escHtml(txt || 'No consta como inquilino/a con contrato vigente.') + '</p>';
                    }
                })
                .catch(function(err) {
                    el.classList.add('parte3-vacia');
                    var det = (err && err.message) ? String(err.message) : '';
                    el.innerHTML = '<p style="margin:0;">Error al cargar datos.' + (det ? ' (' + escHtml(det) + ')' : '') + '</p>';
                });
        }

        function ajustarFuenteParte2() {
            var wrap = document.getElementById('parte2Movimientos');
            if (!wrap || wrap.classList.contains('parte2-vacia')) {
                return;
            }
            var conceptos = wrap.querySelectorAll('.m-concepto');
            if (!conceptos.length) {
                return;
            }
            var encConcepto = wrap.querySelector('.movil-mov-encab span:nth-child(2)');
            var rootPx = parseFloat(getComputedStyle(document.documentElement).fontSize) || 16;
            var start = rootPx * 0.92 * 0.65 * 0.75;
            var minPx = 6;
            var px;
            for (px = start; px >= minPx; px -= 0.5) {
                wrap.style.fontSize = px + 'px';
                var ok = true;
                for (var i = 0; i < conceptos.length; i++) {
                    if (conceptos[i].scrollWidth > conceptos[i].clientWidth + 1) {
                        ok = false;
                        break;
                    }
                }
                if (ok && encConcepto && encConcepto.scrollWidth > encConcepto.clientWidth + 1) {
                    ok = false;
                }
                if (ok) {
                    break;
                }
            }
        }

        function cargarMovimientosParte2(usuarioId) {
            var el = document.getElementById('parte2Movimientos');
            if (!el) {
                return;
            }
            if (!usuarioId) {
                limpiarParte2();
                limpiarParte3();
                return;
            }
            el.classList.remove('parte2-vacia');
            el.style.fontSize = '';
            el.innerHTML = '<p style="margin:0;color:#64748b;">Cargando…</p>';
            var urlMov = new URL('obtener_movimientos_ctacel.php', window.location.href);
            urlMov.searchParams.set('id', String(usuarioId));
            fetch(urlMov.href, { credentials: 'same-origin' })
                .then(function(r) {
                    return r.text().then(function(text) {
                        if (!r.ok) {
                            throw new Error('HTTP ' + r.status);
                        }
                        try {
                            return JSON.parse(text);
                        } catch (e2) {
                            throw new Error('no-json');
                        }
                    });
                })
                .then(function(data) {
                    if (!data || !data.ok) {
                        el.classList.add('parte2-vacia');
                        el.innerHTML = '<p style="margin:0;">' + escHtml((data && data.msg) || 'Sin datos') + '</p>';
                        return;
                    }
                    var movs = data.movimientos || [];
                    if (movs.length === 0) {
                        el.classList.add('parte2-vacia');
                        el.innerHTML = '<p class="parte2-placeholder" style="margin:0;">Sin movimientos en cuenta.</p>';
                        return;
                    }
                    el.classList.remove('parte2-vacia');
                    var h = '<div class="movil-mov-encab"><span>Fecha</span><span>Concepto</span><span>Monto</span><span>Saldo</span></div>';
                    movs.forEach(function(m, idx) {
                        var clsM = (m.monto >= 0) ? 'm-monto-pos' : 'm-monto-neg';
                        var clsS = (m.saldo >= 0) ? 'm-saldo-pos' : 'm-saldo-neg';
                        var ultimo = idx === movs.length - 1;
                        var clsSaldo = 'm-saldo ' + clsS + (ultimo ? ' m-saldo-ultimo' : '');
                        h += '<div class="movil-mov-fila">' +
                            '<span class="m-fecha">' + escHtml(m.fecha) + '</span>' +
                            '<span class="m-concepto">' + escHtml(m.concepto) + '</span>' +
                            '<span class="m-monto ' + clsM + '">$ ' + escHtml(fmtMontoCtaCel(m.monto)) + '</span>' +
                            '<span class="' + clsSaldo + '">$ ' + escHtml(fmtMontoCtaCel(m.saldo)) + '</span>' +
                            '</div>';
                    });
                    el.innerHTML = h;
                    requestAnimationFrame(function() {
                        requestAnimationFrame(ajustarFuenteParte2);
                    });
                })
                .catch(function(err) {
                    el.classList.add('parte2-vacia');
                    var det = (err && err.message) ? String(err.message) : '';
                    el.innerHTML = '<p style="margin:0;">Error al cargar movimientos.' + (det ? ' (' + escHtml(det) + ')' : '') + '</p>';
                });
        }

        function coincidenTerminos(apellidoUpper, terminos) {
            if (!terminos.length) {
                return false;
            }
            return terminos.every(function(t) {
                return apellidoUpper.indexOf(t) >= 0;
            });
        }

        function filtrarPersonasMovil() {
            var inp = document.getElementById('buscPersonasMovil');
            var cont = document.getElementById('lineasPersonasMovil');
            if (!inp || !cont) {
                return;
            }
            var titPrev = document.getElementById('tituloEstadoCuentaMovil');
            if (titPrev && !titPrev.hidden) {
                idPersonaSeleccionadaMovil = null;
                quitarSeleccionLineas();
                actualizarTituloEstadoCuenta(null);
            }
            var busqueda = inp.value.toUpperCase().trim();
            var terminos = busqueda.split(/\s+/).filter(Boolean);
            var filtrados = [];
            if (terminos.length) {
                for (var i = 0; i < PERSONAS_MOVIL.length && filtrados.length < 3; i++) {
                    var p = PERSONAS_MOVIL[i];
                    var ap = (p.apellido || '').toUpperCase();
                    if (coincidenTerminos(ap, terminos)) {
                        filtrados.push(p);
                    }
                }
            }
            var sigueEnLista = idPersonaSeleccionadaMovil && filtrados.some(function(p) {
                return String(p.id) === String(idPersonaSeleccionadaMovil);
            });
            if (!sigueEnLista) {
                idPersonaSeleccionadaMovil = null;
                quitarSeleccionLineas();
                actualizarTituloEstadoCuenta(null);
            }

            var lineas = cont.querySelectorAll('.linea-persona-movil');
            for (var j = 0; j < 3; j++) {
                var el = lineas[j];
                if (!el) {
                    continue;
                }
                el.classList.remove('seleccionada');
                if (filtrados[j]) {
                    el.textContent = filtrados[j].apellido;
                    el.setAttribute('data-id', String(filtrados[j].id));
                    el.classList.remove('vacia');
                    el.setAttribute('tabindex', '0');
                    if (idPersonaSeleccionadaMovil && String(filtrados[j].id) === String(idPersonaSeleccionadaMovil)) {
                        el.classList.add('seleccionada');
                    }
                } else {
                    el.textContent = '—';
                    el.setAttribute('data-id', '');
                    el.classList.add('vacia');
                    el.setAttribute('tabindex', '-1');
                }
            }
        }

        var busc = document.getElementById('buscPersonasMovil');
        var wrapBusq = document.getElementById('busqPersonasMovilWrap');
        if (busc) {
            busc.addEventListener('focus', function() {
                mostrarLineasPorLupaActivada();
            });
            busc.addEventListener('blur', function() {
                window.setTimeout(ocultarLineasSiLupaInactiva, 200);
            });
            busc.addEventListener('input', filtrarPersonasMovil);
            busc.addEventListener('keyup', filtrarPersonasMovil);
        }
        if (wrapBusq && busc) {
            wrapBusq.addEventListener('click', function(e) {
                if (e.target === busc) {
                    return;
                }
                busc.focus();
            });
        }

        var contLineas = document.getElementById('lineasPersonasMovil');
        if (contLineas) {
            contLineas.addEventListener('click', function(e) {
                var line = e.target.closest('.linea-persona-movil');
                if (!line || !contLineas.contains(line)) {
                    return;
                }
                var id = line.getAttribute('data-id');
                if (!id || line.classList.contains('vacia')) {
                    idPersonaSeleccionadaMovil = null;
                    quitarSeleccionLineas();
                    actualizarTituloEstadoCuenta(null);
                    if (busc) {
                        busc.focus();
                    }
                    return;
                }
                idPersonaSeleccionadaMovil = id;
                quitarSeleccionLineas();
                line.classList.add('seleccionada');
                actualizarTituloEstadoCuenta(line.textContent.trim());
            });
        }

        var resizeParte2Timer;
        window.addEventListener('resize', function() {
            clearTimeout(resizeParte2Timer);
            resizeParte2Timer = setTimeout(ajustarFuenteParte2, 150);
        });

        var titEstado = document.getElementById('tituloEstadoCuentaMovil');
        if (titEstado) {
            titEstado.addEventListener('click', function() {
                if (titEstado.hidden) {
                    return;
                }
                idPersonaSeleccionadaMovil = null;
                quitarSeleccionLineas();
                actualizarTituloEstadoCuenta(null);
                filtrarPersonasMovil();
                mostrarLineasPorLupaActivada();
                var b = document.getElementById('buscPersonasMovil');
                if (b) {
                    b.focus();
                }
            });
            titEstado.addEventListener('keydown', function(e) {
                if (titEstado.hidden) {
                    return;
                }
                if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    titEstado.click();
                }
            });
        }

        var btnResumen = document.getElementById('btnResumenCta');
        var backdropResumen = document.getElementById('backdropResumenCta');
        var cerrarResumenBtn = document.getElementById('cerrarResumenCta');
        var iframeResumen = document.getElementById('iframeResumenCta');
        var urlResumenCta = new URL('resumen_cuentas.php?embed=1', window.location.href).href;

        function abrirResumenCta() {
            if (!backdropResumen || !iframeResumen) {
                return;
            }
            if (iframeResumen.getAttribute('src') === 'about:blank') {
                iframeResumen.src = urlResumenCta;
            }
            backdropResumen.classList.remove('hidden');
            backdropResumen.setAttribute('aria-hidden', 'false');
            document.body.style.overflow = 'hidden';
        }

        function cerrarResumenCtaFn() {
            if (!backdropResumen) {
                return;
            }
            backdropResumen.classList.add('hidden');
            backdropResumen.setAttribute('aria-hidden', 'true');
            document.body.style.overflow = '';
        }

        if (btnResumen) {
            btnResumen.addEventListener('click', abrirResumenCta);
        }
        if (cerrarResumenBtn) {
            cerrarResumenBtn.addEventListener('click', cerrarResumenCtaFn);
        }
        if (backdropResumen) {
            backdropResumen.addEventListener('click', function(e) {
                if (e.target === backdropResumen) {
                    cerrarResumenCtaFn();
                }
            });
        }
        window.addEventListener('message', function(ev) {
            try {
                if (ev.data && ev.data.type === 'ctacel_cerrar_resumen') {
                    cerrarResumenCtaFn();
                }
            } catch (eMsg) {}
        });
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && backdropResumen && !backdropResumen.classList.contains('hidden')) {
                cerrarResumenCtaFn();
            }
        });
    })();
    </script>
</body>
</html>
