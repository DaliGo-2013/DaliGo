# Parte Max-1 → Director · 2026-08-06 · v36 P-M04-10/11/12 HECHOS — F1 de M04 completa, espera doble llave

**Rama:** `feature/m04-bodegas-parametricas` @ `ecc7092` (3 commits: lote `9e23e62` + bundle `ebf43eb` + docs) · nace de main `204ceb9` (fresco de hoy). Ojo: main ya avanzó a `0004bdd` (visor de Marcos) mientras cerraba — la rama NO lo trae; re-fetch antes del merge, como siempre.
**Suite:** baseline de main fresco **1588 / 11.481, cero rojos** (fijada ANTES de empezar, corrida limpia de hoy) → la corrida completa de la rama está **en curso al despachar este parte**; la cifra final viaja en el commit siguiente sobre este mismo archivo (el árbol queda intacto durante la corrida). Las baterías del lote y todos los candados estructurales ya corrieron verdes por filtro (57 + 83).
**Bundle:** `app-rbQZrEKh` — superset verificado contra el bundle de HEAD: **0 pérdidas, +1 selector** (`ms-1.5`); `git show --stat` confirma que viajó en su commit.

## Qué quedó construido (F1 completa, PLAN-M04 §3)

- **P-M04-10 · Bodegas editables.** Migración aditiva (6 columnas locales, varchar explícito ≤191; `estado_baja` ya creada para F2 como pediste). `Bodega::PROPOSITOS` + scopes `enOperacion()`/`deSucursal()` + relación con Sucursal. `ClasificacionBodegasSeeder` con el veredicto del anexo 2 por `bsale_office_id` (los 16 ids del catastro 02-07), en `DatabaseSeeder` tras `SucursalSeeder` — **jamás pisa una fila confirmada**; en BD sin espejo es no-op. Edit/update bajo `manage sucursales` (cero permisos nuevos: `RoleMatrixSeedTest` intacto); index/show siguen en `manage productos`. Badges por partial compartido: propósito, «por confirmar», «nueva — por clasificar», «en baja» (F2 la activará). Subtítulos «(solo lectura)» sincerados.
- **P-M04-11 · Guardas de sucursales COMPLETAS.** Las 2 dictadas (bodegas, hojas de ruta) **+ 2 que encontró el sweep**: `devoluciones` y `traslados_servicio` (origen/destino) también son RESTRICT y hoy daban **500 crudo** al eliminar — mismo patrón de 3 líneas, mensajes que dicen qué reasignar. Decisión declarada abajo. + el test de la guarda de máquinas que existía sin cobertura, + `Js::from` en el confirm de eliminar (el bug del apóstrofo de la bitácora 28-07 estaba vivo en sucursales).
- **P-M04-12 · Adopción automática.** Gancho mínimo en `StockSync::run()` fase 1 (`wasRecentlyCreated` → acumular → avisar tras el `withoutAuditing`); la lógica de espejo NO se tocó. Evento `bodega.nueva` registrado en los 4 puntos M15 (EVENTOS, urlDestino → **la ficha de clasificación**, urlDestinoPara con `can('manage sucursales')`, plantilla como clave nueva sin one-shot + sumada a `CLAVES_M15`). Try/catch POR destinatario (patrón vehículos): un correo malo no tumba la sync. Botón «Agregar bodega» = modal instructivo (F4 lo vuelve form real si D-005 lo permite). Contador `nuevas` en stats y en la tabla del comando.

## Candados del dictado — los 6, con mutación donde correspondía

1. **Seeder 2× = mismo estado con una confirmada en el medio** ✓ (`ClasificacionBodegasSeederTest`, 5 tests: confirmo SANTA ROSA con valores propios → re-seed → sobreviven).
2. **Sync no pisa `sucursal_id`/`proposito`/`en_operacion`/`alias`** ✓ MUTADO: metí `proposito` al array del upsert → rojo exacto.
3. **403 sin `manage sucursales`** ✓ con el caso que separa: un usuario CON `manage productos` ve el inventario pero NO clasifica (y no ve los botones — control positivo con admin).
4. **Las 6 muertas fuera del scope operativo** ✓ (16 bodegas sembradas → `enOperacion()` = 10 exactas). Nota: hoy NO existe ningún selector de bodegas en la app (grep exhaustivo, 0 hits) — el scope es el contrato para los que vengan (F2/F3).
5. **Sucursal con bodegas → destroy bloqueado con mensaje útil** ✓ MUTADO: sin la guarda, el test cae con la `QueryException` del RESTRICT — el 500 exacto que la guarda previene.
6. **Badge «por confirmar» desaparece al confirmar** ✓ MUTADO: quité el `clasificacion_confirmada => true` del update → rojo.

Orden de mutación respetado (fix → verde → COMMIT → mutar → rojo → `git checkout --` → grep del marcador, 3/3).

## Decisiones declaradas (criterio propio, sweep hecho, alternativa nombrada)

1. **Guardar = confirmar.** El update setea `clasificacion_confirmada=true` siempre (por eso `proposito` es required). Alternativa descartada: checkbox/botón aparte de «confirmar» — un paso más para el mismo acto humano.
2. **P-M04-11 ampliado de 2 a 4 guardas.** El dictado nombraba bodegas + hojas; el sweep encontró que `devoluciones` (mi propio módulo) y `traslados_servicio` también revientan con 500. El espíritu del paso es «destroy jamás da 500»; eran 3 líneas c/u con test. Si preferís revertir las 2 extra, es un revert limpio de 2 ifs + 2 tests.
3. **Los [B] se siembran con la hipótesis del anexo** (SANTA ROSA→Mirador/insumos, CONTENEDORES→Abate/transito, etc.) y `confirmada=false`: el badge marca que es hipótesis; pre-cargar en blanco habría sido tirar información del Excel.
4. **«Una vez por bodega» = `wasRecentlyCreated`**, sin tabla `bodega_avisos`: para un catálogo de 16 filas el molde de `vehiculo_avisos` es overkill. Trade-off declarado: tras un restore de BD el aviso podría repetirse una vez.
5. **Entrada a editar en el `show`**, no lápiz en la fila: el candado vivo `test_la_fila_de_la_bodega_enlaza_directo_a_su_stock` prohíbe un segundo control en la fila (doctrina del dueño 03-08).
6. **Drive-by `Js::from`** en el confirm de sucursales: el barrido del 28-07 no alcanzó esta vista y el destroy que este paso endurece se enviaba SIN confirmación ante un apóstrofo.

## Hallazgos para tu radar (no ejecutados)

- **RUTA-MAESTRA tiene colisión de numeración**: el `P-M04-10` viejo de E4 (tests de concurrencia) ≠ el nuevo de PLAN-M04 v2. Marqué los 3 pasos como «(v2)» bajo E3 con nota fechada y absorbí el viejo `P-M04-01`; la reestructura E3/E4 contra el plan v2 es tuya.
- `devolucion_bodega_reingreso` sigue siendo texto libre `'CONTENEDORES'` — y CONTENEDORES es una [B] que podría morir (¿dónde entra la importación?). El select real es F3; si la ronda 2 mata CONTENEDORES, esa config queda apuntando a una bodega cerrada.
- Los otros 3 FKs `nullOnDelete` de sucursales (`ordenes_servicio`, `lotes_servicio`, `dte_emitidos`) no revientan pero pierden trazabilidad en silencio al eliminar — `dte_emitidos` es documento tributario. No los toqué (no eran del paso); queda anotado.

**Pendiente de mí:** la cifra de la suite (commit siguiente). Después, nada: la rama espera la **doble llave**. QA sugerido para el dueño (del «Hecho cuando» del plan): clasificar una [B] desde el celular, intentar eliminar una sucursal con bodegas, y crear una bodega de prueba en Bsale para verla llegar sola con su aviso.
