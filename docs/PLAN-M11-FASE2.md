# Plan — Producción Fase 2 (M11): preformas → botellones + aprobación del jefe

> **Documento de implementación para otra IA.** Autocontenido: contexto, decisiones ya tomadas
> con el usuario, esquema exacto, contratos, archivos, tests y verificación. Antes de programar,
> leer `CLAUDE.md` (reglas vivas + bitácora), `HANDOFF.md` y `PROYECTO_DALIGO.md` (módulo M11,
> correcciones #7/#8/#9 de Luis). Repo: `C:\Users\mauri\OneDrive\Documents\Claude\DaliGo`
> (= `https://github.com/DaliGo-2013/DaliGo`, push a main = deploy automático a staging).

## ⚠️ RECONCILIACIÓN OBLIGATORIA ANTES DE IMPLEMENTAR (decisión del usuario, 2026-06-12)

En el working tree de esta PC existe **trabajo en curso SIN commitear** de una sesión anterior
que implementó esta fase con un **diseño alternativo** (tablas dedicadas `tipos_botellon`,
`maquinas`, `produccion_registros` + backfill + CRUDs + vistas). El usuario decidió de forma
explícita: **ESTE PLAN MANDA** — los botellones y preformas se modelan como **productos del
catálogo** (tabla `productos`, integración con precios/stock/Bsale), NO como tablas dedicadas.

**Pasos de reconciliación (hacer ANTES de implementar el plan):**
1. **Respaldar** el trabajo en curso en una rama (nada se pierde definitivamente):
   `git checkout -b backup/produccion-tipos-botellon && git add -A && git commit -m "wip: respaldo diseño alternativo (tipos_botellon/maquinas/registros) descartado por decisión de diseño" && git checkout main`
2. **Limpiar main** del trabajo descartado: `git checkout -- .` + borrar los untracked del diseño
   alternativo (`git clean -fd -- app/Http/Controllers/Admin/MaquinaController.php app/Http/Controllers/Admin/TipoBotellonController.php app/Models/Maquina.php app/Models/ProduccionRegistro.php app/Models/TipoBotellon.php database/migrations/2026_06_12_*.php database/seeders/TipoBotellonSeeder.php resources/views/admin/maquinas resources/views/admin/tipos-botellon tests/Feature/Admin/MaquinaManagementTest.php tests/Feature/Admin/TipoBotellonManagementTest.php` — revisar `git status` antes y después).
3. **Rescatables de la rama de respaldo** (adaptar, no copiar a ciegas):
   - `resources/views/components/chip-radio.blade.php` (radio táctil ≥48px para operarios — útil
     para el selector de botellón de este plan; ya documentado en el catálogo de CLAUDE.md).
   - Las mejoras de UX de `mi-reporte.blade.php` (stepper/tandas) como referencia visual.
   - El concepto de **máquinas por sucursal** queda como **extensión futura opcional** (atribuir
     producción a una máquina) — NO construirlo en esta fase.
4. Ojo: la entrada de `chip-radio` en el catálogo de `CLAUDE.md` (working tree) puede conservarse
   solo si el componente se rescata; si no, quitarla.

## Contexto

**Qué se pide:** que el soplador registre en SU vista (celular) la transformación de **preformas**
(insumo) en **botellones** (producto terminado), usando productos **de testeo** creados por nosotros,
y que su jefe (jefe de bodega) **apruebe** el trabajo. Es el módulo **M11 de la biblia**, reglas:
**#8** el soplador usa **celular** (mobile-first 375px), **#9** **solo lo aprobado** genera efectos
de stock, **#7** el modo "constructivo" queda fuera.

**Qué existe (Fase 1, NO romper — 16 tests verdes en `tests/Feature/Admin/ProduccionTest.php`):**
- `produccion_asignaciones` (soplador_id, fecha, turno `dia|noche`, `asignadas` = cantidad de
  preformas, creado_por; UNIQUE soplador/fecha/turno) — modelo `ProduccionAsignacion` (hasOne reporte).
- `produccion_reportes` (asignacion_id UNIQUE→1:1, primera/segunda/malo, motivo, obs, estado
  `borrador|enviado|aprobado|devuelto`, enviado_at, revisado_por/at, motivo_ajuste, devuelto_motivo)
  — modelo `ProduccionReporte`: derivados `total/diferencia/tasa_primera`, helpers
  `editablePorSoplador()` / `esPendienteDeRevision()`, scopes `pendientes()/delDia()`.
- `Admin\ProduccionController` (panel, asignar, reporteShow, aprobar, devolver, ajustar) bajo
  `permission:manage production`; `Produccion\MiProduccionController` (index del día, update con
  guards de propiedad/estado y motivo obligatorio si hay diferencia) bajo `permission:report production`.
- Vistas `resources/views/admin/produccion/{index,asignar,reporte}.blade.php`,
  `resources/views/produccion/mi-reporte.blade.php` (Alpine live-calc),
  `resources/views/components/produccion/estado-badge.blade.php`.
- Roles ya sembrados: `soplador` → `report production`; `jefe_bodega` → `manage production` + `view users`.
- **Limitación actual:** las cantidades son genéricas — no hay producto (ni preforma ni botellón).

**Restricción DURA:** las tablas `stocks` y `bodegas` son **espejo de Bsale** con sync horaria que
pisa/borra (`app/Services/Bsale/StockSync.php`). **La producción NO escribe ahí.** Por eso el
resultado de aprobar vive en un **kardex local** (tabla nueva), listo para empujar a Bsale por API
en una fase futura (`POST /v1/stocks/receptions|consumptions.json`, ver `docs/BSALE_API.md`).

## Decisiones ya tomadas con el usuario (no re-discutir)
1. **Evolucionar la Fase 1** (no módulo paralelo).
2. **Kardex local de producción** al aprobar (sin tocar `stocks` espejo ni Bsale).
3. **Varios botellones por reporte** (tabla de líneas): el soplador reporta N líneas
   {botellón × 1ª/2ª/malo} por turno. La asignación fija **la preforma** del turno y su cantidad.
4. Productos de testeo con prefijo `TEST-` vía seeder idempotente (staging es el entorno de prueba).

---

## 1. Seeder de productos de testeo

**Nuevo `database/seeders/ProduccionTesteoSeeder.php`** (idempotente, `firstOrCreate` por `sku`):

| SKU | nombre | categoria |
|---|---|---|
| `TEST-PREFORMA-600G` | Preforma 600 g (testeo) | `Preformas (testeo)` |
| `TEST-PREFORMA-750G` | Preforma 750 g (testeo) | `Preformas (testeo)` |
| `TEST-BOTELLON-10L` | Botellón 10 L (testeo) | `Botellones (testeo)` |
| `TEST-BOTELLON-20L` | Botellón 20 L (testeo) | `Botellones (testeo)` |
| `TEST-BOTELLON-20L-MANILLA` | Botellón 20 L con manilla (testeo) | `Botellones (testeo)` |

Todos `activo=true`, `bsale_variant_id=null` (productos locales: el `CatalogSync` solo "adopta"
filas locales cuyo SKU coincide EXACTO con una variante de Bsale — el prefijo `TEST-` no colisiona).
Cablear al final de `database/seeders/DatabaseSeeder.php` (el deploy corre `db:seed --force`, así
staging recibe los datos de prueba solo con desplegar). En producción real, la asignación podrá
elegir productos reales espejados de Bsale — el seeder solo garantiza material de prueba.

## 2. Migración (una sola, aditiva, MySQL 5.7-safe — strings 191, sin ENUM nativo, sin CTE)

`database/migrations/<ts>_produccion_fase2_lineas_y_movimientos.php`:

```php
// a) La asignación fija QUÉ preforma se trabaja (nullable => históricos no se rompen).
Schema::table('produccion_asignaciones', function (Blueprint $t) {
    $t->foreignId('preforma_id')->nullable()->after('asignadas')
        ->constrained('productos')->nullOnDelete();
});

// b) Líneas del reporte: un botellón por fila con su desglose.
Schema::create('produccion_reporte_lineas', function (Blueprint $t) {
    $t->id();
    $t->foreignId('reporte_id')->constrained('produccion_reportes')->cascadeOnDelete();
    $t->foreignId('producto_id')->constrained('productos')->restrictOnDelete(); // no perder historia
    $t->unsignedInteger('primera')->default(0);
    $t->unsignedInteger('segunda')->default(0);
    $t->unsignedInteger('malo')->default(0);
    $t->timestamps();
    $t->unique(['reporte_id', 'producto_id']); // un botellón aparece una vez por reporte
});

// c) Kardex local de producción: SOLO se escribe al aprobar (regla #9).
Schema::create('produccion_movimientos', function (Blueprint $t) {
    $t->id();
    $t->foreignId('reporte_id')->constrained('produccion_reportes')->cascadeOnDelete();
    $t->foreignId('producto_id')->constrained('productos')->restrictOnDelete();
    $t->string('tipo'); // constantes de clase, NO enum MySQL: consumo_preforma|produccion_primera|produccion_segunda|merma
    $t->unsignedInteger('cantidad');
    $t->date('fecha');  // fecha del reporte (no del approve), para reportería por día
    $t->timestamps();
    $t->index(['producto_id', 'fecha']);
    $t->index('reporte_id');
});
```
`down()`: drop de las 2 tablas + `dropConstrainedForeignId('preforma_id')`.

## 3. Modelos

- **Nuevo `app/Models/ProduccionReporteLinea.php`**: `$table`, fillable
  (`reporte_id, producto_id, primera, segunda, malo`), casts int, `reporte()` belongsTo,
  `producto()` belongsTo (el botellón), derivado `getTotalAttribute()`.
- **Nuevo `app/Models/ProduccionMovimiento.php`**: constantes
  `TIPO_CONSUMO_PREFORMA='consumo_preforma'`, `TIPO_PRODUCCION_PRIMERA='produccion_primera'`,
  `TIPO_PRODUCCION_SEGUNDA='produccion_segunda'`, `TIPO_MERMA='merma'` + array `TIPOS`;
  fillable; casts (`fecha=date`); `reporte()`, `producto()`.
- **`ProduccionAsignacion`**: + `preforma_id` en fillable; + `preforma()` belongsTo Producto.
- **`ProduccionReporte`**: + `lineas()` hasMany, `movimientos()` hasMany;
  + método `recalcularTotalesDesdeLineas(): void` (suma de líneas → columnas
  `primera/segunda/malo` y save — las columnas pasan a ser **agregados**; así el panel del jefe,
  los derivados y los reportes legacy siguen funcionando sin reescritura);
  + **`implements AuditableContract` + `use AuditableTrait`** (owen-it ya instalado; los cambios
  de estado quedan en `/admin/audits`). Registrar
  `ProduccionReporte::class => 'Reporte de producción'` en `AuditController::MODELOS`
  (`app/Http/Controllers/Admin/AuditController.php` — lección de auditorías pasadas: TODO modelo
  auditable se registra ahí el mismo día).
- **Factories nuevas** (hoy no existen): `ProduccionAsignacionFactory`,
  `ProduccionReporteFactory`, `ProduccionReporteLineaFactory` — clonar estilo de
  `database/factories/ClienteFactory.php`.

## 4. Contrato nuevo del soplador (`Produccion\MiProduccionController::update`)

El PATCH pasa de `primera/segunda/malo` planos a **líneas**:

```
lineas                 => required|array|min:1      (al ENVIAR; en borrador puede ser min:0)
lineas.*.producto_id   => required|integer|distinct|exists:productos,id
lineas.*.primera       => required|integer|min:0|max:100000
lineas.*.segunda       => required|integer|min:0|max:100000
lineas.*.malo          => required|integer|min:0|max:100000
enviar                 => boolean (flag existente)
motivo                 => requerido si enviar=1 y diferencia != 0 (regla existente, se conserva)
obs                    => nullable|string|max:1000 (existente)
```

Flujo en `update()`: guards existentes (propiedad + `editablePorSoplador()`) → `DB::transaction`:
borrar líneas del reporte y recrear desde el request (upsert simple; el unique compuesto protege) →
`recalcularTotalesDesdeLineas()` → lógica existente de enviar/motivo. La **diferencia** sigue siendo
`asignadas − total` (preformas asignadas vs botellones totales reportados).

**`ajustar` del jefe** (`Admin\ProduccionController::ajustar`): mismo contrato `lineas[]` +
`motivo_ajuste` requerido (reemplaza el ajuste de totales planos; una sola fuente de verdad).
Recalcula totales igual que el soplador.

## 5. Aprobación → kardex (corazón de la fase; regla #9)

En `Admin\ProduccionController::aprobar`, dentro de `DB::transaction`:

```php
abort_unless($reporte->esPendienteDeRevision(), 403);
if ($reporte->movimientos()->exists()) { return back()->with('status', 'Ya registrado.'); } // idempotencia

$lineas = $reporte->lineas;            // colección
$totalBotellones = $lineas->sum(fn ($l) => $l->primera + $l->segunda + $l->malo);

// 1) Consumo de preformas (1 preforma = 1 intento de botellón). Si la asignación
//    no tiene preforma (reporte legacy), se omite el consumo y solo se registra producción.
if ($reporte->asignacion->preforma_id && $totalBotellones > 0) {
    ProduccionMovimiento::create([reporte, producto => preforma_id, TIPO_CONSUMO_PREFORMA, $totalBotellones, fecha => $reporte->fecha]);
}
// 2) Por línea: producción 1ª, producción 2ª y merma (solo cantidades > 0).
foreach ($lineas as $l) {
    primera>0 => TIPO_PRODUCCION_PRIMERA; segunda>0 => TIPO_PRODUCCION_SEGUNDA; malo>0 => TIPO_MERMA;
}
// 3) estado=APROBADO, revisado_por/revisado_at (lógica existente).
```

`devolver` NO genera movimientos (el soplador corrige y reenvía). `aprobado` es terminal
(no existe des-aprobar; el guard de idempotencia cubre el doble-submit).

## 6. UI (reusar librería de componentes — catálogo en `CLAUDE.md`; nada de hex hardcodeado)

1. **`admin/produccion/asignar.blade.php`** (jefe): agregar `x-select` **"Preforma"** sobre
   los productos activos con `categoria LIKE '%preforma%'` (fallback: todos los activos, con
   `x-input-hint` explicando). El controlador pasa `$preformas`; validar `preforma_id`
   `required|exists:productos,id` en `asignarStore` (requerida para asignaciones nuevas).
2. **`produccion/mi-reporte.blade.php`** (soplador, **CELULAR — regla #8**, mobile-first 375px,
   targets táctiles ≥44px, sin scroll horizontal):
   - Cabecera: preforma asignada (nombre + SKU) y meta del turno (`asignadas` preformas).
   - **Líneas dinámicas con Alpine** (sin librerías nuevas): estado inicial desde
     `$reporte->lineas` (JSON), botón "+ Agregar botellón" (select de productos activos
     `categoria LIKE '%botell%'`, fallback todos), por línea inputs numéricos 1ª/2ª/malo y botón
     quitar; nombres de input `lineas[i][producto_id]` etc.
   - Totales y **diferencia vs asignadas en vivo** (verde si 0, ámbar si ≠0 — patrón ya existente
     en la vista); campo `motivo` visible solo si diferencia ≠ 0 (`x-show`).
   - Botones existentes: "Guardar borrador" / "Confirmar y enviar" (confirm). Estados
     enviado/aprobado quedan read-only mostrando las líneas en tabla.
3. **`admin/produccion/reporte.blade.php`** (jefe): tabla de líneas (botellón, 1ª, 2ª, malo,
   total); bloque **"Al aprobar se registrará"** (preview: consumo de N preformas + altas/mermas
   por botellón); si `aprobado`, listar los movimientos generados. La acción "Ajustar" edita
   líneas (mismo parcial/estructura que el form del soplador, server-rendered con Alpine).
4. **Nueva `admin/produccion/movimientos.blade.php`** — **kardex**: `x-list-card` paginado (25)
   con filtros (producto por nombre/SKU, tipo, rango de fechas) al estilo de
   `admin/productos/index.blade.php`; chips resumen del filtro (total 1ª, 2ª, mermas, consumos).
   Ruta: `GET admin/produccion/movimientos` → `ProduccionController::movimientos` (dentro del
   grupo `permission:manage production` existente en `routes/web.php`). Enlace desde el panel
   (`admin/produccion/index.blade.php`, botón secundario "Kardex").
5. `components/produccion/estado-badge.blade.php` se reusa tal cual.

## 7. Tests (PHPUnit — actualizar Fase 1 + nuevos; seed `RolesAndPermissionsSeeder` en setUp)

**Actualizar `tests/Feature/Admin/ProduccionTest.php`** al contrato `lineas[]`:
`test_soplador_guarda_borrador`, `test_soplador_envia_reporte_cuadrado`,
`test_enviar_con_diferencia_exige_motivo`, `test_jefe_ajusta_cantidades_con_motivo`
(y `asignarStore` ahora exige `preforma_id`: actualizar `test_jefe_asigna_y_crea_reporte_en_borrador`
y `test_reasignar_actualiza_cantidad_sin_duplicar`).

**Nuevo `tests/Feature/Admin/ProduccionFase2Test.php`:**
- líneas se guardan y los totales del reporte se recalculan (primera/segunda/malo = suma);
- producto duplicado en `lineas[]` → error de validación (`distinct`);
- enviar sin líneas → error; borrador sin líneas → permitido;
- **aprobar genera movimientos exactos** (consumo = suma total con la preforma de la asignación;
  1ª/2ª/merma por botellón; solo cantidades > 0);
- **aprobar dos veces no duplica** movimientos (idempotencia);
- devolver NO genera movimientos; reporte devuelto se reedita y al reenviar+aprobar genera una vez;
- reporte legacy (asignación sin preforma) aprueba sin movimiento de consumo;
- kardex: 403 sin permiso, filtros por producto/tipo/fecha funcionan;
- seeder de testeo idempotente (correr 2 veces → 5 productos TEST-, sin duplicar);
- vista soplador muestra preforma asignada y permite agregar botellón de testeo.

## 8. Orden de implementación sugerido (commits — cada uno con suite verde)
1. `feat(m11): fase 2a — seeder de testeo + migración + modelos + factories` (con tests de modelo/seeder).
2. `feat(m11): fase 2b — reporte por líneas del soplador (vista celular) + contrato lineas[]`.
3. `feat(m11): fase 2c — preforma en asignación, ajuste por líneas, aprobación → kardex + vista movimientos`.

(Si se prefiere un solo commit, mantener ese orden interno de construcción.)

## 9. Gotchas OBLIGATORIOS para la IA implementadora (de la bitácora y la arquitectura)
- **NO escribir jamás en `stocks`/`bodegas`** (espejo Bsale; la sync horaria pisa y borra).
- **`whereDate()` para buscar por fecha casteada** — `updateOrCreate` con `date` no matchea
  (bitácora 2026-06-04; `asignarStore` ya lo hace así: conservarlo).
- Tests de auditoría: `phpunit.xml` ya define `AUDITING_CONSOLE=true` (owen-it no audita en CLI
  sin eso — bitácora 2026-06-05).
- Tras tocar Blade/CSS → **`npm run build` y commitear `public/build/`** (el server no tiene Node;
  Tailwind v4 purga las clases no usadas — clases nuevas requieren rebuild sí o sí).
- Seeders **idempotentes** (`firstOrCreate`): el deploy corre `db:seed --force` completo en cada push.
- MySQL 5.7: strings índice ≤191, sin ENUM nativo para `tipo`, sin CTE/window; tests en SQLite
  (cuidar divergencias de collation).
- UI: español, tema claro sobrio, componentes del catálogo (`x-list-card`, `x-list-row`,
  `x-select`, `x-form-footer`, `x-input-*`, `x-badge`), responsive verificado a **375/768/1024**.
- Si surge un error nuevo durante la implementación → **entrada en la bitácora de `CLAUDE.md`
  ANTES de commitear** (regla de oro del repo).

## 10. Verificación end-to-end
1. Local: `php artisan migrate:fresh --seed` → existen los 5 productos `TEST-` y los roles.
2. `php artisan test` → toda la suite verde (hoy 235 + nuevos; ninguno de Fase 1 roto sin actualizar).
3. `npm run build` → commitear `public/build/` si cambió.
4. Flujo manual (con `php artisan serve`): crear usuario soplador y jefe (`app:assign-role`);
   como jefe asignar TEST-PREFORMA-600G ×600 a un soplador; como soplador (celular/responsive 375px)
   agregar líneas TEST-BOTELLON-20L (580/10/10) → enviar; como jefe revisar (preview de movimientos)
   → aprobar; verificar kardex: consumo 600 preformas + 580 1ª + 10 2ª + 10 merma; re-aprobar
   imposible; devolver otro reporte y verificar que NO genera movimientos.
5. Deploy: push → Actions verde → verificar en `staging.impdali.cl` (los productos TEST- se
   siembran solos) repitiendo el flujo manual con usuarios reales de prueba.

## Fuera de alcance (anotado, no construir ahora)
- Push del kardex a Bsale por API (`receptions/consumptions`) — fase futura, requiere validar con Víctor.
- PWA offline del soplador (mayor riesgo del proyecto; esta fase es web responsive).
- Metas/indicadores avanzados por soplador (tablero); Guía de Producción (GP); recalibrado auditado
  al cambiar preforma a mitad de turno.
