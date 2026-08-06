# PLAN-M04 · Inventario real — bodegas y sucursales FULL PARAMÉTRICAS

> **Estado: VIGENTE (visto bueno del dueño 2026-08-06)** — GO de F1 emitido a Max-1
> (dictado v36). Insumos: D-003 resuelta parcial (anexo 2 de `docs/DECISIONES.md`) +
> **corrección de rumbo del dueño (06-ago)**: nada de clasificación congelada en seeder —
> bodegas y sucursales se agregan/editan/eliminan DESDE LA APP, todo paramétrico, con
> guardas operacionales. La v1 de este plan queda superseded. Autor: Director.

## 0. El problema en una frase

La app VE el inventario (espejo `bodegas`+`stocks`, sync cada 15 min) pero no lo
ENTIENDE ni lo ADMINISTRA: no sabe qué bodega es de qué sucursal, las 16 heredadas no se
pueden ordenar desde la app, y los movimientos locales (M11 producción, M13 devoluciones)
apuntan a bodegas de texto libre.

## 1. Principios

1. **Bsale sigue siendo master del stock.** Los DTE descuentan stock EN Bsale; una bodega
   que Bsale no conoce no puede recibir documentos. Todo lo paramétrico de este plan es
   **local y aditivo**; el sync (`StockSync`) no se toca.
2. **Paramétrico ≠ divergente.** La app administra la CAPA LOCAL (clasificación,
   visibilidad, ciclo de vida operacional); la existencia física de la office y el stock
   siguen viniendo de Bsale hasta que D-005 habilite push.
3. **Delete duro NUNCA en bodegas**: mientras la office exista en Bsale, el sync la
   re-crearía a los 15 minutos; y el histórico (stocks, movimientos) la referencia.
   «Eliminar» = **baja lógica con guarda de traslado** (§3.2).
4. **El kardex local es la verdad de la app, listo para empujar** (contrato ya probado 2×:
   `ProduccionMovimiento`, `DevolucionMovimiento`). El empuje real queda [B:D-005].

## 2. Modelo de datos

Columnas nuevas en `bodegas` (migración aditiva; `StockSync::upsertBodega` hace upsert por
campos explícitos y no las toca):

| Columna | Tipo | Contenido |
|---|---|---|
| `sucursal_id` | FK nullable → `sucursales` | NULL = transversal (MERMAS, RESERVA) |
| `proposito` | string(191) | `fisica` · `virtual_operativa` · `transito` · `insumos` · `taller` · `cerrada` |
| `en_operacion` | boolean default true | false = invisible en pantallas operativas |
| `clasificacion_confirmada` | boolean default false | false en las 5 filas [B] de D-003 |
| `estado_baja` | string nullable | null · `pendiente_traslado` · `dada_de_baja` |

Tabla nueva `bodega_traslados` (orden de traslado del wizard de baja): bodega origen,
bodega destino, estado (`pendiente`/`completado`/`anulado`), quién la pidió, timestamps +
`bodega_traslado_items` (producto, cantidad al momento de la orden). Varchar sí o sí
explícito ≤191.

Seeder `ClasificacionBodegasSeeder`: **solo PRE-CARGA inicial** de la auditoría D-003
(matchea por `bsale_office_id`, NUNCA por nombre; idempotente: no pisa filas ya
confirmadas). Después manda la UI.

## 3. Fases

### F1 · CRUD paramétrico + clasificación (GO candidato: Max-1)

- **P-M04-10 · Bodegas editables.** La pantalla admin de bodegas
  (`resources/views/admin/bodegas`, hoy `only(['index','show'])`) gana `edit`/`update`
  (permiso `manage sucursales`, el mismo de sucursales — sin permiso nuevo, lección M13):
  sucursal, propósito, en_operacion, alias local. Badges: propósito, «por confirmar»
  ([B] de D-003), «NUEVA — por clasificar», «EN BAJA». Migración + seeder + scopes
  (`enOperacion()`, `deSucursal()`). Cambios auditados (modelo ya Auditable).
- **P-M04-11 · Guardas de sucursales completadas.** `SucursalController::destroy` ya
  bloquea por usuarios y máquinas; se suman **bodegas asignadas** y **hojas de ruta**
  (mismo patrón: mensaje que dice QUÉ reasignar). CRUD de sucursales ya existe — no se
  construye, se completa.
- **P-M04-12 · Adopción automática de bodegas nuevas.** Bodega creada en Bsale → el sync
  la trae → entra con `clasificacion_confirmada=false` + badge «NUEVA — por clasificar»
  + notificación (M15) a quienes tengan `manage sucursales`. Botón «Agregar bodega» en la
  app: instructivo + aviso de que aparecerá sola al próximo sync (si D-005 revela que la
  API permite crear offices, el botón pasa a crear directo — F4).
- Candados mínimos: seeder 2× = mismo estado; sync simulado no pisa clasificación local;
  403 sin permiso; las 6 muertas de D-003 invisibles en selectores operativos; alias
  varchar(191).

### F2 · Wizard de baja con guarda de traslado (el pedido literal del dueño)

- **P-M04-20 · Dar de baja.** Al pedir la baja de una bodega:
  - **stock 0 en todo producto** → baja inmediata (`estado_baja=dada_de_baja`,
    `en_operacion=false`).
  - **stock ≠ 0** → wizard: lista los ítems con existencias (desde el espejo `stocks`),
    exige bodega destino, crea la **orden de traslado** (imprimible/exportable — reusar
    el escritor Excel de la casa) y deja la bodega `pendiente_traslado` (fuera de
    selectores operativos, visible en admin con su badge).
  - **Cierre automático**: cuando un sync posterior confirme stock 0, la baja se completa
    SOLA y la orden pasa a `completado` (+ notificación M15 al solicitante). Mientras
    D-005 no habilite push, el traslado físico se ejecuta en Bsale; cuando lo habilite,
    el MISMO wizard ejecuta consumption+reception por API sin cambiar la UX.
- Candados: bodega con stock no puede saltarse el wizard (mutado); baja no rompe hojas de
  ruta/movimientos históricos; el sync que trae stock nuevo a una `pendiente_traslado`
  NO la revive sin aviso (notifica «llegó stock a una bodega en baja»).

### F3 · Kardex unificado (lectura)

- **P-M04-30** Vista «Movimientos de inventario»: une los 2 kardex locales (producción +
  devoluciones) por bodega/producto/fecha, con el idioma mes/año de los informes de la
  casa, y muestra la brecha local-vs-espejo por producto. El `bodega_destino` de M11/M13
  pasa de texto libre a **select de bodegas vivas** (FK nuevo nullable; el texto
  histórico se conserva, no se migra).

### F4 · Push/creación por API — **FUERA de este plan** `[B:D-005]`

- `receptions`/`consumptions` (traslado real) y creación de offices por API. Preguntas
  sumadas a D-005. Ni se diseña hasta que soporte Bsale responda.

## 4. Hecho cuando

- [ ] Las 16 bodegas clasificadas y EDITABLES en producción; las 6 muertas invisibles en
      operación (dadas de baja vía el flujo real, no por seeder).
- [x] Sucursales: eliminar con bodegas/hojas asignadas bloquea con mensaje útil —
      **QA del dueño en producción (06-ago, celular): funcionando**.
- [ ] Una bodega con stock NO se puede dar de baja sin orden de traslado; una con stock 0
      sí, al tiro; el cierre automático post-sync funciona (probado con sync simulado).
- [ ] Bodega nueva en Bsale aparece sola con su badge y su notificación.
- [ ] M11 y M13 registran movimientos contra bodega real (select), no texto.
- [x] QA del dueño (parte F1): clasificación en producción probada desde el celular —
      **APROBADO 06-ago, «todo funcionando»**. Quedan para F2 los tramos del wizard
      (dar de baja con/sin stock) y la prueba opcional de crear bodega en Bsale.

## 5. Preguntas abiertas

- Las 5 [B] de D-003 (viajan en la ronda 2 — no bloquean F1).
- A D-005 (soporte Bsale): ¿se pueden crear offices por API? ¿el traslado se modela como
  consumption+reception y es atómico?
