# Investigación: Bug de Tab en producción (gestionar_finca.php)

## 1) DÓNDE se bloquea Tab

### Causa raíz: **setupTab corre antes de que existan los campos del formulario**

El script `setupTab` está **dentro** del `<form>`, **antes** de los inputs (tipo_horas, fecha, horas, observaciones, btnGuardar):

```html
<form id="formPDT">
  <script>
    // setupTab corre aquí
    if (document.readyState === 'loading') 
      document.addEventListener('DOMContentLoaded', setupTab);
    else 
      setupTab();  // ← En prod a veces readyState ya es 'interactive'/'complete'
  </script>
  <!-- Los campos están ABAJO, aún no existen cuando el script corre -->
  <select id="tipo_horas">...
  <input id="fecha">...
  ...
</form>
```

Cuando `document.readyState !== 'loading'` (p. ej. en prod por caché, bfcache o timing distinto), se ejecuta `setupTab()` de inmediato. En ese momento `tipo_horas`, `fecha`, `horas`, etc. **aún no existen** en el DOM. La comprobación `if(!f||!t||!fe||!h||!o||!g)return` hace que se salga sin registrar el listener. Resultado: **no se añade el manejador de Tab**.

### Otros manejadores revisados (no son la causa)

| Archivo | Qué hace | ¿Afecta Tab? |
|---------|----------|--------------|
| `body onkeydown` | Solo ESC (keyCode 27) | No |
| `manejarEsc` | Solo Escape | No |
| `nav_enter_form_inc.php` | Solo Enter (keyCode 13) | No |
| `#modalObservaciones` | display:none por defecto | No |
| `#modalInactividad` | No incluido en gestionar_finca | No |

## 2) Cómo reproducir y confirmar con logs

### Reproducir

1. Abrir `gestionar_finca.php` en producción.
2. Poner foco en "Tipo de trabajo".
3. Pulsar Tab varias veces.
4. Si el bug está presente: el foco no avanza o se queda en el mismo campo.

### Confirmar con logs

Añadir temporalmente al inicio de `setupTab`:

```javascript
console.log('[setupTab] readyState=', document.readyState, 
  'tipo_horas=', !!document.getElementById('tipo_horas'),
  'formPDT=', !!document.getElementById('formPDT'));
```

- Si en prod ves `tipo_horas= false` → los campos no existen cuando corre `setupTab`.
- Si ves `tipo_horas= true` pero Tab no avanza → buscar otra causa.

## 3) Fix aplicado

### Cambios realizados

**A) gestionar_finca.php**
- Se eliminó el script `setupTab` que estaba **dentro** del form (antes de los campos).
- Se añadió un nuevo script al **final** del body (antes de `nav_enter_form_inc.php`) que:
  - Ejecuta `setupTabPDT` cuando el DOM está listo.
  - Reintenta hasta 20 veces (cada 100 ms) si algún elemento no existe.
  - Usa `dataset.tabInit` para no registrar el listener más de una vez.

**B) nav_enter_form_inc.php**
- Se añadió al inicio del handler: `if (e.key === 'Tab' || e.keyCode === 9) return;`
- Garantiza que Tab nunca sea interceptado por este handler (por si hay diferencias entre navegadores).

### Por qué funciona

1. El script corre **después** de que el form y todos sus campos estén en el DOM.
2. Si algún elemento falta (timing en prod), se reintenta hasta 2 s.
3. `nav_enter_form_inc` ignora explícitamente Tab, evitando conflictos.
