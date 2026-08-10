# Dictado vigente — Max-1 (Forjador A, stream 1)
> Emitido por el Director el 2026-08-10 (v42 — F1 COMPLETA; GO P-M11-11: OEE + Pareto). Manda sobre lo anterior.

MODELO: Opus 4.8 · high.

## ✅ F1 de PLAN-M11-FINAL COMPLETA — el stream B ya está en producción

P-M11-20 de Max-2 entró con doble llave (`1c040c3`, 10-ago): tabla `produccion_paradas`
(motivo en lista cerrada de 7, clase `planificada|no_planificada` derivada server-side
con `ProduccionParada::claseDe()`, origen `maquina|operario`, inicio/fin `time` con
módulo 1440 para turno noche, `cerrada_al_envio`) + `cavidades_activas` tinyint nullable
en `ProduccionReporte`. **Tu esquema de consumo YA convive con el suyo en main** — el
suelo para tu OEE está firme. Baseline HOY: **1771 / 12.661** en main `1c040c3`.

## 🟢 GO — P-M11-11 · OEE por máquina/molde + Pareto de paradas (F2, stream A)

PLAN-M11-FINAL §4-F2. Con las paradas de Max-2 y tu backflush, los 3 factores existen:

- **OEE = Disponibilidad × Rendimiento × Calidad**, por máquina, semana/mes:
  - **Disponibilidad**: tiempo del turno − Σ duración de paradas NO planificadas (las
    planificadas —cambio de molde, mantención— dentro del tiempo planificado, doctrina
    del benchmark/OEE.com). Las abiertas-cerradas-al-envío cuentan por su duración real.
  - **Rendimiento**: producción real vs teórica. Teórica = tiempo disponible / ciclo
    ideal. **El ciclo ideal por tipo de botellón AÚN NO EXISTE como dato** — agrégalo a
    la RECETA (columna `ciclo_ideal_seg` decimal nullable, tu tabla, tu stream); NULL =
    rendimiento «sin ciclo cargado» (se muestra el dato faltante, no un 100 % falso).
    Considera `cavidades_activas` del reporte si viene (NULL = todas → factor 1).
  - **Calidad**: buenos / (buenos + merma) — ya lo tienes por tanda.
- **OEE target por máquina** (aporte B4 del benchmark): columna en `maquinas`
  (`oee_target` tinyint nullable %), editable donde se edita la máquina; el informe
  pinta contra el target.
- **Pareto de paradas**: por motivo, con duración acumulada y conteo, filtro
  mes/máquina, separando clase — los 3 no planificados top concentran ~80 %.
- **% merma con «scrap de arranque» separado** (el motivo ya existe en las paradas y en
  MOTIVOS_DEFECTO de las tandas).
- Superficie: pestaña/sección en los informes de producción existentes, idioma mes/año
  de la casa, permisos existentes de informes — cero permisos nuevos.

### Candados mínimos
1. MUTADO: paradas planificadas descontando disponibilidad → rojo (deben NO descontarla).
2. Ciclo ideal NULL → el informe declara «sin ciclo cargado», jamás rendimiento 100 %.
3. OEE nunca > 100 % (rendimiento capado por validación de datos, no por clamp del
   informe — si pasa de 100 es señal de ciclo ideal mal cargado y se muestra el aviso).
4. Pareto cuadra: Σ duraciones del Pareto == Σ paradas del período.
5. Medianoche: parada 23:40→06:30 aporta 410 min, no negativos.
6. El soplador no ve el informe (permiso de informes) ni costos en ninguna parte.

## Territorio
- **Max-2** arranca P-M11-21 (alertas SIC + panel vivo) EN PARALELO — él consume tus
  MISMOS datos pero en superficies distintas (jobs/M15/panel «hoy»); tú eres informes
  históricos. Frontera: `ProduccionParada` (modelo) es de Max-2 — tú LEES; si necesitas
  un scope nuevo, pídelo por parte, no lo agregues tú.
- **Marcos** sigue a toda máquina en el simulador. Re-fetch religioso — la carrera del
  10-ago la perdió el DIRECTOR esta vez (I-08 funcionó: re-merge + suite entera).

## Recordatorios
Rama nueva desde main FRESCO; suite COMPLETA de main fresco ANTES de empezar (fija tu
baseline; la del Director: 1771/12.661). Suite completa antes del push. Blade → build +
superset. `git checkout origin/main --` para conflictos. varchar ≤191. Parte al buzón →
doble llave.

CIERRE: parte a docs/fleet/buzon/partes/ + push.
