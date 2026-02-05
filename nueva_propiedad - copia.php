<?php
include 'db.php';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Nueva Propiedad - HHH</title>
    <style>
        body { font-family: 'Segoe UI', sans-serif; background: #f0f2f5; padding: 20px; margin: 0; }
        .card { background: white; padding: 25px; border-radius: 8px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); max-width: 500px; margin: auto; }
        h2 { color: #007bff; margin-top: 0; font-size: 16px; text-transform: uppercase; border-bottom: 2px solid #007bff; padding-bottom: 10px; }
        label { display: block; margin: 10px 0 5px; font-size: 11px; font-weight: bold; color: #555; text-transform: uppercase; }
        input, textarea { width: 100%; padding: 8px; box-sizing: border-box; border: 1px solid #ccc; border-radius: 4px; text-transform: uppercase; font-size: 12px; }
        textarea { min-height: 80px; resize: vertical; }
        .btns { margin-top: 20px; display: flex; gap: 10px; }
        button, .btn-cancelar { flex: 1; padding: 10px; border: none; border-radius: 4px; cursor: pointer; font-weight: bold; text-align: center; text-decoration: none; font-size: 12px; }
        button { background: #28a745; color: white; }
        .btn-cancelar { background: #6c757d; color: white; display: block; }
        button:hover { background: #218838; }
        .btn-detalle { background: #17a2b8; color: white; padding: 6px 10px; font-size: 11px; margin-right: 6px; margin-bottom: 6px; }
        .btn-detalle:hover { background: #138496; }
        .carga-rapida { margin-bottom: 8px; }
        .buscador-contenedor { position: relative; }
        .sugerencias { position: absolute; width: 100%; background: white; border: 1px solid #ddd; z-index: 100; max-height: 120px; overflow-y: auto; display: none; box-shadow: 0 2px 8px rgba(0,0,0,0.15); }
        .sugerencia-item { padding: 8px; cursor: pointer; font-size: 12px; border-bottom: 1px solid #eee; text-transform: uppercase; }
        .sugerencia-item:hover { background: #e7f3ff; }
    </style>
</head>
<body>

<div class="card">
    <h2>Nueva propiedad</h2>
    <form action="guardar_propiedad.php" method="POST" onsubmit="return validarPropietario()">
        <div class="buscador-contenedor">
            <label>Propietario *</label>
            <input type="text" id="bus_propietario" placeholder="BUSCAR USUARIO..." onkeyup="buscarPropietario(this.value)" autocomplete="off">
            <input type="hidden" name="propietario_id" id="propietario_id" value="">
            <div id="sug_propietario" class="sugerencias"></div>
        </div>

        <label>Propiedad *</label>
        <input type="text" name="propiedad" required autofocus placeholder="Ej: DEPTO 1A">
        
        <label>Consorcio</label>
        <input type="text" name="consorcio" placeholder="Ej: EDIFICIO CENTRAL">
        
        <label>Padrón</label>
        <input type="text" name="padron" placeholder="Ej: 12345">
        
        <label>Carga rápida detalle</label>
        <div class="carga-rapida">
            <button type="button" class="btn-detalle" onclick="document.getElementById('detalle').value='Un dormitorio, con un baño completo.'">1 dormitorio</button>
            <button type="button" class="btn-detalle" onclick="document.getElementById('detalle').value='Dos dormitorios con baño completo y toilete.'">2 dormitorios</button>
            <button type="button" class="btn-detalle" onclick="document.getElementById('detalle').value='Tres dormitorios con baño completo y toilete.'">3 dormitorios</button>
        </div>
        
        <label>Detalle técnico</label>
        <textarea id="detalle" name="detalle" placeholder="Descripción del inmueble"></textarea>
        
        <div class="btns">
            <button type="submit">GUARDAR</button>
            <a href="propiedades.php" class="btn-cancelar">VOLVER</a>
        </div>
    </form>
</div>

<script>
function buscarPropietario(q) {
    var lista = document.getElementById('sug_propietario');
    if (q.length < 2) { lista.style.display = 'none'; return; }
    fetch('buscar_personas.php?q=' + encodeURIComponent(q)).then(function(r) { return r.json(); }).then(function(data) {
        lista.innerHTML = '';
        if (data.length > 0) {
            lista.style.display = 'block';
            data.forEach(function(p) {
                var div = document.createElement('div');
                div.className = 'sugerencia-item';
                div.innerText = p.apellido;
                div.onclick = function() {
                    document.getElementById('propietario_id').value = p.id;
                    document.getElementById('bus_propietario').value = p.apellido;
                    lista.style.display = 'none';
                };
                lista.appendChild(div);
            });
        } else {
            lista.style.display = 'none';
        }
    });
}
function validarPropietario() {
    if (!document.getElementById('propietario_id').value) {
        alert('Seleccione un propietario (busque y elija de la lista).');
        document.getElementById('bus_propietario').focus();
        return false;
    }
    return true;
}
</script>
</body>
</html>
