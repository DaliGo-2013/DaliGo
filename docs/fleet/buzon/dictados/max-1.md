# Dictado vigente — Max-1 (Forjador A, stream 1)
> Emitido por el Director el 2026-07-30 (v32 — GO M13 Devoluciones, prioridad ★ del dueño; arranque desde cero con plan sellado antes de la primera migración). Manda sobre lo anterior.

MODELO: Opus 4.8 · high (diseño de módulo nuevo; xhigh solo si el modelo de datos se resiste).

## ✅ Contexto: tu gate R-31 está EN PRODUCCIÓN y E-NAV CERRADA (v31 sigue válido como historia)
Merge `ba4944b`, Deploy+Tests verdes, QA celular del dueño OK, campanita = entrada a propósito.

## 🟢 GO M13 — Devoluciones (E6), módulo desde CERO (L)
**El dueño lo pidió como prioridad ★ y hoy está en 0 % — cero código.** No confundir con la
acción `devolver` de M11 ni con el taller M12 (nota §5.6 de RUTA-MAESTRA: esa confusión ya
pasó una vez). Fuente: RUTA-MAESTRA §E6 (P-M13-01..04) + biblia flujo A-12.

**Rama:** `feature/m13-devoluciones` desde main FRESCO (baseline suite 1199).

### Orden de trabajo
1. **PRIMERO el plan, no el código:** `docs/planes/PLAN-M13.md` (1-2 páginas, patrón de
   PLAN-M14/M15) con: modelo de datos propuesto, rutas (públicas y admin), estados de la
   devolución, integración con M14 (reembolso) y M15 (avisos), y el recorte de alcance de
   abajo. Parte al buzón con el plan → **VISTO BUENO del dueño antes de la PRIMERA
   migración** (misma regla que M14). Mientras esperas puedes adelantar lo que no persiste:
   esqueleto de rutas firmadas, form Blade, validaciones.
2. Con el visto bueno: **P-M13-01** (formulario público del cliente: ruta SIN auth con token
   firmado + fotos obligatorias) y **P-M13-02** (categorización transporte/fábrica/otro +
   reglas automáticas por tipo y origen).
3. **P-M13-03 entra RECORTADO:** el reembolso vía M14 si ≥ umbral SÍ (el motor existe;
   cablear una acción nueva de aprobación es terreno conocido). El **reingreso automático a
   stock NO se construye**: necesita movimientos propios de M04 y M04 es hoy un espejo
   read-only bloqueado por D-003. Déjalo como estado terminal «apta para reingreso» +
   notificación M15 a bodega, y el hook documentado para cuando M04 exista. Esta es la
   parte que el plan de la ruta maestra suponía lista y no lo está — dilo explícito en
   PLAN-M13.md para que el dueño lo apruebe con los ojos abiertos.
4. **P-M13-04** (reportes por causa y canal) queda para un segundo lote — no lo metas en este.

### Seguridad — es una ruta PÚBLICA con upload, trátala como frontera hostil
- Token firmado con expiración (patrón del QR de retiro y del portal M12); throttle por IP;
  el link público NO revela datos del cliente más allá de lo imprescindible.
- Fotos: validar mime/size en servidor, storage privado (no `public/`), nombres generados
  (nunca el nombre original), y límites de upload de cPanel verificados ANTES de fijar los
  del validador («hecho cuando» de E6 lo exige vía IA-cPanel — pídele el dato al dueño en
  el parte del plan si no puedes verificarlo tú).
- La asociación a documento de venta va como folio de referencia (texto validado) + cliente
  del espejo M03: la emisión propia (M05) aún no existe y no debes depender de ella.

### Territorio
- **Marcos sigue construyendo M05 Facturación/DTE** — ni de refilón.
- **Max-2** está en P-DSP-05 (PWA del conductor, despachos). M13 no comparte archivos con
  despachos; si un cruce aparece (p. ej. notificaciones), gana el que llegó primero a main
  y el otro rebasea.

## Recordatorios
Suite COMPLETA antes de cualquier push (baseline **1199**). Blade tocado → build + grep
superset. Conflictos con `git checkout origin/main -- <archivo>`, nunca con `>` (el `>` de
PS 5.1 mete BOM y revienta Vite). Parte al buzón → doble llave.

CIERRE: parte a docs/fleet/buzon/partes/ + push.
