<?php
include 'db.php';
if ((int)date('j') > 10) include 'actualizar_ipc_desde_api.php';
include 'liquidar_alquileres_mes.php'; // Liquidar alquileres del mes si aún no se cargaron (desde día 1)
// Consulta para listar usuarios, poniendo a "CAJA" (ID 1) primero
$sql = "SELECT * FROM usuarios ORDER BY (id = 1) DESC, apellido ASC";
$resultado = mysqli_query($conexion, $sql);

// Consulta para detectar si falta el índice del mes actual
$mes_actual = date('Y-m-01');
$res_check = mysqli_query($conexion, "SELECT id FROM indices WHERE fecha = '$mes_actual' LIMIT 1");
$falta_indice = (mysqli_num_rows($res_check) == 0);
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
        
        /* Cabecera Central y Reloj */
        .cabecera-detalle { display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px; }
        #reloj-sistema { font-size: 1rem; color: #aaa; font-weight: 300; min-width: 150px; text-align: right; }

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
            background: #6c757d; color: white; border: none; padding: 2px 5px; 
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
        <div style="display:flex; justify-content:space-between; align-items:center;">
            <h2 style="font-size:11px; margin:0;">USUARIOS</h2>
            <a href="registro.html" style="background:#28a745; color:white; padding:2px 6px; border-radius:3px; font-size:9px; text-decoration:none; font-weight:bold;">+ NUEVO</a>
        </div>
        <input type="text" id="txtBuscador" placeholder="🔍 BUSCAR (EJ: HERR HU)..." onkeyup="filtrar()" style="width:100%; font-size:10px; margin-top:5px; padding:5px; border:1px solid #ccc; border-radius:4px; box-sizing:border-box;">
        
        <div class="scroll-usuarios">
            <table class="tabla-usuarios">
                <tbody id="cuerpo">
                    <?php while ($f = mysqli_fetch_array($resultado)) {
                        echo "<tr onclick='cargarMovimientos(this, {$f['id']})'>
                                <td>
                                    <span class='nombre-txt'>".strtoupper($f['apellido'])."</span>
                                    <button class='btn-edit-inline' onclick='event.stopPropagation(); abrirEditor({$f['id']})'>📝 EDITAR</button>
                                </td>
                              </tr>";
                    } ?>
                </tbody>
            </table>
        </div>
        
        <a href="propiedades.php" class="btn-abm-prop">⚙️ Admin. Propiedades</a>
        <a href="contrato_alquiler.php" class="btn-abm-prop btn-contrato">📜 Contrato de Alquiler</a>
        <a href="abm_indices.php" class="btn-abm-prop btn-indice">📈 ABM INDICE IPC</a>

        <?php if($falta_indice): ?>
            <div class="alerta-indice">⚠️ FALTA IPC MES ACTUAL</div>
        <?php endif; ?>
    </div>

    <div class="contenedor-grid">
        <button id="btnWord" class="btn-word-recibo" onclick="generarWord()">📄 RECIBO WORD</button>
        
        <div class="cabecera-detalle">
            <h2 id="tituloMovimientos" style="font-size:1rem; color:#007bff; margin:0;">DETALLE DE CUENTA</h2>
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
                
                <tfoot id="filaCarga" style="display:none; background:#f8f9fa;">
                    <tr>
                        <td class="al-cen" style="position:relative;">
                            <input type="text" id="ins_fecha" style="width:95%" placeholder="dd/mm/aaaa" maxlength="10" title="Formato: dd/mm/aaaa. Doble clic: calendario." onfocus="cursorAlInicioFecha()" ondblclick="abrirCalendarioFecha(event)">
                            <input type="date" id="ins_fecha_cal" style="display:none; position:absolute; left:0; top:0; width:95%; height:100%; margin:0; border:1px solid #007bff; box-sizing:border-box; font-size:inherit;">
                        </td>
                        <td><input type="text" id="ins_concepto" style="width:95%" onfocus="ponerFechaActual()"></td>
                        <td>
                            <select id="ins_compro" style="width:95%">
                                <option value="ALQUILER">ALQUILER</option>
                                <option value="EXPENSAS">EXPENSAS</option>
                                <option value="VARIOS">VARIOS</option>
                                <option value="DEBITO AUTOMATICO">DEBITO AUTOMATICO</option>
                                <option value="TARJETA">TARJETA</option>
                                <option value="DEBITO BANCO">DEBITO BANCO</option>
                                <option value="BOLETA">BOLETA</option>
                                <option value="EFVO">EFVO</option>
                            </select>
                        </td>
                        <td><input type="text" id="ins_refer" style="width:95%"></td>
                        <td><input type="number" id="ins_monto" style="width:95%; text-align:right;"></td>
                        <td colspan="2"><button onclick="guardar()" style="background:#28a745; color:white; width:100%; border:none; padding:5px; font-weight:bold; cursor:pointer;">OK</button></td>
                    </tr>
                </tfoot>
            </table>
        </div>

        <div style="display:flex; align-items:stretch; gap:10px; margin-top:15px;">
            <button id="btnIngreso" class="btn-caja" style="background:#28a745;" onclick="preparar('INGRESO')">INGRESO</button>
            <button id="btnRetiro" class="btn-caja" style="background:#dc3545;" onclick="preparar('RETIRO')">RETIRO</button>
            <div id="panelBotonesExtra" class="grid-botones-extra" style="display:none;">
                <button type="button" class="btn-caja btn-extra" style="background:#6c757d;" onclick="abrirModalCobroExp()">Cobro Exp/transferencia</button>
                <button type="button" class="btn-caja btn-extra" style="background:#6c757d;" onclick="void(0)">—</button>
                <button type="button" class="btn-caja btn-extra" style="background:#6c757d;" onclick="void(0)">—</button>
                <button type="button" class="btn-caja btn-extra" style="background:#6c757d;" onclick="void(0)">—</button>
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
                <button type="button" class="btn-exp" onclick="cargarExpensa(this)" data-concepto="MANTENIMIENTO ASCENSOR">7. MANTEN. ASCENSOR</button>
                <button type="button" class="btn-exp" onclick="cargarExpensa(this)" data-concepto="EDET (Electricidad común)">8. EDET (Electricidad)</button>
                <button type="button" class="btn-exp" onclick="cargarExpensa(this)" data-concepto="SEGURO CONTRA INCENDIO">9. SEGURO INCENDIO</button>
                <button type="button" class="btn-exp" onclick="cargarExpensa(this)" data-concepto="SAT (Agua y cloacas común)">10. SAT (Agua)</button>
                <button type="button" class="btn-exp" onclick="cargarExpensa(this)" data-concepto="RESERVA INDEMN P/DESPIDO 10%">11. RESERVA IND. 10%</button>
                <button type="button" class="btn-exp" onclick="cargarExpensa(this)" data-concepto="PROP AGUINALDO S/CARG SOC 8,33%">12. PROP. AGUINALDO 8,33%</button>
                <button type="button" class="btn-exp" onclick="cargarExpensa(this)" id="btnImprimirExpensas" data-concepto="IMPRIMIR EXPENSAS">IMPRIMIR EXPENSAS</button>
                <button type="button" class="btn-exp" onclick="cargarExpensa(this)" data-concepto="EXTRAORDINARIA">EXTRAORDINARIA</button>
                <button type="button" class="btn-exp btn-liquidar" onclick="cargarExpensa(this)" data-concepto="LIQUIDAR EXPENSAS">LIQUIDAR EXPENSAS</button>
            </div>
        </div>
    </div>

    <div id="modalCobroExp" class="modal-overlay" onclick="if(event.target===this) cerrarModalCobroExp()">
        <div class="modal-cobro" onclick="event.stopPropagation()">
            <h3>Cobro Exp/transferencia</h3>
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

<script>
let uSel = null; 
let tipo = ''; 
let movSel = null;

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
    
    document.getElementById("btnIngreso").classList.add("btn-activo");
    document.getElementById("btnRetiro").classList.add("btn-activo");
    document.getElementById("btnWord").style.display = "none";
    document.getElementById("tituloMovimientos").innerText = "MOVIMIENTOS DE: " + fila.querySelector('.nombre-txt').innerText;

    var nomUsuario = fila.querySelector('.nombre-txt').innerText.toUpperCase().trim();
    var esConsorcio = nomUsuario.indexOf("CONSORCIO") === 0;
    document.getElementById("panelConsorcio").style.display = esConsorcio ? "block" : "none";
    document.getElementById("panelBotonesExtra").style.display = esConsorcio ? "none" : "grid";  // Ocultar los 4 botones extra si es Consorcio

    fetch('obtener_movimientos.php?id=' + id)
        .then(r => r.text())
        .then(data => {
            document.getElementById("tablaMovimientos").innerHTML = data;
            document.getElementById("divScroll").scrollTop = document.getElementById("divScroll").scrollHeight;
        });
}

function abrirEditor(id) {
    if (prompt("CLAVE DE SEGURIDAD PARA EDITAR USUARIO:") === "4961") {
        window.location.href = 'editar_usuario.php?id=' + id;
    } else { alert("Clave incorrecta."); }
}

function abrirModalCobroExp() {
    if (!uSel) { alert('Seleccioná un usuario primero.'); return; }
    document.getElementById('modalCobroExp').classList.add('visible');
    document.getElementById('cobroPropiedad').innerHTML = '<option value="">-- Cargando... --</option>';
    document.getElementById('cobroPeriodo').value = String(new Date().getMonth() + 1).padStart(2,'0') + '/' + new Date().getFullYear();
    document.getElementById('cobroMonto').value = '';
    fetch('obtener_propiedades_propietario.php?id=' + uSel)
        .then(r => r.json())
        .then(props => {
            var sel = document.getElementById('cobroPropiedad');
            sel.innerHTML = '<option value="">-- Elegir propiedad --</option>';
            props.forEach(function(p) {
                var opt = document.createElement('option');
                opt.value = p.propiedad_id;
                opt.textContent = p.propiedad + (p.consorcio ? ' (' + p.consorcio + ')' : '');
                sel.appendChild(opt);
            });
            if (props.length === 0) sel.innerHTML = '<option value="">-- Sin propiedades --</option>';
        });
}

function cerrarModalCobroExp() {
    document.getElementById('modalCobroExp').classList.remove('visible');
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

function seleccionarFila(el, fecha, concepto, compro, ref, monto) {
    document.querySelectorAll('.fila-mov').forEach(f => f.classList.remove('fila-mov-seleccionada'));
    el.classList.add('fila-mov-seleccionada');
    movSel = { fecha, concepto, compro, ref, monto, usuario: document.querySelector('.fila-seleccionada .nombre-txt').innerText };
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
    if(!movSel) return;
    const html = `<html><body style="font-family:Arial;"><div style="border:4px double #000; padding:20px; width:550px; margin:auto;">
        <h2 style="text-align:center;">RECIBO DE CAJA</h2><hr>
        <p><strong>FECHA:</strong> ${movSel.fecha}</p>
        <p><strong>CLIENTE:</strong> ${movSel.usuario}</p>
        <p><strong>CONCEPTO:</strong> ${movSel.concepto}</p>
        <p><strong>DETALLE:</strong> ${movSel.compro} / ${movSel.ref}</p>
        <h3 style="text-align:right; border-top:1px solid #eee; padding-top:10px;">TOTAL: $ ${movSel.monto}</h3>
        <br><br><br><p style="text-align:center;">___________________________<br>Firma Autorizada</p>
    </div></body></html>`;
    const blob = new Blob(['\ufeff', html], { type: 'application/msword' });
    const link = document.createElement('a');
    link.href = URL.createObjectURL(blob);
    link.download = 'Recibo_' + movSel.usuario.trim().replace(/\s+/g, '_') + '.doc';
    link.click();
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
    .then(() => {
        cargarMovimientos(document.querySelector('.fila-seleccionada'), uSel);
        document.getElementById("filaCarga").style.display = "none";
        document.getElementById("ins_concepto").value = "";
        document.getElementById("ins_refer").value = "";
        document.getElementById("ins_monto").value = "";
    });
}
</script>
</body>
</html>