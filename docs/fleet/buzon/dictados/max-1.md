# Dictado vigente — Max-1 (Forjador A, stream 1)
> Emitido por el Director el 2026-08-06 (v38 — GO F2 de PLAN-M04: wizard de baja con orden de traslado). Manda sobre lo anterior.

MODELO: Opus 4.8 · high.

## Antes de nada: tu F1 tiene QA del dueño APROBADO

Probó la clasificación en producción desde su celular el mismo día del merge: «todo
funcionando». F1 cerrada de punta a punta en un día. El GO de F2 vino con la opción
explícita del dueño de NO esperar la ronda 2 de Luis — el wizard no depende de qué
bodega muera.

## 🟢 GO — P-M04-20 · el wizard de baja (F2 de PLAN-M04, el pedido literal del dueño)

**Lee `docs/planes/PLAN-M04.md` §3-F2** (VIGENTE). El corazón: «eliminar» una bodega
jamás pierde stock ni da 500 — o está vacía y muere al tiro, o el sistema te obliga a
decidir a dónde va lo que contiene.

### Comportamiento
- **Stock 0 en todos los productos** → baja inmediata: `estado_baja='dada_de_baja'` +
  `en_operacion=false`. (La columna ya existe — tu migración F1 la creó; NO hay migración
  nueva salvo las 2 tablas de traslados.)
- **Stock ≠ 0** → wizard:
  1. Lista los ítems con existencias (desde el espejo `stocks`: real/reservado/disponible).
  2. Exige bodega DESTINO viva (scope `enOperacion()`, excluida la propia).
  3. Crea la **orden de traslado**: `bodega_traslados` (origen, destino, estado
     `pendiente|completado|anulado`, solicitante, timestamps) + `bodega_traslado_items`
     (producto, cantidad AL MOMENTO de la orden — foto, no referencia viva). Varchar ≤191.
  4. Deja la bodega `estado_baja='pendiente_traslado'`: fuera de selectores operativos,
     visible en admin con su badge «EN BAJA» (el badge ya existe de F1).
  5. Orden imprimible/exportable — **reusa el escritor Excel de la casa** (el de Marcos,
     el mismo de los informes).
- **Cierre automático**: cuando un sync posterior confirme stock 0 en la bodega
  `pendiente_traslado` → baja se completa SOLA (`dada_de_baja`) + orden a `completado` +
  notificación M15 al solicitante. Evento nuevo o reuso de `bodega.nueva` como molde — a
  tu criterio declarado (sweep + alternativa nombrada, como siempre).
- **Stock nuevo llegando a una `pendiente_traslado`** → NO la revive: notificación
  «llegó stock a una bodega en baja» (mismo destino M15).
- El traslado FÍSICO hoy se ejecuta en Bsale (D-005 pendiente) — la orden es el puente.
  Cuando D-005 habilite push, el mismo wizard ejecutará por API (F4, no tuyo).

### Candados mínimos
1. Bodega con stock NO puede saltarse el wizard (MUTADO: quitar el check → rojo).
2. Baja con stock 0 funciona al tiro y no toca `stocks` ni históricos.
3. Cierre automático post-sync: simulado 2× (idempotente, notifica UNA vez).
4. Stock nuevo en bodega en baja → notifica, no revive (mutado si es barato).
5. 403 sin `manage sucursales` en todo el flujo de baja.
6. Orden con foto de cantidades: cambiar el stock DESPUÉS de la orden no altera la orden.
7. Destino no puede ser bodega muerta/en baja ni la misma origen.

## Territorio
- **Marcos MUY activo** (3+ pushes hoy, simulador; hubo outage de GitHub Actions — lee
  I-09 del tablero: rojos con «job not acquired by Runner» = infra, no código; máx 1
  re-run en cola).
- **Max-2** en pausa — sin cruce.

## Recordatorios
Rama nueva desde main FRESCO. **Suite COMPLETA de main fresco ANTES de empezar** para
fijar TU baseline (la última del Director fue 1614/11.676 en `276a54f` y main ya avanzó
con Marcos — la cifra del día la fijas tú). Suite completa de nuevo antes del push. Blade
tocado → build + grep superset. Conflictos con `git checkout origin/main -- <archivo>`,
nunca con `>` (BOM de PS 5.1). Parte al buzón → doble llave.

CIERRE: parte a docs/fleet/buzon/partes/ + push.
