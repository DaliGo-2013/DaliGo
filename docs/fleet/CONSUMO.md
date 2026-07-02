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
|  |  |  |  |  |  |  |  |  |

> (¹) El kickoff dicta Sonnet 5·medium para el Director, pero la sesión corrió con Fable 5 —
> el modelo se elige en el selector de la app, no desde la sesión. Mauricio decide si la
> cambia para las próximas sesiones del Director.
> (²) `/usage` es comando de terminal interactivo, no disponible en esta sesión → proxy por
> duración según §4.
> (³) Sesión anterior a la constitución de la flota: no reportó /usage. Desde su próxima
> sesión debe reportarlo en el parte de cierre (FLOTA §5).
> (⁴) DESVIACIÓN: la sesión corrió en Fable 5, no Opus 4.8 del roster/dictado — consumo
> Max acelerado. Mauricio: fijar el modelo en el selector ANTES de abrir cada sesión y
> correr /usage tú mismo para completar esta fila (la sesión no puede ejecutar comandos
> de UI).
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

## 4. Notas

- Si `/usage` no existe en la versión de Claude Code de una cuenta: usar `/status` + duración de
  la sesión como proxy, y anotarlo (columna /usage = "n/d").
- El % semanal es el que manda para planificar el día siguiente; el de sesión (ventana 5h) manda
  para decidir si cabe UNA tarea más hoy.
- Los límites exactos por plan no son públicos (verificado 2026-07-02): este ledger ES la fuente.
