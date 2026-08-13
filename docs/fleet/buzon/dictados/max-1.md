# Dictado vigente — Max-1 (Forjador A, stream 1)
> Emitido por el Director el 2026-08-13 (v53 — QA del dueño OK; se ABRE el Bloque A · Servicio Técnico; GO A1: Costos → Listado). Manda sobre lo anterior.

MODELO: Opus 4.8 · high.

## ✅ El dueño hizo el QA de los lotes 4 y 5 — todo funcionando. Se ABRE el Bloque A.

La fase «en vuelo» quedó cerrada con QA (menú 47→42). Arranca el primer bloque del mapa
aprobado: **A · Servicio Técnico** (anfitrión: el Listado del taller). Tres lotes, uno por
doble llave, como siempre.

**Orden del bloque (lo reordené con motivo):** A1 Costos → A2 Traslados → A3 Informe.
Los dos primeros son mudanzas limpias; el Informe lo dejé para el final porque cambió de
forma (te explico abajo). No arranques A2 ni A3 sin su dictado.

## 🟢 GO — A1 · «Costos generales de reparación» pasa al Listado de ST (42 → **41**)

- **Qué es**: tiempos estándar + valor hora (1 recurso, uso raro), bajo
  `gestionar tiempos reparacion`.
- **Verificación de permisos que ya hice (para que no repitas el susto del Informe)**:
  solo `jefe_ventas` y `admin` tienen `gestionar tiempos reparacion`, y **ambos ven el
  Listado** (`view|manage servicio tecnico`). No hay rol que gestione costos sin ver el
  Listado → **consolidación limpia**, sin el problema de acceso que sí tiene el Informe.
  Confírmalo en tu baseline igual (no te fíes de mi palabra: el seeder es aditivo y algún
  admin pudo tocar permisos desde la UI).
- **Forma**: sección «Configuración» del taller dentro del Listado (junto a donde vivirá
  el botón/QR ya presente), **gateada por `@can('gestionar tiempos reparacion')`** — no la
  muestres a quien ve el Listado sin ese permiso. Si eliges pestaña en vez de sección,
  gateada por el MISMO permiso (idioma del `_tabs` calculado por permiso; el `<x-tab-nav>`
  no gatea solo). Declara la forma y el porqué, como en el Lote 4.
- **Ruta y permiso se CONSERVAN** — mudanza, no retiro.
- **Mini-candado**: línea en `CONSOLIDADAS` + **mútala** (quitar la ruta del `activo` del
  anfitrión → 2 rojos → restaurar → verde), como en los lotes 3-5.
- **`VolverTest`**: Costos era ítem del menú; al pasar a hija/sección, ajústalo por la
  fuente única según la doctrina de hijas/pestañas. Nada de amoldes a mano.

## La cola del Bloque A (NO arranques sin dictado)

**A2 · Traslados al taller → Listado** (41 → 40). También verificado limpio por mí: todos
los roles con `despachar|recibir traslado servicio` (jefe_sucursal, jefe_ventas,
jefe_bodega, tecnico) ven el Listado. Pestaña «Traslados» que conserva su OR. Es flujo
activo (no catálogo), por eso va después de Costos.

**A3 · Informe — REPLANTEADO, ya no es «Informe → Listado» a secas** (40 → 39).
Hallazgo que te ahorro descubrir a mitad de camino: el **técnico industrial** tiene
`ver informe industrial` pero **NO** `view servicio tecnico`. Hoy entra al informe
industrial por su ítem del menú; si el Informe se vuelve pestaña del Listado (que él no
ve), **pierde el acceso**. Las rutas del informe están en su propio grupo de permiso
(web.php L243), separado del Listado (L224) — por eso hoy sí lo alcanza.
**Decisión del dueño: PARTIR el Informe por dominio:**
- **Informe industrial → pestaña de la Agenda de terreno** (que el técnico SÍ ve; su
  dominio). Sería la 3ª pestaña de la Agenda (Agenda · Servicios · Informe industrial),
  gateada por `ver informe industrial`. `grid-cols-3`, todavía no toca la deuda de 4.
- **Informe dispensadores → pestaña/sección del Listado**, gateada por
  `ver informe dispensadores`.
- **El landing `admin.servicio-tecnico.informe`**: decide su destino y decláralo (retirar
  si cada cara ya tiene entrada, o mantener reapuntado). Verifica que NADIE pierda acceso
  tras el cambio — ese es el candado de este lote.
Cuando llegues a A3 te lo dicto formal; por ahora solo para que lo tengas en el radar y no
lo construyas como decía el mapa viejo.

## Territorio
- **Max-2** en pausa. **Marcos** activo en el simulador — rama corta, re-fetch religioso.

## Nota de infra (I-10, en el tablero)
GitHub con 500 intermitentes en push a main. Receta: rama `tmp/*` para subir objetos y
aislar el ref, reintenta; borra la temporal por API si el git también cae. §I-10.

## Recordatorios
Rama nueva desde main FRESCO antes de tocar un archivo; suite COMPLETA de main fresco
ANTES de empezar; candado mutado; parte al buzón. Baseline del Director: **2005 / 14.193**
en `c5f5b47` (más lo que Marcos haya sumado — re-fetch).

CIERRE: parte a docs/fleet/buzon/partes/ + push. A1 Costos → doble llave → A2. Verifica los
permisos SIEMPRE antes de consolidar: el Informe casi se lleva un acceso por delante.
