# PLAN-MENSAJES · Chat interno entre usuarios — mensajería propia, costo cero de terceros

> **Estado: VIGENTE (definido por el dueño, 2026-08-18)** — nació como alternativa
> económica a la API de WhatsApp (D-007, que sigue APLAZADA): mensajería DENTRO de la
> app, sobre el motor propio. Decisiones del dueño ya tomadas: **chat con hilos 1-a-1**
> (pantalla propia con conversaciones e historial) · **todos con todos** (cualquier
> usuario interno escribe a cualquier otro). Forja: **Max-2** (stream B). Ritmo de la
> casa: lento y seguro, un lote = un merge = una doble llave.

## 0. El pedido en una frase

Mensajes entre usuarios dentro de DaliGo — sin WhatsApp, sin costos de terceros,
construido sobre lo que ya existe.

## 1. Lo que ya existe (construir ENCIMA, no al lado)

- **Motor M15 completo**: `NotificacionDispatcher` (evento → canales por preferencia),
  campanita (canal `database`), correo, reintentos, plantillas en `configuracion`.
  El AVISO de «tienes un mensaje nuevo» viaja por aquí (evento nuevo
  `mensaje.recibido`) — el chat no reemplaza la campanita, la usa.
- **Patrón de badges declarativos** del menú (`MenuPrincipal::badges()`) para el
  contador de no-leídos.
- **Equipo chico** (decenas de usuarios): sin problemas de escala; los listados de
  usuarios ya se cargan con `->get()`.

## 2. Restricciones de diseño (no negociables)

1. **Sin websockets ni servicios externos** — el hosting cPanel manda: refresco suave
   (polling ligero o al navegar), y el aviso inmediato lo da la campanita/correo vía
   M15.
2. **Doctrina de densidad del menú** (PLAN-MENU-DENSIDAD cerró 47→32): toda superficie
   nueva exige declarar por qué no puede vivir en un apartado existente. El F0 propone
   dónde vive la entrada (candidatos: ícono hermano de la campanita en la cabecera /
   ítem de primer nivel como «Mis entregas») CON la justificación escrita.
3. **Permisos**: todos los usuarios internos — el F0 propone si va sin permiso nuevo o
   con uno (`usar mensajes`) para poder apagar a futuro.
4. **Cero pérdida ni cambio del motor M15**: el evento nuevo entra al catálogo
   `Notificacion::EVENTOS` como cualquier otro; la campanita ni se entera de que
   existe un módulo nuevo.

## 3. Protocolo (el de la casa)

- **F0 — Diseño (SOLO DOCS, dictado v25 a Max-2)**: modelo de datos (tablas
  conversaciones/mensajes, índices, no-leídos), flujo de pantallas (lista de
  conversaciones + hilo + composición), integración M15 (cuándo dispara
  `mensaje.recibido` — primera del hilo vs cada mensaje vs digest anti-spam),
  ubicación en la UI con doctrina, permisos, y **mapa de lotes** con esfuerzo.
  Entregable: anexo §5 de ESTE archivo. El dueño da visto bueno al diseño ANTES del
  primer lote de código.
- **F1+ — Lotes**: un lote = un merge = una doble llave del dueño; parte al buzón;
  verificación invariante del Director; QA del dueño en celular al cierre.

## 3.5 Espejo en Trello (pedido del dueño 19-ago)

Cinco tarjetas «Chat interno · <etapa>» en el tablero del dueño (Diseño · Motor de
mensajes · Pantallas · Actualización automática · Entrada en el menú), movidas POR EL
DIRECTOR por API: «Tareas DaliGo» → «En Curso DaliGo» (al dictar el lote) → «Terminadas
DaliGo» (al merge con doble llave, con los hallazgos como comentarios en lenguaje de
negocio). Los forjadores no tocan Trello.

## 4. Coordinación de flota

- **Max-2 forja** (su primer proyecto fuera del territorio PWA/M11) — modelo Fable 5
  fijado por el dueño.
- **Max-1 corre PLAN-PARAMETRICOS en paralelo** (Dashboard → Comercial): territorios
  disjuntos, pero AMBOS con re-fetch religioso — Marcos también sigue activo.
- Los dos proyectos no se cruzan en archivos salvo `MenuPrincipal` (si el F0 propone
  ítem) y `Notificacion::EVENTOS` — el Director secuencia esos merges.

## 5. Anexo — diseño F0 (Max-2, 2026-08-19 · dictado v25)

> Todo lo de abajo está diseñado sobre la foto de `origin/main` en `c72995a`
> (M15 con 36 eventos, menú en 32, poll de firma en su 3er uso). Cada elección
> lleva su porqué; donde el dictado dio a elegir, la recomendación va marcada
> **[RECOMENDACIÓN]** y la decide el dueño.

### 5.1 Modelo de datos — dos tablas + contadores denormalizados

**`conversaciones`** — una fila por PAR de usuarios (canónico):

| columna | tipo | notas |
|---|---|---|
| `id` | id | |
| `user_menor_id` | FK users cascadeOnDelete | el MENOR id del par |
| `user_mayor_id` | FK users cascadeOnDelete | el MAYOR id del par |
| `ultimo_mensaje_at` | timestamp nullable | orden de la lista (índice) |
| `no_leidos_menor` | unsigned int default 0 | mensajes sin leer DEL lado menor |
| `no_leidos_mayor` | unsigned int default 0 | ídem lado mayor |
| timestamps | | |

Restricciones: `unique(user_menor_id, user_mayor_id)` + índices
`(user_menor_id, ultimo_mensaje_at)` y `(user_mayor_id, ultimo_mensaje_at)`.

**`mensajes`** — append-only:

| columna | tipo | notas |
|---|---|---|
| `id` | id | |
| `conversacion_id` | FK cascadeOnDelete | |
| `emisor_id` | FK users nullOnDelete | el mensaje sobrevive al emisor («—») |
| `texto` | string(1000) | validado `max:1000`; sin índice (largo OK en 5.7) |
| timestamps | | `created_at` = enviado |

Índice `(conversacion_id, id)` para el historial paginado.

**Los porqués:**
- **Dos tablas y no clave compuesta en `mensajes`**: «mis conversaciones ordenadas
  por último mensaje» y «no-leídos por conversación» salen de UNA query indexada
  sobre `conversaciones`; con solo `mensajes` sería GROUP BY + MAX por par en cada
  render (feo en MySQL 5.7 y siempre más caro).
- **Par canónico (menor-id, mayor-id)**: `Conversacion::entre($a, $b)` canonicaliza
  y hace `firstOrCreate`; el unique compuesto es la red final (doctrina
  `vehiculo_avisos`/kaizen). Conversación conmigo mismo: rechazada por validación.
- **Contadores denormalizados y no `leido_at` por mensaje ni punteros**: enviar =
  `+1` al contador del OTRO **dentro de la misma transacción con `lockForUpdate`
  sobre la fila de la conversación** (el idioma de la casa para check-then-act);
  abrir el hilo = MI contador a 0. El badge del menú es `SUM(mi contador)` — una
  query indexada. La deriva no puede acumularse: cada lectura re-ancla a 0.
- **Retención [RECOMENDACIÓN]: para siempre en v1.** Decenas de usuarios × texto
  corto = volumen trivial (36k mensajes/año a 100/día). Una retención configurable
  (`mensajes_retencion_dias`, purga por scheduler en la grilla */15) queda anotada
  como futuro nivel-1 de PLAN-PARAMETRICOS — no se construye ahora.
- **Sin editar ni borrar mensajes en v1** (traza honesta, cero superficie extra).
  **Sin Auditable** en ninguna de las dos tablas: alto volumen y la fila es su
  propia traza — el mismo criterio de `Notificacion`.
- **Usuario eliminado**: el cascade se lleva sus hilos completos. Declarado y
  aceptado: `nullOnDelete` rompería el unique del par canónico (dos NULL chocan
  conceptualmente), y en la práctica los usuarios se desactivan quitándoles roles,
  no se borran.
- **Offline [RECOMENDACIÓN]: online-only en v1** (la opción B que el dictado
  permitió declarando alternativa): un chat sin señal tampoco recibiría la
  campanita/mail del otro lado — la conversación es viva por naturaleza. El form
  de composición avisa «necesitas señal» con el `$store.red` que ya existe (mismo
  indicador de mi-reporte). Encolar mensajes por la cola offline queda como lote
  futuro si el dueño lo pide (la tabla no necesita cambios para eso: se agregaría
  `cliente_uuid` con su unique en una migración aditiva).

### 5.2 Pantallas (3, ancho `formulario`, móvil primero)

1. **Lista de conversaciones** — `GET /mensajes` (`mensajes.index`):
   molde EXACTO de la bandeja de notificaciones (`notificaciones/index`): fila
   entera clickeable, tinte `bg-brand-50/40` + punto brand cuando hay no-leídos,
   `<x-avatar>` con iniciales, nombre + último mensaje truncado +
   `diffForHumans()`, y badge brand con el contador de esa conversación.
   Orden: `ultimo_mensaje_at` desc. **Sin paginación** (con equipo de decenas el
   tope natural es N−1 hilos; `->get()` como los listados de usuarios). Botón
   «Nuevo mensaje» en la cabecera.
2. **Hilo** — `GET /mensajes/{conversacion}` (`mensajes.show`, `whereNumber`):
   burbujas por lado con la paleta-4 — las mías brand suave (`bg-brand-50`) a la
   derecha, las del otro `bg-neutral-100` a la izquierda; hora con `enChile()`
   (timestamp con hora → macro obligatoria, P-TZ-02). Historial con la paginación
   estándar de la casa (`paginate(50)` descendente; la página se renderiza en
   orden cronológico). Composición al pie: `<x-textarea rows="2" maxlength="1000">`
   + botón `h-12 w-full` (molde del bloque kaizen de mi-reporte, con su guarda de
   texto vacío y sin comillas dobles en el x-data).
   **Marcar leído en el GET del hilo** (bajar MI contador a 0): idempotente, solo
   toca estado propio, y en esta app no hay speculation-rules ni `rel=prefetch`
   (el SW es passthrough para navegaciones) — riesgo de prefetch nulo, declarado
   para el gate. Gate de propiedad: 403 si no soy participante (mismo
   `abort_unless` de mi-reporte).
3. **Nuevo mensaje** — `GET /mensajes/nuevo` (`mensajes.create`):
   `<x-select>` de usuarios precargado (molde `notas/_form`; NO existe
   autocompletado de usuarios en la casa y con decenas no hace falta — si algún
   día crece, `<x-buscador-remoto>` ya acepta un endpoint `{id, label}`),
   excluyéndome, + textarea. El POST canonicaliza el par, crea/encuentra la
   conversación y redirige al hilo.

Rutas: grupo `auth` + `permission:usar mensajes`, prefix `mensajes`, names
`mensajes.*`. La ruta `conteo` del poll va ANTES de `{conversacion}` (doctrina
de despachos/vivo). `POST /mensajes/{conversacion}` = enviar al hilo.

### 5.3 Integración M15 — evento `mensaje.recibido` + anti-spam de RÁFAGA

- **Catálogo**: `mensaje.recibido` («Mensaje interno recibido») entra a
  `Notificacion::EVENTOS` (37º). Plantilla `notif_plantilla_mensaje_recibido` en
  `ConfiguracionSeeder` (clave NUEVA → sin one-shot), placeholders `{emisor}` y
  `{extracto}`. La matriz de preferencias del perfil la muestra sola (bucle sobre
  EVENTOS) y el mail respeta `PreferenciaCanal` con el default del motor (ON) —
  el motor NO se toca (restricción §2.4).
- **Destino**: `notificable` = la **Conversacion** (estable; el mensaje puntual
  envejece). `urlDestino()` → `mensajes.show`; `urlDestinoPara()` → navegable
  solo si el usuario es participante del hilo. OJO de implementación: son DOS
  `match` separados y el segundo tiene `default => false` — un evento nuevo que
  solo toca el primero queda mudo (trampa conocida del motor).
- **Anti-spam [RECOMENDACIÓN]: aviso por RÁFAGA** — se despacha `mensaje.recibido`
  **solo si el contador de no-leídos del receptor estaba en 0** antes de este
  mensaje. Mientras no lea, los siguientes mensajes callan (ya tiene la campanita
  encendida por ese hilo); cuando lee (contador → 0), el próximo vuelve a avisar.
  Un chat activo de 40 mensajes = **1 campanita + 1 mail**, y una conversación
  retomada horas después vuelve a avisar sola.
  - Contra «cada mensaje»: 40 filas en la campanita — lo prohíbe el dictado.
  - Contra «digest»: exigiría scheduler (grilla I-01 */15 → hasta 15 min de
    latencia para un chat), estado extra de acumulación, y una plantilla que
    resuma N hilos. La ráfaga da la misma protección con CERO estado nuevo: es
    una comparación sobre el contador que ya existe (el primo natural de la
    racha del SIC).
  - El despacho va FUERA de la transacción del mensaje (after-commit): un mail
    caído no revierte el mensaje, y el try/catch por destinatario del molde SIC
    no aplica aquí (receptor único).

### 5.4 Refresco sin websockets — poll de firma (3er… 4º uso del molde)

- **Molde exacto** de vivo/cola-bodega: la MISMA función privada calcula la firma
  para la vista y para el endpoint JSON (si divergieran, el monitor recargaría en
  loop o nunca); script inline con `setInterval` 20 s, guard
  `document.visibilityState !== 'visible'`, compara firma horneada al render y
  hace `window.location.reload()` — recarga completa, no parche de DOM.
- **Hilo**: `GET /mensajes/{conversacion}/conteo` — seguro porque
  `{conversacion}` lleva `whereNumber` y `conteo` es un segmento FIJO posterior
  al parámetro (mismo caso que `mi-reporte/{reporte}/paradas`; la doctrina
  «conteo antes del parámetro» aplica a rutas HERMANAS del mismo nivel, como la
  de la lista). Firma = `md5(último_id . '|' . mi_contador)`.
- **Lista**: `GET /mensajes/conteo` (ANTES de `{conversacion}`). Firma = md5 de
  `(id, ultimo_mensaje_at, mi_contador)` de mis conversaciones.
- **Costo**: 1 request liviana (1-2 queries indexadas, sin render) cada 20 s por
  pestaña VISIBLE. Peor caso realista (todo el equipo con el chat abierto a la
  vez): ~decenas de requests/min — el hosting ya corre TRES polls idénticos
  (vivo, cola de bodega, por-confirmar de ST) sin queja. Sin pestaña visible,
  cero requests (el guard corta antes del fetch).
- **Campanita y badge del menú: SIN poll** — doctrina vigente de la campanita
  («v1 sin polling: se refresca al navegar»). El shell no se toca; la inmediatez
  fuera de la app la da el MAIL de M15. Si el dueño después quiere campanita
  viva, es un proyecto del shell, no de este módulo.

### 5.5 Ubicación en la UI [RECOMENDACIÓN]: ítem de PRIMER NIVEL «Mensajes» (32→33)

- **Por qué no puede vivir en un apartado existente** (declaración que exige la
  doctrina): el chat es transversal a TODOS los roles («todos con todos») — un
  módulo de dominio como anfitrión (Operación, Comercial…) se lo esconde a los
  roles que no ven ese módulo. La familia correcta son los links personales de
  primer nivel que YA existen con este mismo argumento: Mi producción, Mis
  entregas, Aprobaciones. «Mensajes» es de esa familia (superficie personal
  transversal), no una pantalla de un dominio.
- **Por qué NO el ícono hermano de la campanita** (el otro candidato del §2.2):
  la campanita es un CANAL (te llegan cosas de todos los módulos); el chat es un
  LUGAR (vas tú a conversar). Dos íconos con badge en la topbar móvil (375px)
  compiten por el espacio del título, y ese shell tiene contratos finos
  (`CampanitaTest` fija el aria-label exacto; `SidebarTest` cuenta
  `aria-current`). El ítem de menú usa el patrón badge declarativo YA existente
  sin tocar una línea del shell.
- **Badge**: key `mensajes_no_leidos` en el ítem + resolver en
  `MenuPrincipal::resolverBadges()` = `SUM` de mi contador sobre mis
  conversaciones, gateado por `usar mensajes` (mismo molde que los 7 resolvers;
  hereda el TTL de 10 s y el memoizado por request). `badge_title`:
  `:n mensaje(s) sin leer`. Cumple la doctrina de badges: es una ACCIÓN pendiente
  anclada al ítem exacto donde se resuelve.
- Card del Inicio opcional en el zócalo de accesos (`MenuPrincipalTest` exige
  cards ⊆ menú — entra con el ítem, no antes).

### 5.6 Permisos [RECOMENDACIÓN]: permiso nuevo `usar mensajes`, apagable

- Nuevo permiso `usar mensajes` («Usar la mensajería interna») en el seeder,
  asignado a **todos los roles** — incluidos soplador, conductor y member: son
  exactamente los usuarios de celular a los que este chat les reemplaza WhatsApp.
  Seeder aditivo con `givePermissionTo` (doctrina: jamás `syncPermissions`);
  label y categoría en `config/permissions.php`.
- **Apagable sin deploy**: quitar el permiso a un rol (o a un usuario) desde
  Administración → Roles. Precedente exacto de la casa: `simular carga` («Usar el
  simulador»). El menú y las rutas gatean por el permiso → el menú jamás ofrece
  un 403.
- Rechazado el «sin permiso» (estilo bandeja de la campanita, que es «lo propio»):
  la bandeja es pasiva; el chat INTERRUMPE a otros usuarios, y el propio plan §2.3
  pide poder apagarlo a futuro. El costo es una línea por rol.

### 5.7 Mapa de lotes para F1+ (cada lote = un merge = una doble llave)

| Lote | Esfuerzo | Contenido | Candados que trae |
|---|---|---|---|
| **MSG-1** | **M** | Modelo + backend puro, SIN UI: migraciones, `Conversacion` (par canónico + `entre()`) y `Mensaje`, envío con transacción/lock/contadores, evento `mensaje.recibido` (EVENTOS + plantilla seeder + `urlDestino`/`urlDestinoPara`), anti-spam de ráfaga, permiso `usar mensajes` en seeder | par canónico (A,B)≡(B,A) **mutado** · contadores suben/bajan · ráfaga: el 2º mensaje calla y tras leer vuelve a avisar, **mutado** · 403 no-participante · texto ≤1000 · conmigo-mismo rechazado · plantilla sembrada (+1 en `NotificacionConfigSeedTest`) · `urlDestinoPara` navegable solo para participantes |
| **MSG-2** | **M** | Las 3 pantallas + marcar-leído en el GET + rutas web + volcado 375/768 | no-leído pintado (tinte + punto + contador) · hilo ajeno 403 · orden cronológico dentro de la página · marcar-leído baja el contador · select excluye al propio usuario |
| **MSG-3** | **S** | Poll de firma en hilo y lista (endpoints `conteo` + scripts del molde vivo) | firma cambia con mensaje nuevo / estable sin cambios · la MISMA función alimenta vista y endpoint (mutado: divergencia → rojo) |
| **MSG-4** | **S** | Ítem «Mensajes» + badge `mensajes_no_leidos` + card del Inicio | `aria-current` único · badge con title-contrato `:n mensaje(s) sin leer` · `MenuPrincipalTest` (cards ⊆ menú, labels únicos) · `HigienePermisosTest` (el permiso se usa) |

Orden a propósito (lo pidió el dictado): backend testeable puro primero, el menú
al final — si algo se atrasa, nunca queda un ítem de menú apuntando a media obra.
Coordinación: MSG-1 toca `Notificacion::EVENTOS` y MSG-4 toca `MenuPrincipal` —
los dos archivos compartidos con PLAN-PARAMETRICOS; el Director secuencia esos
merges (§4).

---
**VEREDICTOS DEL DUEÑO AL DISEÑO F0 (2026-08-19, al Director): APROBADO ENTERO.**
Las 5 recomendaciones ratificadas: ítem de primer nivel «Mensajes» (menú 32→33, badge
de no-leídos) · aviso por RÁFAGA · permiso `usar mensajes` en todos los roles ·
online-only en v1 · retención para siempre en v1. Fase de código: MSG-1 (backend puro,
dictado v26) → MSG-2 (pantallas) → MSG-3 (poll) → MSG-4 (menú+badge, al final).

**CONSTRUCCIÓN COMPLETA 4/4 (2026-08-20):** MSG-1 `c89867e` → MSG-2 `5bf39df` →
MSG-3 `1d7ad3e` → MSG-4 `1c044df` (todas con doble llave). El ítem «Mensajes» quedó
entre Aprobaciones y ST (familia personal transversal, argumentado por Max-2), badge
con fuente única `Conversacion::noLeidosDeUsuario()` compartida con `firmaChat`.
**PENDIENTE: QA del dueño en celular (las 4 etapas juntas) → cierre del plan.**

**QA DEL DUEÑO (2026-08-20, teléfono y PC): funcional APROBADO con UN hallazgo —
la viveza.** «Hay que recargar para ver los mensajes, no es instantáneo»: el poll
de 20 s con recarga completa cumple su contrato pero se siente lento en una
conversación (y el reload pierde texto a medio escribir). Veredicto de infra del
Director: websockets/Reverb DESCARTADOS en HostGator compartido (sin daemons —
el proveedor mata procesos; la restricción §Restricciones ya lo decía);
instantáneo real = migrar a VPS (decisión de negocio futura). **El dueño eligió:
poll fino → lote MSG-5** (4 s solo en chat + el hilo trae-y-appendea sin reload
conservando el composer + tick inmediato al volver a la pestaña). MSG-5 es ahora
el lote de cierre del plan.
