# PLAN-M15 · Notificaciones — plan fino de la unidad E1
> **Estado: VIGENTE — verificado contra el código el 2026-07-02 (commit 4da5de2)**

> **Unidad:** E1 · M15 Notificaciones (RUTA-MAESTRA §4/E1) · **Rama:** `feature/m15-notificaciones` · **Stream:** 2
> **Objetivo:** motor centralizado de notificaciones multi-canal con **email operativo**, **campanita in-app** y **WhatsApp enchufable** (stub hasta D-007).
> **Hecho cuando:** tests verdes; en staging un evento llega por correo real y a la campanita; reintento ante fallo verificado.

---

## 0. Verificación de vigencia (qué se revisó del código)

| Área | Archivo verificado | Estado hoy |
|---|---|---|
| Cola | `config/queue.php` | default `database`; tablas `jobs`/`job_batches`/`failed_jobs` ya migradas (`0001_01_01_000002`) |
| Correo | `config/mail.php`, `.env.example` | mailer default `log`; SMTP configurable por env (`MAIL_*`); sin Mailables propios aún |
| Scheduler | `routes/console.php` | 4 syncs Bsale por hora (`:00/:20/:40/:50`), cron por-minuto verificado (bitácora 2026-07-02); **no hay cron de `queue:work`** → delegación |
| Permisos | `database/seeders/RolesAndPermissionsSeeder.php`, `config/permissions.php` | patrón `firstOrCreate` aditivo + etiqueta; NO usar `syncPermissions` |
| Config | `app/Models/Configuracion.php`, `ConfiguracionSeeder` | K/V tipado con caché forever; `get()/set()`; seeds `firstOrCreate` |
| Servicios | `app/Services/Bsale/*` | patrón: constructor config-inyectado, excepciones propias, sin escritura a Bsale |
| Auditoría | `app/Http/Controllers/Admin/AuditController.php` | mapa `MODELOS` (FQCN → etiqueta); modelos con `AuditableTrait` |
| Nav | `resources/views/layouts/navigation.blade.php` | desktop `lg:flex` con dropdowns por dominio + user-dropdown; móvil con `x-responsive-nav-heading` |
| Usuario | `app/Models/User.php` | trait `Notifiable` presente; `sucursal_id`; auditable |
| Suite | `php artisan test` local | **364 verdes** (gate del kickoff §2.2 cumplido en este clon) |

Regla de re-sellado: si pasan >7 días o entran commits que toquen estas áreas, re-verificar antes de seguir (docs/planes/README.md).

---

## 1. Diseño

### 1.1 Arquitectura (capas GUIA-DALIGO)

```
Módulo emisor (M14/M12/M13/…)            «hoy: botón "enviar prueba" en /admin/notificaciones»
        │  Notificar::evento('x.y', $origen, $usuario, $datos)
        ▼
app/Services/Notificaciones/NotificacionDispatcher
        │  1. resuelve plantilla (Configuracion json por evento)
        │  2. resuelve canales efectivos (preferencias_canal + defaults)
        │  3. crea 1 fila `notificaciones` POR canal (estado=pendiente)
        │  4. encola app/Jobs/EnviarNotificacion (cola database)
        ▼
Job EnviarNotificacion ── contrato Canal ──► CanalMail (Mail::raw/Mailable → SMTP)
        │                                    CanalDatabase (marca enviada; campanita lee la tabla)
        │                                    CanalWhatsApp (stub: Log::info + enviada_stub) [B:D-007]
        ▼
éxito → estado=enviada, enviada_at · fallo → estado=fallida, intentos++, ultimo_error,
programada_para = now + backoff(intentos)  ──►  comando programado `notificaciones:reintentar`
(scheduler cada 5 min) re-encola las fallidas vencidas hasta `notif_reintentos_max`
```

- **Canal database = registro campanita**: siempre se crea (es la traza in-app y el fallback universal). Mail respeta preferencias/opt-out. WhatsApp nace deshabilitado por default (stub).
- **Reintentos gestionados por nosotros** (estado + `programada_para` en la tabla propia, job con `tries=1`): visibles en `/admin/notificaciones`, testeables, sin depender de `failed_jobs`.
- Sin daemons (HANDOFF §4): todo por cron. `queue:work --stop-when-empty --max-time=55` cada minuto (delegación P-M15-03).

### 1.2 Esquema (MySQL 5.7: VARCHAR(191) en índices, sin JSON nativo requerido)

**`notificaciones`** — una fila por (evento disparado × canal):
| Columna | Tipo | Nota |
|---|---|---|
| id | bigint PK | |
| evento | varchar(191) índice | clave del catálogo `Notificacion::EVENTOS` |
| notificable_type / notificable_id | nullableMorphs | objeto origen (polimórfica) |
| user_id | FK users nullOnDelete, nullable | destinatario interno |
| destinatario | varchar(191) nullable | email/teléfono externo si no es user |
| canal | varchar(32) | `mail` · `database` · `whatsapp` |
| titulo | varchar(191) | asunto renderizado |
| cuerpo | text | cuerpo renderizado |
| payload | text nullable | JSON serializado (datos del evento) |
| estado | varchar(32) índice | `pendiente` · `enviada` · `fallida` · `leida` (database) |
| intentos | unsignedTinyInt default 0 | |
| ultimo_error | text nullable | |
| programada_para | timestamp nullable | próximo reintento (backoff) |
| enviada_at / leida_at | timestamp nullable | |
| timestamps | | |
Índices compuestos: `[user_id, canal, estado]` (campanita), `[estado, programada_para]` (reintentos).

**`preferencias_canal`** — opt-out por usuario/evento/canal:
| Columna | Tipo |
|---|---|
| id, user_id (FK cascade), evento varchar(191), canal varchar(32), habilitado boolean default true, timestamps |
Unique `[user_id, evento, canal]` (191 OK). Sin fila = default del canal (mail on, whatsapp off, database siempre).

Modelo `Notificacion` (con `AuditableTrait` + alta en `AuditController::MODELOS`) y `PreferenciaCanal`. Catálogo de eventos como constante `Notificacion::EVENTOS` (fuente única para validación, seeds y UI — patrón `MOTIVOS_DEFECTO`). Evento inicial: `sistema.prueba`; los módulos consumidores (M14/M12/M13) agregan los suyos al integrar.

### 1.3 Plantillas y configuración (P-M15-04)
Claves en `Configuracion` (grupo `notificaciones`, seeds idempotentes en `ConfiguracionSeeder`):
- `notif_plantilla_{evento}` (json): `{"asunto": "...", "cuerpo": "... {placeholders} ..."}` — editable desde la UI de Configuración existente, sin tabla extra (biblia §5 solo pide 2 tablas).
- `notif_reintentos_max` (integer, default 3) · `notif_backoff_minutos` (json, default `[5, 15, 60]`).
- `notif_remitente_nombre` (string; hoy "DaliGo" — se ajusta cuando se tome D-001).
Placeholders `{nombre}`, `{url}`, etc. se reemplazan desde `payload` con `strtr` (sin motor de templates nuevo).

---

## 2. Pasos (mapa 1:1 con RUTA-MAESTRA §4/E1)

| Paso | Alcance | Archivos nuevos/tocados | Hecho cuando |
|---|---|---|---|
| **P-M15-01** | Migraciones `notificaciones` + `preferencias_canal` (§1.2) | `database/migrations/…` (2) | `migrate:fresh --seed` verde en SQLite; DDL razonado para MySQL 5.7 |
| **P-M15-02** | Dispatcher + contrato `Canal` + 3 canales + Job + modelos | `app/Services/Notificaciones/*` (contrato + 3 canales + dispatcher), `app/Jobs/EnviarNotificacion.php`, `app/Models/{Notificacion,PreferenciaCanal}.php` | test unitario de dispatch verde; stub WhatsApp loguea |
| **P-M15-03** | Cola database operativa + **delegación cron** `queue:work --stop-when-empty --max-time=55` | prompt con plantilla VERIFICACION-CPANEL → Mauricio; evidencia a `docs/qa/INFRA/` | IA-cPanel confirma cron creado (crontab textual, lección bitácora 2026-07-02) |
| **P-M15-04** | Plantillas por evento + claves `Configuracion` + seeds | `ConfiguracionSeeder` (aditivo) | seeds idempotentes (2ª corrida no duplica); plantilla editable en UI |
| **P-M15-05** | Reintentos backoff (comando `notificaciones:reintentar`, scheduler cada 5 min) + vista `/admin/notificaciones` (filtros estado/canal/evento + botón "enviar prueba") | `app/Console/Commands/`, `routes/console.php`, controller + Blade (componentes `x-list-card`/`x-list-row`/`x-badge`), permiso `view notificaciones` | fallo simulado reintenta con backoff y muere en max; vista 403 sin permiso |
| **P-M15-06** | Campanita in-app: contador no-leídas + dropdown propias + marcar leída — desktop y móvil | `navigation.blade.php` (cambio MÍNIMO), ruta propia, partial | visible 375/768/1024; `npm run build` + grep bundle `lg\:flex`/`lg\:hidden` (bitácora 2026-06-15) |
| **P-M15-07** | Preferencias por usuario (canal × evento, opt-out) en el perfil | vista perfil + controller + `preferencias_canal` | opt-out respetado por el dispatcher (test) |
| **P-M15-08** | Tests integrales | `tests/Feature/Notificaciones/*` | dispatch por preferencia · reintento/backoff · opt-out · 403 · campanita marca leída · stub whatsapp · seeds idempotentes |
| **P-M15-09** | Merge coordinado + deploy + QA staging | protocolo kickoff §5.6 (fetch→merge main→`view:clear`+`npm run build`→grep→suite→**go de Mauricio**) + plantilla QA-FUNCIONAL | QA staging: correo real llega + campanita + fila en admin |
| **P-M15-10** | **Delegación** SPF/DKIM/DMARC del dominio + test de entregabilidad Gmail/Outlook | prompt VERIFICACION-CPANEL | reporte IA-cPanel: registros DNS OK + correo NO cae a spam |

Commits pequeños por paso, **suite completa verde antes de cada commit** (CI no corre en la rama), push frecuente de la rama a origin (respaldo). Los `[x]` de RUTA-MAESTRA se marcan en la rama con hash (regla del mismo push).

---

## 3. Integración con archivos compartidos (anti-colisión §5.4 — cambios MÍNIMOS)

| Archivo | Cambio único |
|---|---|
| `RolesAndPermissionsSeeder.php` | + `'view notificaciones'` al array (aditivo; admin lo recibe con el resto) |
| `config/permissions.php` | + etiqueta `'view notificaciones' => 'Ver notificaciones'` |
| `routes/web.php` | + grupo `admin/notificaciones` **al final** del archivo |
| `navigation.blade.php` | + campanita junto al user-dropdown (desktop) y en el bloque inferior (móvil) |
| `database/seeders/ConfiguracionSeeder.php` | + claves `notif_*` (firstOrCreate) |
| `AuditController::MODELOS` | + `Notificacion::class => 'Notificación'` |

Territorio del stream 1 (producción): **intocado**. Ningún trigger de producción en E1.

---

## 4. Decisiones, riesgos y fuera de alcance

- **[B:D-007]** WhatsApp: solo el stub (interfaz `Canal` deja el enchufe listo). Activarlo = implementar `CanalWhatsApp` real cuando Marco responda; cero refactor.
- **D-001** (nombre del sistema): plantillas leen `notif_remitente_nombre` de Configuración → cambiar el nombre luego = editar una clave, no código.
- **D-011**: QA de P-M15-09/10 se hace en `staging.impdali.cl` (único entorno hoy); la entregabilidad (SPF/DKIM) se configura sobre el dominio real `impdali.cl`, sirve para `daligo.impdali.cl` futuro.
- **SMTP prod**: HANDOFF no registra cuenta SMTP; P-M15-10 incluye pedir/verificar la cuenta de correo saliente vía delegación. Credenciales JAMÁS al repo: `MAIL_*` van al `.env` del servidor (hueco «PEGAR» en el prompt).
- **Fuera de alcance E1** (biblia M15 completo, fases posteriores): confirmaciones de lectura IMAP, elección de canal por *clientes externos* al registrarse (hoy solo usuarios internos), WhatsApp real, triggers de M14/M12/M13.
- **Riesgo merge** (2 streams + main caliente): mitigado con fetch por sesión, cambios mínimos en compartidos y el protocolo de merge del kickoff §5.6 (`public/build/` jamás a mano).

---

## 5. Delegaciones a redactar (las entrego como prompt listo para despachar)

1. **P-M15-03 · Cron de la cola** (plantilla VERIFICACION-CPANEL): crear `* * * * * /opt/cpanel/ea-php83/root/usr/bin/php /home4/impdali/daligo/artisan queue:work --stop-when-empty --max-time=55 >> /dev/null 2>&1` + devolver crontab textual completo (verificar que el `schedule:run` por-minuto sigue intacto).
2. **P-M15-10 · Entregabilidad** (plantilla VERIFICACION-CPANEL): verificar/crear SPF, DKIM, DMARC para `impdali.cl` en cPanel (Email Deliverability), cuenta SMTP para el sistema, y test de envío a Gmail/Outlook externos con veredicto inbox/spam.
