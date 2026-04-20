<?php
include 'verificar_sesion.php';
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
                <h2 id="p1"><span class="num" aria-hidden="true">1</span> Parte 1</h2>
                <p class="placeholder">Contenido pendiente.</p>
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
</body>
</html>
