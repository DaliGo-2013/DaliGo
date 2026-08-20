# Parte de Max-1 — 2026-08-14 · Dictado v59, C1 HECHO: «Roles» vive como pestaña de «Usuarios»

> Forjador A, stream 1 · rama `feature/menu-c1-roles-usuarios` (commit `6b44be1`) —
> **espera doble llave**. C2 («Registro del sistema» 3→1) NO arrancado: llega como v60.

## El número

| | |
|---|---|
| Menú antes → después | **39 → 38 rótulos** (11 primer nivel + 26 subítems + 1 cuenta, verificado por tinker) |
| Pérdida de rutas/permisos | **CERO** — las 6 rutas del resource `roles` (index/create/store/edit/update/destroy) responden idéntico bajo `manage roles`; las de users intactas |
| Suite | baseline main `da54469` (worktree aislado): **2185 verdes + 1 ROJO** / 15.103 aserciones · rama: **2185 verdes + el MISMO 1 rojo** / 15.134 aserciones — **delta 0 tests y 0 rojos** (las +31 aserciones son la 8ª entrada del mini-candado) |
| Bundle | **byte-idéntico** (cero clases nuevas) |

## El cruce de audiencias (confirmado por mi cuenta, como pidió el dictado)

`manage roles` aparece **una sola vez** en `RolesAndPermissionsSeeder`: la **declaración**
del permiso (L28). **Ningún `givePermissionTo` de rol lo nombra** → solo lo recibe el admin
por la lista maestra. `view users` lo portan 4 roles (admin + jefe_ventas + jefe_bodega +
jefe_sucursal). Las dos direcciones del Director confirmadas:

- **¿Alguien define roles sin ver Usuarios?** No — {admin} ⊂ audiencia del anfitrión.
- **¿Alguien ve Usuarios sin `manage roles`?** Sí, los tres jefes → **por eso la pestaña
  va gateada** (patrón A2/Agenda: las pestañas se calculan por permiso, y con una sola el
  nav no se dibuja). El jefe ve su listado de cuentas igual que siempre.

Caveat del dictado atendido: el seeder es piso aditivo, la UI pudo sumar el permiso a
alguien — pero eso no cambia la forma, porque el gateo es por permiso en tiempo de render,
no por rol hardcodeado. Riesgo real ≈ solo-admin, como decía el Director.

## 🔴 AVISO AL DIRECTOR: main viene con 1 test ROJO — NO es de este lote

La suite de la **baseline** (worktree aislado sobre `da54469`, **sin una sola línea mía**)
dio **1 rojo**. La de mi rama dio **el mismo 1 rojo, con idéntico conteo de tests** — o sea
el fallo ya vivía en main antes del C1 y mi lote no lo agrava ni lo tapa.

- **Test rojo**: `Tests\Feature\VisitaIndustrialTest > crea la solicitud sin fecha y con
  preferida` (`test_crea_la_solicitud_sin_fecha_y_con_preferida`, L219).
- **NO es flaky, es determinista**: lo corrí **dos veces** en el worktree limpio y falló
  idéntico las dos (`Failed asserting that true is false` — el mensaje de
  `assertSessionHasNoErrors()` en L228, o sea el POST está devolviendo errores de
  validación que antes no devolvía).
- **Por qué no es mío**: aparece en una corrida donde mi código no existe; delta de tests
  0 y delta de rojos 0 entre baseline y rama. Mi lote no toca la visita pública.
- **Pista para quien lo tome** (mirada de 2 minutos, sin tocar nada): el terreno lo
  movieron 4 commits de hoy sobre la visita pública —`e1ba3b6` «fuera el texto libre, el
  cliente elige la hora del horario real», `0e76ad4` feriados/vacaciones/media jornada,
  `684a989` lunes a viernes, `1a6d792` los candados del tipo fijo—. El test describe el
  flujo **«sin fecha, con fecha preferida»** y su helper `payload()` (L40) **no manda
  `fecha` ni `hora`**; la validación del controller público ahora tiene una `fecha` con
  `required`. Huele al patrón de la bitácora [2026-08-13]: el código cambió de contrato y
  su test quedó stale. **No lo toco** — territorio ajeno y fuera de mi dictado; queda
  reportado para que el Director lo asigne.

## ⚠️ Hallazgo que el dictado NO contemplaba: la card «Roles» del Inicio

`AccesosDashboard` tenía una card `roles` (L39). Al quitar el ítem del menú, esa card
quedaba huérfana y **habría puesto rojo**
`MenuPrincipalTest::test_cards_del_dashboard_son_subconjunto_del_menu` (exige que toda card
tenga un ítem con su misma ruta y un permiso compatible).

**Decisión mía, declarada: se RETIRA la card** — mismo criterio y precedente del Lote 1
(card «Precios»): «Usuarios» ya tiene card y su descripción literalmente dice «Cuentas **y
roles** del equipo», así que dos cards al mismo destino serían ruido. La key huérfana en
`users.dashboard_colores` es tolerada por construcción (D-013). Si el dueño prefiere
conservar el acceso directo desde el Inicio, reapuntarla es una línea.

## Qué se tocó

1. **`admin/users/_tabs.blade.php`** (nuevo): «Usuarios · Roles» con `<x-tab-nav>`, la
   pestaña de Roles bajo `can('manage roles')`, con el matiz de permisos en su docblock.
2. **Montaje solo en los dos index** (`users/index`, `roles/index`) — create/edit quedan
   fuera, precedente Documentos (Lote 3). Detalle de layout declarado: estas dos pantallas
   usan `py-12` **sin** `space-y-*`, así que el nav va envuelto en un `div.mb-6` (el margen
   lo pone la vista, no el componente compartido).
3. **`MenuPrincipal`**: fuera el ítem `roles`; `admin.roles.*` entra al `activo` de
   `usuarios`. No había nota previa defendiendo el ítem aparte → no hubo rastro que
   preservar (a diferencia de B1).
4. **`AccesosDashboard`**: card retirada con el comentario del porqué.
5. **`MenuConsolidacionesTest`**: 8ª entrada. **Mutada**: quitar el patrón del `activo` →
   2 rojos exactos → restaurar (grep del marcador) → 3/3 verde (341 aserciones).
6. Reflejo del `Str::is` corrido igual que pidió el dictado:
   `Str::is('admin.users.*', 'admin.roles.index')` → **false**. Prefijos disjuntos, cero
   riesgo de doble `aria-current`.

## Candados

Batería dirigida: **133 verdes** (Sidebar + MenuPrincipal + Volver + Navigation +
**Dashboard + DashboardColores** —los que vigilan la card retirada— + RoleManagement +
UserManagement + FilasClickeables + IdiomaEspanol). **Cero amoldes**: todos pegan a rutas
conservadas o derivan de la fuente única.

## Para el radar del Director

- QA del dueño: Usuarios con las pestañas «Usuarios · Roles» (un jefe con `view users` sin
  `manage roles` NO ve la pestaña — el gateo que justifica el patrón), Roles operando igual
  (crear/editar rol), el Inicio sin la card «Roles», y Administración con un ítem menos.
- Marcador: **47 → 38** en nueve lotes. Con C2 (3→1) el mapa llega a 36 y quedan D y E.
- Radar C2 leído: audiencia idéntica (los 3 permisos solo-admin), tab-nav triple
  (`grid-cols-3` ya existe en el componente), 2 cards del Inicio a reapuntar y los links de
  la campanita que sobreviven. Cuando llegue el v60 arranco desde main fresco.

## Fuera de alcance (declarado)

C2 (espera v60 — ni tocado) · Bloques D y E · la deuda del `<x-tab-nav>` a 4 pestañas (se
paga en E) · territorio de Marcos y Max-2.
