---
name: pre-merge
description: Use BEFORE merging a feature branch into main, or before any push to main containing significant code changes - in this repo main deploys AUTOMATICALLY to production. Also use when the user says revisa antes del merge, auditoria pre-merge or estamos listos para mergear. Runs the adversarial R-31 gate audit from the recetario (tests, build, MySQL 5.7, locks, permisos, x-components, responsive) and returns a gate table + APROBADO/RECHAZADO verdict.
---

Ejecuta la auditoría adversarial R-31 de `docs/delegacion/RECETARIO-PROMPTS.md` §4 sobre la rama
o rango que indique el usuario (si no indica, usa el diff de la rama actual contra `origin/main`).

Sigue la ficha R-31 al pie de la letra: busca razones para RECHAZAR, verifica TODOS los gates ahí
listados (tests, build, MySQL 5.7, locks, permisos, x-componentes, responsive), contrasta contra
la bitácora de `CLAUDE.md`, y entrega:

1. Tabla `gate → OK / FALLO / NO VERIFICABLE` con evidencia concreta (archivo/línea/comando).
2. `VEREDICTO: APROBADO | APROBADO CON OBSERVACIONES | RECHAZADO`.

El texto canónico de la auditoría vive SOLO en la ficha R-31 (regla anti-drift) — si esta skill
y el recetario difieren, manda el recetario.
