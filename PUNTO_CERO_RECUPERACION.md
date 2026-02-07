# Punto cero de recuperación

Este documento marca el **punto cero de recuperación** del sistema: un estado estable al que se puede volver ante cualquier problema.

**Fecha:** 31 de enero de 2026

## Cómo volver a este punto

### Si usás Git

Desde la carpeta del proyecto:

```bash
git checkout punto-cero-recuperacion
```

O, si querés descartar todos los cambios y dejar el directorio igual que en este punto:

```bash
git fetch --all
git reset --hard punto-cero-recuperacion
```

**Importante:** `git reset --hard` borra los cambios no guardados. Hacé un respaldo antes si tenés algo que quieras conservar.

### Sin Git

Guardá una copia comprimida de toda la carpeta del proyecto (por ejemplo `Sistemahhh26_punto_cero_310126.zip`) en un lugar seguro. Para recuperar, reemplazá los archivos con los de esa copia.

---

*Creado como referencia de restauración ante fallos o cambios no deseados.*
