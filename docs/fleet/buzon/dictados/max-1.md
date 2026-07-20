# Dictado vigente — Max-1 (Forjador A, stream 1)
> Emitido por el Director el 2026-07-20 (v16 — lote UX EN PRODUCCIÓN; GO lote S2). Manda sobre lo anterior.

MODELO: Opus 4.8 · high (fixes S).

## ✅ LOTE UX DEL QA EN PRODUCCIÓN (merge `6befc87`, doble llave, Deploy+Tests success 13:50)
Los 3 fixes (#1 nav «Historial de aprobaciones», #3 motivo+magnitud en «Mis solicitudes»,
#7 diff anterior→nuevo) VIVOS. Buen trabajo resolviendo los 2 bloqueadores + tu precisión al
diagnóstico (el test era verde-engañoso por el chip del zócalo M16, no rojo — me corregiste
bien; mi agente dio falso positivo). Nota de resolución del Director: al mergear, main había
avanzado otra vez (M12 `informes ST`), así que el conflicto de manifest lo resolví al lado de
main con EVIDENCIA (grep de las 6 clases que agrega el lote — todas en el bundle de main → main
superset del árbol mergeado; el lote no agrega clases nuevas). Tu rama cumplió su ciclo.

## DOCTRINA para la bitácora (territorio tuyo, agrégala cuando toques CLAUDE.md)
Un `assertSee('>X<', false)` (markup pegado) puede pasar **por la razón equivocada**: la cadena
`>X<` puede existir pegada en OTRA superficie de la misma página (aquí el chip del zócalo del
dashboard con `>{{ $item['label'] }}<`), no en el elemento que el test cree verificar. Verde
engañoso = peor que rojo. Regla: asertar por RUTA/marcador estable, nunca por forma pegada del HTML.

## 🟢 GO lote S2 — rama NUEVA `fix/qa-aprobaciones-ux2` desde main FRESCO
Del backlog (`buzon/backlog-hallazgos-qa-15-07.md`), en orden de valor:
1. **#5 (alta)** — filas de la bandeja de notificaciones CLICKEABLES al destino por evento:
   `aprobacion.*` → bandeja del aprobador / mis-solicitudes del solicitante. El modelo ya carga
   `notificable`+`payload`. Cierra la regla de bitácora "toda alerta necesita superficie donde
   actuar". Es el de mayor impacto de uso real — cabeza del lote.
2. **#9b (media)** — enlace "ver historial de cambios" desde la ficha del reporte →
   `/admin/auditoria` filtrada por ese reporte. NO duplicar registro: la traza ya existe.
3. **#8 (media)** — plantillas ricas de correo para `aprobacion.*` (motivo/magnitud/reporte +
   link de acceso) vía `notif_plantilla_<evento>` del motor M15; distinguir el TÍTULO por
   resultado (hoy los 3 dicen "Solicitud resuelta").
Recordatorios duros: suite COMPLETA verde por commit (corre `composer test` entero, no un
subset — la lección del verde-engañoso); si tocas Blade → merge de main fresco ANTES + `npm run
build` + grep del bundle superset (flota + M12 seguimiento/informes + lo nuevo); asertar por
ruta/marcador. Parte al buzón → merge doble llave.

Pendiente del dueño (no bloquea): #9c (rojo en Rechazada, decisión de paleta) y la fecha de
arranque del espejo de documentos (despachos).

CIERRE por lote: parte a docs/fleet/buzon/partes/ + push.
