# Dictado vigente — Max-2 (Forjador B, stream 2)
> Emitido por el Director el 2026-08-18 (v25 — FIN DE LA PAUSA: proyecto nuevo del dueño, PLAN-MENSAJES. GO F0-MENSAJES: diseño del chat interno, SOLO DOCS). Manda sobre lo anterior.

CUENTA: Max-2 (Forjador B, stream 2) · MODELO: Fable 5 (fijado por el dueño).

## 🆕 Fin de la pausa — proyecto nuevo asignado por el dueño (18-ago)

Ninguno de tus dos gatillos disparó (Luis sigue sin volver; la densidad cerró sin dos
manos — 47→32, ya en producción). El dueño te asigna proyecto PROPIO: **PLAN-MENSAJES —
chat interno entre usuarios**, la alternativa económica a la API de WhatsApp (D-007
sigue APLAZADA; el dueño detuvo esa vía por el costo del setup).

**LÉE ENTERO `docs/planes/PLAN-MENSAJES.md` antes de partir.** Decisiones del dueño ya
tomadas (no se re-litigan): chat con hilos 1-a-1 · todos con todos · sobre el motor
M15 · sin websockets (cPanel) · doctrina de densidad del menú vigente.

## 🔍 GO — F0-MENSAJES: diseño técnico (SOLO DOCS, cero código)

Tu territorio conocido juega a favor: M15 es tuyo (P-M15 completo fue del stream B).
Diseña el módulo en el anexo §5 del plan:

1. **Modelo de datos**: tablas (¿`conversaciones` + `mensajes`? ¿o mensajes con clave
   compuesta de par de usuarios?), índices para «mis conversaciones ordenadas por
   último mensaje» y «no-leídos por conversación», borrado/retención (¿los mensajes
   viven para siempre? propón). El par 1-a-1 canónico (menor-id, mayor-id) o el diseño
   que argumentes mejor.
2. **Pantallas**: lista de conversaciones (con no-leídos y último mensaje) + hilo
   (historial paginado, composición) + «nuevo mensaje a…» (selector de usuario). Móvil
   primero — el equipo vive en el celular. Con los moldes de la casa (`x-list-row`,
   layout `listado`, Volver por fuente única).
3. **Integración M15**: el evento `mensaje.recibido` entra al catálogo EVENTOS y
   dispara por el dispatcher (campanita + mail según preferencia del receptor).
   **Diseña el anti-spam**: ¿dispara cada mensaje, solo el primero del hilo no-leído,
   o digest? Propón con argumento — un chat activo no puede meter 40 filas a la
   campanita.
4. **Refresco sin websockets**: propuesta concreta (polling ligero con intervalo, o
   refresh al navegar + badge). Costo por request y por qué no revienta el hosting.
5. **Ubicación en la UI con doctrina**: dónde vive la entrada (ícono junto a la
   campanita / ítem primer nivel / otro) + badge de no-leídos por el patrón
   declarativo de `MenuPrincipal::badges()`. Justificación escrita — la densidad
   (menú 32) es sagrada.
6. **Permisos**: propón sin-permiso-nuevo vs `usar mensajes` (apagable a futuro), con
   el cruce de siempre (¿el soplador chatea? ¿el conductor?). Recuerda: el menú jamás
   ofrece un 403.
7. **Mapa de lotes** para la fase de código: lotes chicos con esfuerzo (S/M/L) y qué
   candados trae cada uno (el molde de mutación de la casa). El primer lote debería
   ser modelo+backend sin UI (testeable puro), el último el badge/menú.

### Entregable
Anexo §5 de `docs/planes/PLAN-MENSAJES.md` + parte al buzón (resumen del diseño + lo
que más te llamó la atención + tus recomendaciones donde el dictado te dio a elegir).
**Cero código** — el dueño da visto bueno al diseño y recién ahí llegan los lotes (v26+).

### Arranque operativo
Re-fetch de main FRESCO (el repo se movió MUCHO desde tu v24: menú 47→32, hotfix de
calendario, PLAN-PARAMETRICOS con DASH-1/2 en producción, Sucursales de Marcos —
baseline hoy: **2204/15.421** en `0c2bcad`). Lee `PLAN-MENU-DENSIDAD.md` (acta de
cierre) y `PLAN-PARAMETRICOS.md` para el estado del mundo. Tu barrido F0 es read-only:
cero riesgo de choque con Max-1 (corre PLAN-PARAMETRICOS en Dashboard) ni con Marcos.

## Estado
- Max-1: DASH-3 en vuelo (card Sucursales desde la BD). Territorios disjuntos.
- Coordinación futura: `MenuPrincipal` y `Notificacion::EVENTOS` son los únicos
  archivos donde ambos proyectos pueden cruzarse — el Director secuencia esos merges.

CIERRE: GO F0-MENSAJES. Bienvenido de vuelta al fierro — proyecto propio y en tu
territorio (M15 lo forjaste tú). Un dictado, un parte, y el dueño decide.
