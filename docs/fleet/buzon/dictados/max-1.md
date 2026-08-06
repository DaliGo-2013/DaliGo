# Dictado vigente — Max-1 (Forjador A, stream 1)
> Emitido por el Director el 2026-08-06 (v37 — F1 DE M04 EN PRODUCCIÓN; en pausa hasta el próximo GO). Manda sobre lo anterior.

MODELO: Opus 4.8 · high.

## ✅ F1 de PLAN-M04 está EN PRODUCCIÓN (merge `276a54f`, doble llave 06-ago)

Verificación del Director sobre el árbol unión: **suite 1614 verdes / 11.676 aserciones,
cero rojos** — cuadre EXACTO con tu cifra de cierre (tu rama 1612 + los 2 tests del visor
de Marcos que tu base no traía). Bundle por reconstrucción: tu `ms-1.5` presente; las 7
clases que «desaparecen» respecto a tu bundle son huérfanas reales del refactor del visor
(0 usos en el árbol unión — el drop de Tailwind es correcto, tu build también lo era en tu
base). Deploy y Tests de CI verdes. Rama borrada tras ancestría.

**M04 se destrabó tras 24 días pospuesto, y tu lote lo hizo el mismo día en que D-003
aterrizó.** El ciclo de la factura tocó el **50 % justo** — por primera vez con avance en
su INICIO. Tus 2 guardas extra del sweep (devoluciones, traslados ST) son exactamente el
espíritu del paso bien leído: «destroy jamás da 500». Y el cuadre de tu baseline fijada
ANTES de empezar contra mi corrida de la unión es el estándar de cómo se despacha un parte
con la suite en curso sin mentir: árbol intacto + cifra prometida + cifra entregada.

## ⏸️ EN PAUSA — sin lote activo

El próximo GO depende de insumos del dueño:
- **F2 de PLAN-M04 (wizard de baja + orden de traslado)**: puede arrancar sin la ronda 2
  (el wizard no depende de QUÉ bodega muere), pero el dueño decide si va ya o espera.
- Las **5 [B] de D-003** cierran con la ronda 2 de Luis (xlsx v2 en su poder) — cuando
  vuelvan, el cierre es un ajuste de datos vía UI (clasificar), no código tuyo.
- Tus hallazgos del radar quedaron registrados: la colisión de numeración la absorbiste
  bien (nota fechada en §10); `devolucion_bodega_reingreso` texto libre + los 3
  `nullOnDelete` silenciosos quedan para F2/F3.

Si abres sesión y este dictado sigue en v37: revisa el buzón por si hay v38, y si no lo
hay, cierra sesión sin gastar ventana.

## Recordatorios
Baseline HOY: **1614 / 11.676** en main `276a54f`. Las reglas de siempre siguen (suite
completa, superset, `git checkout origin/main --`, parte al buzón).

CIERRE: nada pendiente de tu lado.
