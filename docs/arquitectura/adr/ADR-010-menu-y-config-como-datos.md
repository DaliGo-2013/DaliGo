# ADR-010 · Menú y configuración como datos, con candados estructurales

- **Estado:** vigente
- **Fecha:** 2026-07-24 (menú V4) · 2026-08-18 (densidad 47 → 32) · PLAN-PARAMETRICOS en curso

## Contexto
El menú creció a 47 ítems escritos a mano en Blade, con dos ítems resaltados a la vez, patrones `routeIs` que se comían a sus hermanos y anchos de dropdown que emitían clases inválidas en silencio. En paralelo, textos de avisos, audiencias y parámetros comerciales vivían hardcodeados: cambiar «quién recibe este correo» era un deploy.

## Decisión
**Lo que el dueño debería poder cambiar sin programador es un dato; lo que estructura la app es un dato con candado.**
- El menú es `App\Support\MenuPrincipal` (módulos → ítems con `label`, `route`, `activo`, `permiso`, `icon`, `badge`). Agregar un ítem = **una línea**; la visibilidad del módulo se deriva de sus ítems. Antes de crear un ítem, el parte declara por qué no cabe en uno existente (doctrina de densidad).
- Plantillas de notificación, audiencias de avisos y parámetros de negocio viven en `configuracion` (editables) con seeder idempotente y one-shots encadenadas que se verifican entre sí (`OneShotPlantillasCandadoTest`).
- **Candados estructurales**: tests que leen `MenuPrincipal`, las rutas, los Blades y el bundle, y fallan **nombrando archivo:línea** — existencia de ruta/permiso/ícono, un solo `aria-current`, tokens de ancho validados, paleta, marco, Volver, ⓘ. Toda regla nueva de UI nace con su candado y se prueba **por mutación**.

## Alternativas consideradas
- **Blade a mano con `@can`** — es lo que había: drift, duplicados, dos activos.
- **Reglas de UI solo en CLAUDE.md** — un documento no falla en rojo; tres entradas de bitácora dejaron la advertencia sin candado y el defecto volvió (bitácora [2026-08-25]).

## Consecuencias
Un token desconocido **revienta** (ancho de dropdown, `ancho` de página) en vez de caer en silencio al default. La suite de PHP no evalúa Alpine, así que los candados de comportamiento JS son estructurales sobre el HTML renderizado (comillas, balance de comentarios, forma completa del atributo).

## Evidencia
`CLAUDE.md` «Navegación», «Paneles flotantes», «Ancho de página», bitácora [2026-07-24], [2026-07-26], [2026-07-28], [2026-08-21], [2026-08-25] · `docs/planes/PLAN-MENU-DENSIDAD.md`, `PLAN-PARAMETRICOS.md` · `MenuPrincipalTest`, `SidebarTest`, `PanelAncladoTest`, `AnchoDePaginaTest`, `Parametros*Test`.
