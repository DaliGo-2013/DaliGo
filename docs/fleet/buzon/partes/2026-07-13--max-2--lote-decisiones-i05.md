# Parte de cierre — Max-2 · Lote DECISIONES + I-05 · 2026-07-13

CUENTA: Max-2 · Forjador B (stream 2) · Opus 4.8 · high
TAREA: dictado v2 §1 (lote DECISIONES.md) + expediente I-05 (del dictado anterior), con los anexos del buzón
ESTADO: HECHA. Push único al ramo `feature/m15-notificaciones` (docs-only; sin tocar código ni main).

EVIDENCIA (commit de este parte):
- **DECISIONES.md:**
  - D-001 → TOMADA (nombre = DaliGo). D-009 → DESCARTADA. D-010 → CERRADA.
  - D-002 → TOMADA como ESTRATEGIA (accesos al cierre de cada módulo) + matriz módulo×rol del Investigador anexada como referencia.
  - D-012 → TOMADA (repo público por política del dueño; expediente I-04).
  - D-007 → APLAZADA (stub WhatsApp hasta nueva orden).
  - D-003 → respuesta de Ricardo anexada (16 bodegas: 4 físicas, 5 cerradas/prueba, 2 de ST DISTINTAS no-duplicadas, Contenedores=recepción de importación; Luis pendiente por Santa Rosa / Reserva Sucursales). M04 pospuesto → sin fecha crítica.
  - D-004 → respuesta de Melisa anexada (dolor NO es el cobro; sí conciliación transferencia/Webpay + traspaso entre sucursales; Getnet cuadre tarjeta → M13).
  - D-006 → info de zonas de Héctor + modelo `zonas` del Director (catálogo simple vendedor↔zona; alimenta DESPACHOS-v1).
  - D-005 → REDEFINIDA: "Víctor" = sysadmin INTERNO de DALI (NO contacto Bsale). Corregido en DECISIONES, RUTA §0, BSALE_API.md, PROYECTO_DALIGO.md y nota en CORRECCIONES-LUIS.md. Ruta = docs oficiales + soporte Bsale; pregunta clave nueva (endpoint `documents` filtrable por fecha) subió por el pivote a despachos.
- **I-05 archivado:** `docs/qa/INFRA/2026-07-08--INFRA--servidor-comprometido-i05.md` (respuesta íntegra 11/11 + aclaración del Director). Cuenta HostGator comprometida desde Nov-2022 (405 webshells/backdoors), ANTERIOR a la app y sin afectarla (crontab limpio, llave de deploy legítima por fingerprint, claves ya rotadas) → remediación asignada a Víctor, fuera del alcance de la app. 3 `.env.bak` muertos borrados.
- **I-04 expediente** (del ciclo anterior, incluido en el mismo push): `docs/qa/INFRA/2026-07-08--INFRA--i04-gitguardian-barrido-deploykey.md`; endpoint SSH redactado en 2 docs (hallazgo bajo del barrido).
- **RUTA-MAESTRA:** marcas `[B:D-001]`/`[B:D-002]` retiradas; panel §0 y bloqueos actualizados; stream 2 → PIVOTE A DESPACHOS.
- **BITACORA-SESIONES:** entrada del lote.

TESTS: n/a (docs-only; cero código tocado).
/usage: ← Mauricio completa. Sesión en Opus 4.8 ✓.

SIGUIENTE (dictado v2 §2): **PLAN-DESPACHOS-V1 sellado** — carve-out M05-parcial + M07 + M08-MVP (espejo de documentos Bsale + entidad `despacho` con estados/zona + QR anti-fraude con pantalla de cola + PWA conductor sobre el memo SPIKE-PWA; M04 pospuesto). Gate: visto bueno de Mauricio ANTES de la primera migración. Luego §3 micro-backlog M15.
