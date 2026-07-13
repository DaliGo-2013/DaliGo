# Parte de cierre — Max-2 · PLAN-DESPACHOS-V1 sellado · 2026-07-13

CUENTA: Max-2 · Forjador B (stream 2) · Opus 4.8 · high
TAREA: dictado v2 §2 — redactar y sellar PLAN-DESPACHOS-V1 (carve-out M05-parcial + M07 + M08-MVP)
ESTADO: HECHA (plan sellado). **NO arranco código** — el dictado exige visto bueno de Mauricio antes de la 1ª migración. Push a la rama nueva.

EVIDENCIA:
- Rama nueva `feature/despachos-v1` desde main FRESCO (`fcb9466`) — no seguí en el cajón viejo `feature/m15-notificaciones`.
- **`docs/planes/PLAN-DESPACHOS-V1.md`** sellado (línea 2: VIGENTE 2026-07-13 commit `fcb9466`), estructura calcada de PLAN-M14/M15 (§0 verificación con spot-checks reales → §5 delegaciones).
- Sellado CONTRA el código con 3 exploradores paralelos (patrón syncs Bsale, patrón QR M12 + superficie compartida, sello/SPIKE/zonas).
- RUTA-MAESTRA: unidad DESPACHOS-v1 registrada en §5 con P-DSP-00..07 `[ ]` + enlace al plan; E3·M04 marcado POSPUESTO; panel §0 actualizado.

DISEÑO EN UNA LÍNEA: espejo read-only de documentos Bsale (sync #5, upsert sin delete — documentos inmutables) → entidad `despacho` con zonas → QR anti-fraude de retiro (lock + alerta doble retiro + cola McDonald's) → PWA conductor firma+foto+hora offline sobre el memo SPIKE-PWA.

DECISIONES QUE PIDO CONFIRMAR EN EL VISTO BUENO:
1. **P-DSP-00 primero (riesgo #1):** el shape del nodo `details` de `documents.json` es DOC-ONLY (nunca explorado — el Anexo A verificó products/prices/stock/offices pero NO documentos). Propongo un GET real read-only `documents.json?limit=3&expand=[details,client,office]` ANTES de congelar la migración. ¿OK?
2. **Sin modified-since:** Bsale solo filtra documentos por fecha de EMISIÓN. Cambios post-emisión (pago, rechazo SII, nota de crédito) → v1 re-barre una ventana móvil; el fiel real es webhook (fuera de v1). ¿Aceptas la ventana móvil para v1?
3. **Zonas (D-006):** implemento el modelo del Director (tabla `zonas` + `users.zona_id`, zona del cliente derivada de `vendedor_id`). Semilla Norte/Sur/6ª/7ª. ¿OK sin esperar los límites exactos de Luis?
4. **M14 (P-DSP-06/07):** la aprobación sobre umbral se cablea SOLO tras el merge de M14 a main (dependencia de la rama de Max-1). P-DSP-01..05 no dependen de M14. ¿Confirmas el orden?
5. **Permisos nuevos:** `manage despachos` (jefe_bodega) + `confirmar entrega` (conductor). ¿OK?

TESTS: n/a (documento; cero código/migración este turno — gate respetado).
/usage: ← Mauricio completa. Sesión en Opus 4.8 ✓.

SIGUIENTE: visto bueno de Mauricio → P-DSP-00 (exploración read-only) → P-DSP-01 (espejo). En paralelo queda §3 del dictado (micro-backlog M15, baja prioridad).
