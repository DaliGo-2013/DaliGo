# El tablero de tareas: se queda en Trello, con las tarjetas automáticas (13-08-2026)

**El problema real:** no es la herramienta, es que las tarjetas se cargan **de a una y a mano**.
«Estamos perdiendo mucho tiempo en el envío una a una» (dueño, 13-08).

**La decisión:** **quedarse en Trello** y automatizar la carga. Se evaluó mudarse a Notion y se
descartó — ver §5.

---

## 1. Trello tiene MCP oficial, y funciona en el plan gratis

Atlassian publica un MCP remoto en **`https://mcp.trello.com/v1`** (oficial, desde mediados de
2026). Autenticación **OAuth 2.0**, con alcance **por Workspace**: se elige en la pantalla de
consentimiento cuál se autoriza, y el asistente hereda los permisos que ya tiene esa persona.

**Lo que puede hacer**, que es justo lo que hacía falta:

- crear, ver, actualizar, **mover** y **archivar** tarjetas;
- crear y actualizar checklists con sus ítems;
- poner y quitar etiquetas;
- buscar en tarjetas y tableros.

**Lo que todavía NO puede** (Atlassian las lista como «próximamente»):

- **comentar** tarjetas → lo que se verificó va en la **descripción** de la tarjeta, no como
  comentario;
- **adjuntar** archivos → no se pueden subir capturas por esta vía;
- borrar de verdad: archiva, no elimina. (Que para un historial es lo correcto.)

---

## 2. Los límites del plan gratis, verificados

| Límite | Plan gratis |
|---|---|
| Tarjetas | **ilimitadas** (no hay tope por tablero ni por lista) |
| Colaboradores | **10 por Workspace** — hoy el tablero tiene 5 |
| Tableros | **10 por Workspace** (y se pueden crear varios Workspaces) |
| Automatizaciones (Butler) | 250 corridas por mes |
| Archivos | 10 MB por archivo |
| Power-Ups | ilimitados por tablero |

**La automatización de acá no gasta ese cupo de 250:** no usa Butler, usa la API. El cupo queda
libre para las reglas del tablero.

---

## 3. Cómo se cargan las tarjetas solas

Son **dos caminos**, y la diferencia importa porque uno necesita un token y el otro no.

### a) Claude, al cerrar cada tarea — por MCP (sin ningún token)

Al terminar algo, Claude crea la tarjeta directo en **Terminadas** —o mueve la que está en **En
Curso**— con la descripción cargada: qué se hizo, el commit, el link al PR y **qué se
verificó**. Es OAuth, así que **no hay credenciales en ninguna parte**: cada uno autoriza su
cuenta desde su máquina.

Es el camino que cubre la mayor parte del trabajo, porque la mayor parte pasa por una sesión.

### b) GitHub Action en cada deploy — por API REST (con token, en Secrets)

Cuando se mergea a `main`, un Action crea la tarjeta de lo que salió a producción. **No
necesita que haya una sesión abierta**: corre en GitHub y no se olvida.

> ⚠️ **El MCP NO sirve para esto.** Es OAuth interactivo: necesita un navegador y una persona.
> Para CI hace falta la **API REST** de Trello con `key` + `token`, y eso va en **GitHub
> Secrets** (`TRELLO_KEY`, `TRELLO_TOKEN`, `TRELLO_LISTA_TERMINADAS`), **nunca** en el repo:
> DaliGo es **público** (D-012).

### c) Lo que ninguno de los dos cubre

Lo que se hace fuera de una sesión y no termina en un deploy —una llamada, una decisión de
jefatura, un dato que llegó por WhatsApp— sigue siendo carga a mano. No hay forma de que un
programa se entere de eso.

---

## 4. Revisada del tablero, 13-08-2026

Estado: un tablero, dos proyectos, seis listas y **473 tarjetas**.

| Lista | Tarjetas |
|---|---|
| Tareas Boveda.Lols | 14 |
| En Curso Boveda.Lols | 6 |
| Terminadas Boveda.Lols | 132 |
| Tareas DaliGo | 33 |
| En Curso DaliGo | 16 |
| Terminadas DaliGo | 272 |

**① El historial no se puede leer.** Buena parte de las 272 + 132 tarjetas de «Terminadas» se
titulan **`image.png`**: son capturas pegadas sin título. Eso vuelve el historial **imposible de
buscar** y de reportar — nadie puede contestar «¿qué se hizo en julio?» sin abrir tarjeta por
tarjeta, y jefatura menos. Es el costo más alto del tablero de hoy, y es exactamente lo que la
carga automática arregla **de acá en adelante**: una tarjeta creada por Claude o por el Action
tiene título, commit, PR y qué se verificó. Lo viejo queda como está: renombrar 400 tarjetas a
mano cuesta más de lo que rinde.

**② 22 tarjetas «En Curso» para dos personas.** «En Curso» está funcionando como «pendiente
priorizado». No es un problema si es a propósito, pero conviene saberlo antes de mirar el
tablero para decidir en qué se está.

**③ Los dos proyectos en un tablero.** Funciona y no lo cambiaría por ahora. Si algún día
molesta, el plan gratis permite **10 tableros por Workspace**, así que separarlos no cuesta
plata. Lo que sí conviene con el tiempo: **archivar** las «Terminadas» viejas por trimestre — la
tarjeta archivada se sigue buscando y la lista deja de pesar.

**④ 5 colaboradores sobre 10.** Hay lugar para jefatura sin pagar nada.

---

## 5. Por qué NO nos mudamos a Notion

Se evaluó primero (misma sesión) y con el tablero a la vista la conclusión se dio vuelta:

| | Trello gratis | Notion gratis |
|---|---|---|
| Información ilimitada | **sí**, tarjetas ilimitadas | **solo con UN miembro**; con dos o más, tope de 1.000 bloques y no acepta contenido nuevo |
| Personas | 10 colaboradores por Workspace | 1 miembro + 10 invitados |
| MCP oficial | **sí**, y escribe | sí, y escribe |
| Lo que hay que migrar | **nada** | 473 tarjetas y cinco personas reaprendiendo |

El criterio del dueño fue **«gratis e información ilimitada, que al final para eso lo usamos»**.
En ese criterio Trello gana, y encima no hay migración: lo que faltaba era la automatización, no
otra herramienta. Notion sigue siendo mejor para **documentos y bases relacionadas**; el día que
haga falta eso, se revisa (queda `docs/NOTION-TAREAS.md` en el historial de git con el diseño
completo, incluida la trampa del miembro único).

---

## 6. Qué falta para dejarlo andando

**Marcos (no lo puede hacer Claude):**

1. Autorizar el conector de Trello: es un OAuth en el navegador y hay que **elegir el Workspace**
   del tablero.
2. Para el Action: sacar la `key` y el `token` de Trello y cargarlos en GitHub Secrets.

**Claude, después de eso:**

1. Mapear los **IDs de las seis listas** (con el MCP ya conectado) — sin eso, ninguna
   automatización sabe adónde escribir.
2. Dejar la regla de «al cerrar una tarea, la tarjeta» en `CLAUDE.md`.
3. Escribir el Action y verificarlo contra el tablero real, no contra nombres supuestos.

---

## Fuentes

- [Conectar Trello con asistentes de IA (Trello MCP)](https://support.atlassian.com/trello/docs/connect-trello-to-ai-assistants-with-trello-mcp/) ·
  [repo oficial](https://github.com/atlassian/trello-mcp-server)
- Límites del plan gratis: [Trello free plan limits](https://www.usecarly.com/blog/trello-free-plan-limits/) ·
  [Trello pricing 2026](https://www.usecarly.com/blog/trello-pricing/)
- Notion (opción descartada): [uso de bloques](https://www.notion.com/help/understanding-block-usage) ·
  [precios](https://www.notion.com/es/pricing)
