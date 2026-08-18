# Dictado vigente — Max-1 (Forjador A, stream 1)
> Emitido por el Director el 2026-08-18 (v63 — D1 EN PRODUCCIÓN: Bloque D COMPLETO. EN PAUSA hasta el QA del dueño → luego E1, el CIERRE). Manda sobre lo anterior.

MODELO: Opus 4.8 · high.

## ✅ D1 está EN PRODUCCIÓN (merge `61dd90d`, doble llave 18-ago) — menú 36 → 35

Suite del Director sobre el árbol mergeado: **2196 verdes / 15.231, CERO rojos** (calcada
al parte). Conté el menú (35), da. Rama borrada. **Bloque D COMPLETO.**

Lo fino de D1, para el molde de E1:
1. **El trato de P-NAV-06 quedó de manual**: la ruta salió del candado con el rastro de
   sus tres vidas Y volvió a `test_pantalla_hija_tiene_exactamente_un_volver` en el
   MISMO commit — el Volver restaurado no pasó ni un push sin candado. Ese es el molde
   exacto para Máquinas y Tipos de botellón en E1.
2. **«Ítem retirado no es motivo para volver al comodín»** — la lista explícita del
   `activo` se respetó y el comentario lo deja dicho. Doctrina citable.
3. Baseline corrida a `ec06a48` con el PR #19 absorbido y declarado — así se maneja el
   drift de Marcos.

## ⏸️ EN PAUSA — E1 espera el QA del dueño del Bloque D (corto)

El dueño verifica en celular: Producción resalta estando en el Kardex, el Volver del
Kardex lleva al panel, la sidebar sin el ítem, el botón «Kardex» de la cabecera como
entrada. Con su visto bueno llega el **v64 con E1 — el CIERRE del mapa**.

## 📡 Radar E1 (NO arranques — estúdialo, es el lote mayor)

**«Configuración de producción» 4→1 (35 → 32)**: Máquinas · Tipos de botellón · Recetas ·
Moldes → una superficie. El reconocimiento del Director (verificado hoy en MenuPrincipal
L90-95): los 4 ítems portan `manage production` — **permiso idéntico por construcción,
las 4 pestañas SIN gateo** (precondición B1, la más limpia posible para el lote más
grande).

Lo que E1 trae que ningún lote trajo:
1. **La deuda del `<x-tab-nav>` se paga AQUÍ**: hoy `count($tabs)===3 ? 'grid-cols-3' :
   'grid-cols-2'` — con 4 pestañas caería a 2 columnas EN SILENCIO. Extiende el
   componente a 4 (`grid-cols-4`) con la forma que el componente pida (mapa
   count→clase, no un ternario anidado); Tailwind debe tener la clase en el bundle —
   si `grid-cols-4` no está en uso en ninguna vista, el bundle CAMBIA y deja de ser
   byte-idéntico: **decláralo y recompila sobre el árbol del lote** (superset vs padres,
   receta I-06). Candado del componente: con 4 pestañas, las 4 en una fila.
2. **Anfitrión NUEVO**: ninguna de las 4 es anfitriona natural (ninguna pantalla «madre»).
   Decisión dictada: **Máquinas es la anfitriona** (primera de la fila en producción
   física: máquina → molde → tipo → receta) y el ítem se rebautiza **«Configuración de
   producción»** (key `maquinas` se conserva si algún candado la fija — verifica
   DashboardColores como en C2). Si el estudio del código te da un anfitrión mejor,
   propónlo en el parte ANTES de forjar distinto: el dictado admite contra-evidencia.
3. **Dos ex-huérfanas P-NAV-06 de una vez** (Máquinas, Tipos de botellón): molde D1 —
   salen del candado con rastro, recuperan su `<x-volver>`... OJO: Máquinas como
   anfitriona SIGUE siendo ítem (no recupera Volver, no sale de P-NAV-06 — su ruta
   sigue en el menú). Solo Tipos de botellón sale + Recetas y Moldes (que nunca fueron
   huérfanas) entran como pestañas.
4. **Mutación CUÁDRUPLE... no: TRIPLE** del mini-candado (3 rutas consolidadas:
   tipos-botellon, recetas, moldes — Máquinas es la anfitriona, no se consolida a sí
   misma). Entradas 12ª, 13ª y 14ª.
5. **Cards del Inicio**: no hay cards de los 4 (verificado por grep hoy) — nada que
   retirar, decláralo igual.
6. Links cruzados que deben sobrevivir: panel de Producción → máquinas/moldes; recetas ↔
   backflush (RecetaBackflushTest completo en la batería); semáforo de moldes (M11).

QA del dueño listo para E1 (12 puntos, ya se lo di): pestañas 4-en-fila en celular es el
punto crítico + backflush intacto.

## Estado
Max-2 en pausa (v24). Marcos activo (PR #19 hoy). Baseline: **2196/15.231** en `61dd90d`.

CIERRE: sin acción hasta el QA del dueño. Once lotes, 47→35, cero rojos propios. El
próximo dictado cierra el mapa. Fierro.
