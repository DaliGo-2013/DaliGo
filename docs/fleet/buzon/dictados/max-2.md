# Dictado vigente — Max-2 (Forjador B, stream 2)
> Emitido por el Director el 2026-07-20 (v9 — el día de QA lo corrió Max-1; GO P-DSP-04). Manda sobre lo anterior.

MODELO: Fable 5 se justifica en P-DSP-04 (QR anti-fraude, diseño de seguridad) si el dueño lo fija; si no, Opus 4.8 · high.

## Contexto: el día de QA (v8) YA no es tuyo
El QA guiado de M14/M15 (matriz A1-A8/B1-B7) lo corrió **Max-1** por directiva directa del
dueño en su sesión — 15/15 flujos, **E2·M14 CERRADA**, acta archivada. No lo repitas. Tu foco
vuelve a DESPACHOS.

## ✅ DECISIÓN DEL DUEÑO 20-07: fecha de arranque del espejo = DEFAULT 7 días
`documentos_sync_desde` queda SIN fijar → `DocumentSync` arranca el espejo virgen con los
últimos 7 días al primer run (es tu `DIAS_DEFAULT`). **No requiere código ni seed** — ya es el
comportamiento por defecto. Punto cerrado; no lo toques.

## 🟢 GO P-DSP-04 — QR anti-fraude de retiro (M07, el corazón de la unidad)
Según PLAN-DESPACHOS-V1 §2, con estos énfasis (ya verificados como diseño en P-DSP-03):
1. `validarRetiro` bajo `lockForUpdate` + re-check del estado con la fila bloqueada. TODO
   escaneo deja fila en `escaneos_despacho` (valido / doble_retiro / estado_invalido). El 2º
   escaneo dispara ALERTA visible y NO cambia estado. Tests del lock + de la carrera (doble-tap
   del operador de bodega — patrón bitácora [2026-06-30]).
2. QR firmado `URL::signedRoute` sobre el `codigo DSP-` (no el id — no enumerable), reusa el
   patrón M12 (`dibujarQrsMostrador`, chunk qrcode ya en bundle) + página imprimible.
3. Superficie de escaneo: si es puesto de bodega autenticado → `manage despachos`; si semi-
   pública → `signed` + throttle. Decide por el flujo real y documéntalo.
4. Cola "McDonald's": polling JSON liviano patrón `porConfirmarConteo()` de ST; pantalla apta
   para monitor de bodega (texto grande, estados por relleno — paleta de 4, rojo solo
   destructivo, YA confirmado por el dueño).
5. Entrega total/parcial: parcial marca `entrega_parcial` y el saldo queda visible.
6. Recordatorio del requisito que salió de tu propia review P-DSP-03: `crearDesdeDocumento`
   re-verifica el doc contra Bsale antes de despachar (el `cancellation_status` local puede
   estar stale >1 día). Ese guard ya está; P-DSP-04 se apoya en él, no lo dupliques.

Recordatorios duros de flota (lecciones frescas):
- Suite COMPLETA verde por commit — corre `composer test` ENTERO, no un subset (lección del
  "verde engañoso": un assert puede pasar por la razón equivocada).
- Blade/JS → merge de main FRESCO ANTES + `npm run build` + grep del bundle superset. Main se
  mueve rápido (M12 empuja seguido: seguimiento, instalaciones, informes ST); tu rama
  `feature/despachos-v1` acumula P-DSP-00..03 sin mergear — refréscala seguido para que el
  merge final (P-DSP-07) no sea un choque de bundles.
- Asertar por RUTA/marcador estable, nunca por forma pegada del HTML.

CIERRE por paso: parte a docs/fleet/buzon/partes/ + push.
