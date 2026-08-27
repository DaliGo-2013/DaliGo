# PLAN-MENSAJES · Chat interno entre usuarios — mensajería propia, costo cero de terceros

> **Estado: 🏁 CERRADO (QA final del dueño 2026-08-20: «quedó de diez y está
> suuuuper rápido, es casi instantáneo» — su propio caso de las capturas
> verificado: el texto permanece).** Acta: 5 lotes con doble llave en 2 días
> (MSG-1 motor `c89867e` → MSG-2 pantallas `5bf39df` → MSG-3 refresco `1d7ad3e`
> → MSG-4 menú `1c044df` → MSG-5 chat vivo `1a45026`), CERO rojos propios de
> Max-2 en todo el proyecto. Del «detengamos WhatsApp, propongo algo económico»
> al chat vivo en producción: hilos 1-a-1 todos-con-todos, aviso con anti-ráfaga,
> burbujas que aparecen solas en ~4 s sin perder lo escrito — sin websockets
> (imposibles en el hosting) y sin costos de terceros. Deuda futura anotada:
> instantáneo real (0 s) = migrar a VPS (decisión de negocio); retención
> configurable = candidato nivel-1 de PARAMETRICOS cuando toque Mensajes.
>
> (Histórico: VIGENTE desde 2026-08-18 — nació como alternativa económica a la
> API de WhatsApp (D-007, que sigue APLAZADA): mensajería DENTRO de la app,
> sobre el motor propio. Decisiones del dueño: **chat con hilos 1-a-1** ·
> **todos con todos**. Forja: **Max-2** (stream B). Ritmo de la casa: un lote =
> un merge = una doble llave.)

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

**MSG-5 EN PRODUCCIÓN — CONSTRUCCIÓN 5/5 (2026-08-20, merge `1a45026`, doble
llave):** endpoint `nuevos?desde=X` pintado por el server (partial `_burbuja`
compartido con el render — XSS por construcción), el hilo appendea SIN reload
(salió de `<x-poll-recarga>`, candado estructural ajustado), marcar-leído
solo-cuando-trae, tick-al-volver heredado por los 3 consumidores del
componente, append solo página 1, lista a 4 s. E2E medido en browser real por
Max-2 ANTES del reporte del dueño (que llegó con capturas del caso exacto —
diagnóstico confirmado: era el reload de MSG-3). Suite 2291/16.002 + re-suite
árbol final 2299/16.029. **PENDIENTE: QA final del dueño (su mismo caso:
escribir sin enviar, recibir mensaje → el texto queda) → ACTA DE CIERRE.**

---

## §7 · Anexo — Fase 2: candidatas (F0-MENSAJES-2, dictado v35)

> **Catálogo para veredictos del dueño** (2026-08-27, Max-2). CERO código: cada
> candidata dice qué es en palabras de negocio, qué toca, cuánto cuesta (S/M/L),
> qué riesgos despierta y mi recomendación con porqué. **Orden: por VALOR para el
> equipo según mi criterio** — lo que más mejora el uso diario por unidad de
> esfuerzo y riesgo. El criterio, declarado: primero terminar de pulir el gesto
> que la gente ya hace todos los días (conversar), después darle alcance (fotos,
> buscar), y recién al final las piezas que pelean contra la infraestructura
> (push, instantáneo). Los veredictos son PROPUESTOS: decide el dueño,
> candidata por candidata.

### El menú de un vistazo

| # | Candidata | Esfuerzo | Mi recomendación |
|---|---|---|---|
| 1 | El hilo termina de sentirse chat (enviar sin recarga + abrir abajo + el texto nunca se pierde) | S/M | **SÍ — la primera** |
| 2 | El correo del chat lleva botón «Abrir en DaliGo» al hilo | S | **SÍ — barata y ya debió estar** |
| 3 | Una foto en el hilo (adjuntos) | M | **SÍ, acotada a foto** |
| 4 | Buscar en mensajes | M | SÍ, cuando el corpus crezca (puede esperar) |
| 5 | Aviso vivo fuera del chat (badge/campanita del shell + título de pestaña) | M | A MEDIAS: título de pestaña sí; shell vivo es proyecto aparte |
| 6 | «Visto» sí, «escribiendo» no | M / — | Visto: SÍ si el dueño lo valora. Escribiendo: NO |
| 7 | Difusión / grupos | M / L | NO por ahora — M15 ya es el canal de avisos |
| 8 | Retención configurable | S | SÍ como higiene, sin apuro |
| 9 | Mensajes sin señal (encolar offline) | M | Dormida — solo si el uso real la pide |
| 10 | Editar/borrar con ventana de tiempo | M | NO — la traza honesta vale más de lo que parece |
| 11 | Push PWA al teléfono | L | NO en este hosting — el porqué completo abajo |
| 12 | Instantáneo real (VPS) | — | Constancia: decisión de negocio, no un lote |

---

### 1 · El hilo termina de sentirse chat — S/M · **recomiendo SÍ, la primera**

**Qué es.** MSG-5 hizo vivo el RECIBIR (la burbuja del otro aparece sola), pero
el ENVIAR sigue siendo de formulario clásico: cada mensaje propio recarga la
página entera y devuelve el scroll arriba. Y hay dos fricciones hermanas medidas
en el código: (a) el hilo ABRE ARRIBA — al entrar se ve el mensaje más viejo de
la página y hay que scrollear a mano hasta el último y el composer (no existe
ningún scroll inicial: el único `scrollIntoView` corre cuando llega burbuja
nueva, `show.blade.php:86-88`); (b) si el servidor rechaza el envío (tope de
1000, por ejemplo), **el texto escrito SE PIERDE** — el composer del hilo no
siembra `old('texto')` (a diferencia de `create.blade.php:27`, que sí lo
conserva). Esta candidata es la continuación natural del hallazgo de viveza del
QA: mismo espíritu, la otra mitad del gesto.

**Qué toca.** Solo el hilo y su controller: `responder()` aprende a contestar
JSON cuando el fetch lo pide (el molde exacto ya existe: `nuevos()` devuelve
`{ultimo, html}` con el partial `_burbuja` — el envío propio reusa ESA misma
respuesta y appendea la burbuja recién creada), el composer se limpia tras el
2xx y conserva el texto ante 422 mostrando el error; scroll inicial al fondo al
cargar página 1; `old('texto')` como respaldo del camino sin JS. De pasada, dos
pulidos del mismo territorio: `aria-live="polite"` en `#hilo-mensajes` (hoy las
burbujas appendeadas no se anuncian a lectores de pantalla) y autoresize simple
del textarea (hoy queda fijo en 2 líneas con scroll interno). Cero migraciones,
cero motor: `Mensajeria::enviar` no se toca.

**Riesgos/deudas.** El form clásico debe seguir funcionando como respaldo (la
guarda offline «necesitas señal» ya intercepta el submit — se conserva tal
cual). Enter-para-enviar queda FUERA a propósito: en el celular Enter es salto
de línea y el equipo es mayormente móvil; cambiarlo sorprendería.

**Recomendación: SÍ y primera.** Es el lote con mejor razón valor/esfuerzo del
catálogo: convierte el chat de «formulario rápido» a chat de verdad usando
piezas que MSG-5 ya dejó puestas, y arregla una pérdida de texto real (la 422)
que contradice la promesa que el QA del dueño acaba de verificar («el texto
permanece»).

### 2 · El correo del chat navega al hilo — S · **recomiendo SÍ**

**Qué es.** Hoy el correo de «Mensaje de {emisor}» llega con el extracto, pero
SIN el botón naranjo «Abrir en DaliGo»: quien lo lee tiene que abrir la app y
buscar el hilo a mano. La campanita SÍ navega al hilo; el correo no.

**Qué toca.** Una línea de payload: `Mensajeria` no pasa `url` al despachar
(`app/Services/Mensajes/Mensajeria.php:76-89`) y la vista del correo
(`emails/notificacion.blade.php:21-33`) pinta el botón SOLO si el payload trae
`url`. Se agrega `url => route('mensajes.show', $conversacion)` + su candado.

**Riesgos/deudas.** Ninguno estructural. Detalle a decidir en el lote: el link
lleva sesión (quien no la tenga aterriza en login y sigue al hilo — conducta
estándar de la casa).

**Recomendación: SÍ.** El correo es EL aviso para quien no vive dentro de la
app (con la ráfaga, es exactamente UNA vez por conversación pendiente); dejarlo
sin destino es un cabo suelto de la v1 más que una feature nueva.

### 3 · Una foto en el hilo — M · **recomiendo SÍ, acotada a foto**

**Qué es.** Adjuntar una foto al mensaje: «mira cómo llegó esta máquina», «este
es el comprobante». En un negocio donde el taller ya vive de fotos (el QR pide
2 obligatorias), la conversación sin imagen queda coja.

**Qué toca.** La infraestructura pesada YA existe y está madura: compresión y
saneo central (`App\Support\ImagenComprimida`: 1280px JPEG q72, corrige EXIF,
re-encode que sanea payloads), componente único `<x-archivo-input>` (candado
que prohíbe el input crudo), disco privado `local` (ninguna URL pública jamás)
y hasta un lightbox listo para copiar (`admin/servicio-tecnico/_fotos.blade.php`).
Lo nuevo: migración aditiva (columna `foto_path` nullable en `mensajes` — o
tabla aparte si se quiere más de una por mensaje; recomiendo columna: UNA foto
por mensaje, como WhatsApp de a uno), validación de la casa
(`mimetypes:` jpeg/png/webp/heic/heif + `max:8192` — jamás la regla `image`,
gotcha HEIC), ruta de descarga autenticada con gate de PARTICIPANTE (mismo
patrón que las fotos de ST), el partial `_burbuja` pinta la miniatura (y como
el endpoint `nuevos` usa el MISMO partial, la foto llega viva al otro lado sin
trabajo extra — el diseño de MSG-5 paga dividendos), y el archivo se guarda
DESPUÉS del commit con try/catch (patrón de la casa: el filesystem no es
transaccional).

**Riesgos/deudas.** (a) Storage del hosting compartido es finito: fotos de chat
se acumulan sin el ciclo de vida de una orden — conviene atarla a la candidata
8 (la retención purga mensaje Y foto); (b) el envío deja de ser un INSERT puro:
sube el peso del POST en señal mala (la guarda online-only ya cubre); (c) UNA
foto por mensaje mantiene el modelo simple — resistir el «y también PDF, y
varias»: eso es Drive, no chat.

**Recomendación: SÍ, acotada.** Es la candidata con más valor de NEGOCIO nuevo
(las demás pulen; esta habilita conversaciones que hoy pasan por WhatsApp
personal con fotos de máquinas de la empresa). El costo real es M, no L,
justamente porque la v1 de uploads de esta casa ya pagó todos los peajes.

### 4 · Buscar en mensajes — M · recomiendo SÍ, sin apuro

**Qué es.** Una caja de búsqueda: «¿dónde me mandaron el número de la guía?».
Hoy no existe ni endpoint ni input; encontrar algo viejo es scrollear páginas
de a 50.

**Qué toca.** El molde de la casa alcanza y sobra: `LIKE %term%` portable
(≈40 buscadores existentes lo usan; FULLTEXT sería la PRIMERA divergencia de
motor del repo — la suite corre en SQLite y no lo soporta sin bifurcar por
driver, cosa que este repo nunca ha hecho y no debería empezar a hacer por
esto), scope OBLIGATORIO a mis conversaciones (`whereIn(conversacion_id, mías)`
— jamás cruzar hilos ajenos: mismo gate de participante de siempre), mínimo 2
caracteres + límite de resultados (molde `buscarCliente`), y el gotcha Ñ
documentado (doble caja en SQLite). El salto al resultado es calculable exacto
con el índice que ya existe (`page = floor(count(id > M)/50)+1` sobre
`[conversacion_id, id]`) + ancla `data-mensaje-id` que cada burbuja ya lleva.
Con decenas de usuarios el corpus son miles de filas, no millones: el LIKE sin
índice de texto está en el mismo orden que los buscadores existentes.

**Riesgos/deudas.** (a) Un resultado viejo aterriza en página histórica = modo
lectura sin poll (guard `onFirstPage()` — coherente, pero hay que decirlo en la
pantalla); (b) el valor crece con el corpus: hoy el chat tiene días de vida —
buscar en 200 mensajes se resuelve con el ojo.

**Recomendación: SÍ, pero sin apuro.** Bien hecha con lo que hay, cero deuda.
La pondría después de 1-3 simplemente porque su valor madura con los meses de
uso, y las otras pagan desde el día uno.

### 5 · Aviso vivo fuera del chat — S+M · recomiendo A MEDIAS

**Qué es.** Hoy, si estás parado en el Inicio (o en cualquier pantalla que no
sea del chat), el badge «Mensajes (2)» del menú NO se mueve hasta que navegas:
se hornea server-side en cada render y el único poll vive en las pantallas del
chat. Dos piezas distintas bajo el mismo título: (a) **título de pestaña** — que
la pestaña diga «(2) DaliGo» cuando hay no-leídos; (b) **shell vivo** — que el
badge del menú y la campanita se refresquen solos en TODA la app.

**Qué toca.** (a) es S dentro de las pantallas del chat (el poll ya trae la
firma; `document.title` es una línea)… pero la verdad incómoda: con la pestaña
OCULTA el guard de visibilidad corta el poll a propósito (para no gastar red de
fondo), o sea el «(2)» aparecería justo cuando ya estás mirando. Para que sirva
de verdad hay que pollear oculto a intervalo lento (30-60 s) — una excepción
deliberada al guard, solo para un JSON liviano. (b) es un poll global del
SHELL: cada usuario en cualquier pantalla pide el conteo cada X s — el acta ya
lo deslindó («si el dueño después quiere campanita viva, es un proyecto del
shell, no de este módulo», §5.4) y con razón: multiplica requests por TODOS los
usuarios × TODAS las pantallas, y de paso le tocaría también a la campanita M15
(coordinación con territorio compartido).

**Riesgos/deudas.** Carga transversal del hosting compartido (el mismo LiteSpeed
que sirve producción); el shell es de todos los módulos — un lote ahí pide
coordinación de flota.

**Recomendación: A MEDIAS.** El título de pestaña con poll lento oculto: SÍ
(S, vive solo en las pantallas del chat, mejora real para el que trabaja con
la pestaña abierta de fondo). El shell vivo: NO como lote de Mensajes — si el
dueño lo quiere, es un proyecto propio del shell con su propio F0, como el
acta ya anotó.

### 6 · «Visto» sí, «escribiendo» no — M / — · recomiendo según el dueño

**Qué es.** (a) «Visto»: el emisor sabe si el otro ya leyó (el doble check).
(b) «Escribiendo…»: ver que el otro está tecleando.

**Qué toca.** (a) La lectura YA se registra server-side (marcarLeida baja el
contador; el request de `nuevos` marca al traer) — lo que falta es MOSTRARLA al
emisor. Costo honesto: hoy no hay `leido_at` por mensaje; la forma barata y
suficiente es un `leido_hasta_id` POR LADO en `conversaciones` (migración
aditiva, dos columnas), que `marcarLeida` ya sabría poblar, y el JSON de
`nuevos` — que YA viaja cada 4 s — carga un flag extra para pintar el check en
las burbujas propias. Sin tabla nueva, sin write extra (se escribe donde ya se
escribía). (b) «Escribiendo» NO tiene canal digno sin websockets: sería
escribir estado efímero en BD a cada tecla y leerlo por poll — escrituras
basura contra MySQL compartido para un dato que caduca en 2 segundos.

**Riesgos/deudas.** El «visto» cambia la RELACIÓN, no solo la pantalla: mete la
presión social del doble check en un equipo chico («me dejó en visto»). Eso es
una decisión de cultura del equipo, no técnica — por eso no lo recomiendo yo:
lo decide el dueño con esa carta sobre la mesa.

**Recomendación.** Escribiendo: **NO** (pelea contra la infraestructura y el
poll de 4 s ya hace que la respuesta aparezca casi al tiro — el valor marginal
es bajísimo). Visto: técnicamente limpio y barato (M chico); si al dueño le
gusta el doble check, se puede; si duda, el chat vive perfecto sin él.

### 7 · Difusión / grupos — M / L · recomiendo NO por ahora

**Qué es.** (a) Difusión: un mensaje a varios de una vez («a todos los
conductores: mañana se sale 7:30»). (b) Grupo real: un hilo compartido donde
todos ven todo.

**Qué toca — el costo de verdad, sin vender fácil.** El 1-a-1 está HORNEADO en
tres lugares del motor: el unique `(user_menor_id, user_mayor_id)` del schema
(una fila por PAR — no existe tabla de participantes), los contadores que son
DOS columnas fijas por lado (`no_leidos_menor/mayor` — toda la mecánica de
`columnaContadorDe`/`otroLado`/`noLeidosDeUsuario` asume exactamente dos
lados), y `entre()` que canonicaliza min/max. Un **grupo real es L**: tabla
pivote de participantes con contador por miembro, reescribir la firma del
poll, el badge, la ráfaga por miembro, el gate de participante en 4 endpoints,
y las pantallas — es medio plan nuevo, no un lote. La **difusión es M** y NO
toca el modelo: un solo formulario que crea N hilos 1-a-1 (`entre()` reusa los
existentes) y manda el mismo texto por `Mensajeria::enviar` en loop — cada
receptor responde en SU hilo privado.

**Riesgos/deudas.** La difusión duplica un canal que YA existe: para avisos
formales está M15 (campanita + correo + plantillas + preferencias). Un botón
de difusión en el chat invita a usarlo de megáfono y llena N bandejas con el
mismo texto sin las reglas de M15. El grupo real, además del costo L, cambia
el carácter de la herramienta (moderación, quién agrega a quién, historial de
entradas/salidas — deudas que ni WhatsApp resuelve bien).

**Recomendación: NO por ahora.** Si aparece el caso real («necesito avisar a
los 5 conductores a la vez y que respondan»), la difusión-como-N-hilos es la
respuesta barata y honesta. El grupo real solo con un caso de negocio que M15
y la difusión no cubran — hoy no lo veo.

### 8 · Retención configurable — S · recomiendo SÍ como higiene

**Qué es.** Cuánto tiempo se guardan los mensajes (deuda anotada en el acta:
«retención para siempre en v1», ratificada por el dueño). Una perilla
`mensajes_retencion_dias` en Configuración: 0 = para siempre.

**Qué toca.** El molde nivel-1 de la casa completo: clave en `configuracion` +
UI con label/ayuda en español + validación por RANGOS (el mapa clave→[min,max]
de `ConfiguracionController` — un mínimo sano tipo 90 días para que un dedazo
no borre la historia), y la purga como comando en el scheduler (grilla `*/15`,
`hourlyAt` — I-01). Borra por `created_at` con el cascade ya puesto; si la
candidata 3 (fotos) entra, la purga borra TAMBIÉN el archivo del disco (patrón
`VehiculoDocumentoController::destroy`). **Deslinde declarado:** aunque el acta
la llamó «candidato nivel-1 de PARAMETRICOS», Mensajes quedó explícitamente
FUERA de ese plan (PLAN-PARAMETRICOS §4: es entero de Max-1; Mensajes es el
frente de Max-2) — si se hace, es un lote de ESTA fase 2 usando los moldes de
la casa, sin pisar territorio.

**Riesgos/deudas.** Borrar mensajes rompe la «traza honesta» hacia atrás — es
la MISMA decisión que la retención de auditoría que ya existe: el dueño ya
tiene el criterio. El chat vivo no se inmuta (ids monótonos: purgar viejos no
toca `desde`).

**Recomendación: SÍ, sin apuro.** Volumen trivial hoy (36k mensajes/año
estimados), pero es S, cierra una deuda escrita, y si entran las fotos deja el
ciclo de vida resuelto ANTES de que el storage lo cobre.

### 9 · Mensajes sin señal (encolar offline) — M · dormida

**Qué es.** Hoy sin señal el chat NO envía (guarda deliberada: «Necesitas señal
para enviar el mensaje. Lo escrito se conserva») — el dueño eligió online-only
en v1. La candidata: encolar el mensaje y que salga solo al volver la señal,
como las tandas del soplador.

**Qué toca.** El molde completo existe (cola offline de tandas: IndexedDB +
UUID de cliente + unique compuesto + clasificación permanente/transitorio); la
tabla solo necesita `cliente_uuid` con su unique (migración aditiva anotada en
§5.1). M por los bordes: orden de llegada vs orden de envío, y el hilo abierto
del receptor que appendea por id.

**Riesgos/deudas.** Un chat donde lo enviado «sale después» confunde más que
un aviso claro de «sin señal» — en conversación (a diferencia de un registro
de producción) el contexto caduca: puede llegar una respuesta a una pregunta
que ya se resolvió por teléfono.

**Recomendación: dormida.** Solo si el uso real la pide (operarios de planta
chateando en zonas sin señal). La decisión online-only del dueño sigue siendo
la correcta para conversación.

### 10 · Editar/borrar con ventana — M · recomiendo NO

**Qué es.** Poder corregir o retirar un mensaje durante N minutos tras enviarlo
(semilla del Director: «la v1 decidió traza honesta — ¿cambia el dueño de
opinión?»).

**Qué toca — el trade-off completo.** No es solo el CRUD: (a) el chat vivo
asume ids monótonos append-only (`desde = max id`) — un mensaje editado JAMÁS
se re-trae: las pantallas abiertas del receptor seguirían mostrando el texto
viejo hasta recargar, o sea **la edición miente en vivo** salvo que el JSON de
`nuevos` aprenda también a re-pintar editados (más estado, más candados); (b)
borrar deja huecos en conversaciones que hoy son su propia traza (el modelo lo
declara: «no se edita ni se borra — traza honesta», sin Auditable a propósito);
(c) aparecen rutas PUT/DELETE nuevas con gates de autor+ventana, y la pregunta
incómoda de siempre: ¿editado se marca? ¿el receptor que YA lo leyó se entera?

**Riesgos/deudas.** En un equipo de trabajo el mensaje enviado es un hecho
operativo («me dijiste 500 preformas») — la editabilidad convierte el chat en
terreno de disputa. La traza honesta es una FEATURE de negocio, no una
limitación técnica.

**Recomendación: NO.** El costo técnico es M pero la deuda conceptual es
grande y el beneficio real (corregir un typo) no la paga. Si el dueño quiere
suavizar el caso del typo, la alternativa barata es cultural: mandar la
corrección como mensaje siguiente, como en cualquier equipo.

### 11 · Push PWA al teléfono — L · recomiendo NO en este hosting

**Qué es.** La notificación de verdad en el celular: la app cerrada y suena
«Mensaje de Marcos». La semilla pide honestidad con los límites — acá va.

**Qué toca — el costo completo en ESTE hosting.** Hoy hay CERO piezas: el SW
es passthrough sin handler `push`/`notificationclick`, no hay librería, no hay
tabla de suscripciones, ningún JS llama a `pushManager` (grep: 0 usos). Push
real (VAPID) exige: paquete composer nuevo (`minishlink/web-push`) ⇒ **`composer
install` MANUAL por SSH** (el deploy no instala — gotcha documentado, y
acabamos de ver a HostGator borrar su composer sin aviso, `69d4aee`); llaves
VAPID en env; tabla de endpoints por usuario/dispositivo + endpoint de
suscripción + JS de opt-in; handlers nuevos en `sw.js` (bump de versión); y la
librería recomienda gmp/bcmath para ECDSA — **no verificable desde el repo si
ea-php83 las trae**. Latencia: colgado del job de notificaciones existente =
hasta 15 min por la grilla del cron (**mata el propósito de un push**);
síncrono en el request del emisor = N round-trips HTTPS a los push services
dentro del request de quien envía. Y la mitad del equipo: en iOS el Web Push
solo existe en PWA instalada al home screen (16.4+) — el que abre por Safari
no recibe nada. La alternativa barata (Notification API local, sin servidor)
NO cubre iOS en absoluto y solo funciona con la pestaña ya abierta — es la
candidata 5a, no un push.

**Riesgos/deudas.** Es la pieza con más superficie de fallo silencioso del
catálogo (suscripciones que caducan, endpoints muertos, permisos revocados) en
el hosting que ya nos reescribe crons y borra binarios.

**Recomendación: NO acá.** El día que el instantáneo real justifique un VPS
(candidata 12), el push entra en el mismo paquete con websockets — hacerlo dos
veces es pagar el peaje L dos veces. Mientras tanto: la ráfaga + correo
navegable (candidata 2) + el poll de 4 s cubren el 90% del valor con el 5% del
riesgo.

### 12 · Instantáneo real (VPS) — constancia, no un lote

Queda constancia (el dictado lo pide): mensajes en 0 s = websockets = migrar a
VPS. Es una decisión de NEGOCIO (costo mensual + migración + quién administra)
que arrastra mucho más que el chat: colas por-minuto, push, campanita viva y
el fin de la grilla `*/15`. No es un lote de esta fase; es una puerta que el
dueño abre cuando el negocio lo pida. El poll de 4 s («casi instantáneo», QA
del dueño) es el techo de ESTE hosting — y ya lo tocamos.

---

**Entrega F0-MENSAJES-2 (2026-08-27, Max-2):** catálogo de 12 candidatas para
veredictos del dueño. Sugerencia de empaque si aprueba las recomendadas:
MSG-6 = candidatas 1+2 (el gesto completo, S/M) · MSG-7 = candidata 3 (la foto,
M) · MSG-8 = candidatas 8+4 según apetito.

**VEREDICTOS DEL DUEÑO AL CATÁLOGO §7 (2026-08-27, al Director):** APROBADAS
las candidatas **#1** (el hilo se siente chat), **#2** (botón «Abrir en DaliGo»
en el correo), **#3** (foto en el hilo) y **#6-Visto** (el doble check SÍ; el
«escribiendo» NO). **En COLA** (agendadas sin apuro): #4 buscar en mensajes ·
#8 retención configurable · #5a título de pestaña con no-leídos. **Los 4 NO
aceptados con los porqués de Max-2**: #7 grupos/difusión (M15 es el canal de
avisos) · #9 offline (dormida) · #10 editar/borrar (traza honesta) · #11 push
PWA (hosting). #12 queda de constancia (VPS = negocio). **Lotes de fase 2:
MSG-6 = #1+#2 (empaque de Max-2, dictado v36) → MSG-7 = #3 foto → MSG-8 =
#6 Visto → cola (#4/#8/#5a).**
