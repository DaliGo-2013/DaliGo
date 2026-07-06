# KICKOFF · Stream 2 — Unidad E1 · M15 Notificaciones

> **Para la IA que recibe esto:** eres el **segundo stream de desarrollo** de DaliGo. Este
> documento es tu contrato de trabajo: contexto, tarea, reglas de coordinación y estándares.
> Léelo completo antes de hacer NADA. Redactado el 2026-07-02 (paso P-S0-16).

---

## 1. Contexto y actores

**DaliGo** es un ERP-lite interno (Laravel 12 + Blade + Alpine + Tailwind v4) para Importadora
DALI (Chile). Complementa a Bsale (facturación al SII) — **jamás lo reemplaza ni le escribe**.
El proyecto se construye con streams de IA coordinados por documentación compartida:

| Actor | Rol | Territorio |
|---|---|---|
| **Mauricio** (dueño) | Prioridades, decisiones, despacho de briefs, copy/paste con la IA externa | todo |
| **Stream 1** (otra sesión de Claude) | Módulo M11 Producción + hotfixes, commitea **directo a `main`** | módulo producción |
| **Stream 2** (TÚ) | **Unidad E1 · M15 Notificaciones**, SIEMPRE en la rama `feature/m15-notificaciones` | módulo notificaciones |
| **IA externa de cPanel/QA** | Único actor con ojos/manos en el servidor, cPanel y staging | infra + QA funcional |

**Sobre la IA de cPanel/QA (importante):** tú NO tienes acceso al servidor ni a staging. Cuando
necesites algo de infra (crons, DNS, logs del server, pruebas en staging.impdali.cl), se lo
delegas: redactas un prompt con las plantillas de `docs/delegacion/plantillas/`, se lo entregas
a Mauricio, él lo copia/pega en esa IA y te trae la respuesta, que se archiva ÍNTEGRA en
`docs/qa/` (protocolo completo: `docs/delegacion/PROTOCOLO-DELEGACION.md`). En E1 la necesitarás
al menos dos veces: P-M15-03 (cron de la cola) y P-M15-10 (SPF/DKIM/DMARC).

---

## 2. Setup (antes de todo)

1. **Clon NUEVO en carpeta propia** (ej. `DaliGo-M15`) — JAMÁS compartas working tree con el
   stream 1: `git clone https://github.com/DaliGo-2013/DaliGo.git DaliGo-M15`
2. Sigue `HANDOFF.md` §10 al pie de la letra (composer, npm, `.env` con SQLite, `migrate:fresh
   --seed`, `npm run build`) y **verifica la suite verde** (`php artisan test`, hoy ~364) ANTES
   de escribir una línea. Si no está verde, detente y repórtalo.
3. Crea tu rama desde main fresco: `git fetch origin && git checkout -b feature/m15-notificaciones origin/main`

## 3. Lectura obligatoria, EN ESTE ORDEN

No escribas código sin haber leído TODO esto (es tu estándar de calidad):

1. `docs/PROTOCOLO-SESION.md` — cómo se arranca y cierra CADA sesión de trabajo.
2. `docs/RUTA-MAESTRA.md` — §0 (estado) y §4/E1 (tu unidad, tus pasos).
3. `PROYECTO_DALIGO.md` — la biblia: busca M15 (qué y por qué) y las reglas transversales.
4. `HANDOFF.md` — completo: stack, infra, deploy, cómo está hecho lo construido.
5. `CLAUDE.md` — **completo, incluida TODA la bitácora de errores**: cada entrada es un error
   que ya nos costó caro; repetir uno documentado es inaceptable.
6. `docs/GUIA-DALIGO.md` — las convenciones de arquitectura del repo (4 capas).
7. `docs/delegacion/` — protocolo + 3 plantillas de la IA externa.
8. `docs/DECISIONES.md` — en especial D-007 (WhatsApp: SIN API aún → canal stub) y D-011
   (entornos: hoy staging = única instancia; Bsale es solo-lectura).

## 4. Tu tarea: E1 · M15 Notificaciones (núcleo multi-canal, email primero)

**Objetivo:** motor centralizado de notificaciones (tablas `notificaciones` + `preferencias_canal`,
plantillas por evento, triggers, reintentos) con canal **email** operativo y canal **WhatsApp
enchufable** (stub que loguea, hasta D-007). **Hecho cuando:** tests verdes; en staging un evento
llega por correo real y a la campanita; reintento ante fallo verificado.

Pasos (la fuente viva es RUTA-MAESTRA §4/E1 — márcalos ahí, EN TU RAMA, al completarlos):

- P-M15-01 · Migraciones `notificaciones` (polimórfica: evento, canal, destinatario, payload,
  estado, reintentos) + `preferencias_canal` — MySQL 5.7: VARCHAR(191) en índices
- P-M15-02 · `NotificacionDispatcher` + contrato `Canal` (`CanalMail`, `CanalDatabase`,
  `CanalWhatsApp` stub que loguea)
- P-M15-03 · Cola database + **delegación IA-cPanel**: segundo cron `queue:work
  --stop-when-empty --max-time=55`
- P-M15-04 · Plantillas por evento + seeds idempotentes + claves en `Configuracion`
- P-M15-05 · Reintentos con backoff + vista `/admin/notificaciones` (permiso `view notificaciones`)
- P-M15-06 · Campanita in-app en nav (desktop + responsive) — `npm run build` + grep del bundle
- P-M15-07 · Preferencias por usuario (canal por tipo de evento, opt-out)
- P-M15-08 · Tests (dispatch por preferencia, reintento, opt-out, 403)
- P-M15-09 · Merge + deploy + QA staging (plantilla QA-FUNCIONAL: correo real + campanita + fila en admin)
- P-M15-10 · **Delegación IA-cPanel**: SPF/DKIM/DMARC + test de entregabilidad a Gmail/Outlook externos

**Tu PRIMER entregable no es código:** escribe el plan fino `docs/planes/PLAN-M15.md` con el
**sello de vigencia** (regla en `docs/planes/README.md`: verificado contra el código, con fecha
y commit) y preséntaselo a Mauricio para su visto bueno ANTES de la primera migración.

## 5. Reglas anti-colisión con el stream 1 (CRÍTICAS)

1. **TODO tu trabajo vive en `feature/m15-notificaciones`. PROHIBIDO pushear a `main`.**
   Pushea tu rama a origin con frecuencia (respaldo): NO dispara deploy ni CI (ambos workflows
   corren solo en `main` — verificado).
2. **CI no corre en tu rama** → suite completa local verde ANTES de cada commit, sin excepciones.
3. **Territorio prohibido** (lo trabaja el stream 1; ni lo toques ni lo "mejores de pasada"):
   `app/Http/Controllers/Admin/ProduccionController.php`, `app/Http/Controllers/Produccion/**`,
   `app/Models/Produccion*`, `app/Models/Maquina.php`, `app/Models/TipoBotellon.php`,
   `resources/views/admin/produccion/**`, `resources/views/produccion/**`,
   `app/Console/Commands/ProduccionLimpiarPruebas.php`, `tests/Feature/Admin/Produccion*`.
4. **Archivos compartidos** (los tocarás con cambios MÍNIMOS; el conflicto se resuelve al merge):
   `resources/views/layouts/navigation.blade.php` (campanita), `routes/web.php` (agrega tu grupo
   al final), `database/seeders/RolesAndPermissionsSeeder.php` (permiso nuevo, idempotente),
   `config/permissions.php`, `database/seeders/DatabaseSeeder.php`.
5. **`git fetch origin` al inicio de CADA sesión** (este repo tiene varios autores empujando a
   main — lección de la bitácora 2026-06-30).
6. **Merge final (P-M15-09) SIEMPRE coordinado con Mauricio:** `git fetch` → merge de
   `origin/main` hacia tu rama → resolver conflictos → **`public/build/` JAMÁS se resuelve a
   mano**: tras el merge, `php artisan view:clear && npm run build` y commitea el bundle
   regenerado → grep del bundle (clases `lg\:flex` y `lg\:hidden` presentes — bitácora
   2026-06-15) → suite verde → recién ahí Mauricio autoriza el merge a `main` (= deploy).
7. Los docs de estado (RUTA-MAESTRA, BITACORA-SESIONES, CLAUDE.md) los actualizas **en tu rama**
   según la regla del mismo push; llegan a main con tu merge.

## 6. Estándares innegociables (resumen — la fuente es `CLAUDE.md`)

- **MySQL 5.7 en producción** (dev/tests = SQLite): VARCHAR(191) en índices únicos de strings,
  sin CTE, sin window functions, sin JSON_TABLE. Razona todo DDL en términos de MySQL 5.7
  (SQLite no valida FKs/índices igual — bitácora 2026-06-30).
- **Fechas casteadas:** filtros de rango con `whereDate()`, NUNCA `whereBetween` (bitácora,
  reincidió 2 veces).
- **Concurrencia:** todo check-then-act que mute un agregado va con `DB::transaction` +
  `lockForUpdate` sobre la fila ancla (bitácora 2026-06-30/07-02).
- **Permisos:** spatie por ruta (`middleware('permission:...')`), permiso nuevo al seeder
  idempotente + etiqueta en `config/permissions.php`, nav con `@can`.
- **UI:** SOLO componentes `<x-*>` existentes (catálogo en CLAUDE.md); paleta ESTRICTA de 4
  colores (brand/neutral/blanco; rojo solo destructivo); responsive obligatorio 375/768/1024
  sin scroll horizontal; motion sutil; UI en español.
- **Assets:** tras tocar Blade/CSS/JS → `npm run build` + commit de `public/build/` (el server
  no tiene Node). Tras ELIMINAR clases → `view:clear` antes del build.
- **Cierre de sesión:** checklist de `docs/PROTOCOLO-SESION.md` §3; errores resueltos → entrada
  en la bitácora de CLAUDE.md ANTES de commitear; español en commits y UI.
- **Secretos:** JAMÁS en el repo, en prompts ni en chats. Los huecos «PEGAR...» los llena Mauricio.

## 7. Qué NO hacer

- No pushear a `main` (ni "solo esta vez").
- No tocar el territorio del stream 1 (§5.3) ni refactorizar código ajeno "de pasada".
- No correr NADA en el servidor: no tienes acceso; toda infra va vía IA de cPanel (§1).
- No escribir en Bsale: el cliente es solo-lectura por diseño; no agregues métodos de escritura.
- No inventar decisiones de negocio: si te falta una respuesta, ficha D-0NN en
  `docs/DECISIONES.md` y sigue con lo no bloqueado.
- No marcar pasos `[x]` sin commit/evidencia (regla anti-autoengaño de RUTA-MAESTRA).

---

**Prompts del día a día:** `docs/delegacion/RECETARIO-PROMPTS.md` — tus secciones son §3 y §4
(R-20…R-34; para traer main a tu rama usa R-33). Skills disponibles: `/arranque`, `/cierre`, `/pre-merge`.

**Arranque sugerido de tu primera sesión:** setup (§2) → lectura (§3) → `docs/planes/PLAN-M15.md`
con sello de vigencia → visto bueno de Mauricio → P-M15-01. Éxito 🚀
