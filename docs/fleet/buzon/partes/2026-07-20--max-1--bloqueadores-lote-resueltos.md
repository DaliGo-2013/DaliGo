# Parte — Max-1 · Los 2 bloqueadores del lote RESUELTOS (rama `fix/qa-aprobaciones-ux` @ `ad750a1`)

> De: Max-1 · Para: Director / Mauricio · Dictado v15 ejecutado.

## Bloqueador 1 (test) — resuelto, con una precisión al diagnóstico

- **Fix aplicado tal como se dictó:** el test verifica la bandeja por su RUTA
  (`assertSee(route('aprobaciones.index'), false)`) + el historial por la suya + el label
  «Historial de aprobaciones». Cero dependencia del idiom `>X<`.
- **Precisión (importa para la doctrina de flota):** la aserción vieja **NO fallaba** — los
  «634 verdes» del parte fueron una corrida real y completa. `>Aprobaciones<` SÍ existe
  pegado en el HTML del dashboard… pero por el **chip del zócalo M16-v1**
  (`...ring-neutral-200">{{ $item['label'] }}</a>`), no por el nav-link que el comentario
  del test decía verificar. Es decir: el test **pasaba por la razón equivocada** — verde
  engañoso, que es peor que rojo. El fix del Director es el correcto igual; la lección
  para la bitácora de ambos: un assert de markup pegado puede satisfacerse desde OTRA
  superficie de la misma página — asertar por ruta/marcador estable, no por forma del HTML.
- Bonus del dictado aplicado: guard `is_array()` en el diff de la tarjeta (#7) — payload
  con forma inesperada degrada a «sin diff», no revienta.

## Bloqueador 2 (bundle stale) — resuelto por el camino de siempre

- Merge de `origin/main` a la rama (`8531bf4`; conflicto solo en manifest → **regenerado**,
  `package*` sin cambios).
- Bundle nuevo **`app-BRXmIX49.css`** con **grep superset 14/14**: las 7 de la flota
  (`lg\:flex`, `lg\:hidden`, `sm\:grid-cols-3`, `lg\:grid-cols-5`, `min-w-[1.5rem]`,
  `bg-white/60`, `min-w-8`) **y** las 7 del boceto seguimiento M12 (`ring-4`,
  `border-amber-200`, `bg-amber-50`, `text-amber-800`, `top-11`, `last:pb-0`,
  `h-[calc(100%-1.75rem)]` — esta última verificada por su valor `calc(100% - 1.75rem)`:
  el grep del selector requiere escapar `(`/`%` en el CSS compilado, gotcha de grep, no
  ausencia). Cero regresión al escenario campanita.

## Verificación

**Suite COMPLETA: 638 verdes (2.037 aserciones)** en el árbol mergeado (main @ `687023e`
con seguimiento + instalaciones f2). Corrida entera, exit 0 — archivo de salida disponible.

**Listo para la doble llave del Director** (el dueño ya validó los 3 fixes en el QA).
Tras el merge arranco el **lote S2** (`fix/qa-aprobaciones-ux2` desde main fresco: #5 filas
clickeables, #9b enlace a auditoría, #8 plantillas ricas) — un lote a la vez, como se dictó.

Nota: B4 del acta ya quedó cerrado — el dueño confirmó la reversión del checkbox el 17-07
(acta actualizada en `687023e`).

— Max-1
