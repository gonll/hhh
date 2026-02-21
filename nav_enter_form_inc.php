<?php
/**
 * Incluir en páginas con formularios alta/modificación.
 * Los formularios deben tener class="form-nav-enter" para que Enter pase al siguiente campo.
 */
?>
<script>
(function() {
    function initNavEnter() {
        document.querySelectorAll('form.form-nav-enter').forEach(function(form) {
            if (form.dataset.navEnterInit) return;
            form.dataset.navEnterInit = '1';
            form.addEventListener('keydown', function(e) {
                if (e.key !== 'Enter' && e.keyCode !== 13) return;
                var el = document.activeElement;
                if (!el || !form.contains(el)) return;
                var tag = (el.tagName || '').toUpperCase();
                if (tag === 'TEXTAREA') return;
                if (tag === 'BUTTON' || (el.type === 'submit' || el.type === 'button')) return;
                if (tag !== 'INPUT' && tag !== 'SELECT') return;
                if (el.type === 'hidden') return;
                e.preventDefault();
                var focusables = [].slice.call(form.querySelectorAll('input:not([type=hidden]):not([type=submit]):not([type=button]):not([type=reset]), select, textarea'));
                var idx = focusables.indexOf(el);
                if (idx >= 0 && idx < focusables.length - 1) {
                    focusables[idx + 1].focus();
                } else {
                    var btn = form.querySelector('button[type="submit"]');
                    if (btn) btn.focus(); else form.submit();
                }
            }, true);
        });
    }
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initNavEnter);
    } else {
        initNavEnter();
    }
})();
</script>
