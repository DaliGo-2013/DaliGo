# Parte de cierre — Max-2 · P-M11-21 · Alertas SIC + panel «Hoy en vivo» (F2, stream B)

CUENTA: Max-2 (Forjador B, stream 2) · MODELO: Fable 5 (fijado por el dueño)
TAREA: **P-M11-21** — GO del dictado v20 (PLAN-M11-FINAL §4-F2)
ESTADO: **HECHA** — pide doble llave
/usage INICIO → FIN: n/d (lo captura el dueño)
SIGUIENTE: doble llave de `feature/m11-alertas-sic`. Con la de Max-1
(`feature/m11-oee-pareto`, parte v42) F2 queda completa → se abre F3. Queda
P-M11-22 (semáforo de preformas + notas del jefe) como mi siguiente lote de F2
si el Director lo dicta antes.

## EVIDENCIA

Rama **`feature/m11-alertas-sic`** desde main fresco (`2d10ad8`), pusheada — 2
commits de código + merges de main, suite COMPLETA verde en cada uno:

| Commit | Qué | Suite |
|---|---|---|
| `eb5bb50` | Motor: comando `produccion:corte-sic` + service CorteSic + tabla `produccion_cortes` + evento M15 + 3 claves de config + 14 tests | 1788 / 12.766 |
| `381d18e` | Panel «Hoy en vivo»: ProduccionVivoController (vivo/conteo/firma) + vista con poll + duracionMinutosHasta + menú + build + 7 tests | **1795 / 12.803** |

**Los 6 candados del dictado, verificados:**
1. 2 cortes seguidos bajo umbral → 2º con asunto `⚠ URGENTE: `; 3º corte igual →
   silencio (la fila del corte SÍ se registra); recuperarse resetea la racha y una
   nueva caída re-avisa normal.
2. Sin asignación hoy → silencio (ni filas ni avisos).
3. Primer corte del turno (<60 min transcurridos) → se abstiene; `proyeccionPct`
   con guards explícitos (0 minutos / 0 meta → 0, jamás división).
4. MUTADO (gate): comentado el guard `wasRecentlyCreated` →
   `test_correr_dos_veces_el_mismo_slot_no_duplica` ROJO exacto → restaurado.
5. Panel: jefe 200 / soplador redirect+aviso (ambas rutas); paradas abiertas con
   duración corriendo SERVER-SIDE (`duracionMinutosHasta`, módulo 1440 — el test
   de madrugada cruza la medianoche: 23:40 → 02:00 = «lleva 2 h 20 min»).
6. Cortes 100% en FechaNegocio; frontera nocturna con travelTo: a las 02:00 de
   Chile el corte y el panel apuntan al reporte de AYER turno noche (el de hoy
   con turno noche queda fuera). Tests con fechas FIJAS de invierno chileno —
   con la fecha real del calendario el DST corría las horas de pared (lección
   del 31-07 aplicada en la primera pasada, no descubierta después).

## ⚠ Desviaciones declaradas (para tu veredicto)

1. **La unidad del corte es el REPORTE, no la máquina.** El dictado dice «por
   máquina con asignación activa» pero la meta POR MÁQUINA no existe en el
   esquema (las asignaciones son soplador+fecha+turno; `Maquina` es
   nombre+sucursal+activa). La meta real es `asignadas` del reporte — proyectar
   contra otra cosa sería inventar el denominador. Las máquinas del turno viajan
   nombradas en el aviso y el panel desglosa por máquina lo que SÍ existe por
   máquina (vendible, merma, paradas abiertas). El día que exista OEE-target por
   máquina (ficha de moldes F3), el corte puede refinarse.
2. **Horarios de turno = HIPÓTESIS editable** (`produccion_turnos`: día
   08:00-20:00, noche 20:00-08:00, patrón D-003): las horas reales las confirma
   el dueño/Luis desde Configuración, sin código. El umbral (85%) también es
   clave editable.
3. **Urgente = prefijo de asunto** (`⚠ URGENTE: ` vía placeholder {urgencia}):
   no existe flag de urgencia en M15; es el patrón vigente de la casa
   («⚠ VENCIDO» de vehículos). Un solo evento, una plantilla.

## Decisiones técnicas que valen registro

- **Slot del corte en UTC** (`corte_slot`, unique con reporte): Chile tiene DST
  y una «hora bonita» chilena es ambigua el día del cambio. El filtro de
  «horario de turno» vive DENTRO del service con FechaNegocio (el comando
  reclama por condición, no por cadencia — patrón de la casa; primera tarea de
  Console que usa FechaNegocio, el motor sigue en UTC).
- **Anti-spam** = tabla `produccion_cortes` + `firstOrCreate` del slot ANTES de
  notificar + racha leída de las filas (molde `vehiculo_avisos`).
- **Panel**: controller propio (`ProduccionVivoController`) — ProduccionController
  es archivo caliente de frontera con el stream A. Poll de firma md5 calculada
  por LA MISMA función para vista y JSON (candado anti-loop de la cola de
  bodega); la firma incluye los minutos corriendo de paradas abiertas a
  propósito → con parada abierta recarga ≤60 s y el «lleva X min» server-side
  se mantiene honesto; sin paradas la firma es estable y el monitor no recarga.
- **Semáforo paleta-4** (`CorteSic::variante`, candado estilo M18): al día =
  neutral · en riesgo = brand · crítico (racha ≥2) = danger. 0 producido con
  horas corridas ES riesgo (neutral solo sin proyección que leer).
- Riesgo conocido (spec de proyección lineal): un soplador que digita las
  tandas EN LOTE al final del turno dispara aviso legítimo a mitad de turno —
  el aviso trae paradas abiertas y el panel la última tanda como contexto para
  descartarlo. Si molesta en la práctica, el umbral es editable.

## Hallazgo de drift para tu radar (no es de mi lote)

Al rebuild post-merge, el bundle nuevo PURGÓ 11 clases `xl:*` (xl:sticky,
xl:col-span-*, xl:grid-cols-*) que venían en el bundle commiteado de main
(`app-BcKOrCsd.css`) y que **ningún fuente de HEAD usa** (grep en resources/ y
en la historia: cero). Es la firma de un build hecho con árbol sucio (familia
bitácora 30-07: WIP sin commitear o archivos extra alimentando el escaneo de
Tailwind). Mi bundle (`app-NNzx3hVB.css`) es el reproducible desde HEAD; si el
autor de ese build (stream de carga) tiene WIP con `xl:*`, su próximo build las
regenera. Verificado superset del resto: 0 clases usadas perdidas, críticas
`lg:flex`/`lg:hidden` presentes.

## Verificación adicional (gate propio)

- MUTADO ejecutado: guard `wasRecentlyCreated` cegado →
  `test_correr_dos_veces_el_mismo_slot_no_duplica` ROJO exacto → restaurado con
  `git checkout --` (árbol commiteado antes de mutar) → verde.
- `produccion:corte-sic --dry-run` manual sobre fixture SQLite real: tabla
  legible con 2 reportes en curso, acción `aviso`, cero escritura.
- Panel renderizado real verificado a 375/768 (volcado, patrón bitácora 26-07):
  sin overflow; barra 26% = 130/500 exacta; «proyección 78%» = 130×720/240/500
  exacta; badge «En riesgo» variante brand; parada abierta «desde las 10:15,
  lleva 1 h 45 min» (10:15→12:00 Chile) — el cálculo server-side clava el
  minuto; script del poll apuntando a vivo/conteo presente.
- Candados vecinos verdes en bloque: SidebarTest + MenuPrincipalTest +
  ProduccionParadasTest (el refactor de duración no movió nada) +
  ScheduleBsaleTest (60/60).
