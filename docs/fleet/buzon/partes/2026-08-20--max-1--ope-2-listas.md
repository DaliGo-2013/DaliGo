# Parte de Max-1 — 2026-08-20 · Dictado v78, OPE-2 HECHO: las listas de motivos y las procedencias son perillas, con el 4º hermano declarativo (espera doble llave)

> Forjador A, stream 1 · rama `feature/param-ope-2-listas` (commit `efd45a94`,
> nace de `2dbd16b3`) pusheada. **NO mergeo**: doble llave. NO arranco OPE-3.

## Qué quedó forjado (hallazgos #9 y #13, molde COM-1 + la pieza nueva)

**Tres claves TIPO_JSON** del grupo `produccion` (LISTAS_SIMPLES, UI una-por-línea,
defaults = las constantes vivas EXACTAS — fuentes: `ProduccionParada::MOTIVOS` (7),
`::MOTIVOS_PLANIFICADOS` (2) y `ProduccionAsignacion::PROCEDENCIAS` (saco/caja)):

- `produccion_motivos_parada` — los chips que toca el operario al registrar una
  parada. La lista sigue CERRADA (Rule::in lee la vigente): cambia QUIÉN la escribe.
- `produccion_motivos_planificados` — el subconjunto que no descuenta disponibilidad.
- `produccion_procedencias_preforma` — el selector opcional de asignar producción.

**El 4º hermano declarativo: `PARES_SUBCONJUNTO`** (hermano de RANGOS /
PARES_ORDENADOS / LISTAS_SIMPLES). Pares [hijo, madre]; valida en las DOS
direcciones al guardar cualquiera de las puntas, con la comparación
case-insensitive de `parseListaSimple`. **Decisión declarada (con el código a la
vista): RECHAZO en ambas** — el hijo con un elemento fuera de la madre se rechaza
(«agrégalo allá primero»), y la madre que suelta uno que el hijo nombra también
(«quítalo de allá primero»), mismo criterio que quitar un segmento en uso (COM-1).
Sin ajuste guiado silencioso: en una lista que gobierna el OEE, un auto-arreglo
que el admin no pidió es peor que un mensaje. Como en PARES_ORDENADOS: si la otra
punta no está sembrada no hay par que cruzar (la UI solo edita filas existentes y
el seeder siembra ambas juntas).

## El candado OEE-histórico-intacto (el matiz del F0, ahora en test)

`claseDe()` deriva de la lista VIGENTE y se PERSISTE al crear — verificado en los
DOS escenarios del test: motivo **reclasificado** hoy (a planificados) → el OEE de
hoy-histórico byte-idéntico (lee la columna, no la función); motivo **retirado**
de la madre → OEE idéntico Y la parada sigue visible con su motivo legado en el
informe. **Matiz declarado**: la etiqueta de clase del PARETO sí es viva
(`Oee::pareto` re-deriva con `claseDe()` solo para el rótulo de la fila — los
minutos y % salen de las duraciones persistidas). Cosmética coherente: la fila
del Pareto describe el motivo según la clasificación de hoy; los NÚMEROS no se
reescriben.

## El gotcha que cazó el propio rojo (vale bitácora si reincide)

El primer `assertDontSee('Corte de luz')` del candado del motivo retirado falló
con el fix CORRECTO: «Corte de luz» vive también en `MOTIVOS_DIFERENCIA` (otro
form de la MISMA pantalla del operario) y varios motivos más tienen gemelos en
`MOTIVOS_DEFECTO`. Los tres candados de pantalla quedaron por la **forma contigua
del chip** (`name="parada_motivo" value="X"`, con `false`) — doctrina
verde-engañoso aplicada en las dos direcciones.

## Verificación (invariante, delta 100 % atribuido)

- **Baseline propio** (worktree aislado + robocopy + diagnóstico autoloader, sobre
  `2dbd16b3`): **2272 / 15.873** — POR ENCIMA del 2253/15.764 del dictado porque
  main recibió después los merges de Marcos (cierre de trabajo en terreno, +19
  tests). Para esto era el «recuenta tú».
- **Rama**: **2279 / 15.941** — clava EXACTO: +7 tests / +44 aserciones
  (sección OPE-2 de `ParametrosOperacionTest`, 14 tests / 148 en total) **+24**
  (candados-iteradores del seeder: 542→566, mismos 17 tests — tercer lote con el
  mismo +24 por 3 claves). Cero tests existentes movidos ni amoldados.
- Batería dirigida (Produccion* + Configuracion* + Parametros*): **253 / 1.537**.
- **Mutación post-commit** (`claseDe()` de vuelta a la constante): **1 rojo
  EXACTO predicho** (`mover_un_motivo_a_planificados…`; el resto lee listas
  sembradas o la columna persistida) → `checkout --` → grep de los 2 marcadores.
- **Bundle byte-idéntico** (status real vacío): el único Blade tocado cambia
  `::MOTIVOS` → `::motivos()`, cero clases nuevas.

## Incidente de método (para la bitácora de la flota, sin daño)

A mitad del lote, un `git status`/`grep` mostró el árbol LIMPIO con los 9 edits
«desaparecidos». Falsa alarma con lección: el `cd` del diagnóstico del autoloader
dejó la sesión de shell parada EN EL WORKTREE baseline — los comandos con ruta
RELATIVA leían el worktree (detached, limpio por definición) mientras los edits
iban por ruta absoluta al checkout real. Regla barata: tras lanzar un baseline en
worktree, `cd` de vuelta ANTES de cualquier verificación, y ante un «se me
borraron los cambios», `pwd` primero.

## Para el radar del Director

- Rama lista: **va la doble llave**. Con el merge, el deploy siembra las 3 claves
  y los defaults son los históricos — producción no se mueve.
- El mecanismo PARES_SUBCONJUNTO queda listo para los candidatos de otros módulos
  que anunciaste en el radar.
- Espero dictado para OPE-3 (config de preforma + higiene — cierra el módulo).
  NO lo arranco solo.

## Fuera de alcance (declarado)

OPE-3 · merge (doble llave) · «Mi producción» salvo el form de paradas que ya
era del lote · MenuPrincipal/chat (Max-2) · territorio de Marcos ·
RUTA-MAESTRA/Trello (Director).
