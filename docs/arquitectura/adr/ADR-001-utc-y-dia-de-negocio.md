# ADR-001 · Storage en UTC + `FechaNegocio` para el día operativo

- **Estado:** vigente
- **Fecha:** 2026-07-20 · **Decide:** equipo (PLAN-TIMEZONE, opción C)

## Contexto
`app.timezone` es UTC y el negocio opera en Chile (UTC−4/−3). Desde las 20:00–21:00 hora chilena, `now()->toDateString()` ya es **mañana**: el soplador nocturno no veía sus producciones, la cola del jefe se vaciaba, el pulso marcaba ceros y el formulario público rechazaba el «hoy» del cliente (`after_or_equal:today`). Además los timestamps se mostraban en UTC (+4 h): el QA leyó «15:45» cuando eran las 11:45.

La tentación era flipear `app.timezone` a `America/Santiago`. Rompe los `diffForHumans` históricos, corre el backoff de la cola y las tareas del cron en el cutover, y obliga a migrar todos los `datetime` guardados.

## Decisión
**El storage y el motor se quedan en UTC. El «hoy» del negocio y el render son responsabilidad de dos helpers, y nadie llama `now()` para una fecha que ve el usuario.**
- `App\Support\FechaNegocio::hoy() / esHoy() / ahora()` → el día operativo chileno (`config('daligo.tz_negocio')`).
- `Carbon::macro('enChile')` → render de timestamps **con hora**; jamás sobre casts `date` puros (su medianoche UTC retrocedería un día).
- Los deltas del motor (`subMinutes`, backoff, colas) siguen en UTC; los `diffForHumans` no se tocan.
- La suite se congela a **mediodía UTC** (`tests/TestCase`) para que la fecha sea la misma en ambas zonas.

## Alternativas consideradas
- **Flipear `app.timezone`** — rompe históricos y motor; migración de datos en caliente.
- **Convertir en cada vista a mano** — 25 sitios sin fuente única; el error vuelve con la próxima pantalla.

## Consecuencias
Tres familias de fecha con reglas distintas (día de negocio / render / motor). El gate greppea la familia completa (`toDateString|format('Y-m-d'|isToday|now()->translatedFormat|after_or_equal:today`). Comparar «cuántos días faltan» exige pasar ambas puntas por `toDateString()` (bitácora [2026-08-04]).

## Evidencia
`docs/planes/PLAN-TIMEZONE.md` · `CLAUDE.md` bitácora [2026-07-20] ×2, [2026-08-04] · `FechaNegocioTest`, `RenderEnChileTest`, `DiasHabilesTest`.
