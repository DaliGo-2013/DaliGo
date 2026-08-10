# Parte de Max-1 — 2026-08-08 · Dictado v40: P-M11-10 HECHO (receta paramétrica + backflush al aprobar)

> Forjador A, stream 1 · rama `feature/m11-recetas-backflush` (commits `f654a23` lote + `655ca62` docs) — **espera doble llave**.
> F1 de PLAN-M11-FINAL, stream A. El pendiente nº1 histórico del tracker («descuento de preforma») queda construido.

## Cifras

| | |
|---|---|
| Baseline (main fresco `1dfc16f`, antes de empezar) | **1711 verdes / 12.269 aserciones** |
| Suite completa de la rama (árbol intacto) | **1729 verdes / 12.462 aserciones, cero rojos** |
| Delta | +18 tests exactos = RecetaBackflushTest (9) + RecetaSeederTest (2) + RecetaCrudTest (7) |
| Bundle | `app-CrCK5Y1Q.css` → `app-DxUyeYa_.css`; superset verificado: control positivo 602 tokens, **0 perdidos**, 1 clase nueva en uso (`lg:grid-cols-5`); commiteado en el lote (verificado `git show --stat`) |

## Qué se construyó (los 6 candados del dictado, todos con test)

1. **La fórmula** — al aprobar, el kardex descuenta componentes = **(buenos + merma) × receta**. Candado `test_el_consumo_por_receta_incluye_la_merma` **MUTADO**: fórmula a solo-buenos → rojo exacto (100→90 y 200→180), y de paso el contrato histórico (`ProduccionKardexTest::test_aprobar_genera_movimientos_exactos`) también se puso rojo — doble discriminación.
2. **Devolución sin movimiento; aprobar→devolver→re-enviar→aprobar = UN solo set** — `test_devolver_no_genera_y_aprobar_tras_devolucion_no_duplica` (el guard `movimientos()->exists()` + lock de `aprobar()` ya existían; el test lo fija con receta presente).
3. **Receta editada no re-escribe el pasado** — `test_editar_la_receta_no_reescribe_movimientos_pasados` (kardex = snapshot, byte-igual tras editar cantidad Y componente).
4. **Seeder 2× idempotente; lo editado JAMÁS se pisa** — `RecetaSeederTest` (2): 8 filas estables, `firstOrCreate` no actualiza (ni confirmadas ni borradores editados), y **cero productos creados**.
5. **403 sin permiso** — `test_recetas_requieren_permiso_de_produccion`: soplador Y usuario con `manage productos` (la separación de M04-F1): GET → redirect al Inicio con aviso, PUT → 403 crudo.
6. **Ni un costo a la vista** — `test_las_pantallas_de_recetas_no_muestran_costos`: regex de palabra completa (lección `\brut\b`) sobre el texto percibido de index/edit: sin precio/costo/valor/CLP/UF ni montos `$`.

**Mutación 3 (extra):** quitar `'Consumo de tapa'` de `ETIQUETAS` → `test_el_kardex_muestra_consumo_de_tapa` rojo. **Mutación 2 (extra):** `(int)` truncando en vez de `round()` → `test_redondeo_half_up_sobre_el_agregado` rojo (7≠8). Las 3 mutaciones post-commit, restauradas con `git checkout --` + grep del marcador (orden 28-07).

## Decisiones de criterio (declaradas)

1. **Esquema:** filas `(producto_id botellón, rol 'preforma'|'tapa', componente_id NULLABLE, cantidad decimal(14,4), confirmada)` + unique `[producto_id, rol]`. Anclada al **producto** (el PLAN §2 lo dice textual; la BOM del benchmark es por producto; dos tipos → mismo producto no pueden divergir). `componente_id` nullable porque la hipótesis del seeder no puede adivinar el SKU en un catálogo espejado de miles — Luis enlaza por UI (D-003).
2. **La asignación GANA en preforma:** producto del movimiento = `asignacion?->preforma_id ?? componente_id` (la asignación es la verdad física del turno; la receta aporta la CANTIDAD). La UI no ofrece selector de componente para preforma — es el knob que no se puede acertar.
3. **Tipo nuevo `consumo_tapa`** (no un genérico): el histórico ya dice `consumo_preforma`, dos filas «sin producto» serían indistinguibles, y reusar `consumo_preforma` para tapa rompía la identidad consumo==producción+merma del candado M-1. Chips del kardex 4→5 (grilla `grid-cols-2 sm:grid-cols-3 lg:grid-cols-5`).
4. **`confirmada` es badge, NO gate:** la receta aplica confirmada o no (como la clasificación de bodegas). Fijado en `test_tapa_sin_componente_registra_movimiento_sin_producto` (hipótesis sin confirmar opera igual).
5. **Fallback = receta implícita `{preforma: 1}`, UN solo camino por tanda:** sin rama legacy paralela, el doble conteo es imposible por construcción. Prueba dura: **`ProduccionKardexTest` (14) quedó verde SIN TOCAR** — el fallback es byte a byte el comportamiento histórico. Reporte mixto (tipo con receta + tipo sin) probado con conteo exacto.
6. **Redondeo:** columna `cantidad` sigue integer (no se toca kardex histórico); `(int) round(Σ unidades × factor)` UNA vez sobre el agregado, nunca por tanda. Documentado en el docblock para que nadie lo «arregle».
7. **Preview = kardex por construcción:** `planParaReporte()` es la fuente ÚNICA que consumen el preview del reporte y `generarParaReporte()`. De paso murió una divergencia PREEXISTENTE: el preview usaba `$reporte->total` (ajustable por el jefe) mientras el kardex usaba las tandas — `test_el_preview_muestra_lo_que_generara_no_los_totales_ajustados` lo fija (total pisado a 500 → preview sigue mostrando −100).
8. **CRUD propio + ítem de menú «Recetas»** con rutas `admin.recetas.*` FUERA del prefijo que el ítem `produccion` enumera (candado doble aria-current) — SidebarTest/HigienePermisos/MenuPrincipal pasaron solos, cero permisos nuevos. Selector de tapa con el idioma `preformasParaSelector` (categoría `%tapa%`, fallback activos, sin dañadas) y validación con el MISMO scope (regla M-3).
9. **El seeder NO crea productos** (nada de TEST-TAPA fantasma): el candado `assertSame(6 TEST-%)` del seeder de testeo quedó intacto. Verificado en la BD local con la cadena real del deploy (`migrate` + `db:seed`): 8 recetas, 4 botellones, todas «por confirmar».
10. **`activa` del PLAN §2 omitida** (YAGNI declarado): quitar la cantidad de tapa borra la fila — sin flag muerto. `confirmada` sí va.
11. **Un test existente amoldado en el mismo push** (doctrina PwaTest 13-07): `ProduccionTest::test_procedencia_sin_preforma_no_oculta_el_aviso_del_kardex` fijaba el texto del preview viejo con un fixture SIN tandas; el preview nuevo pinta las líneas REALES del plan. Se le dio una tanda al fixture y la señal «(sin preforma asignada)» se conservó — ahora diferenciada por tipo (preforma → se corrige en la asignación; tapa → «(sin producto enlazado)» → se enlaza en la receta).

## Docs (mismo push)

- `PlanProyecto` M11: «Descuento de preforma contra el stock real» pasó de `falta` a `hecho` (literal intacto — está clavado por assert) con el detalle de la receta; `falta` ahora: meta del día, GP, OEE/paradas en curso.
- RUTA-MAESTRA: bloque nuevo **PLAN-M11-FINAL** (F1–F3, 2 streams) al final de la sección F2 con P-M11-10 `[x]` y los 6 pasos restantes `[ ]`; fila M11 del tracker con el hecho anotado y **el 75% conservado a propósito** — la fase F1 landea con AMBOS streams y el recálculo a 85% es del Director.

## Para el radar del Director

- **Frontera respetada:** `ProduccionReporte` no fue tocado (cero columnas, cero líneas); las vistas del soplador (`resources/views/produccion/*`) tampoco. Mi único roce con territorio compartido fue `ProduccionTest` (decisión 11, test-only).
- El preview del reporte ahora crece con las tandas (una línea por movimiento real). Si Max-2 agrega paradas a esa pantalla, no chocamos: toqué solo el bloque del kardex.
- La hipótesis [B] queda operativa desde el deploy: las recetas nacen «por confirmar» y **el flujo actual no cambia en nada** hasta que alguien confirme una receta con tapa (el fallback es el comportamiento de siempre). La respuesta de Luis es un ajuste de datos vía `/admin/recetas`, como se diseñó.

## QA sugerido al dueño (celular, staging/producción)

1. **Producción → Recetas**: ver los 4 botellones TEST con badge «por confirmar» → abrir uno → poner tapa×1 (o dejarla vacía) → guardar → el badge desaparece.
2. Asignar producción → tandas → **ver el preview «Al aprobar se registrará»** (ahora muestra consumo de tapa si la receta la tiene) → aprobar → el kardex muestra las mismas líneas.
3. **Kardex**: chip nuevo «Consumo tapa» + filtro por tipo.
4. Devolver un reporte enviado → sin movimientos.

## Fuera de alcance (declarado)

P-M11-11 (OEE, F2 — espera doble llave de F1) · moldes (F3) · paradas/PWA (Max-2, en paralelo) · push del kardex a Bsale (D-005) · «GP» y molido ([B] de Luis) · clases de componente MP/servicio (B8).
