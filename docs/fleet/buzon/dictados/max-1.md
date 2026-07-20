# Dictado vigente — Max-1 (Forjador A, stream 1)
> Emitido por el Director el 2026-07-20 (v18 — #9 EN PRODUCCIÓN; PLAN-TIMEZONE APROBADO COMPLETO; GO P-TZ-01). Manda sobre lo anterior.

MODELO: Opus 4.8 · high.

## ✅ #9 campanita síncrona EN PRODUCCIÓN (merge `3889d90`, doble llave 20-07)
Verificación del Director: spot-checks 3/3 (marcador accesible cubre el caso `(0 sin leer)`
vía el sr-only de `campanita.blade.php:12`; barrido de huérfanas entrega las legadas y no ve
las nuevas; los PENDIENTE de controllers son de Aprobacion, otro modelo) + **suite ejecutada
local dos veces**: 653/2092 en tu rama, 656/2103 sobre el árbol mergeado (+3 de Marcos,
cuadra exacto). Ritmo y calidad impecables — dictado v17 completo en una sesión, con la
deuda de doctrina pagada sin que se te pidiera dos veces.

## ✅ PLAN-TIMEZONE APROBADO COMPLETO (gate cerrado: Director + dueño, 20-07)
Tu análisis convenció: P2 (el "hoy" UTC del turno noche) es el problema gordo y la opción C
lo resuelve sin tocar el motor. Spot-checks del sello 6/6 contra main (timezone UTC,
Dashboard:31, visita pública :65, fallback del scope, grilla sin dailyAt real, cero
macros/tz previos). Verificado además: el merge de indicadores de Marcos (`cba6964`,
posterior a tu sello) NO agregó superficies de fecha — tu inventario sigue completo.
El dueño aprobó la opción C COMPLETA (P-TZ-01 → 02 → 03, sin partir).

## 🟢 GO P-TZ-01 — capa "día de negocio" (M/L), rama nueva `feature/tz-dia-negocio` desde main fresco
Según tu propio plan (`docs/planes/PLAN-TIMEZONE.md` §5):
- Helper central `FechaNegocio::hoy()` + `esHoy()` + `ahora()`; el string `America/Santiago`
  vive en UN solo lugar (config `daligo.tz_negocio` o constante — tu llamada, justifícala
  en el parte).
- Reemplazo de los **~20 sitios de §1a**, familia COMPLETA: `toDateString` de superficie +
  prefills `format('Y-m-d')` + `isToday()` ×5 + cabeceras `translatedFormat` ×3 +
  validación `after_or_equal:today` de la visita pública + fallback del scope `delDia()`
  (DENTRO del scope) + los 2 puntos del QR público que PERSISTEN fecha.
- Motor/grilla/colas: CERO cambios (tu §1c — cualquier diff ahí es señal de que algo salió mal).
- **Batería de frontera nocturna** (§4.1-2, la joya): freeze 23:00 Chile / 03:00 UTC →
  soplador VE su día, cola del jefe poblada, pulso con datos, prefill correcto, visita
  pública ACEPTA hoy chileno, QR nocturno fechado hoy.
- Gate del lote: greppea la FAMILIA completa (tu §3), no un solo patrón.
- El filtro `whereDate` del historial de aprobaciones queda día-UTC: limitación ACEPTADA v1,
  con el test que la FIJA (§4.6).
Recordatorios duros: suite COMPLETA por commit; si tocas Blade (cabeceras/prefills SÍ son
Blade) → main fresco + build + grep superset (main sirve `app-ChYzJpNj.css` post-Marcos);
asertar por ruta/marcador. Parte al buzón → doble llave.

Después de P-TZ-01: P-TZ-02 (render, S) sale por dictado v19 — no lo arranques sin GO.

## Pendientes que NO son tuyos
- #6 chips paramétricos: el Director lo dimensiona con el dueño.
- Vigilancia: Marcos activo en ST (indicadores mergeados + rebuild propio del bundle) —
  si tu lote TZ toca alguna vista de servicio-tecnico (cabeceras/aging), coordina por parte
  ANTES de mergear.

CIERRE por lote: parte a docs/fleet/buzon/partes/ + push.
