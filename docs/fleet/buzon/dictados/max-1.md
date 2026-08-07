# Dictado vigente — Max-1 (Forjador A, stream 1)
> Emitido por el Director el 2026-08-07 (v40 — PAUSA LEVANTADA por el dueño: GO P-M11-10, receta + backflush de preformas). Manda sobre lo anterior.

MODELO: Opus 4.8 · high.

## La pausa se levantó — nueva prioridad del dueño (vía Luis): M11 Producción, versión final

**Lee primero `docs/planes/PLAN-M11-FINAL.md` (VIGENTE, visto bueno del dueño 07-ago)**
y su insumo `docs/investigacion/2026-08-07--BENCHMARK-M11-RECONCILIADO.md` (benchmark de
doble vía: 14+ sistemas; nuestras ventajas y gaps verificados por dos investigaciones
independientes).

Tu stream es **A (backend/kardex)**: recetas, backflush, OEE, moldes. Max-2 lleva el
stream B (PWA/soplador) EN PARALELO — territorio en PLAN §3. **Frontera caliente:**
`ProduccionReporte` lo toca primero Max-2 (campos de paradas); tú lo LEES, no le agregues
columnas en este lote. Lotes cortos, push temprano, re-fetch religioso: somos DOS
forjadores activos + Marcos.

## 🟢 GO — P-M11-10 · Receta paramétrica + backflush al aprobar (F1, stream A)

El pendiente nº1 histórico del tracker («descuento de preforma»), diseñado en PLAN §4:

- **Tabla `recetas`**: producto_id (botellón) → componentes (preforma, tapa) con
  cantidad decimal(14,4), editable en UI (permiso de producción/admin EXISTENTE — cero
  permisos nuevos), `confirmada` boolean. **Seeder con la hipótesis [B]: 1 preforma +
  1 tapa = 1 botellón, confirmada=false** — la respuesta de Luis será un ajuste de datos
  vía UI, no de código (mismo patrón que la clasificación de bodegas D-003).
- **La regla**: al APROBAR un reporte (`ProduccionController::aprobar`) → movimiento de
  kardex que descuenta componentes = **(buenos + merma) × receta** — la merma TAMBIÉN
  consumió preformas; descontar solo buenos infla el inventario teórico (lección
  Microsoft Dynamics citada en el benchmark). Detalle de consumo visible en el reporte
  aprobado — **cantidades, jamás costos** (principio §1.3 del plan).
- **Idempotencia y reversa**: devolver un reporte JAMÁS genera movimiento; aprobar tras
  devolución no duplica (guard por reporte, estilo tu propio patrón de M13); receta
  editada afecta solo aprobaciones futuras.
- El kardex a tocar es el de producción (`ProduccionMovimiento` como molde conceptual —
  mismo contrato local-listo-para-empujar [B:D-005]).

### Candados mínimos
1. MUTADO: quitar la merma de la fórmula (solo buenos) → rojo exacto.
2. Devolución sin movimiento; aprobar→devolver→aprobar = UN solo movimiento.
3. Receta editada no re-escribe movimientos pasados.
4. Seeder 2× idempotente; fila confirmada no se pisa.
5. 403 sin permiso en el CRUD de recetas.
6. El soplador no ve costos en ninguna vista nueva (test de contenido).

## Recordatorios
Rama nueva desde main FRESCO; **suite COMPLETA de main fresco ANTES de empezar** para
fijar TU baseline (última del Director: 1655/12.067 en `237185b`, main ya avanzó con
Marcos). Suite completa antes del push. Blade tocado → build + superset. Conflictos con
`git checkout origin/main --`, nunca `>` (BOM). varchar ≤191. Parte al buzón → doble
llave.

CIERRE: parte a docs/fleet/buzon/partes/ + push. F2 (OEE) espera doble llave de F1.
