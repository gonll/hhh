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
    <form method="post" action="deploy_accion.php" style="display: inline; margin: 0;" onsubmit="return confirm('¿Ejecutar git add, commit, pull y push?');">
        <input type="hidden" name="action" value="subir_codigo">
        <button type="submit" style="background:#17a2b8; color:white; border:none; padding:8px 12px; border-radius:4px; font-weight:bold; font-size:11px; cursor:pointer;">Subir código</button>
    </form>
    <form method="post" action="deploy_accion.php" style="display: inline; margin: 0;" onsubmit="return confirm('¿Generar dump de la base local y enviarlo al servidor?');">
        <input type="hidden" name="action" value="subir_db">
        <button type="submit" style="background:#6c757d; color:white; border:none; padding:8px 12px; border-radius:4px; font-weight:bold; font-size:11px; cursor:pointer;">Subir base de datos</button>
    </form>
</div>
