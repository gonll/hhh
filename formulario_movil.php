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
    <title>Formulario móvil</title>
    <style>
        :root {
            --fondo: #f0f4f8;
            --tarjeta: #fff;
            --borde: #d8e0ea;
            --acento: #0d6efd;
            --texto: #1a1d21;
            --muted: #5c6b7a;
            --volver-bg: #334155;
            --volver-hover: #1e293b;
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
            margin: 0 0 4px;
            text-align: center;
            color: var(--texto);
        }
        .sub {
            text-align: center;
            font-size: 0.8rem;
            color: var(--muted);
            margin: 0 0 8px;
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
        .busq-personas-wrap {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-top: 4px;
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
        .bar-volver {
            position: fixed;
            right: 12px;
            bottom: max(12px, env(safe-area-inset-bottom, 12px));
            left: 12px;
            display: flex;
            justify-content: flex-end;
            pointer-events: none;
        }
        .bar-volver a {
            pointer-events: auto;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 12px 20px;
            min-height: 44px;
            background: var(--volver-bg);
            color: #fff;
            text-decoration: none;
            font-weight: 600;
            font-size: 0.95rem;
            border-radius: 10px;
            box-shadow: 0 4px 14px rgba(0,0,0,.2);
        }
        .bar-volver a:hover, .bar-volver a:active {
            background: var(--volver-hover);
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
            <h1>Formulario</h1>
            <p class="sub">Vista optimizada para celular · seis bloques (contenido a definir)</p>
        </header>

        <form class="form-movil" action="#" method="post" onsubmit="return false;">
            <section class="parte" aria-labelledby="p1">
                <h2 id="p1"><span class="num" aria-hidden="true">1</span> Parte 1 — Personas</h2>
                <div class="busq-personas-wrap" id="busqPersonasMovilWrap" role="search" aria-label="Buscar personas">
                    <span class="lupa" aria-hidden="true">🔍</span>
                    <input type="search" id="buscPersonasMovil" placeholder="BUSCAR (EJ: HERR HU)…" autocomplete="off" inputmode="search" aria-label="Buscar personas">
                </div>
                <div class="lineas-personas-movil lineas-inactivas" id="lineasPersonasMovil" role="list" aria-live="polite">
                    <div class="linea-persona-movil vacia" role="listitem" data-id="" tabindex="-1">—</div>
                    <div class="linea-persona-movil vacia" role="listitem" data-id="" tabindex="-1">—</div>
                    <div class="linea-persona-movil vacia" role="listitem" data-id="" tabindex="-1">—</div>
                </div>
                <div id="tituloEstadoCuentaMovil" class="titulo-estado-cuenta" hidden role="button" tabindex="0" title="Tocar para buscar otra persona"></div>
            </section>
            <section class="parte" aria-labelledby="p2">
                <h2 id="p2"><span class="num" aria-hidden="true">2</span> Parte 2</h2>
                <p class="placeholder">Contenido pendiente.</p>
            </section>
            <section class="parte" aria-labelledby="p3">
                <h2 id="p3"><span class="num" aria-hidden="true">3</span> Parte 3</h2>
                <p class="placeholder">Contenido pendiente.</p>
            </section>
            <section class="parte" aria-labelledby="p4">
                <h2 id="p4"><span class="num" aria-hidden="true">4</span> Parte 4</h2>
                <p class="placeholder">Contenido pendiente.</p>
            </section>
            <section class="parte" aria-labelledby="p5">
                <h2 id="p5"><span class="num" aria-hidden="true">5</span> Parte 5</h2>
                <p class="placeholder">Contenido pendiente.</p>
            </section>
            <section class="parte" aria-labelledby="p6">
                <h2 id="p6"><span class="num" aria-hidden="true">6</span> Parte 6</h2>
                <p class="placeholder">Contenido pendiente.</p>
            </section>
        </form>
    </div>

    <div class="bar-volver">
        <a href="index.php?desktop=1">Volver</a>
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
            if (!tit || !lineasCont) {
                return;
            }
            if (!nombreVisible) {
                tit.innerHTML = '';
                tit.hidden = true;
                lineasCont.classList.remove('lineas-ocultas');
                if (busc && document.activeElement === busc) {
                    lineasCont.classList.remove('lineas-inactivas');
                } else {
                    lineasCont.classList.add('lineas-inactivas');
                }
                return;
            }
            tit.textContent = 'Estado de cuenta de : ' + nombreVisible;
            tit.hidden = false;
            lineasCont.classList.add('lineas-ocultas');
            lineasCont.classList.add('lineas-inactivas');
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
    })();
    </script>
</body>
</html>
