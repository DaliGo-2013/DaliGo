# Dictado vigente — Max-1 (Forjador A, stream 1)
> Emitido por el Director el 2026-07-20 (v17 — lote S2 EN PRODUCCIÓN; GO fichas M). Manda sobre lo anterior.

MODELO: Opus 4.8 · high.

## ✅ LOTE S2 EN PRODUCCIÓN (merge `d05e410`, doble llave, Deploy+Tests success 20-07 15:17)
Los 3 fixes (#5 filas de la bandeja clickeables al destino, #8 correos ricos con título por
resultado, #9b traza del reporte en auditoría) VIVOS. QA staging del Director: manifest vivo →
`app-CJEXLMRv.css` (200, bytes exactos), `/notificaciones` `/aprobaciones` `/aprobaciones/mias`
`/admin/audits` → 302.

Nota de verificación (para tu calibración): tu "652 verdes" fue **EJECUTADA por el Director en
local** (composer install + suite entera, dos veces: en tu rama y sobre el árbol mergeado) —
exacta. Y tu "superset 14/14" quedó corto SIN culpa tuya: `st-header-prolijo` (Marcos) entró a
main DESPUÉS de tu base, pero sus 15 clases ya estaban en tu bundle; además tu bundle purgó 176
clases que main arrastraba MUERTAS (0/176 usadas en el árbol, verificado token-exacto — basura
de builds anteriores). Purga legítima, cero regresión. Buen lote: la doctrina aplicada desde el
diseño (asserts por ruta, conteo derivado, caída del `required_with` cazada por suite entera) se
notó en la verificación.

## Deuda chica de doctrina (tuya, para tu PRÓXIMO paso por territorio M15 — no abras rama por esto)
`CampanitaTest:97` usa `assertSee('>3<', false)` — markup pegado, el mismo patrón del
verde-engañoso. Cuando vuelvas a tocar campanita/notificaciones: migrar a marcador estable
(aria-label o data-attr del badge). Anótalo, no bloquea nada.

## 🟢 GO fichas M del backlog (`buzon/backlog-hallazgos-qa-15-07.md`), en este orden
1. **#9 (S) — campanita síncrona:** el canal `database` no tiene transporte externo → marcar
   `enviada` SÍNCRONO al despachar (hoy viaja por la cola de la grilla, latencia ≤15 min).
   Rama nueva `fix/campanita-sincrona` desde main fresco. Validar que NO rompa: el badge
   server-rendered, el resto de la cola (mail sigue por cola), y el claim atómico del
   dispatcher. Suite completa + parte.
2. **#2 (M) — timezone UTC: SOLO PLAN, CERO CÓDIGO.** `app.timezone` UTC→America/Santiago es
   delicada: toca el "hoy" de prod (`whereDate`), la grilla `*/15` (¿los slots se corren?),
   los timestamps ya guardados (¿+4h retroactivo o solo render?) y los tests. Entregar
   PLAN-TIMEZONE sellado con: inventario de superficies `whereDate`/`now()`/`diffForHumans`,
   decisión render-vs-storage (recomendación del Director: timezone de RENDER, storage sigue
   UTC — pero pruébame lo contrario si el análisis lo da), matriz de riesgo y batería de
   tests. GATE: visto bueno del Director + dueño ANTES de una línea de código.
3. **#6 chips paramétricos — NO ARRANCAR:** el Director lo dimensiona con el dueño primero.

Recordatorios duros (sin cambios): suite COMPLETA por commit; Blade → main fresco + build +
grep superset (ahora incluye las clases de `st-header-prolijo` que YA está en main, y ojo con
la rama nueva de Marcos `st-industrial-kpis` sin mergear); asertar por ruta/marcador. Parte al
buzón → doble llave.

CIERRE por lote: parte a docs/fleet/buzon/partes/ + push.
