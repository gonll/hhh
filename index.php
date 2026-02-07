<?php
include 'db.php';
include 'verificar_sesion.php';
include 'respaldar_automatico.php'; // Respaldo automático diario
$archivo_respaldo = hacerRespaldoAutomatico(); // Ejecutar respaldo si no se hizo hoy (retorna nombre de archivo si se hizo nuevo)
if ((int)date('j') > 10) include 'actualizar_ipc_desde_api.php';
include 'liquidar_alquileres_mes.php'; // Liquidar alquileres del mes si aún no se cargaron (desde día 1)
// Consulta para listar usuarios, poniendo a "CAJA" (ID 1) primero
$sql = "SELECT * FROM usuarios ORDER BY (id = 1) DESC, apellido ASC";
$resultado = mysqli_query($conexion, $sql);

// Consulta para detectar si falta el índice del mes actual
$mes_actual = date('Y-m-01');
$res_check = mysqli_query($conexion, "SELECT id FROM indices WHERE fecha = '$mes_actual' LIMIT 1");
$falta_indice = (mysqli_num_rows($res_check) == 0);
$nivelAcceso = (int)($_SESSION['acceso_nivel'] ?? 0);
$soloLectura = ($nivelAcceso < 2);
// Nivel 0: solo puede Partes desde cel
if ($nivelAcceso === 0) {
    header('Location: partes_desde_cel.php');
    exit;
}
// Usuarios para modal Ant/cel (nivel 3): todos excepto CAJA (id 1)
$usuarios_anticipo = [];
if ($nivelAcceso === 3) {
    $r_ant = mysqli_query($conexion, "SELECT id, apellido FROM usuarios WHERE id != 1 ORDER BY apellido ASC");
    if ($r_ant) {
        while ($u = mysqli_fetch_assoc($r_ant)) {
            $usuarios_anticipo[] = $u;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Sistema HHH 2026</title>
    <style>
        body { font-family: 'Segoe UI', sans-serif; background: #f0f2f5; margin: 0; padding: 10px; display: flex; align-items: flex-start; }
        
        /* Contenedor Lateral Izquierdo */
        .contenedor-usuarios { 
            background: white; padding: 10px; border-radius: 8px; 
            box-shadow: 0 2px 10px rgba(0,0,0,0.1); width: 25vw; margin-right: 1vw; 
            height: 90vh; display: flex; flex-direction: column; font-size: 10px; 
        }

        /* Panel Central de Movimientos */
        .contenedor-grid { 
            background: white; padding: 15px; border-radius: 8px; 
            box-shadow: 0 2px 10px rgba(0,0,0,0.1); width: 71vw; height: 90vh; position: relative; 
        }

        /* Botón Word - Posicionado a la izquierda del reloj */
        .btn-word-recibo { 
            position: absolute; top: 15px; right: 180px; 
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
        .btn-indice { background: #f39c12; }
        .btn-admin-prop { background: #17a2b8; }
        
        /* Cabecera Central y Reloj */
        .cabecera-detalle { display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px; }
        #reloj-sistema { font-size: 1rem; color: #aaa; font-weight: 300; min-width: 150px; text-align: right; }
        .btn-ant-cel { background: #17a2b8; color: white; border: none; padding: 6px 12px; border-radius: 4px; font-size: 11px; font-weight: bold; cursor: pointer; }
        .btn-ant-cel:hover { background: #138496; }
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
        .scroll-grid { height: 75%; overflow-y: auto; border: 1px solid #ddd; margin-top: 5px; background: #fff; }

        .tabla-datos { width: 100%; border-collapse: collapse; font-size: 12px; table-layout: fixed; }
        .tabla-datos th { background: #007bff; color: white; padding: 8px 5px; position: sticky; top: 0; z-index: 10; font-weight: bold; }
        
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
        #ins_monto::-webkit-inner-spin-button,
        #ins_monto::-webkit-outer-spin-button {
            -webkit-appearance: none;
            margin: 0;
        }
        #ins_monto { -moz-appearance: textfield; }
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
    </style>
</head>
<body>

    <div class="contenedor-usuarios">
        <?php if (isset($_GET['msg']) && ($_GET['msg'] === 'solo_lectura' || $_GET['msg'] === 'sin_permiso')): ?>
            <p style="font-size:10px; color:#856404; background:#fff3cd; padding:6px; border-radius:4px; margin:0 0 6px;">Su nivel solo permite consulta (sin altas, bajas ni modificaciones).</p>
        <?php endif; ?>
        <?php if (isset($_GET['mail_enviado']) && $_GET['mail_enviado'] == '1'): ?>
            <p id="mensajeMailEnviado" style="font-size:10px; color:#155724; background:#d4edda; padding:6px; border-radius:4px; margin:0 0 6px;">✓ Mail enviado correctamente a Herrera Hugo.</p>
        <?php endif; ?>
        <?php if (isset($_GET['mail_error'])): ?>
            <p id="mensajeMailError" style="font-size:10px; color:#721c24; background:#f8d7da; padding:6px; border-radius:4px; margin:0 0 6px;">✗ Error al enviar mail: <?= htmlspecialchars($_GET['mail_error']) ?></p>
        <?php endif; ?>
        <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:4px; margin-bottom:4px;">
            <span style="font-size:9px; color:#666;"><?= htmlspecialchars($_SESSION['acceso_usuario'] ?? '') ?> (nivel <?= (int)($_SESSION['acceso_nivel'] ?? 0) ?>)</span>
            <div>
                <?php if (isset($_SESSION['acceso_nivel']) && $_SESSION['acceso_nivel'] >= 3): ?>
                    <a href="gestionar_accesos.php" style="color:#007bff; font-size:9px; margin-right:6px;">Accesos</a>
                    <a href="respaldar_bd.php" style="color:#28a745; font-size:9px; margin-right:6px;">Respaldar</a>
                <?php endif; ?>
                <a href="logout.php" style="color:#dc3545; font-size:9px;">Salir</a>
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
                        echo "<tr onclick='cargarMovimientos(this, {$f['id']})'>
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
                <a href="gestionar_finca.php" class="btn-abm-prop" style="flex: 1;">Finca</a>
                <a href="gestionar_azucares.php" class="btn-abm-prop" style="flex: 1;">Azucar</a>
                <a href="#" class="btn-abm-prop" onclick="return false;" style="flex: 1;">Arriendos</a>
            </div>
            <a href="propiedades.php" class="btn-abm-prop btn-admin-prop">⚙️ Admin. Propiedades</a>
            <a href="contrato_alquiler.php" class="btn-abm-prop btn-contrato">📜 Contrato de Alquiler</a>
            <a href="abm_indices.php" class="btn-abm-prop btn-indice">📈 ABM INDICE IPC</a>
        <?php endif; ?>

        <?php if($falta_indice): ?>
            <div class="alerta-indice">⚠️ FALTA IPC MES ACTUAL</div>
        <?php endif; ?>
    </div>

    <div class="contenedor-grid">
        <button id="btnWord" class="btn-word-recibo" onclick="generarWord()">📄 RECIBO WORD</button>
        
        <div class="cabecera-detalle">
            <div style="display:flex; align-items:center; gap:10px;">
                <h2 id="tituloMovimientos" style="font-size:1rem; color:#007bff; margin:0;">DETALLE DE CUENTA</h2>
                <?php if ($nivelAcceso === 3): ?>
                <button type="button" class="btn-ant-cel" onclick="abrirModalAntCel()">Ant/cel</button>
                <?php endif; ?>
            </div>
            <div id="reloj-sistema"></div>
        </div>
        
        <div class="scroll-grid" id="divScroll">
            <table class="tabla-datos">
                <thead>
                    <tr>
                        <th style="width:12%;" class="al-cen">FECHA</th>
                        <th style="width:36%;" class="al-izq">CONCEPTO</th>
                        <th style="width:14%;" class="al-cen">COMPROB</th>
                        <th style="width:10%;" class="al-cen">REF</th>
                        <th style="width:13%;" class="al-der">MONTO</th>
                        <th style="width:13%;" class="al-der">SALDO</th>
                        <th style="width:40px;" class="al-cen">X</th>
                    </tr>
                </thead>
                <tbody id="tablaMovimientos">
                    <tr><td colspan="7" style="padding:50px; text-align:center; color:gray;">SELECCIONE UN USUARIO</td></tr>
                </tbody>
                
                <?php if (!$soloLectura): ?>
                <tfoot id="filaCarga" style="display:none; background:#f8f9fa;">
                    <tr>
                        <td class="al-cen" style="position:relative;">
                            <input type="text" id="ins_fecha" style="width:95%" placeholder="dd/mm/aaaa" maxlength="10" title="Formato: dd/mm/aaaa. Doble clic: calendario." onfocus="cursorAlInicioFecha()" ondblclick="abrirCalendarioFecha(event)">
                            <input type="date" id="ins_fecha_cal" style="display:none; position:absolute; left:0; top:0; width:95%; height:100%; margin:0; border:1px solid #007bff; box-sizing:border-box; font-size:inherit;">
                        </td>
                        <td><input type="text" id="ins_concepto" style="width:95%" onfocus="ponerFechaActual()"></td>
                        <td>
                            <select id="ins_compro" style="width:95%" onchange="avisarComprobanteCaja()">
                                <option value="ALQUILER">ALQUILER</option>
                                <option value="EXPENSAS">EXPENSAS</option>
                                <option value="VARIOS">VARIOS</option>
                                <option value="DEBITO AUTOMATICO">DEBITO AUTOMATICO</option>
                                <option value="TARJETA">TARJETA</option>
                                <option value="DEBITO BANCO">DEBITO BANCO</option>
                                <option value="BOLETA">BOLETA</option>
                                <option value="TRANSFERENCIA">TRANSFERENCIA</option>
                                <option value="EFVO">EFVO</option>
                                <option value="SUELDO/EXTRAS">SUELDO/EXTRAS</option>
                                <option value="Exp Extraordinaria">Exp Extraordinaria</option>
                            </select>
                        </td>
                        <td><input type="text" id="ins_refer" style="width:95%"></td>
                        <td><input type="number" id="ins_monto" style="width:95%; text-align:right;"></td>
                        <td colspan="2"><button onclick="guardar()" style="background:#28a745; color:white; width:100%; border:none; padding:5px; font-weight:bold; cursor:pointer;">OK</button></td>
                    </tr>
                </tfoot>
                <?php endif; ?>
            </table>
        </div>

        <?php if (!$soloLectura): ?>
        <div style="display:flex; align-items:stretch; gap:10px; margin-top:15px; flex-wrap:wrap;">
            <button id="btnIngreso" class="btn-caja" style="background:#28a745;" onclick="preparar('INGRESO')">INGRESO</button>
            <button id="btnRetiro" class="btn-caja" style="background:#dc3545;" onclick="preparar('RETIRO')">RETIRO</button>
            <button type="button" id="btnResumenCtas" class="btn-caja btn-activo" style="background:#fff3cd; color:#856404; border:1px solid #ffeeba; display:none;" onclick="abrirResumenCtas()">Resumen de Ctas.</button>
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
                    <button type="button" id="btnCobroExpEfvo" class="btn-caja btn-extra" style="background:#fff3cd; color:#856404; border:1px solid #ffeeba; display:none;" onclick="abrirModalCobroExp(true)">Cobro expensas efvo</button>
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

    <div id="modalCobroExp" class="modal-overlay" onclick="if(event.target===this) cerrarModalCobroExp()">
        <div class="modal-cobro" onclick="event.stopPropagation()">
            <h3 id="modalCobroExpTitulo">Cobro Exp/transferencia</h3>
            <p id="cobroUsuarioNombre" style="margin:0 0 12px; font-weight:bold; color:#333; font-size:13px;"></p>
            <label>Propiedad</label>
            <select id="cobroPropiedad" required>
                <option value="">-- Cargando... --</option>
            </select>
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
        <div class="modal-cobro" onclick="event.stopPropagation()">
            <h3>Liquidar expensas</h3>
            <p id="liqExpConsorcioNombre" style="margin:0 0 12px; font-weight:bold; color:#333; font-size:13px;"></p>
            <label>Mes a liquidar (MM/AAAA)</label>
            <input type="text" id="liqExpMes" placeholder="Ej: 01/2025" maxlength="7">
            <p style="font-size:10px; color:#666; margin:8px 0 0;">Suma retiros/gastos desde la última LIQ EXP hasta fin de mes y reparte por % a propietarios.</p>
            <div class="btns">
                <button type="button" class="btn-guardar" onclick="ejecutarLiquidarExpensas()">Liquidar</button>
                <button type="button" class="btn-cerrar" onclick="cerrarModalLiqExp()">Cerrar</button>
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

<script>
let uSel = null; 
let tipo = ''; 
let movSel = null;
let esConsorcioUsuario = false;

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
    document.getElementById("tituloMovimientos").innerText = "MOVIMIENTOS DE: " + fila.querySelector('.nombre-txt').innerText;

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
    if (resumenLinea) resumenLinea.style.display = esConsorcioUsuario ? "flex" : "none";
    if (btnResumenCtas) btnResumenCtas.style.display = esCajaUsuario ? "block" : "none";
    // Mostrar botón Word si es consorcio (aunque no haya movimiento seleccionado)
    if (esConsorcioUsuario) {
        document.getElementById("btnWord").style.display = "block";
    }
    
    // Cobro Exp/transferencia y Cobro expensas efvo: solo si es propietario o inquilino. Sueldo/Extras: solo si NO es propietario ni inquilino (y no Caja).
    if (esCajaUsuario) {
        if (btnCobroExpTransf) btnCobroExpTransf.style.display = "none";
        if (btnCobroExpEfvo) btnCobroExpEfvo.style.display = "none";
        if (btnSueldoExtras) btnSueldoExtras.style.display = "none";
    } else {
        fetch('obtener_propiedades_propietario.php?id=' + id)
            .then(function(r) { return r.json(); })
            .then(function(props) {
                var esPropOInq = props && props.length > 0;
                if (btnCobroExpTransf) btnCobroExpTransf.style.display = esPropOInq ? "block" : "none";
                if (btnCobroExpEfvo) btnCobroExpEfvo.style.display = esPropOInq ? "block" : "none";
                if (btnSueldoExtras) btnSueldoExtras.style.display = esPropOInq ? "none" : "block";
            })
            .catch(function() {
                if (btnCobroExpTransf) btnCobroExpTransf.style.display = "none";
                if (btnCobroExpEfvo) btnCobroExpEfvo.style.display = "none";
                if (btnSueldoExtras) btnSueldoExtras.style.display = "block";
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

    fetch('obtener_movimientos.php?id=' + id)
        .then(r => r.text())
        .then(data => {
            document.getElementById("tablaMovimientos").innerHTML = data;
            document.getElementById("divScroll").scrollTop = document.getElementById("divScroll").scrollHeight;
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
        });
}

function abrirEditor(id) {
    var esNivel3 = <?= (isset($_SESSION['acceso_nivel']) && $_SESSION['acceso_nivel'] >= 3) ? 'true' : 'false' ?>;
    if (esNivel3) {
        window.location.href = 'editar_usuario.php?id=' + id;
    } else if (prompt("CLAVE DE SEGURIDAD PARA EDITAR USUARIO:") === "4961") {
        window.location.href = 'editar_usuario.php?id=' + id;
    } else {
        alert("Clave incorrecta.");
    }
}

var cobroExpEsEfvo = false;
function abrirModalCobroExp(esEfvo) {
    cobroExpEsEfvo = !!esEfvo;
    document.getElementById('modalCobroExpTitulo').textContent = cobroExpEsEfvo ? 'Cobro expensas efvo' : 'Cobro Exp/transferencia';
    if (!uSel) { alert('Seleccioná un usuario primero.'); return; }
    var fila = document.querySelector('#cuerpo tr.fila-seleccionada');
    var nomUsu = fila ? fila.querySelector('.nombre-txt').innerText : '';
    document.getElementById('cobroUsuarioNombre').textContent = 'Usuario: ' + nomUsu;
    document.getElementById('modalCobroExp').classList.add('visible');
    document.getElementById('cobroPropiedad').innerHTML = '<option value="">-- Cargando... --</option>';

    var movFila = document.querySelector('#tablaMovimientos tr.fila-mov-seleccionada');
    var periodoDefault = String(new Date().getMonth() + 1).padStart(2,'0') + '/' + new Date().getFullYear();
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
    document.getElementById('liqExpMes').value = String(new Date().getMonth() + 1).padStart(2, '0') + '/' + new Date().getFullYear();
    document.getElementById('modalLiqExp').classList.add('visible');
    setTimeout(function() { document.getElementById('liqExpMes').focus(); }, 100);
}

function cerrarModalLiqExp() {
    document.getElementById('modalLiqExp').classList.remove('visible');
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
                msgEl.textContent = 'Error: ' + txt;
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
    window.open('imprimir_expensas_consorcio.php?id=' + uSel, '_blank', 'width=900,height=700');
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
            if (txt.trim() === 'OK') {
                cerrarModalLiqExp();
                var fila = document.querySelector('#cuerpo tr.fila-seleccionada');
                if (fila) cargarMovimientos(fila, uSel);
                alert('Liquidación de expensas guardada correctamente.');
            } else {
                alert('Error: ' + txt);
            }
        });
}

function guardarCobroExp() {
    var propId = document.getElementById('cobroPropiedad').value;
    var periodo = document.getElementById('cobroPeriodo').value.trim();
    var monto = document.getElementById('cobroMonto').value.replace(',', '.').trim();
    if (!propId || !periodo || !monto) {
        alert('Completá propiedad, período y monto.');
        return;
    }
    if (isNaN(parseFloat(monto)) || parseFloat(monto) <= 0) {
        alert('El monto debe ser un número mayor a 0.');
        return;
    }
    var fd = new FormData();
    fd.append('usuario_id', uSel);
    fd.append('propiedad_id', propId);
    fd.append('periodo', periodo);
    fd.append('monto', monto);
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
                alert('Error: ' + txt);
            }
        });
}

function seleccionarFila(el, movimientoId, fecha, concepto, compro, ref, monto) {
    document.querySelectorAll('.fila-mov').forEach(f => f.classList.remove('fila-mov-seleccionada'));
    el.classList.add('fila-mov-seleccionada');
    movSel = { movimientoId, fecha, concepto, compro, ref, monto, usuario: document.querySelector('.fila-seleccionada .nombre-txt').innerText };
    document.getElementById("btnWord").style.display = "block";
}

function eliminarMovSeguro(movId) {
    if (prompt("CLAVE DE SEGURIDAD PARA ELIMINAR:") === "4961") {
        if (confirm("¿Eliminar este movimiento permanentemente?")) {
            fetch('eliminar_movimiento.php?mid=' + movId)
                .then(r => r.text())
                .then(res => {
                    if (res.trim() === "OK") cargarMovimientos(document.querySelector('.fila-seleccionada'), uSel);
                });
        }
    } else { alert("Clave incorrecta."); }
}

function generarWord() {
    if (esConsorcioUsuario && uSel) {
        // Si es consorcio, mostrar interfaz de impresión de expensas
        window.open('imprimir_expensas_consorcio.php?id=' + uSel, '_blank', 'width=900,height=700');
    } else if (!movSel || !movSel.movimientoId) {
        return;
    } else {
        // Si no es consorcio, generar recibo normal
        window.open('generar_recibo_word.php?id=' + movSel.movimientoId, '_blank', 'noopener');
    }
}

// Orden de campos al cargar movimiento (Enter pasa al siguiente)
const CAMPOS_MOV = ['ins_fecha', 'ins_concepto', 'ins_compro', 'ins_refer', 'ins_monto'];

document.addEventListener('keydown', function(e) {
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
    tipo = 'RETIRO';
    var concepto = botonEl.getAttribute('data-concepto');
    document.getElementById("filaCarga").style.display = "table-footer-group";
    ponerFechaActual();
    document.getElementById("ins_concepto").value = concepto;
    document.getElementById("ins_refer").value = "";
    document.getElementById("ins_monto").value = "";
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
    setTimeout(function() {
        document.getElementById("ins_monto").focus();
    }, 0);
}

function avisarComprobanteCaja() {
    if (!esConsorcioUsuario) return;
    var compro = document.getElementById("ins_compro").value;
    if (compro === "BOLETA" || compro === "EFVO") {
        alert("⚠️ Con BOLETA o EFVO el movimiento se grabará también en Caja (efectivo).\n\nSi no es correcto, cambiá el comprobante antes de guardar.");
    }
}

function preparar(t) { 
    tipo = t; 
    document.getElementById("filaCarga").style.display = "table-footer-group";
    ponerFechaActual();
    setTimeout(function() {
        document.getElementById("ins_concepto").focus();
    }, 0);
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
    let m = document.getElementById("ins_monto").value;
    if(!m || !uSel) return;
    let p = new URLSearchParams({ 
        id: uSel, 
        fecha: fechaTextoAISO(), 
        concepto: document.getElementById("ins_concepto").value, 
        compro: document.getElementById("ins_compro").value, 
        refer: document.getElementById("ins_refer").value, 
        monto: (tipo === 'INGRESO' ? Math.abs(m) : -Math.abs(m)) 
    });
    fetch('guardar_movimiento.php', { method: 'POST', body: p })
    .then(r => r.text())
    .then(txt => {
        if (txt !== 'OK' && txt !== 'OK_CAJA') {
            alert('Error: ' + txt);
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
</script>
<?php include 'timeout_sesion_inc.php'; ?>
</body>
</html>