# Dictado vigente — Max-1 (Forjador A, stream 1)
> Emitido por el Director el 2026-08-06 (v36 — GO F1 de PLAN-M04: bodegas full paramétricas). Manda sobre lo anterior.

MODELO: Opus 4.8 · high.

## 🟢 GO — F1 de PLAN-M04 (M04 Inventario SE DESTRABA tras 24 días pospuesto)

**Lee primero `docs/planes/PLAN-M04.md` (VIGENTE, visto bueno del dueño 06-ago)** — ahí
está el diseño completo. D-003 quedó resuelta parcial (anexo 2 de `docs/DECISIONES.md`:
5 bodegas viven, 6 mueren, 5 [B] con pregunta en curso). Corrección de rumbo del dueño:
**nada congelado en seeder — todo administrable desde la app**.

Tu lote es **F1 completa** (P-M04-10 + 11 + 12). F2 (wizard de baja con traslado) NO va
en este lote — ni la empieces.

### P-M04-10 · Bodegas editables
- Migración ADITIVA a `bodegas`: `sucursal_id` FK nullable → `sucursales`, `proposito`
  string **(191 explícito — gotcha MySQL 5.7, SQLite no te va a avisar)**, `en_operacion`
  bool default true, `clasificacion_confirmada` bool default false, `estado_baja` string
  nullable (la usa F2; créala ya para no migrar dos veces), `alias` string(191) nullable.
- La pantalla admin de bodegas (hoy `only(['index','show'])`) gana `edit`/`update` con el
  permiso EXISTENTE `manage sucursales` — **sin permiso nuevo a propósito** (tu propia
  lección de M13: la matriz de roles no se toca).
- Badges en index/show: propósito, «por confirmar» (las 5 [B]), «NUEVA — por clasificar»,
  «EN BAJA» (F2 la activará). Cambios auditados (el modelo ya es Auditable).
- Scopes `enOperacion()` y `deSucursal()`.
- Seeder `ClasificacionBodegasSeeder`: pre-carga del anexo 2 de D-003, matchea por
  `bsale_office_id` (NUNCA por nombre), idempotente, y **no pisa filas con
  `clasificacion_confirmada=true`** (después del primer run, manda la UI).

### P-M04-11 · Guardas de sucursales completadas
- `SucursalController::destroy` ya bloquea por usuarios y máquinas. Suma **bodegas
  asignadas** y **hojas de ruta** con el MISMO patrón (mensaje que dice qué reasignar).

### P-M04-12 · Adopción automática de bodegas nuevas
- Office nueva en Bsale → `StockSync` la trae (ya lo hace) → debe quedar
  `clasificacion_confirmada=false` (default de la migración lo da gratis) + notificación
  (M15) a quienes tengan `manage sucursales`, una sola vez por bodega.
- Botón «Agregar bodega» en el index: modal/instructivo («se crea en Bsale; aparecerá
  aquí sola en ≤15 min») — NO intenta crear por API (eso es F4, espera D-005).
- **`StockSync` NO se modifica en su lógica de espejo** — solo el gancho de notificación
  post-upsert si es creación. Candado: sync simulado 2× no duplica notificación ni pisa
  clasificación local.

### Candados mínimos del lote
1. Seeder 2× = mismo estado (idempotencia real, con una fila confirmada en el medio).
2. Sync simulado no pisa `sucursal_id`/`proposito`/`en_operacion` editados.
3. 403 sin `manage sucursales` en edit/update.
4. Las 6 muertas de D-003 fuera de los selectores operativos (scope `enOperacion()`).
5. Sucursal con bodegas → destroy bloqueado con mensaje útil.
6. Mutación: badge «por confirmar» desaparece al confirmar desde la UI.

## Territorio
- **Max-2** en pausa (P-DSP-10 espera ronda 2) — sin cruce.
- **Marcos** sigue MUY activo en main (visor 3D, M05). Rama corta, push temprano,
  re-fetch antes de cualquier merge tuyo.

## Recordatorios
Rama nueva desde main FRESCO. **Suite COMPLETA de main fresco ANTES de empezar** (así
fijas TU baseline del día — main corre rápido; la última cifra del Director fue
1556/11.358 en `f3be802` y puede haber subido). Suite completa de nuevo antes del push.
Blade tocado → build + grep superset. Conflictos con `git checkout origin/main --
<archivo>`, nunca con `>` (BOM de PS 5.1). Parte al buzón → doble llave.

CIERRE: parte a docs/fleet/buzon/partes/ + push.
