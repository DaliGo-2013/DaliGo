# RUTA MAESTRA — DaliGo · paso a paso y estado vivo

> Este documento responde dos preguntas: **¿dónde estamos parados?** y **¿qué sigue?**
> La especificación (el QUÉ y el POR QUÉ) vive en `PROYECTO_DALIGO.md` (la biblia) — aquí NO se repite, se enlaza.
> **Regla de oro:** si hiciste código y no actualizaste este archivo en el mismo push, la sesión no terminó.
> Cómo trabajar con este documento: `docs/PROTOCOLO-SESION.md`.

---

## 0. DÓNDE ESTAMOS HOY (panel vivo — actualizar en cada cierre de sesión)

| Campo | Valor |
|---|---|
| **Última actualización** | 2026-07-13 (**P-M14-06 hecho en rama** — historial admin `/admin/aprobaciones` con filtros + resumen, `8c8d3e2`, 485 tests, verificado en preview 375/768/1280; E2·M14 va **6/7**, solo falta P-M14-07 merge coordinado) · 2026-07-08 (**E1·M15 CERRADA — primera unidad completa de la flota, kickoff→producción en 6 días**: P-M15-10 [x] con entregabilidad verificada — SPF/DKIM VALID + DMARC creado, Gmail=RECIBIDOS con captura del dueño, fila mail Enviada, correos M12 des-atascados del log; evidencia en `docs/qa/INFRA/`) · 2026-07-07 noche (**P-M15-09 [x] CERRADO** — QA staging APROBADO CON OBSERVACIONES aceptado y archivado en `docs/qa/M15/`; motor de reintentos probado EN PRODUCCIÓN · 2026-07-07 tarde (**M15 MERGEADO A MAIN = DEPLOY** — P-M15-09 fase deploy hecha con doble llave, reintentador corregido a `everyFifteenMinutes()` · **E2 arranca**: `docs/planes/PLAN-M14.md` sellado — motor de aprobaciones, espera VISTO BUENO de Mauricio antes de la primera migración · **I-03 CERRADA**: token renovado por Mauricio y verificado — las 4 syncs corrieron OK en sus slots nuevos, espejo descongelado) · 2026-07-07 (**I-01 CERRADA en modo compatibilidad** — cron `*/15` + syncs en :00/:15/:30/:45 · **P-SPK-03 hecho → spike PWA COMPLETO**) · 2026-07-06 (**P-M12-01 piloto** QR mergeado `1639d71` LIVE; SMTP pendiente P-M15-10) |
| **Fase actual** | F1→F2 (código adelantado al Gantt; decisiones de F0 atrasadas) |
| **Unidad activa** | **DESPACHOS-v1** [stream 2, rama `feature/despachos-v1` — PLAN sellado `docs/planes/PLAN-DESPACHOS-V1.md`, esperando visto bueno de Mauricio antes de la 1ª migración] · **E2 · M14 Aprobaciones** [stream 1, EN CONSTRUCCIÓN — **6/7**, P-M14-01..06 en rama `feature/m14-aprobaciones`] · E1·M15 CERRADA · E0 cerrada salvo pendientes menores |
| **Próximo paso** | Stream 1: **P-M14-07** (re-sellado PLAN-M14 + gate `/pre-merge` R-31 + MERGE COORDINADO doble llave + QA staging celular) — P-M14-01..06 hechos en rama `feature/m14-aprobaciones` (último `8c8d3e2`, 485 tests; motor E2E + bandeja + historial) · Stream 2: lote DECISIONES/I-04/I-05 (este push) → **PLAN-DESPACHOS-V1 sellado** (M04 pospuesto por el dueño) → micro-backlog M15 · Mauricio: `P-S0-03/04` briefs |
| **Bloqueos activos** | D-003 (bodegas — Ricardo respondió 13-07, Luis pendiente; M04 pospuesto → sin fecha crítica), D-005 (soporte Bsale, bloquea M05-F2; ruta docs subió por DESPACHOS) — semáforo en `docs/DECISIONES.md` §2 |
| **Salud doc↔código** | VERIFICADA el 2026-07-07 (infra por SSH: crontab `*/15` vivo, 4 syncs OK en sus slots, espejo al día tras I-03) |
| **Avance global** | **≈ 21 %** (tracker en §10) |

**Hecho:** M01 Core · M02 Catálogo+Precios · M03 Clientes · M11 Producción F1 · Taller ST básico (subset de M12) · Espejo inventario read-only (base de M04) · **M15 Notificaciones (E1, cerrada 2026-07-08)**
**En curso:** E0 (esta consolidación)
**Especificado sin código:** M04 · M05 · M07 · M08 · M12 (resto) · **M13 (sin código — ver nota §5.6)** · M14 · M15 · M16
**Standby/backlog:** M06 · M09 · M10

---

## 1. Cómo leer este documento

- **Unidades de trabajo `E0…E13`** (§4–§8): bloques secuenciales de 1–6 semanas; cada una tiene objetivo, prerrequisitos, rama git, criterio de "hecho" y qué se delega a la IA de QA.
- **Pasos `P-<área>-<nn>`**: la unidad atómica (1–4 horas). Marcas:

| Marca | Significado |
|---|---|
| `- [ ]` | Pendiente |
| `- [ ] [EN CURSO]` | Iniciado, no cerrado |
| `- [ ] [B:D-0NN]` | **Bloqueado** por la decisión D-0NN (ver `docs/DECISIONES.md`) — greppable con `\[B:D-` |
| `- [x] … (commit \`hash\` / evidencia)` | Hecho. **Prohibido marcar `[x]` sin commit o evidencia QA enlazada.** |

- Un módulo pasa a **HECHO** solo cuando todos sus pasos están `[x]` **y** hay al menos una evidencia QA con veredicto APROBADO en `docs/qa/Mxx/`.
- Los detalles de implementación de lo YA construido no están aquí: están en `HANDOFF.md` (manual técnico). Aquí solo el estado.

---

## 2. Mapa general (fases × módulos)

Orden rector: biblia §6 (Gantt). Fechas re-baselinadas el 2026-07-01 (§11, R-001).

| Fase | Contenido | Hito re-baselinado | Estado |
|---|---|---|---|
| F0 Discovery | Decisiones Sprint 0 (§3) | **H1' = 31-jul-2026: las 10 decisiones cerradas** | 🔴 atrasado (se persigue en paralelo) |
| F1 Transversales | M01 ✅ · M02 ✅ · M03 ✅ · **M15 (E1)** · **M14 (E2)** · M16-v0 (E10) | H2 login ✅ · **H3' ≈ 9-oct-2026** | 🟡 en curso |
| F2 Núcleo operativo | **M04 (E3–E4)** · M11 ✅F1 · **M05 (E5)** · **M13 (E6)** · **M07 (E7)** · **M08 MVP (E8)** · **M12 resto (E9)** · M16-v1 (E10) | **H4' ≈ 5-dic-2026** | ⚪ pendiente |
| F3 Piloto Mirador | Hardening, migración datos, capacitación, marcha blanca dic (E11) | **H5' = go-live 11-ene-2027** | ⚪ pendiente |
| F4 Rollout Abate | Config+capacitación, M09-mini (E12) | **H6' ≈ 9-feb-2027** | ⚪ pendiente |
| F5 Coquimbo + cierre | Config, deuda técnica, docs finales (E13) | **H7' ≈ fin feb-2027** | ⚪ pendiente |

---

## 3. Sprint 0 — decisiones que destraban el plan

Las 10 decisiones viven en **`docs/DECISIONES.md`** (fichas D-001…D-010 con briefs listos para enviar). Aquí solo el trabajo operativo:

- [x] **P-S0-01** · Registro de decisiones creado con briefs copy/paste (este push, 2026-07-01)
- [x] **P-S0-02** · Transcripción y cotejo de `Correcciones luis.pdf` → `docs/CORRECCIONES-LUIS.md` (este push, 2026-07-01)
- [ ] **P-S0-03** · Enviar los briefs D-001, D-003, D-004, D-005, D-007 a sus decisores (Mauricio los despacha; anotar fecha de envío en cada ficha)
- [ ] **P-S0-04** · D-003: ~~obtener el catastro~~ ✔ obtenido (16 bodegas, evidencia P-S0-08) → **enviar la tabla pre-llenada a Luis/Ricardo** (lista en `docs/DECISIONES.md` D-003)
- [ ] **P-S0-05** · Revisar semáforo cada viernes hasta cerrar las 10 (ritual §0)
- [ ] **P-S0-06** · Aclarar con Luis/Mauricio las 3 anotaciones ambiguas del escaneo (ver `docs/CORRECCIONES-LUIS.md` §Discrepancias): "14 m³" junto a peso/dimensiones, la serie de códigos `1010/1020/…/8010001`, y "(Nuevo APP B[sale?])" junto a M10
- [x] **P-S0-07** · Delegación infra a IA-cPanel: cron del scheduler corregido (los `*/20`+`*/15` reemplazados por UNO `* * * * *` — hallazgo: `bsale:sync-stock` a :50 JAMÁS había corrido en prod; primera corrida verificada, 28.350 stocks al día), `deploy.sh` des-congelado, `schedule:list` y logs limpios (evidencia: `docs/qa/INFRA/2026-07-02--INFRA--cron-deploysh-infra.md`)
- [x] **P-S0-08** · Query duplicados `bsale_variant_id`: **0 duplicados** → migración `unique` habilitada para E3. Catastro real de bodegas obtenido: **16** (no ~25) — "Santa Rosa" ES una bodega Bsale (evidencia: `docs/qa/INFRA/2026-07-02--INFRA--duplicados-variantid-catastro-bodegas.md`)
- [ ] **P-S0-09** · Rotar contraseña de la BD `impdali_daligo` + `.env` + `config:cache` (la clave se compartió por chat alguna vez — HANDOFF §9). **Pospuesto por decisión del dueño (2026-07-02)**; hacerlo idealmente antes de F3/piloto. La clave nueva NUNCA pasa por un chat: se escribe directo en cPanel y `.env`
- [ ] **P-S0-10** · Investigar los **44 omitidos / 44 errores recurrentes** de `bsale:sync-clients` (observación QA 2026-07-02). Hipótesis: colisiones de RUT duplicado en Bsale (comportamiento documentado, HANDOFF §8c) — confirmar en `bsale-sync.log` y reclasificar el conteo para que no parezca fallo
- [ ] **P-S0-11** · Confirmar seeders visibles (`RUNNING/DONE`) en el log de Actions del próximo deploy
- [ ] **P-S0-12** · Diagnosticar `git status` del servidor: "ahead of 'origin/main' by 111 commits" — probable remote `origin` apuntando al repo viejo o fetch por URL sin tracking ref. Pedir `git remote -v` en la próxima delegación de infra (no afecta el deploy actual)
- [x] **P-S0-13** · Auditoría E2E de M11 Producción + hardening pre-demo (pedido del jefe, fuera de plan): 3 exploradores + verificación línea a línea → 5 fixes (whereDate en historial del soplador, locks en devolver/ajustar/destroy espejando aprobar, `max:` anti-dedazo en cantidades, hint stale de asignar) + 3 tests de regresión (361 verdes) + demo local E2E verificada (commit `3ff976d`, 2026-07-02)
- [x] **P-S0-14** · Panel del jefe: sección "Pendientes de otros días" — las alertas por-aprobar/devueltos son globales pero la cola era solo de hoy → un enviado viejo quedaba contado e invisible (hallazgo del dueño en staging). Partial `_fila-reporte` compartido + test (commit `49f695a`, 2026-07-02)
- [x] **P-S0-15** · Aislamiento de pruebas: comando `produccion:limpiar-pruebas` (borra asignaciones/reportes/tandas/kardex + audits de reportes, con confirmación; catálogo intacto) + **D-011 TOMADA** (URL oficial `daligo.impdali.cl`, staging queda de pruebas, separación real en F3). Verificado: Bsale es solo-lectura por construcción (commit `3d1defd`, 2026-07-02)
- [x] **P-S0-16** · Kickoff del **stream 2** (segunda cuenta Claude): arranca E1 · M15 en la rama `feature/m15-notificaciones` con brief completo en `docs/delegacion/KICKOFF-E1-M15.md` (lectura obligatoria de toda la doc, reglas anti-colisión, territorio prohibido, merge coordinado; deploy/CI verificados solo-main) (commit `4da5de2`, 2026-07-02)
- [x] **P-S0-17** · **Flota constituida** (6 cuentas: 2 Max forjadores + 4 Pro con roles Director/QA/Investigador/Escriba) + tablero de 3 días: `docs/fleet/{FLOTA,TABLERO-3-DIAS,CONSUMO}.md` y `docs/delegacion/KICKOFF-DIRECTOR.md`. Incluye matriz modelo×esfuerzo (estado Anthropic verificado 2026-07-02) y ledger empírico de consumo vía `/usage` (este push, 2026-07-02)
- [x] **P-S0-18** · **Recetario de prompts + 3 skills de flota**: biblioteca oficial de Claude Code (48 prompts) evaluada y adaptada → `docs/delegacion/RECETARIO-PROMPTS.md` (24 fichas R-xx en español, por momento del flujo y rol) + skills `/arranque`, `/cierre`, `/pre-merge` en `.claude/skills/` (delgadas, anti-drift: solo referencian el doc canónico; viajan a las 6 cuentas vía pull) (este push, 2026-07-02)

---

## 4. F1 · Transversales restantes

### M01 Core — HECHO ✅
| Spec | Estado | Detalle técnico | Evidencia |
|---|---|---|---|
| biblia §4/M01 | 4/4 incrementos | `HANDOFF.md` §8 (histórico) | suite de tests (auth, usuarios, roles, sucursales, config, auditoría) |

### M02 Catálogo + Precios — HECHO ✅ (webhooks y enlace M04 pendientes para E13/E3)
| Spec | Estado | Detalle técnico | Evidencia |
|---|---|---|---|
| biblia §4/M02 | sync catálogo/precios + import/export CSV + cron horario | `HANDOFF.md` §8b | tests Bsale sync (catálogo, precios) |

### M03 Clientes — HECHO ✅ (boleta rápida es de M05; historial de compras post-M05)
| Spec | Estado | Detalle técnico | Evidencia |
|---|---|---|---|
| biblia §4/M03 | CRUD + sync ~47.800 clientes + vendedor_id | `HANDOFF.md` §8c | tests clientes + sync |

### E1 · M15 Notificaciones — núcleo multi-canal, email primero (~2 sem) — [CERRADA 2026-07-08 · stream 2 · 6 días de kickoff a producción]
> Asignada el 2026-07-02 al stream 2 (segunda cuenta Claude), rama `feature/m15-notificaciones`.
> Kickoff/contrato: `docs/delegacion/KICKOFF-E1-M15.md`. Los `[x]` de esta unidad se marcan en la rama.
> **Plan fino:** `docs/planes/PLAN-M15.md` (sello 2026-07-02, commit `4da5de2`) — **APROBADO por Mauricio el 2026-07-02** con 2 ajustes obligatorios incorporados (Notificacion sin audit / PreferenciaCanal sí; reintentador atómico con `withoutOverlapping` + claim por UPDATE) y 3 notas menores documentadas. Luz verde P-M15-01/02.
**Objetivo:** motor centralizado (tablas `notificaciones` + `preferencias_canal`, plantillas por evento, triggers, reintentos) con canal **email** operativo y canal **WhatsApp enchufable** (stub hasta D-007). No bloqueada por Marco: esa es la gracia del diseño.
**Rama:** `feature/m15-notificaciones` · **Depende de:** nada (sí requiere cron de cola → delegación).
**Hecho cuando:** tests verdes; en staging un evento llega por correo real y a la campanita; reintento ante fallo verificado.

- [x] **P-M15-01** · Migraciones `notificaciones` (polimórfica: evento, canal, destinatario, payload, estado, reintentos) + `preferencias_canal` — MySQL 5.7: VARCHAR(191) en índices; unique de preferencias con evento a 100 chars por el prefijo utf8mb4 (este push, 2026-07-02)
- [x] **P-M15-02** · `NotificacionDispatcher` + contrato `Canal` (`CanalMail`, `CanalDatabase`, `CanalWhatsApp` stub que loguea) + job `EnviarNotificacion` (tries=1, reintento propio con backoff) + 14 tests — suite 378 verdes (este push, 2026-07-02)
- [x] **P-M15-03** · Cola database + delegación IA-cPanel: cron `queue:work` — despachado y verificado (worker corre, procesa, sale sin errores); evidencia `docs/qa/INFRA/2026-07-04--INFRA--cron-queue-work-m15.md` (2026-07-04). ⚠️ La misma respuesta destapó que el cron del **scheduler** estaba en `*/20` → escalado al Director (I-01). **Actualización I-01 (2026-07-07):** HostGator estrangula crons por-minuto → grilla `*/15` alineada; el cron de cola quedó `*/15 … queue:work --stop-when-empty --max-time=840` (latencia ≤15 min; NO re-delegar la spec vieja por-minuto/`--max-time=55`)
- [x] **P-M15-04** · Plantillas por evento + seeds idempotentes + claves en `Configuracion` (`notif_plantilla_sistema_prueba`, `notif_reintentos_max`, `notif_backoff_minutos`, `notif_remitente_nombre` — grupo `notificaciones`) + 4 tests; verificado seed 2× a mano sin duplicados; suite 382 verdes (este push, 2026-07-04)
- [x] **P-M15-05** · Reintentos con backoff + vista `/admin/notificaciones` (permiso `view notificaciones`) — comando `notificaciones:reintentar` ATÓMICO (lockForUpdate + claim por UPDATE, robusto a scheduler degradado) agendado cada 5 min `withoutOverlapping`; panel read-only con filtros estado/canal/evento + botón "enviar prueba"; permiso aditivo en seeder + label; `PreferenciaCanal` en `AuditController::MODELOS`; 403 sin permiso con test (este push, 2026-07-04)
- [x] **P-M15-06** · Campanita in-app en nav (desktop + responsive) — icono `bell`, partial `campanita` (contador no-leídas + dropdown + marcar leída/todas), bandeja personal en rutas `auth` (valida dueño, 403 ajeno); cambio mínimo en `navigation.blade.php`; `npm run build` + grep del bundle verificado (`lg\:flex`/`lg\:hidden` presentes, escapadas). Suite 397 verdes (este push, 2026-07-04)
- [x] **P-M15-07** · Preferencias por usuario (canal por tipo de evento, opt-out) — tarjeta en el perfil (`prefs[evento][canal]`), `NotificacionPreferenciaController` con `updateOrCreate`; el dispatcher las respeta (test de integración form→dispatch). Canal database fijo (campanita siempre) (este push, 2026-07-04)
- [x] **P-M15-08** · Tests (dispatch por preferencia, reintento, opt-out, 403) — cobertura del checklist completa; suite 405 verdes (este push, 2026-07-04)
- **Correcciones de auditoría del Director (gate P-M15-09), aplicadas:** (1) página personal `/notificaciones` legible en móvil + panel admin en el nav Administración; (2) `leer` exige canal database (404 si no); (3) `withoutOverlapping(10)` en el reintentador; (4) barrido self-healing de pendientes huérfanas (>10 min) en el mismo comando; + menores (claim limpia `programada_para`, dedup de queries de la campanita, test de humo endurecido)
- [x] **P-M15-09** · Merge + deploy + QA staging — HECHO (2026-07-07): merge a main `cfae59a` (6 conflictos por unión, bundle grepeado 4/4, suite 444→445 verdes con la corrección `everyFifteenMinutes()` + test de grilla), deploy Actions verde (migraciones M15 DONE en MySQL prod + 6 seeders en el log), **QA staging/producción APROBADO CON OBSERVACIONES — aceptado por el Director** (database punta a punta OK: panel→cola→campanita→página personal→badge 0; mail Fallida = alcance P-M15-10; motor de reintentos PROBADO EN PRODUCCIÓN: intentos 1→2 + backoff `[5,15,60]` en vivo). Evidencia: `docs/qa/M15/2026-07-07--M15--qa-funcional-staging.md`
- [x] **P-M15-10** · Delegación IA-cPanel: SPF/DKIM/DMARC + entregabilidad — HECHO (2026-07-08): APROBADO CON OBSERVACIONES, 15/16 pasos + paso 12 cerrado con captura del dueño (**Gmail=RECIBIDOS, no spam**). Causa raíz del correo roto: cuenta `servicio@staging.impdali.cl` con auth fallida → queda `servicio@impdali.cl`; SPF reparado a VALID (faltaba la IP del server), DKIM VALID, DMARC creado `p=none`; fila mail del panel = **Enviada**; bonus M12 verificado (mailer `smtp`, nada al log). "Mostrar original" (SPF/DKIM/DMARC en cabeceras) pendiente-opcional; Outlook sin casilla; rotación de claves → R-04 del tablero. Evidencia: `docs/qa/INFRA/2026-07-08--INFRA--entregabilidad-correo-p-m15-10.md`
- **Micro-backlog M15** (del QA staging 2026-07-07, sin bloqueo — construir cuando toque, NO ahora): (a) el panel `/admin/notificaciones` no muestra el correo de destino, solo el nombre; (b) el error SMTP (`ultimo_error`) sale truncado en la UI del panel; (c) endurecer `test_campanita_visible_en_el_nav` (assertear el badge)

### E2 · M14 Aprobaciones digitales + spike PWA offline (~2.5 sem, spike en paralelo)
**Objetivo M14:** motor polimórfico (`aprobaciones`, `reglas_aprobacion`), umbral desde `Configuracion` (`umbral_aprobacion_clp` ya sembrado), bandeja del aprobador usable desde celular, escalamiento por scheduler, notifica vía M15. Primer consumidor real: ajuste de reporte de producción (M11).
**Objetivo spike:** mitigar el MAYOR riesgo técnico (offline M08) en W12, no en W27 — service worker + cola IndexedDB sobre `mi-reporte` del soplador; memo de arquitectura para M08.
**Ramas:** `feature/m14-aprobaciones` · `spike/pwa-offline-m11`.
**Hecho cuando:** flujo solicitar→notificar→aprobar/rechazar→escalar con tests; QA aprueba desde celular real; spike demostrado con modo avión.

- [ ] **P-M14-01** · Esquema motor (`aprobaciones` polimórfica + `reglas_aprobacion`)
- [ ] **P-M14-02** · Servicio `Aprobaciones::solicitar()` (evalúa reglas; auto-aprueba si no matchea — clave del "Héctor 5→1-2 pasos")
- [ ] **P-M14-03** · Bandeja móvil `/aprobaciones` (botones h-12, `lockForUpdate` contra doble-tap — lección bitácora 2026-06-30)
- [ ] **P-M14-04** · Escalamiento por scheduler (N min configurable → siguiente rol + re-notificación)
- [ ] **P-M14-05** · Cablear `ProduccionController::ajustar` como primer consumidor
- [ ] **P-M14-06** · Historial + vista por aprobador/solicitante, auditable
- [ ] **P-M14-07** · Tests + merge + QA staging desde celular
- [x] **P-SPK-01** · Spike: manifest + service worker sobre `mi-reporte` (instalable, cache assets, detección online/offline) — `public/{manifest.json,sw.js,icons/}`, ruta `/offline` standalone, `Alpine.store('red')` + `<x-produccion.indicador-red>`; estrategia conservadora validada adversarialmente (fallback SOLO en catch por los opaqueredirect de auth, scope `/`, guard de localhost); 4 tests, 368 verdes (`ee01204`, 2026-07-02)
- [x] **P-SPK-02** · Spike: cola IndexedDB para `registroStore` offline con idempotencia (UUID cliente) — migración `cliente_uuid` + unique `[reporte_id, cliente_uuid]`, endpoint idempotente dentro del lock + respuesta JSON, `resources/js/offline-queue.js` (encolar/drenar con token CSRF fresco, clasificación transitorio/permanente), integración en el form del soplador (encola offline + contador + reload al reconciliar); 4 tests de idempotencia, 372 verdes; verificado E2E en preview (2 tandas offline → sincronizan sin duplicar) (`793bfcc`, 2026-07-02)
- [x] **P-SPK-03** · Spike: prueba de campo (modo avión, matar app a mitad de cola) + memo `docs/SPIKE-PWA.md` con la arquitectura elegida para M08 — memo sellado y verificado contra el código (7 secciones + guardarraíl golden-hash en `PwaTest`); **prueba de campo APROBADA por el dueño el 06-07** (capturas verificadas por el Director, tablero día 3 `50f8878`): A OK, B OK, 4/4 tandas sin duplicados, motivo por tanda sobrevivió la cola (este push, 2026-07-07)

### E10-v0 · M16 BI corte 0 — Dashboard ejecutivo v0 — ✅ CERRADA 2026-07-14 (plan: `docs/planes/PLAN-M16-V0.md`)
> Alcance re-baselinado por el plan sellado: el Inicio (`/dashboard`) se convierte en el tablero, cards por permiso (no "solo admin"); **stock crítico OMITIDO en v0 con evidencia** (sin señal de mínimo en el espejo Bsale — plan §4). Los IDs P-M16-0x de abajo son los pasos del PLAN-M16-V0 (el P-M16-02 de E10-v1 es otra cosa).
- [x] **P-M16-01** · Controller: 6 cards nuevas de lectura agrupadas por módulo (Producido/Avance/Merma de hoy, Recepciones por confirmar, Aprobaciones pendientes espejo de bandeja, Notificaciones fallidas) — queries agregadas `whereDate`/COUNT/SUM sin N+1, semilla del Inicio intacta, `$indicadores` plano preservado como contrato de tests; +10 tests (conteos exactos + visibilidad por rol), 581 verdes (`900d74a`, 2026-07-14)
- [x] **P-M16-02** · Vista `/dashboard` por secciones (encabezado de módulo + misma grilla `x-stat-card`) — responsive verificado 375/768/1280 sin scroll horizontal, build con grep 7/7 (`f268997`, 2026-07-14)
- [x] **P-M16-03** · Gate pre-merge R-31 adversarial 16/16 OK con 3 observaciones anotadas para v0.1 (`f9b0721`) + merge coordinado con doble llave Director+Mauricio a main (`4900d5b`, Deploy+Tests verdes, bundle verificado servido en staging) — **E10-v0 CERRADA**; la rama `feature/m16-v0-dashboard` cumplió su ciclo (2026-07-14)

---

## 5. F2 · Núcleo operativo

### DESPACHOS-v1 · carve-out M05-parcial + M07 + M08-MVP (~stream 2) — [EN CURSO]
> **Pivote del dueño (2026-07-13):** M04 pospuesto; el stream 2 arranca DESPACHOS. Plan fino sellado: `docs/planes/PLAN-DESPACHOS-V1.md` (2026-07-13, commit `fcb9466`). **Rama:** `feature/despachos-v1`. **Gate:** visto bueno de Mauricio ANTES de la primera migración.
**Objetivo:** que un pedido facturado en Bsale se retire sin fraude (QR único validado en bodega + alerta de doble retiro) y se entregue con prueba (conductor: firma+foto+hora, offline-first). Emisión sigue en Bsale.
**Hecho cuando:** en staging un documento real de Bsale se espeja, su QR valida en la cola de bodega (2º escaneo = ALERTA), y el conductor confirma la entrega desde el celular sobreviviendo un corte de señal; tests verdes.

- [ ] **P-DSP-00** · Exploración read-only del shape real de `documents.json` (fija el nodo `details` antes de la migración) — evidencia a `docs/qa/INFRA/`
- [ ] **P-DSP-01** · Espejo `documentos_venta`+`_detalles` + `DocumentSync` + `bsale:sync-documents` (upsert por `bsale_document_id`, sin delete; grilla `*/15` slot `:45`)
- [ ] [B:D-006] **P-DSP-02** · Catálogo `zonas` + `users.zona_id` + seeder; zona del cliente derivada de `vendedor_id`
- [ ] **P-DSP-03** · Entidad `Despacho` + `escaneos_despacho` (código `DSP-`, estados, auditable) + panel admin crear/listar
- [ ] **P-DSP-04** · QR anti-fraude (M07): QR firmado + escaneo en bodega (`lockForUpdate`, alerta doble retiro) + cola "McDonald's" (polling) + entrega total/parcial
- [ ] **P-DSP-05** · PWA conductor (M08-MVP): hoja de ruta por zona + confirmación firma+foto+hora offline (cola IndexedDB `entregas`, sobre el memo SPIKE-PWA §4)
- [ ] **P-DSP-06** · Integración M14: despacho sobre umbral solicita aprobación (tras merge de M14 a main)
- [ ] **P-DSP-07** · Gate `/pre-merge` R-31 + merge coordinado doble llave + QA staging (escaneo + entrega desde celular)

### E3 · M04-F1 Inventario: del espejo a módulo (~2.5 sem) — [B:D-003] · **POSPUESTO (pivote a DESPACHOS 2026-07-13)**
**Base real:** el espejo read-only YA existe (`Bodega`, `Stock`, `StockSync`, cron `:45` — grilla `*/15` de I-01) — documentado en `HANDOFF.md` §8e. E3 construye encima, no desde cero.
**Rama:** `feature/m04-inventario-f1` · **Depende de:** D-003 (levantamiento) — D-002 deseable, no bloqueante (default conservador).
**Hecho cuando:** stock mostrado cuadra contra Bsale en 5 SKUs × 3 bodegas (QA staging); roles operativos reciben 403 en la vista cruzada.

- [ ] [B:D-003] **P-M04-01** · Campos locales en `bodegas`: clasificación física/virtual, propósito, `sucursal_id`
- [ ] **P-M04-02** · Vistas de stock por producto/bodega/sucursal + permisos `view stock`/`manage inventario`
- [ ] **P-M04-03** · Vista cruzada filtrada por perfil (accesos por rol se definen al CIERRE del módulo — estrategia D-002; interim: solo admin/jefes)
- [ ] **P-M04-04** · Alertas básicas: bajo mínimo, sin movimiento 10 días; punto de reorden por SKU
- [ ] **P-M04-05** · Migración `unique` en `bsale_variant_id` — ✔ habilitada: P-S0-08 confirmó **0 duplicados** en prod (evidencia 2026-07-02)
- [ ] **P-M04-06** · Tests + merge + QA staging

### E4 · M04-F2 Reservas por vendedor + transferencias (~3 sem)
**Objetivo:** la corrección #2 de Luis: reservas con dueño (vendedor) y vencimiento; movimientos locales (patrón kardex de M11, sin corromper el espejo); transferencia entre sucursales consumiendo M14; alertas de reservas vencidas.
**Rama:** `feature/m04-reservas-transferencias` · **Depende de:** E2, E3; D-005 define si el traspaso empuja a Bsale o queda local (diseñar para ambos).
**Hecho cuando:** flujo Coquimbo C-08 digitalizado punta a punta en staging (solicitar → aprobar desde celular → notificar → reserva bloqueada); tests de concurrencia (`lockForUpdate`).

- [ ] **P-M04-07** · Esquema reservas (dueño, vencimiento configurable) + movimientos locales
- [ ] **P-M04-08** · Transferencias entre sucursales vía M14 + notificación M15
- [ ] **P-M04-09** · Liberación automática de reservas vencidas (scheduler) + alertas
- [ ] **P-M04-10** · Tests de concurrencia + merge + QA con 2 usuarios (vendedor + jefe)

### E5 · M05 Ciclo de la factura (~6 sem, 3 sub-fases mergeables)
**Rama(s):** `feature/m05-cotizaciones` → `feature/m05-emision-bsale` → `feature/m05-boleta-rapida`.
**Hecho cuando:** una cotización creada en staging termina como DTE real en el **sandbox** de Bsale (JAMÁS probar escritura contra producción primero); aprobación de Héctor en 1 paso.

- [ ] **P-M05-01** · F1: `cotizaciones`/`cotizacion_items`, vencimiento configurable, validación de stock asignado, estados del documento
- [ ] **P-M05-02** · F1: asignación vendedor/cliente/stock + envío por correo (M15)
- [ ] [B:D-005] **P-M05-03** · F2: emisión DTE vía Bsale API (sandbox primero), folio/urlPdf, idempotencia por `salesId`
- [ ] **P-M05-04** · F2: aprobación Héctor 5→1-2 pasos (reglas M14 auto-validan pago+stock+descuento) + QR del documento (insumo M07)
- [ ] **P-M05-05** · F2: cierre administrativo con conciliación de pagos
- [ ] [B:D-004] **P-M05-06** · F3: boleta rápida <1 min sin datos de cliente
- [ ] **P-M05-07** · F3: bono conductor por destino/km (tabla configurable) — dato para Matías/RRHH
- [ ] **P-M05-08** · Tests por sub-fase + QA staging del ciclo M-02 completo de la biblia

### E6 · M13 Devoluciones (~2.5 sem)
> ⚠️ **Nota de corrección (2026-07-01):** M13 NO tiene código. No confundir con la acción `devolver` de los reportes de producción (M11) ni con el taller (M12). Esta nota existe porque una versión anterior del HANDOFF daba a entender lo contrario.

**Rama:** `feature/m13-devoluciones` · **Depende de:** E4, E5-F1, M14/M15.
**Hecho cuando:** flujo A-12 completo en staging desde el link público del cliente hasta el reingreso a stock; límites de upload verificados por IA-cPanel.

- [ ] **P-M13-01** · Formulario público del CLIENTE (ruta sin auth con token firmado) + fotos obligatorias
- [ ] **P-M13-02** · Categorización transporte/fábrica/otro + reglas automáticas por tipo y origen
- [ ] **P-M13-03** · Reembolso vía M14 si ≥ umbral; reingreso automático a stock (movimiento M04) si buen estado
- [ ] **P-M13-04** · Reportes por causa y por canal + tests + QA staging (desde un celular)

### E7 · M07 QR anti-fraude en retiro (~2 sem)
**Contexto:** caso real de intento de retiro con factura adulterada (biblia §4/M07). Mayor ROI político del proyecto.
**Rama:** `feature/m07-qr-retiro` · **Depende de:** E5-F2.
**Hecho cuando:** doble escaneo del mismo QR dispara alerta y bloquea; retiro > umbral exige aprobación remota al celular; pantalla de bodega refresca sola.

- [ ] **P-M07-01** · Validación de QR en puesto de bodega (estado/monto/cliente/items) + registro de escaneos
- [ ] **P-M07-02** · Alerta de doble entrega + bloqueo; entrega total/parcial
- [ ] **P-M07-03** · Aprobación remota sobre umbral (M14) + pantalla cola de bodega "tipo McDonald's" (polling)
- [ ] **P-M07-04** · Tests + QA staging con celular real escaneando QR impreso

### E8 · M08 Despacho + PWA conductor MVP (~5.5 sem) — mayor riesgo técnico
**Alcance MVP (biblia):** guía de despacho, hoja de ruta por zona con estados, PWA conductor con la arquitectura del spike (E2): ruta del día, confirmación firma+foto+hora **offline-first**, forma de pago; cierre de ruta alimenta bono (E5). Cotizador transportistas solo si hay API keys a tiempo. Venta-en-ruta y plan B = post-MVP.
**Ramas:** `feature/m08-despacho` + `feature/m08-pwa-conductor` · **Depende de:** E5, spike aprobado, D-006 (zona simple vs CRM).
**Hecho cuando:** un pedido de staging se entrega con el teléfono en modo avión y sincroniza al volver (firma y foto persistidas); ciclo M-03/M-04/M-05 de Mirador sin papel punta a punta.

- [ ] **P-M08-01** · Guía de despacho al emitir/cargar + estados de ruta (asignada→cargada→en ruta)
- [ ] [B:D-006] **P-M08-02** · Hoja de ruta por zona con vendedor asignado
- [ ] **P-M08-03** · PWA conductor: ruta del día + confirmación offline-first (arquitectura del memo `docs/SPIKE-PWA.md`)
- [ ] **P-M08-04** · Cierre de ruta → cierre administrativo + bono conductor
- [ ] **P-M08-05** · Cotizador transportistas (Chilexpress/Starken/Cruz del Sur) — si hay API keys; si no → F4
- [ ] **P-M08-06** · Tests + QA offline agresivo (guion: matar app a mitad de sync, doble envío, foto grande con mala señal)

### E9 · M12 Servicio técnico completo (~2.5 sem — el taller base YA existe, ver HANDOFF §8f)
**Rama:** `feature/m12-taller-completo` · **Depende de:** E1 (alertas), E5 (vincular documento para garantía).
**Hecho cuando:** máquina de prueba recorre pre-ingreso→diagnóstico→aprobación por link→retiro; alertas 3/6/12 meses disparan con fechas simuladas.

- [ ] [EN CURSO] **P-M12-01** · Pre-ingreso online con QR (cliente llena antes de llegar) — **piloto adelantado** en rama `feature/m12-ingreso-qr-piloto` (2026-07-06): formulario público sin login vía QR firmado por sucursal (`URL::signedRoute` + throttle + honeypot) → orden real `fuente='qr'` sin confirmar → el encargado confirma la recepción (`confirmada_at`) y ahí se dispara el correo (Mailable standalone, migrable a M15). Historial **compartido** por las 3 sucursales con filtro/badge por **sucursal de recepción** y rótulo "se repara en **Mirador** (casa matriz)" — Coquimbo/Abate reciben pero no reparan. 391 tests verdes. Gate R-31 **APROBADO CON OBSERVACIONES** + **mergeado a main** (`1639d71`, deploy OK 16s) — **live en producción**. **Falta:** configurar SMTP en el `.env` del servidor (P-M15-10) para que el correo de confirmación se entregue (ya es resiliente si falta) + QA real en prod (escanear QR → enviar → confirmar).
- [ ] **P-M12-02** · Cotización estructurada del técnico + aprobación del cliente por link WhatsApp (`wa.me` hasta D-007)
- [ ] **P-M12-03** · Alertas 3m (fin garantía) / 6m (bodegaje $) / 12m (desarme/reventa/donación con registro de destino) + tablero de máquinas próximas a plazo
- [ ] **P-M12-04** · Sugerencia automática de repuestos según histórico + cobro hora de servicio en no aprobadas
- [ ] **P-M12-05** · Tests + QA staging del flujo completo

### E10-v1 · M16 BI corte 1 (~1 sem, tras E5)
- [ ] **P-M16-02** · Ventas/descuentos por vendedor con margen (datos E5) + transferencias con aprobador (E4)

> Candidato para v2 (del escaneo de Luis, `docs/CORRECCIONES-LUIS.md`): reporte "pedidos de repuestos de servicio técnico" (la función operativa vive en M12; el listado/reporte iría en M16-v2).

---

## 6. F3 · Piloto Mirador (E11) → **H5' go-live 11-ene-2027**

**Racional del re-baseline:** W34 original caía en fiestas; marcha blanca en diciembre (doble registro papel+sistema) y corte de papel post-fiestas. Es colchón, no atraso.
**Depende de:** E5–E9 estables en staging; **D-001 (nombre) es última llamada aquí**.
**Hecho cuando:** 1 semana de marcha blanca sin incidentes P1; checklist de go-live firmado por Luis/Mauricio.

- [ ] **P-F3-01** · Hardening: índices/carga (~48k clientes, ~28k stocks), revisión de seguridad de rutas públicas (M12/M13)
- [ ] **P-F3-02** · Backup automatizado + **restore ENSAYADO** (delegación IA-cPanel: dry-run en BD aparte)
- [ ] **P-F3-03** · Migración de datos: peso/dimensiones por SKU, usuarios reales con roles/sucursal
- [ ] **P-F3-04** · Manuales de 1 página por rol + capacitación (Pedro, Ricardo, Héctor, sopladores)
- [ ] **P-F3-05** · Marcha blanca diciembre (doble registro) + monitoreo semanal
- [ ] **P-F3-06** · Ejecutar la separación real staging/producción antes de usuarios reales (hoy staging = prod): prod = `daligo.impdali.cl` con BD limpia, staging queda en `staging.impdali.cl` (decisión D-011, 2026-07-02)
- [ ] **P-F3-07** · Go-live + criterio "1 semana sin P1" antes de cortar papel

---

## 7. F4 · Rollout Abate (E12) → **H6' ≈ 9-feb-2027**

- [ ] **P-F4-01** · Configuración/usuarios/capacitación Abate + especialidad taller (recepción que deriva a Mirador — validar flujo con Gonzalo)
- [ ] **P-F4-02** · **M09-mini (stretch):** bandeja de órdenes ML con filtrado de canceladas + boleta vinculada a ID de orden — ataca las **991 órdenes pendientes** reales
- [ ] [B:D-008] **P-F4-03** · Impresión de etiquetas térmica (según decisión de hardware)
- [ ] **P-F4-04** · Go-live Abate

---

## 8. F5 · Coquimbo + cierre (E13) → **H7' ≈ fin feb-2027**

- [ ] **P-F5-01** · Configuración Coquimbo (producción ya opera vía M11) + flujo C-01/C-08 con transferencias reales
- [ ] **P-F5-02** · Deuda técnica: webhooks Bsale (reemplaza polling), staleness de espejos, push kardex M11→Bsale (si D-005 lo validó), plan de migración MySQL 5.7→8.x
- [ ] **P-F5-03** · Documentación final + manuales + retrospectiva + traspaso a soporte

---

## 9. Módulos fuera de fase

| Módulo | Estado | Regla |
|---|---|---|
| **M06 POS sala de venta** | [STANDBY] | "Lo que funciona no se toca" (Luis). No construir. Revisable post-MVP. |
| **M09 Marketplaces completo** | [BACKLOG] | Solo el M09-mini entra como stretch en F4 (P-F4-02). API Falabella queda fuera. |
| **M10 eCommerce** | [BACKLOG] [B:D-009] | Fuera de los 9 meses. No dejar que entre por la ventana. |

---

## 10. Tracker de avance (base 100, ponderado por esfuerzo)

> Regla anti-autoengaño: un ítem solo suma cuando su criterio de "hecho" pasó QA en staging.
> M09/M10 están fuera de la base: si entran, suman como bonus, no diluyen.

| Ítem | Peso | % | Aporta |
|---|---|---|---|
| M01 Core | 6 | 100 % | 6.0 |
| M02 Catálogo+precios | 5 | 90 % | 4.5 |
| M03 Clientes | 4 | 70 % | 2.8 |
| M04 Inventario | 9 | 15 % (espejo) | 1.4 |
| M05 Ciclo factura | 10 | 0 % | 0 |
| M07 QR retiro | 4 | 0 % | 0 |
| M08 Despacho+PWA | 12 | 0 % | 0 |
| M11 Producción | 6 | 75 % | 4.5 |
| M12 Servicio técnico | 8 | 25 % (taller base) | 2.0 |
| M13 Devoluciones | 4 | 0 % | 0 |
| M14 Aprobaciones | 5 | 0 % | 0 |
| M15 Notificaciones | 5 | 0 % | 0 |
| M16 BI | 7 | 0 % | 0 |
| F3 Piloto (hardening/migración/capacitación) | 7 | 0 % | 0 |
| F4 Rollout Abate | 5 | 0 % | 0 |
| F5 Coquimbo + cierre | 3 | 0 % | 0 |
| **TOTAL** | **100** | | **≈ 21.2** |

**Lectura ejecutiva (hitos):** H1' decisiones 31-jul · H2 ✅ · H3' transversales 9-oct · H4' núcleo 5-dic · **H5' Mirador sin papel 11-ene-2027** · H6' Abate 9-feb · H7' cierre fin feb-2027.

---

## 11. Re-planificaciones

Cada cambio al plan se anota aquí con fecha y motivo. El ORDEN de los módulos es el de la biblia y no se cambia sin decisión registrada.

### R-001 · 2026-07-01 — Re-baseline inicial (sesión E0)
- **Qué:** fechas de hitos ajustadas (tabla §2); el orden de módulos NO cambió.
- **Por qué:** el código va ADELANTADO al Gantt (M01–M03+M11-F1+taller listos en W9; estaban planificados hasta W15–W23) pero las decisiones de F0 van ATRASADAS (debían cerrar en W8). El equipo real es 1 stream (dueño+IA), no 2 devs paralelos: la ruta es secuencial. H5 se movió del 21-dic al 11-ene-2027 porque W34 caía en fiestas (marcha blanca en dic, corte de papel después). Total: +4 semanas honestas vs plan original.
- **Además:** spike PWA offline adelantado a E2/W12 (el Gantt lo dejaba implícito en M08/W27) para atacar temprano el mayor riesgo técnico declarado (biblia §6/riesgos).
