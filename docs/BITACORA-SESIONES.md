# Bitácora de sesiones — crónica de la creación de DaliGo

> **Append-only:** nunca se edita una entrada pasada; la más reciente va ARRIBA. Junto con la bitácora
> de errores de `CLAUDE.md` y las evidencias de `docs/qa/`, esto constituye la documentación oficial
> del proceso de creación de la app.

## Formato de entrada

```markdown
### [AAAA-MM-DD] Título de la sesión
- **Quién:** Mauricio + Claude (u otra IA/persona)
- **Objetivo declarado:** paso(s) P-xxx
- **Qué se hizo:** resumen con hashes de commit
- **Pasos marcados:** P-xxx [x], …
- **Decisiones:** D-xxx tomada/creada (o "ninguna")
- **Delegaciones:** enviadas/recibidas → archivo en docs/qa/ (o "ninguna")
- **Próximo paso:** UN paso concreto
```

---

## Sesiones

### [2026-07-04] Stream 2 · día 4: 4 correcciones de auditoría + preferencias + tests (P-M15-07/08)
- **Quién:** Mauricio + Claude (Max-2 · Forjador B, **Opus 4.8**)
- **Objetivo declarado:** 4 correcciones del Director (gate del merge) + P-M15-07 + P-M15-08
- **Qué se hizo:** **Correcciones:** (1) página personal `/notificaciones` mobile-first (lista in-app propia + marcar leída; el link móvil y el "Ver todas" del dropdown apuntan ahí) + panel admin agregado al dropdown Administración; (2) `leer` exige `canal=database` (404 si no — antes sacaba una mail/whatsapp fallida del reintentador); (3) `withoutOverlapping(10)` en el reintentador (TTL, convención); (4) **barrido self-healing** de pendientes huérfanas (crash post-claim): el mismo comando reclama `pendiente` con `updated_at <= now()-10min`, las toca y re-despacha (idempotente por el guard del job). **Menores:** claim limpia `programada_para`; campanita computa sus 2 queries una vez en el nav (antes 3); test de humo endurecido (assertea el badge del conteo). **P-M15-07:** preferencias en el perfil (`prefs[evento][canal]` con `updateOrCreate`); el dispatcher las respeta (test form→dispatch). **P-M15-08:** cobertura del checklist. **Suite 405 verdes.** Bug atrapado por test: `data_get($m, "sistema.prueba.mail")` interpreta el punto del evento como anidación → habría ignorado el opt-in; corregido a acceso directo `$m[$evento][$canal]`.
- **Pasos marcados:** P-M15-07 [x], P-M15-08 [x]; 4 correcciones + menores aplicadas.
- **Decisiones:** ninguna.
- **Delegaciones:** ninguna nueva (P-M15-10 SPF/DKIM pendiente).
- **Próximo paso:** **P-M15-09** — merge coordinado con Mauricio (fetch → merge origin/main → resolver docs → `view:clear`+`npm run build`+grep → suite verde → autoriza merge a main) + QA staging (plantilla QA-FUNCIONAL, IA-cPanel).

### [2026-07-04] Stream 2 · día 3: reintentador atómico + panel + campanita (P-M15-05/06)
- **Quién:** Mauricio + Claude (Max-2 · Forjador B, **Opus 4.8**) + 3 exploradores paralelos
- **Objetivo declarado:** P-M15-05 + P-M15-06, dictado del Director
- **Qué se hizo:** (1) **P-M15-05**: comando `notificaciones:reintentar` ATÓMICO (`DB::transaction` + `lockForUpdate` sobre fallidas vencidas con `intentos < max` → claim por UPDATE a pendiente → dispatch solo lo reclamado; robusto a scheduler degradado: reclama por `programada_para <= now()`, no por cadencia — vale si I-01 reincide) agendado `everyFiveMinutes()->withoutOverlapping()`; panel `/admin/notificaciones` read-only (patrón AuditController: filtros validados `Rule::in`, paginado, `x-list-card`/`x-badge`) + botón "enviar prueba"; permiso `view notificaciones` aditivo + label; `PreferenciaCanal` en `AuditController::MODELOS`. (2) **P-M15-06**: icono `bell`, partial `campanita` (contador + dropdown 5 últimas + marcar leída/todas), bandeja personal en rutas `auth` (NO admin; `abort_unless` dueño, 403 ajeno), cambio mínimo en `navigation.blade.php` (desktop + móvil), `npm run build`. (3) `RoleMatrixSeedTest` (existente) rojo por mi permiso nuevo → agregado `view notificaciones` al esperado del admin. **Suite 397 verdes.** Gotcha: el grep del bundle marcó `lg:flex` MISSING = falso negativo (el CSS escapa `.lg\:flex`); con match literal, presente. Colisión con main (P-SPK-02): cero en código.
- **Pasos marcados:** P-M15-05 [x], P-M15-06 [x].
- **Decisiones:** ninguna. · **Delegaciones:** ninguna nueva.
- **Próximo paso:** P-M15-07 (preferencias/opt-out por usuario) + P-M15-08 (tests integrales) → P-M15-09 (merge coordinado + QA staging).

### [2026-07-04] Stream 2 · día 2: seeds de configuración M15 + prompt del cron de cola
- **Quién:** Mauricio + Claude (Max-2 · Forjador B) — nota consumo: sesión aún en Fable 5 (dictado pedía Opus 4.8; el switch de modelo es de Mauricio)
- **Objetivo declarado:** P-M15-03 (delegación) + P-M15-04 (seeds), dictado del Director día 2
- **Qué se hizo:** (1) **P-M15-04**: 4 claves `notif_*` en `ConfiguracionSeeder` (plantilla json del evento de prueba, reintentos max, backoff, nombre de remitente placeholder D-001) + 4 tests (idempotencia 2×, casteo por tipo, re-seed no pisa valor editado, dispatcher renderiza con la plantilla sembrada) — verificado además con doble `db:seed` manual (4 claves, 0 duplicados); **suite 382 verdes local**. (2) **P-M15-03**: prompt de delegación del cron `queue:work --stop-when-empty --max-time=55` redactado sección por sección con la plantilla VERIFICACION-CPANEL (estado previo textual → [CAMBIO] único → crontab completo de vuelta con verificación de que el `schedule:run` por-minuto sigue → corrida manual del worker → log), entregado vía Mauricio para revisión del Director ANTES del despacho. Main avanzó (spike PWA + partes fleet): no se mergea ahora — no toca M15; merge en P-M15-09.
- **Pasos marcados:** P-M15-04 [x]; P-M15-03 [EN CURSO] (prompt entregado, falta despacho + evidencia). *(P-M15-03 cerrado [x] en la actualización de más abajo, mismo día.)*
- **Decisiones:** ninguna.
- **Delegaciones:** 1 redactada y entregada (cron de cola); pendiente de despacho por Mauricio.
- **Próximo paso:** al volver la evidencia del cron → cerrar P-M15-03 y archivarla en `docs/qa/INFRA/`; en paralelo **P-M15-05** (reintentador atómico + vista `/admin/notificaciones`) según tablero día 3.

### [2026-07-04] Stream 2 · cierre P-M15-03 + HALLAZGO: scheduler volvió a `*/20`
- **Quién:** Mauricio + Claude (Max-2 · Forjador B, ahora sí en **Opus 4.8**) + IA externa de cPanel
- **Objetivo declarado:** procesar la respuesta de la delegación del cron de cola
- **Qué se hizo:** la IA-cPanel agregó el cron `* * * * * … queue:work --stop-when-empty --max-time=55` y lo verificó (worker corre, cola vacía, sale sin errores) → **P-M15-03 [x]**, evidencia íntegra en `docs/qa/INFRA/2026-07-04--INFRA--cron-queue-work-m15.md`. **HALLAZGO CRÍTICO al leer el paso 1:** el cron del **scheduler** está en `*/20 * * * *`, NO en `* * * * *`; el log de la propia respuesta no muestra ninguna corrida de `bsale:sync-stock` (con `*/20` el :50 nunca se dispara). Esto **contradice la evidencia archivada de P-S0-07** (que lo dejó en `* * * * *` con stock corriendo a :50) → o hubo una reversión posterior no documentada, o el server nunca persistió el fix. Es el bug original de vuelta. Escalado al Director (no es territorio de stream 2; toca las syncs de otro stream). Impacto en M15: el reintentador P-M15-05 (vía `schedule:run`) correría cada 20 min en vez de 5 → granularidad degradada, no roto (reclama por `programada_para <= now()`).
- **Pasos marcados:** P-M15-03 [x].
- **Decisiones:** ninguna.
- **Delegaciones:** 1 recibida y archivada (cron de cola, APROBADO CON OBSERVACIONES).
- **Próximo paso:** Director decide re-despacho del fix del scheduler (`*/20 → * * * * *`); stream 2 sigue con **P-M15-05** (reintentador atómico robusto a scheduler no-por-minuto + vista `/admin/notificaciones`).

### [2026-07-02] Stream 2: visto bueno de PLAN-M15 + P-M15-01/02 construidos (motor de notificaciones)
- **Quién:** Mauricio + Claude (Max-2 · Forjador B) — **nota consumo: sesión corrió en Fable 5, no Opus 4.8 del roster**
- **Objetivo declarado:** incorporar ajustes del visto bueno → P-M15-01 y P-M15-02
- **Qué se hizo:** (1) merge de `origin/main` a la rama (trae `docs/fleet/`; conflicto solo en esta bitácora, resuelto conservando ambas entradas — `41dc503`); (2) PLAN-M15 actualizado con los 2 ajustes obligatorios (Notificacion SIN audit / PreferenciaCanal SÍ; reintentador atómico `withoutOverlapping` + claim por UPDATE) y las 3 notas (transiciones por canal, `{{ }}` siempre, campanita v1 sin polling); (3) **P-M15-01**: migraciones `notificaciones` + `preferencias_canal` MySQL 5.7-safe (unique de preferencias con evento a 100 chars por el prefijo utf8mb4) — `ff27f40`; (4) **P-M15-02**: `NotificacionDispatcher` + contrato `Canal` + `CanalMail`/`CanalDatabase`/`CanalWhatsApp`(stub) + job `EnviarNotificacion` (tries=1, reintento propio con backoff de `Configuracion`) + Mailable con vista escapada — `771f278`; (5) 14 tests nuevos → **suite 378 verdes local** (CI no corre en la rama). Gotcha de test: columna datetime trunca microsegundos → comparar backoff con `toDateTimeString()`, no `equalTo`.
- **Pasos marcados:** P-M15-01 [x], P-M15-02 [x] (en la rama).
- **Decisiones:** ninguna nueva (ajustes = dictado del visto bueno).
- **Delegaciones:** ninguna enviada; la del cron de cola (P-M15-03) es el siguiente paso y su prompt sale de PLAN-M15 §5.
- **Próximo paso:** **P-M15-03** — redactar la delegación del cron `queue:work` (plantilla VERIFICACION-CPANEL) y entregarla a Mauricio; en paralelo P-M15-04 (plantillas + seeds, no depende del cron).

### [2026-07-02] Se constituye la FLOTA: 6 cuentas Claude orquestadas + tablero de 3 días
- **Quién:** Mauricio + Claude (Opus, stream 1)
- **Objetivo declarado:** P-S0-17 — organizar 2 cuentas Max + 4 Pro con roles, dictado de modelo/esfuerzo y control de consumo.
- **Qué se hizo:** investigación del estado Anthropic a hoy (modelos Fable 5/Opus 4.8/Sonnet 5/Haiku 4.5; esfuerzo low→max; `/fast`; límites por plan NO públicos → medición empírica vía `/usage`). Diseño: Max-1 forjador stream 1 (spike PWA), Max-2 forjador stream 2 (E1·M15, ya operando), Pro-1 DIRECTOR (tablero/verificación/ledger, escribe solo `docs/fleet/`), Pro-2 AUDITOR/QA (read-only), Pro-3 INVESTIGADOR (decisiones D-0xx), Pro-4 ESCRIBA (docs aislados). Bus = Mauricio + repo; territorio exclusivo por cuenta; tareas L/XL solo a Max. Archivos: `docs/fleet/{FLOTA,TABLERO-3-DIAS,CONSUMO}.md` + `docs/delegacion/KICKOFF-DIRECTOR.md`.
- **Pasos marcados:** P-S0-17 [x]. · **Decisiones:** ninguna. · **Delegaciones:** 4 prompts de arranque entregados a Mauricio (Director, QA, Investigador, Escriba).
- **Próximo paso:** día 1 del tablero (2026-07-03): Max-1 → P-SPK-01; el Director se constituye y toma el mando del tablero.

### [2026-07-02] Stream 2 arranca: setup del entorno + PLAN-M15 con sello (pendiente visto bueno)
- **Quién:** Mauricio + Claude (stream 2, primera sesión)
- **Objetivo declarado:** kickoff §2/§3 + primer entregable (PLAN-M15 antes de P-M15-01)
- **Qué se hizo:** (1) toolchain local desde cero — esta PC no tenía PHP: instalado PHP **8.3.31** (misma versión fijada en `composer.json`) + Composer 2.10.2, extensiones sqlite/mbstring/etc. y `cacert.pem` (lección bitácora 2026-06-08); (2) clon propio `DaliGo-M15` (jamás el working tree del stream 1) + rama `feature/m15-notificaciones` desde `origin/main` (`4da5de2`); (3) setup HANDOFF §10 completo — gotcha: el `.env.example` trae `DB_DATABASE=` vacío y Laravel lo trata como seteado → apuntarlo explícito al sqlite; (4) **gate del kickoff cumplido: suite 364 verdes local**; (5) lectura obligatoria §3 completa (protocolo, RUTA, biblia M15, HANDOFF, CLAUDE.md íntegro, GUIA, delegación+plantillas, DECISIONES con D-011 nueva); (6) **`docs/planes/PLAN-M15.md`** escrito con sello de vigencia (verificado contra el código: cola/mail/scheduler/permisos/config/servicios/audit/nav) — diseño: dispatcher + contrato Canal (mail/database/whatsapp-stub), 2 tablas MySQL 5.7-safe, plantillas como claves `Configuracion`, reintentos propios con backoff, campanita, mapa P-M15-01…10 y 2 delegaciones listas para redactar.
- **Pasos marcados:** ninguno todavía (el plan es pre-P-M15-01; nota agregada en §4/E1)
- **Decisiones:** ninguna nueva. D-007 (stub) y D-011 (staging=QA) incorporadas al plan.
- **Delegaciones:** ninguna enviada aún; las 2 de E1 (cron cola, SPF/DKIM) quedaron especificadas en el plan §5.
- **Próximo paso:** **visto bueno de Mauricio a PLAN-M15.md** → recién ahí P-M15-01 (migraciones).

### [2026-07-02] Nace el stream 2: kickoff de E1 · M15 Notificaciones en rama paralela
- **Quién:** Mauricio + Claude (Opus, stream 1)
- **Objetivo declarado:** P-S0-16 — dar una tarea grande a la segunda cuenta de Claude de Mauricio sin chocar con el trabajo de M11.
- **Qué se hizo:** brief completo en `docs/delegacion/KICKOFF-E1-M15.md` para que el stream 2 construya la unidad E1 (la siguiente del plan) en la rama `feature/m15-notificaciones`: lectura obligatoria de toda la doc, presentación de la IA de cPanel/QA, reglas anti-colisión (territorio prohibido de producción, archivos compartidos con cambio mínimo, `public/build` se regenera post-merge y jamás se resuelve a mano, merge final coordinado con Mauricio), estándares innegociables y primer entregable = `docs/planes/PLAN-M15.md` con sello de vigencia. Verificado que `deploy.yml`/`tests.yml` disparan SOLO en `main` (pushear la rama no despliega; CI no corre en ramas → suite local obligatoria).
- **Pasos marcados:** P-S0-16 [x]; E1 pasa a [EN CURSO · stream 2] en §0/§4.
- **Decisiones:** ninguna. · **Delegaciones:** ninguna (el kickoff ES la delegación, a un dev-stream).
- **Próximo paso:** stream 2 → PLAN-M15 + visto bueno → P-M15-01. Stream 1/Mauricio → P-S0-03/04 (briefs).

### [2026-07-02] Aislamiento de pruebas: comando de limpieza + D-011 (URL oficial y entornos)
- **Quién:** Mauricio + Claude (Opus)
- **Objetivo declarado:** P-S0-15 (duda del dueño: ¿los datos de prueba quedan para siempre? ¿se escribe algo en Bsale?)
- **Qué se hizo:**
  - **Verificado con evidencia:** Bsale es SOLO-LECTURA por construcción (`BsaleClient` únicamente tiene `get()`/`each()`; los `->delete()` de las syncs son sobre tablas espejo locales; el kardex es local y el push está sin construir hasta D-005).
  - **Aclarado:** hoy hay UNA sola instancia/BD (staging = futura prod) — los datos de prueba SÍ persisten. Nuevo comando **`produccion:limpiar-pruebas`** (confirmación + `--force`; borra asignaciones/reportes/tandas/kardex + audits de reportes en transacción; catálogo/usuarios/Bsale intactos) + 2 tests.
  - **D-011 TOMADA** (Mauricio): oficial futura = `daligo.impdali.cl`, staging queda de pruebas, separación real de entornos se ejecuta en F3 (P-F3-06 actualizado). HANDOFF §9 reconciliado (cierra la deuda "dominio de staging").
  - Incidente de entorno local: Windows bloqueó `php.exe` (Control de aplicaciones) a mitad de sesión; el dueño lo destrabó y se continuó (no se pusheó nada sin tests verdes).
- **Pasos marcados:** P-S0-15 [x]. · **Decisiones:** D-011 tomada. · **Delegaciones:** ninguna.
- **Próximo paso:** sin cambio — P-S0-03/04 → E1 · M15 Notificaciones.

### [2026-07-02] Fix panel del jefe: "Pendientes de otros días" (alerta sin destino)
- **Quién:** Mauricio + Claude (Opus)
- **Objetivo declarado:** P-S0-14 (hallazgo del dueño en staging: "Por aprobar: 1" con "Cola · hoy: 0")
- **Qué se hizo:** las alertas por-aprobar/devueltos son globales pero la cola era solo de hoy → sección nueva "Pendientes de otros días" (patrón de los devueltos-de-otros-días del soplador), fila de reporte extraída al partial `_fila-reporte.blade.php` compartido, info-tips aclarados. Test nuevo → 362 verdes. Verificado en preview: enviado de ayer visible con fecha, aprobado desde ahí, panel vuelve a "Todo al día".
- **Pasos marcados:** P-S0-14 [x].
- **Decisiones:** ninguna. · **Delegaciones:** ninguna.
- **Próximo paso:** sin cambio — P-S0-03/04 → E1 · M15. (En staging queda 1 enviado del 01/07 —"Romcom Enjoyer"— que ahora será visible para aprobar/devolver.)

### [2026-07-02] Auditoría E2E de M11 + hardening pre-demo (reunión del jefe)
- **Quién:** Mauricio + Claude (Opus), en una segunda máquina (contexto reconstruido desde la doc)
- **Objetivo declarado:** fuera de plan (pedido del jefe: funcionalidad lista lo antes posible) → P-S0-13 creado. Auditar la calidad de TODO el flujo de producción end-to-end y dejar la demo lista.
- **Qué se hizo:**
  - **Auditoría E2E de M11** (3 agentes exploradores en paralelo + verificación propia línea a línea + comprobaciones empíricas con tinker): veredicto general SÓLIDO (owner-checks, máquina de estados, kardex desde tandas, sin N+1, 73 tests).
  - **5 hallazgos confirmados y corregidos:** `whereBetween` reincidente en `sopladorHistorial` (vacío en SQLite con rango de 1 día), locks faltantes en `devolver`/`ajustar`/`destroyReporte` (carrera aprobar∥devolver podía desincronizar el kardex), `max:100000` anti-dedazo en cantidades, hint stale en `asignar.blade.php`. **8 falsas alarmas descartadas** por verificación (detalle en la bitácora de CLAUDE.md).
  - **3 tests de regresión nuevos** (el de F1 verificado que falla con el código viejo) → **361 verdes**.
  - **Demo E2E local verificada** en preview: asignar (jefe) → 2 tandas + motivos (soplador a 375px, intercepts client-side funcionando) → enviar → aprobar → kardex consistente (470 = 450+10+10, 6 movimientos) → drill-downs e historial con rango de 1 día OK. Cero errores de consola/red.
  - **Drifts de doc cerrados:** línea duplicada P-S0-08 en RUTA-MAESTRA, HANDOFF §8e/§9 (cron ya resuelto, decía "en curso").
- **Pasos marcados:** P-S0-13 [x] (creado y cerrado en esta sesión).
- **Decisiones:** ninguna.
- **Delegaciones:** ninguna (QA staging post-deploy queda opcional para después de la reunión).
- **Próximo paso:** sin cambio — P-S0-03/04 (despachar briefs, tabla de bodegas D-003) → arrancar E1 · M15 Notificaciones. La reunión del jefe puede re-priorizar; registrar lo que salga como R-002 si cambia el plan.

### [2026-07-02] Cierre de E0 — delegaciones de infra ejecutadas, hallazgo del cron y catastro de bodegas
- **Quién:** Mauricio + Claude + IA externa de cPanel/QA (primeras delegaciones reales del protocolo)
- **Objetivo declarado:** P-S0-07 y P-S0-08
- **Qué se hizo:**
  - **P-S0-07 (infra):** la IA externa detectó que el crontab real difería del runbook (dos `schedule:run` en `*/20` y `*/15`, ninguno por-minuto) y SE DETUVO a confirmar — el protocolo funcionó. Corrección aplicada: cron único `* * * * *`. **Hallazgo mayor:** `bsale:sync-stock` (:50) jamás había corrido por cron en producción; primera corrida verificada (28.350 stocks al día, 0 errores). `deploy.sh` des-congelado (`no-skip-worktree`). Rotación de contraseña BD pospuesta por decisión del dueño → P-S0-09.
  - **P-S0-08 (BD):** 0 duplicados de `bsale_variant_id` (migración unique habilitada para E3) + **catastro real de bodegas: 16** (la biblia estimaba ~25) — "Santa Rosa" ES una bodega Bsale; 2 de prueba; posible duplicidad en las de servicio técnico. Tabla pre-llenada para Luis/Ricardo lista en D-003.
  - Evidencias archivadas en `docs/qa/INFRA/` (las 2 primeras del proyecto); bitácora de CLAUDE.md += entrada del cron; HANDOFF §8e/§9 corregidos con el estado real.
- **Pasos marcados:** P-S0-07 [x], P-S0-08 [x]; creados P-S0-09 (password pospuesto), P-S0-10 (44 errores sync-clients), P-S0-11 (seeders en log de deploy), P-S0-12 (git "ahead by 111" en el servidor).
- **Decisiones:** ninguna tomada; D-003 avanzó (catastro obtenido, falta respuesta de Luis/Ricardo).
- **Delegaciones:** 2 ejecutadas y archivadas (ver `docs/qa/README.md` índice).
- **Próximo paso:** P-S0-03/04 (Mauricio despacha los briefs, en especial la tabla de bodegas de D-003) → arrancar E1 · M15 Notificaciones.

### [2026-07-01] Sesión E0 — consolidación documental y nacimiento del sistema de gestión
- **Quién:** Mauricio + Claude (Opus)
- **Objetivo declarado:** unidad E0 completa — análisis integral del proyecto + documentación oficial paso a paso.
- **Qué se hizo:**
  - **Análisis cruzado en profundidad** (3 agentes en paralelo): documentación del repo (biblia/HANDOFF/CLAUDE/docs), los 9 documentos de negocio de la carpeta "contexto dali" (Gantt, hoja de ruta 2025, correcciones, flujos, procesos operativos por sucursal, 991 órdenes ML pendientes) y auditoría del código real (rutas/modelos/migraciones/seeders/tests).
  - **Hallazgos clave:** el código va ADELANTADO al Gantt (M01/M02/M03/M11-F1 + taller ST + espejo de inventario ya construidos, ~358 tests verdes) pero las decisiones de F0 van atrasadas; el HANDOFF tenía "código fantasma" (espejo de inventario y taller sin documentar) y una redacción que se prestaba a creer M13 implementado (NO lo está); `docs/PLAN-M11-FASE2.md` obsoleto (drift doc↔código).
  - **Transcripción del escaneo** `Correcciones luis.pdf` (13 págs) → `docs/CORRECCIONES-LUIS.md`: la biblia cubre todo; 6 novedades menores anotadas (paso P-S0-06).
  - **Sistema documental creado:** `docs/RUTA-MAESTRA.md` (tablero maestro con unidades E0–E13, re-baseline R-001, tracker ~21%), `docs/DECISIONES.md` (D-000…D-010 con briefs), `docs/PROTOCOLO-SESION.md`, `docs/delegacion/` (protocolo + 3 plantillas), `docs/qa/`, `docs/planes/` (regla del sello de vigencia; PLAN-M11-FASE2 archivado), esta bitácora.
  - **HANDOFF adelgazado** a manual técnico: nota M13 SIN CÓDIGO, secciones nuevas §8e (espejo inventario) y §8f (taller ST), estado migrado a RUTA-MAESTRA; README con nuevo orden de lectura + mapa de docs; CLAUDE.md regla de oro ampliada con el cierre de sesión.
- **Pasos marcados:** P-S0-01 [x], P-S0-02 [x] (los commits: ver este mismo push).
- **Decisiones:** creadas D-001…D-010 (todas abiertas salvo D-000 retroactiva); ninguna tomada.
- **Delegaciones:** preparadas (pendientes de envío por Mauricio): P-S0-07 (infra cPanel: cron duplicado, password BD, deploy.sh) y P-S0-08 (query duplicados `bsale_variant_id` + CSV de bodegas).
- **Próximo paso:** P-S0-03 (despachar briefs) y P-S0-07/P-S0-08 (delegaciones); luego arrancar E1 (M15 Notificaciones).
