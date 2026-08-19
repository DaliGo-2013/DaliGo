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

## 4. Coordinación de flota

- **Max-2 forja** (su primer proyecto fuera del territorio PWA/M11) — modelo Fable 5
  fijado por el dueño.
- **Max-1 corre PLAN-PARAMETRICOS en paralelo** (Dashboard → Comercial): territorios
  disjuntos, pero AMBOS con re-fetch religioso — Marcos también sigue activo.
- Los dos proyectos no se cruzan en archivos salvo `MenuPrincipal` (si el F0 propone
  ítem) y `Notificacion::EVENTOS` — el Director secuencia esos merges.

## 5. Anexo — diseño F0 (lo llena Max-2)

*(pendiente: dictado v25)*
