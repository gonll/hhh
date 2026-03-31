<?php
include 'db.php';
include 'verificar_sesion.php';
require_once __DIR__ . '/config_clave_borrado.php';
include 'respaldar_automatico.php'; // Respaldo automático diario
$archivo_respaldo = hacerRespaldoAutomatico(); // Ejecutar respaldo si no se hizo hoy (retorna nombre de archivo si se hizo nuevo)
if ((int)date('j') > 10) include 'actualizar_ipc_desde_api.php';
include 'liquidar_alquileres_mes.php'; // Liquidar alquileres del mes si aún no se cargaron (desde día 1)
// Consulta para listar usuarios, poniendo a "CAJA" (ID 1) primero, con fecha fin de contrato vigente
$sql = "SELECT u.*, 
        (SELECT a.fecha_fin 
         FROM alquileres a 
         WHERE (a.inquilino1_id = u.id OR a.inquilino2_id = u.id) 
         AND a.estado = 'VIGENTE' 
         ORDER BY a.fecha_fin DESC 
         LIMIT 1) as fecha_fin_contrato
        FROM usuarios u 
        ORDER BY (u.id = 1) DESC, u.apellido ASC";
$resultado = mysqli_query($conexion, $sql);

// Consulta para detectar si falta el índice del mes actual
$mes_actual = date('Y-m-01');
$res_check = mysqli_query($conexion, "SELECT id FROM indices WHERE fecha = '$mes_actual' LIMIT 1");
$falta_indice = (mysqli_num_rows($res_check) == 0);
$nivelAcceso = (int)($_SESSION['acceso_nivel'] ?? 0);
$soloLectura = ($nivelAcceso < 2);
// Nivel 0: usuario zafra → Cosecha; resto → Partes desde cel
if ($nivelAcceso === 0) {
    $usuario = (string)($_SESSION['acceso_usuario'] ?? '');
    header('Location: ' . (stripos($usuario, 'zafra') !== false ? 'cosecha.php' : 'partes_desde_cel.php'));
    exit;
}
// Usuarios para modal Ant/cel (nivel 3): todos excepto CAJA (id 1)
$usuarios_anticipo = [];
$consorcios_lista = [];
require_once __DIR__ . '/config_tutoriales.php';
if ($nivelAcceso === 3) {
    $r_ant = mysqli_query($conexion, "SELECT id, apellido FROM usuarios WHERE id != 1 ORDER BY apellido ASC");
    if ($r_ant) {
        while ($u = mysqli_fetch_assoc($r_ant)) {
            $usuarios_anticipo[] = $u;
        }
    }
    $r_con = mysqli_query($conexion, "SELECT id, apellido, consorcio FROM usuarios WHERE UPPER(apellido) LIKE 'CONSORCIO%' ORDER BY apellido ASC");
    if ($r_con) {
        while ($c = mysqli_fetch_assoc($r_con)) {
            $consorcios_lista[] = ['id' => (int)$c['id'], 'apellido' => $c['apellido'], 'consorcio' => trim($c['consorcio'] ?? '')];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Sistema HHH 2026</title>
    <!-- Favicons -->
    <link rel="icon" href="/favicon.ico" sizes="any">
    <link rel="icon" type="image/png" sizes="16x16" href="/favicon-16x16.png">
    <link rel="icon" type="image/png" sizes="32x32" href="/favicon-32x32.png">

    <!-- Apple -->
    <link rel="apple-touch-icon" href="/apple-touch-icon.png">

    <!-- Android / PWA -->
    <link rel="manifest" href="/site.webmanifest">
    <link rel="icon" type="image/png" sizes="192x192" href="/android-chrome-192x192.png">
    <link rel="icon" type="image/png" sizes="512x512" href="/android-chrome-512x512.png">
    <style>
        body { font-family: 'Segoe UI', sans-serif; background: #f0f2f5; margin: 0; padding: 10px; display: flex; flex-direction: column; align-items: stretch; }
        .deploy-navbar { flex: 0 0 auto; margin-bottom: 8px; }
        .contenedor-principal { display: flex; align-items: flex-start; flex: 1; min-width: 0; }
        /* Contenedor Lateral Izquierdo */
        .contenedor-usuarios { 
            background: white; padding: 10px; border-radius: 8px; 
            box-shadow: 0 2px 10px rgba(0,0,0,0.1); width: 25vw; margin-right: 1vw; 
            height: 90vh; display: flex; flex-direction: column; font-size: 10px; 
        }

        /* Panel Central de Movimientos */
        .contenedor-grid { 
            background: white; padding: 15px; border-radius: 8px; 
            box-shadow: 0 2px 10px rgba(0,0,0,0.1); width: 71vw; height: calc(90vh - 2cm); position: relative;
            display: flex; flex-direction: column; min-height: 0;
        }

        /* Botón Word - Posicionado a la izquierda del reloj */
        .btn-word-recibo { 
            position: absolute; top: 15px; right: calc(150px - 2cm); 
            background: #007bff; color: white; border: none; padding: 5px 10px; 
            border-radius: 4px; font-size: 10px; font-weight: bold; cursor: pointer; 
            display: none; z-index: 100; 
        }
        
        /* Botones de Administración lateral */
        .btn-abm-prop { 
            background: #6c757d; color: white; text-decoration: none; text-align: center; 
            padding: 8px; margin-top: 8px; border-radius: 4px; font-weight: bold; 
            font-size: 9px; text-transform: uppercase; display: block;
        }
        .btn-contrato { background: #007bff; } 
        .btn-recibos-porteros { background: #6f42c1; }
        .btn-indice { background: #f39c12; }
        .btn-tutoriales { background: #17a2b8; color: white !important; }
        .link-tutorial:hover { background: #e7f3ff !important; border-color: #007bff !important; color: #007bff !important; }
        .btn-finca { background: #28a745; }
        .btn-arriendos { background: #0047AB; }
        .btn-cosecha { background: #6f42c1; }
        .btn-admin-prop { background: #17a2b8; }
        
        /* Cabecera Central y Reloj */
        .cabecera-detalle { display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px; flex-shrink: 0; }
        #reloj-sistema { font-size: 1rem; color: #aaa; font-weight: 300; min-width: 150px; text-align: right; }
        .btn-ant-cel { display: none; background: #17a2b8; color: white; border: none; padding: 6px 12px; border-radius: 4px; font-size: 11px; font-weight: bold; cursor: pointer; }
        .btn-ant-cel:hover { background: #138496; }
        @media (max-width: 768px) { .btn-ant-cel { display: inline-block; } }
        .btn-imprimir-estado { display: inline-block; background: #D4A5A5; color: #333; border: 1px solid #D4A5A5; padding: 6px 12px; border-radius: 4px; font-size: 11px; font-weight: bold; cursor: pointer; }
        .btn-imprimir-estado:hover { background: #C89595; }
        /* Modal Ant/cel responsive - pantalla completa en cel con 3 secciones */
        .modal-ant-cel-overlay { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 9998; align-items: center; justify-content: center; padding: 10px; box-sizing: border-box; }
        .modal-ant-cel-overlay.activo { display: flex; }
        .modal-ant-cel { background: white; border-radius: 8px; padding: 20px; max-width: 400px; width: 100%; box-shadow: 0 4px 20px rgba(0,0,0,0.2); display: flex; flex-direction: column; }
        .modal-ant-cel h3 { margin: 0 0 15px 0; font-size: 1rem; color: #007bff; }
        .modal-ant-cel label { display: block; margin-bottom: 4px; font-weight: bold; font-size: 11px; }
        .modal-ant-cel input, .modal-ant-cel select { width: 100%; padding: 8px; border: 1px solid #ced4da; border-radius: 4px; box-sizing: border-box; font-size: 12px; }
        .modal-ant-cel .form-g { margin-bottom: 12px; }
        .modal-ant-cel .ant-cabecera { padding-bottom: 12px; }
        .modal-ant-cel .ant-seccion { border-bottom: 2px solid #dee2e6; padding: 16px 0; }
        .modal-ant-cel .ant-seccion:last-of-type { border-bottom: none; }
        .modal-ant-cel .ant-seccion-titulo { font-size: 13px; font-weight: bold; color: #333; margin-bottom: 10px; padding-bottom: 6px; border-bottom: 1px solid #eee; }
        .ant-fila-fecha-monto { display: flex; gap: 12px; align-items: flex-end; }
        .ant-fila-fecha-monto .form-g { flex: 1; margin-bottom: 0; min-width: 0; }
        @media (max-width: 400px) { .ant-fila-fecha-monto { flex-direction: column; align-items: stretch; } .ant-fila-fecha-monto .form-g { margin-bottom: 12px; } .ant-fila-fecha-monto .form-g:last-child { margin-bottom: 0; } }
        .modal-ant-cel .botones, .modal-ant-cel .ant-botones { display: flex; gap: 12px; margin-top: 15px; flex-shrink: 0; }
        .modal-ant-cel .botones button, .modal-ant-cel .ant-botones button { padding: 8px 16px; border: none; border-radius: 4px; font-weight: bold; cursor: pointer; font-size: 12px; flex: 1; }
        .modal-ant-cel .btn-cerrar { background: #6c757d; color: white; }
        .modal-ant-cel .btn-guardar-ant { background: #28a745; color: white; }
        @media (max-width: 768px) {
            .modal-ant-cel-overlay.activo { padding: 0; align-items: stretch; }
            .modal-ant-cel { max-width: none; width: 100%; height: 100%; min-height: 100vh; min-height: 100dvh; border-radius: 0; padding: 0; display: flex; flex-direction: column; }
            .modal-ant-cel .ant-cabecera { padding: 16px; border-bottom: 2px solid #007bff; background: #f8f9fa; flex-shrink: 0; }
            .modal-ant-cel .ant-cabecera h3 { margin: 0; font-size: 1.1rem; }
            .modal-ant-cel .ant-cabecera p { margin: 6px 0 0 0; font-size: 11px; color: #666; }
            .modal-ant-cel .ant-cuerpo { flex: 1; display: flex; flex-direction: column; overflow: auto; }
            .modal-ant-cel .ant-seccion { padding: 20px 16px; border-bottom: 3px solid #dee2e6; min-height: 0; }
            .modal-ant-cel .ant-seccion-titulo { font-size: 14px; margin-bottom: 12px; }
            .modal-ant-cel input, .modal-ant-cel select { font-size: 16px; min-height: 44px; padding: 10px 12px; }
            .modal-ant-cel .ant-botones { margin-top: auto; padding: 16px; border-top: 3px solid #dee2e6; background: #f8f9fa; flex-shrink: 0; display: flex; gap: 12px; }
            .modal-ant-cel .ant-botones button { padding: 14px 20px; font-size: 16px; min-height: 48px; }
        }
        @media (max-width: 480px) { .modal-ant-cel .ant-botones button { padding: 14px; font-size: 15px; } }
        .buscador-ant-wrap { position: relative; }
        .buscador-ant-wrap input[type="text"] { padding-right: 36px; }
        .buscador-ant-wrap .ico-lupa { position: absolute; right: 10px; top: 50%; transform: translateY(-50%); pointer-events: none; color: #6c757d; font-size: 1rem; }
        .buscador-ant-resultados { position: absolute; top: 100%; left: 0; right: 0; background: white; border: 1px solid #ced4da; border-top: none; max-height: 180px; overflow-y: auto; z-index: 100; display: none; font-size: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.1); }
        .buscador-ant-resultados .item-ant { padding: 8px 10px; cursor: pointer; border-bottom: 1px solid #eee; }
        .buscador-ant-resultados .item-ant:hover { background: #e7f3ff; }
        .buscador-ant-resultados .item-ant:last-child { border-bottom: none; }
        .form-g.fecha-ant-wrap { position: relative; }
        #ant_fecha_cal { display: none; position: absolute; left: 0; top: 0; width: 100%; height: 100%; margin: 0; border: 1px solid #007bff; border-radius: 4px; box-sizing: border-box; font-size: inherit; }

        .scroll-usuarios { flex-grow: 1; overflow-y: auto; border: 1px solid #eee; margin-top: 5px; }
        .scroll-grid { flex: 1; min-height: 0; display: flex; flex-direction: column; border: 1px solid #ddd; margin-top: 5px; background: #fff; overflow: hidden; }
        .tabla-header { flex-shrink: 0; width: 100%; }
        .scroll-movimientos { flex: 1 1 0; min-height: 150px; overflow-y: scroll !important; overflow-x: auto; scrollbar-gutter: stable; }
        .scroll-movimientos::-webkit-scrollbar { width: 12px; }
        .scroll-movimientos::-webkit-scrollbar-track { background: #f1f1f1; border-radius: 6px; }
        .scroll-movimientos::-webkit-scrollbar-thumb { background: #888; border-radius: 6px; }
        .scroll-movimientos::-webkit-scrollbar-thumb:hover { background: #555; }
        .tabla-carga { flex-shrink: 0; border-top: 1px solid #ddd; }

        .tabla-datos { width: 100%; border-collapse: collapse; font-size: 12px; table-layout: fixed; }
        .tabla-datos th { background: #007bff; color: white; padding: 8px 5px; font-weight: bold; }
        .tabla-header { border-bottom: 1px solid #007bff; }
        .tabla-body td { border: 1px solid #ddd; padding: 8px 5px; }
        
        .tabla-usuarios { width: 100%; border-collapse: collapse; }
        .tabla-usuarios td { 
            border-bottom: 1px solid #eee; padding: 8px 6px; cursor: pointer; 
            text-transform: uppercase; display: flex; justify-content: space-between; align-items: center; 
        }

        /* Botón Editar Inline */
        .btn-edit-inline { 
            background: #D4A5A5; color: white; border: none; padding: 2px 5px; 
            border-radius: 3px; font-size: 8px; cursor: pointer; display: none; font-weight: bold;
        }
        .fila-seleccionada .btn-edit-inline { display: block; }

        .fila-seleccionada { background-color: #e7f3ff !important; font-weight: bold; border-left: 3px solid #007bff; }
        .fila-mov-seleccionada { background-color: #fff9c4 !important; outline: 1px solid #fbc02d; }

        .btn-caja { flex: 0 0 25%; padding: 12px; border: none; border-radius: 4px; color: white; font-weight: bold; cursor: pointer; opacity: 0.3; pointer-events: none; text-transform: uppercase; }
        .btn-activo { opacity: 1 !important; pointer-events: auto !important; }
        .btn-caja.btn-extra { opacity: 1 !important; pointer-events: auto !important; text-transform: none; font-size: 8px; padding: 4px 6px; line-height: 1.2; }
        .grid-botones-extra { display: grid; grid-template-columns: 1fr 1fr; grid-template-rows: 1fr 1fr; gap: 4px; align-self: stretch; min-width: 0; }
        .btn-exp { width: 26ch; min-width: 26ch; padding: 3px 6px; border: 1px solid #007bff; background: white; color: #007bff; border-radius: 3px; cursor: pointer; text-align: left; font-weight: 500; font-size: 7px; line-height: 1.2; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; box-sizing: border-box; }
        .btn-exp:hover { background: #e7f3ff; }
        .btn-exp.btn-liquidar { background: #28a745; color: white; border-color: #28a745; }
        .btn-exp.btn-liquidar:hover { background: #218838; }
        .btn-exp.btn-imprimir-exp { background: #D4A5A5; color: #333; border-color: #D4A5A5; }
        .btn-exp.btn-imprimir-exp:hover { background: #C89595; }

        .al-izq { text-align: left !important; padding-left: 10px !important; }
        .al-der { text-align: right !important; padding-right: 10px !important; }
        .al-cen { text-align: center !important; }

        .alerta-indice {
            background: #fff3cd; color: #856404; padding: 5px; margin-top: 10px; 
            border-radius: 4px; border: 1px solid #ffeeba; text-align: center; font-weight: bold;
        }

        /* Sin icono de fecha en campo fecha y sin flechitas en campo monto */
        input[type="date"]::-webkit-calendar-picker-indicator {
            opacity: 0;
            cursor: text;
            width: 100%;
            height: 100%;
        }
        input[type="date"]::-webkit-inner-spin-button { display: none; }
        input[type="text"].input-monto { -moz-appearance: textfield; }
        .modal-overlay { display: none; position: fixed; top:0; left:0; right:0; bottom:0; background: rgba(0,0,0,0.5); z-index: 1000; justify-content: center; align-items: center; }
        .modal-overlay.visible { display: flex; }
        .modal-cobro { background: white; padding: 20px; border-radius: 8px; box-shadow: 0 4px 20px rgba(0,0,0,0.3); width: 320px; }
        .modal-cobro h3 { margin: 0 0 15px; color: #007bff; font-size: 14px; text-transform: uppercase; }
        .modal-cobro label { display: block; margin: 8px 0 3px; font-size: 11px; font-weight: bold; }
        .modal-cobro select, .modal-cobro input { width: 100%; padding: 6px; box-sizing: border-box; border: 1px solid #ccc; border-radius: 4px; font-size: 12px; }
        .modal-cobro .btns { margin-top: 15px; display: flex; gap: 8px; }
        .modal-cobro .btns button { flex: 1; padding: 8px; border: none; border-radius: 4px; cursor: pointer; font-weight: bold; font-size: 12px; }
        .modal-cobro .btn-guardar { background: #28a745; color: white; }
        .modal-cobro .btn-cerrar { background: #6c757d; color: white; }
        .col-operacion-link { cursor: pointer; color: #007bff !important; }
        .col-operacion-link:hover { text-decoration: underline; }
        .celda-fecha-editable { cursor: pointer; }
        .celda-fecha-editable:hover { background: #e7f3ff !important; }
        .link-precio-azucar { color: #0080ff !important; cursor: pointer; text-decoration: none; }
        .link-precio-azucar:hover { text-decoration: underline; color: #0066cc !important; }
    </style>
</head>
<body>
    <?php if (defined('ENVIRONMENT') && ENVIRONMENT === 'dev'): ?>
    <nav class="deploy-navbar"><?php include 'controlDeploy.php'; ?></nav>
    <?php endif; ?>

    <div class="contenedor-principal">
    <div class="contenedor-usuarios">
        <?php if (isset($_GET['msg']) && ($_GET['msg'] === 'solo_lectura' || $_GET['msg'] === 'sin_permiso')): ?>
            <p style="font-size:10px; color:#856404; background:#fff3cd; padding:6px; border-radius:4px; margin:0 0 6px;">Su nivel solo permite consulta (sin altas, bajas ni modificaciones).</p>
        <?php endif; ?>
        <?php if (isset($_GET['mail_enviado']) && $_GET['mail_enviado'] == '1'): ?>
            <p id="mensajeMailEnviado" style="font-size:10px; color:#155724; background:#d4edda; padding:6px; border-radius:4px; margin:0 0 6px;">✓ Mail enviado correctamente a Herrera Hugo.</p>
        <?php endif; ?>
        <?php if (isset($_GET['mail_error'])): ?>
            <p id="mensajeMailError" style="font-size:10px; color:#721c24; background:#f8d7da; padding:6px; border-radius:4px; margin:0 0 6px;">✗ Falta dato o corregir.</p>
        <?php endif; ?>
        <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:4px; margin-bottom:4px;">
            <span style="font-size:9px; color:#666;"><?= htmlspecialchars($_SESSION['acceso_usuario'] ?? '') ?> (nivel <?= (int)($_SESSION['acceso_nivel'] ?? 0) ?>)</span>
            <div>
                <?php if (isset($_SESSION['acceso_nivel']) && $_SESSION['acceso_nivel'] >= 3): ?>
                    <a href="gestionar_accesos.php" style="color:#007bff; font-size:9px; margin-right:6px;">Accesos</a>
                    <a href="respaldar_bd.php" style="color:#28a745; font-size:9px; margin-right:6px;">Respaldar</a>
                    <a href="restaurar_bd.php" style="color:#721c24; font-size:9px; margin-right:6px;" title="Restaurar BD desde archivo .sql">Restaurar</a>
                <?php endif; ?>
                <a href="<?= (isset($_SESSION['acceso_nivel']) && $_SESSION['acceso_nivel'] >= 1 && $_SESSION['acceso_nivel'] <= 3) ? 'respaldar_al_salir.php' : 'logout.php' ?>" style="color:#dc3545; font-size:9px;">Salir</a>
            </div>
        </div>
        <div style="display:flex; justify-content:space-between; align-items:center;">
            <h2 style="font-size:11px; margin:0;">USUARIOS</h2>
            <?php if (!$soloLectura): ?>
                <a href="registro.php" style="background:#28a745; color:white; padding:2px 6px; border-radius:3px; font-size:9px; text-decoration:none; font-weight:bold;">+ NUEVO</a>
            <?php endif; ?>
        </div>
        <input type="text" id="txtBuscador" placeholder="🔍 BUSCAR (EJ: HERR HU)..." onkeyup="filtrar()" style="width:100%; font-size:10px; margin-top:5px; padding:5px; border:1px solid #ccc; border-radius:4px; box-sizing:border-box;">
        
        <div class="scroll-usuarios">
            <table class="tabla-usuarios">
                <tbody id="cuerpo">
                    <?php while ($f = mysqli_fetch_array($resultado)) {
                        $btnEdit = $soloLectura ? '' : "<button class='btn-edit-inline' onclick='event.stopPropagation(); abrirEditor({$f['id']})'>📝 EDITAR</button>";
                        $fechaFinAttr = '';
                        if (!empty($f['fecha_fin_contrato'])) {
                            $fechaFinAttr = " data-fecha-fin='".htmlspecialchars($f['fecha_fin_contrato'])."'";
                        }
                        echo "<tr data-id=\"{$f['id']}\" onclick='cargarMovimientos(this, {$f['id']})'$fechaFinAttr>
                                <td>
                                    <span class='nombre-txt'>".strtoupper($f['apellido'])."</span>
                                    $btnEdit
                                </td>
                              </tr>";
                    } ?>
                </tbody>
            </table>
        </div>
        
        <?php if (!$soloLectura): ?>
            <div style="display: flex; gap: 8px; margin-bottom: 8px;">
                <a href="gestionar_finca.php?modo=completo" class="btn-abm-prop btn-finca" style="flex: 1;">Finca</a>
                <a href="gestionar_azucares.php" class="btn-abm-prop" style="flex: 1;">Azucar</a>
                <a href="arriendos.php" class="btn-abm-prop btn-arriendos" style="flex: 1;">Arriendos</a>
                <a href="cosecha.php" class="btn-abm-prop btn-cosecha" style="flex: 1;">Cosecha</a>
            </div>
            <div style="display: flex; gap: 8px; margin-bottom: 8px;">
                <a href="propiedades.php" class="btn-abm-prop btn-admin-prop" style="flex: 1;">⚙️ Admin. Propiedades</a>
                <a href="contrato_alquiler.php" class="btn-abm-prop btn-contrato" style="flex: 1;">📜 Contrato de Alquiler</a>
            </div>
            <div style="display: flex; gap: 8px; margin-bottom: 8px; flex-wrap: wrap;">
                <a href="recibos_porteros.php" class="btn-abm-prop btn-recibos-porteros" style="flex: 0 1 auto; min-width: 90px;">🪪 Recibos Porteros</a>
                <a href="abm_indices.php" class="btn-abm-prop btn-indice" style="flex: 0 1 auto; min-width: 90px;">📈 ABM INDICE IPC</a>
                <button type="button" class="btn-abm-prop btn-tutoriales" style="flex: 0 1 auto; min-width: 90px; border: none; cursor: pointer;" onclick="abrirModalTutoriales()">🎬 Tutoriales</button>
            </div>
        <?php endif; ?>

    </div>

    <div class="contenedor-grid">
        <button id="btnWord" class="btn-word-recibo" onclick="generarWord()">📄 RECIBO WORD</button>
        
        <div class="cabecera-detalle">
            <div style="display:flex; align-items:center; gap:10px;">
                <h2 id="tituloMovimientos" style="font-size:1rem; color:#007bff; margin:0;">DETALLE DE CUENTA</h2>
                <?php if ($nivelAcceso === 3): ?>
                <button type="button" class="btn-ant-cel" onclick="abrirModalAntCel()">Ant/cel</button>
                <button type="button" class="btn-imprimir-estado" onclick="imprimirEstadoCuenta()">Imprimir estado de cuenta</button>
                <?php endif; ?>
            </div>
            <div style="display:flex; align-items:center; gap:12px; flex-wrap:wrap;">
                <div id="reloj-sistema"></div>
                <?php if ($nivelAcceso >= 3): ?>
                <span id="migrar-saldo-control" style="display:none; align-items:center; gap:8px; flex-wrap:wrap;">
                    <span style="font-size:11px;">Saldo actual: <strong id="migrar-saldo-actual" data-valor="0">--</strong></span>
                    <label style="font-size:11px;">Saldo objetivo:</label>
                    <input type="text" id="monto-migrar" class="input-monto" inputmode="decimal" placeholder="Ej: 0" style="width:100px; padding:6px 8px; font-size:11px; border:1px solid #ced4da; border-radius:4px;" oninput="actualizarDiferenciaMigrar()">
                    <span id="migrar-diferencia-wrap" style="font-size:11px; display:none;">INICIAL a crear: <strong id="migrar-diferencia"></strong></span>
                    <button type="button" onclick="migrarSaldo()" style="background:#28a745; color:white; border:none; padding:8px 12px; border-radius:4px; font-weight:bold; font-size:11px; cursor:pointer;">Migrar</button>
                </span>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="scroll-grid" id="divScroll">
            <table class="tabla-datos tabla-header">
                <colgroup id="colgroupHeader">
                    <col style="width:12%"><col style="width:36%"><col style="width:14%"><col style="width:10%"><col style="width:13%"><col style="width:13%"><col style="width:40px">
                </colgroup>
                <thead>
                    <tr>
                        <th class="al-cen">FECHA</th>
                        <th class="al-izq">CONCEPTO</th>
                        <th class="al-cen">COMPROB</th>
                        <th class="al-cen">REF</th>
                        <th class="al-der">MONTO</th>
                        <th class="al-der">SALDO</th>
                        <th class="al-cen">X</th>
                    </tr>
                </thead>
            </table>
            <div class="scroll-movimientos" id="scrollMovimientos">
                <div id="btnCargarAnteriores" style="display:none; padding:6px 12px; background:#e7f3ff; border-bottom:1px solid #007bff; text-align:center; cursor:pointer; font-size:11px; font-weight:bold; color:#007bff;" onclick="cargarMasAnteriores()">↑ Cargar más movimientos anteriores</div>
                <table class="tabla-datos tabla-body">
                <colgroup id="colgroupBody">
                    <col style="width:12%"><col style="width:36%"><col style="width:14%"><col style="width:10%"><col style="width:13%"><col style="width:13%"><col style="width:40px">
                </colgroup>
                    <tbody id="tablaMovimientos">
                        <tr><td colspan="7" style="padding:50px; text-align:center; color:gray;">SELECCIONE UN USUARIO</td></tr>
                    </tbody>
                </table>
            </div>
            <?php if (!$soloLectura): ?>
            <table class="tabla-datos tabla-carga">
                <colgroup>
                    <col style="width:12%"><col style="width:36%"><col style="width:14%"><col style="width:10%"><col style="width:13%"><col style="width:13%"><col style="width:40px">
                </colgroup>
                <tfoot id="filaCarga" style="display:none; background:#f8f9fa;">
                    <tr id="filaCheckCaja" style="display:none;">
                        <td colspan="4" style="padding: 4px 8px; font-size: 11px;">
                            <label style="white-space:nowrap; cursor:default;">
                                <input type="checkbox" id="ins_grabar_caja" disabled readonly tabindex="-1"> Grabar en Caja (efectivo)
                            </label>
                        </td>
                        <td colspan="3"></td>
                    </tr>
                    <tr>
                        <td class="al-cen" style="position:relative;">
                            <input type="text" id="ins_fecha" style="width:95%" placeholder="dd/mm/aaaa" maxlength="10" title="Formato: dd/mm/aaaa. Doble clic: calendario." onfocus="cursorAlInicioFecha()" ondblclick="abrirCalendarioFecha(event)">
                            <input type="date" id="ins_fecha_cal" style="display:none; position:absolute; left:0; top:0; width:95%; height:100%; margin:0; border:1px solid #007bff; box-sizing:border-box; font-size:inherit;">
                        </td>
                        <td><input type="text" id="ins_concepto" style="width:95%" onfocus="ponerFechaActual()" oninput="actualizarCheckGrabarCaja()"></td>
                        <td>
                            <select id="ins_compro" style="width:95%" onchange="actualizarCheckGrabarCaja(); avisarComprobanteCaja();">
                                <option value="ALQUILER EFVO">ALQUILER EFVO</option>
                                <option value="ALQUILER TRANSF">ALQUILER TRANSF</option>
                                <option value="PGO ARRIENDO">PGO ARRIENDO</option>
                                <option value="VARIOS">VARIOS</option>
                                <option value="DEBITO AUTOMATICO">DEBITO AUTOMATICO</option>
                                <option value="TARJETA">TARJETA</option>
                                <option value="DEBITO BANCO">DEBITO BANCO</option>
                                <option value="BOLETA">BOLETA</option>
                                <option value="TRANSFERENCIA">TRANSFERENCIA</option>
                                <option value="EFVO">EFVO</option>
                                <option value="CHEQUE/ECHEQ">CHEQUE/ECHEQ</option>
                                <option value="SUELDO/EXTRAS">SUELDO/EXTRAS</option>
                                <option value="MORA">MORA</option>
                            </select>
                        </td>
                        <td><input type="text" id="ins_refer" style="width:95%"></td>
                        <td><input type="text" id="ins_monto" class="input-monto" inputmode="decimal" placeholder="0" style="width:95%; text-align:right;"></td>
                        <td colspan="2"><button onclick="guardar()" style="background:#28a745; color:white; width:100%; border:none; padding:5px; font-weight:bold; cursor:pointer;">OK</button></td>
                    </tr>
                </tfoot>
            </table>
            <?php endif; ?>
        </div>

        <?php if (!$soloLectura): ?>
        <!-- Panel Cobro en caja (sin título visible) -->
        <div id="panelCobroCaja" style="display:none; margin-top:10px; margin-bottom:10px; padding:12px; background:#e8f5e9; border-radius:6px; border:1px solid #a5d6a7;">
            <div style="display:flex; flex-wrap:wrap; align-items:center; gap:12px; font-size:11px;">
                <div id="cobroCaja_saldoWrap" style="display:flex; align-items:center; gap:6px;">
                    <label style="font-weight:bold;">Saldo a la fecha:</label>
                    <span id="cobroCaja_saldo" style="font-weight:bold; min-width:90px;">$ 0,00</span>
                </div>
                <div style="display:flex; align-items:center; gap:6px;">
                    <label style="font-weight:bold;">Fecha</label>
                    <input type="text" id="cobroCaja_fecha" placeholder="dd/mm/aaaa" maxlength="10" style="width:95px; padding:6px 8px; border:1px solid #a5d6a7; border-radius:4px;" title="Formato: dd/mm/aaaa">
                </div>
                <div style="display:flex; flex-direction:column; align-items:flex-start; gap:2px;">
                    <div style="display:flex; align-items:center; gap:6px;">
                        <label style="font-weight:bold;">Dinero Ingresado</label>
                        <input type="text" id="cobroCaja_dinero" inputmode="decimal" placeholder="0" oninput="actualizarCobroCajaVuelto(); mostrarCartelExpensasPrimero(this)" style="width:100px; padding:6px 8px; border:1px solid #a5d6a7; border-radius:4px;">
                    </div>
                    <div id="cartelExpensasPrimero" style="display:none; font-size:10px; color:#856404; font-weight:bold;">Primero las expensas siempre</div>
                </div>
                <div id="cobroCaja_item1Wrap" style="display:none; flex:0 0 auto; align-items:center; gap:6px;">
                    <label style="font-weight:bold;">Item 1</label>
                    <span id="cobroCaja_item1" style="min-width:180px; padding:6px 8px; background:#fff; border:1px solid #ced4da; border-radius:4px; color:#666;"></span>
                </div>
                <div id="cobroCaja_item2Wrap" style="display:none; flex:0 0 auto; align-items:center; gap:6px;">
                    <label style="font-weight:bold;">Item 2</label>
                    <span id="cobroCaja_item2" style="min-width:180px; padding:6px 8px; background:#fff; border:1px solid #ced4da; border-radius:4px; color:#666;"></span>
                </div>
                <div id="cobroCaja_vueltoWrap" style="display:none; flex:0 0 auto; align-items:center; gap:6px;">
                    <label style="font-weight:bold;">Vuelto</label>
                    <input type="text" id="cobroCaja_vuelto" readonly placeholder="0" style="width:90px; padding:6px 8px; background:#f8f9fa; border:1px solid #ced4da; border-radius:4px; text-align:right;">
                </div>
                <label style="display:flex; align-items:center; gap:6px; cursor:default;">
                    <input type="checkbox" id="cobroCaja_dejarCuenta" style="cursor:pointer;" onchange="guardarDejarCuentaDefault(this.checked)">
                    <span>Dejar a cuenta próximo pago</span>
                </label>
                <div style="display:flex; gap:8px; margin-left:auto;">
                    <button type="button" onclick="aceptarCobroCaja()" style="background:#28a745; color:white; border:none; padding:6px 14px; border-radius:4px; font-weight:bold; cursor:pointer;">Aceptar</button>
                    <button type="button" onclick="cancelarCobroCaja()" style="background:#6c757d; color:white; border:none; padding:6px 14px; border-radius:4px; font-weight:bold; cursor:pointer;">Cancelar</button>
                </div>
            </div>
        </div>
        <div id="indicadorVueltoEntregar" style="display:none; margin-top:8px; padding:10px 14px; background:#fff3cd; border:1px solid #ffc107; border-radius:6px; font-weight:bold; color:#856404; font-size:13px;">⚠️ Entregar vuelto: $ <span id="indicadorVueltoMonto">0,00</span></div>
        <div style="display:flex; align-items:stretch; gap:10px; margin-top:15px; flex-wrap:wrap;">
            <button id="btnIngreso" class="btn-caja" style="background:#28a745;" onclick="preparar('INGRESO')">INGRESO</button>
            <button id="btnRetiro" class="btn-caja" style="background:#dc3545;" onclick="preparar('RETIRO')">RETIRO</button>
            <button type="button" id="btnResumenCtas" class="btn-caja btn-activo" style="background:#17a2b8; color:white; border:1px solid #138496; display:none;" onclick="abrirResumenCtas()">Resumen de Ctas.</button>
            <div id="resumenConsorcioLinea" style="display:none; align-self:stretch; flex:0 1 38%; max-width:420px; min-width:280px; font-size:10px; line-height:1.35; padding:8px 10px; background:#fff3cd; border-radius:4px; border:1px solid #ffeeba; color:#856404; box-sizing:border-box; flex-direction:column; justify-content:space-between;">
                <div style="flex-shrink:0; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; text-align:right;">LIQUIDACIÓN DE EXPENSAS anteriores Ordinarias: $<span id="resumenLiqOrdinarias">0,00</span></div>
                <div style="flex-shrink:0; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; text-align:right;">LIQUIDACIÓN DE EXPENSAS anteriores Extraordinarias: $<span id="resumenLiqExtraordinarias">0,00</span></div>
                <div style="flex-shrink:0; display:flex; justify-content:space-between; gap:8px; font-weight:bold;">
                    <span>Cobrado: $<span id="resumenCobradoMes">0,00</span></span>
                    <span>Gastos a la fecha: $<span id="resumenGastadoMes" style="color:#721c24;">0,00</span></span>
                </div>
            </div>
            <div id="panelBotonesExtra" style="display:none;">
                <div class="grid-botones-extra">
                    <button type="button" id="btnCobroExpTransf" class="btn-caja btn-extra" style="background:#28a745; color:white; display:none;" onclick="abrirModalCobroExp(false)">Cobro Exp/transferencia</button>
                    <button type="button" id="btnCobroExpEfvo" class="btn-caja btn-extra" style="background:#fff3cd; color:#856404; border:1px solid #ffeeba; display:none;" onclick="abrirModalCobroExp(true)">Cobro expensas en efectivo (grabar en Caja)</button>
                    <button type="button" id="btnSueldoExtras" class="btn-caja btn-extra" style="background:#D4A5A5; color:white;" onclick="cargarSueldoExtras()">Sueldo/Extras</button>
                </div>
            </div>
        </div>

        <div id="panelConsorcio" style="display:none; margin-top:8px; padding:8px; background:#f8f9fa; border-radius:4px; border:1px solid #dee2e6;">
            <div style="display:flex; flex-wrap:wrap; gap:4px;">
                <button type="button" class="btn-exp" onclick="cargarExpensa(this)" data-concepto="SUELDOS NETO SEGUN RECIBOS">1. SUELDOS NETO SEGÚN RECIBOS</button>
                <button type="button" class="btn-exp" onclick="cargarExpensa(this)" data-concepto="HORAS EXTRAS">2. HORAS EXTRAS</button>
                <button type="button" class="btn-exp" onclick="cargarExpensa(this)" data-concepto="A.F.I.P. F931 Total">3. A.F.I.P. F931 Total</button>
                <button type="button" class="btn-exp" onclick="cargarExpensa(this)" data-concepto="SUTERH">4. SUTERH</button>
                <button type="button" class="btn-exp" onclick="cargarExpensa(this)" data-concepto="FATERYH">5. FATERYH</button>
                <button type="button" class="btn-exp" onclick="cargarExpensa(this)" data-concepto="SALUD PUBLICA">6. SALUD PUBLICA</button>
                <button type="button" class="btn-exp btn-liquidar" onclick="abrirModalLiquidarExpensas()">LIQUIDAR EXPENSAS</button>
                <button type="button" class="btn-exp btn-imprimir-exp" onclick="abrirImprimirExpensas()" style="background:#D4A5A5; color:#333; border-color:#D4A5A5;">IMPRIMIR EXPENSAS</button>
                <?php if ($nivelAcceso === 3): ?>
                <button type="button" id="btnBorrarLiqExp" class="btn-exp" onclick="abrirModalBorrarLiqExp()" style="display:none; background:#dc3545; color:white; border-color:#dc3545;">BORRAR LIQ EXP</button>
                <?php endif; ?>
                <button type="button" class="btn-exp" onclick="cargarExpensa(this)" data-concepto="SEGURO CONTRA INCENDIO">7. SEGURO INCENDIO</button>
                <button type="button" class="btn-exp" onclick="cargarExpensa(this)" data-concepto="SAT (Agua y cloacas común)">8. SAT (Agua)</button>
                <button type="button" class="btn-exp" onclick="cargarExpensa(this)" data-concepto="MANTENIMIENTO ASCENSOR">9. MANTEN. ASCENSOR</button>
                <button type="button" class="btn-exp" onclick="cargarExpensa(this)" data-concepto="EDET (Electricidad común)">10. EDET (Electricidad)</button>
                <button type="button" class="btn-exp" onclick="cargarExpensa(this)" data-concepto="PROP AGUINALDO S/CARG SOC 8,33%">11. PROP. AGUINALDO 8,33%</button>
                <button type="button" class="btn-exp" onclick="cargarExpensaExtraordinaria()">EXTRAORDINARIA</button>
            </div>
        </div>
        <?php endif; ?>
    </div>

    </div><!-- .contenedor-principal -->

    <div id="modalCobroExp" class="modal-overlay" onclick="if(event.target===this) cerrarModalCobroExp()">
        <div class="modal-cobro" onclick="event.stopPropagation()">
            <h3 id="modalCobroExpTitulo">Cobro Exp/transferencia</h3>
            <p id="cobroUsuarioNombre" style="margin:0 0 12px; font-weight:bold; color:#333; font-size:13px;"></p>
            <label>Propiedad</label>
            <select id="cobroPropiedad" required onchange="mostrarOcultarReciboCobro()">
                <option value="">-- Cargando... --</option>
            </select>
            <div id="cobroReciboWrap" style="display:none;">
                <label>Recibo N° <span style="color:#dc3545;">*</span></label>
                <input type="text" id="cobroRecibo" placeholder="Ej: 123" maxlength="20" required>
            </div>
            <label>Período (MM/AAAA)</label>
            <input type="text" id="cobroPeriodo" placeholder="Ej: 01/2025" maxlength="7">
            <label>Monto</label>
            <input type="text" id="cobroMonto" placeholder="Ej: 15000" inputmode="decimal">
            <div class="btns">
                <button type="button" class="btn-guardar" onclick="guardarCobroExp()">Guardar</button>
                <button type="button" class="btn-cerrar" onclick="cerrarModalCobroExp()">Cerrar</button>
            </div>
        </div>
    </div>

    <div id="modalLiqExp" class="modal-overlay" onclick="if(event.target===this) cerrarModalLiqExp()">
        <div class="modal-cobro" onclick="event.stopPropagation()" style="max-width: 95%; max-height: 90vh; overflow: auto;">
            <h3>Liquidar expensas</h3>
            <p id="liqExpConsorcioNombre" style="margin:0 0 12px; font-weight:bold; color:#333; font-size:13px;"></p>
            <label>Mes a liquidar (MM/AAAA)</label>
            <input type="text" id="liqExpMes" placeholder="Ej: 01/2025" maxlength="7">
            <p style="font-size:10px; color:#666; margin:8px 0 0;">Suma retiros/gastos desde la última LIQ EXP hasta fin de mes y reparte por % a propietarios.</p>
            <div id="liqExpDetalle" style="display:none; margin-top:16px; padding:12px; background:#f8f9fa; border-radius:6px; border:1px solid #dee2e6;">
                <h4 style="margin:0 0 10px; font-size:12px; color:#333;">Valores tomados para el cálculo</h4>
                <p style="margin:0 0 8px; font-size:11px;"><strong>Total expensas:</strong> <span id="liqExpTotal"></span></p>
                <p style="margin:0 0 8px; font-size:11px;"><strong>Ordinarias:</strong> <span id="liqExpOrdinarias"></span> — <strong>Extraordinarias:</strong> <span id="liqExpExtraordinarias"></span></p>
                <div style="max-height:200px; overflow-y:auto; font-size:10px; margin-top:8px;">
                    <table style="width:100%; border-collapse:collapse;">
                        <thead><tr style="background:#e9ecef;"><th style="padding:4px; text-align:left;">Fecha</th><th style="padding:4px; text-align:left;">Concepto</th><th style="padding:4px; text-align:left;">Comprobante</th><th style="padding:4px; text-align:right;">Monto</th></tr></thead>
                        <tbody id="liqExpTablaMov"></tbody>
                    </table>
                </div>
            </div>
            <div class="btns" style="margin-top:12px;">
                <button type="button" class="btn-guardar" onclick="ejecutarLiquidarExpensas()">Liquidar</button>
                <button type="button" class="btn-cerrar" onclick="cerrarModalLiqExp()">Cerrar</button>
            </div>
        </div>
    </div>

    <div id="modalImprimirExpensas" class="modal-overlay" onclick="if(event.target===this) cerrarModalImprimirExpensas()">
        <div class="modal-cobro" onclick="event.stopPropagation()">
            <h3>Imprimir expensas</h3>
            <p style="font-size:11px; color:#666; margin:0 0 12px;">Elegí el mes y año de la liquidación que querés imprimir (debe existir un movimiento LIQ EXPENSAS con esa referencia).</p>
            <div style="display:flex; flex-wrap:wrap; gap:12px; align-items:center; margin-bottom:12px;">
                <label style="display:flex; align-items:center; gap:8px;">Mes
                    <select id="impExpMes" style="padding:8px; min-width:120px;">
                        <?php for ($m = 1; $m <= 12; $m++): ?>
                        <option value="<?= $m ?>"<?= (int)date('n') === $m ? ' selected' : '' ?>><?= str_pad((string)$m, 2, '0', STR_PAD_LEFT) ?></option>
                        <?php endfor; ?>
                    </select>
                </label>
                <label style="display:flex; align-items:center; gap:8px;">Año
                    <select id="impExpAnio" style="padding:8px; min-width:100px;">
                        <?php
                        $yMax = (int)date('Y');
                        for ($y = $yMax; $y >= $yMax - 15; $y--):
                        ?>
                        <option value="<?= $y ?>"<?= $y === $yMax ? ' selected' : '' ?>><?= $y ?></option>
                        <?php endfor; ?>
                    </select>
                </label>
            </div>
            <div class="btns">
                <button type="button" class="btn-guardar" onclick="confirmarImprimirExpensas()">Imprimir</button>
                <button type="button" class="btn-cerrar" onclick="cerrarModalImprimirExpensas()">Cerrar</button>
            </div>
        </div>
    </div>

    <?php if ($nivelAcceso === 3): ?>
    <div id="modalBorrarTodasLiqExp" class="modal-overlay" onclick="if(event.target===this) cerrarModalBorrarTodasLiqExp()">
        <div class="modal-cobro" onclick="event.stopPropagation()">
            <h3>Borrar TODAS las liquidaciones de expensas</h3>
            <p style="font-size:11px; color:#666; margin:0 0 12px;">Elimina TODOS los movimientos de liquidación (LIQ EXPENSAS, Honorarios, LIQ EXP, LIQ EXP EXT) del consorcio en cuenta del consorcio y en cuentas de propietarios e inquilinos. Sin filtro de período. <strong>Solo nivel 3.</strong></p>
            <label>Consorcio</label>
            <select id="borrarTodasLiqExpConsorcio" style="width:100%; padding:8px; margin-bottom:12px;">
                <option value="">-- Seleccionar consorcio --</option>
                <?php foreach ($consorcios_lista as $c): ?>
                <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['apellido'] . ($c['consorcio'] ? ' (' . $c['consorcio'] . ')' : '')) ?></option>
                <?php endforeach; ?>
            </select>
            <div class="btns">
                <button type="button" class="btn-guardar" onclick="ejecutarBorrarTodasLiqExp()" style="background:#721c24;">Borrar TODAS</button>
                <button type="button" class="btn-cerrar" onclick="cerrarModalBorrarTodasLiqExp()">Cerrar</button>
            </div>
        </div>
    </div>
    <div id="modalBorrarLiqExp" class="modal-overlay" onclick="if(event.target===this) cerrarModalBorrarLiqExp()">
        <div class="modal-cobro" onclick="event.stopPropagation()">
            <h3>Borrar liquidación de expensas (por período)</h3>
            <p style="font-size:11px; color:#666; margin:0 0 12px;">Elimina en la cuenta del consorcio: LIQ EXPENSAS y Honorarios (ref. mes siguiente al período), y en propietarios/inquilinos: LIQ EXP y LIQ EXP EXT. Los cobros de expensas (EXP/EFVO, etc.) no se borran. <strong>Solo nivel 3.</strong></p>
            <div id="borrarLiqExpPaso1">
                <label>Consorcio</label>
                <p id="borrarLiqExpConsorcioFijoMsg" style="display:none; font-size:10px; color:#555; margin:0 0 6px;">Se usa el consorcio de la cuenta que está viendo (no se puede cambiar desde acá).</p>
                <select id="borrarLiqExpConsorcio" style="width:100%; padding:8px; margin-bottom:12px;">
                    <option value="">-- Seleccionar consorcio --</option>
                    <?php foreach ($consorcios_lista as $c): ?>
                    <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['apellido'] . ($c['consorcio'] ? ' (' . $c['consorcio'] . ')' : '')) ?></option>
                    <?php endforeach; ?>
                </select>
                <p style="font-size:11px; margin:0 0 8px;">Período a borrar (mes y año de la referencia)</p>
                <div style="display:flex; flex-wrap:wrap; gap:12px; align-items:center; margin-bottom:12px;">
                    <label style="display:flex; align-items:center; gap:8px;">Mes
                        <select id="borrarLiqExpMes" style="padding:8px; min-width:120px;">
                            <?php for ($m = 1; $m <= 12; $m++): ?>
                            <option value="<?= $m ?>"<?= (int)date('n') === $m ? ' selected' : '' ?>><?= str_pad((string)$m, 2, '0', STR_PAD_LEFT) ?></option>
                            <?php endfor; ?>
                        </select>
                    </label>
                    <label style="display:flex; align-items:center; gap:8px;">Año
                        <select id="borrarLiqExpAnio" style="padding:8px; min-width:100px;">
                            <?php
                            $yMaxB = (int)date('Y');
                            for ($y = $yMaxB; $y >= $yMaxB - 15; $y--):
                            ?>
                            <option value="<?= $y ?>"<?= $y === $yMaxB ? ' selected' : '' ?>><?= $y ?></option>
                            <?php endfor; ?>
                        </select>
                    </label>
                </div>
                <div class="btns">
                    <button type="button" class="btn-guardar" onclick="pasoSiguienteBorrarLiqExp()">Siguiente</button>
                    <button type="button" class="btn-cerrar" onclick="cerrarModalBorrarLiqExp()">Cerrar</button>
                </div>
            </div>
            <div id="borrarLiqExpPaso2" style="display:none;">
                <div id="borrarLiqExpConfirmText" style="font-size:12px; line-height:1.5; margin-bottom:16px; padding:12px; background:#fff3cd; border:1px solid #ffc107; border-radius:6px; color:#333;"></div>
                <p style="font-size:11px; color:#721c24; margin:0 0 12px;">¿Confirma eliminar esta liquidación? No se puede deshacer.</p>
                <div class="btns">
                    <button type="button" class="btn-guardar" onclick="ejecutarBorrarLiqExp()" style="background:#dc3545;">Sí, eliminar</button>
                    <button type="button" class="btn-cerrar" onclick="volverPasoBorrarLiqExp()">Volver</button>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <div id="modalPrecioAzucar" class="modal-overlay" onclick="if(event.target===this) cerrarModalPrecioAzucar()">
        <div class="modal-cobro" onclick="event.stopPropagation()">
            <h3>Precio bolsa azúcar</h3>
            <p style="font-size:11px; color:#666; margin:0 0 12px;">Precio de bolsa azúcar incluido IVA, precio final.</p>
            <label>Precio de bolsa azúcar incluido IVA, precio final</label>
            <input type="text" id="precioAzucarBolsa" placeholder="Ej: 100" inputmode="decimal">
            <div class="btns">
                <button type="button" class="btn-guardar" onclick="guardarPrecioAzucar()">Guardar</button>
                <button type="button" class="btn-cerrar" onclick="cerrarModalPrecioAzucar()">Cerrar</button>
            </div>
        </div>
    </div>

    <?php if ($nivelAcceso === 3): ?>
    <script>
    var usuariosAnticipoLista = <?= json_encode(array_map(function($u) { return ['id' => (int)$u['id'], 'apellido' => $u['apellido']]; }, $usuarios_anticipo)) ?>;
    </script>
    <div id="modalAntCel" class="modal-ant-cel-overlay" onclick="if(event.target===this) cerrarModalAntCel()">
        <div class="modal-ant-cel" onclick="event.stopPropagation()">
            <div class="ant-cabecera">
                <h3>Anticipo (Ant/cel)</h3>
                <p>Retiro como anticipo. Sin movimiento de caja. Comprobante y referencia: ANTICIPO.</p>
            </div>
            <div class="ant-cuerpo">
                <div class="ant-seccion">
                    <div class="ant-seccion-titulo">1. Búsqueda de usuario</div>
                    <div class="form-g">
                        <div id="buscadorAntWrap" class="buscador-ant-wrap">
                            <input type="text" id="ant_buscador" placeholder="Buscar usuario..." autocomplete="off">
                            <span class="ico-lupa" aria-hidden="true">🔍</span>
                            <input type="hidden" id="ant_usuario" value="">
                            <div id="ant_resultados" class="buscador-ant-resultados"></div>
                        </div>
                    </div>
                </div>
                <div class="ant-seccion">
                    <div class="ant-seccion-titulo">2. Fecha y monto</div>
                    <div class="ant-fila-fecha-monto">
                        <div class="form-g fecha-ant-wrap">
                            <label>Fecha</label>
                            <input type="text" id="ant_fecha" placeholder="dd/mm/aaaa" maxlength="10" value="<?= date('d/m/Y') ?>" title="Doble clic: calendario." onfocus="cursorInicioAntFecha()" ondblclick="abrirCalendarioAntFecha(event)">
                            <input type="date" id="ant_fecha_cal">
                        </div>
                        <div class="form-g">
                            <label>Monto (retiro)</label>
                            <input type="number" id="ant_monto" step="0.01" min="0.01" placeholder="Ej: 50000" required>
                        </div>
                    </div>
                </div>
                <div class="ant-seccion">
                    <div class="ant-seccion-titulo">3. Concepto</div>
                    <div class="form-g">
                        <label>Concepto</label>
                        <input type="text" id="ant_concepto" value="Anticipo">
                    </div>
                    <div id="ant_msg" style="font-size:11px; margin-bottom:8px; display:none;"></div>
                </div>
            </div>
            <div class="ant-botones">
                <button type="button" class="btn-guardar-ant" onclick="guardarAnticipo()">Guardar</button>
                <button type="button" class="btn-cerrar" onclick="cerrarModalAntCel()">Cancelar</button>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Modal Movimientos de pago - Operación (como en venta de azúcar) -->
    <div id="modalMovimientosOperacion" class="modal-overlay" onclick="if(event.target===this) cerrarModalMovimientosOperacion()">
        <div class="modal-cobro" onclick="event.stopPropagation()" style="max-width:90%; max-height:90vh; overflow:auto; width:auto;">
            <h3 id="modalMovimientosOperacionTitulo">Movimientos de pago - Operación N° <span id="modalOpNumero"></span></h3>
            <div id="wrapImpresionMovOp" style="margin-bottom:15px;">
                <table style="width:100%; border-collapse:collapse; font-size:11px;">
                    <thead>
                        <tr style="background:#007bff; color:white;">
                            <th class="al-cen" style="padding:6px; border:1px solid #0056b3;">Fecha</th>
                            <th class="al-izq" style="padding:6px; border:1px solid #0056b3;">Concepto</th>
                            <th class="al-cen" style="padding:6px; border:1px solid #0056b3;">Comprobante</th>
                            <th class="al-cen" style="padding:6px; border:1px solid #0056b3;">Referencia</th>
                            <th class="al-izq" style="padding:6px; border:1px solid #0056b3;">Usuario</th>
                            <th class="al-der" style="padding:6px; border:1px solid #0056b3;">Monto</th>
                            <th class="al-der" style="padding:6px; border:1px solid #0056b3;">Saldo</th>
                        </tr>
                    </thead>
                    <tbody id="tablaMovimientosOperacion">
                        <tr><td colspan="7" style="text-align:center; padding:30px; color:gray;">Cargando...</td></tr>
                    </tbody>
                </table>
            </div>
            <div class="btns">
                <button type="button" class="btn-guardar" onclick="azucarImprimirWrapIndex('wrapImpresionMovOp', azucarTituloModalMovOpIndex())" title="Imprimir movimientos">🖨️ Imprimir</button>
                <button type="button" class="btn-guardar" onclick="azucarWhatsappWrapIndex('wrapImpresionMovOp', azucarTituloModalMovOpIndex())" title="Enviar por WhatsApp">WhatsApp</button>
                <button type="button" class="btn-cerrar" onclick="cerrarModalMovimientosOperacion()">Cerrar</button>
            </div>
        </div>
    </div>

    <!-- Modal Tutoriales -->
    <div id="modalTutoriales" class="modal-overlay" onclick="if(event.target===this) cerrarModalTutoriales()">
        <div class="modal-cobro" onclick="event.stopPropagation()" style="max-width: 900px; width: 95%; max-height: 90vh; overflow: auto; display: flex; flex-direction: column;">
            <div id="tutorialHeaderWrap">
                <h3 style="margin: 0 0 15px 0; color: #007bff;">Tutoriales</h3>
                <p style="margin: 0 0 10px 0;"><a href="#" onclick="cerrarModalTutoriales(); return false;" style="text-decoration: underline; color: #007bff;">← Volver al principal</a></p>
                <p style="font-size: 11px; color: #666; margin-bottom: 12px;">Presione ESC para cerrar</p>
            </div>
            <div id="tutorialVideoWrap" style="display: none; margin-bottom: 15px; background: #000; border-radius: 4px; overflow: hidden; min-height: 360px;">
                <video id="tutorialVideo" controls playsinline style="width: 100%; min-height: 360px; max-height: 85vh; object-fit: contain; display: block;" preload="auto"></video>
                <p id="tutorialVideoAviso" style="display: none; margin: 8px; font-size: 11px; color: #ffc107;">Si escuchás audio pero no ves imagen, el video usa un codec no soportado por el navegador (ej. HEVC). Convertilo a H.264 con <a href="https://handbrake.fr/" target="_blank" rel="noopener">HandBrake</a> o VLC (Medio → Convertir/Guardar).</p>
                <p style="margin: 8px; font-size: 11px;"><a href="#" id="tutorialAbrirEnlace" target="_blank" rel="noopener" style="color: #17a2b8;">Abrir video en nueva pestaña</a> &nbsp;|&nbsp; <a href="#" onclick="volverListaTutoriales(); return false;" style="color: #17a2b8;">← Volver a la lista</a> &nbsp;|&nbsp; <a href="#" onclick="cerrarModalTutoriales(); return false;" style="color: #17a2b8;">Cerrar</a></p>
            </div>
            <div id="tutorialListaWrap" style="display: flex; flex-direction: column; gap: 8px;">
                <?php for ($i = 1; $i <= 10; $i++): $url = $tutorial_urls[$i] ?? ''; $tit = $tutorial_titulos[$i] ?? '(Próximamente)'; $estilo = 'display: block; padding: 10px 12px; background: #f8f9fa; border: 1px solid #dee2e6; border-radius: 4px; text-decoration: none; color: #333; font-weight: 500;'; ?>
                <a href="#" class="link-tutorial" data-video="<?= htmlspecialchars($url) ?>" onclick="return reproducirTutorial(this);" style="<?= $estilo ?>"><?= $i ?>. <?= htmlspecialchars($tit) ?></a>
                <?php endfor; ?>
            </div>
            <div id="tutorialBtnsWrap" class="btns" style="margin-top: 15px;">
                <button type="button" class="btn-cerrar" onclick="cerrarModalTutoriales()">Cerrar</button>
            </div>
        </div>
    </div>

<script>
let uSel = null; 
let tipo = ''; 
let movSel = null;
let esConsorcioUsuario = false;
let esPropietarioOInquilino = false;
let esArrendadorUsuario = false;
let movScrollData = null;
let saldoActualCuenta = 0;
/** Solo usuarios con acceso_nivel === 3 (borrar liquidaciones, etc.) */
var accesoNivel3 = <?= $nivelAcceso === 3 ? 'true' : 'false' ?>;

// Parsear monto: acepta 24.44 o 24,44 (teclado numérico). Evita que 24.44 se interprete como 2.444.
function parseMonto(str) {
    str = String(str || '').trim().replace(/\s/g, '');
    if (!str) return NaN;
    var lastComma = str.lastIndexOf(',');
    var lastPeriod = str.lastIndexOf('.');
    if (lastComma > lastPeriod) {
        str = str.replace(/\./g, '').replace(',', '.');
    } else if (lastPeriod > lastComma) {
        str = str.replace(/,/g, '');
        if (lastComma < 0 && (str.match(/\./g) || []).length > 1) {
            str = str.replace(/\./g, '');
        } else if (lastComma < 0 && lastPeriod >= 0) {
            var after = str.substring(lastPeriod + 1);
            if (after.length === 3 && /^\d+$/.test(after)) str = str.replace(/\./g, '');
        }
    } else if (lastComma >= 0) {
        var numCommas = (str.match(/,/g) || []).length;
        if (numCommas > 1 || (str.substring(lastComma + 1).length !== 2)) {
            str = str.replace(/,/g, '');
        } else {
            str = str.replace(',', '.');
        }
    }
    return parseFloat(str);
}

// RELOJ ACTUALIZADO
function actualizarReloj() {
    const ahora = new Date();
    const dia = String(ahora.getDate()).padStart(2, '0');
    const mes = String(ahora.getMonth() + 1).padStart(2, '0');
    const anio = ahora.getFullYear();
    const hora = String(ahora.getHours()).padStart(2, '0');
    const mins = String(ahora.getMinutes()).padStart(2, '0');
    document.getElementById('reloj-sistema').innerText = `${dia}/${mes}/${anio} ${hora}:${mins}`;
}
setInterval(actualizarReloj, 1000);
actualizarReloj();

// --- FUNCIÓN FILTRAR ACTUALIZADA (PUNTO DE DISEÑO 2) ---
function filtrar() {
    let busqueda = document.getElementById("txtBuscador").value.toUpperCase().trim();
    
    // Dividimos la búsqueda en palabras sueltas
    let terminos = busqueda.split(/\s+/); 

    document.querySelectorAll("#cuerpo tr").forEach(fila => {
        let textoFila = fila.innerText.toUpperCase();
        
        // Verificamos que TODAS las palabras escritas estén en la fila
        let coincide = terminos.every(termino => textoFila.includes(termino));

        fila.style.display = coincide ? "" : "none";
    });
}

function cargarMovimientos(fila, id) {
    uSel = id;
    document.querySelectorAll("#cuerpo tr").forEach(r => r.classList.remove("fila-seleccionada"));
    fila.classList.add("fila-seleccionada");
    
    var btnIng = document.getElementById("btnIngreso");
    var btnRet = document.getElementById("btnRetiro");
    if (btnIng) btnIng.classList.add("btn-activo");
    if (btnRet) btnRet.classList.add("btn-activo");
    document.getElementById("btnWord").style.display = "none";
    
    // Construir título con fecha de fin de contrato si existe
    var nombreUsuario = fila.querySelector('.nombre-txt').innerText;
    var fechaFinContrato = fila.getAttribute('data-fecha-fin');
    var tituloTexto = "MOVIMIENTOS DE: " + nombreUsuario;
    if (fechaFinContrato) {
        // Formatear fecha de YYYY-MM-DD a dd/mm/yyyy
        var fechaParts = fechaFinContrato.split('-');
        if (fechaParts.length === 3) {
            var fechaFormateada = fechaParts[2] + '/' + fechaParts[1] + '/' + fechaParts[0];
            tituloTexto += " (Fin contrato: " + fechaFormateada + ")";
        }
    }
    document.getElementById("tituloMovimientos").innerText = tituloTexto;

    var nomUsuario = fila.querySelector('.nombre-txt').innerText.toUpperCase().trim();
    esConsorcioUsuario = nomUsuario.indexOf("CONSORCIO") === 0;
    var esCajaUsuario = (id === 1); // Caja tiene ID 1
    var panelCons = document.getElementById("panelConsorcio");
    var panelExtra = document.getElementById("panelBotonesExtra");
    var resumenLinea = document.getElementById("resumenConsorcioLinea");
    var btnResumenCtas = document.getElementById("btnResumenCtas");
    var btnCobroExpTransf = document.getElementById("btnCobroExpTransf");
    var btnCobroExpEfvo = document.getElementById("btnCobroExpEfvo");
    var btnSueldoExtras = document.getElementById("btnSueldoExtras");
    
    if (panelCons) panelCons.style.display = esConsorcioUsuario ? "block" : "none";
    if (panelExtra) panelExtra.style.display = esConsorcioUsuario ? "none" : "grid";
    var panelCobroCaja = document.getElementById("panelCobroCaja");
    if (panelCobroCaja) {
        panelCobroCaja.style.display = "none";
        resetCobroCaja();
    }
    if (resumenLinea) resumenLinea.style.display = esConsorcioUsuario ? "flex" : "none";
    if (btnResumenCtas) btnResumenCtas.style.display = esCajaUsuario ? "block" : "none";
    // Mostrar botón Word si es consorcio (aunque no haya movimiento seleccionado)
    if (esConsorcioUsuario) {
        document.getElementById("btnWord").style.display = "block";
    }
    var btnBorrarLiq = document.getElementById("btnBorrarLiqExp");
    if (btnBorrarLiq) btnBorrarLiq.style.display = (esConsorcioUsuario && accesoNivel3) ? "inline-block" : "none";
    
    // Cobro Exp/transferencia y Cobro expensas efvo: solo si es propietario o inquilino. Sueldo/Extras: solo si NO es propietario ni inquilino (y no Caja).
    esPropietarioOInquilino = false;
    if (esCajaUsuario) {
        if (btnCobroExpTransf) btnCobroExpTransf.style.display = "none";
        if (btnCobroExpEfvo) btnCobroExpEfvo.style.display = "none";
        if (btnSueldoExtras) btnSueldoExtras.style.display = "none";
    } else {
        fetch('obtener_propiedades_propietario.php?id=' + id)
            .then(function(r) { return r.json(); })
            .then(function(props) {
                var esPropOInq = props && props.length > 0;
                esPropietarioOInquilino = esPropOInq;
                var esInquilino = props && props.some(function(p) { return (p.rol || "").toUpperCase() === "INQUILINO"; });
                if (btnCobroExpTransf) btnCobroExpTransf.style.display = esPropOInq ? "block" : "none";
                if (btnCobroExpEfvo) btnCobroExpEfvo.style.display = esPropOInq ? "block" : "none";
                if (btnSueldoExtras) btnSueldoExtras.style.display = esPropOInq ? "none" : "block";
                if (panelCobroCaja) {
                    panelCobroCaja.style.display = esInquilino ? "block" : "none";
                    if (esInquilino) resetCobroCaja();
                }
            })
            .catch(function() {
                if (btnCobroExpTransf) btnCobroExpTransf.style.display = "none";
                if (btnCobroExpEfvo) btnCobroExpEfvo.style.display = "none";
                if (btnSueldoExtras) btnSueldoExtras.style.display = "block";
                if (panelCobroCaja) panelCobroCaja.style.display = "none";
            });
    }
    if (!esConsorcioUsuario) {
        if (resumenLinea) {
            document.getElementById("resumenLiqOrdinarias").textContent = "0,00";
            document.getElementById("resumenLiqExtraordinarias").textContent = "0,00";
            document.getElementById("resumenCobradoMes").textContent = "0,00";
            document.getElementById("resumenGastadoMes").textContent = "0,00";
        }
    }

    movScrollData = { first_fecha: '', first_id: 0, last_fecha: '', last_id: 0, has_more_older: false, has_more_newer: false, loading: false };
    var urlMov = 'obtener_movimientos.php?id=' + id;
    fetch(urlMov)
        .then(r => r.json())
        .then(function(data) {
            document.getElementById("tablaMovimientos").innerHTML = data.html;
            esArrendadorUsuario = !!data.es_arrendador;
            movScrollData.first_fecha = data.first_fecha || '';
            movScrollData.first_id = data.first_id || 0;
            movScrollData.last_fecha = data.last_fecha || '';
            movScrollData.last_id = data.last_id || 0;
            movScrollData.has_more_older = !!data.has_more_older;
            movScrollData.has_more_newer = !!data.has_more_newer;
            actualizarBtnCargarAnteriores();
            var saldo = parseFloat(data.saldo_actual);
            saldoActualCuenta = isNaN(saldo) ? 0 : saldo;
            var saldoEl = document.getElementById("migrar-saldo-actual");
            if (saldoEl) {
                saldoEl.setAttribute('data-valor', isNaN(saldo) ? '0' : saldo);
                saldoEl.textContent = (isNaN(saldo) ? '--' : '$ ' + saldo.toLocaleString('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }));
            }
            actualizarSaldoCobroPanel();
            actualizarDiferenciaMigrar();
            // Mostrar siempre el último movimiento cronológicamente (el más reciente) tras cualquier acción (borrar, grabar, etc.)
            var scrollEl = document.getElementById("scrollMovimientos");
            var tbody = document.getElementById("tablaMovimientos");
            if (scrollEl && tbody) {
                function scrollAlUltimo() {
                    var ultimaFila = tbody.querySelector("tr.fila-mov:last-child");
                    if (ultimaFila) {
                        ultimaFila.scrollIntoView({ behavior: 'auto', block: 'end' });
                    }
                    scrollEl.scrollTop = scrollEl.scrollHeight;
                }
                requestAnimationFrame(function() {
                    scrollAlUltimo();
                    setTimeout(function() { scrollAlUltimo(); onScrollMovimientos(); }, 150);
                });
            }
            if (esConsorcioUsuario) {
                fetch('obtener_resumen_consorcio.php?id=' + id)
                    .then(r => r.json())
                    .then(function(res) {
                        var fmt = function(n) { return (n != null && !isNaN(n)) ? Number(n).toLocaleString('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) : '0,00'; };
                        document.getElementById("resumenLiqOrdinarias").textContent = fmt(res.liq_ordinarias);
                        document.getElementById("resumenLiqExtraordinarias").textContent = fmt(res.liq_extraordinarias);
                        document.getElementById("resumenCobradoMes").textContent = fmt(res.cobrado_mes);
                        document.getElementById("resumenGastadoMes").textContent = fmt(res.gastado_mes);
                    })
                    .catch(function() {
                        document.getElementById("resumenLiqOrdinarias").textContent = '0,00';
                        document.getElementById("resumenLiqExtraordinarias").textContent = '0,00';
                        document.getElementById("resumenCobradoMes").textContent = '0,00';
                        document.getElementById("resumenGastadoMes").textContent = '0,00';
                    });
            }
        })
        .catch(function() {
            document.getElementById("tablaMovimientos").innerHTML = "<tr><td colspan='7' style='text-align:center; padding:30px; color:red;'>Error al cargar movimientos</td></tr>";
            movScrollData = null;
            esArrendadorUsuario = false;
            saldoActualCuenta = 0;
            actualizarSaldoCobroPanel();
            actualizarBtnCargarAnteriores();
        });
}

function actualizarBtnCargarAnteriores() {
    var btn = document.getElementById("btnCargarAnteriores");
    if (btn) btn.style.display = (movScrollData && movScrollData.has_more_older && !movScrollData.loading) ? "block" : "none";
}
function cargarMasAnteriores() {
    if (!movScrollData || movScrollData.loading || !uSel || !movScrollData.has_more_older) return;
    var div = document.getElementById("scrollMovimientos");
    if (!div) return;
    movScrollData.loading = true;
    actualizarBtnCargarAnteriores();
    var url = 'obtener_movimientos.php?id=' + uSel + '&before_fecha=' + encodeURIComponent(movScrollData.first_fecha) + '&before_id=' + movScrollData.first_id;
    fetch(url).then(r => r.json()).then(function(data) {
        if (data.html && data.html.indexOf('NO HAY MOVIMIENTOS') < 0 && data.html.indexOf('fila-mov') >= 0) {
            var tbody = document.getElementById("tablaMovimientos");
            var oldHeight = div.scrollHeight;
            tbody.innerHTML = data.html + tbody.innerHTML;
            movScrollData.first_fecha = data.first_fecha || movScrollData.first_fecha;
            movScrollData.first_id = data.first_id || movScrollData.first_id;
            movScrollData.has_more_older = !!data.has_more_older;
            var saldo = parseFloat(data.saldo_actual);
            if (!isNaN(saldo)) { saldoActualCuenta = saldo; actualizarSaldoCobroPanel(); }
            div.scrollTop = div.scrollHeight - oldHeight;
        } else {
            movScrollData.has_more_older = false;
        }
        movScrollData.loading = false;
        actualizarBtnCargarAnteriores();
    }).catch(function() { movScrollData.loading = false; actualizarBtnCargarAnteriores(); });
}
function onScrollMovimientos() {
    if (!movScrollData || movScrollData.loading || !uSel) return;
    var div = document.getElementById("scrollMovimientos");
    if (!div) return;
    actualizarBtnCargarAnteriores();
    var margin = 80;
    if (movScrollData.has_more_older && div.scrollTop <= margin) {
        movScrollData.loading = true;
        var url = 'obtener_movimientos.php?id=' + uSel + '&before_fecha=' + encodeURIComponent(movScrollData.first_fecha) + '&before_id=' + movScrollData.first_id;
        fetch(url).then(r => r.json()).then(function(data) {
            if (data.html && data.html.indexOf('NO HAY MOVIMIENTOS') < 0 && data.html.indexOf('fila-mov') >= 0) {
                var tbody = document.getElementById("tablaMovimientos");
                var oldHeight = div.scrollHeight;
                tbody.innerHTML = data.html + tbody.innerHTML;
                movScrollData.first_fecha = data.first_fecha || movScrollData.first_fecha;
                movScrollData.first_id = data.first_id || movScrollData.first_id;
                movScrollData.has_more_older = !!data.has_more_older;
                var saldo = parseFloat(data.saldo_actual);
                if (!isNaN(saldo)) { saldoActualCuenta = saldo; actualizarSaldoCobroPanel(); }
                div.scrollTop = div.scrollHeight - oldHeight;
            } else {
                movScrollData.has_more_older = false;
            }
            movScrollData.loading = false;
            actualizarBtnCargarAnteriores();
        }).catch(function() { movScrollData.loading = false; actualizarBtnCargarAnteriores(); });
    } else if (movScrollData.has_more_newer && div.scrollTop + div.clientHeight >= div.scrollHeight - margin) {
        movScrollData.loading = true;
        var url = 'obtener_movimientos.php?id=' + uSel + '&after_fecha=' + encodeURIComponent(movScrollData.last_fecha) + '&after_id=' + movScrollData.last_id;
        fetch(url).then(r => r.json()).then(function(data) {
            if (data.html && data.html.indexOf('NO HAY MOVIMIENTOS') < 0 && data.html.indexOf('fila-mov') >= 0) {
                var tbody = document.getElementById("tablaMovimientos");
                tbody.innerHTML = tbody.innerHTML + data.html;
                movScrollData.last_fecha = data.last_fecha || movScrollData.last_fecha;
                movScrollData.last_id = data.last_id || movScrollData.last_id;
                movScrollData.has_more_newer = !!data.has_more_newer;
                var saldo = parseFloat(data.saldo_actual);
                if (!isNaN(saldo)) { saldoActualCuenta = saldo; actualizarSaldoCobroPanel(); }
                div.scrollTop = div.scrollHeight;
            } else {
                movScrollData.has_more_newer = false;
            }
            movScrollData.loading = false;
            actualizarBtnCargarAnteriores();
        }).catch(function() { movScrollData.loading = false; actualizarBtnCargarAnteriores(); });
    }
}

function abrirEditor(id) {
    var esNivel3 = <?= (isset($_SESSION['acceso_nivel']) && $_SESSION['acceso_nivel'] >= 3) ? 'true' : 'false' ?>;
    if (esNivel3) {
        window.location.href = 'editar_usuario.php?id=' + id;
    } else if (prompt("CLAVE DE SEGURIDAD PARA EDITAR USUARIO:") === <?= json_encode(obtener_clave_borrado($conexion)) ?>) {
        window.location.href = 'editar_usuario.php?id=' + id;
    } else {
        alert("Clave incorrecta.");
    }
}

var cobroExpEsEfvo = false;
function abrirModalCobroExp(esEfvo) {
    cobroExpEsEfvo = !!esEfvo;
    document.getElementById('modalCobroExpTitulo').textContent = cobroExpEsEfvo ? 'Cobro expensas en efectivo (grabar en Caja)' : 'Cobro Exp/transferencia';
    if (!uSel) { alert('Seleccioná un usuario primero.'); return; }
    var fila = document.querySelector('#cuerpo tr.fila-seleccionada');
    var nomUsu = fila ? fila.querySelector('.nombre-txt').innerText : '';
    document.getElementById('cobroUsuarioNombre').textContent = 'Usuario: ' + nomUsu;
    document.getElementById('modalCobroExp').classList.add('visible');
    document.getElementById('cobroPropiedad').innerHTML = '<option value="">-- Cargando... --</option>';

    var movFila = document.querySelector('#tablaMovimientos tr.fila-mov-seleccionada');
    var hoy = new Date();
    var mesPrev = hoy.getMonth(); // 0-based: ene=0, feb=1, ...
    var anioPrev = hoy.getFullYear();
    if (mesPrev === 0) { mesPrev = 12; anioPrev--; }
    var periodoDefault = String(mesPrev).padStart(2,'0') + '/' + anioPrev;
    var montoDefault = '';
    var refDefault = '';
    var conceptoExpensa = '';
    var esExpensa = false;
    if (movFila && movFila.dataset) {
        var compro = (movFila.dataset.compro || '').toUpperCase();
        var concepto = (movFila.dataset.concepto || '').toUpperCase();
        var ref = (movFila.dataset.ref || '').trim();
        var monto = parseFloat(movFila.dataset.monto) || 0;
        esExpensa = (compro === 'EXP/TRANSF' || compro === 'LIQ EXP' || concepto.indexOf('EXPENSAS') >= 0);
        if (esExpensa && ref) {
            refDefault = ref;
            periodoDefault = ref;
        }
        if (esExpensa && !isNaN(monto)) {
            montoDefault = String(Math.abs(monto));
            if (concepto) conceptoExpensa = concepto;
        }
    }
    document.getElementById('cobroPeriodo').value = periodoDefault;
    document.getElementById('cobroMonto').value = montoDefault;

    fetch('obtener_propiedades_propietario.php?id=' + uSel)
        .then(r => r.json())
        .then(props => {
            var sel = document.getElementById('cobroPropiedad');
            sel.innerHTML = '<option value="">-- Elegir propiedad --</option>';
            props.forEach(function(p) {
                var opt = document.createElement('option');
                opt.value = p.propiedad_id;
                var texto = p.propiedad + (p.consorcio ? ' (' + p.consorcio + ')' : '');
                opt.textContent = texto;
                sel.appendChild(opt);
            });
            // Agregar opciones de consorcio para pago de varias propiedades
            var consorciosVistos = {};
            props.forEach(function(p) {
                if (p.consorcio && p.consorcio.trim()) {
                    var c = p.consorcio.trim().toUpperCase();
                    if (!consorciosVistos[c]) {
                        consorciosVistos[c] = true;
                        var optCons = document.createElement('option');
                        optCons.value = 'consorcio:' + c;
                        optCons.textContent = 'CONSORCIO ' + c + ' - Recibo N°';
                        sel.appendChild(optCons);
                    }
                }
            });
            if (props.length === 0) sel.innerHTML = '<option value="">-- Sin propiedades --</option>';

            if (esExpensa && conceptoExpensa) {
                var nombreProp = conceptoExpensa.replace(/^EXPENSAS\s*,?\s*/i, '').trim();
                if (nombreProp) {
                    var opciones = sel.querySelectorAll('option[value]');
                    for (var i = 0; i < opciones.length; i++) {
                        var txt = opciones[i].textContent;
                        var parteProp = txt.split(' (')[0].trim().toUpperCase();
                        if (parteProp === nombreProp || txt.toUpperCase().indexOf(nombreProp) >= 0 || nombreProp.indexOf(parteProp) >= 0) {
                            opciones[i].selected = true;
                            break;
                        }
                    }
                }
            }
        });
}

function cerrarModalCobroExp() {
    document.getElementById('modalCobroExp').classList.remove('visible');
    var reciboInp = document.getElementById('cobroRecibo');
    if (reciboInp) reciboInp.value = '';
}

function mostrarOcultarReciboCobro() {
    var propVal = document.getElementById('cobroPropiedad').value;
    var wrap = document.getElementById('cobroReciboWrap');
    if (wrap) {
        wrap.style.display = (propVal && propVal.indexOf('consorcio:') === 0) ? 'block' : 'none';
        if (wrap.style.display === 'none') document.getElementById('cobroRecibo').value = '';
    }
}

function abrirModalLiquidarExpensas() {
    if (!uSel) {
        alert('Seleccioná un usuario (Consorcio) primero.');
        return;
    }
    if (!esConsorcioUsuario) {
        alert('El usuario seleccionado debe ser un Consorcio.');
        return;
    }
    var fila = document.querySelector('#cuerpo tr.fila-seleccionada');
    var nom = fila ? fila.querySelector('.nombre-txt').innerText : '';
    document.getElementById('liqExpConsorcioNombre').textContent = 'Consorcio: ' + nom;
    var mesActual = String(new Date().getMonth() + 1).padStart(2, '0') + '/' + new Date().getFullYear();
    document.getElementById('liqExpMes').value = mesActual;
    fetch('obtener_siguiente_mes_liq_exp.php?usuario_id=' + uSel)
        .then(function(r) { return r.text(); })
        .then(function(txt) {
            if (txt && txt.trim()) {
                document.getElementById('liqExpMes').value = txt.trim();
            }
            document.getElementById('modalLiqExp').classList.add('visible');
            setTimeout(function() { document.getElementById('liqExpMes').focus(); }, 100);
        })
        .catch(function() {
            document.getElementById('modalLiqExp').classList.add('visible');
            setTimeout(function() { document.getElementById('liqExpMes').focus(); }, 100);
        });
}

function cerrarModalLiqExp() {
    document.getElementById('liqExpDetalle').style.display = 'none';
    document.getElementById('modalLiqExp').classList.remove('visible');
}

var modalPrecioAzucarData = { movimientoId: 0, arriendoId: 0, arriendoFecha: 0 };
function abrirModalPrecioAzucar(movimientoId, arriendoId, arriendoFecha) {
    modalPrecioAzucarData = { movimientoId: movimientoId, arriendoId: arriendoId, arriendoFecha: arriendoFecha };
    document.getElementById('precioAzucarBolsa').value = '';
    document.getElementById('modalPrecioAzucar').classList.add('visible');
    document.getElementById('precioAzucarBolsa').focus();
}
function cerrarModalPrecioAzucar() {
    document.getElementById('modalPrecioAzucar').classList.remove('visible');
}
function guardarPrecioAzucar() {
    var precio = parseFloat((document.getElementById('precioAzucarBolsa').value || '').replace(',', '.').replace(/\s/g, ''));
    if (isNaN(precio) || precio <= 0) {
        alert('Ingrese un precio válido.');
        return;
    }
    var fd = new FormData();
    fd.append('movimiento_id', modalPrecioAzucarData.movimientoId);
    fd.append('arriendo_id', modalPrecioAzucarData.arriendoId);
    fd.append('arriendo_fecha', modalPrecioAzucarData.arriendoFecha);
    fd.append('precio_bolsa', precio);
    fetch('actualizar_movimiento_arriendo_azucar.php', { method: 'POST', body: fd })
        .then(function(r) { return r.text(); })
        .then(function(txt) {
            if (txt.trim() === 'OK') {
                cerrarModalPrecioAzucar();
                var fila = document.querySelector('#cuerpo tr.fila-seleccionada');
                if (fila && uSel) cargarMovimientos(fila, uSel);
            } else {
                alert(txt || 'Error al actualizar.');
            }
        });
}

function borrarLiqExpPeriodoDesdeSelects() {
    var mes = parseInt(document.getElementById('borrarLiqExpMes').value, 10);
    var anio = parseInt(document.getElementById('borrarLiqExpAnio').value, 10);
    if (isNaN(mes) || mes < 1 || mes > 12) return '';
    if (isNaN(anio) || anio < 2000) return '';
    return (mes < 10 ? '0' : '') + mes + '/' + anio;
}

function aplicarBorrarLiqExpConsorcioDefecto() {
    var sel = document.getElementById('borrarLiqExpConsorcio');
    var nota = document.getElementById('borrarLiqExpConsorcioFijoMsg');
    if (!sel) return;
    sel.disabled = false;
    if (nota) nota.style.display = 'none';
    if (esConsorcioUsuario && uSel) {
        var idStr = String(uSel);
        var optExists = false;
        for (var i = 0; i < sel.options.length; i++) {
            if (sel.options[i].value === idStr) {
                optExists = true;
                break;
            }
        }
        if (optExists) {
            sel.value = idStr;
            sel.disabled = true;
            sel.title = 'Consorcio de la cuenta actual';
            if (nota) nota.style.display = 'block';
            return;
        }
    }
    sel.value = '';
    sel.title = '';
}

function abrirModalBorrarLiqExp() {
    if (!accesoNivel3) {
        alert('Esta acción solo está disponible para usuarios con nivel de acceso 3.');
        return;
    }
    if (!uSel || !esConsorcioUsuario) {
        alert('Seleccioná primero la cuenta del consorcio en la lista de usuarios.');
        return;
    }
    var el = document.getElementById('modalBorrarLiqExp');
    if (el) {
        el.classList.add('visible');
        aplicarBorrarLiqExpConsorcioDefecto();
        var p1 = document.getElementById('borrarLiqExpPaso1');
        var p2 = document.getElementById('borrarLiqExpPaso2');
        if (p1) p1.style.display = '';
        if (p2) p2.style.display = 'none';
    }
}
function cerrarModalBorrarLiqExp() {
    var el = document.getElementById('modalBorrarLiqExp');
    if (el) el.classList.remove('visible');
}
function volverPasoBorrarLiqExp() {
    var p1 = document.getElementById('borrarLiqExpPaso1');
    var p2 = document.getElementById('borrarLiqExpPaso2');
    if (p1) p1.style.display = '';
    if (p2) p2.style.display = 'none';
    aplicarBorrarLiqExpConsorcioDefecto();
}
function pasoSiguienteBorrarLiqExp() {
    if (!accesoNivel3) return;
    var consorcioId = document.getElementById('borrarLiqExpConsorcio').value;
    var periodo = borrarLiqExpPeriodoDesdeSelects();
    if (!consorcioId) {
        alert('Seleccioná un consorcio.');
        return;
    }
    if (!periodo) {
        alert('Elegí mes y año válidos.');
        return;
    }
    var sel = document.getElementById('borrarLiqExpConsorcio');
    var nombreCons = '';
    if (sel && sel.options[sel.selectedIndex]) {
        nombreCons = sel.options[sel.selectedIndex].text || '';
    }
    var html = '<strong>Consorcio:</strong> ' + nombreCons + '<br>' +
        '<strong>Período (referencia):</strong> ' + periodo + '<br><br>' +
        'Se eliminarán en el consorcio: <strong>LIQ EXPENSAS</strong> y <strong>Honorarios</strong> (referencia del mes siguiente al período), y en propietarios/inquilinos: <strong>LIQ EXP</strong> y <strong>LIQ EXP EXT</strong>. No se borran cobros (EXP/EFVO).';
    var box = document.getElementById('borrarLiqExpConfirmText');
    if (box) box.innerHTML = html;
    var p1 = document.getElementById('borrarLiqExpPaso1');
    var p2 = document.getElementById('borrarLiqExpPaso2');
    if (p1) p1.style.display = 'none';
    if (p2) p2.style.display = '';
}
function ejecutarBorrarLiqExp() {
    if (!accesoNivel3) {
        alert('Sin permiso (solo nivel 3).');
        return;
    }
    var consorcioId = document.getElementById('borrarLiqExpConsorcio').value;
    var periodo = borrarLiqExpPeriodoDesdeSelects();
    if (!consorcioId || !periodo) {
        alert('Datos incompletos. Volvé al paso anterior.');
        return;
    }
    var fd = new FormData();
    fd.append('consorcio_id', consorcioId);
    fd.append('periodo', periodo);
    fetch('eliminar_liq_expensas_periodo.php', { method: 'POST', body: fd, credentials: 'same-origin' })
        .then(function(r) { return r.text(); })
        .then(function(txt) {
            var data;
            try {
                data = JSON.parse(txt);
            } catch (e) {
                throw new Error((txt || '').trim().slice(0, 500) || 'El servidor no devolvió JSON (¿sesión expirada?).');
            }
            return data;
        })
        .then(function(data) {
            if (data && data.ok) {
                alert(data.msg);
                cerrarModalBorrarLiqExp();
                volverPasoBorrarLiqExp();
                var fila = document.querySelector('#cuerpo tr.fila-seleccionada');
                if (fila) cargarMovimientos(fila, uSel);
            } else {
                alert(data && data.msg ? data.msg : 'No se pudo eliminar.');
            }
        })
        .catch(function(err) {
            alert('Error al borrar: ' + (err && err.message ? err.message : String(err)));
        });
}

function abrirModalBorrarTodasLiqExp() {
    if (!accesoNivel3) {
        alert('Esta acción solo está disponible para usuarios con nivel de acceso 3.');
        return;
    }
    var el = document.getElementById('modalBorrarTodasLiqExp');
    if (el) {
        el.classList.add('visible');
        var selCons = document.getElementById('borrarTodasLiqExpConsorcio');
        selCons.value = esConsorcioUsuario && uSel ? String(uSel) : '';
    }
}
function cerrarModalBorrarTodasLiqExp() {
    var el = document.getElementById('modalBorrarTodasLiqExp');
    if (el) el.classList.remove('visible');
}
function ejecutarBorrarTodasLiqExp() {
    if (!accesoNivel3) {
        alert('Sin permiso (solo nivel 3).');
        return;
    }
    var consorcioId = document.getElementById('borrarTodasLiqExpConsorcio').value;
    if (!consorcioId) {
        alert('Seleccioná un consorcio.');
        return;
    }
    if (!confirm('¿Eliminar TODOS los movimientos de liquidación de expensas (LIQ EXPENSAS, Honorarios, LIQ EXP, LIQ EXP EXT) del consorcio seleccionado? Esta acción no se puede deshacer.')) return;
    var fd = new FormData();
    fd.append('consorcio_id', consorcioId);
    fetch('borrar_todos_liq_expensas.php', { method: 'POST', body: fd, credentials: 'same-origin' })
        .then(function(r) { return r.text(); })
        .then(function(txt) {
            var data;
            try {
                data = JSON.parse(txt);
            } catch (e) {
                throw new Error((txt || '').trim().slice(0, 500) || 'El servidor no devolvió JSON (¿sesión expirada?).');
            }
            return data;
        })
        .then(function(data) {
            if (data && data.ok) {
                alert(data.msg);
                cerrarModalBorrarTodasLiqExp();
                var fila = document.querySelector('#cuerpo tr.fila-seleccionada');
                if (fila) cargarMovimientos(fila, uSel);
            } else {
                alert(data && data.msg ? data.msg : 'No se pudo eliminar.');
            }
        })
        .catch(function(err) {
            alert('Error al borrar: ' + (err && err.message ? err.message : String(err)));
        });
}

function imprimirMovimientos() {
    if (!uSel) {
        alert('Seleccioná un usuario primero.');
        return;
    }
    var cant = prompt('¿Cuántos últimos movimientos imprimir?', '20');
    if (cant === null) return;
    var n = parseInt(cant, 10);
    if (isNaN(n) || n < 1) {
        alert('Ingresá un número válido (mínimo 1).');
        return;
    }
    if (n > 500) n = 500;
    window.open('imprimir_movimientos.php?id=' + uSel + '&limit=' + n, '_blank', 'width=900,height=700');
}

function imprimirEstadoCuenta() {
    if (!uSel) {
        alert('Seleccioná un usuario primero.');
        return;
    }
    window.open('imprimir_movimientos.php?id=' + uSel + '&limit=15', '_blank', 'width=900,height=700');
}

function abrirModalAntCel() {
    var el = document.getElementById('modalAntCel');
    if (el) {
        el.classList.add('activo');
        var hoy = new Date();
        var dd = String(hoy.getDate()).padStart(2, '0');
        var mm = String(hoy.getMonth() + 1).padStart(2, '0');
        var aaaa = hoy.getFullYear();
        var fechaHoy = dd + '/' + mm + '/' + aaaa;
        document.getElementById('ant_concepto').value = 'Anticipo';
        document.getElementById('ant_monto').value = '';
        document.getElementById('ant_buscador').value = '';
        document.getElementById('ant_usuario').value = '';
        document.getElementById('ant_resultados').style.display = 'none';
        document.getElementById('ant_msg').style.display = 'none';
        document.getElementById('ant_fecha').value = fechaHoy;
        setTimeout(function() { document.getElementById('ant_buscador').focus(); }, 100);
    }
}
function cerrarModalAntCel() {
    var el = document.getElementById('modalAntCel');
    if (el) el.classList.remove('activo');
}
(function() {
    var buscador = document.getElementById('ant_buscador');
    var resultados = document.getElementById('ant_resultados');
    var hiddenId = document.getElementById('ant_usuario');
    if (!buscador || !resultados) return;
    function filtrarAnt() {
        var q = (buscador.value || '').toUpperCase().trim();
        var lista = typeof usuariosAnticipoLista !== 'undefined' ? usuariosAnticipoLista : [];
        var filtrados = q ? lista.filter(function(u) { return (u.apellido || '').toUpperCase().indexOf(q) >= 0; }) : lista;
        resultados.innerHTML = filtrados.slice(0, 50).map(function(u) {
            return '<div class="item-ant" data-id="' + u.id + '" data-nombre="' + (u.apellido || '').replace(/"/g, '&quot;') + '">' + (u.apellido || '') + '</div>';
        }).join('');
        resultados.style.display = filtrados.length > 0 ? 'block' : 'none';
    }
    buscador.addEventListener('input', filtrarAnt);
    buscador.addEventListener('focus', filtrarAnt);
    resultados.addEventListener('click', function(e) {
        if (e.target.classList.contains('item-ant')) {
            hiddenId.value = e.target.getAttribute('data-id');
            buscador.value = e.target.getAttribute('data-nombre') || '';
            resultados.style.display = 'none';
        }
    });
    document.addEventListener('click', function(e) {
        var wrap = document.getElementById('buscadorAntWrap');
        if (wrap && resultados && !wrap.contains(e.target)) resultados.style.display = 'none';
    });
})();
function antFechaAISO() {
    var v = (document.getElementById('ant_fecha').value || '').trim();
    if (!v) return '';
    var partes = v.split(/[\/\-\.]/);
    if (partes.length !== 3) return v;
    var d = partes[0].padStart(2, '0'), mes = partes[1].padStart(2, '0'), anio = partes[2];
    if (anio.length === 2) anio = '20' + anio;
    return anio + '-' + mes + '-' + d;
}
function guardarAnticipo() {
    var usuarioId = document.getElementById('ant_usuario').value;
    var fechaISO = antFechaAISO();
    var concepto = document.getElementById('ant_concepto').value.trim() || 'Anticipo';
    var monto = parseFloat(document.getElementById('ant_monto').value);
    var msgEl = document.getElementById('ant_msg');
    if (!usuarioId) {
        msgEl.textContent = 'Seleccioná un usuario.';
        msgEl.style.display = 'block';
        msgEl.style.color = '#dc3545';
        return;
    }
    if (!monto || monto <= 0) {
        msgEl.textContent = 'Ingresá un monto mayor a 0.';
        msgEl.style.display = 'block';
        msgEl.style.color = '#dc3545';
        return;
    }
    if (!fechaISO) {
        msgEl.textContent = 'Ingresá la fecha (dd/mm/aaaa).';
        msgEl.style.display = 'block';
        msgEl.style.color = '#dc3545';
        return;
    }
    msgEl.style.display = 'none';
    var p = new URLSearchParams({
        id: usuarioId,
        fecha: fechaISO,
        concepto: concepto,
        compro: 'ANTICIPO',
        refer: 'ANTICIPO',
        monto: -Math.abs(monto)
    });
    fetch('guardar_movimiento.php', { method: 'POST', body: p })
        .then(function(r) { return r.text(); })
        .then(function(txt) {
            if (txt.trim() === 'OK' || txt.trim() === 'OK_CAJA') {
                cerrarModalAntCel();
                if (uSel === usuarioId) {
                    var fila = document.querySelector('#cuerpo tr.fila-seleccionada');
                    if (fila) cargarMovimientos(fila, usuarioId);
                }
            } else {
                msgEl.textContent = 'Falta dato o corregir.';
                msgEl.style.display = 'block';
                msgEl.style.color = '#dc3545';
            }
        })
        .catch(function() {
            msgEl.textContent = 'Error de red.';
            msgEl.style.display = 'block';
            msgEl.style.color = '#dc3545';
        });
}

function abrirImprimirExpensas() {
    if (!uSel) {
        alert('Seleccioná un usuario (Consorcio) primero.');
        return;
    }
    if (!esConsorcioUsuario) {
        alert('El usuario seleccionado debe ser un Consorcio.');
        return;
    }
    var d = new Date();
    var mesEl = document.getElementById('impExpMes');
    var anioEl = document.getElementById('impExpAnio');
    if (mesEl) mesEl.value = String(d.getMonth() + 1);
    if (anioEl) anioEl.value = String(d.getFullYear());
    document.getElementById('modalImprimirExpensas').classList.add('visible');
}

function cerrarModalImprimirExpensas() {
    document.getElementById('modalImprimirExpensas').classList.remove('visible');
}

function confirmarImprimirExpensas() {
    if (!uSel) return;
    var mes = parseInt(document.getElementById('impExpMes').value, 10);
    var anio = parseInt(document.getElementById('impExpAnio').value, 10);
    if (!mes || mes < 1 || mes > 12 || !anio || anio < 2000) {
        alert('Seleccioná un mes y año válidos.');
        return;
    }
    cerrarModalImprimirExpensas();
    window.open('imprimir_expensas_consorcio.php?id=' + uSel + '&mes=' + mes + '&anio=' + anio, '_blank', 'width=900,height=700');
}

function ejecutarLiquidarExpensas() {
    var mes = document.getElementById('liqExpMes').value.trim();
    if (!mes) {
        alert('Ingresá el mes a liquidar (MM/AAAA).');
        return;
    }
    var fd = new FormData();
    fd.append('usuario_id', uSel);
    fd.append('mes', mes);
    fetch('liquidar_expensas_consorcio.php', { method: 'POST', body: fd })
        .then(function(r) { return r.text(); })
        .then(function(txt) {
            var data;
            try {
                data = JSON.parse(txt);
            } catch (e) {
                if (txt.trim() === 'OK') {
                    var fila = document.querySelector('#cuerpo tr.fila-seleccionada');
                    if (fila) cargarMovimientos(fila, uSel);
                    alert('Liquidación de expensas guardada correctamente.');
                    cerrarModalLiqExp();
                    return;
                }
                alert(txt || 'Falta dato o corregir.');
                return;
            }
            if (data && data.ok) {
                var fila = document.querySelector('#cuerpo tr.fila-seleccionada');
                if (fila) cargarMovimientos(fila, uSel);
                alert('Liquidación de expensas guardada correctamente.');
                cerrarModalLiqExp();
            } else {
                alert('Falta dato o corregir.');
            }
        });
}

function guardarCobroExp() {
    var propVal = document.getElementById('cobroPropiedad').value;
    var periodo = document.getElementById('cobroPeriodo').value.trim();
    var monto = document.getElementById('cobroMonto').value.replace(',', '.').trim();
    if (!propVal || !periodo || !monto) {
        alert('Completá propiedad, período y monto.');
        return;
    }
    if (isNaN(parseFloat(monto)) || parseFloat(monto) <= 0) {
        alert('El monto debe ser un número mayor a 0.');
        return;
    }
    var fd = new FormData();
    fd.append('usuario_id', uSel);
    if (propVal.indexOf('consorcio:') === 0) {
        var recibo = (document.getElementById('cobroRecibo') || {}).value;
        if (!recibo || !recibo.trim()) {
            alert('Completá el número de recibo.');
            return;
        }
        fd.append('consorcio', propVal.substring(10));
        fd.append('recibo_numero', recibo.trim());
    } else {
        fd.append('propiedad_id', propVal);
    }
    fd.append('periodo', periodo);
    fd.append('monto', parseFloat(monto));
    fd.append('efvo', cobroExpEsEfvo ? '1' : '0');
    fetch('guardar_cobro_expensa.php', { method: 'POST', body: fd })
        .then(r => r.text())
        .then(txt => {
            if (txt === 'OK') {
                cerrarModalCobroExp();
                var fila = document.querySelector('#cuerpo tr.fila-seleccionada');
                if (fila) cargarMovimientos(fila, uSel);
                document.getElementById('cobroMonto').value = '';
            } else {
                alert('Falta dato o corregir.');
            }
        });
}

function actualizarDiferenciaMigrar() {
    var saldoEl = document.getElementById("migrar-saldo-actual");
    var inp = document.getElementById('monto-migrar');
    var diffWrap = document.getElementById('migrar-diferencia-wrap');
    var diffEl = document.getElementById('migrar-diferencia');
    if (!saldoEl || !inp || !diffWrap || !diffEl) return;
    var saldoActual = parseFloat(saldoEl.getAttribute('data-valor') || '0');
    var objetivo = parseMonto(inp.value);
    if (isNaN(objetivo) || inp.value === '') {
        diffWrap.style.display = 'none';
        return;
    }
    var diferencia = objetivo - saldoActual;
    diffWrap.style.display = 'inline';
    diffEl.textContent = '$ ' + diferencia.toLocaleString('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    diffEl.style.color = diferencia >= 0 ? '#28a745' : '#dc3545';
}

function migrarSaldo() {
    if (!uSel) {
        alert('Seleccioná un usuario primero.');
        return;
    }
    var inp = document.getElementById('monto-migrar');
    if (!inp) return;
    var montoObjetivo = parseMonto(inp.value);
    if (isNaN(montoObjetivo)) {
        alert('Ingresá un saldo objetivo válido.');
        return;
    }
    fetch('obtener_saldo_usuario.php?id=' + uSel)
        .then(function(r) { return r.json(); })
        .then(function(data) {
            var saldoActual = parseFloat(data.saldo) || 0;
            var montoAGrabar = montoObjetivo - saldoActual;
            if (Math.abs(montoAGrabar) < 0.01) {
                alert('El saldo ya es $ ' + montoObjetivo.toLocaleString('es-AR', { minimumFractionDigits: 2 }) + '. No se requiere migración.');
                return Promise.resolve('SKIP');
            }
            if (!confirm('Saldo actual: $ ' + saldoActual.toLocaleString('es-AR', { minimumFractionDigits: 2 }) + '\nSaldo objetivo: $ ' + montoObjetivo.toLocaleString('es-AR', { minimumFractionDigits: 2 }) + '\n\nSe creará movimiento INICIAL de $ ' + montoAGrabar.toLocaleString('es-AR', { minimumFractionDigits: 2 }) + '\n(para que el saldo quede en $ ' + montoObjetivo.toLocaleString('es-AR', { minimumFractionDigits: 2 }) + ')')) return Promise.resolve('SKIP');
            var fd = new FormData();
            fd.append('usuario_id', uSel);
            fd.append('monto', montoObjetivo);
            return fetch('migrar_saldo.php', { method: 'POST', body: fd });
        })
        .then(function(res) {
            if (res === 'SKIP') return 'SKIP';
            return res && res.text ? res.text() : '';
        })
        .then(function(txt) {
            if (txt === 'SKIP' || typeof txt !== 'string') return;
            if (txt.trim() === 'OK') {
                var fila = document.querySelector('#cuerpo tr.fila-seleccionada');
                if (fila) cargarMovimientos(fila, uSel);
                inp.value = '';
                actualizarDiferenciaMigrar();
                alert('Migración realizada correctamente.');
            } else {
                alert(txt.trim() || 'Error al migrar.');
            }
        })
        .catch(function() { alert('Error al obtener saldo o migrar.'); });
}

var cobroCajaItem1 = null, cobroCajaItem2 = null;

function actualizarSaldoCobroPanel() {
    var el = document.getElementById("cobroCaja_saldo");
    if (!el) return;
    var s = saldoActualCuenta;
    el.textContent = "$ " + s.toLocaleString("es-AR", { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    el.style.color = s >= 0 ? "#28a745" : "#dc3545";
}

function ponerFechaActualCobroCaja() {
    var inp = document.getElementById("cobroCaja_fecha");
    if (!inp) return;
    var hoy = new Date();
    inp.value = String(hoy.getDate()).padStart(2, '0') + '/' + String(hoy.getMonth() + 1).padStart(2, '0') + '/' + hoy.getFullYear();
}

function guardarDejarCuentaDefault(checked) {
    try { sessionStorage.setItem("cobroCaja_dejarCuentaDefault", checked ? "1" : "0"); } catch (e) {}
}

function resetCobroCaja() {
    cobroCajaItem1 = null;
    cobroCajaItem2 = null;
    var wrap1 = document.getElementById("cobroCaja_item1Wrap");
    var wrap2 = document.getElementById("cobroCaja_item2Wrap");
    var wrapV = document.getElementById("cobroCaja_vueltoWrap");
    var el1 = document.getElementById("cobroCaja_item1");
    var el2 = document.getElementById("cobroCaja_item2");
    var dinero = document.getElementById("cobroCaja_dinero");
    var vuelto = document.getElementById("cobroCaja_vuelto");
    var chk = document.getElementById("cobroCaja_dejarCuenta");
    actualizarSaldoCobroPanel();
    if (wrap1) wrap1.style.display = "none";
    if (wrap2) wrap2.style.display = "none";
    if (wrapV) wrapV.style.display = "none";
    if (el1) el1.textContent = "";
    if (el2) el2.textContent = "";
    if (dinero) dinero.value = "";
    if (vuelto) vuelto.value = "";
    var dejarCuentaDefault = false;
    try { dejarCuentaDefault = sessionStorage.getItem("cobroCaja_dejarCuentaDefault") === "1"; } catch (e) {}
    if (chk) chk.checked = dejarCuentaDefault;
    ponerFechaActualCobroCaja();
}

function asignarCobroCajaItem(concepto, monto, periodo) {
    var montoAbs = Math.abs(parseFloat(monto) || 0);
    if (montoAbs <= 0) return;
    var texto = "Cobro de: " + (concepto || "") + " $ " + montoAbs.toLocaleString("es-AR", { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    var wrap1 = document.getElementById("cobroCaja_item1Wrap");
    var wrap2 = document.getElementById("cobroCaja_item2Wrap");
    var wrapV = document.getElementById("cobroCaja_vueltoWrap");
    var el2 = document.getElementById("cobroCaja_item2");
    if (!cobroCajaItem1) {
        cobroCajaItem1 = { concepto: "COBRO DE: " + (concepto || "").toUpperCase(), monto: montoAbs, periodo: (periodo || "").trim() };
        cobroCajaItem2 = null;
        var el1 = document.getElementById("cobroCaja_item1");
        if (el1) el1.textContent = texto;
        if (wrap1) wrap1.style.display = "flex";
        if (wrapV) wrapV.style.display = "flex";
        if (wrap2) wrap2.style.display = "none";
        if (el2) el2.textContent = "";
    } else if (!cobroCajaItem2) {
        var conceptoNorm = ("COBRO DE: " + (concepto || "").toUpperCase()).trim();
        if (cobroCajaItem1.concepto === conceptoNorm && Math.abs(cobroCajaItem1.monto - montoAbs) < 0.01) return;
        cobroCajaItem2 = { concepto: conceptoNorm, monto: montoAbs, periodo: (periodo || "").trim() };
        if (el2) el2.textContent = texto;
        if (wrap2) wrap2.style.display = "flex";
    } else {
        var conceptoNorm2 = ("COBRO DE: " + (concepto || "").toUpperCase()).trim();
        if (cobroCajaItem1.concepto === conceptoNorm2 && Math.abs(cobroCajaItem1.monto - montoAbs) < 0.01) return;
        cobroCajaItem2 = { concepto: conceptoNorm2, monto: montoAbs, periodo: (periodo || "").trim() };
        if (el2) el2.textContent = texto;
    }
    actualizarCobroCajaVuelto();
}

function actualizarCobroCajaVuelto() {
    var dinero = parseMonto((document.getElementById("cobroCaja_dinero") || {}).value) || 0;
    var totalItems = (cobroCajaItem1 ? cobroCajaItem1.monto : 0) + (cobroCajaItem2 ? cobroCajaItem2.monto : 0);
    var vuelto = dinero - totalItems;
    var el = document.getElementById("cobroCaja_vuelto");
    if (el) el.value = vuelto >= 0 ? vuelto.toLocaleString("es-AR", { minimumFractionDigits: 2, maximumFractionDigits: 2 }) : (vuelto.toLocaleString("es-AR", { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + " (falta)");
}

function aceptarCobroCaja() {
    if (!uSel || uSel === 1) { alert("Seleccioná un usuario válido."); return; }
    if (!cobroCajaItem1 && !cobroCajaItem2) { alert("Seleccioná al menos un registro en la grilla."); return; }
    var dinero = parseMonto((document.getElementById("cobroCaja_dinero") || {}).value) || 0;
    if (isNaN(dinero) || dinero < 0) { alert("Ingresá un monto válido de dinero recibido."); return; }
    var totalItems = (cobroCajaItem1 ? cobroCajaItem1.monto : 0) + (cobroCajaItem2 ? cobroCajaItem2.monto : 0);
    if (totalItems > 0 && dinero <= 0) {
        alert("Ingresá el monto de dinero recibido.");
        return;
    }
    var items = [];
    var saldoAFecha = saldoActualCuenta;
    function extraerConcepto(c) {
        var s = (c || "").trim();
        if (s.toUpperCase().indexOf("COBRO DE:") === 0) return s.substring(9).trim();
        return s;
    }
    function conceptoPagoParcialConItem(itemRef) {
        var base = (dinero > saldoAFecha) ? "PAGO SALDO" : "PAGO A CUENTA";
        if (dinero > saldoAFecha && itemRef) {
            var nom = extraerConcepto(itemRef.concepto);
            if (nom) return nom + " - " + base;
        }
        return base;
    }
    if (dinero < totalItems) {
        if (!cobroCajaItem2) {
            items.push({ concepto: conceptoPagoParcialConItem(cobroCajaItem1), monto: dinero });
        } else {
            var m1 = cobroCajaItem1.monto;
            if (dinero >= m1) {
                items.push({ concepto: cobroCajaItem1.concepto, monto: m1, periodo: cobroCajaItem1.periodo || "" });
                items.push({ concepto: conceptoPagoParcialConItem(cobroCajaItem2), monto: dinero - m1 });
            } else {
                items.push({ concepto: cobroCajaItem1.concepto, monto: dinero, periodo: cobroCajaItem1.periodo || "" });
            }
        }
    } else {
        if (cobroCajaItem1) items.push({ concepto: cobroCajaItem1.concepto, monto: cobroCajaItem1.monto, periodo: cobroCajaItem1.periodo || "" });
        if (cobroCajaItem2) items.push({ concepto: cobroCajaItem2.concepto, monto: cobroCajaItem2.monto, periodo: cobroCajaItem2.periodo || "" });
    }
    var fechaEl = document.getElementById("cobroCaja_fecha");
    var fechaVal = fechaEl ? fechaEl.value.trim() : "";
    if (!fechaVal) {
        ponerFechaActualCobroCaja();
        fechaVal = ((document.getElementById("cobroCaja_fecha") || {}).value || "").trim();
    }
    var partes = (fechaVal || "").split(/[\/\-\.]/);
    var fechaISO = "";
    if (partes.length === 3) {
        var d = partes[0].padStart(2, "0"), m = partes[1].padStart(2, "0"), a = partes[2];
        if (a.length === 2) a = "20" + a;
        fechaISO = a + "-" + m + "-" + d;
    }
    if (!fechaISO) fechaISO = new Date().toISOString().slice(0, 10);
    var vuelto = dinero - totalItems;
    var chkDejar = document.getElementById("cobroCaja_dejarCuenta");
    var fd = new FormData();
    fd.append("usuario_id", uSel);
    fd.append("fecha", fechaISO);
    fd.append("items", JSON.stringify(items));
    fd.append("dejar_cuenta", chkDejar && chkDejar.checked ? "1" : "0");
    fd.append("vuelto", vuelto >= 0 ? vuelto.toFixed(2) : "0");
    fetch("guardar_cobro_caja.php", { method: "POST", body: fd })
        .then(function(r) { return r.text(); })
        .then(function(txt) {
            if (txt.trim() === "OK") {
                var msgVuelto = "";
                if (!chkDejar || !chkDejar.checked) {
                    if (vuelto > 0) msgVuelto = "\n\nEntregar vuelto: $ " + vuelto.toLocaleString("es-AR", { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                }
                var itemsRecibo = items.slice();
                if (vuelto > 0) {
                    if (chkDejar && chkDejar.checked) {
                        itemsRecibo.push({ concepto: "A CUENTA PROXIMO PAGO", monto: vuelto });
                    } else {
                        itemsRecibo.push({ concepto: "(A ENTREGAR VUELTO)", monto: vuelto });
                    }
                }
                var totalRecibo = itemsRecibo.reduce(function(s, it) { return s + (parseFloat(it.monto) || 0); }, 0);
                var periodoRecibo = (cobroCajaItem1 && cobroCajaItem1.periodo) ? cobroCajaItem1.periodo : ((cobroCajaItem2 && cobroCajaItem2.periodo) ? cobroCajaItem2.periodo : "");
                var urlRecibo = "generar_recibo_cobro_caja.php?usuario_id=" + uSel + "&fecha=" + encodeURIComponent(fechaISO) + "&items=" + encodeURIComponent(JSON.stringify(itemsRecibo)) + "&total=" + encodeURIComponent(totalRecibo.toFixed(2));
                if (periodoRecibo) urlRecibo += "&periodo=" + encodeURIComponent(periodoRecibo);
                window.open(urlRecibo, "_blank", "noopener");
                resetCobroCaja();
                var fila = document.querySelector("#cuerpo tr.fila-seleccionada");
                if (fila) cargarMovimientos(fila, uSel);
                if (msgVuelto) alert("Cobro guardado correctamente." + msgVuelto);
            } else {
                alert(txt.trim() || "Error al grabar.");
            }
        });
}

function cancelarCobroCaja() {
    resetCobroCaja();
}

function seleccionarFila(el, movimientoId, fecha, concepto, compro, ref, monto) {
    document.querySelectorAll('.fila-mov').forEach(f => f.classList.remove('fila-mov-seleccionada'));
    el.classList.add('fila-mov-seleccionada');
    movSel = { movimientoId, fecha, concepto, compro, ref, monto, usuario: document.querySelector('.fila-seleccionada .nombre-txt').innerText };
    document.getElementById("btnWord").style.display = "block";
    var panelCobro = document.getElementById("panelCobroCaja");
    if (panelCobro && panelCobro.style.display !== "none" && uSel !== 1) {
        asignarCobroCajaItem(concepto, monto, ref);
    }
    if (tipo === 'INGRESO' && concepto) {
        document.getElementById("filaCarga").style.display = "table-footer-group";
        document.getElementById("ins_concepto").value = "Cobro de: " + concepto;
        ponerFechaActual();
    }
    if (tipo === 'RETIRO' && esArrendadorUsuario && concepto) {
        document.getElementById("filaCarga").style.display = "table-footer-group";
        var conceptoFormateado = (concepto || '').replace(/KILOS\s+DTOS PACTADOS/g, "KILOS. DTOS PACTADOS").replace(/\bKILOS\b(?!\.?\s*DTOS PACTADOS)/i, "KILOS. DTOS PACTADOS: ");
        var precioRef = (ref || '').trim();
        document.getElementById("ins_concepto").value = "Pago de : " + conceptoFormateado.trim() + " precio Ref $" + (precioRef || '');
        document.getElementById("ins_compro").value = "PGO ARRIENDO";
        document.getElementById("ins_refer").value = String(new Date().getFullYear());
        actualizarCheckGrabarCaja();
        ponerFechaActual();
    }
}

function eliminarMovSeguro(movId) {
    if (esConsorcioUsuario) {
        if (!confirm("¿Eliminar este movimiento permanentemente?")) return;
        fetch('eliminar_movimiento.php?mid=' + movId)
            .then(r => r.text())
            .then(res => {
                if (res.trim() === "OK") {
                    var fila = document.querySelector('#cuerpo tr.fila-seleccionada') || document.querySelector('#cuerpo tr[data-id="' + uSel + '"]');
                    if (fila) cargarMovimientos(fila, uSel);
                }
            });
        return;
    }
    var claveIngresada = prompt("CLAVE DE SEGURIDAD PARA ELIMINAR:");
    if (claveIngresada === null) return;
    var fd = new FormData();
    fd.append('clave', claveIngresada);
    fetch('verificar_clave_borrado.php', { method: 'POST', body: fd })
        .then(r => r.text())
        .then(function(res) {
            if (res.trim() !== "OK") {
                alert("Clave incorrecta.");
                return;
            }
            if (!confirm("¿Eliminar este movimiento permanentemente?")) return;
            fetch('eliminar_movimiento.php?mid=' + movId)
                .then(r2 => r2.text())
                .then(function(res2) {
                    if (res2.trim() === "OK") {
                        var fila = document.querySelector('#cuerpo tr.fila-seleccionada') || document.querySelector('#cuerpo tr[data-id="' + uSel + '"]');
                        if (fila) cargarMovimientos(fila, uSel);
                    }
                });
        });
}

function generarWord() {
    if (esConsorcioUsuario && uSel) {
        abrirImprimirExpensas();
    } else if (!movSel || !movSel.movimientoId) {
        return;
    } else {
        // Si no es consorcio, generar recibo normal
        window.open('generar_recibo_word.php?id=' + movSel.movimientoId, '_blank', 'noopener');
    }
}

// Orden de campos al cargar movimiento (Enter pasa al siguiente)
const CAMPOS_MOV = ['ins_fecha', 'ins_concepto', 'ins_compro', 'ins_refer', 'ins_monto'];

var cartelExpensasTimer = null;
function mostrarCartelExpensasPrimero(inp) {
    if (!esPropietarioOInquilino) return;
    var v = (inp.value || '').replace(/[\s,.]/g, '');
    if (v.length > 0 && /\d/.test(v)) {
        var el = document.getElementById('cartelExpensasPrimero');
        if (el) {
            el.style.display = 'block';
            if (cartelExpensasTimer) clearTimeout(cartelExpensasTimer);
            cartelExpensasTimer = setTimeout(function() { el.style.display = 'none'; cartelExpensasTimer = null; }, 1000);
        }
    }
}

(function() {
    var filaCarga = document.getElementById("filaCarga");
    if (filaCarga) {
        filaCarga.addEventListener("keydown", function(e) {
            if (e.key === "Escape") {
                filaCarga.style.display = "none";
                var inpCal = document.getElementById("ins_fecha_cal");
                if (inpCal && inpCal.style.display === "block") {
                    inpCal.style.display = "none";
                    var inpTexto = document.getElementById("ins_fecha");
                    if (inpTexto) inpTexto.style.visibility = "";
                }
                var insFecha = document.getElementById("ins_fecha");
                var insConcepto = document.getElementById("ins_concepto");
                var insRefer = document.getElementById("ins_refer");
                var insMonto = document.getElementById("ins_monto");
                if (insFecha) insFecha.value = "";
                if (insConcepto) insConcepto.value = "";
                if (insRefer) insRefer.value = "";
                if (insMonto) insMonto.value = "";
                e.stopPropagation();
                e.preventDefault();
            }
        });
    }
})();

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        var modalBorrarTodas = document.getElementById('modalBorrarTodasLiqExp');
        if (modalBorrarTodas && modalBorrarTodas.classList.contains('visible')) {
            cerrarModalBorrarTodasLiqExp();
            return;
        }
        var modalBorrarLiq = document.getElementById('modalBorrarLiqExp');
        if (modalBorrarLiq && modalBorrarLiq.classList.contains('visible')) {
            cerrarModalBorrarLiqExp();
            return;
        }
        var modalPrecioAz = document.getElementById('modalPrecioAzucar');
        if (modalPrecioAz && modalPrecioAz.classList.contains('visible')) {
            cerrarModalPrecioAzucar();
            return;
        }
        var modalAnt = document.getElementById('modalAntCel');
        if (modalAnt && modalAnt.classList.contains('activo')) {
            cerrarModalAntCel();
            return;
        }
        var modalTut = document.getElementById('modalTutoriales');
        if (modalTut && modalTut.classList.contains('visible')) {
            cerrarModalTutoriales();
            return;
        }
    }
    if (e.key !== 'Enter') return;
    const filaCarga = document.getElementById("filaCarga");
    if (filaCarga.style.display === 'none') return;
    const id = document.activeElement.id;
    const idx = CAMPOS_MOV.indexOf(id);
    if (idx === -1) return;
    e.preventDefault();
    if (idx < CAMPOS_MOV.length - 1) {
        document.getElementById(CAMPOS_MOV[idx + 1]).focus();
    } else {
        guardar();
    }
});

function ponerFechaActual() {
    var inpFecha = document.getElementById("ins_fecha");
    if (!inpFecha || document.getElementById("filaCarga").style.display === "none") return;
    if ((inpFecha.value || "").trim() !== "") return; /* No sobrescribir si el usuario ya ingresó otra fecha */
    var hoy = new Date();
    var d = String(hoy.getDate()).padStart(2, '0');
    var m = String(hoy.getMonth() + 1).padStart(2, '0');
    var a = hoy.getFullYear();
    inpFecha.value = d + '/' + m + '/' + a;
}

function cursorAlInicioFecha() {
    var inp = document.getElementById("ins_fecha");
    if (!inp) return;
    setTimeout(function() { inp.setSelectionRange(0, 0); inp.focus(); }, 0);
}

function abrirCalendarioFecha(e) {
    e.preventDefault();
    var inpTexto = document.getElementById("ins_fecha");
    var inpCal = document.getElementById("ins_fecha_cal");
    if (!inpTexto || !inpCal) return;
    var v = (inpTexto.value || "").trim().split(/[\/\-\.]/);
    if (v.length === 3) {
        var d = v[0].padStart(2, '0'), m = v[1].padStart(2, '0'), a = v[2];
        if (a.length === 2) a = '20' + a;
        inpCal.value = a + '-' + m + '-' + d;
    } else {
        var hoy = new Date();
        inpCal.value = hoy.getFullYear() + '-' + String(hoy.getMonth() + 1).padStart(2, '0') + '-' + String(hoy.getDate()).padStart(2, '0');
    }
    inpTexto.style.visibility = 'hidden';
    inpCal.style.display = 'block';
    inpCal.focus();
    if (inpCal.showPicker) inpCal.showPicker();
    function cerrarCal() {
        var val = inpCal.value;
        if (val) {
            var p = val.split('-');
            inpTexto.value = p[2] + '/' + p[1] + '/' + p[0];
        }
        inpCal.style.display = 'none';
        inpTexto.style.visibility = '';
        inpTexto.focus();
        inpCal.removeEventListener('change', onCambio);
        inpCal.removeEventListener('blur', onBlur);
    }
    function onCambio() { cerrarCal(); }
    function onBlur() { setTimeout(cerrarCal, 150); }
    inpCal.addEventListener('change', onCambio);
    inpCal.addEventListener('blur', onBlur);
}

function cursorInicioAntFecha() {
    var inp = document.getElementById('ant_fecha');
    if (inp) {
        setTimeout(function() {
            var val = inp.value || '';
            var len = val.length;
            var hastaDia = len >= 2 ? 2 : len;
            inp.setSelectionRange(0, hastaDia);
            inp.selectionStart = 0;
            inp.selectionEnd = hastaDia;
        }, 0);
    }
}
function abrirCalendarioAntFecha(e) {
    e.preventDefault();
    var inpTexto = document.getElementById('ant_fecha');
    var inpCal = document.getElementById('ant_fecha_cal');
    if (!inpTexto || !inpCal) return;
    var v = (inpTexto.value || '').trim().split(/[\/\-\.]/);
    if (v.length === 3) {
        var d = v[0].padStart(2, '0'), m = v[1].padStart(2, '0'), a = v[2];
        if (a.length === 2) a = '20' + a;
        inpCal.value = a + '-' + m + '-' + d;
    } else {
        var hoy = new Date();
        inpCal.value = hoy.getFullYear() + '-' + String(hoy.getMonth() + 1).padStart(2, '0') + '-' + String(hoy.getDate()).padStart(2, '0');
    }
    inpTexto.style.visibility = 'hidden';
    inpCal.style.display = 'block';
    inpCal.focus();
    if (inpCal.showPicker) inpCal.showPicker();
    function cerrarCal() {
        var val = inpCal.value;
        if (val) {
            var p = val.split('-');
            inpTexto.value = p[2] + '/' + p[1] + '/' + p[0];
        }
        inpCal.style.display = 'none';
        inpTexto.style.visibility = '';
        inpTexto.focus();
        inpCal.removeEventListener('change', onCambio);
        inpCal.removeEventListener('blur', onBlur);
    }
    function onCambio() { cerrarCal(); }
    function onBlur() { setTimeout(cerrarCal, 150); }
    inpCal.addEventListener('change', onCambio);
    inpCal.addEventListener('blur', onBlur);
}

function cargarExpensa(botonEl) {
    if (!uSel) {
        alert("Seleccione un usuario primero.");
        return;
    }
    var concepto = botonEl.getAttribute('data-concepto');
    if (tipo === 'INGRESO') {
        document.getElementById("filaCarga").style.display = "table-footer-group";
        ponerFechaActual();
        document.getElementById("ins_concepto").value = "Cobro de: " + concepto;
    } else {
        tipo = 'RETIRO';
        document.getElementById("filaCarga").style.display = "table-footer-group";
        ponerFechaActual();
        document.getElementById("ins_concepto").value = concepto;
    }
    document.getElementById("ins_refer").value = "";
    document.getElementById("ins_monto").value = "";
    actualizarCheckGrabarCaja();
    setTimeout(function() {
        document.getElementById("ins_refer").focus();
    }, 0);
}


function cargarExpensaExtraordinaria() {
    if (!uSel) {
        alert("Seleccione un usuario (Consorcio) primero.");
        return;
    }
    tipo = 'RETIRO';
    var hoy = new Date();
    var mes = String(hoy.getMonth() + 1).padStart(2, '0');
    var anio = hoy.getFullYear();
    document.getElementById("filaCarga").style.display = "table-footer-group";
    ponerFechaActual();
    document.getElementById("ins_concepto").value = "Expensa extraordinaria ";
    document.getElementById("ins_compro").value = "Exp Extraordinaria";
    document.getElementById("ins_refer").value = mes + "/" + anio;
    document.getElementById("ins_monto").value = "";
    actualizarCheckGrabarCaja();
    setTimeout(function() {
        var inp = document.getElementById("ins_concepto");
        inp.focus();
        inp.setSelectionRange(inp.value.length, inp.value.length);
    }, 0);
}

function abrirResumenCtas() {
    window.open('resumen_cuentas.php', '_blank', 'width=1200,height=800');
}

function cargarSueldoExtras() {
    if (!uSel) {
        alert("Seleccioná un usuario primero.");
        return;
    }
    tipo = 'INGRESO';
    var meses = ['ENERO','FEBRERO','MARZO','ABRIL','MAYO','JUNIO','JULIO','AGOSTO','SEPTIEMBRE','OCTUBRE','NOVIEMBRE','DICIEMBRE'];
    var hoy = new Date();
    // Mes anterior
    var mesAnterior = hoy.getMonth() - 1;
    var anioAnterior = hoy.getFullYear();
    if (mesAnterior < 0) {
        mesAnterior = 11;
        anioAnterior--;
    }
    var mesAnteriorNombre = meses[mesAnterior];
    var mesRef = String(mesAnterior + 1).padStart(2, '0');
    
    document.getElementById("filaCarga").style.display = "table-footer-group";
    ponerFechaActual();
    document.getElementById("ins_concepto").value = "SUELDO/EXTRAS MES " + mesAnteriorNombre;
    document.getElementById("ins_compro").value = "SUELDO/EXTRAS";
    document.getElementById("ins_refer").value = mesRef + "/" + anioAnterior;
    document.getElementById("ins_monto").value = "";
    actualizarCheckGrabarCaja();
    setTimeout(function() {
        document.getElementById("ins_monto").focus();
    }, 0);
}

function avisarComprobanteCaja() {
    // El checkbox "Grabar en Caja" es solo indicador (no modificable por el usuario)
}

function preparar(t) { 
    tipo = t; 
    document.getElementById("filaCarga").style.display = "table-footer-group";
    document.getElementById("ins_fecha").value = "";
    ponerFechaActual();
    if (t === 'RETIRO' && esArrendadorUsuario && movSel && movSel.concepto) {
        var conceptoVal = (movSel.concepto || '').replace(/KILOS\s+DTOS PACTADOS/g, "KILOS. DTOS PACTADOS").replace(/\bKILOS\b(?!\.?\s*DTOS PACTADOS)/i, "KILOS. DTOS PACTADOS: ").trim();
        var precioRef = (movSel.ref || '').trim();
        document.getElementById("ins_concepto").value = "Pago de : " + conceptoVal + " precio Ref $" + (precioRef || '');
        document.getElementById("ins_compro").value = "PGO ARRIENDO";
        document.getElementById("ins_refer").value = String(new Date().getFullYear());
    } else if (t === 'INGRESO' && movSel && movSel.concepto) {
        var conceptoUpper = (movSel.concepto || '').toUpperCase();
        // Verificar si empieza con "VENTA DE AZUCAR" o "VENTA AZUCAR" (puede tener espacios o guiones)
        if (conceptoUpper.indexOf('VENTA DE AZUCAR') === 0 || conceptoUpper.indexOf('VENTA AZUCAR') === 0 || conceptoUpper.indexOf('VENTA DE AZÚCAR') === 0 || conceptoUpper.indexOf('VENTA AZÚCAR') === 0) {
            var conceptoNuevo = 'COBRO VTA AZUCAR';
            var ref = (movSel.ref || '').trim();
            document.getElementById("ins_concepto").value = conceptoNuevo;
            document.getElementById("ins_refer").value = ref;
            document.getElementById("ins_compro").value = "CHEQUE/ECHEQ";
        } else {
            document.getElementById("ins_concepto").value = "Cobro de: " + movSel.concepto;
            document.getElementById("ins_refer").value = "";
        }
    } else {
        document.getElementById("ins_concepto").value = "";
        document.getElementById("ins_refer").value = "";
    }
    document.getElementById("ins_monto").value = "";
    actualizarCheckGrabarCaja();
    setTimeout(function() {
        document.getElementById("ins_concepto").focus();
    }, 0);
}

function actualizarCheckGrabarCaja() {
    var chk = document.getElementById("ins_grabar_caja");
    var fila = document.getElementById("filaCheckCaja");
    if (!chk || !fila) return;
    if (uSel === 1) {
        fila.style.display = "none";
        return;
    }
    fila.style.display = "";
    var compro = (document.getElementById("ins_compro").value || "").toUpperCase();
    // BOLETA, EFVO, ALQUILER EFVO y VARIOS graban en Caja. ALQUILER TRANSF no.
    var grabarPorDefecto = (compro === "BOLETA" || compro === "EFVO" || compro === "ALQUILER EFVO" || compro === "VARIOS");
    chk.checked = grabarPorDefecto;
}

function fechaTextoAISO() {
    var v = (document.getElementById("ins_fecha").value || "").trim();
    if (!v) return "";
    var partes = v.split(/[\/\-\.]/);
    if (partes.length !== 3) return v;
    var d = partes[0].padStart(2, '0'), mes = partes[1].padStart(2, '0'), anio = partes[2];
    if (anio.length === 2) anio = '20' + anio;
    return anio + '-' + mes + '-' + d;
}

function guardar() {
    let m = parseMonto(document.getElementById("ins_monto").value);
    if(isNaN(m) || !uSel) return;
    var montoAbs = Math.abs(m);
    if (tipo === 'RETIRO' && montoAbs > saldoActualCuenta) {
        var msg = 'El monto del retiro ($ ' + montoAbs.toLocaleString('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ') es mayor que el saldo actual de la cuenta ($ ' + saldoActualCuenta.toLocaleString('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + '). ¿Desea continuar de todos modos?';
        if (!confirm(msg)) return;
    }
    var compro = (document.getElementById("ins_compro").value || "").toUpperCase();
    var grabaEnCaja = (compro === "BOLETA" || compro === "EFVO" || compro === "ALQUILER EFVO" || compro === "VARIOS");
    var grabarCaja = (uSel !== 1 && grabaEnCaja && compro !== "PGO ARRIENDO") ? 1 : 0;  /* PGO ARRIENDO nunca graba en Caja */
    let p = new URLSearchParams({ 
        id: uSel, 
        fecha: fechaTextoAISO(), 
        concepto: document.getElementById("ins_concepto").value, 
        compro: document.getElementById("ins_compro").value, 
        refer: document.getElementById("ins_refer").value, 
        monto: (tipo === 'INGRESO' ? Math.abs(m) : -Math.abs(m)),
        grabar_caja: grabarCaja
    });
    fetch('guardar_movimiento.php', { method: 'POST', body: p })
    .then(r => r.text())
    .then(txt => {
        if (txt !== 'OK' && txt !== 'OK_CAJA') {
            alert('Falta dato o corregir.');
            return;
        }
        cargarMovimientos(document.querySelector('.fila-seleccionada'), uSel);
        document.getElementById("filaCarga").style.display = "none";
        document.getElementById("ins_concepto").value = "";
        document.getElementById("ins_refer").value = "";
        document.getElementById("ins_monto").value = "";
    });
}

<?php if ($archivo_respaldo !== null && $archivo_respaldo !== false): ?>
// Descargar automáticamente el respaldo recién creado
(function() {
    var archivo = <?= json_encode($archivo_respaldo) ?>;
    var url = 'descargar_respaldo_auto.php?archivo=' + encodeURIComponent(archivo);
    var link = document.createElement('a');
    link.href = url;
    link.style.display = 'none';
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
})();
<?php endif; ?>

// Detectar parámetros desde gestión de azúcares (nuevo cobro o ir a estado de cuenta del comprador)
(function() {
    var urlParams = new URLSearchParams(window.location.search);
    var nuevoCobro = urlParams.get('nuevo_cobro');
    var usuarioId = urlParams.get('usuario_id');
    var operacion = urlParams.get('operacion');
    var referencia = urlParams.get('referencia');
    
    if (nuevoCobro === '1' && usuarioId && operacion && referencia) {
        // Nuevo cobro: seleccionar usuario y preparar formulario de ingreso
        setTimeout(function() {
            var filasUsuarios = document.querySelectorAll('#cuerpo tr[data-id]');
            for (var i = 0; i < filasUsuarios.length; i++) {
                var fila = filasUsuarios[i];
                var idFila = fila.getAttribute('data-id');
                if (idFila === usuarioId) {
                    cargarMovimientos(fila, parseInt(usuarioId));
                    setTimeout(function() {
                        preparar('INGRESO');
                        document.getElementById("ins_concepto").value = "COBRO VTA AZUCAR";
                        document.getElementById("ins_refer").value = decodeURIComponent(referencia);
                        document.getElementById("ins_compro").value = "CHEQUE/ECHEQ";
                        document.getElementById("ins_monto").value = "";
                        setTimeout(function() {
                            document.getElementById("ins_concepto").focus();
                        }, 100);
                    }, 300);
                    break;
                }
            }
        }, 500);
    } else if (usuarioId) {
        // Solo usuario_id: ir a estado de cuenta del comprador (ej. desde link en gestión azúcar)
        setTimeout(function() {
            var filasUsuarios = document.querySelectorAll('#cuerpo tr[data-id]');
            for (var i = 0; i < filasUsuarios.length; i++) {
                var fila = filasUsuarios[i];
                var idFila = fila.getAttribute('data-id');
                if (idFila === usuarioId) {
                    cargarMovimientos(fila, parseInt(usuarioId));
                    history.replaceState({}, '', 'index.php');
                    break;
                }
            }
        }, 500);
    }
})();

// Ocultar mensajes de mail después de 30 segundos
(function() {
    var mensajeEnviado = document.getElementById('mensajeMailEnviado');
    var mensajeError = document.getElementById('mensajeMailError');
    
    if (mensajeEnviado) {
        setTimeout(function() {
            mensajeEnviado.style.transition = 'opacity 0.5s';
            mensajeEnviado.style.opacity = '0';
            setTimeout(function() {
                mensajeEnviado.remove();
            }, 500);
        }, 30000); // 30 segundos
    }
    
    if (mensajeError) {
        setTimeout(function() {
            mensajeError.style.transition = 'opacity 0.5s';
            mensajeError.style.opacity = '0';
            setTimeout(function() {
                mensajeError.remove();
            }, 500);
        }, 30000); // 30 segundos
    }
})();

    function escAzucarIdx(s) {
        var d = document.createElement('div');
        d.textContent = s == null ? '' : String(s);
        return d.innerHTML;
    }
    function azucarTituloModalMovOpIndex() {
        var h = document.getElementById('modalMovimientosOperacionTitulo');
        return h ? h.innerText.replace(/\s+/g, ' ').trim() : 'Movimientos de pago';
    }
    function azucarCloneParaImprimirIndex(wrapEl) {
        var clone = wrapEl.cloneNode(true);
        clone.querySelectorAll('a').forEach(function(a) {
            a.parentNode.replaceChild(document.createTextNode(a.textContent.trim()), a);
        });
        return clone.innerHTML;
    }
    function azucarImprimirWrapIndex(wrapId, titulo) {
        var wrap = document.getElementById(wrapId);
        if (!wrap) return;
        var inner = azucarCloneParaImprimirIndex(wrap);
        var iframe = document.createElement('iframe');
        iframe.setAttribute('aria-hidden', 'true');
        iframe.style.cssText = 'position:fixed;right:0;bottom:0;width:0;height:0;border:0;visibility:hidden';
        document.body.appendChild(iframe);
        var doc = iframe.contentWindow.document;
        var css = 'body{font-family:Arial,sans-serif;padding:14px;margin:0;}h2{font-size:14px;margin:0 0 12px 0;}table{border-collapse:collapse;width:100%;font-size:11px;}th,td{border:1px solid #444;padding:5px;}th{background:#007bff;color:#fff;}';
        doc.open();
        doc.write('<!DOCTYPE html><html><head><meta charset="UTF-8"><style>' + css + '</style></head><body><h2>' + escAzucarIdx(titulo) + '</h2>' + inner + '</body></html>');
        doc.close();
        iframe.onload = function() {
            try {
                iframe.contentWindow.focus();
                iframe.contentWindow.print();
            } finally {
                setTimeout(function() {
                    if (iframe.parentNode) iframe.parentNode.removeChild(iframe);
                }, 1500);
            }
        };
    }
    function azucarWhatsappWrapIndex(wrapId, titulo) {
        var wrap = document.getElementById(wrapId);
        if (!wrap) return;
        var table = wrap.querySelector('table');
        var lines = [titulo, ''];
        if (table) {
            table.querySelectorAll('tr').forEach(function(tr) {
                var parts = [];
                tr.querySelectorAll('th, td').forEach(function(c) {
                    parts.push(c.textContent.replace(/\r?\n/g, ' ').replace(/\s+/g, ' ').trim());
                });
                lines.push(parts.join(' | '));
            });
        }
        var text = lines.join('\n');
        if (text.length > 4000) text = text.slice(0, 3990) + '…';
        window.open('https://wa.me/?text=' + encodeURIComponent(text), '_blank');
    }

    function abrirModalMovimientosOperacion(operacion) {
        if (!operacion) return;
        document.getElementById('modalOpNumero').textContent = operacion;
        document.getElementById('modalMovimientosOperacion').classList.add('visible');
        var tbody = document.getElementById('tablaMovimientosOperacion');
        tbody.innerHTML = '<tr><td colspan="7" style="text-align:center; padding:30px; color:gray;">Cargando...</td></tr>';
        fetch('obtener_movimientos_operacion.php?operacion=' + encodeURIComponent(operacion))
            .then(function(r) { return r.text(); })
            .then(function(html) { tbody.innerHTML = html; })
            .catch(function(e) { tbody.innerHTML = '<tr><td colspan="7" style="text-align:center; padding:30px; color:red;">Error al cargar</td></tr>'; });
    }
    function cerrarModalMovimientosOperacion() {
        document.getElementById('modalMovimientosOperacion').classList.remove('visible');
    }
    function abrirModalTutoriales() {
        document.getElementById('modalTutoriales').classList.add('visible');
        document.getElementById('tutorialVideoWrap').style.display = 'none';
        var lista = document.getElementById('tutorialListaWrap');
        if (lista) lista.style.display = 'flex';
        var header = document.getElementById('tutorialHeaderWrap');
        if (header) header.style.display = 'block';
        var btns = document.getElementById('tutorialBtnsWrap');
        if (btns) btns.style.display = 'block';
    }
    function cerrarModalTutoriales() {
        var v = document.getElementById('tutorialVideo');
        if (v) { v.pause(); v.src = ''; }
        document.getElementById('modalTutoriales').classList.remove('visible');
    }
    function reproducirTutorial(el) {
        var url = el && el.getAttribute('data-video');
        if (!url) {
            alert('No hay video disponible. Agregá la URL en config_tutoriales.php');
            return false;
        }
        var wrap = document.getElementById('tutorialVideoWrap');
        var v = document.getElementById('tutorialVideo');
        var aviso = document.getElementById('tutorialVideoAviso');
        var abrirLink = document.getElementById('tutorialAbrirEnlace');
        if (wrap && v) {
            var lista = document.getElementById('tutorialListaWrap');
            if (lista) lista.style.display = 'none';
            var header = document.getElementById('tutorialHeaderWrap');
            if (header) header.style.display = 'none';
            var btns = document.getElementById('tutorialBtnsWrap');
            if (btns) btns.style.display = 'none';
            v.src = url;
            wrap.style.display = 'block';
            if (aviso) aviso.style.display = 'none';
            if (abrirLink) { abrirLink.href = url; abrirLink.style.display = 'inline'; }
            v.play().catch(function() {});
            v.addEventListener('loadedmetadata', function checkVideo() {
                v.removeEventListener('loadedmetadata', checkVideo);
                if (v.videoWidth === 0 && v.duration > 0 && aviso) {
                    aviso.style.display = 'block';
                }
            }, { once: true });
        }
        return false;
    }
    function volverListaTutoriales() {
        var v = document.getElementById('tutorialVideo');
        if (v) { v.pause(); v.src = ''; }
        document.getElementById('tutorialVideoWrap').style.display = 'none';
        var lista = document.getElementById('tutorialListaWrap');
        if (lista) lista.style.display = 'flex';
        var header = document.getElementById('tutorialHeaderWrap');
        if (header) header.style.display = 'block';
        var btns = document.getElementById('tutorialBtnsWrap');
        if (btns) btns.style.display = 'block';
    }
    var divScroll = document.getElementById('scrollMovimientos');
    if (divScroll) {
        divScroll.addEventListener('scroll', function() {
            onScrollMovimientos();
        });
    }
    document.getElementById('tablaMovimientos').addEventListener('click', function(e) {
        var td = e.target.closest('.col-operacion-link');
        if (td && td.getAttribute('data-operacion')) {
            e.stopPropagation();
            e.preventDefault();
            abrirModalMovimientosOperacion(td.getAttribute('data-operacion'));
            return;
        }
        var tdFecha = e.target.closest('.celda-fecha-editable');
        if (tdFecha && !tdFecha.classList.contains('editando')) {
            e.stopPropagation();
            e.preventDefault();
            var tr = tdFecha.closest('tr');
            var movId = tr && tr.dataset ? tr.dataset.movimientoId : '';
            var fechaActual = tr && tr.dataset ? tr.dataset.fecha : '';
            if (!movId || !uSel) return;
            tdFecha.classList.add('editando');
            var inp = document.createElement('input');
            inp.type = 'date';
            inp.value = fechaActual || '';
            inp.style.cssText = 'width:100%; padding:2px; font-size:inherit; border:2px solid #007bff; box-sizing:border-box;';
            tdFecha.textContent = '';
            tdFecha.appendChild(inp);
            inp.focus();
            function guardarFecha() {
                if (!tdFecha.classList.contains('editando')) return;
                tdFecha.classList.remove('editando');
                var nuevaFecha = inp.value;
                inp.remove();
                if (!nuevaFecha) {
                    tdFecha.textContent = fechaActual ? (fechaActual.split('-').reverse().join('/')) : '';
                    return;
                }
                var fd = new FormData();
                fd.append('movimiento_id', movId);
                fd.append('fecha', nuevaFecha);
                fetch('actualizar_fecha_movimiento.php', { method: 'POST', body: fd })
                    .then(function(r) { return r.text(); })
                    .then(function(txt) {
                        if (txt.trim() === 'OK') {
                            var p = nuevaFecha.split('-');
                            tdFecha.textContent = p[2] + '/' + p[1] + '/' + p[0];
                            if (tr && tr.dataset) tr.dataset.fecha = nuevaFecha;
                            var fila = document.querySelector('#cuerpo tr.fila-seleccionada');
                            if (fila) cargarMovimientos(fila, uSel);
                        } else {
                            tdFecha.textContent = fechaActual ? (fechaActual.split('-').reverse().join('/')) : '';
                            alert(txt || 'Error al actualizar.');
                        }
                    });
            }
            inp.onblur = guardarFecha;
            inp.onkeydown = function(ev) {
                if (ev.key === 'Enter') { ev.preventDefault(); guardarFecha(); }
                if (ev.key === 'Escape') {
                    tdFecha.classList.remove('editando');
                    inp.remove();
                    tdFecha.textContent = fechaActual ? (fechaActual.split('-').reverse().join('/')) : '';
                }
            };
        }
    });
</script>
<?php include 'timeout_sesion_inc.php'; ?>
</body>
</html>