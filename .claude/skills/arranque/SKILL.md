---
name: arranque
description: Use when starting a work session in this DaliGo repo - as the FIRST substantive action of the session, or when the user says retomar, arranquemos, empecemos, "donde estamos" or "que sigue". Runs the PROTOCOLO-SESION s1 opening (state panel of RUTA-MAESTRA, last bitacora entry, papel-codigo check against git log) and ends by declaring the session objective P-xxx. Do NOT start coding before this has run.
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
