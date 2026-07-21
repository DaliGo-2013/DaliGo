# Dictado vigente — Max-1 (Forjador A, stream 1)
> Emitido por el Director el 2026-07-21 (v23 — PLAN-TIMEZONE 100% en código; housekeeping de cierre). Manda sobre lo anterior.

MODELO: Opus 4.8 · medium (chore S).

## ✅ Tests de frontera EN MAIN (merge `6e40051`, doble llave 21-07, Deploy+Tests success)
Nacieron verdes sobre el `daf948d` de Marcos — la coreografía canal-directo + tests-flota
funcionó: cero colisiones, comportamiento fijado. Suite verificada por el Director 704/2259.

**PLAN-TIMEZONE queda 100% APLICADO EN CÓDIGO**: P1 render (`50f1f61`) · P2 día de negocio
(`293d0aa`) · calendario (Marcos `daf948d`) · hallazgo update():151 (ídem) · frontera
nocturna+mes blindada (`6e40051`). Pendiente solo P-TZ-03 (QA de borde del dueño, no es código).

## 🟢 TAREA — housekeeping de cierre TZ (S, main directo: docs = inocuo)
1. `docs/planes/PLAN-TIMEZONE.md`: estado PROPUESTA → **APLICADO** (cabecera), con los 4
   hashes de arriba y la nota de que el calendario y el hallazgo los aplicó Marcos por canal
   directo (decisión del dueño 21-07 — deja el precedente documentado: en territorio con
   churn activo, el fix viaja por el dueño y la flota fija el comportamiento con tests).
2. `docs/RUTA-MAESTRA.md`: marcar la unidad timezone según convención (con evidencia).
3. Si tocas CLAUDE.md por la doctrina nueva (coreografía anti-churn), una entrada CORTA.
4. Parte al buzón con /usage.

## Después: STANDBY
Sin cola nueva hasta que el dueño dimensione #6 (chips paramétricos) con el Director, o
priorice otra cosa. Si el QA de borde de esta noche (P-TZ-03) arroja hallazgos, vuelven por
dictado. Max-2 sigue en DESPACHOS (P-DSP-04) — no cruces su territorio.

CIERRE: parte a docs/fleet/buzon/partes/ + push.
