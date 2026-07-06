---
name: cierre
description: Cierre de sesion DaliGo - ejecuta la checklist obligatoria de 6 puntos del PROTOCOLO-SESION (marcar pasos en RUTA-MAESTRA, bitacora, decisiones, HANDOFF, mismo push) mostrando el estado de cada item. Usar como ULTIMO acto de cada sesion de trabajo.
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
