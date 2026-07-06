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

### [2026-07-06] SMTP en prod + formulario del QR: código del producto (buscador de catálogo) y fecha de hoy
- **Quién:** Marco + Claude (Opus 4.8) + IA de cPanel
- **Objetivo declarado:** dejar operativo el correo del piloto P-M12-01 e iterar el formulario con feedback de prueba en vivo.
- **Qué se hizo:**
  - **SMTP configurado en producción** (delegación a IA-cPanel, veredicto APROBADO CON OBSERVACIONES): cuenta `servicio@impdali.cl`, `.env` con `MAIL_MAILER=smtp` / `mail.impdali.cl:465` / `smtps` / from `servicio@impdali.cl`; `config:cache`; prueba de envío `ENVIADO_SIN_ERROR`. El correo del QR ya se entrega de verdad. **Pendiente:** rotar la clave (quedó en `~/.bash_history` durante el setup) + prueba de entregabilidad a un dominio externo.
  - **Formulario público del QR** (rama `feature/qr-form-producto-fecha`): campo **"Código del equipo (producto Dali)"** con autocompletado del catálogo (SKU/nombre) reusando `<x-buscador-remoto>`; endpoint público nuevo `ingreso-taller/buscar-producto` (sin auth, `throttle:30,1` por ser autocompletado; solo lee SKU/nombre). `producto_id` validado (`exists`) y guardado, y ahora sale en el correo. Campo **"Fecha de ingreso" = hoy** (solo lectura; el servidor sigue forzando la fecha).
  - 5 tests nuevos (búsqueda pública, mínimo 2 caracteres, guarda `producto_id`, rechaza producto inexistente, render de código+fecha) → **396 verdes**. `view:clear` + build (CSS 43 kB).
- **Pasos marcados:** ninguno (P-M12-01 sigue [EN CURSO]). · **Decisiones:** ninguna. · **Delegaciones:** SMTP (IA-cPanel) — reporte APROBADO CON OBSERVACIONES.
- **Próximo paso:** rotar la clave del correo (prompt entregado) + QA real end-to-end (QR → enviar con un Gmail → confirmar → recibir el correo, ver si cae en Recibidos o Spam).

### [2026-07-06] Ingreso a servicio técnico por QR (piloto de P-M12-01) + historial compartido con separación por sucursal
- **Quién:** Marco + Claude (Opus 4.8)
- **Objetivo declarado:** adelantar **P-M12-01** como piloto — que un cliente ingrese su máquina al taller escaneando un QR del mostrador, **sin crearse usuario**, y reciba el folio por correo.
- **Qué se hizo:** (rama `feature/m12-ingreso-qr-piloto`, **sin mergear aún** — no se puede marcar `[x]` sin QA staging)
  - **Flujo público por QR:** ruta sin auth `ingreso-taller` con link **firmado** (`URL::signedRoute`, sucursal embebida) + `throttle:6,1` + honeypot; `App\Http\Controllers\Publico\IngresoTallerPublicoController` (create/store/gracias); vistas `publico/taller/*` sobre `<x-guest-layout>` (mobile-first). El envío crea una orden **real** (`fuente='qr'`, `estado='recibido'`, `confirmada_at=null`) — NO un pre-ingreso aparte.
  - **Confirmación del encargado:** `ServicioTecnicoController::confirmar` (permiso `manage`, `lockForUpdate` anti doble-envío) setea `confirmada_at` y AHÍ dispara el correo. Bloque "Por confirmar (QR)" + auto-refresco liviano en el índice; botón en el detalle.
  - **Correo piloto standalone:** `App\Mail\IngresoTallerRecibido` + `emails/taller/recibido` (mailer nativo `config/mail.php`). Migrable al motor **M15** (evento `taller.recibido`) cuando llegue a main.
  - **QR imprimible:** página admin `servicio-tecnico/qr` (un QR firmado por sucursal), dibujado en el cliente con `qrcode` (npm) vía **import dinámico** → chunk aparte, no engorda el bundle global; assets `public/build` commiteados.
  - **Historial compartido + separación por sucursal de recepción** (pedido del dueño): el listado ya era compartido por las 3 sucursales; se agregó **filtro "Sucursal (recepción)"** + badge "Recibido en X" por fila; en el detalle se rotula **"Se repara en Mirador (casa matriz)"** cuando la recepción NO fue central (Coquimbo/Abate reciben pero no reparan). Derivado de `es_central`, **sin campo nuevo**.
  - **Migración** `…140000_add_email_y_confirmacion_a_ordenes_servicio` (idempotente): `cliente_email`, `confirmada_at`.
  - **Tests:** 14 del flujo público (`IngresoTallerPublicoTest`) + 3 del módulo (filtro por sucursal; detalle repara-en-Mirador) → **391 verdes**.
- **Pasos marcados:** ninguno `[x]`; **P-M12-01 [EN CURSO]** (piloto en rama; falta QA staging + merge por el gate). · **Decisiones:** ninguna (regla Mirador-casa-matriz la confirmó el dueño; correo standalone-vs-M15 = decisión de implementación del piloto). · **Delegaciones:** ninguna.
- **Próximo paso:** QA en staging del flujo QR (escanear → enviar → confirmar → correo real con SMTP) + gate `/pre-merge` antes de mergear a main; luego migrar el correo a M15.

### [2026-07-02] Recetario de prompts oficial adaptado + 3 skills de flota (P-S0-18)
- **Quién:** Mauricio + Claude (cuenta original/casa)
- **Objetivo declarado:** evaluar la [biblioteca oficial de prompts de Claude Code](https://code.claude.com/docs/en/prompt-library) y sincronizar el repo local (18 commits detrás).
- **Qué se hizo:**
  - Pull fast-forward limpio de los 18 commits de la flota; entorno local migrado y suite verificada (**372 verdes**).
  - Análisis de la biblioteca oficial (48 prompts, 5 fases SDLC): veredicto SÍ adaptable — ~22 llenan vacíos reales del flujo; el resto duplica el sistema documental o no aplica a HostGator.
  - **`docs/delegacion/RECETARIO-PROMPTS.md`** creado: 24 fichas R-01…R-71 en español, organizadas por momento del flujo y por rol de la flota, con slots, ejemplos DaliGo reales, gotchas caros horneados (R-31) y veredictos unificados con PROTOCOLO-DELEGACION.
  - **3 skills delgadas** en `.claude/skills/` que viajan a las 6 cuentas vía git pull: `/arranque` (PROTOCOLO-SESION §1), `/cierre` (checklist §3), `/pre-merge` (auditoría R-31). Regla anti-drift: la skill solo referencia el doc canónico. Regla de graduación en R-71: un prompt se vuelve skill cuando 2+ cuentas lo usan semanalmente.
  - Integraciones: fila en el mapa del README, nota en PROTOCOLO-DELEGACION §4 (plantillas=IA externa vs recetario=flota), referencia en ambos KICKOFF-*.
- **Pasos marcados:** P-S0-18 [x]. · **Decisiones:** ninguna. · **Delegaciones:** ninguna.
- **Próximo paso:** los del panel §0 (streams de la flota siguen su tablero; Mauricio: P-S0-03/04 briefs).

### [2026-07-02] P-SPK-02 hecho: cola offline de tandas (el soplador registra sin señal)
- **Quién:** Mauricio + Claude (Opus 4.8, Max-1/stream 1) — dictado del Director (Opus 4.8·xhigh)
- **Objetivo declarado:** P-SPK-02 (día 2 del tablero) — cola IndexedDB con idempotencia.
- **Qué se hizo:** migración `cliente_uuid` + unique compuesto `[reporte_id, cliente_uuid]`; `registroStore` idempotente dentro del lock + respuesta JSON (ValidationException para 422 reales); `resources/js/offline-queue.js` (encolar/drenar con CSRF fresco del meta, clasificación transitorio/permanente, guard de reentrada, reload al reconciliar); integración en el form del soplador (encola si offline, feedback optimista, contador de pendientes). Diseño validado adversarialmente ANTES de codear (3 bloqueantes: unique compuesto, CSRF sin serializar, clasificación de errores). 4 tests de idempotencia → 372 verdes. Verificado E2E en preview: 2 tandas offline → IndexedDB (sin `_token`) → online → drenan → 2 registros reales sin duplicar. Las decisiones quedaron en la bitácora de CLAUDE.md.
- **Pasos marcados:** P-SPK-02 [x]. · **Decisiones:** ninguna. · **Delegaciones:** ninguna.
- **Próximo paso (Max-1):** P-SPK-03 — prueba de campo real (modo avión, matar app a mitad de cola) + memo `docs/SPIKE-PWA.md` con la arquitectura elegida para M08.

### [2026-07-02] P-SPK-01 hecho: mi-reporte es instalable (spike PWA, día 1 del tablero de flota)
- **Quién:** Mauricio + Claude (Opus, Max-1/stream 1)
- **Objetivo declarado:** P-SPK-01 (tarea Día 1 del tablero — mayor riesgo técnico del proyecto, adelantado de W27 a W12)
- **Qué se hizo:** manifest.json (scope `/`, start_url mi-reporte, iconos 192/512+maskable generados con GD), `public/sw.js` conservador (assets `/build/*` cache-first inmutables; navegaciones network-first con fallback `/offline` SOLO en catch; passthrough sin respondWith para el resto; jamás HTML autenticado), ruta+vista `/offline` standalone, `Alpine.store('red')` con confirmación vía `/up` y registro del SW con guard de localhost, componente `<x-produccion.indicador-red>` en las 2 vistas del soplador. Diseño validado adversarialmente ANTES de implementar (3 bloqueantes corregidos en papel: opaqueredirect, scope, passthrough). 4 tests nuevos → 368 verdes. Verificado en preview: SW activo, caches pobladas, indicador reactivo, login intacto; navegador de dev limpiado (unregister). Las 5 reglas del SW quedaron en la bitácora de CLAUDE.md como contrato para P-SPK-02/M08.
- **Pasos marcados:** P-SPK-01 [x]. · **Decisiones:** ninguna. · **Delegaciones:** ninguna.
- **Próximo paso:** Mauricio instala en su celular desde staging (criterio "celular real"); mañana P-SPK-02 (cola IndexedDB offline con idempotencia UUID — sin Background Sync por iOS: vaciar en `online`).

### [2026-07-02] Se constituye la FLOTA: 6 cuentas Claude orquestadas + tablero de 3 días
- **Quién:** Mauricio + Claude (Opus, stream 1)
- **Objetivo declarado:** P-S0-17 — organizar 2 cuentas Max + 4 Pro con roles, dictado de modelo/esfuerzo y control de consumo.
- **Qué se hizo:** investigación del estado Anthropic a hoy (modelos Fable 5/Opus 4.8/Sonnet 5/Haiku 4.5; esfuerzo low→max; `/fast`; límites por plan NO públicos → medición empírica vía `/usage`). Diseño: Max-1 forjador stream 1 (spike PWA), Max-2 forjador stream 2 (E1·M15, ya operando), Pro-1 DIRECTOR (tablero/verificación/ledger, escribe solo `docs/fleet/`), Pro-2 AUDITOR/QA (read-only), Pro-3 INVESTIGADOR (decisiones D-0xx), Pro-4 ESCRIBA (docs aislados). Bus = Mauricio + repo; territorio exclusivo por cuenta; tareas L/XL solo a Max. Archivos: `docs/fleet/{FLOTA,TABLERO-3-DIAS,CONSUMO}.md` + `docs/delegacion/KICKOFF-DIRECTOR.md`.
- **Pasos marcados:** P-S0-17 [x]. · **Decisiones:** ninguna. · **Delegaciones:** 4 prompts de arranque entregados a Mauricio (Director, QA, Investigador, Escriba).
- **Próximo paso:** día 1 del tablero (2026-07-03): Max-1 → P-SPK-01; el Director se constituye y toma el mando del tablero.

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
