<?php
include 'db.php';
include 'verificar_sesion.php';
$sql = "SELECT p.*, u.apellido as nombre_inquilino, a.fecha_inicio as inicio, a.fecha_fin as vencimiento 
        FROM propiedades p 
        LEFT JOIN alquileres a ON a.propiedad_id = p.propiedad_id AND a.estado = 'VIGENTE'
        LEFT JOIN usuarios u ON a.inquilino1_id = u.id 
        ORDER BY p.propiedad ASC";
$resultado = mysqli_query($conexion, $sql);
$nivelAcceso = (int)($_SESSION['acceso_nivel'] ?? 0);
$soloLectura = ($nivelAcceso < 2);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Administración de Propiedades - HHH</title>
    <style>
        body { font-family: 'Segoe UI', sans-serif; background: #f0f2f5; margin: 0; padding: 10px; }
        .contenedor { background: white; padding: 15px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); width: 98vw; margin: auto; box-sizing: border-box; }
        
        .encabezado { display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; border-bottom: 2px solid #007bff; padding-bottom: 5px; }
        h2 { margin: 0; color: #007bff; text-transform: uppercase; font-size: 14px; }
        
        .controles { margin-bottom: 10px; }
        #txtBuscar { width: 100%; padding: 6px; border: 1px solid #ccc; border-radius: 4px; font-size: 11px; text-transform: uppercase; box-sizing: border-box; }
        
        .tabla-prop { width: 100%; border-collapse: collapse; font-size: 10px; table-layout: fixed; }
        .tabla-prop th { background: #007bff; color: white; padding: 8px; text-align: center; position: sticky; top: 0; z-index: 10; text-transform: uppercase; }
        .tabla-prop td { padding: 6px; border-bottom: 1px solid #eee; text-transform: uppercase; overflow: hidden; vertical-align: middle; }
        .tabla-prop tr:hover { background-color: #f1f7ff; }

        /* REDISTRIBUCIÓN DE COLUMNAS */
        .col-propiedad { width: 18%; }
        .col-consorcio { width: 15%; }
        .col-porcentaje { width: 8%; }
        .col-padron { width: 8%; }
        .col-detalle { width: 13%; }
        .col-estado { width: 12%; }
        .col-inicio { width: 9%; }
        .col-vencimiento { width: 9%; }
        .col-acciones { width: 18%; }

        /* ALINEACIÓN DE BOTONES */
        .flex-acciones { 
            display: flex; 
            justify-content: center; /* Centra el bloque de botones en la celda */
            gap: 5px; /* Espacio exacto entre botones */
            align-items: center;
        }

        .btn { 
            height: 24px; /* Altura fija para todos */
            border: none; 
            border-radius: 3px; 
            cursor: pointer; 
            font-weight: bold; 
            font-size: 8px; 
            text-transform: uppercase; 
            display: flex;
            align-items: center;
            justify-content: center;
            box-sizing: border-box;
        }
        
        /* Botones principales con el mismo ancho exacto */
        .w-grande { width: 85px; } 
        /* Botón de impresión más pequeño */
        .w-chico { width: 45px; }

        .btn-baja { background: #dc3545; color: white; } 
        .btn-fin { background: #fd7e14; color: white; }  
        .btn-print { background: #007bff; color: white; }
        .btn-editar { background: #D4A5A5; color: white; }

        .fila-prop-seleccionada { background-color: #cce5ff !important; border-left: 3px solid #007bff; }
        
        .btn-volver { background: #ffc107; color: #333; margin-top: 15px; font-size: 10px; padding: 5px 15px; text-decoration: none; }
        .btn-volver:hover { background: #e0a800; }

        .al-cen { text-align: center !important; }
        .al-izq { text-align: left !important; }
        .inquilino-nombre { color: #28a745; font-weight: bold; }
        .disponible { color: #999; font-style: italic; }
    </style>
</head>
<body>

<div class="contenedor">
    <div class="encabezado">
        <h2>Gestión de Propiedades</h2>
        <?php if (!$soloLectura): ?>
            <a href="nueva_propiedad.php" style="background:#28a745; color:white; padding:5px 10px; border-radius:3px; text-decoration:none; font-weight:bold; font-size:10px;">+ NUEVA</a>
        <?php endif; ?>
    </div>

    <div class="controles">
        <input type="text" id="txtBuscar" placeholder="🔍 BUSCAR..." onkeyup="filtrarPropiedades()">
        <?php if (!$soloLectura): ?>
        <div id="barraEditar" style="display:none; margin-top:8px;">
            <a href="#" id="linkEditarProp" class="btn btn-editar w-grande" style="text-decoration:none; padding:6px 12px;" onclick="event.preventDefault(); abrirEditarPropiedad(); return false;">✏️ EDITAR DATOS</a>
        </div>
        <?php endif; ?>
    </div>

    <table class="tabla-prop">
        <thead>
            <tr>
                <th class="col-propiedad">Propiedad</th>
                <th class="col-consorcio">Consorcio</th>
                <th class="col-porcentaje">%</th>
                <th class="col-padron">Padrón</th>
                <th class="col-detalle">Detalle Técnico</th>
                <th class="col-estado">Inquilino / Estado</th>
                <th class="col-inicio">Inicio</th>
                <th class="col-vencimiento">Vencimiento</th>
                <th class="col-acciones">Acciones</th>
            </tr>
        </thead>
        <tbody id="cuerpoTabla">
            <?php while ($f = mysqli_fetch_array($resultado)): 
                $alquilada = !empty($f['nombre_inquilino']);
                $inicio = !empty($f['inicio']) ? date('d/m/Y', strtotime($f['inicio'])) : '-';
                $venc = !empty($f['vencimiento']) ? date('d/m/Y', strtotime($f['vencimiento'])) : '-';
            ?>
            <tr class="fila-propiedad" data-id="<?= $f['propiedad_id'] ?>" onclick="seleccionarPropiedad(<?= $f['propiedad_id'] ?>, this, event)">
                <td class="al-izq"><?= $f['propiedad'] ?></td>
                <td class="al-cen"><?= $f['consorcio'] ?></td>
                <td class="al-cen" style="font-weight:bold; color:#007bff;"><?= $f['porcentaje'] !== null ? number_format($f['porcentaje'], 2, ',', '.') . '%' : '-' ?></td>
                <td class="al-cen"><?= $f['padron'] ?></td>
                <td class="al-cen" style="font-size: 8px; color: #777; white-space: nowrap;"><?= substr($f['detalle'],0,30) ?>...</td>
                <td class="al-cen">
                    <?= $alquilada ? "<span class='inquilino-nombre'>{$f['nombre_inquilino']}</span>" : "<span class='disponible'>DISPONIBLE</span>" ?>
                </td>
                <td class="al-cen"><?= $inicio ?></td>
                <td class="al-cen"><?= $venc ?></td>
                <td>
                    <?php if (!$soloLectura): ?>
                    <div class="flex-acciones" onclick="event.stopPropagation()">
                        <?php if($alquilada): ?>
                            <button onclick="finalizarContrato(<?= $f['propiedad_id'] ?>)" class="btn btn-fin w-grande">FIN CONTRATO</button>
                            <button onclick="imprimirContrato(<?= $f['propiedad_id'] ?>)" class="btn btn-print w-chico">IMPRIMIR CONTRATO</button>
                        <?php else: ?>
                            <button onclick="eliminarPropiedad(<?= $f['propiedad_id'] ?>)" class="btn btn-baja w-grande">BAJA PROP.</button>
                            <div style="width: 45px;"></div> 
                        <?php endif; ?>
                    </div>
                    <?php else: ?>
                    <span style="color:#999; font-size:9px;">Solo lectura</span>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endwhile; ?>
        </tbody>
    </table>

    <a href="index.php" class="btn-volver">← VOLVER AL PANEL</a>
</div>

<script>
var propSeleccionadaId = null;
var esNivel3 = <?= (isset($_SESSION['acceso_nivel']) && $_SESSION['acceso_nivel'] >= 3) ? 'true' : 'false' ?>;

function seleccionarPropiedad(id, filaEl, ev) {
    if (ev && ev.target && (ev.target.tagName === 'BUTTON' || ev.target.tagName === 'A' || ev.target.closest('button') || ev.target.closest('a'))) return;
    document.querySelectorAll('.fila-propiedad').forEach(function(r) { r.classList.remove('fila-prop-seleccionada'); });
    filaEl.classList.add('fila-prop-seleccionada');
    propSeleccionadaId = id;
    var barra = document.getElementById('barraEditar');
    if (barra) {
        barra.style.display = 'block';
    }
}

function abrirEditarPropiedad() {
    if (!propSeleccionadaId) {
        alert('Seleccione una propiedad primero.');
        return;
    }
    if (!esNivel3) {
        var clave = prompt("CLAVE DE SEGURIDAD PARA EDITAR PROPIEDAD:");
        if (clave === null) return;
        if (clave !== "4961") {
            alert("Clave incorrecta.");
            return;
        }
    }
    window.location.href = 'editar_propiedad.php?id=' + propSeleccionadaId;
}

function filtrarPropiedades() {
    let filtro = document.getElementById("txtBuscar").value.toUpperCase();
    let filas = document.querySelectorAll("#cuerpoTabla tr");
    filas.forEach(f => f.style.display = f.innerText.toUpperCase().includes(filtro) ? "" : "none");
}

function eliminarPropiedad(id) {
    if (!esNivel3) {
        var clave = prompt("CLAVE DE SEGURIDAD PARA ELIMINAR PROPIEDAD:");
        if (clave === null) return;
        if (clave !== "4961") {
            alert("Clave incorrecta.");
            return;
        }
    }
    if(confirm("¿Eliminar propiedad definitivamente?")) {
        fetch('eliminar_propiedad.php?id='+id)
            .then(function(r) { return r.text(); })
            .then(function(res) {
                if(res.trim()==="OK") {
                    location.reload();
                } else {
                    alert("Error al eliminar: " + res);
                }
            })
            .catch(function(error) {
                alert("Error de conexión: " + error);
            });
    }
}

function finalizarContrato(idProp) {
    if(confirm("¿Finalizar contrato vigente?")) {
        fetch('finalizar_contrato.php?id='+idProp).then(r=>r.text()).then(res=>{
            if(res.trim()==="OK") location.reload();
        });
    }
}

function imprimirContrato(idProp) {
    window.open('generar_word_contrato.php?id=' + idProp, '_blank');
}
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        window.location.href = 'index.php';
    }
});
</script>
<?php include 'timeout_sesion_inc.php'; ?>
</body>
</html>