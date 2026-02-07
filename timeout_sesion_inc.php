<?php
if (empty($_SESSION['acceso_id'])) return;
$nivelAcceso = (int)($_SESSION['acceso_nivel'] ?? 0);
// Nivel 3 y 4: 10 minutos. Nivel 1 y 2 (Partes): 4 horas.
$minutosInactividad = ($nivelAcceso >= 3) ? 10 : (4 * 60);
$inactividadMs = $minutosInactividad * 60 * 1000;
?>
<div id="modalInactividad" style="display:none; position:fixed; top:0; left:0; right:0; bottom:0; background:rgba(0,0,0,0.6); z-index:9999; justify-content:center; align-items:center; flex-direction:column;">
    <div style="background:white; padding:25px; border-radius:8px; max-width:360px; text-align:center; box-shadow:0 4px 20px rgba(0,0,0,0.3);">
        <p style="margin:0 0 10px; font-size:14px;">¿Seguís ahí? La sesión se cerrará por inactividad en <strong id="countdownInactividad">30</strong> segundos si no respondés.</p>
        <button type="button" id="btnSigoAca" style="padding:10px 24px; background:#28a745; color:white; border:none; border-radius:4px; font-weight:bold; cursor:pointer;">Sigo acá</button>
    </div>
</div>
<script>
(function() {
    var INACTIVIDAD_MS = <?= $inactividadMs ?>;  // Nivel 3: 10 min; Nivel 1-2: 2 min
    var AVISO_MS = 30 * 1000;            // 30 segundos para responder al aviso
    var CHECK_MS = 5000;                 // revisar cada 5 segundos
    var lastActivity = Date.now();
    var warningShown = false;
    var warningTimer = null;
    var countdownTimer = null;
    var modal = document.getElementById('modalInactividad');
    var countdownEl = document.getElementById('countdownInactividad');
    var btnSigo = document.getElementById('btnSigoAca');

    function resetActivity() {
        lastActivity = Date.now();
    }

    function hideWarning() {
        warningShown = false;
        if (warningTimer) { clearTimeout(warningTimer); warningTimer = null; }
        if (countdownTimer) { clearInterval(countdownTimer); countdownTimer = null; }
        if (modal) modal.style.display = 'none';
    }

    function cerrarSesion() {
        window.location.href = 'logout.php';
    }

    function showWarning() {
        if (warningShown) return;
        warningShown = true;
        if (modal) {
            modal.style.display = 'flex';
            var seg = 30;
            if (countdownEl) countdownEl.textContent = seg;
            countdownTimer = setInterval(function() {
                seg--;
                if (countdownEl) countdownEl.textContent = seg;
                if (seg <= 0 && countdownTimer) {
                    clearInterval(countdownTimer);
                    countdownTimer = null;
                }
            }, 1000);
        }
        warningTimer = setTimeout(function() {
            hideWarning();
            cerrarSesion();
        }, AVISO_MS);
    }

    function checkInactivity() {
        if (warningShown) return;
        if (Date.now() - lastActivity >= INACTIVIDAD_MS) {
            showWarning();
        }
    }

    ['mousemove', 'mousedown', 'keydown', 'scroll', 'touchstart', 'click'].forEach(function(ev) {
        document.addEventListener(ev, resetActivity, true);
    });

    if (btnSigo) {
        btnSigo.addEventListener('click', function() {
            resetActivity();
            hideWarning();
        });
    }

    setInterval(checkInactivity, CHECK_MS);
})();
</script>
