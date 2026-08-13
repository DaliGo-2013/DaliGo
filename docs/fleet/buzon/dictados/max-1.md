# Dictado vigente — Max-1 (Forjador A, stream 1)
> Emitido por el Director el 2026-08-13 (v51 — Lote 4 EN PRODUCCIÓN; GO Lote 5, el último de la fase «en vuelo»). Manda sobre lo anterior.

MODELO: Opus 4.8 · high.

## ✅ Lote 4 está EN PRODUCCIÓN (merge `6d6a2ce`, doble llave 13-ago)

**Menú 44 → 43.** Suite del Director sobre el árbol mergeado: **2005 verdes / 14.152
aserciones** — tu delta 0 y tus +39 aserciones, exactos. Conté el menú (11+31+1=43), da.
Bundle byte-idéntico. Rama borrada.

Lo que hiciste bien y quiero que se repita:

1. **Elegiste la forma por la jerarquía, no por la estética, y encontraste el hecho
   técnico que decide.** «Botón, no pestaña» porque los permisos NO son idénticos y
   `<x-tab-nav>` no gatea — una pestaña le daría 403 al vendedor con `view`. Eso no estaba
   escrito en el dictado; lo dedujiste del contrato del componente.
2. **Desviaste la LETRA del mapa y lo declaraste.** El mapa decía «junto al bloque
   por-confirmar»; viste que ese bloque es doblemente condicional y la entrada quedaría
   indescubrible → cabecera permanente. Cumpliste el espíritu y dijiste dónde te saliste
   de la letra. Un forjador que sigue el mapa a ciegas rompe cosas; uno que lo sigue a
   ciegas y NO avisa las rompe en silencio. Tú hiciste lo tercero: correcto y transparente.
3. **El amolde de NavigationTest en el estándar del Lote 2**: moviste la aserción a la
   ubicación nueva (admin ve el botón + href) y AGREGASTE la que faltaba (el vendedor NO
   lo ve — el gateo que justifica la forma botón). Un verde-trivial menos.
4. **Corregiste el proceso del Lote 3**: rama cortada ANTES de tocar un archivo. Cerrado.

## 🟢 GO — Lote 5 · «Servicios de terreno» pasa a pestaña de «Agenda de terreno» (43 → **42**)

Este vuelve al patrón limpio de pestaña (a diferencia del Lote 4):

- **Permiso IDÉNTICO**: ambas bajo `agendar servicio terreno` (Servicios es el tarifario
  de escritura; la Agenda ya lo enlaza desde su cabecera, «Catálogo de servicios»). Como
  el permiso es el mismo, **sí corresponde `<x-tab-nav>`** — precondición cumplida.
- **Anfitrión**: Agenda de terreno. Pestañas «Agenda» · «Servicios de terreno». El `_tabs`
  es un archivo de datos de ~10 líneas, igual que el `_tabs` de facturación del Lote 3.
- `aria-current="true"`, jamás `"page"` (colisión con SidebarTest — ya lo sabes).
- **Hereda el mini-candado**: línea en `CONSOLIDADAS` + **mútala** (quitar la ruta del
  `activo` del anfitrión → 2 rojos → restaurar → verde), como en los lotes 3 y 4.
- **Ruta y permiso se CONSERVAN** — mudanza, no retiro. Servicios de terreno tiene 5
  rutas: verifica que todas sigan respondiendo y que la cabecera de la Agenda reapunte su
  link «Catálogo de servicios» a la pestaña.
- **`VolverTest`**: si «Servicios de terreno» era ítem con Volver o sin él, ajústalo por
  la fuente única según la doctrina de hijas/pestañas; no amoldes el test a mano.

## ⛳ Después del Lote 5 se ABRE el trabajo en BLOQUES (dueño 13-ago)

El dueño aprobó **TODO** el mapa F0 (47→30), condición: **en bloques por módulo, no todas
juntas** (§4.1 del plan). El Lote 5 cierra la fase «en vuelo». **Después NO arranques
solo**: el primer bloque (A · Servicio Técnico: Informe→Listado, Costos→Listado,
Traslados→Listado) se abre con su propio dictado, y solo cuando el dueño haya hecho el QA
de los lotes 4 y 5 en el celular. Un lote por doble llave dentro del bloque, como siempre.
No abras el Bloque A por tu cuenta.

## Territorio
- **Max-2** en pausa (M11 100 % construido).
- **Marcos** MUY activo en el simulador. Rama corta, push temprano, re-fetch religioso.

## Nota de infra (I-10, ya en el tablero)
GitHub sigue con 500 intermitentes en `git push` a main mientras su página dice
«operacional». Si te pasa: no toques el árbol, push a rama `tmp/*` (sube objetos + aísla
el ref) y reintenta; borra la temporal por API si el git también cae. Receta en §I-10.

## Recordatorios
Rama nueva desde main FRESCO antes de tocar un archivo; suite COMPLETA de main fresco
ANTES de empezar (baseline del Director: **2005 / 14.152** en `6d6a2ce`). Suite completa
antes del push. Parte al buzón → doble llave → (fin de la fase «en vuelo»; espera el
dictado del Bloque A).

CIERRE: parte a docs/fleet/buzon/partes/ + push. Cuatro lotes, cuatro restas. Falta uno para 42.
