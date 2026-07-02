# HANDOFF — DaliGo (traspaso de contexto para continuar el proyecto)

> **Para la IA que recibe esto:** este documento es el **manual técnico** de DaliGo y
> **complementa** a [`PROYECTO_DALIGO.md`](PROYECTO_DALIGO.md) (la "biblia" de producto/negocio, en este mismo repo).
> La **biblia** dice *qué* construir y *por qué*; este **HANDOFF** dice *cómo está hecho* y *cómo se despliega*.
> ⚠️ **El ESTADO del proyecto (qué está hecho, qué sigue, avance) NO vive aquí: vive en
> [`docs/RUTA-MAESTRA.md`](docs/RUTA-MAESTRA.md) §0** — regla de "estado único". En el repo también hay un `CLAUDE.md` con
> las reglas vivas + bitácora de errores (Claude Code lo lee solo; otras IAs deberían leerlo igual).
>
> **Idioma del proyecto:** español (UI, commits, comunicación).
> **Última actualización:** 2026-07-01 (sesión E0 de consolidación).

---

## 0. Cómo usar este documento

1. Lee `PROYECTO_DALIGO.md` (la biblia: 16 módulos M01–M16, reglas de negocio, Gantt).
2. Lee `docs/RUTA-MAESTRA.md` (**dónde estamos y qué sigue** — el estado vivo del proyecto).
3. Lee este `HANDOFF.md` (stack, infra, deploy, convenciones, cómo quedó implementado lo construido).
4. Lee `CLAUDE.md` del repo (reglas de diseño + **bitácora de errores resueltos** — no repitas esos errores).
5. Para trabajar: sigue `docs/PROTOCOLO-SESION.md` (retomar en 10 minutos + checklist de cierre).

---

## 1. Qué es DaliGo (resumen de 30 segundos)

Sistema de gestión interno (ERP-lite) para **Importadora DALI / DALI Cargos-Transporte** (Chile).
**Objetivo central:** matar el papel en el ciclo de la factura y dar trazabilidad (quién/qué/cuándo/dónde).
**No reemplaza a Bsale** (el ERP/facturación actual): lo **complementa**. 4 bodegas/sucursales:
**Mirador** (central), **Coquimbo**, **Abate Molina**, **Buzeta**. Debe funcionar como **PWA con modo offline**
(es el mayor riesgo técnico del proyecto). Ver la biblia para el detalle de los 16 módulos y las
18 correcciones de negocio de "Luis".

---

## 2. Qué hay construido (inventario técnico — el ESTADO vive en `docs/RUTA-MAESTRA.md`)

Sistemas en producción (staging) y testeados — el detalle de implementación de cada uno está en las
secciones 8/8b/8c/8d/8e/8f: **M01 Core** (§8) · **M02 Catálogo+Precios** (§8b) · **M03 Clientes** (§8c) ·
**M11 Producción F1** (§8d) · **Espejo de inventario, base de M04** (§8e) · **Taller de servicio técnico,
subset de M12** (§8f). Suite de tests: **~358 verdes**.

> ⚠️ **M13 Devoluciones NO tiene código.** No confundir con la acción `devolver` de los reportes de
> producción (M11, §8d) ni con el taller (§8f). Se registra aquí porque una redacción anterior de este
> documento se prestaba a esa confusión.

Piezas transversales de M01 (base de todo):

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

**Qué sigue y en qué orden:** `docs/RUTA-MAESTRA.md` (unidades E0–E13). Haz `git log` para confirmar el último commit.

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

## 8. M01 Core — cómo quedó implementado (referencia técnica)

> Sección histórica de implementación. M01 está COMPLETO; el estado vive en `docs/RUTA-MAESTRA.md`.

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

> (Esta sección es la copia portable del plan de M01; el archivo local original quedó en una máquina antigua y ya no es referencia.)

---

## 8b. M02 — Catálogo + Bsale (cómo quedó implementado)

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

- **Automatización (cron):** las 3 syncs (`bsale:sync-catalog/clients/prices`) están registradas en
  el scheduler de Laravel (`routes/console.php`), escalonadas por hora (catálogo :00 → clientes :20
  → precios :40; el catálogo va primero porque los precios matchean por `bsale_variant_id`), con
  `withoutOverlapping(15)` y salida a `storage/logs/bsale-sync.log`. **Requiere el cron de cPanel**
  `* * * * * php artisan schedule:run` (ver §5/CLAUDE.md). Frecuencia tunable en `routes/console.php`.

**Pendiente de M02+:** webhooks (sync incremental, alta self-service en el panel de Devs);
enlace catálogo↔M04 (inventario).

### M02.2 — Listas de precios (cómo quedó implementado)

**Hecho:** espejo read-only de las listas de precios de Bsale (lo que faltaba para que M05 cotice).
- **Tablas:** `listas_precios` (nombre, descripción, `bsale_coin_id` [1=CLP], activa, **local:**
  `canal` — la convención "una lista = un canal" no existe en Bsale, la define DaliGo;
  `bsale_price_list_id` unique) y `precios` (FK lista+producto con cascade, `precio_neto` +
  `precio_con_iva` en **decimal(14,4)** — el neto real llega como float largo bruto/1.19,
  `bsale_detail_id`, **unique compuesto lista+producto**).
- **Sync `bsale:sync-prices`** (`App\Services\Bsale\PriceListSync`): cabeceras de TODAS las listas
  (espeja el flag activa, preserva `canal`); **detalles solo de listas ACTIVAS** (las inactivas son
  promos muertas); match al catálogo por `bsale_variant_id` (variante sin producto local se omite y
  cuenta). **A diferencia del catálogo, los precios que Bsale ya no manda SE BORRAN** (un precio
  obsoleto induce a cotizar mal). Shape real verificado: ids llegan como STRING, coin={href,id}.
- **UI** `/admin/listas-precios` (permiso `manage productos`, nav "Precios"): index con listas
  (moneda/canal/inactiva/# precios), show con valores paginados + filtro por SKU/nombre + edición
  del `canal`; la ficha del producto (edit) muestra sus precios por lista (solo lectura).
  `ListaPrecio` auditable (en `AuditController::MODELOS`); `Precio` NO (espejo masivo).
- **Tests:** `BsalePreciosSyncTest` (8) + `ListaPrecioManagementTest` (8).

**Pendiente:** automatización (cron/webhooks), y el mismo diferimiento de staleness de cabeceras
que catálogo/clientes (lista eliminada en Bsale queda con su último estado local).

---

## 8c. M03 — Clientes (cómo quedó implementado — Incremento 1)

**Hecho:** ficha local de clientes + CRUD admin + sync desde Bsale, clonando los patrones de M02.
- **Tabla `clientes`**: `rut` (varchar 20, **unique nullable**, normalizado `12345678-9` sin puntos,
  K mayúscula — nullable porque Bsale trae clientes sin RUT), razón social, giro, email, teléfono,
  dirección/ciudad/comuna, `es_empresa` (Bsale `companyOrPerson`), `envio_factura_email` (Bsale
  `sendDte` — la verdad del envío de DTE vive en Bsale), `activo`; **locales que la sync jamás toca:**
  `segmento` (mayorista/retail/recurrente), `notas`, `vendedor_id` (FK a users, cartera — corrección #2).
  Enlace por `bsale_client_id` (index).
- **Modelo `Cliente`** auditable (en `AuditController::MODELOS`), con `normalizarRut()` y `dvRut()`
  (módulo 11). Regla `App\Rules\RutChileno` valida DV **solo en entrada manual** (la sync no rechaza
  datos históricos). El RUT se normaliza ANTES de validar el unique (mismo RUT en distinto formato = duplicado).
- **CRUD** `/admin/clientes` (permiso único `manage clientes`; **piso: admin + vendedor +
  jefe_ventas** — regla #2 "la gestión es por VENDEDOR") con filtros q (RUT con o sin puntos /
  razón social), segmento, vendedor, estado. Vistas con la librería de componentes.
  **Anti-zombie:** un cliente enlazado a Bsale no se puede eliminar localmente (el sync lo
  recrearía perdiendo segmento/notas/vendedor); se desactiva en Bsale y el espejo lo refleja.
- **Sync `bsale:sync-clients`** (`App\Services\Bsale\ClientSync`, clon de `CatalogSync`): pagina
  `clients.json?state=0`, escalera de upsert (match `bsale_client_id` → adopción por `rut` → create),
  colisiones de RUT duplicado en Bsale se omiten y reportan, `withoutAuditing`, stats al log.
  **Extranjeros (`isForeigner`):** su `code` se guarda crudo (no se normaliza: les borraría las
  letras y fabricaría RUTs falsos). **Consumidor final (`66666666-6`):** se guarda rut null (Bsale
  puede traer varios y el unique los volvería ruido). Personas (`companyOrPerson=0`) prefieren
  nombre+apellido sobre `company`.
  **Shape verificado contra la API real** (~47.800 clientes; email/phone planos; `code` con puntos y a veces vacío).
- **Tests:** `ClienteManagementTest` (19) + `BsaleClientesSyncTest` (11).

**Limitación conocida (igual que el catálogo):** el barrido usa `state=0`, así que un cliente
eliminado en Bsale (state 99) queda `activo=true` local — la desactivación-por-ausencia se difiere
a un incremento posterior.

**Pendiente de M03+:** búsqueda por RUT con precarga desde Bsale en el form, historial de compras y
boleta rápida (dependen de M05), cron de sync.

---

## 8d. M11 — Producción de botellones / sopladores (cómo quedó implementado — Fase 1)

> **Importante (drift histórico):** `docs/PLAN-M11-FASE2.md` describe un diseño que **NO** es el
> implementado. El código siguió un diseño alternativo (máquinas + tipos de botellón + tandas), no el
> de productos+movimientos del plan. Esta sección es el estado **real**.

**Modelo de datos (real):**
- `produccion_asignaciones` (soplador/fecha/turno/`asignadas`/`preforma_id`→productos nullable/`creado_por`; UNIQUE soplador/fecha/turno) — modelo `ProduccionAsignacion` (hasOne reporte, belongsTo preforma).
- `produccion_reportes` (1:1 con asignación; `primera/segunda/malo/danada`, motivo/obs, estado `borrador|enviado|aprobado|devuelto`, enviado/revisado, motivo_ajuste, devuelto_motivo). Auditable. Derivados: `total` (4 categorías = consumo), **`producido` (1ª+2ª = vendible)**, **`merma` (malo+danada)**, `diferencia` (asignadas−total), `tasa_*`. `recalcularDesdeRegistros()` denormaliza desde las tandas.
- `produccion_registros` (tandas, append-only: máquina + tipo de botellón + 4 cantidades).
- `maquinas` (por sucursal; `Maquina::paraSoplador()` filtra por sucursal con fallback) y `tipos_botellon` (`codigo/nombre/`**`producto_id`**`/activo`). Ambos auditables.
- **`produccion_movimientos` (kardex local):** se escribe SOLO al aprobar (`ProduccionMovimiento::generarParaReporte` en `aprobar`, en transacción, idempotente). Tipos: `consumo_preforma|produccion_primera|produccion_segunda|merma`. `producto_id` nullable (degrada si la preforma/tipo no está enlazada). **NUNCA toca `stocks`/`bodegas`** (espejo Bsale); es la verdad local de producción, lista para empujar a Bsale (receptions/consumptions) en una fase futura.

**Controllers / rutas:**
- `Admin\ProduccionController` (`permission:manage production`): `index` (panel del día + por máquina), `sopladores`/`sopladorHistorial`, `asignar`/`asignarStore` (con preforma + transacción), `reporteShow`, `aprobar` (→ kardex), `devolver`, `ajustar`, **`movimientos` (kardex con filtros producto/tipo/fecha + resumen)**. CRUD `maquinas` y `tipos-botellon`.
- `Produccion\MiProduccionController` (`permission:report production`): `index`/`show` del reporte del día, `registroStore`/`registroDestroy` (tandas), `update` (motivo/obs + enviar; motivo obligatorio si diferencia≠0).

**UI:** vista de operario `produccion/mi-reporte.blade.php` (celular, `x-stepper-input` táctil + `x-chip-radio`, diferencia y vendible en vivo). Panel y detalle del jefe con preview "al aprobar se registrará" y kardex. Roles: `soplador`→`report production`; `jefe_bodega`→`manage production`.

**Seeders:** `TipoBotellonSeeder` (tipos base), `MaquinaSeeder` (sopladoras Mirador/Coquimbo), `ProduccionTesteoSeeder` (productos `TEST-` preforma/botellón + enlaza tipos; `bsale_variant_id` null para que el `CatalogSync` no los pise). Todos idempotentes; corren en el deploy (`db:seed --force`).

**Tests:** `ProduccionTest` (flujo Fase 1, 37) + `ProduccionKardexTest` (generación de movimientos, idempotencia, sin-preforma, devolver-no-genera, kardex 403/filtro, accessors producido/merma, seeder idempotente).

**Pendiente de M11+:** push del kardex a Bsale (validar con Víctor); PWA offline; consolidar `tipos_botellon`/`maquinas` en `productos` (decisión estratégica); meta/indicadores avanzados por soplador; auditar recalibrado al cambiar preforma.

---

## 8e. Espejo de inventario (base de M04 — cómo quedó implementado)

> Documentado el 2026-07-01: este código existía sin sección propia en el HANDOFF ("código fantasma").
> Es el punto de partida REAL de M04 (unidad E3 de la RUTA-MAESTRA): se construye encima, no desde cero.

- **Tablas** (migración `2026_06_11_120000_create_inventario_tables.php`): `bodegas` (nombre,
  `bsale_office_id` unique, flags) y `stocks` (FK bodega+producto, `stock_real`/`stock_reservado`/
  `stock_disponible`, `bsale_stock_id`; unique compuesto bodega+producto).
- **Sync `bsale:sync-stock`** (`App\Services\Bsale\StockSync`, patrón de `CatalogSync`): espeja
  offices (bodegas) y stock por variante desde Bsale, **read-only** (DaliGo jamás escribe stock en
  Bsale). Agendado en `routes/console.php` a los **:50 de cada hora** (después de catálogo :00,
  clientes :20, precios :40 — el catálogo va primero porque el match es por `bsale_variant_id`).
  ⚠️ **Hallazgo 2026-07-01, RESUELTO el 2026-07-02 (P-S0-07):** con los crons reales del servidor
  (`*/20` y `*/15`) el minuto :50 nunca coincidía → esta sync **no corría en producción**. Cron
  corregido a `* * * * *` y **verificado**: primera corrida a las :50 con 16 bodegas y 28.350 stocks
  actualizados, 0 errores (evidencia `docs/qa/INFRA/2026-07-02--INFRA--cron-deploysh-infra.md`).
- **UI** `/admin/bodegas` (`Admin\BodegaController`, index/show): listado de bodegas y stock por
  bodega. Vistas `admin/bodegas/{index,show}`.
- **Tests:** `BodegaManagementTest` + `BsaleStockSyncTest`.
- **Lo que NO hace todavía** (es el trabajo de M04/E3–E4): clasificación física/virtual/propósito,
  mapping bodega↔sucursal, vista cruzada por perfil, alertas, reservas por vendedor, movimientos
  locales, transferencias.

---

## 8f. Taller de servicio técnico (subset de M12 — cómo quedó implementado)

> Documentado el 2026-07-01: ídem §8e. Es la base sobre la que E9 construye el M12 completo.

- **Modelos:** `OrdenServicio` (folio = id; cliente, sucursal, fecha_ingreso, tipo_equipo
  ("dispensador" renombrado de "maquina"), marca/modelo/nº serie, falla_reportada, estado, observaciones,
  fecha_entrega/aviso/retiro, trabajo_realizado, mano_obra, `facturacion` garantia|reparacion,
  garantía por documento: tipo/número/fecha — vigencia 6 meses desde compra) y
  `OrdenServicioRepuesto` (producto, cantidad, precio_unitario, subtotal).
- **Estados:** `recibido → en_revision → cotizacion → esperando_repuesto → reparado → entregado | sin_solucion`.
- **Controller:** `Admin\ServicioTecnicoController` (12 métodos: CRUD + `reparacion`/`guardarReparacion` +
  buscadores AJAX `buscarCliente`/`buscarProducto`/`buscarRepuesto`). Permisos: `view servicio tecnico`
  (jefes/vendedores, lectura) y `manage servicio tecnico` (técnico, gestión completa).
- **Vistas:** `admin/servicio-tecnico/*` (index con filtros, show, form, reparación con repuestos).
- **Tests:** `ServicioTecnicoManagementTest` (~520 líneas).
- **Lo que NO hace todavía** (M12 completo, unidad E9): pre-ingreso online con QR, cotización
  estructurada con aprobación del cliente por link WhatsApp, alertas 3/6/12 meses, tablero de plazos,
  sugerencia de repuestos por histórico, cobro de hora de servicio en no aprobadas.

---

## 9. Deuda técnica / pendientes conocidos

> El seguimiento operativo de estos ítems vive en `docs/RUTA-MAESTRA.md` (varios son pasos P-S0-xx de la unidad E0).

- **Rotar la contraseña de la BD** `impdali_daligo`: se compartió por chat en algún momento → cambiarla en cPanel y actualizar `.env` del servidor.
- **Dominio de staging:** la biblia menciona `daliprueba.cl`; nosotros usamos `staging.impdali.cl`. Reconciliar antes de producción real.
- **Cron de cola:** cuando llegue M15 (notificaciones), agregar un segundo cron `php artisan queue:work --stop-when-empty --max-time=55` (el server no tiene daemons).
- **Matriz de permisos por módulo:** se define en Sprint 0 con el negocio; hoy solo hay permisos de usuarios/roles + los nuevos de M01.
- **MySQL 5.7 EOL:** pedir upgrade a 8.x cuando el hosting lo permita.
- **PWA offline:** es el mayor riesgo del proyecto (módulo posterior); diseñar con cuidado.
- ~~**🔴 M02.2 sync de precios — footgun de borrado masivo**~~ ✅ **RESUELTO**: guard en `PriceListSync::syncDetalles` — si la lista trae details pero 0 matchean el catálogo local, se salta el delete y se reporta en `errores` (un barrido con 0 matches nunca es espejo fiel; es catálogo desincronizado). Lista legítimamente vacía en Bsale (`0 details`) sí espeja el borrado. Tests de regresión: 0-match-no-borra y fallo-de-API-a-mitad-no-borra (`BsalePreciosSyncTest`).
- ~~**`productos.bsale_variant_id` sin `unique`**~~ ✅ **RESUELTO (capa de aplicación)**: `bsale_variant_id`/`bsale_product_id` eliminados de `ProductoController::validateData` — el form no los expone y la sync es la única dueña del enlace (test `test_form_ignores_bsale_link_fields`). El import CSV ya los ignoraba (solo-export). **Pendiente opcional:** índice `unique` en BD como cinturón extra (verificar 0 duplicados en prod antes de migrar).
- ~~**Cron mal configurado (hallazgo 2026-07-01, P-S0-07)**~~ ✅ **RESUELTO (2026-07-02):** había DOS
  entradas de `schedule:run` (`*/20` y `*/15`) y ninguna por-minuto → `bsale:sync-stock` (hourlyAt 50)
  jamás corría. Se dejó UNA línea `* * * * *` y se verificó la primera corrida de :50 (16 bodegas,
  28.350 stocks, 0 errores). Evidencia: `docs/qa/INFRA/2026-07-02--INFRA--cron-deploysh-infra.md`.
  Regla derivada: el cron de `schedule:run` es SIEMPRE `* * * * *` (bitácora CLAUDE.md 2026-07-02).

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

*Fin del HANDOFF. Este archivo documenta CÓMO está hecho; se actualiza cuando cambia arquitectura,
infra, deploy o el mapa de archivos. El estado y el avance viven en `docs/RUTA-MAESTRA.md`.*
