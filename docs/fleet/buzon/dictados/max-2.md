# Dictado vigente — Max-2 (Forjador B, stream 2)
> Emitido por el Director el 2026-08-07 (v19 — PAUSA LEVANTADA por el dueño: GO P-M11-20, paradas con duración en la PWA). Manda sobre lo anterior.

MODELO: el que fije el dueño en tu asiento · high.

## La pausa se levantó — nueva prioridad del dueño (vía Luis): M11 Producción, versión final

**Lee primero `docs/planes/PLAN-M11-FINAL.md` (VIGENTE, visto bueno del dueño 07-ago)**
y su insumo `docs/investigacion/2026-08-07--BENCHMARK-M11-RECONCILIADO.md`. Dato que te
va a gustar: el benchmark confirmó con DOS investigaciones independientes que tu cola
offline es mejor que la de sistemas de US$12.000/año (Tulip pierde datos con cortes >50
segundos; la doc oficial de Fusion admite que sin conexión no corre). Tu P-DSP-05 es el
estándar de la casa — y este lote lo extiende.

Tu stream es **B (PWA/experiencia del soplador y del jefe)**: paradas, alertas, panel
vivo, semáforo. Max-1 lleva el stream A (recetas/backflush/kardex) EN PARALELO —
territorio en PLAN §3. **Frontera caliente:** `ProduccionReporte` lo tocas TÚ primero
(campos de paradas van en tu lote); Max-1 solo lo lee. Lotes cortos, push temprano,
re-fetch religioso: son DOS forjadores activos + Marcos.

## 🟢 GO — P-M11-20 · Paradas con duración en la PWA (F1, stream B)

Hoy los motivos de diferencia son un chip sin tiempo. Este lote los convierte en el dato
que desbloquea OEE, MTBF y el Pareto de «qué nos detiene» (PLAN §4):

- **Tabla `produccion_paradas`**: reporte_id, motivo (los 5 de la casa + nuevo **«scrap
  de arranque»** — los botellones malos post-cambio-de-molde son pérdida distinta),
  clase (`planificada` = cambio de molde/mantención · `no_planificada` = falla, faltaron
  preformas, molde dañado), origen (`maquina|operario` — hallazgo del benchmark: la
  parada puede ser del trabajador, patrón «No Machine» de Fusion), inicio, fin,
  maquina_id. varchar ≤191.
- **En mi-reporte**: registrar N paradas del turno con hora inicio/fin (UX táctil de la
  casa, 44px+); parada sin fin al enviar = se cierra con el reporte y queda marcada
  «cerrada al envío». Campo nuevo del turno: **cavidades activas** del molde (numérico
  chico, default = todas).
- **Etiquetas FÁCTICAS, jamás culposas** (principio §1.4 del plan): «qué detuvo la
  producción», nunca «error de alguien» — el que reporta es el mismo soplador.
- **Offline**: los campos nuevos viajan en el MISMO payload de la cola existente (tu
  patrón P-DSP-09: un solo camino, uuid + capturado_at intactos).
- **Para el jefe**: el detalle de paradas visible en la pantalla de aprobación (duración
  calculada, clase y origen con badge).

### Candados mínimos
1. Cola offline drena 2× sin duplicar paradas (idempotencia con los campos nuevos).
2. MUTADO: permitir fin < inicio → rojo (validación de cronología).
3. Parada sin fin no bloquea el envío; queda cerrada-al-envío con marca.
4. El detalle llega ÍNTEGRO a la vista de aprobación del jefe.
5. 403 de siempre; el reporte ajeno no muestra paradas de otro.
6. «Scrap de arranque» y «cavidades activas» persisten y se ven en el show.

## Recordatorios
Rama nueva desde main FRESCO; **suite COMPLETA de main fresco ANTES de empezar** (última
del Director: 1655/12.067 en `237185b`; fija la tuya del día). Suite completa antes del
push. Blade tocado → build + superset. `git checkout origin/main --` para conflictos,
nunca `>` (BOM). Parte al buzón → doble llave.

CIERRE: parte a docs/fleet/buzon/partes/ + push. F2 (alertas SIC + panel vivo + semáforo
de preformas) espera doble llave de F1.
