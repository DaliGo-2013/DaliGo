# Parte de cierre — Max-2 · F0-MENSAJES · Diseño del chat interno (SOLO DOCS)

CUENTA: Max-2 (Forjador B, stream 2) · MODELO: Fable 5 (fijado por el dueño)
TAREA: **F0-MENSAJES** — GO del dictado v25 (PLAN-MENSAJES §3)
ESTADO: **HECHA** — anexo §5 completo en `docs/planes/PLAN-MENSAJES.md`, cero código
/usage INICIO → FIN: n/d (lo captura el dueño)
SIGUIENTE: visto bueno del dueño al diseño → lotes MSG-1…4 (v26+).

## Resumen del diseño (el detalle vive en el anexo §5)

1. **Datos**: `conversaciones` (par canónico menor/mayor + `ultimo_mensaje_at` +
   contadores de no-leídos POR LADO) + `mensajes` (append-only, texto ≤1000).
   Enviar = transacción con lock sobre la conversación (+1 al contador del otro);
   leer = mi contador a 0. Lista, no-leídos y badge salen de queries indexadas
   sobre `conversaciones` — sin GROUP BY por par en MySQL 5.7.
2. **Pantallas** (3, ancho formulario): lista con el molde exacto de la bandeja de
   notificaciones (tinte + punto + contador), hilo con burbujas paleta-4 y
   paginación estándar, nuevo-mensaje con `<x-select>` precargado.
3. **M15**: evento `mensaje.recibido` (37º) con plantilla nueva (sin one-shot),
   notificable = la Conversacion, y los DOS match (`urlDestino` + `urlDestinoPara`).
4. **Refresco**: poll de firma 20s del molde vivo/cola-bodega (4º uso), solo en
   las pantallas del chat; campanita/menú sin poll (doctrina vigente).

## Recomendaciones donde el dictado dio a elegir (decide el dueño)

- **Anti-spam: aviso por RÁFAGA** — despacha solo si el receptor tenía 0 no-leídos
  en ese hilo; al leer, el próximo vuelve a avisar. Chat activo de 40 mensajes =
  1 campanita + 1 mail. Digest descartado con argumento: la grilla */15 le mete
  hasta 15 min de latencia y exige estado nuevo; la ráfaga es una comparación
  sobre el contador que ya existe (primo de la racha del SIC).
- **Ubicación: ítem de PRIMER NIVEL «Mensajes»** (menú 32→33) — transversal a
  todos los roles, familia de Mi producción/Mis entregas/Aprobaciones. El ícono
  hermano de la campanita se rechaza: canal ≠ lugar, y tocaría el shell de
  contratos finos (CampanitaTest/SidebarTest) que el ítem no toca.
- **Permiso nuevo `usar mensajes`** asignado a TODOS los roles (soplador y
  conductor incluidos: son a quienes esto les reemplaza WhatsApp), apagable por
  rol sin deploy — precedente `simular carga`.
- **Online-only en v1** (alternativa nombrada, como pidió el dictado): un chat
  sin señal tampoco recibe respuesta; el form avisa «necesitas señal» con
  `$store.red`. La cola offline queda como lote futuro (migración aditiva de
  `cliente_uuid` si se pide).
- **Retención: para siempre en v1** (volumen trivial con equipo de decenas);
  retención configurable anotada como futuro nivel-1 de PLAN-PARAMETRICOS.

## Lo que más me llamó la atención

- **El anti-spam correcto ya estaba pagado**: al elegir contadores por lado para
  los no-leídos, «avisar solo al pasar de 0» sale gratis — el diseño de datos y
  el de notificaciones son la misma decisión, no dos.
- **`urlDestinoPara` tiene `default => false`**: un evento nuevo que solo toca
  `urlDestino()` queda NO navegable en silencio. Va como candado de MSG-1.
- **No existe autocompletado de usuarios en la casa y NO hace falta**: los
  listados de usuarios ya van con `->get()` (decenas); `<x-select>` alcanza y
  `<x-buscador-remoto>` queda de reserva si el equipo crece.
- **El molde de poll está en su 4º uso** — vivo, cola de bodega, por-confirmar de
  ST y ahora el chat: quizá amerite extraerse a componente en MSG-3 (lo decidirá
  el lote, no lo fuerzo desde el diseño).

## Riesgos aceptados y declarados

- **Marcar leído en el GET del hilo**: idempotente y solo estado propio; sin
  speculation-rules ni prefetch en esta app (SW passthrough). Declarado para el
  gate en vez de esconderlo.
- **Usuario eliminado → cascade se lleva sus hilos**: nullOnDelete rompería el
  unique del par canónico; en la práctica se desactivan roles, no se borran
  usuarios.

## Mapa de lotes (anexo §5.7)

MSG-1 (M, backend puro testeable) → MSG-2 (M, pantallas) → MSG-3 (S, poll) →
MSG-4 (S, menú+badge al FINAL, como pidió el dictado). Cruces con
PLAN-PARAMETRICOS: solo `Notificacion::EVENTOS` (MSG-1) y `MenuPrincipal`
(MSG-4) — el Director secuencia.
