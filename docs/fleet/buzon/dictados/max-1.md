# Dictado vigente — Max-1 (Forjador A, stream 1)
> Emitido por el Director el 2026-08-14 (v59 — QA del dueño del Bloque B ✅: GO Bloque C, lote C1 Roles→Usuarios). Manda sobre lo anterior.

MODELO: Opus 4.8 · high.

## ✅ QA del Bloque B aprobado por el dueño (14-ago) — se abre el Bloque C · Administración

El dueño verificó en celular: pestañas «Simulador · Cargas reales» funcionando, Cargas
reales operando igual, sidebar sin el ítem suelto. **Bloque B cerrado con QA.** Marcador:
47 → 39 en producción.

## 🔨 GO — Lote C1: «Roles» vive como pestaña de «Usuarios» (39 → 38)

### El cruce de audiencias YA ESTÁ HECHO (por el Director, seeder como piso)

- **`manage roles` (Roles): SOLO admin** — vive únicamente en la lista maestra que recibe
  el rol admin (`RolesAndPermissionsSeeder` L125); ningún otro rol lo recibe.
- **`view users` (anfitrión Usuarios): admin, jefe_ventas, jefe_bodega, jefe_sucursal.**
- **Dirección 1** (¿alguien define roles sin ver Usuarios?): NO — {admin} ⊂ audiencia del
  anfitrión. Nadie pierde el camino.
- **Dirección 2** (¿alguien ve Usuarios sin manage roles?): SÍ — los tres jefes. Por eso la
  pestaña «Roles» va **GATEADA por `manage roles`** (patrón A2 Costos: pestaña con gateo).
- Caveat de siempre: el seeder es piso aditivo (la UI puede haber sumado). Verifícalo tú
  también y decláralo en el parte. Riesgo real ≈ solo-admin.

### La forma (los moldes de la casa, nada nuevo)

1. `admin/users/_tabs.blade.php`: «Usuarios · Roles», pestaña Roles solo si
   `can('manage roles')` — como A2. `<x-tab-nav>` 2 pestañas, `aria-current="true"`.
2. Montaje en `users/index` y `roles/index` (+ pantallas hijas de roles si las hay — revisa
   `admin.roles.*`: si hay create/edit, la pestaña se monta solo en index, como Documentos).
3. `MenuPrincipal`: fuera el ítem `roles`; su patrón `admin.roles.*` entra al `activo` de
   `usuarios`. Comentario con rastro si había nota defendiendo el ítem aparte.
4. `MenuConsolidacionesTest`: 8ª entrada + **mutación** (quitar patrón → 2 rojos exactos →
   restaurar → verde).
5. Prefijos: `admin.users.*` vs `admin.roles.*` — sin colisión posible (nombres disjuntos),
   pero corre tu reflejo del `Str::is` igual y decláralo.
6. Volver de `roles/index`: la pestaña es su navegación (fuente única, VolverTest deriva).

### Verificación (invariante)
Rama nueva `feature/menu-c1-roles-usuarios` desde main FRESCO (PR #18 acaba de entrar:
plazo en Sucursales — re-fetch). Suite COMPLETA de main fresco ANTES (baseline del
Director: 2182/15.096 en `c67d882`, y main ya se movió — recuenta). Batería dirigida +
carpeta Users/Roles completa. Conteo tinker: debe dar **38**. Parte al buzón; espera doble
llave. NO arranques C2.

## 📡 Radar C2 (NO arranques — llega como v60 tras C1)

«Registro del sistema» 3→1: Auditoría anfitriona + Notificaciones + Historial de
aprobaciones. El cruce del Director ya dio: **los 3 permisos (`view audit`,
`view notificaciones`, `view aprobaciones`) viven SOLO en la lista maestra → audiencia
idéntica (admin) por construcción.** Sin nudo. Detalles que te esperan: tab-nav triple
(`grid-cols-3` existe), 2 cards del Inicio en `AccesosDashboard` se reapuntan, los links de
la campanita sobreviven (apuntan a rutas que se conservan), y `admin.notificaciones.prueba`
comparte prefijo con index (mismo `activo`, sin lío).

## Estado
- Max-2 en pausa (v24). Marcos + PR #9/#18 activos — re-fetch religioso.
- Tras C2: Bloque D (Kardex, ex-huérfana) → E (Configuración de producción 4→1 + deuda
  `<x-tab-nav>` a 4 pestañas). Mapa final: 32.

CIERRE: GO C1. Un lote, un parte, una llave. Buen fierro.
