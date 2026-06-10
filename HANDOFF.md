# HANDOFF — DaliGo (traspaso de contexto para continuar el proyecto)

> **Para la IA que recibe esto:** este documento es el **estado de ingeniería** de DaliGo y
> **complementa** a [`PROYECTO_DALIGO.md`](PROYECTO_DALIGO.md) (la "biblia" de producto/negocio, en este mismo repo).
> Léelos juntos: la **biblia** dice *qué* construir y *por qué*; este **HANDOFF** dice *qué ya existe*,
> *cómo está hecho*, *cómo se despliega* y *qué sigue*. En el repo también hay un `CLAUDE.md` con
> las reglas vivas + bitácora de errores (Claude Code lo lee solo; otras IAs deberían leerlo igual).
>
> **Idioma del proyecto:** español (UI, commits, comunicación).
> **Fecha del traspaso:** 2026-06-04.

---

## 0. Cómo usar este documento

1. Lee `PROYECTO_DALIGO.md` (la biblia: 16 módulos M01–M16, reglas de negocio, Gantt).
2. Lee este `HANDOFF.md` (estado actual, stack, deploy, convenciones, gotchas, plan en curso).
3. Lee `CLAUDE.md` del repo (reglas de diseño + **bitácora de errores resueltos** — no repitas esos errores).
4. Continúa por el **Plan en curso** (sección 8): *Completar M01 Core*, incremento por incremento.

---

## 1. Qué es DaliGo (resumen de 30 segundos)

Sistema de gestión interno (ERP-lite) para **Importadora DALI / DALI Cargos-Transporte** (Chile).
**Objetivo central:** matar el papel en el ciclo de la factura y dar trazabilidad (quién/qué/cuándo/dónde).
**No reemplaza a Bsale** (el ERP/facturación actual): lo **complementa**. 4 bodegas/sucursales:
**Mirador** (central), **Coquimbo**, **Abate Molina**, **Buzeta**. Debe funcionar como **PWA con modo offline**
(es el mayor riesgo técnico del proyecto). Ver la biblia para el detalle de los 16 módulos y las
18 correcciones de negocio de "Luis".

---

## 2. Estado actual (qué YA está construido y funcionando)

**M01 Core está COMPLETO** (Incrementos 1–4; ver sección 8). En producción (staging) y testeado:

- **Autenticación completa** (Laravel Breeze, stack Blade): login, recuperación de contraseña,
  verificación de email (`MustVerifyEmail`). **Registro público REMOVIDO** (las cuentas las crea un admin).
- **UI en español**, tema **claro y sobrio**: naranjo de marca `#EA580C` + blanco + neutros, **sin degradados**,
  **motion sutil** (`dg-enter`, `dg-shake`, respeta `prefers-reduced-motion`). Tipografía Instrument Sans.
- **Roles y permisos** con `spatie/laravel-permission` v8:
  - Permisos: `view users`, `create users`, `edit users`, `delete users`, `manage roles`.
  - Roles base: **`admin`** (todos los permisos) y **`member`** (vacío).
  - **CRUD de usuarios por admin** (`Admin\UserController`): crear cuenta + asignar rol + marcar email verificado;
    guarda anti-"eliminar/degradar al último admin" (`wouldRemoveLastAdmin`).
  - **CRUD de roles por admin** (`Admin\RoleController`): crear/editar/eliminar roles y asignar permisos desde la UI;
    roles base (`admin`, `member`) inmutables; `admin` siempre conserva `manage roles`.
  - Comando CLI: `php artisan app:assign-role {email} {role}`.
- **Restricción de dominio `@impdali.cl`** (regla `App\Rules\ImpdaliEmail`) tanto al **crear cuentas** como al **iniciar sesión**.
- **Manejo elegante del error 419/CSRF**: en `bootstrap/app.php` se intercepta `HttpException` con
  `getStatusCode() === 419` (Laravel convierte `TokenMismatchException`→419 **antes** de los render callbacks)
  y se redirige con mensaje amable + `withInput`.
- **Hardening HTTPS** en producción: `trustProxies(at: '*')` + `URL::forceScheme('https')` (solo en `production`).
- **Librería de componentes Blade** madura (ver sección 6) — **reutilízala, no reinventes markup**.
- **CI/CD**: `git push origin main` dispara **GitHub Actions** → SSH al servidor → `deploy.sh` (sección 5).
- **Suite de tests verde** (PHPUnit, SQLite en memoria): gestión de usuarios (13), roles (11),
  dominio en login (2), + tests estándar de Breeze (login/logout/verificación/reset) + perfil.

**Último commit sincronizado de referencia:** `8e952d5` (puede haber más recientes; haz `git log` para confirmar).

### Lo que FALTA de M01 Core (el trabajo inmediato — ver Plan, sección 8)
- ~~**Multi-sucursal**~~ ✅ **Hecho** (Incremento 1, commit `e25d773`): tabla `sucursales` + `sucursal_id` en `users`.
- ~~**Roles reales del negocio**~~ ✅ **Hecho** (Incremento 2, commit `e1df23d`): roles del negocio + matriz de partida editable.
- ~~**Configuración global**~~ ✅ **Hecho** (Incremento 3): tabla `configuraciones` + accesores cacheados `Configuracion::get()/set()` + UI admin `/admin/configuracion`.
- ~~**Auditoría**~~ ✅ **Hecho** (Incremento 4): `owen-it/laravel-auditing` en User/Sucursal/Configuracion + audit manual de cambios de rol + UI `/admin/audits`.

**🎉 M01 Core COMPLETO** (Incrementos 1–4). El siguiente hito es M02+ según la biblia `PROYECTO_DALIGO.md`.

---

## 3. Stack técnico (local = producción, fijado a propósito)

| Pieza | Versión / detalle |
|---|---|
| Framework | **Laravel 12** (12.61.x) |
| PHP | **8.3.31** (fijado en `composer.json` → `config.platform.php`; local == prod) |
| Frontend | **Blade + Alpine.js**, **Tailwind CSS v4** vía `@tailwindcss/vite` (sintaxis `@import 'tailwindcss'`, **NO** `@tailwind`) |
| Build | **Vite**. **No** hay `tailwind.config.js` ni `postcss.config.js`; la config vive en `resources/css/app.css` (`@theme`) |
| Auth | **Laravel Breeze** (Blade) |
| Permisos | **spatie/laravel-permission** v8 (teams OFF, guard `web`, caché 24h) |
| BD local (dev) | **SQLite** |
| BD producción | **MySQL 5.7.23** (DB `impdali_daligo`) |
| Tests | **PHPUnit** (no Pest), SQLite en memoria |

### Restricciones de **MySQL 5.7** (¡importante para migraciones!)
- `Schema::defaultStringLength(191)` **ya está activo** en `AppServiceProvider` (índices en `utf8mb4` caben).
- Charset `utf8mb4` / collation `utf8mb4_unicode_ci`.
- **No** usar CTE (`WITH`), **window functions**, ni `JSON_TABLE` (no soportados en 5.7).
- MySQL 5.7 está **EOL** — riesgo conocido; pedir upgrade a 8.x cuando se pueda.

---

## 4. Infraestructura / hosting

- **Hosting:** HostGator **compartido** (cPanel + LiteSpeed). **Sin Node, sin Redis, sin Docker, sin daemons.**
- **Repo GitHub:** `https://github.com/DaliGo-2013/DaliGo` (¡este es el actual! El viejo `Mauricio-Alvarez-T/DaliGo` quedó obsoleto).
- **Servidor SSH:** usuario `impdali`, host `impdali.cl`, **puerto 2222**. Home: `/home4/impdali`. App: `/home4/impdali/daligo` (docroot = `.../daligo/public`).
- **Subdominio staging:** `staging.impdali.cl` (HTTPS con AutoSSL).
- **PHP del servidor:** `ea-php83` (binario `/opt/cpanel/ea-php83/root/usr/bin/php`). La versión se controla por **MultiPHP Manager (vhost)**, **NO** por `AddHandler` en `.htaccess` (eso da 404 en LiteSpeed).
- **opcache:** `opcache.validate_timestamps=1` (vía `public/.user.ini`) para que los deploys apliquen cambios PHP.
- **Hay una "IA de cPanel" aparte** que administra el hosting; por seguridad **no** autoriza llaves SSH/crear BD/deploys por SSH desde fuera. Tareas de cPanel (subdominio/PHP/SSL/BD) se le piden a esa IA; el deploy de código va por GitHub Actions.

---

## 5. Despliegue (CI/CD) — **`git push origin main` = deploy automático**

GitHub Actions (`.github/workflows/deploy.yml`) entra por **SSH (cliente OpenSSH nativo del runner**, *no* `appleboy/ssh-action` — ver bitácora) y corre **`deploy.sh`** en `/home4/impdali/daligo`. `deploy.sh` hace, en orden:

1. `git checkout -- public/.htaccess` (cPanel reinyecta un bloque handler; hay que descartarlo antes del pull)
2. `git pull --ff-only`
3. `composer install --no-dev --optimize-autoloader`
4. `php artisan migrate --force`
5. `php artisan db:seed --class=RolesAndPermissionsSeeder --force` *(el plan lo cambia a `db:seed --force`, ver sección 8)*
6. `php artisan storage:link` (`--force`)
7. `config:cache` + `route:cache` + `view:cache`
8. `php artisan permission:cache-reset`

**Reglas de oro del deploy:**
- **No** correr seeds/cachés a mano en producción; ya están cubiertos.
- `public/build/` **está versionado** (el server no tiene Node) → tras tocar Blade/CSS/JS hay que `npm run build` y **commitear `public/build/` junto con el cambio**, o el CSS/JS sale viejo (Tailwind v4 purga clases no usadas).
- Vigilar el avance en la pestaña **Actions** del repo. Verificar luego en `staging.impdali.cl`.

---

## 6. Convenciones de código y diseño (resumidas de `CLAUDE.md` — léelo completo)

### Diseño / UI (tema claro, sobrio, naranjo de marca)
- **Colores solo por utilidades**, nunca hex hardcodeado. Marca: `brand-600` (#ea580c primario), `brand-700` (hover), `brand-50/100` (fondos). Tokens en `resources/css/app.css` (`@theme`). Neutros `neutral-*`. Destructivo `red-600/500/50`.
- **Iconos:** Heroicons **outline 24** como componentes Blade en `components/icon/` (hoy: `pencil`, `trash`, `plus`). Formato: `fill="none"`, `viewBox="0 0 24 24"`, `stroke-width="1.5"`, `stroke="currentColor"`. Color se hereda con `currentColor`.
- **Radios:** `rounded-lg` (botones/inputs), `rounded-2xl` (tarjetas), `rounded-full` (badges/avatares).
- **Tipografía:** Instrument Sans. Títulos `text-xl font-semibold text-neutral-900`; secciones `text-xs uppercase tracking-wide text-neutral-500`; cuerpo `text-sm`.
- **Motion:** sutil, sin rebotes/loops. `transition duration-150`, `active:scale-[0.98]`, `dg-enter` al cargar, `dg-shake` en errores. Siempre respetar `prefers-reduced-motion`.
- **Responsive obligatorio** (mobile-first): se ve bien y **sin scroll horizontal** a **375 / 768 / 1024px**. Nada de anchos fijos sin fallback. Verificar a esos 3 anchos antes de cerrar.

### Catálogo de componentes (`resources/views/components/`) — **REUTILIZAR**
- **Layout/página:** `app-layout`, `guest-layout`, `page-header` (props `title`, `subtitle`, slot `action`), `list-card` (props `title`, `count`, `countLabel`), `list-row` (slots `leading`/`meta`/`actions`, **responsive: apila en móvil**), `avatar`, `modal`, `dropdown`/`dropdown-link`.
- **Nav:** `nav-link`, `responsive-nav-link` (prop `active`).
- **Botones/enlaces:** `primary-button`, `secondary-button`, `danger-button`, `button-link`, `icon-button` (props `href`, `variant=default|danger`, `label`, `type`, `title`), `secondary-link`.
- **Form:** `input-label`, `text-input`, `select`, `textarea`, `checkbox`, `radio`, `checkbox-item` (slot `note`), `input-error`, `input-hint`, `form-footer` (prop `cancel`).
- **Feedback:** `badge` (`variant=brand|neutral`), `status-alert` (`:status`), `auth-session-status`.

> Patrón de **lista**: `page-header` (+slot action `@can`) → `status-alert` → `list-card` con `list-row` por item (slots leading/meta/actions, `icon-button` editar/eliminar `@can`).
> Patrón de **formulario**: tarjeta `rounded-2xl border bg-white p-6` → `<form>` con bloques `input-label`+`text-input`/`select`+`input-error` → `form-footer :cancel=...` con `primary-button`.

### Patrón admin (backend)
- Rutas con middleware **`permission:<x>`** por acción. Grupo en `routes/web.php` bajo `auth`.
- Seeders **idempotentes** (`firstOrCreate`). Guardas de negocio en controladores (ej. no borrar último admin / roles base).
- Enlaces de nav condicionados con `@can('<permiso>')` (desktop **y** responsive).

### Flujo de trabajo
- **Tests:** `composer test` o `php artisan test`. Tests en `tests/Feature/` y `tests/Unit/`.
- **Entorno local:** `composer dev` (serve+queue+pail+vite) o por separado `php artisan serve` + `npm run dev`.
- **Regla de oro (CLAUDE.md):** cuando resuelvas un error de deploy/build/infra/feature, **agrega una entrada a la bitácora de `CLAUDE.md` ANTES de cerrar la tarea** (síntoma + causa + solución que funcionó).

---

## 7. Mapa de archivos clave

```
app/
  Models/User.php                      # HasFactory, Notifiable, HasRoles; implements MustVerifyEmail; fillable name/email/password
  Rules/ImpdaliEmail.php               # exige dominio @impdali.cl
  Http/Requests/Auth/LoginRequest.php  # aplica ImpdaliEmail + rate limit
  Http/Controllers/Admin/
    UserController.php                  # CRUD usuarios + asignar rol + guarda último-admin
    RoleController.php                  # CRUD roles + permisos; BASE_ROLES=['admin','member']
  Console/Commands/AssignRole.php       # app:assign-role {email} {role}
  Providers/AppServiceProvider.php      # defaultStringLength(191) + forceScheme https en prod
bootstrap/app.php                       # trustProxies('*'), alias middleware spatie, render 419 amable
database/
  migrations/                           # users, cache, jobs, permission_tables (spatie)
  seeders/RolesAndPermissionsSeeder.php # 5 permisos + roles admin/member (idempotente)
  seeders/DatabaseSeeder.php            # llama a RolesAndPermissionsSeeder
  factories/UserFactory.php             # email = ...@impdali.cl
routes/web.php                          # grupo admin (users + roles) con permission:* por ruta
routes/auth.php                         # rutas Breeze
resources/css/app.css                   # @theme (brand-*, Instrument Sans), dg-enter/dg-shake, prefers-reduced-motion
resources/views/components/             # librería de componentes (ver sección 6)
resources/views/admin/{users,roles}/    # vistas index/create/edit (patrones a copiar)
resources/views/layouts/{app,navigation}.blade.php
.github/workflows/deploy.yml            # CI/CD (OpenSSH nativo)
deploy.sh                               # pasos de despliegue (sección 5)
public/build/                           # ASSETS COMPILADOS — versionados, commitear tras npm run build
CLAUDE.md                               # reglas vivas + bitácora de errores
```

---

## 8. Plan en curso — **Completar M01 Core** (trabajo inmediato)

**Decisiones ya tomadas con el usuario:**
- **Cadencia:** *incremento por incremento.* Cada uno: construir → `php artisan test` verde → `npm run build` → commit → `git push` → vigilar **Actions** → verificar en **staging** → recién entonces el siguiente.
- **Roles:** los **7 del negocio** (`vendedor`, `jefe_ventas`, `jefe_bodega`, `conductor`, `tecnico`, `soplador`, `admin`) **+ mantener `member`** como genérico. Nombres en ASCII (el regex del `RoleController` no acepta tildes); mostrar bonito en UI con `Str::headline()`.
- **Vínculo usuario↔sucursal:** **una** sucursal por usuario (FK `sucursal_id` nullable). Extensible a pivote si el negocio lo pide.
- **Matriz de permisos completa:** queda **pendiente de Sprint 0** (decisión de negocio). Se deja una **matriz de partida editable** desde la UI de roles.

### Incremento 1 — Multi-sucursal — ✅ HECHO (commit `e25d773`)
- Migración `create_sucursales_table`: `nombre`(191), `codigo`(191 unique), `ciudad`(null), `direccion`(null), `es_central`(bool), `activa`(bool, def true), timestamps.
- Migración `add_sucursal_id_to_users`: `sucursal_id` nullable FK → sucursales (`onDelete set null`).
- Modelo `App\Models\Sucursal` (`users()` hasMany). `User`: `sucursal()` belongsTo + `sucursal_id` en fillable.
- `SucursalSeeder` idempotente: Mirador (central), Coquimbo, Abate Molina, Buzeta.
- `Admin\SucursalController` (index/create/store/edit/update/destroy), permiso `manage sucursales`, guarda: no eliminar sucursal con usuarios.
- Rutas `/admin/sucursales` (resource except show) bajo `permission:manage sucursales`.
- Vistas `admin/sucursales/{index,create,edit}` (patrón lista/form, reusar componentes).
- Extender `UserController` + vistas `admin/users/{create,edit}`: **selector de sucursal** (`x-select`, nullable).
- Nav: enlace "Sucursales" (`@can('manage sucursales')`, desktop + responsive).
- Tests `Admin/SucursalManagementTest` (CRUD, 403 no-admin, guarda con usuarios).

### Incremento 2 — Roles reales del negocio + matriz de partida — ✅ HECHO (commit `e1df23d`)
> Nota: el Inc 2 original mantuvo `Soplador`/`Jefatura` (TitleCase) junto a los nuevos roles ASCII.
> **ACTUALIZADO (2026-06-10):** tras la auditoría (`docs/AUDITORIA-M01-M02.md`) se **reconcilió a
> 8 roles ASCII** con la migración `reconcile_business_roles`: `Soplador`→`soplador` (renombre,
> preserva asignaciones), `Jefatura`→consolidado en `jefe_bodega` (que ahora tiene
> `manage production` + `view users`), y el huérfano `Ventas` eliminado. Set final:
> `admin, member, vendedor, jefe_ventas, jefe_bodega, conductor, tecnico, soplador`.
> Los nombres de rol creados por UI se normalizan a minúsculas (`RoleController`). Labels de
> permisos centralizados en `config/permissions.php`; display con `Str::headline`.
> `manage settings`/`view audit` se **difirieron** a los Incrementos 3 y 4.
- Extender `RolesAndPermissionsSeeder` (idempotente): crear los 6 roles nuevos del negocio (admin/member ya existen).
- Permisos nuevos de los incrementos: `manage sucursales`, `manage settings`, `view audit` → asignados a `admin`.
- Matriz de partida: `admin`=todos; `jefe_ventas`/`jefe_bodega`=`view users`; el resto (operativos)=sin permisos de gestión (recibirán permisos por módulo a medida que se construyan).

### Incremento 3 — Configuración global — ✅ HECHO
> Modelo `App\Models\Configuracion` (tabla `configuraciones`): valor tipado (`string/integer/decimal/
> boolean/json`) guardado como texto, casteado por `tipo`; accesores estáticos cacheados
> `Configuracion::get($clave,$default)` / `set($clave,$valor)` (`Cache::rememberForever('config.'.$clave)`,
> invalidado en `set()`). **Siempre escribir vía `set()`** (editar la tabla a mano en BD deja la caché
> obsoleta). UI admin solo index/edit/update (`permission:manage settings`); permiso creado aquí
> (diferido del Inc 2). `ConfiguracionSeeder` idempotente siembra `umbral_aprobacion_clp` y
> `cotizacion_vigencia_dias`.
- Migración `create_configuracion_table`: `clave`(191 unique), `valor`(text null), `tipo`(string/integer/decimal/boolean/json), `grupo`(191), `descripcion`(null), timestamps.
- Modelo `App\Models\Configuracion` con cast por `tipo` y estáticos **`Configuracion::get($clave,$default)` / `set()`** cacheados (`Cache::rememberForever('config.'.$clave)`, invalidar al guardar). (Sin helper global → sin tocar el autoload.)
- `ConfiguracionSeeder` idempotente: semilla mínima (`umbral_aprobacion_clp=1000000`, `cotizacion_vigencia_dias=5`). Más parámetros cuando existan los módulos.
- `Admin\ConfiguracionController` (index agrupado por `grupo`, edit/update), permiso `manage settings`. Input según `tipo`.
- Rutas `/admin/configuracion`. Vistas `admin/configuracion/{index,edit}`. Nav "Configuración" (`@can('manage settings')`).
- Tests `Admin/ConfiguracionTest`.

### Incremento 4 — Auditoría — ✅ HECHO
> `owen-it/laravel-auditing` v14. Modelos `User`/`Sucursal`/`Configuracion` con
> `implements AuditableContract` + trait; `User` excluye `password`/`remember_token` vía `$auditExclude`.
> Cambios de rol (pivote spatie, no auto-auditado) se registran **manualmente** con un custom audit
> (`AuditCustom`, evento `roleChanged`) en `UserController`. `Admin\AuditController@index` (solo lectura,
> paginado, filtros por usuario/modelo), permiso `view audit`, vista `admin/audits/index`.
> **Gotcha clave:** owen-it no audita en CLI salvo `audit.console=true`; se hizo env-driven
> (`AUDITING_CONSOLE`), `true` solo en `phpunit.xml` (tests), `false` en prod (los seeders del deploy
> no generan ruido).
- `composer require owen-it/laravel-auditing`; publicar config + migración `audits`.
- `implements Auditable` + `use \OwenIt\Auditing\Auditable` en `User`, `Sucursal`, `Configuracion`.
- Auditar cambios de rol **manualmente** en `UserController::update` (owen-it no captura el pivote spatie).
- `Admin\AuditController@index` (solo lectura, paginado, orden fecha desc, filtro por usuario/modelo), permiso `view audit`.
- Ruta `/admin/audits`. Vista `admin/audits/index` (list-card/list-row: usuario · evento · modelo · fecha · IP). Nav "Auditoría" (`@can('view audit')`).
- Tests `Admin/AuditTest`.

### Cableado transversal (al integrar los incrementos)
- `DatabaseSeeder` llama (idempotente) a Roles + Sucursal + Configuracion seeders.
- `deploy.sh`: cambiar el seed a `php artisan db:seed --force` (DatabaseSeeder completo); mantener `permission:cache-reset`.

### Verificación de cada incremento
- **Local (SQLite):** `php artisan migrate:fresh --seed` → `php artisan test` (todo verde) → `npm run build` → `php artisan serve` y probar a mano (admin ve la sección nueva; no-admin → 403).
- **Prod (MySQL 5.7):** `git push` → vigilar **Actions** → verificar en staging (migraciones aplicadas, vigilar índices en 5.7, seeders corridos, enlaces de nav). Asignar sucursal/rol a usuarios existentes.

> El plan original local está en `C:\Users\mauri\.claude\plans\functional-squishing-river.md` (esta sección es su copia portable).

---

## 8b. M02 — Catálogo + Bsale (estado al 2026-06-10)

**Hecho y en producción:**
- **Catálogo local `productos`** (nivel SKU = variante de Bsale): sku único, nombre, descripción,
  categoría, marca, **peso_kg + alto/ancho/largo_cm** (lo que Bsale NO tiene y M08 necesita),
  `atributos` JSON, activo, `barcode`, `bsale_variant_id`/`bsale_product_id`/`bsale_product_type_id`.
  CRUD admin (`ProductoController`, permiso `manage productos`), Auditable.
- **Import/export CSV** apto Excel-CL (export `;`+BOM; import autodetecta separador, decimales con
  coma, Windows-1252). El import tiene **semántica de PARCHE**: solo toca columnas presentes en el
  archivo (celda vacía borra; columna ausente no se toca); upsert por SKU; filas malas se saltan y
  reportan; `barcode`/`bsale_*` son **solo-export** (no importables). Contadores
  creados/actualizados/sin_cambios/**vaciados**/errores. Sin audit por fila.
- **Sincronización con Bsale** (`bsale:sync-catalog`, manual): barrido read-only de
  productos+variantes (`app/Services/Bsale/BsaleClient` + `CatalogSync`), upsert por
  `bsale_variant_id` (escalera: variant_id → sku sin enlazar [adopción CSV] → crear), **preservando
  campos locales** (peso/dims/marca/descripcion/atributos). Token en `.env`
  (`BSALE_ACCESS_TOKEN`, empresa 26021); corrido en prod: **2.846 productos**. Referencia API:
  `docs/BSALE_API.md`.
- **Plantilla de medidas** (`productos/plantilla-medidas`): CSV de trabajo con los SKUs de medidas
  incompletas + columnas de referencia no importables; filtro `medidas` y contador de progreso
  "X de Y activos" en el index. **Tarea operativa en curso: el equipo carga peso/dimensiones.**

**Pendiente de M02+:** sync de precios (15 listas por zona en Bsale, solo lectura), webhooks
(alta self-service en el panel de Devs) y/o cron para automatizar la sync, enlace catálogo↔M04.

---

## 9. Deuda técnica / pendientes conocidos

- **Rotar la contraseña de la BD** `impdali_daligo`: se compartió por chat en algún momento → cambiarla en cPanel y actualizar `.env` del servidor.
- **Cuenta `marcos.uribe.impdali@gmail.com`**: quedó bloqueada por la regla de dominio `@impdali.cl` (login solo permite ese dominio). Decidir qué hacer (crear su cuenta `@impdali.cl` o excepción).
- **Dominio de staging:** la biblia menciona `daliprueba.cl`; nosotros usamos `staging.impdali.cl`. Reconciliar antes de producción real.
- **Cron de cola:** cuando llegue M15 (notificaciones), agregar un segundo cron `php artisan queue:work --stop-when-empty --max-time=55` (el server no tiene daemons).
- **Matriz de permisos por módulo:** se define en Sprint 0 con el negocio; hoy solo hay permisos de usuarios/roles + los nuevos de M01.
- **MySQL 5.7 EOL:** pedir upgrade a 8.x cuando el hosting lo permita.
- **PWA offline:** es el mayor riesgo del proyecto (módulo posterior); diseñar con cuidado.

---

## 10. Arranque rápido para la nueva IA / nuevo entorno

```bash
git clone https://github.com/DaliGo-2013/DaliGo.git
cd DaliGo
composer install
npm install
cp .env.example .env        # configurar para SQLite local
php artisan key:generate
# en .env: DB_CONNECTION=sqlite ; crear database/database.sqlite vacío
php artisan migrate:fresh --seed
npm run build
php artisan test            # debe quedar verde
php artisan serve           # + npm run dev   (o: composer dev)
```

- **Crear el primer admin** (local): registrar/crear un usuario y luego `php artisan app:assign-role tucorreo@impdali.cl admin`.
- **Deploy:** trabajar en `main`, `npm run build` + commit de `public/build/`, `git push origin main`, vigilar Actions, verificar en `staging.impdali.cl`.
- **Antes de cualquier cosa:** leer `CLAUDE.md` (reglas + bitácora) y respetar las convenciones de la sección 6.

---

*Fin del HANDOFF. Mantener este archivo actualizado a medida que M01 Core avance.*
