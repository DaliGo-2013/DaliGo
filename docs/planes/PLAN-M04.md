# PLAN-M04 · Inventario real — clasificación de bodegas + kardex unificado

> **Estado: BORRADOR (2026-08-06)** — espera el visto bueno del dueño antes del primer GO.
> Insumo: D-003 resuelta parcial (Excel `Bodegas Bsale.xlsx`, Ricardo 13-jul + Luis 06-ago;
> anexo 2 en `docs/DECISIONES.md`). Autor: Director. M04 estuvo pospuesto desde R-002
> (13-jul) exactamente por esta decisión.

## 0. El problema en una frase

La app ya VE el inventario (espejo `bodegas`+`stocks` de Bsale, sync cada 15 min) pero no
lo ENTIENDE: no sabe qué bodega es de qué sucursal, cuáles de las 16 están vivas, y los
movimientos locales que la app misma genera (producción M11, devoluciones M13) apuntan a
bodegas de **texto libre** porque la estructura no existía. D-003 ya la definió.

## 1. Principios (los mismos del espejo, ahora con apellido)

1. **Bsale sigue siendo master del stock.** En Bsale no se toca nada; el sync
   (`StockSync`) sigue trayendo las 16 offices igual que hoy. TODO lo de este plan es
   **aditivo y local**.
2. **Clasificar ≠ borrar.** Las 6 bodegas muertas no se eliminan de la BD (romperían el
   espejo y el histórico): se marcan `en_operacion=false` y desaparecen de las pantallas
   operativas.
3. **El kardex local es la verdad de la app, listo para empujar.** Contrato ya probado
   dos veces (ProduccionMovimiento, DevolucionMovimiento): la app registra SUS movimientos
   y el empuje a Bsale (`receptions`) queda para cuando D-005 responda. Este plan NO
   incluye el empuje.

## 2. Modelo de datos (F1)

Columnas nuevas en `bodegas` (migración aditiva, espejo intacto — `StockSync::upsertBodega`
no las toca porque hace upsert por campos explícitos):

| Columna | Tipo | Contenido |
|---|---|---|
| `sucursal_id` | FK nullable → `sucursales` | NULL = transversal (MERMAS, RESERVA) |
| `proposito` | string(191) corta | `fisica` · `virtual_operativa` · `transito` · `insumos` · `taller` · `cerrada` |
| `en_operacion` | boolean default true | false = no aparece en pantallas operativas |
| `clasificacion_confirmada` | boolean default false | false en las 5 filas [B] de D-003 |

Seeder idempotente `ClasificacionBodegasSeeder` (matchea por `bsale_office_id`, NO por
nombre) con el veredicto del anexo 2 de D-003: 5 vivas, 6 muertas, 5 [B] sin confirmar
(`en_operacion=true` provisorio, `clasificacion_confirmada=false`).

## 3. Fases

### F1 · Clasificación + mapping (GO candidato: Max-1)
- **P-M04-10** Migración + seeder + modelo (relación `sucursal()`, scopes `enOperacion()`,
  `deSucursal()`). Pantalla admin de bodegas existente (`resources/views/admin/bodegas`)
  gana: columna sucursal, badge propósito, badge «por confirmar» en las [B], y un
  toggle de clasificación editable solo con `manage sucursales` (permiso existente; sin
  permiso nuevo — lección M13).
- **P-M04-11** Las pantallas operativas que hoy listan TODAS las bodegas pasan a
  `enOperacion()`: selector de stock, y el `bodega_destino` de M11/M13 pasa de texto
  libre a **select de bodegas vivas** (guardando además el FK nuevo `bodega_id` nullable;
  el texto histórico NO se migra, se conserva).
- Candados: seeder idempotente (correr 2× = mismo estado), espejo intacto tras sync
  simulado, las [B] visibles con su badge, texto libre histórico intacto.

### F2 · Kardex unificado (lectura)
- **P-M04-20** Vista «Movimientos de inventario» que une los 2 kardex locales
  (producción + devoluciones) por bodega/producto/fecha — solo lectura, con el mismo
  idioma mes/año de los informes de la casa. Muestra la brecha local-vs-espejo por
  producto (lo que la app sabe que pasó y Bsale aún no refleja).

### F3 · Empuje a Bsale — **FUERA de este plan** `[B:D-005]`
- `receptions`/`consumptions` de la API. Ni se diseña hasta que soporte Bsale responda.

## 4. Hecho cuando

- [ ] Las 16 bodegas clasificadas en producción; las 6 muertas invisibles en operación.
- [ ] M11 y M13 registran movimientos contra bodega REAL (select, FK), no texto.
- [ ] Las 5 [B] lucen su badge «por confirmar» y el cierre de la ronda 2 solo cambia
      datos (seeder), no código.
- [ ] QA del dueño: pantalla de bodegas + un movimiento de devolución apuntando a
      bodega real.

## 5. Preguntas abiertas (viajan en la ronda 2 — no bloquean F1)

Las 5 del anexo 2 de D-003: las 2 ST (¿cuál queda?), Santa Rosa (propósito/vendibilidad),
Contenedores (si muere, ¿dónde entra la importación?), Reserva Sucursales (¿cómo opera?),
y cuál era LA bodega que esperaba confirmación de un tercero.
