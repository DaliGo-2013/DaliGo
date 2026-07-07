# PLAN-M14 · Aprobaciones digitales — plan fino de la unidad E2
> **Estado: VIGENTE — verificado contra el código el 2026-07-07 (commit bf7ae27)**

> **Unidad:** E2 · M14 Aprobaciones digitales (RUTA-MAESTRA §4/E2) · **Rama:** `feature/m14-aprobaciones` · **Stream:** 1 (Max-1)
> **Objetivo:** motor polimórfico de aprobaciones (`aprobaciones` + `reglas_aprobacion`) que reemplaza WhatsApp y aprobaciones verbales: el consumidor llama `Aprobaciones::solicitar()` y el motor **auto-aprueba si ninguna regla matchea** (clave "Héctor 5→1-2 pasos") o crea una solicitud pendiente que el aprobador resuelve **desde el celular**, con escalamiento automático y notificación vía M15.
> **Hecho cuando:** flujo solicitar→notificar→aprobar/rechazar→escalar con tests; QA aprueba desde celular real; `ProduccionController::ajustar` cableado como primer consumidor.
> **Gate previo al código:** visto bueno de Mauricio sobre este plan ANTES de la primera migración (dictado del Director, 07-07).

## 0. Verificación de vigencia (qué se revisó del código)

| Área | Archivo verificado | Estado hoy (2026-07-07, main `bf7ae27`) |
|---|---|---|
| Primer consumidor | `app/Http/Controllers/Admin/ProduccionController.php:677-701` | `ajustar()` muta DIRECTO (validate + `DB::transaction` + `lockForUpdate`, actualiza reporte + snapshot de asignación). Sin capa de aprobación. Ruta `POST admin/produccion/reporte/{reporte}/ajustar`, permiso `manage production`, form en `admin/produccion/reporte.blade.php` (panel Alpine "editar") |
| Idioma de concurrencia | mismo controller, `aprobar()`/`devolver()` | `DB::transaction` + `lockForUpdate()->first()` + **re-check de estado dentro del lock** — el patrón que la bandeja replica contra el doble-tap (bitácora CLAUDE.md 2026-06-30) |
| Config global | `app/Models/Configuracion.php` + `database/seeders/ConfiguracionSeeder.php` | `get()/set()` cacheados (`rememberForever`); `set()` exige clave PRE-SEMBRADA (`firstOrFail`). `umbral_aprobacion_clp` (INT 1000000) YA sembrado, reservado para reglas monetarias futuras |
| Permisos | `database/seeders/RolesAndPermissionsSeeder.php` + `config/permissions.php` | Array plano + `firstOrCreate` idempotente; admin recibe todos; roles de negocio con `givePermissionTo` ADITIVO (jamás `sync`). Labels en español en `permissions.php` |
| Rutas | `routes/web.php` | Molde de bandeja personal a nivel raíz ya existe: grupo del soplador (`prefix('produccion')` + permiso propio, :185-194) y las rutas `/notificaciones` de M15 (rama) |
| Nav | `resources/views/layouts/navigation.blade.php` | Entradas de primer nivel con `@can(...)` en desktop (:66-70) y gemelo móvil (:128-132). M15 también lo edita (campanita) — colisión coordinada §3 |
| Scheduler | `routes/console.php` | **Grilla `*/15` OBLIGATORIA** (I-01, 2026-07-07): el cron dispara :00/:15/:30/:45; toda tarea debe caer EXACTO ahí. Verificada en vivo hoy por SSH (12:26 CDT, tras cerrar I-03): las 4 syncs corrieron OK en sus slots nuevos — prices 16:31, stock 16:49, catalog 17:00, clients 17:23 UTC |
| Notificaciones (M15) | `origin/feature/m15-notificaciones` → `app/Services/Notificaciones/NotificacionDispatcher.php`, `app/Models/Notificacion.php` | `despachar(string $evento, ?Model $origen, User|string $destinatario, array $datos): Collection`; exige el evento en `Notificacion::EVENTOS` (el comentario invita: "los módulos consumidores M14/M12/M13 agregan aquí sus eventos"). **Rama POR MERGEAR** — secuencia en §3 |
| Auditoría | `app/Http/Controllers/Admin/AuditController.php` | Mapa `MODELOS` al que se agregan los modelos nuevos auditables |

> **Regla de re-sellado** (`docs/planes/README.md`): si pasan >7 días o entran commits que toquen estas áreas, re-verificar la tabla antes de seguir construyendo.

## 1. Diseño

### 1.1 Arquitectura (capas GUIA-DALIGO)

```
Consumidor (ProduccionController::ajustar — v1; M04/M05/M07/M13 después)
   │ validate() + snapshot {anterior, nuevo, objetivo_updated_at}
   ▼
Aprobaciones::solicitar(tipo, $aprobable, $solicitante, motivo, datos, monto)
   │  ¿regla activa del tipo? ──no──┐
   │  ¿monto >= Configuracion::get(regla.umbral_config)? ──no──┤
   │  ¿solicitante NO tiene regla.rol_aprobador? ──lo tiene──┤
   ▼ (matchea)                                               ▼ (no matchea)
crea PENDIENTE (rol_aprobador de la regla)        crea AUTO_APROBADA + handler->aplicar()
   │ post-commit: evento                          INLINE (lock del objetivo, misma tx
   │ 'aprobacion.solicitada' → rol                del mismo request) → flash normal.
   ▼                                              El solicitante NO nota fricción. ✔
Bandeja /aprobaciones (celular, botones h-12)     Scheduler grilla */15 (:00/:15/:30/:45)
   │                                              comando aprobaciones:escalar
   ├─[Aprobar]─► tx{ lock aprobación + re-check   │ pendientes nivel 0 con
   │   estado==pendiente (anti doble-tap) +       │ created_at <= now() − N min y regla
   │   valida hasRole(rol_aprobador)||admin →     │ con rol_escalamiento: tx{lock +
   │   APROBADA → handler->aplicar(){ lock del    │ re-check} → nivel=1, rol_aprobador =
   │   objetivo + re-check updated_at ──cambió──► │ rol_escalamiento, escalada_at=now()
   │   ConflictoAccionException → RECHAZADA       │ post-commit: 'aprobacion.escalada'
   │   automática, jamás payload obsoleto }}      ▼ → usuarios del rol nuevo
   │   post-commit: 'aprobacion.resuelta' → solicitante
   └─[Rechazar + motivo (x-reason-chips)]─► tx{lock+re-check} → RECHAZADA
       post-commit: 'aprobacion.resuelta' → solicitante
```

Decisiones estructurales (razonadas, con alternativas descartadas):

- **La acción diferida vive como PAYLOAD en `aprobaciones.datos`** y se aplica con un **handler por `tipo_accion`** (mapa constante `Aprobaciones::HANDLERS` → clases que implementan `AccionAprobable::aplicar(Aprobacion $a): void`, resueltas por container). Descartado: closures serializadas (se rompen entre deploys) y "el consumidor consulta y re-aplica" (reintroduce pasos y abre carrera aprobada↔aplicada). El flip a `aprobada` y la aplicación corren en LA MISMA transacción: o queda aprobada Y aplicada, o nada.
- **Payload obsoleto = rechazo automático.** El snapshot guarda `objetivo_updated_at`; el handler, ya con el lock del objetivo tomado, compara contra el `updated_at` actual. Si difiere → `ConflictoAccionException` → la aprobación queda `rechazada` con `resultado_motivo` claro ("el objeto fue modificado después de la solicitud; vuelve a solicitar") y se notifica al solicitante. Nunca se aplica un payload viejo sobre datos nuevos.
- **Auto-aprobación deja fila histórica** (`auto_aprobada`, `resuelto_por = solicitante`, `resuelta_at = now()`): el histórico completo que exige la biblia existe aunque no haya habido humano. El consumidor no bifurca lógica — siempre llama `solicitar()` y elige el flash según el estado devuelto.
- **"Escalada" NO es un estado**: es `nivel_escalamiento` (0→1 en v1) + `escalada_at` + reescritura del `rol_aprobador` vigente. La solicitud escalada sigue `pendiente`. "Expirada" no existe en v1 (nadie está sobre el admin); lo pendiente envejece VISIBLE en la bandeja.
- **Aprobador = rol spatie por nombre** (no `user_id`): cualquiera del rol resuelve ("firma de Héctor **o** Luis"), y escalar = reemplazar el rol vigente. Restricción documentada: renombrar un rol rompería el match (los 8 roles del negocio son estables).

Contrato del servicio (`app/Services/Aprobaciones/`):

```php
class Aprobaciones
{
    public const HANDLERS = [ Aprobacion::ACCION_AJUSTE_REPORTE => Acciones\AjusteReporteProduccion::class ];

    public function solicitar(string $tipoAccion, Model $aprobable, User $solicitante,
        string $motivo, array $datos, ?int $monto = null, ?string $descripcion = null): Aprobacion;
    public function aprobar(Aprobacion $aprobacion, User $aprobador): Aprobacion;   // lock+re-check+aplicar
    public function rechazar(Aprobacion $aprobacion, User $aprobador, string $motivo): Aprobacion;
    public function escalarVencidas(): int;                                          // barrido del comando
}
```

### 1.2 Esquema (MySQL 5.7: VARCHAR(191) máx. en indexados; TEXT + cast para JSON; estados string(32) comentados)

**`reglas_aprobacion`** (auditable — es configuración de negocio):

| Columna | Tipo | Nota |
|---|---|---|
| tipo_accion | varchar(64) **unique** | clave del catálogo `Aprobacion::TIPOS_ACCION` (ej. `produccion.ajuste_reporte`) |
| descripcion | varchar(191) | etiqueta humana para UI/reportes |
| activa | boolean default true | inactiva = nunca matchea (todo auto-aprueba) |
| umbral_config | varchar(64) nullable | **CLAVE de `configuraciones`** cuyo valor INT es el umbral; NULL = matchea siempre. Así el umbral es "parametrizable desde admin" sin construir UI de reglas en v1 |
| rol_aprobador | varchar(64) | rol spatie del nivel 0 |
| rol_escalamiento | varchar(64) nullable | siguiente rol si no responde; NULL = no escala |

**`aprobaciones`** (auditable; volumen bajo — a diferencia de `notificaciones`):

| Columna | Tipo | Nota |
|---|---|---|
| tipo_accion | varchar(64) index | |
| regla_id | FK nullable → reglas_aprobacion, nullOnDelete | traza de qué regla disparó; NULL en auto-aprobadas sin regla |
| aprobable_type/_id | `nullableMorphs('aprobable')` | objetivo (v1: ProduccionReporte) |
| solicitante_id | FK nullable → users, nullOnDelete | |
| estado | string(32) default 'pendiente' | `pendiente \| aprobada \| rechazada \| auto_aprobada` |
| monto | unsignedBigInteger nullable | magnitud evaluada (CLP o unidades según tipo); denormalizada para reportes |
| motivo | varchar(255) | del solicitante (aquí viaja `motivo_ajuste`) |
| descripcion | varchar(255) | texto de bandeja («Ajuste reporte #123 de Juan») |
| datos | TEXT nullable, cast array | `{nuevo, anterior, objetivo_updated_at}` — no consultable por SQL a propósito (por eso monto/tipo/estado van como columnas) |
| rol_aprobador | varchar(64) | rol VIGENTE (se reescribe al escalar); la bandeja filtra por él |
| nivel_escalamiento | unsignedTinyInteger default 0 | 0→1 en v1 |
| escalada_at / resuelta_at | timestamp nullable | |
| resuelto_por | FK nullable → users, nullOnDelete | = solicitante en auto-aprobadas |
| resultado_motivo | varchar(255) nullable | rechazo humano o conflicto automático |

Índices compuestos por query real: `(estado, rol_aprobador)` → bandeja; `(estado, created_at)` → barrido de escalamiento.

### 1.3 Reglas, configuración y eventos

- **Seed inicial (`ReglasAprobacionSeeder`, `firstOrCreate` por `tipo_accion`) — UNA regla:** `produccion.ajuste_reporte` → `rol_aprobador='admin'`, `rol_escalamiento=NULL`, `umbral_config='umbral_ajuste_produccion_unidades'`. El `monto` del ajuste = **Σ|Δ|** de asignadas+primera+segunda+malo+danada: el dedazo chico fluye al instante (auto), la reescritura grande espera a Luis. ¿Quién aprueba el ajuste del jefe? → admin. Si el PROPIO admin ajusta → ya es el aprobador → auto-aprobada (cero fricción, con historial).
- **Claves nuevas en `ConfiguracionSeeder`** (grupo `aprobaciones`): `umbral_ajuste_produccion_unidades` INT **50** · `aprobacion_escala_minutos` INT **30** (descripción incluye: "granularidad efectiva 15 min por la grilla del scheduler"). Post-merge M15: `notif_plantilla_aprobacion_{solicitada,escalada,resuelta}` (JSON, placeholders `{tipo} {descripcion} {solicitante} {motivo} {resultado}`).
- **Escalamiento:** comando `aprobaciones:escalar` con `->everyFifteenMinutes()->withoutOverlapping(15)` (emite `*/15` → cae EXACTO en :00/:15/:30/:45 — cumple I-01). Candidatas: `pendiente AND nivel_escalamiento=0 AND created_at <= now()−N` con regla que tenga `rol_escalamiento`; marca por fila con lock+re-check; notifica DESPUÉS del commit. **Límite aceptado y documentado: latencia real N..N+15 min.** Honestidad v1: la única regla sembrada no escala (admin es el tope) — la mecánica queda operativa/testeada y se estrena con M04 (jefe_bodega→admin).
- **Eventos M15** (se registran en `Notificacion::EVENTOS` POST-merge): `aprobacion.solicitada` → usuarios del rol aprobador (NO se emite en auto-aprobadas: cero ruido) · `aprobacion.escalada` → usuarios del rol nuevo · `aprobacion.resuelta` → solicitante (resultado + motivo, incluye rechazo por conflicto). Puente pre-merge: método privado `notificar()` con guard `class_exists(NotificacionDispatcher::class)` → degrada a `Log::info` (sin interfaces ni providers extra).
- **Permisos nuevos:** `aprobar solicitudes` (label «Aprobar solicitudes (bandeja)») → admin, jefe_bodega, jefe_ventas (bandeja vacía hasta que M04/M05 les apunten reglas; el seeder es aditivo). `view aprobaciones` (label «Ver historial de aprobaciones») → admin. **Defensa en profundidad:** el permiso abre la ruta; resolver exige además `hasRole(aprobacion.rol_aprobador)` o ser admin. La cláusula «o admin» es DELIBERADA: el admin puede resolver cualquier pendiente, incluso una solicitud propia bajo una regla futura con otro rol (queda auditado quién solicitó y quién resolvió); la auto-aprobación en cambio solo mira el `rol_aprobador` de la regla.
- **Contrato ante `monto = null`:** si la regla tiene `umbral_config` y el consumidor no entrega `monto`, la solicitud queda **PENDIENTE** (conservador: sin magnitud no se puede probar que está bajo el umbral). Test explícito en P-M14-02; irrelevante para `ajustar()` (siempre calcula Σ|Δ|), vinculante para M04/M05.

## 2. Pasos (mapa 1:1 con RUTA-MAESTRA §4/E2)

> Commits chicos con suite verde; los `[x]` se marcan SOLO en RUTA-MAESTRA (regla de estado único).

| Paso | Alcance | ¿Requiere merge M15? | Hecho cuando |
|---|---|---|---|
| **P-M14-01** Esquema | Migración `aprobaciones`+`reglas_aprobacion` (§1.2), modelos con `AuditableTrait`/casts/consts/scopes, `ReglasAprobacionSeeder` (1 regla), 2 claves en `ConfiguracionSeeder`, `Aprobacion` y `ReglaAprobacion` en `AuditController::MODELOS` | No | `migrate:fresh --seed` verde; seeders idempotentes (correr ×2 sin duplicar); razonado contra MySQL 5.7 (191/índices/FKs) |
| **P-M14-02** Servicio | `Aprobaciones` + `AccionAprobable` + excepciones + handler `AjusteReporteProduccion` + `notificar()` con guard | No (guard) | Tests: auto-aprueba sin regla / bajo umbral / solicitante-aprobador; pendiente sobre umbral y con `monto=null` bajo regla con umbral (contrato conservador §1.3); aprobar aplica; doble `aprobar()` → `AprobacionYaResueltaException`; conflicto `updated_at` → rechazo automático y objetivo intacto |
| **P-M14-03** Bandeja móvil | Rutas `/aprobaciones` (permiso `aprobar solicitudes`) + «mis solicitudes» (auth), controller, vistas con `x-list-card`/`x-collapsible`/`x-badge`, botones h-12 ancho completo, rechazo con `x-reason-chips` + motivo obligatorio; permisos en seeder + labels; nav desktop+móvil con `@can` | No | En viewport móvil (375px, sin scroll horizontal) el aprobador ve sus pendientes y resuelve en ≤2 taps; doble-tap: el segundo recibe «ya fue resuelta» sin doble aplicación (test de lock) |
| **P-M14-04** Escalamiento | Comando `aprobaciones:escalar` + registro `everyFifteenMinutes()` en `console.php`; badge «Escalada» en bandeja | Re-notificación sí; mecánica no | Tests: vieja escala UNA vez y cambia rol; joven no escala; test de schedule verifica expresión `*/15 * * * *` (grilla I-01) |
| **P-M14-05** Cablear `ajustar()` | Reemplazar la mutación directa por `solicitar()` (validación actual intacta; la transacción migra al handler) | No | Ajuste < umbral: aplica en el mismo request (UX de hoy); ajuste ≥ umbral: reporte INTACTO + pendiente para admin; aprobar aplica reporte+snapshot asignación; conflicto → rechazo y reporte intacto |
| **P-M14-06** Historial | `/admin/aprobaciones` (permiso `view aprobaciones`): filtros estado/tipo/solicitante/aprobador/fechas + resumen por aprobador/solicitante; «mis solicitudes» del lado del solicitante | No | Filtros correctos con datos semilla; transiciones visibles en `/admin/auditoria` |
| **P-M14-07** Tests+merge+QA | Registrar 3 eventos en `Notificacion::EVENTOS` + plantillas; suite completa; gate `/pre-merge` (R-31); QA staging desde celular real | **Sí** | Suite verde; en staging: ajuste grande → campanita+correo a Luis → aprueba desde el celular → ajuste aplicado; parte con evidencia |

**Orden:** 01→02→03→05→06 en `feature/m14-aprobaciones` (pre-merge M15) · 04-mecánica pre-merge · **merge M15 a main** → rebase de la rama → eventos/plantillas/re-notificación → 07.

## 3. Integración con archivos compartidos (anti-colisión — cambios MÍNIMOS, coordinados con el Director)

M15 (por mergear) y M14 tocan **los mismos 6 archivos**. Secuencia dictable: **M15 merge primero, M14 rebasa encima**. Cambio único de M14 por archivo:

| Archivo | Cambio único de M14 |
|---|---|
| `database/seeders/RolesAndPermissionsSeeder.php` | +2 strings al array (`aprobar solicitudes`, `view aprobaciones`) + 1 línea en `givePermissionTo` de jefe_bodega y jefe_ventas |
| `config/permissions.php` | +2 labels |
| `database/seeders/ConfiguracionSeeder.php` | +2 claves (post-merge M15: +3 plantillas) |
| `routes/web.php` | +1 grupo `/aprobaciones` (molde del grupo soplador) + 1 ruta admin historial |
| `resources/views/layouts/navigation.blade.php` | +1 `<x-nav-link>` con `@can` (desktop) + gemelo móvil |
| `routes/console.php` | +1 registro `aprobaciones:escalar` (grilla `*/15`) |

⚠️ **Estado del MERGE M15 (verificado contra `origin/feature/m15-notificaciones` = `00297d5`, 07-07 13:15):**
1. ~~Riesgo de la grilla vieja en su `console.php`~~ **RESUELTO**: Max-2 ya mergeó main (`dff13c7`) en la rama — su `console.php` trae la grilla `*/15` idéntica a main; el diff solo AGREGA el bloque `notificaciones:reintentar`.
2. **VIGENTE (para el Director / Max-2):** ese `notificaciones:reintentar` usa `everyFiveMinutes()`: SÍ dispara en la grilla (5 divide a 15) pero **degrada en silencio a cadencia 15 min** y viola la convención I-01 — corregir a `everyFifteenMinutes()` antes o al mergear. M14 no copia el patrón.

## 4. Decisiones, riesgos y fuera de alcance

- `[B:D-007]` toca solo el canal WhatsApp de las notificaciones (vía M15); el motor no se bloquea — email + campanita.
- **"Push" del enunciado de la biblia** ("push/correo/WhatsApp") = **campanita in-app en v1** (canal `database` de M15, siempre activo). Web-push real (notificación de sistema con la app cerrada) queda para M08/PWA — requiere service worker con permiso de notificaciones, fuera del alcance de E2.
- **Umbral de la regla v1 en UNIDADES, no CLP:** RUTA-MAESTRA menciona `umbral_aprobacion_clp` (ya sembrado) como el umbral del motor; el mecanismo se cumple (umbral SIEMPRE desde `configuraciones` vía `reglas.umbral_config`) pero el ajuste de producción se mide en unidades → clave nueva `umbral_ajuste_produccion_unidades`. `umbral_aprobacion_clp` queda reservado para las reglas monetarias (M04/M05/M07/M13). **Confirmar en el visto bueno**; la frase de RUTA se reconcilia al marcar P-M14-01.
- **Cambio de UX para el jefe_bodega:** hoy `ajustar()` es instantáneo; con M14 los ajustes ≥50 unidades esperan a Luis. Mitigado por el umbral (dedazos fluyen) y la notificación inmediata. **Validar el valor 50 con Luis/Mauricio en el visto bueno de este plan.**
- **Rechazo automático por conflicto** puede sorprender al solicitante → el `resultado_motivo` y la notificación lo explican en una frase.
- **Granularidad 15 min del escalamiento** (I-01): latencia real N..N+15 — límite aceptado, no bug.
- **Fuera de alcance de E2:** UI CRUD de reglas (el admin edita umbrales en `/admin/configuracion`; las reglas se siembran), multi-nivel de escalamiento (>1), expiración de pendientes, aprobación offline en la bandeja (si M08 la exige, se hereda el patrón cola+UUID del memo SPIKE-PWA §2.2), reglas para M04/M05/M07/M13 (cada consumidor futuro siembra la suya al integrarse).

## 5. Delegaciones a redactar

Ninguna para la IA de cPanel: el cron del scheduler y el de cola YA existen en la grilla `*/15` (I-01, evidencia `docs/qa/INFRA/2026-07-07--INFRA--i01-cierre-modo-compatibilidad.md`). El escalamiento viaja por esa infraestructura sin tocar cPanel.
