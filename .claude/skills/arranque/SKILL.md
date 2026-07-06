---
name: arranque
description: Retomar el proyecto DaliGo en 10 minutos - ejecuta el arranque de sesion del PROTOCOLO-SESION (panel de estado, ultima bitacora, verificacion papel-codigo) y termina declarando el paso P-xxx objetivo de la sesion. Usar al inicio de CADA sesion de trabajo.
---

Ejecuta el arranque de sesión de `docs/PROTOCOLO-SESION.md` §1 ("retomar el proyecto en 10 minutos"),
paso a paso y mostrando lo que encuentras en cada uno:

1. Lee `docs/RUTA-MAESTRA.md` §0 (panel "Dónde estamos hoy") y resúmelo en 3 líneas.
2. Lee la última entrada de `docs/BITACORA-SESIONES.md`.
3. Verifica papel↔código: `git log --oneline -10` vs los últimos pasos `[x]` — di si coinciden.
4. Si papel y código NO coinciden: la sesión se dedica PRIMERO a reconciliar (§1, nota final).
5. Termina declarando: **"Objetivo de la sesión: {P-xxx} — {título del paso}"** (el campo
   "Próximo paso" del panel §0 es el candidato por defecto; si tu cuenta tiene kickoff propio,
   manda tu stream).

No empieces a programar hasta declarar el objetivo.
