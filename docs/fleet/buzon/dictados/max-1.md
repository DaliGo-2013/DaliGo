# Dictado vigente — Max-1 (Forjador A, stream 1)
> Emitido por el Director el 2026-07-20 (v19 — P-TZ-01 EN PRODUCCIÓN; GO P-TZ-02 render). Manda sobre lo anterior.

MODELO: Opus 4.8 · high.

## ✅ P-TZ-01 EN PRODUCCIÓN (merge `293d0aa` + integración `470a341`, doble llave 20-07)
El turno noche chileno YA VE su día. Verificación del Director: suite **ejecutada ×3**
(663/2123 en tu rama · 666/2137 mergeado · 675/2165 tras re-integrar el catálogo de Marcos),
frontera nocturna 7/7, motor con CERO diffs confirmado. Tu bandera de coordinación ST era
CORRECTA y necesaria: Marcos mergeó 5 lotes HOY (header, informes, agenda-calendario, campos
terreno, menú Más, catálogo) — tus 3 Blades compartidos automergearon limpio (líneas
distintas) y su rama de catálogo no cruzaba código. El manifest conflictuó DOS veces durante
mi merge (main avanzó mientras corría la suite) — resuelto con evidencia ambas: tu lote no
agrega clases (6/6 líneas `class=` idénticas), bundle final `DZvq4P_y` superset verificado
(calendario 7/7 + flota 4/4). Tu reloj determinista en TestCase = hallazgo de valor
estructural: mató el flaky de medianoche que existía desde siempre.

Justificación de config vs constante: ACEPTADA (decisión de despliegue, no parámetro de
usuario; config:cache la congela — razonamiento correcto).

## 🟢 GO P-TZ-02 — capa RENDER (S), rama nueva `fix/tz-render` desde main fresco
Según tu plan (`docs/planes/PLAN-TIMEZONE.md` §5, aprobado completo):
- Macro central `Carbon::macro('enChile')` → `->tz(config('daligo.tz_negocio'))` (reusa la
  config de P-TZ-01 — un solo lugar para el string, como ya dejaste).
- Aplicarlo a los **8 formatos absolutos de §1b**: bandeja admin de notificaciones,
  historial de aprobaciones (admin y «Mis solicitudes»), auditoría, tandas de producción
  (mi-reporte y reporte del jefe) + el `now()->format` del payload de prueba de
  `NotificacionController`.
- Los **4 `diffForHumans` NO SE TOCAN** (ya correctos — deltas puros).
- Tests de §4.3-4: macro convierte instante conocido (UTC 15:45 → 11:45 invierno) + respeta
  DST (verano → -3) + relativos intactos.
- Registro del macro: `AppServiceProvider::boot()` (único candidato natural — si eliges otro
  lugar, justifícalo en el parte).
Recordatorios duros: suite COMPLETA por commit; tocas Blades de render → main fresco +
`npm run build` + grep superset (main sirve `app-DZvq4P_y.css`; Marcos sigue MUY activo —
asume que main avanza mientras trabajas); asertar por ruta/marcador. Parte al buzón →
doble llave.

## Pendientes que NO son tuyos (no arranques nada de esto)
- **Gap calendario de Marcos** (detectado por el Director post-inventario): su
  `AgendaTrabajoController::calendario` + `calendario.blade.php` usan `now()->year/month`,
  `startOfDay()` e `isToday()` → de noche chilena el calendario resalta MAÑANA. Misma
  familia que tu helper resuelve en 6 líneas — pero es TERRITORIO MARCOS; el dueño decide
  quién lo toma. Si te lo dictan, es un `fix/` de 15 minutos con tu `FechaNegocio`.
- #6 chips paramétricos: el Director lo dimensiona con el dueño.
- P-TZ-03 (QA de borde): lo corre el dueño ~21:30 Chile — no requiere código tuyo.

CIERRE por lote: parte a docs/fleet/buzon/partes/ + push.
