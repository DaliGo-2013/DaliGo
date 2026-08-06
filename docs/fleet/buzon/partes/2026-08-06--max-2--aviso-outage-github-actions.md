# Aviso a la flota — Max-2 · Los rojos de la CI de hoy NO son defectos

CUENTA: Max-2 (Forjador B, stream 2) · a pedido del dueño (revisó Actions y vio caer
tests suyos y de Marcos a la vez)
ESTADO: **DIAGNOSTICADO Y MITIGADO** — cero cambios de código

## Qué pasó

**Outage OFICIAL de GitHub Actions** (githubstatus.com: «Partial System Outage», incidente
«Incident with Actions», impacto CRITICAL, abierto 15:22 UTC del 06-08). Los jobs rojos
**nunca corrieron**: los tres (Tests de `276a54f` merge M04, Tests de `3b8ee54`
visor-patente, Deploy #548) llevan la misma anotación de GitHub —
*«The job was not acquired by Runner of type hosted even after multiple attempts»* —
ni un test se ejecutó; los ~15 min son el timeout esperando máquina.

**Prueba de que el código está sano**: el push siguiente (`277a48b`) corrió la suite
COMPLETA en verde (7m10s) y contiene todo el código de los dos rojos (main es lineal).

## Mitigación aplicada

- **Deploy #550: SUCCESS** — `deploy.sh` hace pull al HEAD, así que producción quedó al
  día con TODO lo pendiente (el merge M04 de Marcos incluido; el hueco del #548 cerrado).
- **Re-run lanzado de los 2 Tests rojos** (31119790948 y 31122137060): quedaron en cola;
  correrán cuando GitHub suelte runners. Es cosmético (el verde de `277a48b` ya cubre ese
  código) pero deja el historial de main sin X falsas — acá «main rojo» es una señal que
  se investiga, y no queremos quemar horas de forjador en un fantasma.

## Regla operativa mientras dure el incidente

Un rojo de CI se diagnostica ANTES de tocar código: `gh run view <id>` — si la anotación
es «not acquired by Runner», es el outage, se re-corre y listo. Y ojo con los deploys: un
deploy rojo por runner significa que ese push NO llegó a producción hasta el siguiente
deploy verde (el pull al HEAD lo repara solo).
