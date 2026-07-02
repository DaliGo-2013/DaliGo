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
| 02-07 | Pro-1 Director | Constitución + validar PLAN-M15 + dictado día 1 | M | Fable 5 (¹) | n/d (²) | n/d (²) | ~1 h sesión | HECHA |
| 02-07 | Max-2 Forjador B | Bootstrap stream 2 + PLAN-M15 (pre-flota) | L | Opus 4.8 · high | n/d (³) | n/d (³) | n/d | HECHA (plan aprobado con 2 ajustes) |
| 02-07 | Max-2 Forjador B | Visto bueno + P-M15-01 + P-M15-02 | L | **Fable 5** (⁴) | pendiente (⁴) | pendiente (⁴) | pendiente | HECHA (verificada) |
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

## 3. Calibración (el Director la actualiza al cierre de cada día)

- Promedio real Δsesión por talla en Pro: S = __% · M = __% · L = __%
- Promedio real Δsesión por talla en Max: S = __% · M = __% · L = __% · XL = __%
- Ajustes a la tabla de tallas: _(anotar cambios con fecha)_

## 4. Notas

- Si `/usage` no existe en la versión de Claude Code de una cuenta: usar `/status` + duración de
  la sesión como proxy, y anotarlo (columna /usage = "n/d").
- El % semanal es el que manda para planificar el día siguiente; el de sesión (ventana 5h) manda
  para decidir si cabe UNA tarea más hoy.
- Los límites exactos por plan no son públicos (verificado 2026-07-02): este ledger ES la fuente.
