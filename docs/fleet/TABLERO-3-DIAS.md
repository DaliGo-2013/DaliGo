# TABLERO — 3 días de flota (2026-07-03 → 2026-07-05)

> Lo opera el DIRECTOR (única cuenta que edita este archivo). Marcas: `[ ]` pendiente,
> `[~]` en curso, `[x]` verificada con evidencia, `[!]` bloqueada. Cada tarea indica la
> talla estimada (S/M/L/XL — ver `CONSUMO.md`) y el dictado modelo+esfuerzo.

## Día 1 (2026-07-03)

| Cuenta | Tarea | Talla | Modelo·Esfuerzo | Hecho cuando |
|---|---|---|---|---|
| Max-1 | [x] **P-SPK-01** · Spike PWA: manifest + service worker sobre `mi-reporte` (instalable, cache de assets, detección online/offline) — *VERIFICADO por Director 02-07: `ee01204` (23 archivos), RUTA-MAESTRA [x] mismo push, PwaTest 4 tests, `public/build/` ✓, bitácora ✓, manifest VIVO en staging verificado independiente (scope "/", standalone, 4 iconos), e instalación en celular real CONFIRMADA por Mauricio (captura 02-07). Diseño P-SPK-02 anotado: iOS sin Background Sync → cola se vacía en evento 'online' + carga de página* | L | Fable 5 · xhigh (diseño) → Opus 4.8 · high (impl.) | Instalable en un celular real; assets cachean; indicador online/offline funciona; tests verdes |
| Max-2 | [x] `docs/planes/PLAN-M15.md` con sello de vigencia → visto bueno → **P-M15-01** (migraciones) y **P-M15-02** (Dispatcher + canales) — *VERIFICADO por Director 02-07: plan `01f219a` aprobado (Max-1, 2 ajustes); GATE cumplido en `ff27f40` (plan ajustado + 2 migraciones MISMO push; `Notificacion` sin audit con comentario, `PreferenciaCanal` auditable, test guard línea 244); `771f278` dispatcher+3 canales+job+14 tests (valida catálogo EVENTOS); unique compuesto con evento a 100 chars (prefijo 767 utf8mb4); territorio limpio (0 archivos de producción, 0 compartidos tocados); main intacto (`35e72db`); RUTA-MAESTRA marcada en el mismo push. Suite 378 declarada (no ejecutable por Director; 2ª revisión QA día 2)* | L | Opus 4.8 · high | Plan aprobado; migraciones y servicios con tests en la rama |
| Director | [x] Constituirse (leer kickoff + docs), validar PLAN-M15, abrir ledger día 1, dictar modelo/esfuerzo a cada cuenta — *hecho 02-07: docs leídos completos; PLAN-M15 validado con spot-checks del sello contra clon fresco (queue=database, seeder aditivo, nav lg:flex, 4 syncs sin queue:work, User Notifiable — todo coincide); ledger abierto; dictado día 1 entregado a Mauricio* | M | Sonnet 5 · high | Tablero y ledger al día; dictados entregados a Mauricio |
| QA | [ ] Guion de regresión local de M11 (flujo completo con pasos exactos) + primera revisión adversarial del diff de la rama M15 (`git fetch && git diff origin/main...origin/feature/m15-notificaciones`) | M | Sonnet 5 · high | Guion entregado + hallazgos del diff con file:línea (o "sin hallazgos") |
| Investigador | [ ] Matriz D-002 pre-llenada módulo×rol (leer `RolesAndPermissionsSeeder` + `config/permissions.php` + biblia §7) lista para enviar a Luis + prompt INVESTIGACION-DECISION para P-S0-10 (44 errores sync-clients) | M | Sonnet 5 · medium | Ambos textos listos para copy/paste de Mauricio |
| Escriba | [ ] Refresh de `docs/GUIA-DALIGO.md` (drifts conocidos: sync ya corre por cron; seed también a nivel workflow) | S | Haiku 4.5 · low | Diff solo de ese archivo; entregado al Director para commit vía su parte |

## Día 2 (2026-07-04)

| Cuenta | Tarea | Talla | Modelo·Esfuerzo | Hecho cuando |
|---|---|---|---|---|
| Max-1 | [x] **P-SPK-02** · Cola IndexedDB para `registroStore` offline con idempotencia (UUID cliente) — *VERIFICADO por Director 02-07: `793bfcc` en main (14 archivos, `public/build/` ✓, RUTA/bitácora/CLAUDE.md mismo push). Idempotencia en 3 capas: check dentro del lock + unique compuesto [reporte_id, cliente_uuid] (char 36, NULLs nativos no chocan — MySQL 5.7 razonado) + clasificación de errores en el drenado (2xx borra / 422-403 permanente / 419-5xx reintenta, MAX 5 anti-bucle). FormData completo (motivos incluidos — bitácora 06-26 cubierta), CSRF fresco al drenar, decisión iOS aplicada. 4 tests de idempotencia ✓. Bundle `app-CyIDBx14.js` VIVO en staging verificado independiente (IndexedDB 'daligo'/'tandas' + cliente_uuid presentes). Opus 4.8 ✓* | XL | Opus 4.8 · xhigh | Tanda creada offline se sincroniza al volver la señal, sin duplicados ante reintento; tests |
| Max-2 | [x] **P-M15-03** (cola database + cron `queue:work` vía IA-cPanel) + **P-M15-04** (plantillas por evento + seeds) — *AMBAS VERIFICADAS. P-M15-04: `bfdc77c` (seeder aditivo, 4 tests). P-M15-03: cron creado y verificado por IA-cPanel (crontab textual, worker corrió y salió limpio, 5/5 pasos OK), evidencia íntegra archivada en `532354a` (docs/qa/INFRA/2026-07-04--INFRA--cron-queue-work-m15.md), main intacto, sesión en Opus 4.8 ✓. BONUS: el procesamiento destapó la INCIDENCIA I-01 (abajo)* | L | Opus 4.8 · high | Prompt de delegación entregado a Mauricio; seeds idempotentes; tests |
| Director | [ ] Verificación papel↔código de día 1 (commits vs tablero), ledger día 2, replanificar si algo se atrasó | M | Sonnet 5 · high | Tablero actualizado con evidencia por tarea |
| QA | [ ] Ejecutar guion de regresión M11 en local + probar el spike PWA con modo avión (checklist del Forjador A) + segunda revisión del diff M15 | M | Sonnet 5 · high | Partes de QA con veredicto por flujo |
| Investigador | [ ] D-007: opciones reales 2026 de WhatsApp Business API (Meta directo vs BSP, costos/plazos CL) + consolidar paquete D-005 para Víctor (huecos BSALE_API + sandbox + bodegas) | M | Sonnet 5 · medium | Ficha D-007 con opciones/costos citados + email listo para Víctor |
| Escriba | [ ] Borrador `docs/manuales/BORRADOR-soplador.md` (1 página, nombre del sistema como placeholder por D-001) | S | Haiku 4.5 · low | Página lista siguiendo el flujo real de mi-reporte |

## Día 3 (2026-07-05)

| Cuenta | Tarea | Talla | Modelo·Esfuerzo | Hecho cuando |
|---|---|---|---|---|
| Max-1 | [~] **P-SPK-03** · Prueba de campo (modo avión, matar app a mitad de cola) + memo `docs/SPIKE-PWA.md` con la arquitectura elegida para M08 — *avance 06-07: memo redactado con sello, verificado adversarialmente (95% fiel), guardarraíl golden-hash. PRUEBA DE CAMPO APROBADA por el dueño 06-07 (capturas verificadas por Director): A OK (2 tandas offline con contador → sync sola al volver señal), B OK (matar app con cola pendiente → drena al reabrir), 4/4 tandas sin duplicados en el reporte del jefe, motivo por tanda ("2ª: Rebaba") sobrevivió la cola offline. Falta solo: Mauricio pega a Max-1 el resultado + material I-01 → push único de cierre + parte con /usage* | L | Opus 4.8 · high (memo con Fable 5) | Memo sellado; RUTA-MAESTRA marca P-SPK-01..03 |
| Max-2 | [x] **P-M15-05** (reintentos backoff + vista `/admin/notificaciones`) + **P-M15-06** (campanita nav, cambio mínimo, build + grep del bundle) — *VERIFICADO por Director 06-07 con auditoría adversarial de 3 lentes sobre `fd31b2e` (22 archivos, 397 tests, Opus 4.8 ✓): claim atómico REAL (transaction+lockForUpdate+UPDATE, dispatch fuera), robusto a scheduler degradado, backoff de claves notif_*, compartidos 100% aditivos con conteos exactos, territorio limpio, RoleMatrixSeedTest +1 legítimo, bundle recompilado de verdad (lg\:flex/lg\:hidden + clases nuevas), 14/15 tests sólidos. HALLAZGOS de auditoría: 4 correcciones dictadas como gate → **GATE LEVANTADO 06-07** (ver fila siguiente, `f7353fb`)* | L | Opus 4.8 · high | Vistas funcionando en su local; suite verde en la rama |
| Director | [ ] Informe ejecutivo de los 3 días para Mauricio (avance, consumo real por cuenta, tabla de tallas calibrada) + tablero de los próximos 3 días (propuesta) | M | Sonnet 5 · high | Informe entregado; CONSUMO.md con la tabla calibrada |
| QA | [ ] QA integral del spike (guion adversarial: doble envío, foto grande, matar app) + guion QA-FUNCIONAL-STAGING para M15 (se usará post-merge) | M | Sonnet 5 · high | Ambos guiones entregados; hallazgos del spike reportados a Max-1 vía Director |
| Investigador | [ ] Consolidado: estado de las 10 decisiones D-0xx con lo investigado (qué falta de quién) — insumo del informe del Director | S | Sonnet 5 · medium | Tabla actualizada entregada |
| Escriba | [ ] Borrador `docs/manuales/BORRADOR-jefe-bodega.md` (1 página: asignar → cola → aprobar → kardex) | S | Haiku 4.5 · low | Página lista |

## Día 4+ (post-tablero, directiva de ritmo del dueño)

| Cuenta | Tarea | Talla | Modelo·Esfuerzo | Hecho cuando |
|---|---|---|---|---|
| Max-2 | [x] **Correcciones de auditoría (4+3) + P-M15-07 + P-M15-08** — *VERIFICADO por Director 06-07 en `f7353fb`: (1) página personal `/notificaciones` solo-auth ✓ (2) guard `canal=database` 404 ✓ (3) `withoutOverlapping(10)` ✓ (4) barrido self-healing de huérfanas (>10 min, re-dispatch idempotente — su llamada, bien justificada) ✓ + claim limpia `programada_para` ✓ + fix `data_get` (punto del evento interpretado como anidación — cazado por SU test) ✓. 9 tests nuevos en diff (405 netos), bundle recompilado, main intacto, Opus 4.8 ✓. MOTOR M15 COMPLETO EN LA RAMA* | L | Opus 4.8 · high | Correcciones aplicadas; preferencias respetadas por dispatcher con test; suite verde |
| Max-1 | [x] **F-01** recetario como apoyo automático — *VERIFICADO: `07dbe92`, 4 archivos +10/−3 exactos, regla en CLAUDE.md (auto-carga en 6 cuentas), 3 descriptions con auto-disparo y keywords en español, cuerpos intactos. Desviación: corrió Fable 5 (ledger)* | S | Opus 4.8 · medium (corrió Fable 5) | Regla en CLAUDE.md + skills auto-disparables |
| Max-2 | [~] **P-M15-09** · Merge coordinado + QA staging — **MERGE EN MAIN Y DESPLEGADO 07-07** ✅: doble OK dado, corrección pre-push aplicada (`everyFifteenMinutes()` verificado en main:48), merge `dd54f8c`+`cfae59a`, deploy Actions `success` 17:47, tests de main verdes. **M15 VIVO en producción** (migraciones DONE en MySQL prod, 6 seeders en el log, corrección grilla + test `test_reintentar_agendado_en_la_grilla_de_15` en main ✓, gotcha qrcode en bitácora ✓, 445 tests). **QA STAGING EN CURSO 07-07: pasos 1-7 OK** (database→Enviada, campanita contador+lectura, página personal, badge a 0; mail→Fallida con error SMTP registrado — ESPERADO, alcance de P-M15-10, NO es I-03 aunque el reporte la citó: corregir etiqueta al archivar). **[x] CERRADO 08-07**: veredicto QA APROBADO CON OBSERVACIONES aceptado y ARCHIVADO (`cb0015a` → docs/qa/M15/2026-07-07--M15--qa-funcional-staging.md, 152 líneas, RUTA [x] verificada por el Director, gotcha tinker en bitácora, micro-backlog anotado). Reintentador probado EN PRODUCCIÓN | L | Opus 4.8 · high | Merge en main con suite verde + QA staging APROBADO (correo real + campanita + fila admin) |
| Max-1 | [x] Cierre **P-SPK-03** + **I-01 modo compatibilidad** — *VERIFICADO 07-07: `faf772f` (grilla */15 + hourlyAt 15/30/45 + ScheduleBsaleTest + 2 evidencias QA + plantilla/kickoff actualizados), `d1db5ef` (memo SPIKE-PWA 165 líneas sellado, RUTA P-SPK-01..03 [x] con hashes, golden-hash test), `aa10d2b` (verificación EN VIVO del slot 16:15 UTC). SPIKE PWA COMPLETO. Parte formal recibido 07-07 (403 tests, deploy verde); /usage pendiente* | S | Fable 5 (decisión dueño) | Push único; parte con /usage |
| Max-1 | [~] **E2 · M14 Aprobaciones digitales — arranque**: PLAN-M14 SELLADO en main (`8fb6763`, 167 líneas) y **VALIDADO por el Director 07-07** (spot-checks del sello: ajustar():677 con lock ✓, Configuracion::set firstOrFail ✓, umbral_aprobacion_clp sembrado ✓, grilla verificada ✓; diseño conforme: handlers por tipo_accion, payload-obsoleto→rechazo automático, escalamiento como nivel, contrato monto=null conservador). HALLAZGO ÚTIL del plan: reintentador M15 en everyFiveMinutes() viola convención I-01 → corrección dictada a Max-2 PRE-push. **Falta: visto bueno de Mauricio con 3 puntos de decisión** (umbral 50 unidades, unidades-vs-CLP, cambio UX jefe_bodega). De paso el sello CERRÓ I-03 (syncs verificadas por SSH). ⚠️ Ver R-03: P-M14-01 apareció en la rama sin visto bueno registrado | M (plan) | Fable 5 hoy · Opus 4.8 desde 08-07 | PLAN-M14 sellado + visto bueno; vigilancia reportada |
| Max-2 | [~] **P-M15-10** · Entregabilidad — *prompt REVISADO por el Director 07-07 (2 lentes adversariales): estructura, seguridad y valores MAIL_* correctos (smtps/465 coherente con Laravel 12 ✓, DMARC p=none prudente ✓, salvaguardas ✓), PERO 2 BLOQUEANTES cazados: (1) paso 11 — `$m` dentro de comillas dobles: bash lo expande a vacío → Parse error de PHP, el envío de prueba jamás corre; (2) paso 5b — lógica circular (condiciona el reseteo de clave a una validación que ocurre 6 pasos después). **[x] CERRADO 08-07 → E1·M15 UNIDAD COMPLETA (10/10 pasos)**: entregabilidad APROBADA (SPF/DKIM VALID, DMARC creado, Gmail=RECIBIDOS con captura, fila Mail Enviada, correos M12 des-atascados), respuesta archivada íntegra en docs/qa/INFRA/2026-07-08 (verificada por Director), RUTA con E1 [CERRADA 2026-07-08] y M15 en "Hecho" del panel. Primera unidad completa de la flota: kickoff→producción con QA archivado en 6 días. R-04 (rotación de claves) sigue pendiente del dueño* | M | Opus 4.8 · high | Prompt revisado y despachado; SPF/DKIM OK; correo real a inbox; fila mail "Enviada" |
| Max-1 | [x] **P-M14-02** · Servicio Aprobaciones + handler + excepciones — *VERIFICADO por Director 08-07: `5470f31` (8 archivos +588 exactos), batería 10/10 IDÉNTICA a la dictada (auto×3, pendiente×2 incl. monto=null, aplicar bajo lock, doble-aprobar excepción, conflicto updated_at, rechazo, rol vigente), lock+re-check presente, 3 eventos `aprobacion.*` en Notificacion::EVENTOS (integración post-merge legítima), retoque a PreferenciasCanalTest justificado (conteo derivado del catálogo — más robusto). Suite 461 declarada. Desviaciones del plan pre-aceptadas (sin guard, eventos en 02) — anotar al re-sellar. GO P-M14-03 vigente; mañana: /model Opus + vigilancia crontab ANTES de la bandeja* | L | Fable 5 | Batería de tests del plan verde; suite completa verde |
| Max-1 | [x] **P-M14-01** · Esquema del motor de aprobaciones — *VERIFICADO por Director 07-07: `5d9286d` en `feature/m14-aprobaciones` (8 archivos +456 exactos), migración fiel al PLAN §1.2 (unique `tipo_accion(64)`, índices `[estado,rol_aprobador]` y `[estado,created_at]` con propósito comentado), seeder `firstOrCreate`, 6 tests de esquema, AuditController +2 modelos, suite 451 declarada, gobierno en main `6ae4d0e`. Visto bueno confirmado (R-03 cerrada). Excepción de verificación multi-agente aceptada (paso mecánico, diseño doblemente aprobado)* | M | Fable 5 | migrate:fresh --seed ×2 idempotente; suite verde |

## Incidencias

### I-04 · REPO PÚBLICO + alerta GitGuardian — ABIERTA, MÁXIMA PRIORIDAD (08-07)
GitGuardian alertó "Company Email Password exposed" (push 15:12:25 UTC). Investigación del
Director: los diffs de HOY en todas las ramas están limpios (todo `[CLAVE OCULTA]`/`«PEGAR»`)
→ hipótesis principal: el detector se disparó con el placeholder junto a `MAIL_USERNAME` en la
evidencia archivada. PERO el hallazgo grave del barrido es otro: **el repositorio es PÚBLICO**
(verificado vía gh: visibility=PUBLIC) — biblia de negocio, HANDOFF con rutas de servidor,
nombre de BD y evidencias de infra expuestos a internet. PLAN (en orden): (1) Mauricio pone el
repo PRIVADO ya; (2) ANTES del próximo push a main: configurar auth de `git pull` en el
servidor (deploy key/PAT de solo lectura) porque con repo privado el pull del deploy.sh FALLA;
(3) rotación TOTAL R-04 ampliada: servicio@impdali.cl + admin staging + **clave de BD
impdali_daligo (P-S0-09 deja de estar pospuesta — es obligatoria)**; (4) Mauricio abre la
alerta de GitGuardian y entrega archivo/línea/commit (SIN el valor) para confirmar falso
positivo vs real; (5) Max-2: barrido de secretos de TODA la historia en todas las ramas.

### R-01 · Reconciliación P-S0-18 — CERRADA (origen: Mauricio)
Commit `4d7caa1` (recetario de prompts + skills) lo hizo Mauricio mismo en otra compu en
tiempo muerto (fuente: biblioteca oficial de prompts de Claude Code). Ledger: /usage n/d.
**Derivada → I-02:** la intención era apoyo AUTOMÁTICO del recetario, pero quedaron skills
de ejecución manual. Corrección dictada a Max-1 (tarea F-01 abajo).

### F-01 · Recetario como apoyo automático (corrección de P-S0-18) — dictada a Max-1
La idea del dueño: que las IAs usen el recetario solas cuando la tarea lo amerite, sin
invocar skills a mano. Mecanismo dictado: (a) regla corta en CLAUDE.md (se carga sola en
CADA sesión) que ordena consultar `docs/delegacion/RECETARIO-PROMPTS.md` cuando la tarea
calce con sus momentos (planificar/construir/pre-merge/incidentes/cierre); (b) reescribir
los `description:` de las 3 skills para que el modelo las auto-dispare por contexto (así
funciona el auto-trigger de skills: por descripción, no por invocación manual). Talla S,
territorio stream 1 (CLAUDE.md + .claude/skills en main).

### R-02 — CERRADA por política del dueño (07-07)
El stream M12 (servicio técnico, "Marcos") trabaja AUTÓNOMO por decisión de Mauricio: no
reporta partes ni entra al tablero. **Regla operativa para la flota:** el Director monitorea
solo INTERFERENCIAS — si trabajo de la flota va a tocar archivos/territorio que M12 esté
moviendo, se notifica a Mauricio ANTES de decidir; si no hay cruce, M12 se ignora. Cruce ya
gestionado: superficie de conflicto del merge M15 (seeder/permissions/routes/nav) dictada a
Max-2 con resolución verificada.

### I-03 — CERRADA 07-07: token renovado y VERIFICADO
Evidencia (Max-1 por SSH, 12:26 CDT, en el sello de PLAN-M14): las 4 syncs corrieron OK en
sus slots nuevos — prices 16:31, stock 16:49, catalog 17:00, clients 17:23 UTC. Espejo
descongelado. De paso, señal temprana POSITIVA de la vigilancia I-01: la grilla `*/15`
sobrevivió y opera (chequeo formal de 24h igual corre el 08-07).

### I-03 (histórico) · Bsale responde 401 desde 06-07 ~16:00 CDT — token inválido (ABIERTA, urgente)
Detectada por Max-1: «can not be authenticated» en todas las syncs → espejo congelado
(stock desde 03-07 por I-01, catálogo/clientes/precios desde 06-07 por esto). Solo el dueño
puede: revisar/regenerar el token en el panel de Bsale y ponerlo en el `.env` del servidor.
REGLA DURA: el token JAMÁS pasa por un chat ni por el repo — se escribe directo en el `.env`
(cPanel File Manager) y luego `php artisan config:cache` en la Terminal. Max-1 entrega los
pasos exactos (var de env y ubicación). Cierra cuando un grep del log muestre syncs OK.
**Lateral anexo (Max-1, 07-07):** los correos del taller (M12) caen al LOG por `config:cache`
stale + `.env` SMTP a medio experimento — órdenes 5 y 6 salieron sin correo. Sin acción ahora:
el cierre real del correo saliente es **P-M15-10** (SPF/DKIM + cuenta SMTP).

### R-03 — CERRADA 07-07: visto bueno de M14 confirmado (dado en directo)
El parte de P-M14-01 declara "visto bueno de Mauricio DADO" y el dueño lo transmitió sin
corrección → se registra como confirmado. Los 3 puntos de decisión quedan REGISTRADOS COMO
PROPUESTOS en el plan (umbral 50 unidades · umbral v1 en unidades, `umbral_aprobacion_clp`
reservado a reglas monetarias · UX jefe_bodega: ajustes ≥50 esperan aprobación), salvo que
Mauricio los corrija — el umbral es clave de configuración, cambiarlo después no cuesta código.
Lección de proceso: los vistos buenos dados por canal directo se avisan al Director en el
momento, no en el parte siguiente.

### I-01 — CERRADA 07-07 (modo compatibilidad verificado en vivo; evidencias en docs/qa/INFRA/)
Cierre: `faf772f` + `aa10d2b`. Vigilancia: re-chequeo interino 07-07 11:18 — `*/15` INTACTO y
las 4 syncs disparando (señal fuerte). Chequeo formal de 24h: 08-07 al abrir sesión de Max-1
(`crontab -l` textual); si el reescritor tocó `*/15` → escalar a soporte HostGator con el
borrador ya redactado en la evidencia I-01.

### I-01 (histórico) · Scheduler revertido a `*/20` (regresión de P-S0-07) — FIX APLICADO, en verificación
Detectada 02-07 por Max-2 (evidencia `docs/qa/INFRA/2026-07-04--INFRA--cron-queue-work-m15.md`,
rama M15). Timeline afinada con el baseline del corrector: el fix de P-S0-07 operó toda la
mañana (`sync-stock` corrió 07:54/08:54/09:54 hora log) y la reversión a `*/20` ocurrió entre
~09:54 y ~10:50 hora log — cambio NO registrado, cPanel sin historial de ediciones de cron
(verificado). **CAUSA RAÍZ IDENTIFICADA 07-07** (Max-1 + date-check del dueño): el crontab fue reescrito por
TERCERA vez (~03-07 06:45 CDT: `schedule:run` → `*/19`, `queue:work` → `*/15`). Patrón de las 3
reescrituras: intervalos ≥15 min → **automatización de HostGator que estrangula crons por-minuto
en plan compartido** (hipótesis fuerte). Consecuencia actual: clients/prices/stock NO corren desde
el 03-07 (con `*/19` solo el minuto :00 coincide con una sync). **DOCTRINA NUEVA — modo
compatibilidad (dictada 07-07):** NO reponer `* * * * *` (churn perdido); crontab a `*/15`
ALINEADO (0,15,30,45) + re-agendar las tareas de `routes/console.php` a esa grilla (catalog :00,
clients :15, prices :30, stock :45) + delegación a IA-cPanel/soporte HostGator para confirmar la
política (¿mínimo real?, ¿plan superior lo permite?). M15 sobrevive: reintentador ya es robusto a
scheduler degradado (diseño validado); notificaciones con latencia ≤15 min interina. El GATE
pre-P-M15-09 se REDEFINE: basta el modo compatibilidad aplicado y verificado (no volver a
por-minuto). Evidencias (3 reescrituras + date-check con tail mostrando HTML en laravel.log — ojo
lateral: algo volcó una página HTML de error al log, revisar al archivar) → docs/qa/INFRA/.

## Reglas del tablero

1. Solo el DIRECTOR edita este archivo. Las demás cuentas leen su columna y entregan el parte
   de cierre (formato en `FLOTA.md` §5).
2. Una tarea pasa a `[x]` únicamente con evidencia (commit/archivo/texto) revisada por el Director.
3. Si una cuenta agota su ventana de 5h a mitad de tarea: parte de cierre PARCIAL + el Director
   reasigna o reprograma (por eso las tallas L/XL viven en las cuentas Max).
4. Los merges a `main` de la rama M15 NO ocurren en estos 3 días (siguen el plan de E1).
5. **Directiva del dueño (02-07):** los días del tablero son referencia, no camisa de fuerza —
   lo que importa es completar las tareas EN ORDEN dentro de cada columna. Si una cuenta
   termina su día, avanza a la siguiente tarea de su columna sin esperar la fecha.
