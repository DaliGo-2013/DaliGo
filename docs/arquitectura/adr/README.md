# ADR — Registros de decisiones de arquitectura

Una decisión **técnica** que cambia cómo está construido DaliGo se anota acá, en una ficha de ~15 líneas, **el mismo día que se toma**. Las decisiones de **negocio y producto** (alcance, roles, nombres, hosting) siguen en `docs/DECISIONES.md`; cuando una decisión es las dos cosas, la ficha de negocio enlaza a la ADR.

**Por qué existen:** casi todas las de abajo ya estaban tomadas —y bien fundamentadas— dentro de entradas de bitácora de 400 palabras. Ahí las encuentra quien ya sabe que existen. La ADR es la respuesta corta a «¿por qué está así?» para quien llega nuevo.

## Índice

| ADR | Título | Estado | Fecha de la decisión |
|---|---|---|---|
| [001](ADR-001-utc-y-dia-de-negocio.md) | Storage en UTC + `FechaNegocio` para el día operativo | ✅ vigente | 2026-07-20 |
| [002](ADR-002-espejo-bsale-solo-lectura.md) | Espejo de Bsale de solo lectura por polling `*/15` | ✅ vigente | 2026-06 / 2026-07-07 |
| [003](ADR-003-kardex-local-sin-push.md) | Kardex de producción local, sin push a Bsale | ✅ vigente (puente) | 2026-06-26 |
| [004](ADR-004-assets-compilados-versionados.md) | `public/build` versionado; el servidor no compila | ✅ vigente hasta migrar | 2026-06 |
| [005](ADR-005-emision-dte-puerto-sin-reintentos.md) | Emisión de DTE: puerto, reserva previa, POST sin reintentos | ✅ vigente | 2026-07-28 |
| [006](ADR-006-cola-offline-idempotente.md) | Cola offline: UUID de cliente + CSRF fresco + errores clasificados | ✅ vigente | 2026-07-02 |
| [007](ADR-007-tailwind-source-none.md) | Tailwind v4 `source(none)` | ✅ vigente | 2026-07-30 |
| [008](ADR-008-lock-pesimista-fila-ancla.md) | Lock pesimista sobre la fila ancla en todo endpoint que muta | ✅ vigente | 2026-06-30 |
| [009](ADR-009-permisos-gatean-la-ruta.md) | Permisos gatean la ruta; roles como datos; seeder aditivo | ✅ vigente | 2026-06 / 2026-07-24 |
| [010](ADR-010-menu-y-config-como-datos.md) | Menú y configuración como datos con candados estructurales | ✅ vigente | 2026-07-24 |

## Plantilla

```markdown
# ADR-0NN · Título corto (la decisión, no el tema)

- **Estado:** propuesta · vigente · reemplazada por ADR-0MM
- **Fecha:** AAAA-MM-DD · **Decide:** quién

## Contexto
Qué problema había y qué restricción lo hacía difícil. Dos párrafos máximo.

## Decisión
La regla, en una frase que se pueda citar. Después el cómo, en viñetas.

## Alternativas consideradas
- **X** — por qué no.

## Consecuencias
Lo que se gana, lo que se paga, y qué queda prohibido a partir de acá.

## Evidencia
Enlaces a bitácora, plan, tests que la fijan.
```
