# Parte de Max-1 — 2026-08-13 · Dictado v54, A2 HECHO: «Traslados al taller» vive como pestaña del Listado de ST

> Forjador A, stream 1 · rama `feature/menu-a2-traslados-listado` (commit `8598184`) —
> **espera doble llave**. A3 (Informe partido por dominio) espera su dictado formal.

## El número

| | |
|---|---|
| Menú antes → después | **41 → 40 rótulos** (11 primer nivel + 28 subítems + 1 cuenta, verificado por tinker) — Bloque A: 2 de 3 |
| Pérdida de rutas/permisos | **CERO** — las 5 rutas de traslados responden idéntico (index/show con el OR; create/store bajo `despachar`; recibir bajo `recibir`) |
| Suite | baseline main `a72b15e` (worktree aislado): **2043 verdes / 14.433 aserciones** (main siguió creciendo tras la referencia del v54: disponibilidad de visita) · rama: **2043 verdes / 14.469 aserciones** — **delta 0 tests, exacto** (las +36 aserciones netas son la 6ª entrada del mini-candado) |
| Bundle | **byte-idéntico** — el hash de main ya incluía el drift responsive `xl:`; mi rebuild lo reprodujo exacto (cero clases nuevas del A2) |

## La forma (la que prescribió el dictado, con la taxonomía hecha markup)

El Listado gana su **primera pestaña de FLUJO**: `_tabs-listado` («Listado · Traslados al
taller») con el `<x-tab-nav>` del Lote 3 — nombre distinto del `_tabs` de detalle que ya
vivía en esa carpeta (las etapas de UNA orden). La taxonomía del dictado queda física en
el markup: **config → desplegable «Configuración» de la cabecera** (QR, Costos — lotes 4 y
A1); **flujo → pestaña de primer nivel** (Traslados).

**Cada pestaña gateada por SU permiso** (idioma del `_tabs` de ST): «Listado» con
`view|manage servicio tecnico`, «Traslados» con `despachar|recibir traslado servicio` —
la cadena de custodia tiene dos puntas y el OR se conserva tal cual. Con una sola pestaña
el nav no se dibuja.

## La verificación de permisos que ordenó el dictado (hecha por mi cuenta)

Confirmado contra el seeder: los 4 roles con permisos de traslado —**jefe_sucursal**
(despachar, línea 185-196), **jefe_ventas** (136), **jefe_bodega** (144) y **tecnico**
(162)— portan TODOS `view servicio tecnico`. **Nadie pierde acceso** al volverse pestaña
del Listado. La palabra del Director confirmada, no asumida.

## Qué se tocó

1. **`servicio-tecnico/_tabs-listado.blade.php`** (nuevo): las pestañas del taller, con
   el porqué del gateo y del nombre en su docblock.
2. **`servicio-tecnico/index.blade.php` + `traslados/index.blade.php`**: montan el
   tab-nav bajo su status-alert.
3. **`MenuPrincipal`**: fuera el ítem `traslados`; `admin.traslados.*` entra al `activo`
   del Listado (3ª consolidación sobre el mismo anfitrión). Verificado que el prefijo NO
   pisa `admin.bodegas.traslados.*` (familia distinta, str_starts_with no matchea).
4. **`MenuConsolidacionesTest`**: 6ª entrada. **Mutada**: quitar el patrón → 2 rojos
   exactos → restaurar (grep del marcador) → 3/3 verde (266 aserciones).
5. Traslados index sin Volver (pestaña = su navegación, precedente lotes 1/3/5); su
   `show` conserva el suyo. VolverTest ajustado solo por la fuente única.

## Candados

Batería dirigida: **81 verdes** (Sidebar + MenuPrincipal + Volver + Navigation +
**TrasladoServicioTest completo** — los links bidireccionales orden↔traslado que el
dictado pidió verificar, intactos sin tocar el test — + ServicioTecnicoVisibilidad +
IdiomaEspanol). **Cero amoldes**.

## Para el radar del Director

- QA del dueño: el Listado con sus pestañas «Listado · Traslados al taller» (el vendedor
  sin permisos de traslado ve solo una → sin nav, como siempre), el desplegable
  «Configuración» intacto al lado de «Registrar ingreso», y el flujo de traslados
  operando igual (despachar → en camino → recibir).
- A3 en el radar: Informe partido por dominio (industrial → 3ª pestaña de la Agenda con
  `grid-cols-3`; dispensadores → Listado; el landing decide su destino). Anotado que el
  terreno del Informe se movió con los commits del Excel — partiré de main fresco y
  revisaré `InformeTallerExcel`/`InformeTerrenoExcel` cuando llegue el dictado.
- El Listado va camino a ser el hub que el mapa quería: 3 consolidaciones absorbidas
  (QR, Costos, Traslados) + la que viene (Informe dispensadores) — y el mini-candado las
  fija todas contra el mismo anfitrión.

## Fuera de alcance (declarado)

A3 (espera dictado formal) · los bloques B-E · la deuda del `<x-tab-nav>` a 4 pestañas
(ojo: la 3ª pestaña de la Agenda en A3 usa `grid-cols-3`, que ya existe — la deuda es la
4ª) · territorio de Marcos y Max-2.
