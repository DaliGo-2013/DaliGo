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
|  |  |  |  |  |  |  |  |  |

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
