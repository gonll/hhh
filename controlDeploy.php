<?php
/**
 * Control de deploy – solo se carga cuando ENVIRONMENT=dev (include desde index.php).
 * Top navbar: botones Subir código y Subir base de datos.
 */
?>
<div style="display: flex; align-items: center; gap: 12px; flex-wrap: wrap; padding: 8px 12px; background: #e9ecef; border-radius: 4px; border: 1px solid #dee2e6;">
    <?php if (isset($_GET['deploy'])) {
        $deployOk = ($_GET['deploy'] === 'ok');
        $deployMsg = isset($_GET['msg']) ? htmlspecialchars($_GET['msg']) : '';
        echo '<span style="font-size:11px; ' . ($deployOk ? 'color:#155724;' : 'color:#721c24;') . '">' . ($deployOk ? '✓ Deploy realizado.' : '✗ ' . ($deployMsg ?: 'Error.')) . '</span>';
    } ?>
    <?php
    $nivel_ok = isset($_SESSION['acceso_nivel']) && (int)$_SESSION['acceso_nivel'] >= 3;
    $es_sofia = function_exists('tenant_inmob_es_sofia') && tenant_inmob_es_sofia();
    ?>
    <?php if ($nivel_ok || $es_sofia): ?>
    <form method="post" action="deploy_accion.php" style="display: inline; margin: 0;" onsubmit="return confirm('¿Ejecutar git add, commit, pull y push?');">
        <input type="hidden" name="action" value="subir_codigo">
        <button type="submit" style="background:#17a2b8; color:white; border:none; padding:8px 12px; border-radius:4px; font-weight:bold; font-size:11px; cursor:pointer;">Subir código</button>
    </form>
    <?php endif; ?>
    <?php if (isset($_SESSION['acceso_nivel']) && $_SESSION['acceso_nivel'] >= 3): ?>
    <form id="formSubirDb" method="post" action="deploy_accion.php" style="display: inline; margin: 0;">
        <input type="hidden" name="action" value="subir_db">
        <button type="submit" style="background:#6c757d; color:white; border:none; padding:8px 12px; border-radius:4px; font-weight:bold; font-size:11px; cursor:pointer;">Subir base de datos</button>
    </form>
    <a href="bajar_bd_servidor.php" id="linkBajarBd" style="background:#495057; color:white; padding:8px 12px; border-radius:4px; font-weight:bold; font-size:11px; text-decoration:none; display:inline-block;">Bajar BD del servidor</a>
    <?php if (defined('ENVIRONMENT') && ENVIRONMENT === 'dev'): ?>
    <a href="ctacel.php" style="background:#6f42c1; color:white; padding:8px 12px; border-radius:4px; font-weight:bold; font-size:11px; text-decoration:none; display:inline-block;">CtaCel (prueba)</a>
    <?php endif; ?>
    <script>
    (function () {
        function confirmarTriple(pasos) {
            for (var i = 0; i < pasos.length; i++) {
                if (!window.confirm('Paso ' + (i + 1) + ' de 3:\n\n' + pasos[i])) {
                    return false;
                }
            }
            return true;
        }
        var formSubir = document.getElementById('formSubirDb');
        if (formSubir) {
            formSubir.addEventListener('submit', function (e) {
                if (!confirmarTriple([
                    'Va a generar un volcado de la base LOCAL y enviarlo al SERVIDOR. Esto puede sobrescribir datos en producción. ¿Desea continuar?',
                    'Segunda confirmación: la operación puede tardar varios minutos y afectar a todos los usuarios del sistema en línea. ¿Está seguro?',
                    'Tercera y última confirmación: si pulsa Aceptar, se ejecutará el envío. No hay deshacer. ¿Proceder?'
                ])) {
                    e.preventDefault();
                }
            });
        }
        var linkBajar = document.getElementById('linkBajarBd');
        if (linkBajar) {
            linkBajar.addEventListener('click', function (e) {
                e.preventDefault();
                if (!confirmarTriple([
                    'Va a DESCARGAR desde el servidor un archivo .sql con toda la base de datos. ¿Desea continuar?',
                    'Segunda confirmación: el archivo puede ser muy grande. Asegúrese de guardarlo en un lugar seguro. ¿Continuar?',
                    'Tercera y última confirmación: se iniciará la descarga del volcado del servidor. ¿Proceder?'
                ])) {
                    return;
                }
                window.location.href = linkBajar.getAttribute('href');
            });
        }
    })();
    </script>
    <?php endif; ?>
</div>
