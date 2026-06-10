# Auditoría M01 (Inc 2/3/4) + M02 (Inc 1) — hallazgos y plan de continuación

> ✅ **EJECUTADO (2026-06-10):** la reconciliación de roles a 8 ASCII (migración
> `reconcile_business_roles` + seeder + tests) y los fixes accionables (Producto en
> `AuditController::MODELOS`, normalización de nombre de rol, try/catch por fila en el
> import [aplicado antes, en el incremento de medidas], `Str::headline` en
> dashboard/usuarios, `max:` en peso/dimensiones). Este documento queda como registro histórico.

> **Para retomar en otra máquina.** Documento de traspaso de una auditoría read-only hecha el
> 2026-06-08 sobre los commits `e1df23d` (roles), `1328de8` (config), `729ccd1` (auditoría) y
> `4ee1c54` (catálogo productos). Complementa `HANDOFF.md` y `CLAUDE.md`.
> **Estado del repo al auditar:** local == `origin/main` (sincronizado), árbol limpio, HEAD `040a58c`.

---

## Veredicto: SÓLIDO, sin blockers

- **Dinámico:** **139 tests verdes** (345 assertions) · **8/8 deploys `success`** en Actions · todas las
  migraciones `Ran` en prod · datos OK (`configuraciones`=2, `audits`=2 registrando, `productos`=1).
- **Estático** (22 agentes + verificación adversarial + crítico): **"con-observaciones", 0 blockers**.

| Increment | Estado | Notas |
|---|---|---|
| Inc 3 — Configuración global | ✅ Sólido | Tabla `configuraciones` consistente; `Configuracion::get/set` cacheados con invalidación; validación por tipo; 15 tests. Solo nits. |
| Inc 4 — Auditoría (owen-it) | ✅ Sólido | owen-it `^14` en `require`; 3 gotchas resueltos (console env, `$auditExclude` password, sin guard `api`); audit manual de cambios de rol; 11 tests. |
| M02 Inc 1 — Catálogo productos | ✅ Sólido | Import CSV para Excel-CL (`;`, BOM, Windows-1252, decimales con coma), streaming, idempotente por SKU, round-trip, 23 tests. Complementa Bsale. |
| Inc 2 — Roles | ⚠️ Reconciliar | Quedó en enfoque aditivo; ver decisión abajo. |

---

## ⚠️ DECISIÓN CONFIRMADA CON EL USUARIO: reconciliar roles a 8 ASCII

**Problema.** La otra sesión hizo Inc 2 de forma **aditiva** (documentado en `HANDOFF.md`): mantuvo los
roles legacy `Soplador`/`Jefatura` y **agregó** `jefe_bodega` como rol **separado con solo `view users`**.
Resultado en prod (verificado por SSH):

```
Roles en prod (10): admin, conductor, Jefatura, jefe_bodega, jefe_ventas, member, Soplador, tecnico, vendedor, Ventas
  admin       => los 11 permisos (todo)
  Jefatura    => manage production        <- legacy con el permiso clave
  jefe_bodega => view users               <- nuevo, SIN manage production (footgun)
  Soplador    => report production         <- legacy; no existe 'soplador' minúscula
  jefe_ventas => view users
  Ventas      => (sin permisos)            <- huérfano creado a mano en la UI
  conductor/tecnico/vendedor/member => (sin permisos)
```

**Consecuencia:** el rol nuevo `jefe_bodega` **no puede entrar al panel de Producción** (gateado por
`permission:manage production`); el que lo gestiona sigue siendo el legacy `Jefatura`. Hoy nadie está
afectado (los 4 usuarios son `admin`), pero confunde apenas se asignen los roles del negocio.

**Decisión (usuario): RECONCILIAR a 8 roles ASCII limpios.** Objetivo final:

| Rol (ASCII) | Permisos |
|---|---|
| `admin` | todos |
| `jefe_bodega` | `manage production` + `view users` |
| `jefe_ventas` | `view users` |
| `soplador` | `report production` |
| `vendedor`, `conductor`, `tecnico`, `member` | (sin permisos) |

(8 roles: se eliminan `Soplador`, `Jefatura`, `Ventas`.)

### Plan de reconciliación (commit nuevo, p. ej. `fix(m01): reconciliar roles a 8 ASCII`)

1. **Migración** `php artisan make:migration reconcile_business_roles` — `up()` defensivo con `DB::table`
   (en BD nueva es no-op; el seeder ya crea los nombres correctos):
   ```php
   // Renombrar legacy a ASCII (UPDATE preserva role_id => asignaciones y permisos colgados).
   // OJO MySQL 5.7: collation utf8mb4_unicode_ci es CASE-INSENSITIVE -> NO uses where(name,'soplador')->exists()
   // como guard de "destino libre" (daría falso positivo contra 'Soplador'). Renombra directo:
   DB::table('roles')->where('name', 'Soplador')->update(['name' => 'soplador']);

   // 'Jefatura' debe consolidarse en el rol nuevo 'jefe_bodega' (que ya existe con view users).
   // No se puede renombrar Jefatura->jefe_bodega porque el destino YA existe -> mover el permiso y borrar.
   //   (el seeder, paso 2, ya le da 'manage production' a jefe_bodega; aquí solo borramos Jefatura)
   $jefatura = DB::table('roles')->where('name', 'Jefatura')->first();
   if ($jefatura && ! DB::table('model_has_roles')->where('role_id', $jefatura->id)->exists()) {
       DB::table('role_has_permissions')->where('role_id', $jefatura->id)->delete();
       DB::table('roles')->where('id', $jefatura->id)->delete();
   }
   // 'Ventas' huérfano: borrar si 0 usuarios.
   $ventas = DB::table('roles')->where('name', 'Ventas')->first();
   if ($ventas && ! DB::table('model_has_roles')->where('role_id', $ventas->id)->exists()) {
       DB::table('role_has_permissions')->where('role_id', $ventas->id)->delete();
       DB::table('roles')->where('id', $ventas->id)->delete();
   }
   ```
   `down()`: no-op (reconciliación de datos puntual).
   > Nota: si algún usuario tuviera `Jefatura` asignado (en prod hoy = 0), primero reasignarlo a
   > `jefe_bodega` antes de borrar. Verificar con la consulta SSH de abajo antes de desplegar.

2. **`database/seeders/RolesAndPermissionsSeeder.php`** — cambiar el bloque de roles a la matriz final
   (mapa rol→permisos, `firstOrCreate` + `givePermissionTo`, idempotente/aditivo). Concretamente:
   `soplador => ['report production']`, `jefe_bodega => ['manage production','view users']`,
   `jefe_ventas => ['view users']`, `vendedor/conductor/tecnico/member => []`, `admin => $permissions`.
   Quitar las líneas que crean `Soplador`/`Jefatura`.

3. **Tests:**
   - `tests/Feature/Admin/RoleMatrixSeedTest.php`: la matriz esperada pasa a **8 roles** ASCII
     (hoy fija 9 con `Soplador`/`Jefatura`); actualizar `assertSame(9, …)` → `8` y el mapa.
   - `tests/Feature/Admin/ProduccionTest.php:24,29`: `assignRole('Jefatura')`→`'jefe_bodega'`,
     `assignRole('Soplador')`→`'soplador'`.

4. **Verificación prod (post-deploy, SSH read-only):** los roles deben quedar exactamente
   `admin, member, vendedor, jefe_ventas, jefe_bodega, conductor, tecnico, soplador` (8), con
   `jefe_bodega => manage production, view users` y `soplador => report production`; sin
   `Soplador`/`Jefatura`/`Ventas`.

---

## Hallazgos accionables (warnings, ninguno bloquea) — recomendado aplicarlos

1. **`Producto` es auditable pero falta en `AuditController::MODELOS`**
   - `app/Http/Controllers/Admin/AuditController.php:18-22` — `MODELOS` solo lista User/Sucursal/Configuracion.
   - `Producto` usa `AuditableTrait` (`app/Models/Producto.php:20`) → genera audits, pero no es filtrable
     ni etiquetado en `/admin/audits` (sin crash por el `?? class_basename`).
   - **Fix:** agregar `App\Models\Producto::class => 'Producto'` a `MODELOS` (+ `use App\Models\Producto;`).

2. **`RoleController::update` — unique case-insensitive en MySQL vs case-sensitive en SQLite (tests)**
   - `app/Http/Controllers/Admin/RoleController.php:90-92` — `Rule::unique('roles','name')` + regex
     `/^[a-z0-9 _-]+$/i`. En MySQL 5.7 (`utf8mb4_unicode_ci`) renombrar a otra caja ('Vendedor' vs 'vendedor')
     se rechaza; en tests SQLite no. Divergencia local-vs-prod latente.
   - **Fix:** normalizar el nombre (`mb_strtolower` / slug) antes de validar y persistir en `store`/`update`.

3. **Import CSV de productos sin transacción ni try/catch por fila**
   - `app/Http/Controllers/Admin/ProductoController.php:132-177` — un error fatal a mitad (timeout LiteSpeed,
     o valor que pasa `numeric` pero excede `decimal(10,3)`) aborta toda la carga con 500 y pierde el resumen.
     Recuperable por idempotencia (upsert por SKU), pero frágil.
   - **Fix:** `try/catch` por fila acumulando en `$errores[] = ['fila'=>$fila,'error'=>…]` y `continue`
     (estilo de la entrada de bitácora del 2026-06-05). Mantiene la tolerancia "fila inválida se salta".

4. **`Str::headline` solo en `roles/index`** → nombres con guion bajo en otras vistas.
   - Aplicar `\Illuminate\Support\Str::headline($role->name)` SOLO en el **texto visible** (no en el `value`):
     `resources/views/dashboard.blade.php:21`, `resources/views/admin/users/index.blade.php:38`,
     `resources/views/admin/users/create.blade.php:33`, `resources/views/admin/users/edit.blade.php:28`.
   - Tras tocar Blade: `npm run build` + commit de `public/build/`.

### Nits (opcionales)
- `peso_kg`/dimensiones validan `numeric|min:0` sin `max` → un valor enorme da "Out of range" (500) en MySQL 5.7.
  Agregar `max` acorde a `decimal(10,3)`/`decimal(10,2)` en `ProductoController::validateData` y en el import.
- `HANDOFF.md` no documenta **M02 Inc 1** (catálogo productos) con el detalle de los Inc 1-4. Agregar sección.
- La biblia `PROYECTO_DALIGO.md:236` sigue con `bsale_id` único; el código usa `bsale_variant_id` +
  `bsale_product_id` (decisión correcta según `docs/BSALE_API.md`). Sincronizar el DDL de la biblia.
- Import: avisar en `resources/views/admin/productos/importar.blade.php` que **sin coma el punto se
  interpreta como decimal** (`1.200` = 1,2 — no "mil doscientos").
- `Configuracion::castValor` JSON: un literal `'null'` válido se enmascara como `[]` (`Configuracion.php:99`). Bajo impacto.

---

## Lo que está BIEN (no tocar)
- Producción gatea 100% por **permiso** (`report/manage production`), nunca por nombre de rol → renombrar roles es seguro.
- owen-it: `console` env-driven + `AUDITING_CONSOLE=true` en `phpunit.xml`; `$auditExclude=['password','remember_token']`; guards solo `['web']`.
- MySQL 5.7: `defaultStringLength(191)`; `sku` VARCHAR(64) unique; `audits` morphs 191 + `user_agent` no indexado; `json()` nativo (5.7.8+); decimales coherentes.
- `DatabaseSeeder` llama a Roles+Sucursal+Configuracion (idempotentes); `deploy.sh` con `migrate --force` + `db:seed --force` + `permission:cache-reset` (no cambió → sin self-update lag).
- `public/build` recompilado y commiteado (clases `file:*` del input de import presentes en el bundle).

---

## Consultas SSH read-only de referencia (verificación en prod)
Servidor: `impdali@impdali.cl:2222`, app en `/home4/impdali/daligo`, PHP `/opt/cpanel/ea-php83/root/usr/bin/php`.
> En Windows/PowerShell, base64-encodear el comando para evitar problemas de comillas y falsos positivos
> del sandbox (usar `dangerouslyDisableSandbox` si el sandbox bloquea por heurística de borrado).

- **Roles + permisos:**
  `php artisan tinker --execute="foreach(\Spatie\Permission\Models\Role::with('permissions')->orderBy('name')->get() as \$x){echo \$x->name.' => '.(\$x->permissions->pluck('name')->implode(', ')?:'(sin permisos)').PHP_EOL;}"`
- **Migraciones:** `php artisan migrate:status | tail -16`
- **Conteos:** `Schema::hasTable('configuraciones')`, `DB::table('audits')->count()`, `DB::table('productos')->count()`.

---

## Orden sugerido para retomar
1. `git pull` (estar en `main` al día).
2. Aplicar **reconciliación de roles** (migración + seeder + tests). Verificar que en prod no haya usuarios en `Jefatura` antes de desplegar.
3. Aplicar los **4 arreglos** (Producto en MODELOS, normalizar rol, try/catch import, Str::headline).
4. `php artisan test` (toda la suite verde) → `npm run build` + commit `public/build` → commit → `git push`.
5. Vigilar Actions (verde) → verificar prod por SSH (8 roles ASCII + matriz correcta).
6. (Opcional) Nits + actualizar `HANDOFF.md` (M02) y la biblia (`bsale_*`).

*Documento generado por la auditoría del 2026-06-08. Mantener actualizado al ejecutar.*
