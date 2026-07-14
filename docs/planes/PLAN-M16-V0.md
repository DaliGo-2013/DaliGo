# PLAN-M16-V0 · Dashboard ejecutivo (solo lectura) — mini-plan de alcance
> **Estado: VIGENTE — sellado contra el código el 2026-07-14 (main @ `c86e341`, con M14 ya en producción)**

> **Unidad:** M16-v0 · Dashboard ejecutivo (elegida por el dueño 14-07) · **Rama:** `feature/m16-v0-dashboard` · **Stream:** 1 (Max-1)
> **Objetivo v0:** que el dueño (y cada jefatura) abra el **Inicio** y vea de un vistazo los indicadores
> de lo que YA está en producción — Producción M11, Servicio Técnico M12, Aprobaciones M14,
> Notificaciones M15 — como **cards de lectura clickeables** que llevan a su módulo. **Solo lectura:**
> sin export Excel/PDF, **sin migraciones**, sin permisos nuevos, sin escrituras.
> **Hecho cuando:** `/dashboard` muestra el tablero agrupado por módulo con números correctos y
> clickeables; cada rol ve **solo** sus cards; suite verde; responsive 375/768/1024.
> **Gate previo al código:** confirmación del Director por el buzón ANTES de construir vistas (dictado v5, 14-07).

## 0. Verificación de vigencia (qué se revisó del código — todo con archivo:línea real)

| Área | Archivo verificado | Estado hoy (2026-07-14, main `c86e341`) |
|---|---|---|
| Inicio actual | `routes/web.php:31`, `app/Http/Controllers/DashboardController.php:113` | `GET /dashboard` (middleware solo `auth`) → `DashboardController::index` → `dashboard.blade.php`. Gatea **por permiso** (`$user->can(...)`), nunca por rol (:17). Un `member` sin permisos ve solo el saludo |
| Cards existentes (semilla) | `DashboardController.php:29-79` | 9 indicadores: *Reportes por revisar* (`manage production`, `ProduccionReporte::pendientes()->count()`, alerta), *Productos sin medidas* (`manage productos`), *Clientes* (`manage clientes`), **5 cards ST** (`manage servicio tecnico`, UN `selectRaw('estado, COUNT(*)')->groupBy('estado')->pluck()` :65 — *Equipos en taller* = todo excepto `entregado` :70, + 4 por estado con href filtrado `?estado=` :73), *Usuarios* (`view users`) |
| Componente | `resources/views/components/stat-card.blade.php:1` | `@props(['label','valor','href','alerta'=>false])`; `<a>` completo `rounded-2xl`; número `text-3xl` que se pinta `brand-600` solo si `alerta && valor > 0`; `number_format($valor, 0, ',', '.')` |
| Grilla | `resources/views/dashboard.blade.php:41` | `grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-5`; `x-stat-card` se usa SOLO aquí |
| Contrato de tests | `tests/Feature/DashboardTest.php:14` | Suite dedicada (9 tests): conteos exactos vía `viewData('indicadores')` (:64), visibilidad por rol (:37/:52/:128/:139), badge del nav (:91/:109/:119). **Se extiende, no se rompe.** `PwaTest:45` fija `start_url = '/dashboard'` → el Inicio ES la app instalada; el tablero vive bien ahí |
| Producción M11 | `Admin/ProduccionController.php:70-107`, `app/Models/ProduccionReporte.php:218-223` | Resumen de hoy ya resuelto en 2 queries: `selectRaw SUM(primera/segunda/malo/danada)` sobre `delDia($hoy)` (scope `whereDate` :223) + `ProduccionAsignacion::whereDate('fecha',$hoy)->sum('asignadas')` (:74). `armarResumen` (:107) deriva producido/merma/tasa1/avance con guard de división por cero. "Pendiente de aprobar" = estado `ENVIADO` (`scopePendientes` :218). Rutas jefe bajo `permission:manage production` (`routes/web.php:213`), nombres `admin.produccion.index` / `admin.produccion.dia` (:214) |
| Servicio Técnico M12 | `app/Models/OrdenServicio.php:83/341/360`, `Admin/ServicioTecnicoController.php:266` | `ESTADOS` (7), scope `porConfirmar()` = `whereIn('fuente', ['qr','ruta'])->whereNull('confirmada_at')` (:360); ya existe endpoint JSON de conteo (:266). Listado con filtro `?estado=` bajo `view|manage servicio tecnico` (`routes/web.php:148`); confirmar bajo permiso propio `confirmar servicio tecnico` (:166) |
| Aprobaciones M14 | `AprobacionController.php:34`, `app/Models/Aprobacion.php:33/118` | La bandeja cuenta pendientes: `where('estado','pendiente')` + `whereIn('rol_aprobador', $user->getRoleNames())` salvo admin (ve todas). Rutas: `aprobaciones.index` (`permission:aprobar solicitudes`, `routes/web.php:55`), historial `admin.aprobaciones.index` (`view aprobaciones`) |
| Notificaciones M15 | `app/Models/Notificacion.php:37/92`, `Admin/NotificacionController.php:22` | Estados `pendiente/enviada/fallida/leida`. **No-leídas propias ya viven en la campanita** (`campanitaDe`, `navigation.blade.php:5`) — no se duplican como card. El admin filtra por querystring validado: `admin.notificaciones.index?estado=fallida` funciona hoy. Fallida terminal = `programada_para` null tras `notif_reintentos_max` |
| Stock crítico | `database/migrations/2026_06_11_120000_create_inventario_tables.php:39`, `app/Models/Stock.php:44` | **NO existe señal de mínimo/crítico**: ni columna (`stocks` = real/reservado/disponible espejo Bsale), ni scope, ni clave de configuración (grep en migraciones/seeders: 0). **Se OMITE en v0** (ver §4) |
| Matriz permisos×roles | `database/seeders/RolesAndPermissionsSeeder.php:23-87` | 19 permisos; admin todos; jefe_bodega (`manage production` + `confirmar st` + `aprobar solicitudes` + `view users` + `view st`), jefe_ventas (`manage clientes` + `aprobar solicitudes` + `view users` + `view st`), tecnico (taller + `crear lote`), vendedor (`manage clientes` + `view st`), soplador (`report production`), conductor (`crear lote servicio`), member (nada) |

## 1. Diseño v0 — el Inicio SE CONVIERTE en el dashboard ejecutivo

No hay página nueva: `/dashboard` ya es la landing de todos los roles (y el `start_url` de la PWA).
v0 = **reagrupar** los indicadores existentes **por módulo** (encabezado de sección `text-xs uppercase`
del design system) y **sumar 6 cards nuevas** de lectura (las marcadas NUEVA en las tablas: #1-3, #10, #11 y #15). `<x-stat-card>` intacto (sus 4 props bastan).
Cero markup nuevo; cero componentes nuevos.

### Las cards, una a una (sección · card · fuente exacta · permiso · quién la ve)

**Sección «Producción · hoy»** — permiso `manage production` → admin, jefe_bodega

| # | Card | Número (tabla/modelo) | Enlace | `alerta` |
|---|---|---|---|---|
| 1 | **Producido hoy** (NUEVA) | `SUM(primera+segunda)` de `produccion_reportes` con `delDia(hoy)` — misma `selectRaw` del panel (1 query compartida por las cards 1-3) | `admin.produccion.dia?fecha=hoy` | no |
| 2 | **Avance de hoy (%)** (NUEVA) | producido ÷ `SUM(asignadas)` de `produccion_asignaciones` `whereDate('fecha',hoy)` — mismo cálculo `armarResumen` | `admin.produccion.index` | no |
| 3 | **Merma de hoy (%)** (NUEVA) | `SUM(malo+danada)` ÷ total — mismo `armarResumen` | `admin.produccion.index` | no (merma = neutral de-enfatizado, paleta de 4) |
| 4 | Reportes por revisar (EXISTENTE, intacta) | `ProduccionReporte::pendientes()->count()` (estado `enviado`, global) | `admin.produccion.index` | sí |

**Sección «Servicio Técnico»** — semilla de Marcos **intacta** (permiso `manage servicio tecnico` → admin, tecnico) + 1 nueva

| # | Card | Número | Enlace | `alerta` |
|---|---|---|---|---|
| 5-9 | Equipos en taller · Cotización · Reparado · Entregado · Sin solución (EXISTENTES, intactas: mismo query, mismos labels, mismos href) | 1 `COUNT` `groupBy(estado)` | `admin.servicio-tecnico.index?estado=` | como hoy |
| 10 | **Recepciones por confirmar** (NUEVA) | `OrdenServicio::porConfirmar()->count()` (fuente qr/ruta sin `confirmada_at`) | `admin.servicio-tecnico.index` | sí |

Card 10 va con permiso **`confirmar servicio tecnico`** → admin, jefe_bodega, tecnico (el que puede confirmar es el que necesita ver la cola).

**Sección «Aprobaciones»** — permiso `aprobar solicitudes` → admin, jefe_ventas, jefe_bodega

| # | Card | Número | Enlace | `alerta` |
|---|---|---|---|---|
| 11 | **Aprobaciones pendientes** (NUEVA) | `Aprobacion` `where(estado,'pendiente')` + `whereIn(rol_aprobador, roles del usuario)` — admin sin filtro. **Espejo exacto de la bandeja** (`AprobacionController::index:34`): el número de la card = lo que verá al entrar | `aprobaciones.index` | sí |

**Sección «Administración»** — existentes intactas + 1 nueva

| # | Card | Número | Permiso | Enlace | `alerta` |
|---|---|---|---|---|---|
| 12 | Productos sin medidas (EXISTENTE) | como hoy | `manage productos` | como hoy | sí |
| 13 | Clientes (EXISTENTE) | `Cliente::count()` | `manage clientes` | como hoy | no |
| 14 | Usuarios (EXISTENTE) | `User::count()` | `view users` | como hoy | no |
| 15 | **Notificaciones fallidas** (NUEVA) | `Notificacion::where('estado','fallida')->count()` | `view notificaciones` | `admin.notificaciones.index?estado=fallida` (filtro ya validado en el controller) | sí |

### Quién ve qué (derivado de la matriz del seeder — cero permisos nuevos)

| Rol | Cards que ve |
|---|---|
| admin | todas (15) |
| jefe_bodega | Producción (1-4) + Recepciones por confirmar (10) + Aprobaciones pendientes (11) + Usuarios (14) |
| jefe_ventas | Aprobaciones pendientes (11) + Clientes (13) + Usuarios (14) |
| tecnico | ST (5-9) + Recepciones por confirmar (10) |
| vendedor | Clientes (13) |
| soplador | ninguna card — su CTA «Ir a Mi producción» (existente) sigue siendo lo primero |
| conductor / member | ninguna (conductor no tiene permisos de lectura hoy — anotado, no se inventa uno) |

## 2. Reglas de implementación

- **Queries de LECTURA eficientes:** cada sección = 1-2 queries agregadas (`COUNT`/`SUM`/`groupBy`),
  cero N+1; «hoy» SIEMPRE con `whereDate` (bitácora — jamás `whereBetween` sobre fecha casteada).
  Las cards 1-3 comparten UNA `selectRaw SUM` + una `sum('asignadas')` (mismas del panel del jefe).
- Las queries existentes del `DashboardController` **no se tocan**; las nuevas se suman con el mismo
  patrón `if ($user->can(...)) { $indicadores[] = [...] }` (:29). La agrupación por sección es
  presentación (estructura `$secciones` = label + cards), no cambia ningún número.
- Vista: encabezados de sección (`text-xs font-medium uppercase tracking-wide text-neutral-500`) +
  la misma grilla `grid-cols-2 sm:grid-cols-3 lg:grid-cols-5`. Responsive verificado a 375/768/1024.
- Percentajes como valor entero (`number_format` 0 decimales del componente) con «(%)» en el label.
- Tocar Blade ⇒ `npm install` (chunk qrcode) + `npm run build` + grep del bundle (`-F`:
  `lg\:flex`, `lg\:hidden` + 1 clase nueva si la hubiera) + commit de `public/build/`.

## 3. Pasos

| Paso | Entregable | Hecho cuando |
|---|---|---|
| **P-M16-01** · Controller + tests de conteo | Las 5 cards nuevas en `DashboardController::index` + estructura `$secciones`; `DashboardTest` extendido con conteos EXACTOS por card (datos sembrados en el test) | Tests de conteo verdes; queries existentes intactas (los 9 tests actuales siguen verdes sin editar sus aserciones de valores) |
| **P-M16-02** · Vista + responsive + build | `dashboard.blade.php` agrupado por secciones; preview a 375/768/1024 sin scroll horizontal; bundle recompilado + grep | Verificación visual en preview 3 anchos; grep del bundle OK |
| **P-M16-03** · Visibilidad por rol + cierre | Tests «rol sin permiso NO ve la card» (jefe_ventas sin cards de producción; tecnico sin card de aprobaciones; vendedor solo Clientes; member solo saludo) + suite COMPLETA verde + RUTA-MAESTRA mismo push + parte | Suite verde; merge coordinado según protocolo de flota |

## 4. Omitido en v0 (explícito, con evidencia)

- **Stock crítico:** NO hay señal de mínimo en el espejo Bsale (sin columna en `stocks`/`productos`,
  sin scope, sin config — verificado). Agregarla = migración nueva o campo Bsale por investigar →
  **fase posterior**. (Alternativa barata «productos con disponible = 0» se descartó en v0: con 16
  bodegas espejo es ruido sin definición de negocio.)
- **No-leídas del usuario como card:** ya lo resuelve la campanita del nav (`campanitaDe`) — duplicar
  la señal confunde.
- **Export Excel/PDF, gráficos, filtros de periodo en el Inicio:** fuera de v0 por dictado. El panel
  de producción ya tiene su drill-down por periodo para quien lo necesita.
- **Reportes del día POR SOPLADOR como lista:** el Inicio queda de cards; el desglose por soplador
  ya existe en `admin.produccion.index` (a 1 click desde las cards 1-4).

## 5. Preguntas abiertas para el Director (afinables en el visto bueno)

1. **¿Ampliar las 5 cards ST a `view servicio tecnico`?** Hoy están bajo `manage servicio tecnico`
   (solo admin/tecnico las ven). Ampliarlas con `canany` daría visibilidad a vendedor/jefes (que ya
   pueden abrir el listado ST). **Default v0: NO** — semilla intacta, cero cambio de comportamiento
   para roles existentes; se decide aquí si se amplía.
2. **Card «Aprobaciones pendientes» para admin cuenta TODAS** (espejo de su bandeja). Si se prefiere
   que cuente solo las de rol admin, se cambia el query — default: espejo de la bandeja (consistente
   con lo que ve al hacer click).
