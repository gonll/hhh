<?php
include 'db.php';

// Consulta para obtener solo propiedades desocupadas
$sql_prop = "SELECT propiedad_id, propiedad, consorcio, padron, detalle FROM propiedades WHERE alquiler = 0 OR alquiler IS NULL ORDER BY propiedad ASC";
$res_prop = mysqli_query($conexion, $sql_prop);

// Fechas iniciales por defecto (24 meses)
$fecha_inicio_defecto = date('Y-m-d');
$fecha_fin_objeto = new DateTime($fecha_inicio_defecto);
$fecha_fin_objeto->modify('+24 months');
$fecha_fin_defecto = $fecha_fin_objeto->format('Y-m-t'); 
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Nuevo Contrato - HHH</title>
    <style>
        body { font-family: 'Segoe UI', sans-serif; background: #f0f2f5; padding: 10px; margin: 0; }
        .caja { background: white; padding: 12px; border-radius: 8px; width: 750px; margin: auto; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h3 { margin-top: 0; color: #007bff; border-bottom: 2px solid #007bff; padding-bottom: 5px; font-size: 12px; text-transform: uppercase; }
        .grid-form { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; }
        .campo-completo { grid-column: span 2; }
        label { display: block; font-size: 9px; font-weight: bold; color: #666; margin-bottom: 1px; text-transform: uppercase; }
        input, select { padding: 5px; border: 1px solid #ccc; border-radius: 4px; font-size: 10.5px; width: 100%; box-sizing: border-box; text-transform: uppercase; height: 28px; }
        input:focus { border-color: #007bff; outline: none; background: #fdfdfd; }
        .buscador-contenedor { position: relative; }
        .sugerencias { position: absolute; width: 100%; background: white; border: 1px solid #ddd; z-index: 100; max-height: 100px; overflow-y: auto; display: none; }
        .sugerencia-item { padding: 5px; cursor: pointer; font-size: 10px; border-bottom: 1px solid #eee; }
        .btn-accion { background: #28a745; color: white; border: none; padding: 8px; cursor: pointer; font-weight: bold; border-radius: 4px; width: 100%; text-transform: uppercase; margin-top: 10px; font-size: 11px; }
        .volver { text-decoration: none; color: #007bff; font-weight: bold; font-size: 10px; }
    </style>
</head>
<body>

<div class="caja">
    <h3>Generar Contrato de Alquiler</h3>
    <form id="formContrato" autocomplete="off">
        <div class="grid-form">
            
            <div class="campo-completo">
                <label>Propiedad (Desocupadas)</label>
                <select id="propiedad_id" class="nav-enter" required onchange="actualizarInfoProp()">
                    <option value="">-- SELECCIONE PROPIEDAD --</option>
                    <?php while($p = mysqli_fetch_assoc($res_prop)): ?>
                        <option value="<?= $p['propiedad_id'] ?>" 
                                data-padron="<?= $p['padron'] ?>" 
                                data-detalle="<?= $p['detalle'] ?>">
                            <?= $p['propiedad'] ?> (<?= $p['consorcio'] ?>)
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>

            <div class="buscador-contenedor">
                <label>Inquilino 1 *</label>
                <input type="text" id="bus_inq1" class="nav-enter" onkeyup="buscar(this.value, 'sug_inq1', 'inq1_id')" placeholder="BUSCAR..." required>
                <input type="hidden" id="inq1_id">
                <div id="sug_inq1" class="sugerencias"></div>
            </div>
            <div class="buscador-contenedor">
                <label>Inquilino 2 (Opcional)</label>
                <input type="text" id="bus_inq2" class="nav-enter" onkeyup="buscar(this.value, 'sug_inq2', 'inq2_id')" placeholder="BUSCAR...">
                <input type="hidden" id="inq2_id">
                <div id="sug_inq2" class="sugerencias"></div>
            </div>

            <div class="buscador-contenedor">
                <label>Codeudor 1 *</label>
                <input type="text" id="bus_cod1" class="nav-enter" onkeyup="buscar(this.value, 'sug_cod1', 'cod1_id')" placeholder="BUSCAR..." required>
                <input type="hidden" id="cod1_id">
                <div id="sug_cod1" class="sugerencias"></div>
            </div>
            <div class="buscador-contenedor">
                <label>Codeudor 2 (Opcional)</label>
                <input type="text" id="bus_cod2" class="nav-enter" onkeyup="buscar(this.value, 'sug_cod2', 'cod2_id')" placeholder="BUSCAR...">
                <input type="hidden" id="cod2_id">
                <div id="sug_cod2" class="sugerencias"></div>
            </div>

            <div>
                <label>Plazo (Meses)</label>
                <input type="number" id="plazo" class="nav-enter" value="24" required onchange="recalcularFechaFin()">
            </div>
            <div>
                <label>Destino</label>
                <select id="destino" class="nav-enter">
                    <option value="VIVIENDA">VIVIENDA</option>
                    <option value="CONSULTORIO">CONSULTORIO</option>
                </select>
            </div>

            <div>
                <label>Fecha Inicio</label>
                <input type="date" id="fecha_inicio" class="nav-enter" value="<?= $fecha_inicio_defecto ?>" required onchange="recalcularFechaFin()">
            </div>
            <div>
                <label>Fecha Fin</label>
                <input type="date" id="fecha_fin" class="nav-enter" value="<?= $fecha_fin_defecto ?>" required>
            </div>

            <div>
                <label>Precio Convenido ($)</label>
                <input type="number" id="precio" class="nav-enter" placeholder="0.00" oninput="copiarMonto(this.value)" required>
            </div>
            <div>
                <label>Fecha Firma</label>
                <input type="date" id="fecha_firma" class="nav-enter" value="<?= date('Y-m-d') ?>" required>
            </div>

            <div>
                <label>Monto Depósito Garantía ($)</label>
                <input type="number" id="deposito" class="nav-enter" placeholder="0.00" required>
            </div>
        </div>

        <button type="button" id="btnHacerContrato" class="btn-accion" onclick="validarYEnviar()">Hacer Contrato</button>
    </form>
    <div style="margin-top:10px;"><a href="index.php" class="volver">← VOLVER AL PANEL</a></div>
</div>

<script>
let infoProp = { padron: '', detalle: '' };

// FUNCIÓN CLAVE: Recalcula la fecha fin sumando los meses y buscando el último día
function recalcularFechaFin() {
    const fInicioVal = document.getElementById('fecha_inicio').value;
    const meses = parseInt(document.getElementById('plazo').value);
    
    if (fInicioVal && !isNaN(meses)) {
        let fecha = new Date(fInicioVal + "T00:00:00");
        // Sumar meses
        fecha.setMonth(fecha.getMonth() + meses);
        // Obtener el último día de ese mes resultante
        let ultimoDia = new Date(fecha.getFullYear(), fecha.getMonth() + 1, 0).getDate();
        fecha.setDate(ultimoDia);
        
        // Formatear a YYYY-MM-DD para el input date
        let y = fecha.getFullYear();
        let m = (fecha.getMonth() + 1).toString().padStart(2, '0');
        let d = fecha.getDate().toString().padStart(2, '0');
        
        document.getElementById('fecha_fin').value = `${y}-${m}-${d}`;
    }
}

// Navegación por ENTER
document.addEventListener('keydown', function(e) {
    if (e.key === 'Enter') {
        const active = document.activeElement;
        if (active.classList.contains('nav-enter')) {
            e.preventDefault();
            if (active.required && !active.value.trim()) {
                active.style.backgroundColor = '#ffeeee';
                setTimeout(() => active.style.backgroundColor = '', 500);
                return;
            }
            const inputs = Array.from(document.querySelectorAll('.nav-enter'));
            const index = inputs.indexOf(active);
            if (index > -1 && index < inputs.length - 1) {
                inputs[index + 1].focus();
            } else if (index === inputs.length - 1) {
                validarYEnviar();
            }
        }
    }
});

function actualizarInfoProp() {
    const sel = document.getElementById('propiedad_id');
    const opt = sel.options[sel.selectedIndex];
    infoProp.padron = opt.getAttribute('data-padron') || '';
    infoProp.detalle = opt.getAttribute('data-detalle') || '';
}

function copiarMonto(v) { document.getElementById('deposito').value = v; }

function buscar(q, listaId, hiddenId) {
    const lista = document.getElementById(listaId);
    if (q.length < 2) { lista.style.display = 'none'; return; }
    fetch('buscar_personas.php?q=' + q).then(r => r.json()).then(data => {
        lista.innerHTML = '';
        if (data.length > 0) {
            lista.style.display = 'block';
            data.forEach(p => {
                const div = document.createElement('div');
                div.className = 'sugerencia-item';
                div.innerText = p.apellido;
                div.onclick = () => {
                    document.getElementById(hiddenId).value = p.id;
                    document.getElementById(listaId.replace('sug','bus')).value = p.apellido;
                    lista.style.display = 'none';
                };
                lista.appendChild(div);
            });
        }
    });
}

function validarYEnviar() {
    const d = {
        propiedad_id: document.getElementById('propiedad_id').value,
        nom_prop: document.getElementById('propiedad_id').options[document.getElementById('propiedad_id').selectedIndex].text,
        inq1_id: document.getElementById('inq1_id').value,
        nom_inq: document.getElementById('bus_inq1').value,
        inq2_id: document.getElementById('inq2_id').value,
        cod1_id: document.getElementById('cod1_id').value,
        nom_cod: document.getElementById('bus_cod1').value,
        cod2_id: document.getElementById('cod2_id').value,
        plazo: document.getElementById('plazo').value,
        destino: document.getElementById('destino').value,
        fecha_inicio: document.getElementById('fecha_inicio').value,
        fecha_fin: document.getElementById('fecha_fin').value,
        precio: document.getElementById('precio').value,
        fecha_firma: document.getElementById('fecha_firma').value,
        deposito: document.getElementById('deposito').value,
        padron: infoProp.padron,
        detalle: infoProp.detalle
    };

    if (!d.propiedad_id || !d.inq1_id || !d.cod1_id || !d.precio) {
        alert("COMPLETE CAMPOS OBLIGATORIOS"); return;
    }

    fetch('guardar_contrato.php', { method: 'POST', body: new URLSearchParams(d) })
    .then(r => r.text()).then(res => {
        if (res.trim() === "OK") {
            alert("CONTRATO GUARDADO");
            generarContratoWord(d);
            window.location.href = "index.php";
        } else { alert("Error: " + res); }
    });
}

function generarContratoWord(d) {
    // Aquí va la lógica de generación de Word que ya definimos con las cláusulas del documento [cite: 5, 9, 15, 57]
    const contenido = `<html><body style="font-family:Arial; text-align:justify;">
        <h2 style="text-align:center;">CONTRATO DE LOCACIÓN</h2>
        <p><b>PROPIETARIO:</b> HERRERA Y LLOBETA SRL [cite: 2]</p>
        <p><b>INQUILINO:</b> ${d.nom_inq}</p>
        <p><b>OBJETO:</b> ${d.nom_prop}, Padrón ${d.padron} [cite: 5]</p>
        <p><b>PLAZO:</b> ${d.plazo} meses desde ${d.fecha_inicio} al ${d.fecha_fin} [cite: 9]</p>
        <p><b>PRECIO:</b> $ ${d.precio} [cite: 15]</p>
        <p><b>DEPÓSITO:</b> $ ${d.deposito} [cite: 57]</p>
    </body></html>`;
    const blob = new Blob(['\ufeff', contenido], { type: 'application/msword' });
    const link = document.createElement('a');
    link.href = URL.createObjectURL(blob);
    link.download = `Contrato_${d.nom_inq}.doc`;
    link.click();
}
</script>
</body>
</html>