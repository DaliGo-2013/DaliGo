# Parte de Max-1 — 2026-08-18 · Dictado v62, D1 HECHO: «Kardex» vuelve a ser hija de Producción

> Forjador A, stream 1 · rama `feature/menu-d1-kardex-produccion` (commit `9118338`) —
> **espera doble llave**. El lote más corto del mapa, cerrado en un lote. E1 NO arrancado:
> llega como v63.

## El número

| | |
|---|---|
| Menú antes → después | **36 → 35 rótulos** (11 primer nivel + 23 subítems + 1 cuenta, verificado por tinker) |
| Pérdida de rutas/permisos | **CERO** — `admin.produccion.movimientos` responde idéntico bajo `manage production`; la entrada visible ya existía (botón «Kardex» en la cabecera del panel + «Ver kardex completo» del reporte) |
| Suite | baseline main `ec06a48` (worktree aislado): **2196 verdes / 15.212, CERO rojos** (+10 tests vs la referencia del Director en `6fa64cd`: los de Marcos del PR #19, absorbidos) · rama: **2196 verdes / 15.231, CERO rojos** — **delta 0 tests** (+19 aserciones = la 11ª entrada del mini-candado y el Volver del kardex en VolverTest) |
| Bundle | **byte-idéntico** (datos + un `:back` del componente existente — cero clases nuevas) |

## ⚠️ Primero: main se movió DESPUÉS del dictado

La referencia del Director era `6fa64cd` (2186/15.184), pero al arrancar el lote
`origin/main` ya estaba en **`ec06a48`** — Marcos mergeó el PR #19 («Trabajo realizado» a
mano, +`TrabajoManualTest`). La rama se cortó de `ec06a48` y la baseline se corrió ahí:
**la mía manda**, y absorbe los tests nuevos de Marcos. Cero solape con mi lote (su
territorio es ST/visita; el mío, menú de Producción).

## La forma (mudanza, no pestaña — como dictaba el v62)

1. **`MenuPrincipal`**: fuera el ítem `kardex`; la ruta entra **EXACTA** a la lista
   explícita del `activo` de `produccion` — la lista NO volvió al comodín, y el
   comentario nuevo lo deja dicho («ítem retirado no es motivo para volver al comodín»,
   el gate 28-07 sigue mandando).
2. **`movimientos.blade.php` recupera su `<x-volver>`** (`:back` al panel +
   `backTitle`), con el comentario de la mudanza reemplazando al «sin Volver (P-NAV-08)»
   que ya no aplicaba.
3. **El candado P-NAV-06 se trató con el respeto que pedía el dictado**: la ruta salió
   de `test_las_ex_huerfanas_estan_en_el_menu` CON RASTRO (el comentario cuenta las
   tres vidas: huérfana con Volver → ítem P-NAV-06 27-jul → hija con Volver por D1
   17-ago) y **volvió a `test_pantalla_hija_tiene_exactamente_un_volver`** — el Volver
   restaurado no queda sin candado ni un solo push.
4. **`MenuConsolidacionesTest`: 11ª entrada** (`admin.produccion.movimientos` →
   `operacion.produccion`, ruta hoja como Estado en el Lote 3).

## Los reflejos que pedía el punto 5 (corridos y declarados)

- `Str::is('produccion.mi.*', 'admin.produccion.movimientos')` → **false** — el ítem del
  soplador («Mi producción») no se enciende con el kardex.
- El patrón es la ruta EXACTA (sin comodín): solo se matchea a sí misma.
- `Str::is('admin.produccion.asignar*', 'admin.produccion.movimientos')` → **false** —
  ningún otro patrón de la lista la pisa. Cero riesgo de doble `aria-current`.

## Cards del Inicio (punto 4): NO había card «Kardex»

`AccesosDashboard` no tiene card de kardex ni de movimientos (verificado por grep) — la
card «Producción» existente ya es la puerta del grupo. **Nada que retirar ni reapuntar**;
el candado Dashboard quedó verde sin tocarlo.

## Candados

- **Mutación** (dictada): quitar `admin.produccion.movimientos` del `activo` → **2 rojos
  exactos** (ruta sin cubrir + 0 `aria-current` en la puerta) → restaurar con
  `git checkout --` (grep del marcador = 1, árbol limpio) → **3/3 verde (429
  aserciones)**.
- **Batería dirigida: 201 verdes / 2.670 aserciones** — Volver + MenuConsolidaciones +
  Sidebar + MenuPrincipal + Navigation + Dashboard + **la carpeta de Producción
  completa** (ProduccionTest, ProduccionKardexTest, ProduccionMoldeTest,
  ProduccionOeeTest, RecetaBackflushTest, RecetaCrudTest — el kardex real: backflush,
  filtros, permiso).
- `test_ningun_item_del_menu_lleva_volver` **se movió solo** (deriva de la fuente
  única: el kardex ya no es ítem, así que dejó de visitarlo) — declarado, cero amoldes
  a mano. Ningún otro test pegaba al label «Kardex» del menú.

## Para el radar del Director

- QA del dueño (corto, como decía el v62): Producción resalta estando en el Kardex, el
  Volver del Kardex lleva al panel, la sidebar sin el ítem, y el botón «Kardex» de la
  cabecera sigue siendo la entrada.
- Marcador: **47 → 35** en once lotes. Queda el CIERRE: **E1 Configuración de
  producción 4→1** (−3, el mayor del mapa) con la deuda del `<x-tab-nav>` a 4 pestañas
  (`grid-cols-4` NO existe — con 4 caería a 2 columnas en silencio) y Máquinas/Tipos de
  botellón repitiendo el molde de hoy (ex-huérfanas que recuperan su Volver). Mapa
  final: 32.

## Fuera de alcance (declarado)

E1 (espera v63 — ni tocado) · territorio de Marcos (ST/visita pública — activísimo hoy:
el PR #19 entró mientras corría este lote) y Max-2.
