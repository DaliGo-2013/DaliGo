# Parte de situación — Max-2 · asiento libre, el dueño pide plan

CUENTA: Max-2 (Forjador B, stream 2) · MODELO: Fable 5 (fijado por el dueño)
TAREA: ninguna — parte de SITUACIÓN a pedido del dueño
ESTADO: **EN PAUSA con ventana disponible** — el dueño pide al Director un plan
para este asiento (dictado v24).
/usage INICIO → FIN: n/d (lo captura el dueño)
SIGUIENTE: lo que dicte v24.

## Situación

- **v23 recibido y verificado**: P-M11-23 EN PRODUCCIÓN (merge `e180a6f`,
  doble llave 12-ago, 1961/13.917). PLAN-M11-FINAL **100% construido**. Rama
  borrada; nada pendiente del stream B.
- Working tree limpio sobre `feature/m11-kaizen` (ya mergeada); re-fetch al
  arrancar cualquier lote nuevo, como siempre.
- La sesión de este asiento sigue ABIERTA con ventana disponible: el dueño
  prefiere usarla en un lote nuevo antes que cerrarla.

## Contexto fresco utilizable (para dimensionar el GO)

- Territorio PWA/soplador/jefe completo en memoria de trabajo: cola offline
  idempotente (uuid + lock + unique, en sus dos variantes de ancla),
  mi-reporte y sus tres ramas, panel del jefe, M15, FechaNegocio/DST, grilla
  I-01, doctrinas de gates (mutación en dos sentidos, texto percibido,
  volcado con BD en memoria + `URL::forceRootUrl`).
- Ese contexto rinde más en lotes de ese territorio, pero el asiento toma lo
  que se dicte — los moldes de la casa están todos en la bitácora.

## Candidatas que el propio v23 nombró (decisión del dueño)

1. **P-DSP-10** si volvió la ronda 2 de Luis.
2. **Lotes de la cola de densidad del menú** si el dueño aprueba más
   consolidaciones y quiere dos manos (coordinación de territorio con Max-1,
   que la corre hoy).
3. Lo que el Director estime — el mapa avanzó a 32 y el Bloque B está
  abierto según el ledger de main; este asiento no conoce ese detalle y no
  opina fuera de su territorio.

## Pedido

Dictado **v24** en `docs/fleet/buzon/dictados/max-2.md` con GO (o la
confirmación de seguir en pausa). Este asiento revisa el buzón al recibir la
orden del dueño.
