# Parte de cierre — Max-2 · P-DSP-08 · La hoja de ruta digital (F1)

CUENTA: Max-2 (Forjador B, stream 2) · MODELO: Fable 5 (fijado por el dueño)
TAREA: **P-DSP-08** — GO en firme del dictado v14 (PLAN-DESPACHOS-V2 §2-3)
ESTADO: **HECHA** — pide doble llave

## EVIDENCIA

Rama **`feature/hoja-de-ruta`** desde main fresco (`59aadcc`), pusheada — 5 commits,
suite COMPLETA verde en cada uno:

| Commit | Qué | Suite |
|---|---|---|
| c1 | `hojas_de_ruta` + `hoja_ruta_paradas` + receptor en despachos + permisos/rol/matriz + factories + auditable | 1408 / 10.606 |
| c2 | `HojaRutaService`: folio bajo lock, paradas elegidas, cadena de llaves, salida a ruta | 1426 / 10.650 |
| c3 | Pantallas admin + rutas por llave + menú + build (superset 0/657, sin BOM) | 1437 / 10.734 |
| c3b | `DespachoController::create` → scopes (c3 lo anunció sin aplicarlo; corregido aparte, no escondido) | — |
| c4 | Scoping conductor↔hoja + orden pactado en la PWA | **1441 / 10.744** |

**37 tests nuevos** (HojaRuta 6 · Service 14 · Http 11 · Scoping 4 · Lock 1 · RoleMatrix ajustado).
**3 mutaciones verificadas** (commit ANTES de mutar, ocurrencia correcta, restaurado con
checkout + grep del marcador): re-check de transición fuera → **2 rojos** (saltos + stale);
propagación EN_RUTA fuera → **1 rojo**; rama de hoja del scoping fuera → **2 rojos**.

## Qué quedó construido (lo duro del dictado, punto por punto)

- **Folio desde 1000** (R25): `max(folio, 999)+1` con `lockForUpdate` en transacción; la red
  real es el **unique de BD** (candado estructural) + el builder compilado con `MySqlGrammar`
  emite `for update` (`LockParaMySqlTest` — la lección de honestidad del lock, aplicada de entrada).
- **Máquina secuencial estricta** `borrador→pagos_ok→ruta_autorizada→cargada→en_ruta→cerrada`
  con mapa declarativo `TRANSICIONES` (el primero del repo): candado de FORMA (cadena sin
  saltos ni ciclos que recorre TODOS los estados) + service que re-lee bajo lock. `cerrada`
  declarada sin transición (es P-DSP-10).
- **3 llaves = 3 permisos + rol nuevo `jefe_despacho`** (12º): matriz CRUZADA testeada
  (ventas no da la de bodega, el gestor no da ninguna, un 403 no mueve el estado).
  `*_at`/`*_por` automáticos — la firma de cada transición solo recibe (hoja, usuario);
  un parámetro de hora futuro rompe un test de reflexión (R5 por construcción).
- **Paradas eligiendo documentos** (R1): vigentes según espejo + re-verificación Bsale
  fail-closed al crear el despacho (reusa `crearDesdeDocumento`, cero duplicación); reusa
  despachos sueltos no cerrados; anulado/en-otra-hoja/entregado → rechazo con mensaje.
- **`estado_cobro` por parada** con default fail-safe `cobrar_en_entrega` (si nadie dijo
  pagado, se cobra); `cobro_metodo`/`monto` y `resultado` nacen ya (registro: P-DSP-09).
- **Scoping conductor↔hoja** (R22): con parada manda LA HOJA — solo el conductor de una hoja
  EN RUTA ve y entrega, aunque `despachos.conductor_id` diga otra cosa; el índice de la PWA
  respeta el ORDEN PACTADO (R3). **Aditivo**: despacho sin hoja = regla original, la PWA en
  producción no se rompe. Cierra el hallazgo del gate M07 donde hay hoja.
- **`salirARuta` ESCRIBE `Despacho::EN_RUTA`** — el estado que se leía en 4 lugares y nadie
  escribía; `en_ruta_at` es la hora de salida que exigirá la guía electrónica del 1-nov.
- Auditable patrón Zona (hoja Y parada — la edición del orden es justo lo que R6 rastrea)
  + registradas en el filtro del historial.

## Micro-decisiones (las 4 del plan aprobado — para tu veredicto)

1. **Vehículo: FK blanda + snapshot** (`vehiculo_id` nullOnDelete + `vehiculo`/`patente`
   texto). El dictado sugería FK; el plan V2 decía texto. M18 permite `destroy()` físico →
   una FK dura rompería su flujo (y M18 no se toca). El form ELIGE de `Vehiculo::activos()`
   (nada se tipea) y el store congela ppu/nombre — patrón `traslados_servicio`. `patente`
   a 12 (ancho de `vehiculos.ppu`), no 8 como decía el plan.
2. **Receptor (R13) nace ahora** en `despachos` (nullable): mismo criterio que
   `estado_cobro` — la columna en F1, la exigencia en P-DSP-09.
3. **La salida a ruta la registra `autorizar carga`** (bodega ve partir el camión). No se
   bloquea la salida con paradas sin escanear (el camión partió = partió); **pregunta para
   la ronda 2 con Luis: ¿debe bloquearse?**
4. **`despacho_id` unique GLOBAL en paradas**: un despacho no vive en dos hojas. Si un
   rechazado se re-despacha otro día (R15), su parada vieja se resuelve primero — si la
   operación real necesita otra cosa, es enmienda de P-DSP-09/10.

Extra técnico: `orden` SIN unique a propósito (un swap A↔B se valida fila a fila en MySQL y
chocaría a mitad del update); la secuencia la regenera completa el service. El helper
`despacho()` triplicado de los tests pasó a factories (`DocumentoVenta`/`Despacho`/`HojaDeRuta`).

## SIGUIENTE

**Doble llave de `feature/hoja-de-ruta`** (2 detrás de main al pushear — solo partes de
Max-1, cero solape). Con eso quedan **P-DSP-09** (PWA sobre la hoja: dirección+comuna+
teléfono visibles, receptor, cobro, rechazo) y **P-DSP-10** (edición auditada + cierre +
bono, bloqueado por el Excel de la fórmula). Espero dictado.
