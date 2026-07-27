# Parte — Max-1: dictado v22 COMPLETO — tests de frontera NACIERON VERDES (Marcos ya aplicó) → `test/tz-frontera-agenda` lista para llave · 2026-07-21

> De Max-1 (Forjador A) al Director.

## GATE del dictado: VERDE

El dictado condicionaba el merge a que Marcos aplicara las 2 líneas. **Ya están en main**
(`daf948d`, mergeado 11:11): grilla `isToday`→`esHoy` + `update()` `?? now()`→
`FechaNegocio::ahora()` — el fix que dejé "listo para dictar" entró tal cual, con bitácora
propia de Marcos. Los 3 tests de la batería **nacieron verdes** = confirmación empírica de
que su fix funciona en las fronteras. Estado: **listo para llave**, sin espera.

## Rama `test/tz-frontera-agenda` @ `899cdbf` (SOLO tests — cero archivos de Marcos)

Un solo archivo tocado: `tests/Feature/FechaNegocioTest.php` (+3 tests, 10 total en la
clase). Sin Blade → sin build, como ordenó el dictado. Inmune al churn de agenda por
construcción.

1. **Nocturna (23:00 Chile)** — la agenda abre en el HOY chileno (mes 7 + `diaSel` 20-07 +
   cabecera «· HOY»); celdas scoped por su `?dia=` (el marcador `#dia-{iso}` del dictado ya
   no existe: `bc51e82` volvió las celdas links de selección — mismo scoping, selector
   actualizado). Par positivo/negativo por celda: día chileno CON resalte, mañana-UTC SIN
   `font-bold`. Matiz que el dictado no podía prever: con «abre en HOY», el día chileno
   default va como **seleccionado** (`brand-700`, precedencia `$sel`); la rama `esHoy` de
   Marcos (`brand-600`) se prueba **seleccionando otro día** (`?dia=15`) — así el test cubre
   AMBAS ramas del ternario de la línea que él corrigió.
2. **Frontera de MES** — 31-07 22:00 Chile: abre julio con el 31 seleccionado, no agosto-UTC.
3. **El hallazgo `update():151`** — editar una solicitud QR sin fecha a las 21:30 Chile del
   31-07 redirige a `?anio=2026&mes=7` (julio chileno). Con el `now()` viejo iba a agosto:
   el test fija el fix de Marcos contra regresiones.

Mutación inversa no aplicada (el fix no es mío), como dispuso el dictado.

## Verificación

- `FechaNegocioTest` 10/10 (37 aserciones).
- **Suite completa: 704 verdes (2.259 aserciones)**, exit 0.
- `fix/tz-calendario-agenda-v2` **eliminada del remoto** como se ordenó (registro en los
  partes del 20-07 y 21-07; las 2 líneas viven ahora en el `daf948d` de Marcos).

## Estado TZ global (para el tablero)

P-TZ-01 ✅ prod · P-TZ-02 ✅ prod · agenda ✅ (fix de Marcos + esta batería que lo fija) ·
P-TZ-03 (QA de borde ~21:30) en manos del dueño — con todo lo anterior vivo, debería ver:
día correcto en toda la app, agenda abriendo en HOY chileno, historiales en hora chilena.
Único remanente aceptado: filtros de historial por día UTC (limitación documentada del plan).
