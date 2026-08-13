# Parte de Max-1 — 2026-08-13 · Dictado v53, A1 HECHO: «Costos generales de reparación» entra por el Listado de ST

> Forjador A, stream 1 · rama `feature/menu-a1-costos-listado` (commit `203e036`) —
> **espera doble llave**. A2 (Traslados→Listado) no arranca sin su dictado.

## El número

| | |
|---|---|
| Menú antes → después | **42 → 41 rótulos** (11 primer nivel + 29 subítems + 1 cuenta, verificado por tinker) — primera resta del Bloque A |
| Pérdida de rutas/permisos | **CERO** — las 5 rutas del resource `tiempos-reparacion` responden idéntico bajo `gestionar tiempos reparacion` |
| Suite | baseline main `469bbab` (worktree aislado): **2020 verdes / 14.276 aserciones** (main creció con los tests del simulador de Marcos desde la referencia del v53) · rama: **2020 verdes / 14.310 aserciones** — **delta 0 tests, exacto** (las +34 aserciones netas son la 5ª entrada del mini-candado) |
| Bundle | **byte-idéntico** (el desplegable reusa el idioma literal del «Más» de productos: `<x-dropdown>`/`<x-dropdown-link>` + las clases del trigger ya en el bundle) |

## La verificación de permisos que ordenó el dictado (hecha por mi cuenta)

Confirmado contra el seeder: `gestionar tiempos reparacion` lo portan SOLO **jefe_ventas**
(línea 136 — que también porta `view` + `manage servicio tecnico`) y **admin** (todos los
permisos, línea 105). **Nadie gestiona costos sin ver el Listado** → consolidación limpia,
sin el problema de acceso del Informe. La palabra del Director confirmada, no asumida.

## La forma elegida (declarada — Y UN CAMBIO AL QR DEL LOTE 4 QUE EL DIRECTOR DEBE VER)

La «sección Configuración» del mapa F0 («junto a Códigos QR; ambos raros, ambos config;
cada uno conserva su permiso») **se materializa al llegar su segunda entrada**: un
desplegable **«Configuración»** en la cabecera del Listado, con

- «Códigos QR» — `@can('manage servicio tecnico')`
- «Costos generales de reparación» — `@can('gestionar tiempos reparacion')`

y el trigger gateado por `@canany` de ambos. El porqué: (1) es la sección del mapa hecha
markup — dos utilidades de config agrupadas bajo un solo rótulo; (2) tres botones sueltos
no caben en la cabecera a 375px, y el idioma de la casa para eso es exactamente el «Más»
de productos («para no amontonar el header», dice su comentario); (3) escala para lo que
el bloque traiga (el Informe dispensadores de A3 tiene destino pestaña/sección, pero si
algo más termina siendo config, ya hay casa).

**⚠️ Lo que esto cambia del Lote 4, declarado con letra grande**: el botón suelto
«Códigos QR» que el dueño QA-eó ayer **se muda adentro del desplegable** (de 1 clic a 2).
Es la agrupación que el propio mapa pedía, pero si el dueño prefiere los dos botones
sueltos, es un revert de ~10 líneas — decisión de ustedes en la doble llave, con las dos
caras sobre la mesa.

## Qué se tocó

1. **`servicio-tecnico/index.blade.php`**: el botón QR y la entrada nueva de Costos pasan
   al desplegable «Configuración» (trigger con el idioma del «Más» de productos); el
   comentario de la cabecera cuenta la historia completa (lotes 4 + A1).
2. **`tiempos-reparacion/index.blade.php`**: pasa a HIJA — `:back`/`backTitle` (doctrina
   P-NAV-08); sus create/edit conservan su Volver (ahora nietas, sin cambio). VolverTest
   se ajustó solo por la fuente única — cero amoldes.
3. **`MenuPrincipal`**: fuera el ítem `tiempos-reparacion`; `admin.tiempos-reparacion.*`
   entra al `activo` del Listado (2ª consolidación sobre el mismo anfitrión).
4. **`MenuConsolidacionesTest`**: 5ª entrada del mapa. **Mutada**: quitar el patrón del
   `activo` → 2 rojos exactos → restaurar (grep del marcador) → 3/3 verde (225
   aserciones).

## Candados

Batería dirigida: **71 verdes** (Sidebar + MenuPrincipal + Volver + Navigation +
TiempoReparacionManagement + ServicioTecnicoVisibilidad + IdiomaEspanol) — **cero
amoldes**: TiempoReparacionManagementTest pega a rutas conservadas, y los asserts del QR
del Lote 4 en NavigationTest siguen verdes porque el desplegable renderiza sus links en
el DOM del server (Alpine solo los muestra/oculta).

## Para el radar del Director

- QA del dueño: el Listado con el desplegable «Configuración» (dos entradas para
  jefatura/admin; solo QR para un `manage` sin costos; nada para quien no porta ninguno),
  y la pantalla de Costos con su «Volver».
- A2 (Traslados) espera su dictado; el replanteo del Informe (A3, partir por dominio)
  queda en el radar — la 3ª pestaña de la Agenda usará `grid-cols-3`, que ya existe en el
  componente.
- Proceso completo de la casa: rama antes de tocar, baseline en worktree aislado con
  diagnóstico de autoloader, mutación post-commit, bundle verificado.

## Fuera de alcance (declarado)

A2 y A3 (esperan dictado) · los bloques B-E · la deuda del `<x-tab-nav>` a 4 pestañas ·
territorio de Marcos y Max-2.
