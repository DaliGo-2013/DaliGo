# Dictado vigente — Max-1 (Forjador A, stream 1)
> Emitido por el Director el 2026-07-13. Este archivo manda sobre instrucciones anteriores.

MODELO: Fable 5 disponible hasta el 19-07 (decisión del dueño usarlo); si no, Opus 4.8 · high.

VEREDICTO PREVIO: P-M14-04 y P-M14-05 [x] VERIFICADOS por el Director (7a01da2 — escalar con
everyFifteenMinutes()+withoutOverlapping(15) y test de grilla ✓, ajustar() cableado con la
batería completa de 10 tests exacta al plan ✓, gotcha del docblock */15 bien cazado). E2·M14
va 5/7. Tu parte formal de 04/05 nunca llegó — escríbelo al buzón (partes/) junto con el de
esta tarea, con /usage si Mauricio te lo pasa.

TAREA: P-M14-06 · Historial de aprobaciones
- /admin/aprobaciones (permiso 'view aprobaciones'): filtros estado/tipo/solicitante/
  aprobador/rango de fechas (whereDate, NUNCA whereBetween — bitácora) + resumen por
  aprobador/solicitante. Componentes x-* del catálogo, paginación con withQueryString.
- «Mis solicitudes» del lado del solicitante ya existe (P-M14-03) — enlázala desde donde
  corresponda si falta.
- Transiciones visibles en /admin/auditoria (los modelos ya están en MODELOS).
- Responsive 3 anchos, build + grep del bundle, suite verde, RUTA mismo push.

DESPUÉS (no arranques sin dictado nuevo): P-M14-07 = re-sellado de PLAN-M14 (anotar guard
omitido + eventos en 02) + suite completa + gate /pre-merge + MERGE COORDINADO con doble
llave (Director + Mauricio) + QA staging desde celular real.

CIERRE: parte a docs/fleet/buzon/partes/2026-07-13--max-1--p-m14-06.md + push.
