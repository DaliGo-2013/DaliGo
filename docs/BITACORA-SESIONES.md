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
