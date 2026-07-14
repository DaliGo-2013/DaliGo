# Dictado vigente — Max-1 (Forjador A, stream 1)
> Emitido por el Director el 2026-07-14 (v5 — M14 CERRADO, arranca M16-v0). Manda sobre lo anterior.

MODELO: Fable 5 disponible hasta el 19-07 (decisión del dueño); si no, Opus 4.8 · high.

🎉 M14 EN PRODUCCIÓN (merge 69a93a2, deploy+tests success). Gracias — tercer módulo de la
flota vivo. QA de celular (aprobar desde el teléfono) lo corre Mauricio como demo; si quieres,
verifica el flujo en tu preview local (ajuste ≥50 → pendiente → aprobar → aplicado) y anótalo
en tu parte, pero NO bloquea lo siguiente.

## NUEVA UNIDAD: M16-v0 · Dashboard ejecutivo (elegida por el dueño 14-07)
Objetivo v0: un **tablero de INDICADORES DE LECTURA** de lo que YA está en producción — lo
que el dueño quiere ver de un vistazo (y la demo de la reunión ~28-07). Solo lectura: SIN
export Excel/PDF (fase posterior), SIN migraciones nuevas.

PRIMER ENTREGABLE (NO es código): mini-plan de alcance a `docs/planes/PLAN-M16-V0.md` con
sello de vigencia (patrón PLAN-M15/M14), confírmamelo por el buzón ANTES de construir vistas.
Debe listar: qué CARDS exactas, de qué tabla/modelo sale cada número, y qué rol la ve.

ALCANCE PROPUESTO por el Director (afínalo en el plan):
- Fuentes ya en producción: **Producción M11** (reportes del día por soplador, % 1ª/2ª/mermas,
  pendientes de aprobar), **Servicio Técnico M12** (órdenes por estado — las cards de Marcos
  en el Inicio son la semilla, unifícalas aquí sin romperlas), **Aprobaciones M14** (pendientes
  en cola por rol), **Notificaciones M15** (no-leídas/fallidas), **Stock crítico** (espejo
  Bsale — si hay señal de mínimo; si no, omítelo y anótalo).
- **Permisos:** NO inventes permisos nuevos. Cada card se muestra con el `@can` que el usuario
  YA tiene (D-002: el acceso fino se define al cerrar cada módulo). Un rol ve solo sus cards.
- **Reusa `<x-stat-card>`** (ya existe en el catálogo — indicador clickeable del Inicio) y los
  componentes de tarjeta/badge. Cero markup nuevo si el componente existe.
- Cada card enlaza (clickeable) a su módulo. Números por queries de LECTURA eficientes (sin
  N+1; `whereDate` para "del día" — nunca whereBetween, bitácora).

REGLAS: rama nueva `feature/m16-v0-dashboard` desde main FRESCO (`git fetch` — main tiene M14
recién). Responsive 375/768/1024. Tests: cada card cuenta bien + un rol sin permiso NO la ve.
Tocas Blade → npm run build + grep bundle (recuerda npm install por qrcode). Suite verde por commit.

CIERRE: parte a docs/fleet/buzon/partes/2026-07-14--max-1--plan-m16-v0.md (primero el plan) + push.
