# Protocolo de sesión de trabajo — DaliGo

> Para cualquier colaborador nuevo (humano o IA), en cualquier computador. Si solo puedes leer un
> documento antes de trabajar, que sea este: te dice cómo ubicarte, cómo trabajar y cómo cerrar.

---

## 1. Retomar el proyecto en 10 minutos (arranque de sesión)

| Minuto | Acción |
|---|---|
| 0–2 | Leer `docs/RUTA-MAESTRA.md` **§0 "Dónde estamos hoy"** (fase, unidad activa, próximo paso, bloqueos). |
| 2–4 | Leer la **última entrada** de `docs/BITACORA-SESIONES.md` (qué hizo la sesión anterior y en qué quedó). |
| 4–6 | **Verificar papel↔código:** `git log --oneline -10` y comparar con los últimos pasos `[x]` de RUTA-MAESTRA. ¿Coinciden los commits citados? |
| 6–8 | Si el entorno es nuevo: `HANDOFF.md` §10 (arranque rápido: clone, composer, npm, sqlite, test verde). |
| 8–10 | **Declarar el objetivo de la sesión = UN paso `P-xxx`** de RUTA-MAESTRA (el campo "Próximo paso" de §0 es el candidato por defecto). |

> **Si papel y código NO coinciden** (paso marcado `[x]` sin commit real, o commits sin paso): la sesión
> se dedica PRIMERO a reconciliar RUTA-MAESTRA (documentándolo en la bitácora), DESPUÉS a programar.
> Un tablero mentiroso es peor que no tener tablero — así nació el falso "M13 hecho".

---

## 2. Durante la sesión (4 reglas)

1. **Un paso a la vez.** Si aparece trabajo nuevo no previsto, se AGREGA como paso a RUTA-MAESTRA
   (con ID nuevo) — no se improvisa sin dejar rastro.
2. **Decisión no tomada en el camino** → se registra en `docs/DECISIONES.md` (ficha D-0NN) y el paso
   afectado se marca `[B:D-0NN]`. No inventar la respuesta del negocio.
3. **Ojos en staging/cPanel/servidor** → delegar a la IA externa con las plantillas de
   `docs/delegacion/` (nunca pedir "revisa que todo esté bien": pasos exactos + formato de respuesta).
4. **Convenciones**: las de `CLAUDE.md` mandan (componentes reutilizables, permisos por ruta,
   seeders idempotentes, MySQL 5.7, tests verdes antes de push, bitácora de errores).

---

## 3. Cierre de sesión (checklist OBLIGATORIA — la ejecuta la IA como último acto)

```
1. [ ] RUTA-MAESTRA: marcar pasos [x] con hash de commit; actualizar ficha de la unidad,
       tabla §2 si cambió, y el panel §0 completo (incluye "Próximo paso" — UNO concreto, nunca vacío).
2. [ ] BITACORA-SESIONES.md: nueva entrada arriba (formato del propio archivo).
3. [ ] ¿Se resolvió un error/gotcha? → entrada en la bitácora de CLAUDE.md (regla de oro existente).
4. [ ] ¿Se tomó una decisión? → completar la ficha D-0NN en DECISIONES.md + grep "[B:D-0NN]"
       en RUTA-MAESTRA y desbloquear los pasos.
5. [ ] ¿Cambió arquitectura/deploy/mapa de archivos? → actualizar la sección pertinente de HANDOFF.md.
6. [ ] Commitear los .md JUNTO con el código, en el MISMO push (regla del mismo push).
```

**Regla del mismo push:** ningún push que complete un paso `P-xxx` sale sin tocar
`docs/RUTA-MAESTRA.md` en ese mismo push. Verificación trivial: `git log --stat -3` debe mostrar
el `.md` junto al código. Como push a main = deploy, producción y tablero avanzan siempre juntos.

---

## 4. Cuándo y cómo delegar a la IA de cPanel/QA

- **Cuándo:** verificación funcional en `staging.impdali.cl` tras cada deploy (smoke + específico del
  módulo), tareas de cPanel (crons, BD, SSL, límites PHP, logs del servidor), e investigación para
  destrabar decisiones.
- **Cómo:** protocolo y plantillas en `docs/delegacion/PROTOCOLO-DELEGACION.md` → el dueño copia/pega
  el prompt en la IA externa → pega la respuesta de vuelta → se archiva ÍNTEGRA en `docs/qa/`
  (convención en `docs/qa/README.md`) → veredicto → acción en RUTA-MAESTRA.

---

## 5. Problemas frecuentes

| Situación | Qué hacer |
|---|---|
| "El panel §0 dice X pero el código dice Y" | Protocolo de reconciliación (§1, nota final). Primero corregir el papel. |
| "No sé qué paso sigue" | RUTA-MAESTRA §0 campo **Próximo paso**. Si está vacío, la sesión anterior cerró mal: reconstruir desde `git log` + bitácora y anotarlo. |
| "Necesito algo del negocio (Luis/Víctor/Marco)" | Ficha en DECISIONES.md + brief copy/paste + marcar `[B:D-0NN]` y seguir con trabajo no bloqueado (columna "mientras tanto"). |
| "La respuesta de la IA externa no respeta el formato" | No interpretarla: reenviar el prompt subrayando la sección FORMATO. |
| "Rompí algo en producción" | `CLAUDE.md` bitácora (soluciones conocidas) + revertir con un commit nuevo (nunca force-push a main) + entrada de bitácora nueva. |
