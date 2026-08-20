# Parte de Max-1 — 2026-08-19 · Dictado v77, OPE-1 HECHO: las ventanas del panel y de los informes son perillas (espera doble llave)

> Forjador A, stream 1 · rama `feature/param-ope-1-ventanas` (commit `434a31ed`)
> pusheada. **NO mergeo**: doble llave del Director + dueño. NO arranco OPE-2.

## Qué quedó forjado (molde DASH-1, cuarto uso)

**Tres claves nuevas** del grupo `produccion` (seeder idempotente, ayuda en español,
todas ≤191):

- `produccion_dias_panel` = **7** — la sección «Producción por periodo» del panel.
- `produccion_dias_informe_maquina` = **30** — el informe de rendimiento por máquina.
- `produccion_dias_informe_tipo` = **30** — el informe por tipo de botellón.

`RANGOS`: 2-31 el panel (el tope del pulso) y **7-90 los informes — declarado: 90
queda DENTRO del tope duro de 92 filas de `rango()`**, que sigue intacto como límite
de render aparte (nivel 3, hallazgo #2 del mapa). Rótulos derivados en las 3 vistas
(`Últimos {N} días` / `· últimos {N} días`), el ±1 de DASH-1 comentado en el código
(`rango()` recibe días hacia atrás → `$dias - 1`).

## Las dos decisiones que el dictado pedía declarar

1. **Claves separadas para los dos informes — busqué la contra-evidencia y NO
   alcanza**: comparten helper y literal (`rango($request, 29)` ×2) pero son dos
   literales sueltos en dos métodos sin ningún acople — nada en el código exige que
   se muevan juntos, y mover uno no toca al otro. Duplicación de VALOR, no un
   concepto único → la doctrina DASH-1 (ventanas distintas, perillas distintas)
   manda. Forjé las 3 del dictado.
2. **El OJO al `rango()`, confirmado y fijado en candado**: el helper interpreta el
   request (?desde/?hasta gana siempre) → la clave quedó como el DEFAULT, no un tope.
   `test_el_rango_pedido_por_url_le_gana_a_la_clave`: con las claves movidas a 14/45,
   un rango de 3 días pedido por URL rinde 3 días y `esDefault=false` — el candado
   distingue default-configurable de rango-pedido-por-el-usuario.

## Candados (7 nuevos en `ParametrosOperacionTest`, cifras de borde)

Una sola fixture alimenta las tres pantallas (reporte APROBADO + tanda): hoy=100,
día −7=40 (borde del panel), día −34=50 (borde de los informes). Default idéntico con
BD virgen (100/140/140) · mover cada clave mueve SU serie, SU cifra y SU rótulo y NO
las otras dos (panel 14→140; máquina 45→190 con tipo quieto en 140; tipo 60→190 con
máquina quieta) · request-gana-a-la-clave · rangos UI por ambos bordes (2/31 y 7/90,
con 1, 32, 6, 91 y 'abc' rechazados).

## Verificación (invariante, delta 100 % atribuido)

- **Baseline** (worktree aislado + vendor robocopy + diagnóstico del autoloader,
  sobre `94934cfe`): **2238 / 15.574** — calza EXACTO con el conteo del dictado.
- **Rama**: **2245 / 15.699**. Delta: **+7 tests / +101 aserciones** (los míos) **+24
  aserciones sin tests nuevos** = los candados que iteran las filas del seeder
  (`ConfiguracionSeedLongitud` + `ConfiguracionManagement`: 518→542 con los mismos 17
  tests — 3 filas más de seeder). Cero tests existentes movidos ni amoldados.
- Batería dirigida (Produccion* completo + ParametrosDashboard + ConfiguracionManagement
  + SeedLongitud + ParametrosOperacion): **246 / 1.466 verdes**.
- **Mutación post-commit** `DIAS_PANEL_DEFAULT 7→9`: **1 rojo EXACTO predicho** (solo
  el test de BD virgen — los otros 6 leen la clave sembrada o piden rango por URL) →
  `git checkout --` → grep del marcador (`= 7` presente) → verde.
- **Bundle byte-idéntico** (status real vacío entre marcadores): los rótulos derivados
  no traen clases nuevas.

## Para el radar del Director

- Main movió DESPUÉS de mi baseline: `af7b73f3` (parte MSG-3 de Max-2, solo docs — el
  código MSG-3 espera su doble llave en `feature/msg-3-poll`). Mi rama nace de
  `94934cfe`; si MSG-3 mergea primero, el I-08 de siempre: `ConfiguracionSeeder` es
  archivo compartido — mi hunk agrega 3 entradas tras `produccion_minutos_turno`,
  conflicto trivial de resolver en el orden que decidas.
- Con la doble llave puesta, el deploy siembra las 3 claves solo (seeder en
  `deploy.sh`) y los defaults son los históricos: producción no se mueve un píxel.
- Espero dictado para OPE-2 (las dos listas con el par planificados ⊆ motivos). NO lo
  arranco solo.

## Fuera de alcance (declarado)

OPE-2/OPE-3 · merge (doble llave) · los demás hallazgos del mapa §5.3 ·
RUTA-MAESTRA/Trello (Director) · territorio de Max-2 y Marcos.
