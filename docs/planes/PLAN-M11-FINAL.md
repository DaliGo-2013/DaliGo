# PLAN-M11-FINAL · Producción — la versión definitiva del módulo

> **Estado: VIGENTE (visto bueno del dueño 2026-08-07)** — «todo me hace mucho sentido y
> me gustaría que sea parte de DaliGo». Levanta la pausa de flota SOLO para este plan.
> Insumo: benchmark de doble vía reconciliado
> (`docs/investigacion/2026-08-07--BENCHMARK-M11-RECONCILIADO.md`: 2 investigaciones
> independientes, mismo veredicto). Autor: Director.

## 0. El objetivo en una frase

M11 ya captura mejor que los comerciales (offline + aprobar/devolver); esta versión
final hace que la información CAPTURADA VUELVA procesada a cada rol — al soplador su
turno, al jefe su AHORA, al gerente su número — y conecta producción con el kardex
(preformas) y con los moldes.

## 1. Principios intocables (verificados por el benchmark ×2 vías)

1. **El offline de la PWA no se toca.** Cualquier campo nuevo viaja en el MISMO payload
   de la cola existente (patrón P-DSP-09). Ningún comercial tiene esto — es LA ventaja.
2. **El flujo aprobar/devolver no se toca.** Es además el trigger del backflush.
3. **El operario jamás ve costos ni precios** (regla Katana).
4. **Etiquetas de parada fácticas, jamás culposas** («qué detuvo la producción», nunca
   «error del operador») — el que reporta es el mismo soplador; un código culposo
   garantiza datos falsos.
5. Reglas de la casa: varchar ≤191 explícito, sin permisos nuevos si un existente calza,
   candados mutados, suite completa antes de push.

## 2. Entidades nuevas

| Entidad | Campos clave | Fase |
|---|---|---|
| `recetas` (paramétrica, editable UI como bodegas M04) | producto_id (botellón), componente_id (preforma/tapa), cantidad decimal(14,4), activa | A1 |
| `produccion_paradas` | reporte_id, motivo (los 5 + «scrap de arranque»), clase (`planificada|no_planificada`), origen (`maquina|operario`), inicio, fin, maquina_id | B1 |
| `moldes` (ficha tipo M18 vehículos) | nombre, tipo_botellon, cavidades, ciclo_ideal_seg, ciclos_acumulados, umbral_mantencion, estado | A3 |
| notas del jefe | texto + vigencia, visible en mi-reporte del asignado | B2 |

**[B] abiertos (las 5 preguntas a Luis — NO bloquean el arranque):** la receta se
siembra con la hipótesis 1 preforma + 1 tapa = 1 botellón y `confirmada=false` (editable
en UI — mismo patrón D-003/bodegas: la respuesta de Luis es un ajuste de datos, no de
código); «GP» pendiente de definición; molido valorizable pendiente (A3); nº de moldes y
ciclos ideales los carga el dueño/Luis en la ficha cuando existan.

## 3. Streams — territorio SIN cruce

**Max-1 = backend/kardex (stream A)** — dueño de: recetas, backflush, kardex, OEE,
moldes. Archivos: modelos/servicios/migraciones de inventario-producción,
`ProduccionController::aprobar`, informes.
**Max-2 = PWA/experiencia (stream B)** — dueño de: mi-reporte y todo lo que ve el
soplador, paradas (captura), alertas SIC, panel del jefe en vivo. Archivos: vistas
`resources/views/produccion/*`, offline-queue, jobs/comandos de alerta, M15.
**Frontera caliente:** `ProduccionReporte` (modelo) lo toca PRIMERO Max-2 (campos de
paradas en B1); Max-1 lo lee después. Los merges se serializan por doble llave como
siempre — lotes cortos, push temprano.

## 4. Lotes

### Fase 1 (arranca YA, en paralelo)
- **P-M11-10 (Max-1) · Receta + backflush al aprobar.** Tabla `recetas` + seeder
  hipótesis [B] + CRUD mínimo (permiso existente de producción/admin; UI editable estilo
  clasificación de bodegas). Regla: al APROBAR un reporte → movimiento de kardex
  descuenta componentes = (buenos + merma) × receta, con detalle visible en el reporte
  aprobado (el soplador NO ve costos, solo cantidades). Devolver un reporte jamás genera
  movimiento; re-aprobar tras devolución no duplica (idempotencia por reporte).
  Candados: mutar la regla buenos+merma → rojo; devolución sin movimiento; doble
  aprobación no duplica; receta editada solo afecta reportes futuros.
- **P-M11-20 (Max-2) · Paradas con duración en la PWA.** En mi-reporte: registrar
  paradas del turno (motivo tipificado + clase + origen + inicio/fin; multi-parada por
  reporte). Motivo nuevo «scrap de arranque» + campo «cavidades activas» del turno.
  Payload por la MISMA cola offline (candado: drena idéntico con los campos nuevos).
  El detalle de paradas visible para el jefe al aprobar. Candados: parada sin fin no
  bloquea el envío (fin = al cierre del reporte con aviso), 403, offline 2× sin
  duplicar, orden cronológico validado.

### Fase 2 (tras doble llave de F1)
- **P-M11-11 (Max-1) · OEE por máquina/molde + Pareto.** Con paradas (B1) y ciclo ideal
  (ficha molde o dato en receta mientras tanto): OEE = A×P×Q por máquina, semana/mes,
  con OEE-target por máquina; Pareto de motivos de parada; % merma con «arranque»
  separado. En las vistas de rendimiento existentes, idioma mes/año de la casa.
- **P-M11-21 (Max-2) · Alertas SIC + panel vivo del jefe.** Corte cada 2h (cron):
  proyección del día vs meta por máquina → bajo umbral → M15 al jefe (campanita/correo;
  WhatsApp queda [B:D-007]). Panel «hoy en vivo» del jefe: por máquina, avance/meta,
  paradas abiertas, últimos reportes. Escalamiento simple: 2 cortes seguidos bajo umbral
  → repite aviso marcado urgente.
- **P-M11-22 (Max-2) · El soplador recibe.** Semáforo de preformas en mi-reporte (stock
  espejo M04 de su bodega: verde/amarillo/rojo) + notas del jefe vigentes en su pantalla.

### Fase 3 (tras doble llave de F2)
- **P-M11-12 (Max-1) · Molde como entidad.** Ficha tipo M18: CRUD + contador
  `ciclos_acumulados` alimentado por reportes aprobados (producción/cavidades_activas) +
  umbral → aviso M15 «toca mantención» + OT correctiva automática si un reporte aprobado
  trae «falla de máquina»/«molde dañado». Molido valorizable si Luis confirma [B].
- **P-M11-23 (Max-2) · Kaizen digital.** Botón «proponer mejora» en mi-reporte →
  cola del jefe (motor M14 si calza sin permiso nuevo; si no, bandeja simple).
- **P-M11-30 · Trazabilidad lote preforma** — FUERA de este plan (espera M04-F3/D-005).

## 5. Hecho cuando

- [ ] Aprobar un reporte descuenta preformas/tapas del kardex, visible en movimientos;
      devolver no mueve nada.
- [ ] Una parada registrada en el celular sin señal llega con inicio/fin y motivo.
- [ ] El jefe recibe la alerta de proyección ANTES de que termine el turno.
- [ ] El gerente ve OEE por máquina con su target y el Pareto de paradas del mes.
- [ ] El soplador ve semáforo de preformas y notas del jefe; jamás costos.
- [ ] Un molde avisa solo que le toca mantención.
- [ ] QA del dueño por fase (celular, producción — como M04/M13).

## 6. Tracker

M11 hoy 75 % (peso 6). Al cierre de F1 → 85 %; F2 → 92 %; F3 → 100 % del alcance de
este plan (el peso del tracker se recalcula al landear cada fase, con PlanProyectoTest).
