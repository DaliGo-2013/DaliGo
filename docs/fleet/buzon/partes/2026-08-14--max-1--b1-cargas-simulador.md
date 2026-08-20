# Parte de Max-1 — 2026-08-14 · Dictado v57, B1 HECHO: «Cargas reales» vive como pestaña del Simulador — el Bloque B queda COMPLETO

> Forjador A, stream 1 · rama `feature/menu-b1-cargas-simulador` (commit `048f1bf`) —
> **espera doble llave**. El Bloque C (C1 Roles→Usuarios, C2 Registro del sistema) espera
> su dictado tras el QA del dueño.

## El número

| | |
|---|---|
| Menú antes → después | **40 → 39 rótulos** (11 primer nivel + 27 subítems + 1 cuenta, verificado por tinker) — el Bloque B completo en un lote (B2 Conductores VIVE SOLO, decisión del dueño 14-ago: ni se tocó) |
| Pérdida de rutas/permisos | **CERO** — las 5 rutas (`carga.index/excel` + `cargas-reales.index/store/destroy`) responden idéntico |
| Suite | baseline main `21d53ad` (worktree aislado): **2145 verdes / 14.855 aserciones** (main sumó +102 tests con el drift: días laborables, plazos, cierres de agenda) · rama: **2145 verdes / 14.888 aserciones** — **delta 0 tests, exacto** (las +33 aserciones netas son la 7ª entrada del mini-candado) |
| Bundle | **byte-idéntico** (cero clases nuevas) |

## La verificación de permisos que ordenó el dictado (hecha por mi cuenta)

**Idéntico POR CONSTRUCCIÓN**: las 5 rutas comparten el MISMO grupo
`permission:simular carga` en `routes/web.php` (L628-641) — no hay cruce que hacer porque
no hay dos audiencias: es una sola, definida una vez. Confirmado contra el código.

## Qué se tocó

1. **`admin/carga/_tabs.blade.php`** (nuevo, el `_tabs` de datos de ~10 líneas del patrón
   Lote 3): «Simulador · Cargas reales» con `<x-tab-nav>`, **sin gateo** — la precondición
   limpia (permiso idéntico) por primera vez desde el Lote 3.
2. **Montajes**: `carga/index` (el simulador — territorio ACTIVO de Marcos: el diff ahí es
   **una línea**, el @include) y `cargas-reales/index` (tras su status-alert).
3. **El comentario de `MenuPrincipal` que defendía el ítem aparte, reescrito DEJANDO EL
   RASTRO** (orden explícita del dictado): antes decía «va como ítem aparte a propósito —
   el simulador se usa ANTES de cargar y esto se anota DESPUÉS»; ahora explica que el
   dueño resolvió (14-ago) que ese matiz de momento-de-uso no pesa frente a la densidad —
   la pestaña no impide anotar después, solo agrupa. El estándar del QR: la decisión
   previa se reemplaza con el porqué escrito, no se borra.
4. **Los 2 links contextuales del simulador a Cargas reales SE QUEDAN** (el factor
   «medido en terreno» y «anotá una en Cargas reales»): apuntan a la ruta conservada, que
   ahora ES la pestaña — el link aporta el porqué, la pestaña la estructura (precedente
   Lote 3).
5. **`MenuConsolidacionesTest`**: 7ª entrada (`'admin.cargas-reales.' =>
   'logistica.carga'`). **Mutada**: quitar el patrón del `activo` → 2 rojos exactos →
   restaurar (grep del marcador) → 3/3 verde (303 aserciones). Verificado además que
   `Str::is('admin.carga.*', 'admin.cargas-reales.index')` NO matchea (el punto corta) —
   cero doble aria-current entre anfitrión y consolidada.
6. `cargas-reales/index` sin Volver (pestaña = su navegación); VolverTest ajustado solo
   por la fuente única.

## Candados

Batería dirigida: **295 verdes** — Sidebar + MenuPrincipal + Volver + Navigation +
**la carpeta Carga completa** (todos los tests del simulador de Marcos renderizan la
pantalla con el tab-nav montado sin inmutarse) + IdiomaEspanol. **Cero amoldes.**

## Para el radar del Director

- QA del dueño: el Simulador con las pestañas «Simulador · Cargas reales» arriba (ambas
  visibles para todo portador de `simular carga`), Cargas reales operando igual (anotar,
  borrar, el factor por combinación), y la sidebar de Logística sin el ítem.
- Con el Bloque B cerrado, el marcador queda **47 → 39** en ocho lotes ejecutados + dos
  vive-solos decididos con evidencia (Informe, Conductores). El mapa final del proyecto:
  **32**.
- El Bloque C que viene trae el «Registro del sistema» (3→1): será la primera
  consolidación de MÚLTIPLES ítems en un anfitrión nuevo — el mini-candado ya lo soporta
  (mapa por prefijo), pero la pestaña triple tocará `grid-cols-3` (existe) y quizá el
  patrón de anfitrión-sin-pantalla-previa. Lo evalúo cuando llegue el dictado.

## Fuera de alcance (declarado)

B2 Conductores (VIVE SOLO — el veredicto del mapa §5.1 lo actualiza el Director) ·
Bloque C (espera dictado) · el resto del simulador (solo el @include) · la deuda del
`<x-tab-nav>` a 4 pestañas.
