# CONSUMO — ledger empírico de la flota

> Lo mantiene el DIRECTOR con los partes de cierre de cada cuenta. Objetivo: saber cuánto
> consume cada TIPO de tarea para asignar según presupuesto restante (informativo, no limitante).
> Fuente por cuenta: comando `/usage` en Claude Code (% de la ventana de 5h y % semanal),
> reportado al abrir y cerrar cada sesión.

## 1. Tabla de tallas (estimación inicial → el ledger la calibra)

Sembrada con datos reales de las sesiones del 2026-07-02 en una cuenta Max (auditoría E2E de M11
con 3 agentes + fixes + demo ≈ una jornada intensa; fixes puntuales con test+push ≈ 30-60 min).

| Talla | Ejemplo | Estimación cuenta Pro | Estimación cuenta Max |
|---|---|---|---|
| **S** | Refresh de un doc, borrador de manual, índice | 10–25% sesión | 2–5% |
| **M** | Revisión de diff, guion QA, brief de decisión, matriz | 25–50% sesión | 5–15% |
| **L** | Feature con migración+tests, fix complejo verificado, plan fino | 60–100%+ (riesgo de corte) | 15–35% |
| **XL** | Spike técnico, unidad de módulo, auditoría E2E | NO ASIGNAR a Pro | 35–70% |

Regla derivada: **L y XL solo a cuentas Max**; una Pro nunca recibe tarea que estime >60% de su
sesión restante.

## 2. Ledger (una fila por sesión; el Director la agrega desde el parte de cierre)

| Fecha | Cuenta | Tarea (tablero) | Talla | Modelo·Esfuerzo | /usage inicio (ses/sem) | /usage fin (ses/sem) | Δ sesión | Resultado |
|---|---|---|---|---|---|---|---|---|
| _(ejemplo)_ 03-07 | Pro-2 QA | Guion regresión M11 | M | Sonnet5·high | 0% / 12% | 38% / 17% | 38% | HECHA |
| 02-07 | Pro-1 Director | Constitución + validar PLAN-M15 + dictado día 1 + verificación de 2 partes | M | Fable 5 (¹) | n/d (²) | 22% / 3% (captura 12:57) | ≤22% | HECHA |
| 02-07 | Max-2 Forjador B | Bootstrap stream 2 + PLAN-M15 (pre-flota) | L | Opus 4.8 · high | n/d (³) | n/d (³) | n/d | HECHA (plan aprobado con 2 ajustes) |
| 02-07 | Max-2 Forjador B | Visto bueno + P-M15-01 + P-M15-02 (+ arranque P-M15-04) | L | **Fable 5** (⁴) | n/d | 26% / 11% (captura 12:57) | ≤26% | HECHA (verificada) |
| 02-07 | Max-1 Forjador A | P-SPK-01 spike PWA (+ arranque P-SPK-02) | L | **Fable 5** (⁴)(⁵) | n/d | **86%** / 12% (captura 12:57) | ≤86% | HECHA (verificada, celular real OK) |
| 02-07 | Max-2 Forjador B | P-M15-04 + prompt delegación P-M15-03 | L | **Fable 5** (⁴) | pendiente | pendiente | pendiente | P-M15-04 HECHA (verificada) · 03 PARCIAL |
| 02-07 | Max-1 Forjador A | P-SPK-02 cola IndexedDB offline | XL | Opus 4.8 · xhigh ✓ | **0% / 12%** (captura 13:32) | **pendiente — Mauricio: /usage AHORA en Max-1** | pendiente | HECHA (verificada) — este Δ es el primer dato limpio de XL en Opus |
| 02-07 | Max-2 Forjador B | Cierre P-M15-03 (archivo evidencia + hallazgo I-01) | S | Opus 4.8 ✓ | pendiente | pendiente | pendiente | HECHA (verificada) |
| 06-07 | Max-2 Forjador B | P-M15-05 + P-M15-06 (fd31b2e) | L | Opus 4.8 ✓ | pendiente | pendiente | pendiente | HECHA (verificada, 4 correcciones → gate merge) |
| 05-07 | Mauricio (compu alterna) | P-S0-18 recetario + skills (4d7caa1) | M | Opus 4.8 | n/d | n/d | n/d | HECHA (fuera de flota; corrección F-01 dictada) |
| 06-07 | Max-1 Forjador A | F-01 recetario automático (07dbe92) | S | **Fable 5** (⁴) | n/d | n/d | n/d | HECHA (verificada) |
| 06-07 | Max-2 Forjador B | Correcciones auditoría + P-M15-07/08 (f7353fb) | L | Opus 4.8 ✓ | pendiente | pendiente | pendiente | HECHA (verificada; gate levantado) |
| 07-07 | Max-2 Forjador B | P-M15-09 fase merge (00297d5) | L | mixto Opus/Fable | n/d | **17% / 7%** (captura 07-07) | ≤17% | HECHA (verificada). Detalle: USD 19.48 · API 20min · salida 1.1M tok |
| 07-07 | Pro-1 Director | Operación día del merge: verificación 3 partes + auditoría workflow + gates I-01/I-03 + validación PLAN-M14 + coordinación merge | M-L | Fable 5 100% (decisión dueño) | n/d | **64% / 16%** (captura 07-07) | ≤64% | Jornada completa. Detalle: USD 21.96 · salida 169.5k tok · 100% ejecuciones >150k ctx |
| 07/08-07 | Max-1 Forjador A | P-M14-01 + P-M14-02 (esquema + servicio) | 2×L | Fable 5 98% / Opus 2% | n/d | ventana 0% (reset) · semanal 11% · **Fable semanal 20%** | n/d | AMBAS HECHAS (verificadas). Detalle: **USD 99.60** · API 1h44m · salida 1.0M tok · 100% sesiones con subagentes |
| 06-07 | Max-1 Forjador A | Sesión post-F-01 (P-SPK-03 en curso) | — | **Fable 5** (⁴) | n/d | **100%** / 28% (captura ~14:50) | ventana AGOTADA | CORTADA a mitad de cierre — SIN relevo (dueño esperó el reset 15:29); titular retoma en la misma sesión |
| 07-07 | Max-1 Forjador A | Cierre P-SPK-03 + I-01 modo compat. + PLAN-M14 sellado (faf772f/d1db5ef/aa10d2b/8fb6763) | L | Fable 5 96% / Opus 4% | n/d | **60% / 8%** (captura 07-07, pre PLAN-M14) | ≥60% | TODO HECHO (verificado; 403 tests + plan validado). Detalle sesión: USD 60.08 · API 1h30m · salida 563k tok |
|  |  |  |  |  |  |  |  |  |

> (¹) El kickoff dicta Sonnet 5·medium para el Director, pero la sesión corrió con Fable 5 —
> el modelo se elige en el selector de la app, no desde la sesión. Mauricio decide si la
> cambia para las próximas sesiones del Director.
> (²) `/usage` es comando de terminal interactivo, no disponible en esta sesión → proxy por
> duración según §4.
> (³) Sesión anterior a la constitución de la flota: no reportó /usage. Desde su próxima
> sesión debe reportarlo en el parte de cierre (FLOTA §5).
> (⁴) DESVIACIÓN → RECALIFICADA 07-07: el uso de Fable 5 fue DECISIÓN DEL DUEÑO (el
> modelo está disponible solo hasta el 07-07 en el plan actual; lo aprovecha mientras
> dure, asumiendo el mayor consumo). Desde el 08-07 rige el roster normal (Opus 4.8 en
> Max, Sonnet 5 en Pro) — fijar con /model al abrir. Los Δ de sesiones Fable no
> calibran las tallas de Opus/Sonnet.
> (⁵) Datos de capturas de Mauricio 02-07 ~12:57 (panel de uso, no /usage textual). Sin el
> % de INICIO los Δ son techos (≤). Max-1 al 86% de ventana arrancando P-SPK-02 (XL):
> ventana resetea 13:30 — riesgo de corte asumido.

## 3. Calibración (el Director la actualiza al cierre de cada día)

- Promedio real Δsesión por talla en Pro: S = __% · M = ≤22% (1 dato, Director en Fable 5) · L = __%
- Promedio real Δsesión por talla en Max: S = __% · M = __% · L = ≤26–86% (2 datos, ambos Fable 5) · XL = __%
- Ajustes a la tabla de tallas: _(anotar cambios con fecha)_
- **Calibración 02-07 (primeros datos, tomar con pinzas):** los 3 puntos son techos (sin %
  de inicio) y TODAS las sesiones corrieron Fable 5, que consume mucho más rápido que el
  modelo dictado — no sirven para calibrar Opus/Sonnet todavía. La dispersión Max (26% vs
  86% para talla L) sugiere que P-SPK-01 fue realmente XL o que el contexto largo de Max-1
  (622k tokens) pesó. Dato firme: lo SEMANAL va sano (3–12%) — el cuello es la ventana de
  5h, que se resetea sola. Acción: fijar modelo del roster ANTES de cada sesión y reportar
  /usage textual inicio+fin para tener Δ reales.
- **Calibración 07-07 (primer /usage con desglose, Max-1):** sesión de cierre L =
  USD 60.08 · 96% Fable/4% Opus · 60% ventana · semanal solo 8% (semana nueva al 13-07).
  Confirmado con dinero real: (a) el cuello es SIEMPRE la ventana de 5h, lo semanal sobra;
  (b) Fable 5 concentra ~96% del costo de sesión — al volver el roster Opus/Sonnet (08-07)
  el costo por talla debería caer fuerte; recalibrar entonces con Δ limpios.
- **Calibración 07-07 (comparativa Max-1 vs Max-2, mismo día, ambas talla L):** Max-1
  (Fable-dominante) = USD 60 · 60% ventana. Max-2 (merge, Opus-dominante en tokens) =
  USD 19.5 · 17% ventana. **~3× más barato el mix con Opus** para trabajo del mismo
  calibre — el roster Opus/Sonnet queda validado con datos. Nota del panel de Max-2:
  62% de las ejecuciones de 24h con >150k de contexto — sesiones largas encarecen
  incluso con caché; recomendar /clear entre tareas no relacionadas.
- **Calibración 08-07:** segunda sesión Fable de Max-1 (P-M14-01+02) = **USD 99.60** —
  consistente: sesión intensa en Fable ≈ USD 60–100; equivalente en Opus ≈ USD 20–35.
  Dato nuevo del panel: existe TOPE SEMANAL SEPARADO para Fable (20% consumido) además
  del general (11%). Consejo del propio panel adoptado como regla de flota: en workflows
  multi-agente, los subagentes simples (formateo, conteos, greps) deben correr en modelo
  más barato — los Forjadores lo aplican al configurar sus workflows.
- **Gasto acumulado visible de la flota (02→08-07):** ~USD 201 (Max-1: 159.7 · Max-2:
  19.5 · Director: 22) por: spike PWA completo en prod + M15 completo en prod + M14 al
  29% + 3 incidencias de infra resueltas. Referencia para evaluar el costo/valor del
  esquema de flota.

## 4. Notas

- Si `/usage` no existe en la versión de Claude Code de una cuenta: usar `/status` + duración de
  la sesión como proxy, y anotarlo (columna /usage = "n/d").
- El % semanal es el que manda para planificar el día siguiente; el de sesión (ventana 5h) manda
  para decidir si cabe UNA tarea más hoy.
- Los límites exactos por plan no son públicos (verificado 2026-07-02): este ledger ES la fuente.
