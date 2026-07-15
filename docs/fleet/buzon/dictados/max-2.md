# Dictado vigente — Max-2 (Forjador B, stream 2)
> Emitido por el Director el 2026-07-15 (v8 — DÍA DE QA GUIADO con el dueño: campanita + aprobaciones, TODOS los flujos). Manda sobre lo anterior.

MODELO: Opus 4.8 · high (QA guiado + fixes S — no quemar Fable en esto).

## DIRECTIVA DEL DUEÑO (15-07, miércoles): hoy NO avanzas P-DSP-04.
El día completo (hasta las 16:30) es **QA exhaustivo de M15 campanita + M14 aprobaciones**,
contigo de COPILOTO del dueño: él maneja el teléfono/navegador, tú guías flujo por flujo,
predices el resultado esperado ANTES de cada acción, verificas contra código/tests cuando
algo se vea raro, y llevas el acta. Objetivo doble: (a) que el dueño ENTIENDA cada flujo,
(b) cazar bugs/asperezas reales de uso. P-DSP-04 se retoma mañana.

## Preparación (antes de que el dueño toque nada)
1. `git fetch` + main fresco. OJO: el tweak campanita-móvil de Max-1 debe estar mergeado
   ANTES del QA móvil (el Director lo mergea apenas Max-1 refresque su rama — verifica en
   main que `navigation.blade.php` traiga la campana en cabecera móvil; si no está, empieza
   por los flujos de escritorio y deja lo móvil para después).
2. Verifica en Configuración (staging) los parámetros: `umbral_ajuste_produccion_unidades`
   (50) y `aprobacion_escala_minutos` (30). Para probar escalamiento SIN esperar 30 min,
   propón al dueño bajarlo temporalmente (es parámetro global editable) — Y DEVOLVERLO al
   final (anótalo en el acta como cambio revertido).
3. Cuentas necesarias: un admin (el dueño) + un jefe_bodega real o de prueba. Recuerda el
   gotcha: login exige dominio @impdali.cl.
4. Ten a mano la grilla `*/15`: correos/colas procesan en :00/:15/:30/:45 — latencia ≤15
   min es NORMAL, no bug. Adviérteselo al dueño antes del primer correo.

## LA MATRIZ (guíalo en este orden; por flujo: predicción → acción → veredicto)

### Bloque A — M14 Aprobaciones (núcleo)
A1. Ajuste Δ<50 por jefe_bodega → se aplica AL TIRO, sin solicitud ni campanita (umbral).
A2. Ajuste Δ≥50 por jefe_bodega → flash «pendiente de aprobación», reporte SIN cambios,
    solicitud visible en «Mis solicitudes» del jefe.
A3. Como admin en el TELÉFONO: campanita con badge + correo → Aprobaciones → tarjeta con
    Δ y motivo → Aprobar (1 tap) → «aprobada y aplicada» → reporte con valores nuevos →
    historial /admin/aprobaciones + /admin/auditoria.
A4. Nueva solicitud Δ≥50 → RECHAZAR desde el teléfono → reporte intacto + el jefe recibe
    notificación del rechazo + historial la muestra Rechazada.
A5. Ajuste Δ≥50 hecho POR el admin → se auto-aprueba al tiro (regla: quien aprueba no se
    auto-bloquea). Verificar que el historial igual lo registra.
A6. Payload obsoleto: solicitud Δ≥50 pendiente → ANTES de aprobar, otro cambio al mismo
    reporte (ej. el jefe lo ajusta de nuevo con Δ<50) → al aprobar la vieja → rechazo
    automático por obsoleta (el estado del reporte cambió). Predícelo ANTES con el código.
A7. Doble-tap en Aprobar (el dueño toca 2 veces rápido) → una sola aplicación, sin error
    feo (lock + idempotencia).
A8. Escalamiento: con `aprobacion_escala_minutos` bajado, solicitud pendiente sin respuesta
    → escala según la regla (verificar QUÉ hace: notificación extra / cambio de nivel) en
    la siguiente corrida de la grilla. Revertir el parámetro al final.

### Bloque B — M15 Campanita/Notificaciones
B1. Campana móvil: visible en cabecera con badge (post-merge tweak), tap → bandeja personal.
B2. Bandeja: marcar UNA leída (badge baja), «marcar todas» (badge a 0).
B3. Dropdown desktop: últimas 5 + contador + ver todas.
B4. Preferencias de canal: desactivar mail para un evento → la acción NO manda correo pero
    SÍ campanita; reactivar.
B5. Correo real: llega a inbox (no spam) — SPF/DKIM ya validados en P-M15-10, confirmar que
    sigue OK. Latencia grilla ≤15 min es normal.
B6. Panel admin /admin/notificaciones: fila de cada envío, correo destino visible
    («nombre · correo», tu propio microbacklog), error SMTP ÍNTEGRO expandible en una
    fallida (si no hay fallidas reales, próximo ítem).
B7. Fallida + reintento: si existe alguna fallida terminal, botón reintentar → re-agendada
    → corre en la grilla. (Si no hay ninguna, documenta «sin caso real» — NO fabriques
    rompiendo config en prod.)

### Protocolo de hallazgos
- Aspereza UX o bug: anótalo en el acta con flujo, esperado vs observado, y captura.
- Fix S obvio y seguro (texto, label, redirect): rama `fix/qa-<tema>` + test si aplica →
  parte → el Director mergea con doble llave al final del día (lote).
- Bug M/L o de diseño: NO lo arregles hoy — ficha al acta, el Director prioriza.
- NADA de tocar datos destructivamente en prod: los reportes de prueba se crean, no se
  borran los reales. El parámetro de escalamiento se revierte.

### Acta (entregable del día)
`docs/fleet/buzon/partes/2026-07-15--max-2--acta-qa-m14-m15.md`: tabla flujo × veredicto
(OK / OK con aspereza / BUG) + hallazgos + cambios revertidos + /usage. El Director la
convierte en evidencia de cierre de E2·M14 (P-M14-07) si el bloque A sale bien.

CIERRE: acta al buzón + push (aunque el día quede a medias — parte parcial).
