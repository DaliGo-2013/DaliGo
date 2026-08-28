# Parte de Max-1 — 2026-08-18 · Dictado v64, E1 HECHO: «Configuración de producción» 4→1 — EL MAPA F0 QUEDA LISTO PARA CERRAR

> Forjador A, stream 1 · rama `feature/menu-e1-configuracion-produccion` (commit
> `523998d`) — **espera doble llave**. El último lote del mapa: con este merge,
> **PLAN-MENU-DENSIDAD cierra en 47 → 32**.

## El número

| | |
|---|---|
| Menú antes → después | **35 → 32 rótulos** (11 primer nivel + 20 subítems + 1 cuenta, verificado por tinker) — el lote mayor del plan (−3) |
| Pérdida de rutas/permisos | **CERO** — las familias `admin.maquinas.*`, `admin.tipos-botellon.*`, `admin.recetas.*`, `admin.moldes.*` responden idéntico bajo `manage production` |
| Suite | baseline main `19ac45e` (worktree aislado): **2196 verdes / 15.231, CERO rojos** (= la referencia del Director en `61dd90d`) · rama: **2196 verdes / 15.292, CERO rojos** — **delta 0 tests** (+61 aserciones = las entradas 12ª-14ª del mini-candado y el Volver de Tipos en VolverTest) |
| Bundle | **+1 regla exacta** (`.grid-cols-4` base, 59 bytes), 0 caídas — ver el hallazgo abajo |

## La forma (la dictada)

Anfitriona **Máquinas** (ratificada por mi estudio: primera de la fila física, y es la
ex-huérfana que SE QUEDA como ítem — evita un segundo baile de P-NAV-06), rebautizada
**«Configuración de producción»**, key `maquinas` conservada (precedente C2). Cuatro
pestañas **«Máquinas · Tipos de botellón · Recetas · Moldes» SIN gateo** (permiso
idéntico por construcción — la precondición B1). Montaje solo en los 4 index, con el
detalle de layout declarado: maquinas y tipos-botellon usan `py-12` pelado → `div.mb-6`
(idioma C1); recetas y moldes usan `space-y-6` → include directo (idioma C2).

## ⚠️ Hallazgo del build que CORRIGE al recon (mío y del v64): `grid-cols-4` NO estaba en el bundle

El recon decía «`grid-cols-4` YA está en el bundle (byte-idéntico alcanzable)» — y el
build lo desmintió: el hash del CSS cambió. El diff por tokens (receta 10-08, `tr '}' |
sort | comm`) lo explica en una línea: **entró exactamente UNA regla, `.grid-cols-4`
BASE, y no se cayó ninguna** (64.602 → 64.661 bytes, +59). El grep del recon había
contado un **substring**: lo que existía era `xl:grid-cols-4` (con variante responsive,
compilada como `.xl\:grid-cols-4`), no la clase pelada — la lección del 30-07
(«px-8 matchea dentro de lg:px-8») mordiendo de nuevo, esta vez a los dos recon.
**Receta I-06 aplicada**: bundle recompilado sobre el árbol del lote, superset
verificado por `comm` con control en ambas direcciones (0 caídas / 1 entrada esperada),
clases críticas de la sidebar confirmadas (`lg:flex`, `lg:hidden`, `grid-cols-2/3/4`),
y `public/build` **viajó en el commit** (verificado en el `--stat`).

## La deuda del `<x-tab-nav>`, pagada (punto 2)

Primer cambio al componente desde su nacimiento (Lote 3): fuera el ternario
`count===3 ? cols-3 : cols-2` → **mapa count→clase** con las clases literales
(anti-purga), default sano a 2 columnas y el comentario que cuenta por qué (con el
ternario, 4 pestañas caían a 2 columnas EN SILENCIO — exactamente lo que este lote
habría estrenado). Verificado con render real de las 4 puertas:
`grid-cols-4` presente, **1 pestaña activa y 1 solo resaltado de sidebar** en cada una.
Los consumidores viejos (2 y 3 columnas) intactos — la batería visita las puertas de
TODAS las consolidaciones.

## P-NAV-06, molde D1 — solo Tipos de botellón (punto 5)

- **Tipos de botellón** salió de `test_las_ex_huerfanas_estan_en_el_menu` CON RASTRO
  (tercera vida: huérfana con Volver al panel → ítem P-NAV-06 27-jul → pestaña con
  Volver a la anfitriona) y **entró a `test_pantalla_hija_tiene_exactamente_un_volver`
  en el mismo commit**. Su `<x-volver>` apunta a `admin.maquinas.index`.
- **Máquinas NO salió** del candado — es la anfitriona, su ruta sigue siendo la del
  ítem; quedó anotado en el propio test.
- **Recetas y Moldes** nunca fueron huérfanas: solo pierden el ítem, la pestaña es su
  navegación. `test_ningun_item_del_menu_lleva_volver` se movió solo (deriva de la
  fuente única) — declarado, cero amoldes a mano.
- **Observación de UX declarada, no resuelta**: en la pantalla de Tipos de botellón el
  Volver convive con la pestaña «Máquinas» del tab-nav (van al mismo lugar). Es lo que
  dictaba el contrato del candado P-NAV-06; si en el QA el dueño lo encuentra
  redundante, quitarlo es una línea (y sacar la ruta del test de hijas, otra).

## Los reflejos del punto 9 (corridos por tinker, en LAS DOS direcciones)

Cero colisiones: los 4 wildcards nuevos contra las rutas del ítem `produccion` (lista
explícita, intacta desde D1) y contra `produccion.mi.*` del soplador — y al revés, los
patrones de `produccion`/`mi-produccion` contra las 4 puertas. Todo disjunto.

## Candados

- **Mutación TRIPLE** (dictada, la escala nueva): quitar cada wildcard por separado →
  **2 rojos exactos cada vez** (ruta sin cubrir + resaltado roto en la puerta) →
  restaurar con `git checkout --` (grep del marcador = 1 las tres veces) → **3/3 verde
  (511 aserciones)**. Las tres consolidadas discriminan por separado.
- **`MenuConsolidacionesTest`: entradas 12ª, 13ª y 14ª**. `admin.maquinas.` NO entró al
  mapa a propósito (sigue siendo ítem — el test c fallaría con razón).
- **Batería dirigida: 257 verdes / 4.831 aserciones** — Volver, MenuConsolidaciones
  (todas las puertas del tab-nav: 2, 3 y 4 columnas), Sidebar, MenuPrincipal,
  Navigation, Dashboard, DashboardColores, IdiomaEspanol, PermisosAgrupados,
  MaquinaManagement, TipoBotellonManagement + **la carpeta Producción completa**
  (ProduccionTest, Kardex, Molde, Oee, Notas, RecetaBackflush, RecetaCrud,
  RecetaSeeder) — backflush y semáforo de moldes intactos.
- **Cards del Inicio: cero de los 4** (grep de `AccesosDashboard` vacío, como decía el
  v64) — nada que retirar. Y cero tests pegaban a los labels viejos (grep vacío):
  a diferencia de C2, **cero amoldes** en este lote.

## Para el radar del Director

- QA del dueño (los críticos del v64): **4 pestañas en UNA fila en el celular** (el
  render ya lo confirma en HTML; falta el ojo en pantalla), backflush intacto al
  aprobar un reporte, Operación con 4 ítems (Inventario · Producción · Configuración de
  producción · Devoluciones), los botones de la cabecera del panel (Máquinas / Tipos de
  botellón) siguen llegando, y el Volver de Tipos de botellón (la observación de arriba,
  por si sobra).
- Marcador: **47 → 32 en doce lotes**, mapa F0 completo. Espero el acta de cierre y el
  veredicto de qué sigue.

## Fuera de alcance (declarado)

El acta de cierre del plan (del Director) · la redundancia Volver+pestaña (declarada,
decisión de QA) · territorio de Marcos y Max-2.
