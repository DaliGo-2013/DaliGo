# Parte de Max-1 — 2026-08-17 · Dictado v60, C2 HECHO: «Registro del sistema» 3→1

> Forjador A, stream 1 · rama `feature/menu-c2-registro-sistema` (commit `2ddc868`) —
> **espera doble llave**. Primera consolidación de MÚLTIPLES ítems, cerrada en un lote.
> Bloque D NO arrancado: espera dictado.

## El número

| | |
|---|---|
| Menú antes → después | **38 → 36 rótulos** (11 primer nivel + 24 subítems + 1 cuenta, verificado por tinker) |
| Pérdida de rutas/permisos | **CERO** — las 4 rutas (`admin.audits.index`, `admin.notificaciones.index` + POST `prueba`, `admin.aprobaciones.index`) responden idéntico bajo sus permisos |
| Suite | baseline main `7cba804` (worktree aislado): **2186 verdes / 15.142, CERO rojos** (= la referencia del Director en `fc47fd1`) · rama: **2186 verdes / 15.184, CERO rojos** — **delta 0 tests** (+42 aserciones = las entradas 9ª y 10ª del mini-candado) |
| Bundle | **byte-idéntico** (cero clases nuevas — `grid-cols-3` ya estaba en el bundle por el componente) |

## La forma (la dictada, con dos matices declarados)

Anfitrión **Auditoría** rebautizado **«Registro del sistema»** (key `auditoria` SE
CONSERVA — la fijan las preferencias de color de `DashboardColoresTest:143` y da menos
churn); `activo` triple; tab-nav **«Cambios · Notificaciones · Aprobaciones»** con
`grid-cols-3` (primera vez en una consolidación), CADA pestaña gateada por su permiso en
render — con una sola, el nav no se dibuja (idioma C1). Montaje en los 3 index como hijo
directo del `space-y-6` (estas vistas SÍ lo tienen, a diferencia de C1 — sin `div.mb-6`).

- **Permiso del ítem: `view audit` a secas, NO canAny** — decisión mía, declarada, por el
  precedente C1 (el anfitrión Usuarios quedó con `view users`): un ítem visible por canAny
  llevaría a un usuario con solo `view notificaciones` a un clic que da 403 (la ruta del
  ítem es la de audits), y la doctrina es que el menú jamás ofrece un 403. Hoy es teórico:
  los tres permisos son solo-admin por construcción.
- **El rastro del QA 15-07 (hallazgo #1) se preservó**, no se borró: el comentario del
  ítem consolidado y el docblock del `_tabs` explican por qué la pestaña «Aprobaciones» a
  secas ya no choca con la bandeja — vive DENTRO de «Registro del sistema», la bandeja
  sigue sola en la sidebar con su badge, y la campanita conserva su link con el nombre
  largo «Historial de aprobaciones».

## Cards del Inicio: 3 → 1, como dictaba el v60

`AccesosDashboard`: queda UNA card «Registro del sistema» (ruta del anfitrión, desc
dictada «Cambios, notificaciones y aprobaciones», mismo ícono/color); retiradas
`notificaciones` y `aprobaciones` con el comentario del porqué. Keys huérfanas toleradas
(D-013). El candado Dashboard no exigió nada distinto: verde con el subconjunto nuevo.

## ⚠️ Amoldes que el dictado no listaba (2, ambos en `DashboardTest`)

1. **L103** `assertSee('Auditoría')` → `assertSee('Registro del sistema')` — el string
   viejo desaparece de sidebar y card. El comentario vecino de L104 («historial del
   motor, ahora en el zócalo») quedaba stale: hoy 'Aprobaciones' lo aporta la bandeja.
2. **L409** `assertDontSee('Auditoría')` → `assertDontSee('Registro del sistema')` —
   sin el amolde quedaba **verde-engañoso permanente** (bitácora 29-07: negar una cadena
   que ya no puede existir no vigila nada). El nuevo sí discrimina: el vendedor no tiene
   `view audit`, así que ni ítem ni card.

La intención de ambos sobrevive en su ubicación nueva (admin ve el zócalo completo /
vendedor no ve Administración) — estándar Lote 2.

## Reflejos corridos y declarados (los del punto 8 del dictado)

- `Str::is('aprobaciones.*', 'admin.aprobaciones.index')` → **false** (la bandeja no se
  enciende con el historial).
- `Str::is('notificaciones.*', 'admin.notificaciones.index')` → **false** (la campanita
  de usuario tampoco).
- `Str::is('admin.audits.*', 'admin.aprobaciones.index')` → **false** (los tres patrones
  del `activo` cubren cada uno lo suyo, cero solape).
- `admin.notificaciones.prueba` (POST) comparte prefijo con su index → cae en el `activo`
  sin línea extra, como preveía el dictado.

## Candados

- **`MenuConsolidacionesTest`: entradas 9ª y 10ª**, **mutación DOBLE** como dictaba el
  v60: quitar `admin.notificaciones.*` del `activo` → **2 rojos exactos** (ruta sin
  cubrir + 0 aria-current en la puerta) → restaurar (grep del marcador = 1) → quitar
  `admin.aprobaciones.*` → **los mismos 2 rojos** → restaurar → **3/3 verde (402
  aserciones)**. Las dos mitades del candado discriminan por separado.
- **Batería dirigida: 243 verdes / 4.686 aserciones** — MenuConsolidaciones, Sidebar,
  MenuPrincipal, Volver, Navigation, Dashboard, DashboardColores, IdiomaEspanol,
  RenderEnChile, FechaNegocio, AuditManagement + carpetas **Aprobaciones** y
  **Notificaciones** completas.
- **Verificado, no asumido**: `AprobacionBandejaTest::test_el_nav_distingue_bandeja_de_historial`
  sigue verde **por la superficie correcta** — sus asserts pegan a los hrefs de la
  campanita (`layouts/partials/campanita.blade.php` L77/L80), que sobreviven intactos.
  No es un verde por accidente: el hub ES navegación y conserva los dos nombres.

## Para el radar del Director

- QA del dueño (Bloque C completo): Administración con 3 ítems (Usuarios, Sucursales,
  Registro del sistema); el Registro con sus tres pestañas y la triple en una fila
  (`grid-cols-3`) en el celular; la bandeja «Aprobaciones» intacta con su badge; la
  campanita con sus 4 links; el Inicio con UNA card «Registro del sistema» y sin las de
  Notificaciones/Aprobaciones.
- Marcador: **47 → 36** en diez lotes. Quedan D (Kardex→Producción) y E (Configuración
  de producción 4→1 + la deuda del `<x-tab-nav>` a 4 pestañas). Mapa final: 32.

## Fuera de alcance (declarado)

Bloque D (espera dictado — ni tocado) · Bloque E · territorio de Marcos (visita
pública/agenda) y Max-2.
