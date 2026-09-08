# ADR-006 · Cola offline con UUID de cliente, CSRF fresco y errores clasificados

- **Estado:** vigente
- **Fecha:** 2026-07-02 · **Decide:** equipo (spike P-SPK-02, validado adversarialmente antes de codear)

## Contexto
El soplador registra tandas en planta, a veces sin señal. Una cola offline en una app Blade con sesión tiene tres trampas: el reintento del drenado **duplica** la tanda; el token CSRF serializado con el formulario queda **viejo** tras un rato offline (419); y un error permanente (máquina desactivada mientras estaba offline → 422) se confunde con uno transitorio (red, 5xx) — o se borra en silencio o se reintenta para siempre. iOS además **no tiene Background Sync**.

## Decisión
**Idempotencia por UUID del cliente; token fresco del `<meta>`; clasificación de la respuesta; nunca borrar en silencio.**
- `crypto.randomUUID()` en el cliente → columna `cliente_uuid` con **unique compuesto `[reporte_id, cliente_uuid]`** + check dentro del `lockForUpdate` (defensa en profundidad).
- El drenado **no** serializa `_token`: lee el token del `<meta name="csrf-token">` de la página viva y lo manda en `X-CSRF-TOKEN`, con `Accept: application/json` para recibir 422/403 crudos.
- Las reglas de negocio se lanzan como `ValidationException` (422 real en JSON; mismo redirect con errores en web).
- 2xx → borrar · 422/403 → **permanente** (marcar, no reintentar) · 419/5xx/red → **transitorio** (reintentar hasta `MAX_INTENTOS`). Reload solo tras drenar con éxito.
- Se drena en `online` y en la carga de la página, no con `sync` events.

## Alternativas consideradas
- **Exceptuar el endpoint del CSRF** — rechazado: muta inventario tras auth + permiso + ownership.
- **Serializar el `_token`** — queda stale; 419 permanente.
- **Background Sync API** — iOS no lo implementa.

## Consecuencias
Toda cola offline futura (conductor, terreno) usa este patrón. Cada guarda de precondición de la **tanda** necesita su gemela en la **parada** (bitácora [2026-08-10]). Un 403 que se convierta en 302 haría que el `fetch` viera `ok` del dashboard y borrara la tanda sin registrarla — por eso el handler de errores discrimina `expectsJson()` antes de redirigir (D-014).

## Evidencia
`docs/SPIKE-PWA.md` · `CLAUDE.md` bitácora [2026-07-02] ×2, [2026-07-24], [2026-08-10] · `resources/js/offline-queue.js` · `PwaTest`, tests de idempotencia en `ProduccionTest`.
