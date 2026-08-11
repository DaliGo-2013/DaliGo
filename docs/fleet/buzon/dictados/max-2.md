# Dictado vigente — Max-2 (Forjador B, stream 2)
> Emitido por el Director el 2026-08-11 (v22 — P-M11-22 EN PRODUCCIÓN, F2 COMPLETA; GO P-M11-23: kaizen digital, el último paso del plan). Manda sobre lo anterior.

MODELO: el que fije el dueño en tu asiento · high.

## ✅ P-M11-22 está EN PRODUCCIÓN (merge `c8b343c`, doble llave 11-ago) — F2 COMPLETA

Verificación del Director: **suite 1930 verdes / 13.807 aserciones, cero rojos** —
cuadre exacto (1911 del main con los moldes de Max-1 + tus 19). Fronteras con el stream
A: cero diff. Deploy y Tests verdes. Rama borrada.

Tu gate EXTRA no dictado (silencio también sin sucursal o sin bodegas en operación) es
la clase de defensa que convierte un semáforo en algo confiable: un rojo falso por hueco
de configuración habría enseñado a los sopladores a ignorarlo. Y `stock_disponible` en
vez de `stock_real` con el criterio ya establecido de la ficha de bodega: coherencia de
casa, no invento nuevo.

**El soplador ya RECIBE**: su semáforo de preformas y las notas del jefe. Con esto la
asimetría que detectó el benchmark (capturamos mejor que nadie, devolvíamos poco) quedó
cerrada para los 3 roles: soplador, jefe, gerente.

## 🟢 GO — P-M11-23 · Kaizen digital (F3 stream B — el ÚLTIMO paso de PLAN-M11-FINAL)

PLAN §4-F3, chico a propósito:

- **Botón «Proponer mejora»** en mi-reporte (junto a las notas del jefe que acabas de
  construir): texto libre ≤191, opcional foto NO (sin archivos — texto simple).
- **Tabla `produccion_mejoras`**: soplador_id, texto, estado
  (`pendiente|revisada|aplicada|descartada`), respuesta del jefe nullable ≤191,
  timestamps. Auditable.
- **Bandeja del jefe**: sección en el panel de producción (permiso existente), contador
  de pendientes, acciones revisar/aplicar/descartar con respuesta opcional — la
  respuesta se le muestra al soplador en mi-reporte (su historial de propuestas, estado
  con badge paleta-4).
- **Sin M14 y sin M15 a propósito**: no es una aprobación con consecuencias de sistema
  (es conversación estructurada) y no debe perseguir a nadie — vive en las pantallas.
  Si el dueño después quiere aviso, es una línea.
- **Offline**: el POST de la propuesta viaja por la MISMA cola (patrón de siempre); si
  eso complica el lote, decláralo y déjalo online-only con aviso «necesitas señal» —
  a tu criterio con alternativa nombrada.

### Candados mínimos
1. Propuesta llega a la bandeja; respuesta + estado se ven en mi-reporte del autor (y
   SOLO del autor).
2. 403: soplador no ve bandeja; jefe no propone por otro.
3. varchar ≤191; sin permiso nuevo.
4. Si va por la cola: drena 2× sin duplicar (idempotencia de siempre). Si online-only:
   declarado en el parte.
5. El soplador sigue sin ver costos (candado de texto percibido, tu propio molde).

## Territorio
- **Max-1** queda EN PAUSA (su stream de M11 está completo). Cero cruce.
- **Marcos** sigue activo (flota/documentos hoy). Re-fetch religioso.

## Recordatorios
Rama nueva desde main FRESCO; suite COMPLETA de main fresco ANTES de empezar (baseline
del Director: **1930/13.807** en `c8b343c`). Suite completa antes del push. Blade →
build + superset. `git checkout origin/main --`. Parte al buzón → doble llave.

CIERRE: parte a docs/fleet/buzon/partes/ + push. Con tu próximo lote, PLAN-M11-FINAL
queda 100 % construido.
