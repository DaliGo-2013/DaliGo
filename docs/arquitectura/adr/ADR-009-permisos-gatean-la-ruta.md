# ADR-009 · Permisos gatean la ruta; roles como datos editables; seeder aditivo

- **Estado:** vigente
- **Fecha:** junio 2026 (roles) · 2026-07-24 (D-014, handler amable) · 2026-08-27 (Administración solo admin)

## Contexto
La regla #2 del negocio es «la gestión es por vendedor»: quién ve qué cambia por rol, por sucursal y por cartera, y el dueño quiere **cambiar permisos sin programador ni deploy**. Al mismo tiempo, un 403 crudo o una pantalla que «no aparece» dejan al usuario sin saber qué pasó, y los consumidores de máquina (cola offline, buscadores, `<img>` de rutas protegidas) no pueden recibir un redirect.

## Decisión
**El permiso se exige en la RUTA, el enlace se oculta con `@can`, y el handler de 403/404 explica y devuelve al Inicio. Los roles son datos.**
- `spatie/laravel-permission`; `middleware('permission:…')` en `routes/web.php`; `MenuPrincipal` oculta lo que el usuario no puede ver (`|` = cualquiera).
- Roles del negocio en `RolesAndPermissionsSeeder`, **aditivo** (`givePermissionTo`, nunca `syncPermissions`): un admin puede sumar permisos desde la UI y el deploy no los pisa. **Revocar** un permiso es una migración one-shot, no un cambio del seeder.
- Un permiso nuevo se crea **antes** de la funcionalidad que lo usa (precedente `jefe_sucursal`, `jefe_logistica`); permisos separados cuando dos personas distintas hacen cada punta (traslados, hoja de ruta con tres llaves).
- Handler (D-014): primero los consumidores de máquina (`expectsJson`, `Sec-Fetch-Mode`) reciben el status crudo; el navegador recibe redirect al Inicio con `session('aviso')` — **nunca `back()`** en un GET (bucle). Vistas de error standalone sin `Auth` ni layout.
- Administración (usuarios/roles) es **solo admin** (`PermisosSoloAdmin`).

## Alternativas consideradas
- **Gates ad hoc en cada controlador** — se olvidan; la pantalla aparece en el menú y falla al entrar.
- **`syncPermissions` en el seeder** — revertiría en cada deploy lo que el dueño configuró en la UI.

## Consecuencias
Agregar una pantalla = permiso en el seeder + `middleware` en la ruta + ítem en `MenuPrincipal`. Cada ruta del menú debe resaltar **exactamente un** ítem (`SidebarTest`). El segundo argumento de `abort(403, '…')` es texto de cara al usuario.

## Evidencia
`docs/DECISIONES.md` D-002, D-014 · `CLAUDE.md` «Dos canales de mensaje», bitácora [2026-07-24] ×2, [2026-07-28] · `ManejoErroresTest`, `MenuPermisoRutaTest`, `SidebarTest`.
