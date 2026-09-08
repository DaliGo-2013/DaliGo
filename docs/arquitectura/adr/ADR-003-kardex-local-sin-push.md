# ADR-003 · Kardex de producción local, sin push a Bsale

- **Estado:** vigente como **puente** (el push queda para cuando D-005 lo permita)
- **Fecha:** 2026-06-26 · **Decide:** equipo, auditoría de M11

## Contexto
M11 (producción) nació como conteo + aprobación, aislado del stock: aprobar solo cambiaba `estado`, y `tipos_botellon` no enlazaba con `productos`. El plan original (PLAN-M11-FASE2) proponía empujar a Bsale, pero eso depende de la API y del soporte (D-005), y mientras tanto el módulo no aportaba trazabilidad de insumos.

## Decisión
**Al aprobar un reporte se genera un kardex local (`produccion_movimientos`) que nunca toca las tablas espejo.**
- `ProduccionMovimiento::generarParaReporte()` corre dentro de la transacción de `aprobar()`, con guard de idempotencia (`movimientos()->exists()`).
- Consumo de preforma = **suma de las tandas** (misma fuente que producción y merma), no los totales que el jefe pudo ajustar → kardex internamente consistente; el ajuste queda como capa de reporte.
- Enlaces opcionales al catálogo: `tipos_botellon.producto_id`, `produccion_asignaciones.preforma_id` (nullable; degradan con gracia).
- La preforma se valida contra el **mismo scope** que ofrece el selector (`activo = true`), no solo `exists`.

## Alternativas consideradas
- **Push a Bsale al aprobar** — bloqueado por D-005; se sumará como consumidor del kardex cuando exista.
- **Sin kardex hasta tener el push** — rechazado: M11 seguía siendo una isla.

## Consecuencias
Hay dos verdades temporales (kardex local vs. stock en Bsale) hasta que exista el push; el kardex ya tiene la forma para alimentarlo. Prohibido que cualquier código de producción escriba `stocks` o `bodegas`.

## Evidencia
`CLAUDE.md` bitácora [2026-06-26], [2026-06-30] ×2, [2026-07-02] · `HANDOFF.md` §8d · `ProduccionKardexTest`.
