# FLOTA DaliGo — roster, roles y reglas de orquestación

> Constituida el 2026-07-02 (paso P-S0-17). 6 cuentas de Claude trabajando DaliGo en paralelo,
> coordinadas por documentación compartida en git + Mauricio como bus de mensajes.
> El tablero operativo vive en `TABLERO-3-DIAS.md`; el consumo en `CONSUMO.md`.

---

## 1. Roster

| Cuenta | Plan | Rol | Plataforma | Modelo base | Escribe en git |
|---|---|---|---|---|---|
| **Max-1 · Forjador A** | Max $100 | Stream 1: código pesado en `main` (hoy: spike PWA offline) | Claude Code | Opus 4.8 / Fable 5 · high–xhigh | `main` |
| **Max-2 · Forjador B** | Max $100 | Stream 2: unidad E1·M15 (kickoff `docs/delegacion/KICKOFF-E1-M15.md`) | Claude Code | Opus 4.8 · high | rama `feature/m15-notificaciones` |
| **Pro-1 · DIRECTOR** | Pro $20 | Cerebro: tablero, asignaciones, modelo+esfuerzo por tarea, verificación, ledger | Claude Code | Sonnet 5 · medium–high | **SOLO `docs/fleet/**`** |
| **Pro-2 · AUDITOR/QA** | Pro $20 | Revisión adversarial de diffs + verificación funcional local + guiones QA | Claude Code (read-only del código) | Sonnet 5 · high | **NADA** (entrega texto) |
| **Pro-3 · INVESTIGADOR** | Pro $20 | Destrabar decisiones D-0xx: investigación + briefs listos para enviar | Claude Code o claude.ai | Sonnet 5 · medium | **NADA** (entrega texto) |
| **Pro-4 · ESCRIBA** | Pro $20 | Documentación aislada: manuales, guías, índices | Claude Code | Haiku 4.5 / Sonnet 5 · low–medium | solo archivos asignados en el tablero |

Además existe la **IA externa de cPanel/QA** (no es de la flota; protocolo en
`docs/delegacion/PROTOCOLO-DELEGACION.md`): única con acceso a servidor/staging.

## 2. Reglas del bus (comunicación)

1. **Las cuentas NO se hablan entre sí.** El bus es: (a) el repo git (docs + commits) y
   (b) Mauricio copy/pasteando entre sesiones.
2. Todas reportan **al Director** (vía Mauricio): al cerrar una tarea entregan el "parte de
   cierre" (§5). El Director verifica y actualiza el tablero.
3. **Territorio de escritura exclusivo por cuenta** (tabla §1). Fuera de tu territorio: manos
   fuera, aunque veas algo roto — se reporta al Director.
4. `git fetch origin` al inicio de cada sesión, siempre.
5. Toda cuenta de Code arranca su sesión leyendo: su kickoff → `docs/PROTOCOLO-SESION.md` →
   `docs/fleet/TABLERO-3-DIAS.md` (su columna) → `CLAUDE.md`.

## 3. Matriz modelo × esfuerzo (la usa el Director para dictar cada tarea)

Estado de modelos verificado el 2026-07-02 (docs oficiales Anthropic):

| Tipo de tarea | Modelo | Esfuerzo | Notas |
|---|---|---|---|
| Arquitectura, auditoría profunda, spike de riesgo | **Fable 5** | high–xhigh | Máxima inteligencia, 1M ctx. Solo en cuentas Max (consume rápido). |
| Código agentic complejo (features grandes, merges delicados, bugs duros) | **Opus 4.8** | high (xhigh si el bug resiste) | El caballo de batalla de los Forjadores. `/fast` = mismo Opus con salida rápida a ~2× consumo: solo en Max y solo si la iteración rápida vale el gasto. |
| Features estándar, revisión de diffs, QA, redacción técnica | **Sonnet 5** | medium–high | Default de las cuentas Pro. Rápido y capaz. |
| Tareas mecánicas: índices, formateo, checklists, borradores simples | **Haiku 4.5** | low | El más barato; ideal para el Escriba y chores. |
| `max` effort | cualquiera | — | EXCEPCIONAL: solo con autorización del Director y en cuenta Max. |

Reglas de oro del Director al asignar:
- **Tarea larga (>2h estimadas) → cuenta Max** (las Pro agotan su ventana de 5h antes de terminar).
- Ante la duda de modelo: Sonnet 5 high. Subir de modelo solo si la tarea lo justifica.
- Esfuerzo default = high; bajar a low/medium en chores ahorra 30–70% de tokens (dato oficial).

## 4. Control de consumo (informativo, no limitante)

Los límites exactos por plan **no son públicos** → se miden empíricamente:

1. Cada cuenta de Code corre **`/usage`** al ABRIR y al CERRAR la sesión y anota los % (ventana
   de 5h y semana) en su parte de cierre.
2. El Director registra todo en `CONSUMO.md` (ledger) junto a la talla de la tarea.
3. En 2–3 días el ledger produce la tabla real "talla S/M/L/XL ≈ % de sesión Pro / Max" y el
   Director asigna mirando el presupuesto restante de cada cuenta.
4. Si `/usage` no existe en la versión instalada de alguna cuenta: reportar qué muestra `/status`
   y estimar por tiempo de sesión (anotarlo igual — peor es nada).

## 5. Parte de cierre (formato obligatorio que toda cuenta entrega al Director)

```
CUENTA: (rol)
TAREA: (id del tablero)
ESTADO: HECHA | PARCIAL | BLOQUEADA (motivo)
EVIDENCIA: commits (hash) / archivo entregado / texto pegado
TESTS: verdes (n) | no aplica
/usage INICIO → FIN: sesión X%→Y% · semana X%→Y%
SIGUIENTE: qué recomiendo hacer después
```

El Director NO marca nada como hecho sin evidencia (misma regla anti-autoengaño de RUTA-MAESTRA).

## 6. Escalamiento

- Conflicto de territorio o duda de asignación → Director decide; si es de negocio → ficha D-0NN.
- Bloqueo de infra/staging → prompt para la IA de cPanel (plantillas en `docs/delegacion/`).
- El Director le informa a Mauricio, en cada cierre de día: avance vs tablero, consumo, y el
  dictado de modelo+esfuerzo del día siguiente.
