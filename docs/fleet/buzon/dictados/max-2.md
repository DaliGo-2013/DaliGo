# Dictado vigente — Max-2 (Forjador B, stream 2)
> Emitido por el Director el 2026-08-04 (v14 — PWA DEL CONDUCTOR EN PRODUCCIÓN; GO en firme P-DSP-08 hoja de ruta digital). Manda sobre lo anterior.

MODELO: el que fije el dueño en tu asiento · high.

## ✅ Tu PWA está EN PRODUCCIÓN (merge `d7803f9`, doble llave 04-ago) — DESPACHOS P-DSP-00..05 completo

Verificación del Director sobre el árbol unión con el main del momento (tu rama + módulo
Logística + botón Excel): **suite ejecutada 1402 verdes / 10.579 aserciones, cero rojos**
(tus 1386 + lo que main sumó después), superset 0 clases perdidas contra ambos padres, tus
5 ganchos JS presentes, spot-checks completos. Deploy success. Rama borrada tras ancestría.

Tu disciplina de re-refresh por-paso hizo esta doble llave trivial: el único conflicto fue
el manifest de siempre. Y para el registro: la primera verificación del Director se corrió
sobre una base 4 commits más vieja de lo declarado — se detectó al pushear, se rehizo entera
sobre el árbol real, y quedó anotado en el mensaje del merge. La cifra de arriba es la buena.

## 🟢 GO EN FIRME — P-DSP-08: la hoja de ruta digital (F1 de PLAN-DESPACHOS-V2)

Ya lo tenías del v13; ahora sin condiciones. **Rama NUEVA desde main fresco** (main incluye
tu PWA + M18 Logística — el catálogo de vehículos que M18 trajo te sirve directo para el
campo vehículo/patente de la hoja: revisa qué existe antes de crear columnas).

**Fuente: `docs/planes/PLAN-DESPACHOS-V2.md` (§2 modelo, §3 pasos).** Lo duro:
- `hojas_de_ruta` + `hoja_ruta_paradas`; folio autogenerado **desde 1000** con `lockForUpdate`.
- Estados `borrador → pagos_ok → ruta_autorizada → cargada → en_ruta → cerrada`, **3 llaves
  secuenciales** (jefe ventas → jefe despacho → jefe bodega), permisos nuevos, todo auditado
  (patrón `Zona`/AuditableContract). NO pasa por M14.
- Generación de paradas **desde `documentos_venta`** (Ricardo ELIGE, nada se tipea).
- `estado_cobro` por parada (`pagado | cobrar_en_entrega | credito`) — columna nace aquí,
  el registro del cobro es de P-DSP-09.
- **Scoping conductor↔ruta**: retiro/entrega solo si la hoja `en_ruta` es del conductor.
- Sin UI de conductor (P-DSP-09) y sin campos manuales de hora (timestamps por transición).
- Candados: máquina de estados no saltable (mutada en ambos sentidos), folio único bajo
  carrera, permiso por llave, paradas solo de documentos vigentes (`cancellation_status=0`).

**Ojo con M18**: Logística acaba de entrar y trae vehículos/flota. Si su modelo de vehículo
sirve, referéncialo (FK) en vez de duplicar texto — pero NO modifiques nada de M18 (mano de
Marcos); si algo no calza, anótalo en el parte como decisión pendiente.

## Territorio
- **Marcos**: M05 + M18 Logística, MUY activo (le entraron ~15 commits hoy). Rama corta,
  re-refresh por-paso, push temprano.
- **Max-1**: construyendo M13 Devoluciones (plan re-sellado + visto bueno dado). No comparte
  archivos contigo.

## Recordatorios
Suite COMPLETA antes de cualquier push (baseline HOY: **1402 / 10.579** en main `d7803f9`).
Blade tocado → build + grep superset. Conflictos con `git checkout origin/main -- <archivo>`,
nunca con `>` (BOM de PS 5.1 revienta Vite). Parte al buzón → doble llave.

CIERRE: parte a docs/fleet/buzon/partes/ + push.
