# Dictado vigente — Max-1 (Forjador A, stream 1)
> Emitido por el Director el 2026-08-13 (v54 — A1 EN PRODUCCIÓN; GO A2: Traslados → Listado). Manda sobre lo anterior.

MODELO: Opus 4.8 · high.

## ✅ A1 está EN PRODUCCIÓN (merge `0e9feaa`, doble llave 13-ago) — menú 42 → 41

Suite del Director sobre el árbol re-mergeado: **2032 verdes / 14.396 aserciones** (main
creció con 4 commits ajenos que entraron durante mi verificación — responsive de notebooks
13", los informes de ST a Excel, bundle `xl:`). **Re-merge sobre main fresco por I-08**:
tocaban tu `index.blade.php` pero en OTRA zona (el grid del historial) → auto-merge sin
conflicto, tu desplegable y su grid `xl:` conviven. Conté el menú (11+29+1=41), da. Rama
borrada.

Tres cosas de tu lote que quedan como estándar:

1. **Verificaste los permisos por tu cuenta ANTES de consolidar** — la lección del Informe
   aplicada sin que te la repitiera. Confirmaste en el seeder que solo jefe_ventas y admin
   portan `gestionar tiempos reparacion` y que ambos ven el Listado. «La palabra del
   Director confirmada, no asumida», dijiste. Exacto.
2. **Declaraste con letra grande el cambio de UX al QR del Lote 4** (de botón suelto a
   entrada del desplegable, 1→2 clics). Era una pantalla que el dueño ya había QA-eado; no
   la cambiaste en silencio, pusiste las dos caras sobre la mesa. **El dueño decidió
   mantener el desplegable** — es la «sección Configuración» que el mapa pedía y el QR es
   acción rara. Tu forma queda.
3. **Materializaste la sección del mapa con el idioma de la casa** (`<x-dropdown>` del «Más»
   de productos) en vez de inventar markup — bundle byte-idéntico, escala para lo que venga.

## 🟢 GO — A2 · «Traslados al taller» pasa a pestaña del Listado de ST (41 → **40**)

- **Qué es**: flujo sucursal → casa matriz con dos puntas de permiso (cadena de custodia).
- **Verificación de permisos que ya hice**: los roles con `despachar traslado servicio`
  (jefe_sucursal) o `recibir traslado servicio` (jefe_ventas, jefe_bodega, tecnico) **todos
  ven el Listado** (`view|manage servicio tecnico`). Sin el problema de acceso del Informe.
  Confírmalo en tu baseline igual.
- **Forma**: pestaña «Traslados» del Listado (a diferencia de Costos/QR, que son config y
  van al desplegable; Traslados es un flujo, merece pestaña de primer nivel). Gateada por
  su OR `despachar|recibir traslado servicio` (idioma del `_tabs` calculado por permiso; el
  `<x-tab-nav>` no gatea solo). Si el Listado gana su primera pestaña de flujo, monta el
  `_tabs` con el `<x-tab-nav>` del Lote 3. Declara la forma.
- **OJO — es FLUJO ACTIVO, no catálogo**: tiene links bidireccionales orden↔traslado que
  ya existen; verifícalos tras el cambio. Es la de menor urgencia del bloque por eso, pero
  ya llegó su turno.
- **Ruta y permiso se CONSERVAN** — mudanza, no retiro.
- **Mini-candado**: 6ª entrada en `CONSOLIDADAS` + **mútala** (quitar la ruta del `activo`
  → 2 rojos → restaurar → verde), como en los cinco anteriores.
- **`VolverTest`**: Traslados era ítem; al pasar a pestaña, ajústalo por la fuente única.

## Después de A2 (NO arranques sin dictado)
**A3 · Informe PARTIDO por dominio** (40 → 39, cierra el Bloque A): informe industrial →
pestaña de la Agenda de terreno (3ª pestaña, `grid-cols-3`, gateada por `ver informe
industrial`); informe dispensadores → Listado; el landing `admin.servicio-tecnico.informe`
decide su destino y se declara. Verificar que NADIE pierda acceso — es el candado del lote.
Te lo dicto formal al cerrar A2. **Ojo**: 4 commits ajenos tocaron los informes de ST a
Excel (`InformeTallerExcel`/`InformeTerrenoExcel`) — cuando llegues a A3, parte de main
fresco y revisa esos cambios; el terreno del Informe se movió.

## Territorio
- **Max-2** en pausa. **Marcos y otro flujo** activos (los 4 commits del drift no eran de
  Marcos: responsive + informes Excel). Re-fetch religioso, rama corta.

## Nota de infra (I-10)
GitHub con 500 intermitentes en push a main; receta rama `tmp/*` + API delete en §I-10.

## Recordatorios
Rama nueva desde main FRESCO antes de tocar un archivo; suite COMPLETA de main fresco ANTES
de empezar (baseline del Director: **2032 / 14.396** en `0e9feaa`, y subiendo — re-fetch).
Candado mutado; parte al buzón. A2 Traslados → doble llave → A3 Informe.

CIERRE: parte a docs/fleet/buzon/partes/. Bloque A: 1 de 3 hecho. Verifica permisos SIEMPRE.
