# Dictado vigente — Max-1 (Forjador A, stream 1)
> Emitido por el Director el 2026-07-21 (v21 — render espera doble llave; fix calendario DEVUELTO por fusión de Marcos: re-aplicar como mini-fix de 2 sitios). Manda sobre lo anterior.

MODELO: Opus 4.8 · high.

## Estado de tus 2 ramas del v20

### `fix/tz-render` (P-TZ-02) — ✅ VERIFICADO por el Director, espera la llave del dueño
Suite ejecutada local: **686/2196 exacto a tu parte**; clases +/- idénticas (cero nuevas,
confirmado por diff de atributos — tu "mismo hash" probado por segunda vía); macro con
`copy()` verificado en el provider (buen catch lo de Carbon mutable); conflicto vs main =
SOLO manifest (main sirve `app-GfuV1LWO.css` tras los builds nocturnos de Marcos) → lo
resuelvo yo al lado de main con la evidencia de cero-clases-nuevas. No hagas nada con esta
rama. Nota de inventario: los 6 formatos nuevos que Marcos metió anoche son sobre columnas
`date` puras / días de calendario → INMUNES según tu propio plan (§1b), cero deuda P1 nueva.

### `fix/tz-calendario-agenda` — ❌ NO MERGEABLE, la fusión de Marcos la dejó obsoleta
No es culpa tuya: anoche Marcos mergeó 7 lotes, entre ellos `agenda-terreno-fusion`
(`fdbd965`) que **BORRÓ `calendario.blade.php`** (absorbido en `index.blade.php`) y
reescribió el controller (franjas 2h + multidía + técnico default). Tu rama da modify/delete
+ conflicto de contenido. La rama cumplió su ciclo sin mergear — ciérrala.

**La buena:** Marcos ADOPTÓ tu helper al fusionar — los defaults año/mes ya usan
`FechaNegocio::ahora()` (controller:42-43) y la lista usa `esHoy()` ×3 (index:143-146,
incluida la cabecera «· HOY»). Tu doctrina ya está colonizando el territorio vecino sola.

## 🟢 TAREA 1 — re-aplicar el fix como MINI-FIX (S, ~10 min) · misma excepción autorizada
Rama nueva `fix/tz-calendario-agenda-2` desde main FRESCO. Quedan exactamente **2 sitios UTC**
en la agenda fusionada (verificados por el Director contra main de hoy):
1. `AgendaTrabajoController.php:151`: `$destino = $trabajo->fecha ?? now();` →
   `?? \App\Support\FechaNegocio::ahora()` (es el ancla de scroll/redirect al día).
2. `index.blade.php:91`: `$d->isToday()` (resalte del número en la grilla del calendario) →
   `\App\Support\FechaNegocio::esHoy($d)`.
- Test: adapta tu batería de frontera del v20 a la vista FUSIONADA (23:00 Chile → grilla
  resalta el día chileno scoped a la celda + frontera de mes 31-07/01-08). El caso «· HOY»
  ya lo cubre el código de Marcos — asértalo igual (barato y fija su adopción del helper).
- La mutación-roja de tu v20 vale de nuevo aquí (revertir sitio → test rojo → restaurar).
- NADA más de la agenda: franjas/multidía/técnico-default son de Marcos y están recién
  paridos. Blade tocado → main fresco + build + grep superset (main: `app-GfuV1LWO.css`).

## Después (sin GO nuevo): nada. P-TZ-02 lo merjeo yo con la llave del dueño.
P-TZ-03 (QA de borde ~21:30) sigue siendo del dueño. #6 chips sigue con el Director.

CIERRE: parte a docs/fleet/buzon/partes/ + push.
