# Dictado vigente — Max-2 (Forjador B, stream 2)
> Emitido por el Director el 2026-08-05 (v17 — P-DSP-09 EN PRODUCCIÓN, F2 COMPLETA; en pausa hasta el próximo GO). Manda sobre lo anterior.

MODELO: el que fije el dueño en tu asiento · high.

## ✅ P-DSP-09 está EN PRODUCCIÓN (merge `f3be802`, doble llave 05-ago)

Verificación del Director sobre el árbol unión (main solo había recibido el visor 3D de
Marcos — cero intersección de código con tu rama): **suite 1556 verdes / 11.358 aserciones,
cero rojos** — tus 1553 + 3 del visor, cuadre exacto. Bundle por rebuild: superset CSS
559 ⊇ 558/558, 0 pérdidas. Deploy y Tests de CI verdes. Rama borrada tras ancestría.

**Con esto F2 de PLAN-DESPACHOS-V2 queda COMPLETA** — el conductor ve dirección/comuna/
teléfono, exige receptor, registra cobro y maneja rechazo, todo por la misma cola offline.

Tu hallazgo de que **la idempotencia gana a la validación condicional** quedó verificado
contra el código antes del merge — es exactamente el tipo de defecto que solo un test de
duplicado caza, y el guard está bien puesto (el service re-verifica igual). La micro-decisión
del DV suave del RUT es correcta por la razón que diste: un 422 diferido horas después de la
puerta es un error falso incorregible; el dato duro es la firma.

## ⏸️ EN PAUSA — sin lote activo

**P-DSP-10 (edición de hoja en curso + cierre + bono) está BLOQUEADO por la ronda 2 con
Luis** — el Excel de la fórmula del bono y la rendición del cobro ya viajan en el
cuestionario que el dueño le entregó (6 preguntas, incluida la tuya del bloqueo de salida
con paradas sin escanear y la del rechazo→devolución automática).

Si abres sesión y este dictado sigue en v17: revisa el buzón por si hay v18, y si no lo
hay, cierra sesión sin gastar ventana.

## Recordatorios
Baseline HOY: **1556 / 11.358** en main `f3be802`. Las reglas de siempre siguen (suite
completa, superset, `git checkout origin/main --`, parte al buzón).

CIERRE: nada pendiente de tu lado.
