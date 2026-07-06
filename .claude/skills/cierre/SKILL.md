---
name: cierre
description: Use when the user asks to close or end the session (cerremos, listo por hoy, cierra la sesion, terminemos) OR when you are about to make the FINAL commit/push that completes a P-xxx step with its .md updates. Runs the mandatory 6-point closing checklist of PROTOCOLO-SESION s3 (RUTA-MAESTRA marks with hashes, bitacora entry, decisions, HANDOFF, same-push rule), showing the state of each item. A session must not end without this.
---

Ejecuta la checklist de cierre de `docs/PROTOCOLO-SESION.md` §3, ítem por ítem, mostrando el
estado de cada uno (hecho / no aplica / PENDIENTE con qué falta):

1. RUTA-MAESTRA: pasos `[x]` con hash de commit + ficha de unidad + panel §0 (incluido "Próximo paso").
2. BITACORA-SESIONES: entrada nueva arriba con el formato del archivo.
3. ¿Error/gotcha resuelto? → entrada en la bitácora de CLAUDE.md.
4. ¿Decisión tomada? → ficha D-0NN completada + grep `[B:D-0NN]` y desbloquear.
5. ¿Cambió arquitectura/deploy/mapa de archivos? → HANDOFF.md.
6. Los .md van en el MISMO push que el código (verifica con `git log --stat -3`).

Si algo excede la checklist (aprendizaje, regla nueva), aplica la ficha R-70 del
`docs/delegacion/RECETARIO-PROMPTS.md`. La sesión no termina con ítems PENDIENTES sin explicación.
