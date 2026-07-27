# Parte — Max-1 · Lote S2 del QA LISTO (rama `fix/qa-aprobaciones-ux2` @ `af7cd52`)

> De: Max-1 · Para: Director / Mauricio · Dictado v16 ejecutado (los 3 del backlog, en orden).

## Los 3 fixes

1. **#5 (alta) — Notificaciones ACCIONABLES:** `Notificacion::urlDestino()` mapea el evento a
   su superficie (`aprobacion.solicitada/escalada` → bandeja del aprobador;
   `aprobacion.resuelta` → «Mis solicitudes»; resto → null) y la fila de la bandeja personal
   ES el link (con chevron; las no-accionables quedan como texto). Cierra la regla de
   bitácora «toda alerta necesita superficie donde actuar».
2. **#8 — Plantillas ricas:** 3 plantillas sembradas (`notif_plantilla_aprobacion_{solicitada,
   escalada,resuelta}`) vía el motor M15 — asunto que DISTINGUE el resultado
   («Aprobada:/Rechazada: {descripcion}») + cuerpo con solicitante/motivo/magnitud + **link
   directo** ({url} según destinatario: bandeja para el aprobador, mias para el solicitante).
   `datosNotificacion` ganó `magnitud` (siempre string — el render filtra no-escalares) y
   `resultado` legible; cambios 100% aditivos al servicio.
3. **#9b — Traza del reporte:** enlace «Ver historial de cambios» en la ficha (solo
   `@can('view audit')` — sin 403 invitado) → `/admin/auditoria` con filtro nuevo
   `auditable_id` (solo se aplica junto al type; el filtro por solo-tipo del select existente
   sigue intacto). NO se duplicó registro: la traza ya existía.

## Verificación (con una lección aplicada y una caída propia cazada)

- **Suite COMPLETA: 652 verdes (2.087 aserciones), exit 0.** +8 tests nuevos
  (`AprobacionAccionableTest`: mapping de destinos, fila accionable asertada donde NINGUNA
  otra superficie puede satisfacer la cadena — doctrina del verde-engañoso aplicada desde el
  diseño —, títulos por resultado, idempotencia de plantillas, enlace por permiso, filtro por
  registro).
- **Caída propia cazada por la suite completa:** mi primer intento validaba `auditable_id`
  con `required_with:auditable_type` → rompía el filtro EXISTENTE por solo-tipo (3 tests
  viejos en 302). Corregido: el id es opcional y solo se APLICA junto al type. La regla
  «suite entera, no subset» pagó de inmediato.
- **Territorio M15 (transparencia):** `NotificacionConfigSeedTest` fijaba el conteo del grupo
  notificaciones en 4 (hardcodeado) — mis 3 plantillas lo rompían. Aplicado el patrón YA
  aprobado por el Director en el precedente `PreferenciasCanalTest` (bitácora 2026-07-13):
  conteo derivado de la 1ª corrida — la intención del test (idempotencia) queda intacta.
- **Bitácora:** doctrina del **verde-engañoso** agregada a CLAUDE.md (encargo del dictado),
  con el gotcha hermano del `sr-only` del 14-07 y la técnica de mutación para detectarlos.
- Bundle `app-CJEXLMRv.css`, **grep superset 14/14** (flota + M12 seguimiento). Preview 375:
  bandeja sin overflow.

**Para la doble llave:** rama nacida de main fresco (`3dad302`), diff = 6 archivos de código/
seeds + 2 de tests + CLAUDE.md + bundle. Tras el merge, del backlog quedan las fichas M
(timezone UTC, chips paramétricos del dueño, canal database síncrono).

— Max-1
