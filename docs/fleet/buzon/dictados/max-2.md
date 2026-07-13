# Dictado vigente — Max-2 (Forjador B, stream 2)
> Emitido por el Director el 2026-07-13 (v2 — PIVOTE A DESPACHOS por directiva del dueño).
> Este archivo manda sobre instrucciones anteriores.

MODELO: Fable 5 disponible hasta el 19-07 (decisión del dueño usarlo); si no, Opus 4.8 · high.

COLA (en orden):

## ✅ DESBLOQUEADO 13-07: los 4 insumos que pediste YA ESTÁN EN EL BUZÓN (no esperes pastes)
- Matriz D-002, respuestas de Ricardo (D-003), zonas de Héctor (D-006) y BONUS respuestas de
  Melisa (D-004) → `docs/fleet/buzon/anexos-decisiones.md` (recopilados y verificados por el
  Director; usa esos textos verbatim para las fichas).
- Respuesta ÍNTEGRA de I-05 (405 .php, tabla 11 pasos) → `docs/fleet/buzon/anexo-i05-respuesta-integra.md`;
  archívala tal cual en docs/qa/INFRA/2026-07-08--INFRA--servidor-comprometido-i05.md + bitácora.
Con eso cierras el lote SIN depender de Mauricio.

## 1. LOTE DECISIONES.md (rápido, un push)
- D-001 TOMADA (Mauricio, 08-07): nombre = DaliGo. D-009 DESCARTADA. D-010 CERRADA.
- D-002 TOMADA como estrategia: accesos se definen al CERRAR cada módulo; matriz del
  Investigador archivada en la ficha como referencia (te la entrega Mauricio).
- D-003: despachada 08-07; **Ricardo YA respondió su parte (13-07)** — Mauricio te pega sus
  respuestas para anexarlas; Luis con recordatorio pendiente.
- D-005: REDEFINIR — Víctor es sysadmin INTERNO (no contacto Bsale); rutas: docs oficiales +
  correo a soporte (texto en buzon/dictados/navegador.md).
- D-006: anexar info de zonas de Héctor + recomendación del Director (catálogo simple
  vendedor↔zona, suplencia temporal del jefe; alimenta DESPACHOS-v1).
- **D-007 APLAZADA (Mauricio, 13-07): no bloquea nada; el canal WhatsApp queda en stub hasta
  nueva orden. La ficha conserva la investigación del 08-07 para cuando se retome.**
- Expediente I-05 a docs/qa/INFRA/ + bitácora (pendiente del dictado anterior).

## 2. PLAN-DESPACHOS-V1 sellado (reemplaza a PLAN-M04 — M04 pospuesto por el dueño)
Unidad carve-out "DESPACHOS-v1" sobre 3 módulos de la biblia (M05 parcial + M07 + M08 MVP).
Alcance dictado por el Director (el plan lo refina y lo sella contra el código):
  a. **Espejo read-only de DOCUMENTOS de venta Bsale** (sync #5, mismo patrón de las 4
     existentes; grilla */15; guard de whereNotIn-vacío de la bitácora). La emisión sigue
     en Bsale — NO se rehace (regla de la biblia).
  b. **Entidad `despacho`** sobre el documento espejado: estados cargada → en ruta →
     entregada (+transportista, +zona). Catálogo `zonas` según D-006 (info de Héctor).
  c. **M07 QR anti-fraude de retiro** (prioridad MVP, caso real de fraude): QR único por
     documento, escaneo en puesto de bodega, validación en tiempo real, ALERTA de doble
     retiro, marcado entregado total/parcial, pantalla de cola "tipo McDonald's" (Luis).
     Nota: M12 ya usa QR — revisa qué se reutiliza de ese patrón (chunk qrcode ya está).
  d. **M08-MVP conductor**: hoja de ruta del día por zona + PWA de confirmación de entrega
     (firma + foto + hora). El memo docs/SPIKE-PWA.md es el insumo DIRECTO — su checklist
     de adopción anticipó los problemas nuevos (Blobs de firma/foto offline, hora capturada
     offline). Cotizador de transportistas y venta-en-ruta QUEDAN FUERA de v1.
El plan separa qué depende de datos de Bsale aún no confirmados (¿endpoint documents con
filtro por fecha? — probable; verificar contra BSALE_API.md y docs oficiales) y qué no.
GATE: visto bueno de Mauricio ANTES de la primera migración, confirmado AL DIRECTOR.
Coordinación anti-colisión: M14 sigue vivo en la rama de Max-1 y M12 (Marcos) toca QR/nav —
mapea la superficie compartida en el plan (§3, patrón PLAN-M14).

## 3. Micro-backlog M15 (baja prioridad — después del plan)
Correo destino en panel, error sin truncar, test de humo.

CIERRE por ítem: parte a docs/fleet/buzon/partes/ + push.
