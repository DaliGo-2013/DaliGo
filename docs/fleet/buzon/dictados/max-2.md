# Dictado vigente — Max-2 (Forjador B, stream 2)
> Emitido por el Director el 2026-08-03 (v13 — P-DSP-05 recibido, la demora fue mía; re-refresh y doble llave; después GO P-DSP-08 hoja de ruta digital). Manda sobre lo anterior.

MODELO: el que fije el dueño en tu asiento · high.

## ✅ P-DSP-05: parte RECIBIDO — y la espera fue error del Director, no tuyo

Tu parte del 28-07 (+ adenda del 30-07) esperó 6 días la doble llave. Lo confundí con trabajo
antiguo al barrer el buzón el 30-07: error mío, queda anotado en el tablero. El lote está a la
altura de lo mejor de la flota:

- La **mutación que cayó en la ocurrencia equivocada y quedó verde** — y que lo anotaras para
  la doctrina en vez de contarlo como éxito — es exactamente la honestidad que la doble llave
  necesita para significar algo.
- La **mina del `x-data` anidado** del `<x-archivo-input>` (la foto habría dejado de adjuntarse
  EN SILENCIO) justificó sola la regla de refrescar-por-paso.
- El banco browser con blobs reales y la idempotencia 2×uuid→1 entrega, sin pisar hora ni
  firma: eso es verificación por ejecución, no por lectura.

## 🔴 Paso 1 — re-refresh contra el main de HOY (volviste a quedar atrás: 54/7 al 03-ago)

Main corrió de nuevo desde tu adenda (`b57c672` → `f70469f`): entraron la página `/plan`,
«Sin solución» del taller, tarjetas de permisos, fixes de borde de mes e **idioma-espanol
(paginación y nombres de campo en español)** — cualquiera puede traer candados que te barran.
Mismo protocolo de tu adenda, que quedó como doctrina:

1. Merge de main en `feature/entregas-conductor`. Conflicto esperado: `manifest.json` →
   `git checkout origin/main -- public/build/manifest.json` + rebuild + **superset** (0 clases
   perdidas) + manifest JSON válido sin BOM.
2. Ojo con candados nuevos post-30-07 (los de idioma y `PlanProyectoTest` no existían cuando
   refrescaste).
3. **Suite COMPLETA sobre el árbol mergeado** — la cifra que reportes es la que mando al dueño
   con la doble llave.
4. Parte CORTO al buzón (cifra + qué te barrió + cómo lo resolviste). Con eso verifico y pido
   la llave del dueño el mismo día. No esperes más dictado para empezar este paso.

## 🟢 Paso 2 — GO P-DSP-08: la hoja de ruta digital (F1) — SOLO tras el merge de la PWA

**Entró hoy a main `docs/planes/PLAN-DESPACHOS-V2.md` — léelo entero antes de la primera
migración; es la fuente, esto es el resumen.** Sale de las respuestas de Luis (operaciones)
a la ronda 1 de despachos; D-006 quedó RESUELTA y el scoping de carga ajena tiene diseño
del dueño de la operación.

- Tablas nuevas `hojas_de_ruta` + `hoja_ruta_paradas` (§2 del plan: columnas, estados, reglas).
- **Folio autogenerado desde 1000** (pedido de Luis) — `max(folio)+1` bajo `lockForUpdate`.
- **Cadena de 3 llaves secuenciales auditadas**: jefe ventas (pagos) → jefe despacho (ruta) →
  jefe bodega (carga). Permisos nuevos, NO pasa por M14.
- **Generación de paradas desde `documentos_venta`**: Ricardo ELIGE documentos, nada se tipea.
- **Estado de cobro por parada**: `pagado | cobrar_en_entrega | credito` — el chofer cobra en
  la puerta cuando no hay OK; el registro del cobro es de P-DSP-09, pero la columna nace aquí.
- **Scoping conductor↔ruta**: retiro/entrega solo si la hoja `en_ruta` es tuya.
- **Sin UI de conductor en este lote** (eso es P-DSP-09) y **sin campos manuales de hora**
  (timestamps automáticos por transición — pedido explícito de Luis).
- Candados mínimos: máquina de estados no saltable (mutada), folio único bajo carrera,
  permiso por llave, paradas solo de documentos vigentes.

Rama **nueva desde main fresco DESPUÉS de que la PWA entre** — no encadenes sobre
`feature/entregas-conductor`.

## Territorio
- **Marcos sigue en M05 Facturación/DTE** — ni de refilón.
- **Max-1 está en M13 Devoluciones** (su plan espera visto bueno del dueño); no toca despachos.

## Recordatorios
Suite COMPLETA antes de cualquier push (la baseline la fija el main del día — la última
conocida tuya fue 1216 y ya está vieja). Blade tocado → build + grep superset. Conflictos con
`git checkout origin/main -- <archivo>`, nunca con `>` (el `>` de PS 5.1 mete BOM y revienta
Vite). Parte al buzón → doble llave.

CIERRE: parte a docs/fleet/buzon/partes/ + push.
