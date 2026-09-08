# ADR-008 · Lock pesimista sobre la fila ancla en todo endpoint que muta estado o inventario

- **Estado:** vigente
- **Fecha:** 2026-06-30 (auditoría del merge M11) · reforzada 2026-07-02

## Contexto
Los guards de idempotencia estaban escritos como **check-then-act** sin lock: `aprobar()` verificaba `movimientos()->exists()` y generaba el kardex; dos aprobaciones concurrentes (doble toque en el celular) pasaban ambas y lo duplicaban. `devolver`, `ajustar` y `destroyReporte` competían con `aprobar` por el mismo agregado; la confirmación de un ingreso por QR podía mandar dos correos.

## Decisión
**Todo endpoint que mueve estado o inventario abre `DB::transaction`, toma `lockForUpdate()` sobre la fila ancla del agregado y re-chequea la precondición con la fila bloqueada.** La segunda request sale sin efecto.
- La fila ancla es el agregado que se decide (el reporte, la orden, la solicitud), no las hijas.
- Donde además existe una clave natural, va un **índice único** como barrera física (ADR-005, ADR-006): check + constraint, no uno u otro.
- El idioma de la casa es el de `MiProduccionController`/`aprobar()`; se copia, no se reinventa.

## Alternativas consideradas
- **Solo índices únicos** — no sirven para transiciones de estado (aprobado ↔ devuelto).
- **Locking optimista (versión)** — no es idioma de Laravel en este código y exige manejar el conflicto en cada vista.
- **Confiar en que «no va a pasar»** — pasó: el doble toque es el gesto normal de un teléfono con guantes.

## Consecuencias
Un endpoint nuevo que mute sin lock **no pasa el gate R-31**. La regla aplica a **todos** los endpoints del agregado, no solo al «principal» — el mismo bug sobrevivió un día en otro método por corregir solo el archivo del síntoma (bitácora [2026-07-02]).

## Evidencia
`CLAUDE.md` bitácora [2026-06-30] (revisión adversarial M11), [2026-07-02], [2026-07-06] · `ProduccionKardexTest`, `ServicioTecnicoManagementTest`.
