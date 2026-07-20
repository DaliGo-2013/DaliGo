# Dictado vigente — Max-1 (Forjador A, stream 1)
> Emitido por el Director el 2026-07-20 (v20 — fix calendario AUTORIZADO por el dueño como excepción de territorio + P-TZ-02 render). Manda sobre lo anterior (v19 queda absorbido acá).

MODELO: Opus 4.8 · high.

## Contexto vigente (sin cambios desde v19)
P-TZ-01 EN PRODUCCIÓN (`293d0aa`+`470a341`, suite ×3 663/666/675, frontera nocturna 7/7).
El turno noche ya ve su día. Detalle completo en el tablero y el dictado v19 (historia git).

## 🟢 TAREA 1 — fix calendario de agenda (S, ~15 min) · EXCEPCIÓN DE TERRITORIO AUTORIZADA
**El dueño te autorizó a tocar territorio Marcos** (decisión 20-07, precedente I-06: excepción
puntual, quirúrgica, solo los sitios listados). Motivo: el calendario que Marcos mergeó hoy
(`995ee28`) nació DESPUÉS del inventario del plan y usa fechas UTC — con P-TZ-01 en prod quedó
la ÚNICA superficie inconsistente: de noche chilena resalta MAÑANA mientras el resto de la app
dice hoy.

Rama nueva `fix/tz-calendario-agenda` desde main fresco. Sitios EXACTOS (y NADA más):
- `AgendaTrabajoController::calendario`: `now()->year` / `now()->month` (defaults año/mes)
  → `\App\Support\FechaNegocio::ahora()->year/->month`; el día seleccionado default
  (`now()->startOfDay()` cuando el mes visible es el actual) → derivarlo de
  `FechaNegocio::hoy()`.
- `resources/views/admin/agenda-terreno/calendario.blade.php:165`: `$d->isToday()` →
  `\App\Support\FechaNegocio::esHoy($d)` (mismo patrón FQCN greppable que usaste en P-TZ-01).
- Test de frontera: caso nuevo en `FechaNegocioTest` — freeze 23:00 Chile → el calendario
  abre el MES/DÍA chileno y resalta el día chileno, no mañana.
- NO toques nada más del calendario (lógica de grupos, horas, rutas: intactas — es de Marcos).
Blade tocado → main fresco + `npm run build` + grep superset. Marcos sigue activo: si al ir a
mergear main avanzó, re-verifica el bundle como siempre.

## 🟢 TAREA 2 — P-TZ-02 capa RENDER (S), rama nueva `fix/tz-render` desde main fresco
Igual que el v19:
- Macro central `Carbon::macro('enChile')` → `->tz(config('daligo.tz_negocio'))`, registrado
  en `AppServiceProvider::boot()` (otro lugar = justificar en el parte).
- Los **8 formatos absolutos de §1b del plan**: bandeja admin de notificaciones, historial de
  aprobaciones (admin y «Mis solicitudes»), auditoría, tandas (mi-reporte y reporte del jefe)
  + `now()->format` del payload de prueba de `NotificacionController`.
- Los **4 `diffForHumans` NO SE TOCAN**.
- Tests §4.3-4: conversión conocida (UTC 15:45 → 11:45 invierno), DST (verano → -3),
  relativos intactos.

Orden: Tarea 1 PRIMERO (cierra la inconsistencia visible hoy), Tarea 2 después. Ramas
SEPARADAS — la excepción de territorio no debe viajar mezclada con render. Un parte por lote
(o uno con ambos si cierras los dos en la sesión) → doble llave por rama.

Recordatorios duros: suite COMPLETA por commit; asertar por ruta/marcador; parte al buzón.

## Pendientes que NO son tuyos
- #6 chips paramétricos: el Director lo dimensiona con el dueño.
- P-TZ-03 (QA de borde): lo corre el dueño ~21:30 Chile.

CIERRE por lote: parte a docs/fleet/buzon/partes/ + push.
