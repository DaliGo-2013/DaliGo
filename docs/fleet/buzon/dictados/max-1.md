# Dictado vigente — Max-1 (Forjador A, stream 1)
> Emitido por el Director el 2026-08-10 (v43 — P-M11-11 EN PRODUCCIÓN; GO P-M11-12: el molde como entidad, F3). Manda sobre lo anterior.

MODELO: Opus 4.8 · high.

## ✅ P-M11-11 está EN PRODUCCIÓN (merge `2264e8c`, doble llave 10-ago)

Verificación del Director sobre el árbol unión (main ya traía las alertas SIC de Max-2):
**suite 1806 verdes / 12.867 aserciones, cero rojos** — cuadre exacto (1795 + tus 11).
Conflicto único en ConfiguracionSeeder (ambos streams sembraron claves de turno):
resuelto manteniendo AMBAS con nota de coherencia — tu `produccion_minutos_turno` y los
horarios de Max-2 son dos hipótesis del mismo hecho, hoy coherentes en 12 h; unificarlas
(derivar minutos de los horarios) queda como pulido de F3. Deploy y Tests verdes. Rama
borrada.

Tu «ciclo NULL se declara, no se inventa» y el aviso de OEE>100 en vez de clamp
silencioso son exactamente la casa: los datos que faltan se VEN faltar. Y la corrección
de la premisa del dictado (scrap de arranque no estaba en MOTIVOS_DEFECTO) con 1 línea
aditiva: verificar el dictado contra el repo es tan parte del lote como el código.

## 🟢 GO — P-M11-12 · El molde como entidad (F3, stream A)

PLAN-M11-FINAL §4-F3 — el rasgo que ni Odoo tiene (solo los MES de plásticos):

- **Tabla `moldes`** (ficha estilo M18 vehículos): nombre (≤191), tipo_botellon_id,
  cavidades (tinyint), ciclo_ideal_seg (unsignedSmallInteger nullable — **mover/enlazar
  el dato que hoy vive en la receta**: decide tú si el molde REFERENCIA la receta o la
  receta al molde, con sweep y alternativa nombrada; evita la tercera copia),
  ciclos_acumulados (unsignedBigInteger), umbral_mantencion (nullable), estado
  (`activo|en_mantencion|retirado`), notas. CRUD con permiso existente de producción.
- **El contador se alimenta solo**: cada reporte APROBADO suma al molde del turno
  (producción total / cavidades_activas cuando venga, factor 1 si NULL — el dato ya
  existe de P-M11-20). Idempotencia: devolución no resta, re-aprobación no re-suma
  (mismo guard del backflush).
- **Umbral → aviso M15** «al molde X le toca mantención» (evento nuevo, molde de
  bodega.nueva) a quienes gestionen producción, UNA vez por cruce de umbral (guard
  timestamp, patrón aviso_stock_nuevo de M04-F2). Registrar la mantención resetea el
  contador y deja historial (tabla `molde_mantenciones` mínima: fecha, tipo
  `preventiva|correctiva`, quién, nota).
- **Correctiva automática**: reporte aprobado con parada de motivo «Molde dañado» →
  crea la mantención correctiva pendiente + aviso. (Falla de máquina NO — eso es de la
  máquina, no del molde.)
- **¿Qué molde trabajó el turno?** No existe el dato — inferencia honesta: el molde
  activo del tipo_botellon de la tanda; si hay 2+ moldes activos para el mismo tipo, el
  reporte pide elegirlo (campo nuevo en ProduccionReporte — COORDINA: es frontera; campo
  aditivo nullable está OK pero decláralo en el parte para que Max-2 lo sepa).
- El OEE puede empezar a leer `ciclo_ideal_seg` desde el molde si decides que el molde
  es el portador — si lo haces, la receta muestra el dato del molde (una fuente).

### Candados mínimos
1. Contador: aprobar suma exacto (con cavidades y sin), devolver no resta, re-aprobar
   no duplica (MUTADO el guard → rojo).
2. Umbral cruzado → UN aviso; siguiente reporte sin re-aviso; mantención registrada →
   contador 0 + historial + re-arma el aviso.
3. «Molde dañado» aprobado → correctiva pendiente creada UNA vez.
4. Molde retirado no aparece en selectores.
5. 403 sin permiso; el soplador no ve la ficha de moldes.
6. varchar ≤191; sin permiso nuevo.

## Territorio
- **Max-2** cierra F2 con P-M11-22 (semáforo + notas en mi-reporte) EN PARALELO. Tu
  único roce posible: el campo molde_id en ProduccionReporte — aditivo nullable,
  declarado en el parte. Sus vistas del soplador no las tocas.
- **Marcos** en el simulador. Re-fetch religioso.

## Recordatorios
Rama nueva desde main FRESCO; suite COMPLETA de main fresco ANTES de empezar (baseline
del Director: **1806/12.867** en `2264e8c`). Suite completa antes del push. Blade →
build + superset. `git checkout origin/main --`. Parte al buzón → doble llave.

CIERRE: parte a docs/fleet/buzon/partes/ + push.
