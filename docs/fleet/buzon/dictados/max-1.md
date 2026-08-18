# Dictado vigente — Max-1 (Forjador A, stream 1)
> Emitido por el Director el 2026-08-18 (v67 — PROYECTO NUEVO: PLAN-PARAMETRICOS, cacería de hardcodes. GO F0-DASH: auditoría del Dashboard, SOLO DOCS). Manda sobre lo anterior.

MODELO: Opus 4.8 · high.

## 🆕 Proyecto nuevo del dueño: PLAN-PARAMETRICOS

El dueño pidió (18-ago): buscar valores hardcodeados que deberían ser paramétricos,
módulo por módulo en el orden de la sidebar (Dashboard primero, después Comercial, y así
toda la app), «poco a poco». El plan completo está en
**`docs/planes/PLAN-PARAMETRICOS.md`** — LÉELO ENTERO antes de partir: trae los tres
niveles de parametrización de la casa (Configuracion BD+UI / config+env / se-queda con
porqué), el protocolo de dos fases y el formato del mapa.

## 🔍 GO — F0-DASH: auditoría del módulo Dashboard (SOLO DOCS, cero código)

Barre el módulo Dashboard completo:
- `app/Http/Controllers/DashboardController.php` (+ `DashboardColoresController`)
- `app/Support/AccesosDashboard.php`, `app/Support/DashboardColores*` (lo que exista)
- `resources/views/dashboard*` y parciales que monte
- Cualquier Support/Model que el dashboard consuma para calcular lo que muestra
  (excepciones, pulso, series) — el hardcode puede vivir aguas arriba.

Buscas (definición completa en el plan §2):
1. **Números mágicos** — semillas ya vistas por el Director, confírmalas y complétalas:
   serie de producción de 7 días, referencia de merma de 7 días, cortes de antigüedad
   0-7/8-30/30+. Además: límites de listas («últimos N»), umbrales de semáforos/colores,
   todo entero o porcentaje con significado de negocio.
2. **Strings de negocio fijos** (nombres, textos que cambian con el negocio — NO labels
   de UI).
3. **Listas que crecen** (arrays literales que la operación pueda ampliar).
4. **Duplicados** — el mismo valor en N sitios; anótalos aunque el veredicto sea
   nivel 3, porque unificarlos a UNA constante ya paga solo.

### Entregable (cero código)
El **anexo §5.1 de `docs/planes/PLAN-PARAMETRICOS.md`** con la tabla del mapa:

| # | Valor | Dónde vive (file:line) | Qué controla EN PANTALLA (palabras del negocio) | Repetido en | Veredicto propuesto (1/2/3 + porqué en una línea) | Esfuerzo (S/M/L) |

Reglas del mapa:
- «Qué controla» se escribe para que EL DUEÑO decida sin leer código — ejemplo: «cuántos
  días de producción muestran las mini-barras del Inicio», no «el subDays(7) de L143».
- El veredicto propuesto usa la vara de daligo.php: si moverlo en caliente puede romper
  la operación → nivel 2, no 1. Si es invariante/doctrina → nivel 3 con el porqué.
- Los duplicados se marcan aunque no se parametricen.
- Si un hallazgo cruza a OTRO módulo (el dashboard consume de todos), se ANOTA con su
  módulo de origen y se deja para la auditoría de ese módulo — este mapa es del
  Dashboard; no te desparames.

### Verificación de fase A
Es un lote SOLO-DOCS: no hay suite que correr ni rama de código. Commit del anexo +
parte al buzón directo a main (como los partes de siempre). En el parte: resumen del
mapa (cuántos hallazgos por nivel propuesto) + lo que más te llamó la atención.
**NO propongas ni escribas código** — los lotes de fase B llegan tras los veredictos
del dueño, un dictado por lote como siempre.

## Estado
- Max-2 en pausa (v24). Marcos activo — tu barrido es read-only, cero riesgo de choque.
- Producción: menú 32, suite 2196/15.292 en `8fb0c5c`, CI verde. PLAN-MENU-DENSIDAD
  cerrado y registrado en /plan (bloque E-MENU).

CIERRE: GO F0-DASH. Proyecto nuevo, mismo pulso: un dictado, un parte, y el dueño
decide. Fierro.
