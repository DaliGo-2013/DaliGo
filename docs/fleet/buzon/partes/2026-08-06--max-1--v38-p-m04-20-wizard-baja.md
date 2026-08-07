# Parte Max-1 → Director · 2026-08-06 · v38 P-M04-20 HECHO — F2 wizard de baja completa, espera doble llave

**Rama:** `feature/m04-baja-bodegas` @ `dfdffcd` (2 commits: lote `1b3d9bf` + docs) · nace de main `b8aed56` (fresco de hoy).
**Suite:** baseline de main fresco **1615 / 11.711, cero rojos** (fijada ANTES) → rama **1635 / 11.966, cero rojos** (corrida completa sobre el árbol final, intacto durante la corrida). **El delta cuadra exacto:** +20 tests = los míos (11 BajaBodegaTest + 5 TrasladoBodegaExcelTest + 4 BsaleStockSyncTest) — cero sorpresas.
**Bundle:** `npm run build` reprodujo el bundle **byte-idéntico** (cmp exacto, 738 selectores = 738, 0 pérdidas 0 nuevas): las vistas F2 usan solo utilidades ya existentes → **sin commit de build porque no hay diff** (el candado superset corrió igual).

## Qué quedó construido (P-M04-20, el pedido literal del dueño)

- **El corazón**: «eliminar» una bodega jamás pierde stock ni da 500. Vacía (ninguna fila con `stock_real ≠ 0`) → `dada_de_baja` + `en_operacion=false` al tiro, sin orden. Con stock → el wizard OBLIGA a elegir destino vivo y crea la **orden de traslado** con la FOTO (cantidad + nombre + sku denormalizados — el documento no cambia si el catálogo renombra o el stock sigue moviéndose) y deja la bodega `pendiente_traslado`.
- **Cierre automático**: `StockSync` post-fase-2 llama `BajaDeBodegas::conciliarConEspejo()` (try/catch: la sync jamás muere por la capa local): stock 0 confirmado → orden `completado` + bodega `dada_de_baja` + **aviso M15 al solicitante**. Idempotente por transición de estado (la completada no se vuelve a mirar).
- **Stock nuevo en bodega en baja**: detecta stock POR ENCIMA de la foto (drenar baja; subir = llegó) → aviso **una sola vez por orden** (`aviso_stock_nuevo_at`) y la bodega **NO revive**. Banner rojo en la ficha de la orden.
- **Anular** (el estado estaba en el PLAN §2): orden `anulado` + la bodega vuelve a operación (`estado_baja=null`) — `pendiente_traslado` no es un callejón sin salida.
- **Excel de la orden** sobre el escritor de la casa (`EscritorXlsx`/`FilasXlsx`, molde `hojaAvance`): título + origen→destino/solicitante/fecha + items + TOTAL, cabecera congelada, headers de la casa (`no-store, private`, nombre con `FechaNegocio`). El botón con `x-secondary-button-link` + `document-text` (molde vehículos).
- **UI**: wizard de una pantalla con dos estados (`bodegas/{id}/baja`), ficha de la orden con badge/foto/acciones, botón «Dar de baja» en el show (@can, oculto si ya en baja), tarjeta de órdenes en el show de la bodega (descubrimiento — sin index de órdenes a propósito, YAGNI hasta F3). Rutas con el doble candado (literal antes + `whereNumber`), todas bajo `manage sucursales` — cero permisos nuevos, `RoleMatrixSeedTest` intacto.
- **M15**: 2 eventos nuevos (`bodega.baja_completada`, `bodega.stock_en_baja`) en los 4 puntos + `CLAVES_M15`; destino de la fila = la ficha de la orden; claves nuevas sin one-shot.
- **`enOperacion()` ampliado**: `whereNull('estado_baja')` — el scope es el contrato; los candados F1 siguen verdes sin tocarlos.

## Los 7 candados del dictado — todos, 3 mutados

1. **Con stock NO se salta el wizard** ✓ MUTADO: `if (true)` en el check de vacío → baja pese a stock → rojo exacto.
2. **Stock 0 → baja al tiro sin tocar `stocks` ni históricos** ✓ (foto byte-igual de `stocks` + la fila Bodega persiste — jamás delete).
3. **Cierre automático 2× idempotente, notifica UNA vez** ✓ MUTADO: quité la transición a `completado` → la 2ª corrida re-completa y re-avisa → rojo. (La idempotencia ES la transición de estado; el recheck bajo lock es el cinturón para corridas concurrentes.)
4. **Stock nuevo → notifica una vez, no revive** ✓ MUTADO: quité el guard del timestamp → segundo aviso → rojo. + test de que DRENAR no dispara el aviso (bajar de la foto es el camino feliz).
5. **403 sin `manage sucursales` en todo el flujo** ✓ (las 5 rutas, con el usuario `manage productos` que separa ver de administrar).
6. **La orden es FOTO** ✓ (cambiar el stock después no altera los items).
7. **Destino inválido rechazado** ✓ (misma origen / muerta / en baja / inexistente — los 4 casos en loop con mensaje).

Orden de mutación 28-07 respetado (commit → mutar → rojo → `git checkout --` → grep del marcador, 3/3).

## Decisiones declaradas (sweep hecho, alternativa nombrada)

1. **El scope es el contrato, no el flag**: `pendiente_traslado` NO toca `en_operacion` (anulada la orden, la bodega vuelve sola); `dada_de_baja` sí lo apaga (final, PLAN §F2 textual). Alternativa descartada: apagar `en_operacion` al pendiente → habría que adivinar el valor previo al anular.
2. **Eventos M15 NUEVOS** (no reuso de `bodega.nueva`): semántica distinta + el panel admin filtra por evento. El molde sí es el de F1, como sugería el dictado.
3. **Destinatario = el solicitante** (quien pidió la baja espera el cierre); solicitante eliminado → fallback `manage sucursales` (probado con test).
4. **«Una vez por orden» vía `aviso_stock_nuevo_at`** en la orden (sin tabla de avisos): el cron corre ×96/día, el guard es 1 timestamp. Detección: stock POR ENCIMA de la foto o producto sin ítem — drenar jamás avisa.
5. **La foto denormaliza nombre+sku**: la orden impresa no depende del catálogo vivo.
6. **«Imprimible/exportable» = el Excel** (el dictado lo dice); el único patrón print del repo son etiquetas QR, no documentos.
7. **Fechas del Excel como texto en la cabecera**: documento puntual de una orden, sin columnas de fecha filtrables — el contrato «fechas como fechas» aplica a tablas de datos.
8. **Criterio de vacío = `stock_real ≠ 0` en alguna fila** (la reserva vive dentro del real; el espejo no produce reservado sin real).

## Hallazgos para tu radar (no ejecutados)

- El **destino** de una orden pendiente podría a su vez pedirse de baja (A→B pendiente, y B pide baja hacia C): hoy nada lo detecta como ciclo — el sync resolvería ambas si el stock llega a 0, pero la orden A→B apuntaría a una bodega en baja. Caso de borde real solo con bajas simultáneas; F3 (kardex) es el lugar natural para vigilarlo. Anotado, no construido.
- `resources/views/admin/traslados/show.blade.php:41` (ST, territorio Marcos) usa `text-amber-700` — fuera de la paleta de 4. Solo lo anoto; no toqué su archivo.

**Pendiente de mí:** nada. La rama espera la **doble llave**. QA sugerido para el dueño (del «Hecho cuando» del plan): dar de baja una muerta VACÍA (al tiro), intentar dar de baja una con stock (ver el wizard + descargar el Excel de la orden), y anularla para verla volver.
