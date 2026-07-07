# Recetario de prompts — flota DaliGo

> **Qué es:** prompts listos para copiar/pegar a las cuentas Claude de la flota, ordenados por
> MOMENTO del flujo de trabajo. Adaptados de la [biblioteca oficial de prompts de Claude Code](https://code.claude.com/docs/en/prompt-library)
> al contexto, convenciones y gotchas de DaliGo.
> **Qué NO es:** arranque/cierre de sesión (`docs/PROTOCOLO-SESION.md` §1/§3), contratos de stream
> (`KICKOFF-*.md`), ni delegación a la IA de cPanel (`plantillas/`). Eso vive allá; aquí solo se cita.

---

## 0. Cómo usar este recetario

### 0.1 El mapa de roles (quién recibe cada prompt)

| Cuenta | Rol | Secciones que más usa |
|---|---|---|
| Max-1 | Forjador A (main) | §3, §4, §5 |
| Max-2 | Forjador B (rama M15) | §3, §4 (especialmente R-33) |
| Pro-1 | Director | **ninguna** — opera con su kickoff; verifica que los demás usen el recetario |
| Pro-2 | Auditor | §4 (R-31, R-32) |
| Pro-3 | Investigador | §1, §6 |
| Pro-4 | Escriba | §8 (R-70) |
| — | El dueño (Mauricio) | §1, §7 — y despacha todos los demás |

### 0.2 Anatomía de una ficha

Cada prompt es una ficha con ID greppable `R-xx` (como los `P-xxx` y `D-0xx`): **Cuándo** (situación
disparadora), **Quién** (dueño → cuenta destino), **Prompt** (copiar y rellenar los `{…}`),
**Ejemplo relleno** (caso DaliGo real), **Qué esperar** (la señal de que viene bien), y el
*origen* en la biblioteca oficial (trazabilidad).

### 0.3 Regla anti-duplicación

| Necesitas… | NO está aquí — ve a |
|---|---|
| Arrancar o cerrar una sesión de trabajo | `docs/PROTOCOLO-SESION.md` §1 / §3 (o las skills `/arranque` y `/cierre`) |
| Poner en marcha una cuenta/stream nuevo | `docs/delegacion/KICKOFF-*.md` |
| Pedir algo al servidor/cPanel/staging | `docs/delegacion/plantillas/` (formato de respuesta cerrado) |
| Registrar una decisión de negocio | `docs/DECISIONES.md` (ficha D-0NN) |

---

## 1. Orientarse y preguntar al código (sobre todo para el dueño)

### R-01 · Oriéntame en DaliGo
- **Cuándo:** una cuenta/persona nueva necesita el panorama, o el dueño quiere refrescar cómo está armada la app.
- **Quién:** dueño → cualquier cuenta (ideal Pro-3 Investigador).
- **Prompt:**
  ```
  Dame un panorama de este proyecto: arquitectura, carpetas clave y cómo se conectan las piezas.
  Cierra con los 5 archivos que más me conviene conocer y por qué. No cambies nada.
  ```
- **Ejemplo relleno:** tal cual (no tiene slots).
- **Qué esperar:** mapa rutas→controladores→modelos→vistas + los 5 archivos (probablemente `routes/web.php`, `CLAUDE.md`, `docs/RUTA-MAESTRA.md`, el seeder de roles y `resources/views/components/`).
- *Origen: `get-oriented-in-a`*

### R-02 · ¿Qué pasa cuando…?
- **Cuándo:** Mauricio quiere entender un flujo de la app antes de decidir, corregir o delegar.
- **Quién:** dueño → cualquier cuenta (ideal Pro-3 Investigador, para no gastar contexto de los Forjadores).
- **Prompt:**
  ```
  Soy el dueño del negocio, no programador. Explícame paso a paso qué pasa en el código cuando
  {acción de un usuario}, en lenguaje simple. Nombra los archivos por su ruta, di qué hace cada
  uno en una frase, y termina con un diagrama de texto del flujo. No cambies nada.
  ```
- **Ejemplo relleno:** `{acción de un usuario}` = "un soplador envía su reporte de producción del día".
- **Qué esperar:** recorrido ruta → controlador → modelo → vista (p. ej. `routes/web.php` → `MiProduccionController::update` → `ProduccionReporte` → `mi-reporte.blade.php`) sin jerga, cerrado con el diagrama.
- *Origen: `ask-the-codebase-a`*

---

## 2. Planificar un módulo (spec → plan con sello)

### R-10 · Entrevístame para la spec
- **Cuándo:** al abrir una unidad Exx cuyo alcance fino aún no está claro (antes del plan).
- **Quién:** dueño → el Forjador que la construirá.
- **Prompt:**
  ```
  Vamos a construir {unidad/módulo} (lee su ficha en docs/RUTA-MAESTRA.md y la biblia §4).
  Entrevístame con preguntas de a una sobre reglas de negocio, casos borde y qué debe ver cada
  rol, hasta cubrir todo. No asumas respuestas: si dudo, anótalo como pregunta abierta para
  DECISIONES.md. Al final escribe la spec acordada como sección "Alcance fino" del plan.
  ```
- **Ejemplo relleno:** `{unidad/módulo}` = "E6 · M13 Devoluciones".
- **Qué esperar:** preguntas UNA a la vez (no un cuestionario en bloque); las dudas de negocio terminan como candidatas a D-0NN, no como supuestos.
- *Origen: `draft-a-spec-by`*

### R-11 · Dimensiona antes de comprometerte
- **Cuándo:** antes de prometer fechas o decidir si algo entra en la unidad actual.
- **Quién:** dueño → Forjador (o Pro-3).
- **Prompt:**
  ```
  Dimensiona el cambio {cambio}: qué archivos toca, qué riesgos tiene (revisa la bitácora de
  CLAUDE.md por minas conocidas), y clasifícalo S/M/L/XL con una línea de justificación.
  No implementes nada todavía.
  ```
- **Ejemplo relleno:** `{cambio}` = "agregar zona (norte/oriente/costa/valles) al cliente para las hojas de ruta de M08".
- **Qué esperar:** lista de archivos + talla S/M/L/XL — insumo directo para que el Director asigne cuenta y modelo (regla: L/XL solo a cuentas Max).
- *Origen: `scope-a-change-before`*

### R-12 · Mapa de bordes antes de construir
- **Cuándo:** con la spec lista, antes de escribir el plan (alimenta los tests y el guion de QA).
- **Quién:** dueño → Forjador o Pro-2 Auditor.
- **Prompt:**
  ```
  Lista los estados de error, estados vacíos y casos borde que {funcionalidad} debe cubrir en
  DaliGo. Considera: móvil 375px, conexión mala (HostGator + celular en bodega), permisos por
  rol, datos históricos sucios de Bsale, y doble-click/doble-envío. Devuelve una tabla
  caso → comportamiento esperado → ¿test o QA manual?
  ```
- **Ejemplo relleno:** `{funcionalidad}` = "el formulario público de devoluciones (M13) con fotos obligatorias".
- **Qué esperar:** tabla accionable; las filas "test" se convierten en tests del plan, las "QA manual" en pasos del guion para la IA-QA.
- *Origen: `map-edge-cases-before`*

### R-13 · Plan multi-archivo → `docs/planes/` con SELLO
- **Cuándo:** justo antes de arrancar la unidad (nunca antes — regla de `docs/planes/README.md`).
- **Quién:** dueño → el Forjador que la construirá.
- **Prompt:**
  ```
  Planifica la implementación de {unidad} sin editar nada: lee el código actual de las áreas que
  toca, lista archivo por archivo qué cambiarías y en qué orden, con los tests por paso. Escribe
  el plan en docs/planes/PLAN-{unidad}.md con el sello de vigencia en la línea 2:
  "> **Estado: VIGENTE — verificado contra el código el {fecha} (commit {hash})**".
  Los pasos del plan deben mapear 1:1 con los P-xxx de la unidad en docs/RUTA-MAESTRA.md.
  ```
- **Ejemplo relleno:** `{unidad}` = "E2-M14 Aprobaciones" → `docs/planes/PLAN-M14.md`.
- **Qué esperar:** plan con sello verificable contra `git log`; si cita archivos que no existen o ignora código ya construido (como le pasó a PLAN-M11-FASE2), se rechaza.
- *Origen: `plan-a-multi-file`*

---

## 3. Construir el paso (Forjadores)

### R-20 · Trabaja el paso P-xxx de punta a punta
- **Cuándo:** al arrancar el objetivo de la sesión (el paso único declarado según PROTOCOLO-SESION §1).
- **Quién:** dueño → Max-1 (pasos en main) o Max-2 (pasos de M15 en `feature/m15-notificaciones`).
- **Prompt:**
  ```
  Trabaja el paso {P-xxx} de docs/RUTA-MAESTRA.md de punta a punta: lee su unidad y el plan
  vigente en docs/planes/ si existe, implementa siguiendo las convenciones de CLAUDE.md,
  escribe tests, córrelos y arregla los fallos. Si tocas Blade/CSS/JS, corre npm run build y
  commitea public/build. Termina proponiendo el commit; no marques [x] sin hash real.
  ```
- **Ejemplo relleno:** `{P-xxx}` = "P-M15-02 · NotificacionDispatcher + contrato Canal (CanalMail, CanalDatabase, CanalWhatsApp stub que loguea)".
- **Qué esperar:** código + tests verdes + build si aplica, y un mensaje de commit propuesto; la actualización de RUTA-MAESTRA va en el mismo push (regla del mismo push).
- *Origen: `work-an-issue-end`*

### R-21 · Sigue el patrón existente
- **Cuándo:** lo nuevo tiene un hermano mayor ya construido y probado en el repo.
- **Quién:** dueño → Forjador.
- **Prompt:**
  ```
  Mira cómo está implementado {ejemplo existente} para entender el patrón (controlador, modelo,
  vistas con x-componentes, permisos, seeder, tests) y construye {lo nuevo} de la misma forma.
  Si el patrón viejo contradice una regla de CLAUDE.md, gana CLAUDE.md — y me lo dices.
  ```
- **Ejemplo relleno:** `{ejemplo existente}` = "el CRUD de máquinas (`MaquinaController` + vistas + `MaquinaManagementTest`)", `{lo nuevo}` = "el CRUD de zonas de reparto".
- **Qué esperar:** consistencia total con el patrón citado (mismos componentes Blade, misma estructura de tests); cero markup inventado.
- *Origen: `follow-an-existing-pattern`*

### R-22 · Tests: escribe, corre y arregla
- **Cuándo:** un área quedó con código sin cobertura, o antes de refactorizar algo delicado.
- **Quién:** dueño → Forjador.
- **Prompt:**
  ```
  Escribe tests de Feature para {ruta o clase}, córrelos y arregla lo que falle. Cubre: el camino
  feliz, permisos (403 para roles sin acceso), validaciones con datos malos, y el caso borde de
  fechas si hay rangos (recuerda: whereDate, no whereBetween — bitácora 2026-06-30). Suite
  completa verde antes de terminar.
  ```
- **Ejemplo relleno:** `{ruta o clase}` = "`ProduccionLimpiarPruebas` (el comando artisan nuevo)".
- **Qué esperar:** tests que fallan primero por la razón correcta y terminan verdes; nunca un subset — la suite entera.
- *Origen: `write-tests-run-them`*

---

## 4. Antes del merge a main (gates)

### R-30 · Auto-revisión antes del push
- **Cuándo:** el Forjador terminó y está por commitear/pushear (main = deploy automático).
- **Quién:** dueño → el mismo Forjador que escribió el código.
- **Prompt:**
  ```
  Revisa tus cambios sin commitear (git diff + git status) y señala cualquier cosa riesgosa antes
  del push: secretos o credenciales, migraciones incompatibles con MySQL 5.7, borrados masivos
  sin guard, whereNotIn con array potencialmente vacío, N+1 evidentes, y clases Tailwind fuera
  de la paleta de 4 colores. Lista hallazgo → gravedad → fix propuesto. Si no hay nada, dilo
  explícitamente tras nombrar qué revisaste.
  ```
- **Ejemplo relleno:** tal cual (no tiene slots).
- **Qué esperar:** o una lista corta accionable, o un "revisé X, Y, Z: limpio" — nunca un "se ve bien" sin evidencia.
- *Origen: `review-your-changes-before`*

### R-31 · Auditoría adversarial de gates
- **Cuándo:** antes de que una rama (o un lote de commits en main) se dé por cerrada; siempre antes del merge de `feature/m15-notificaciones`.
- **Quién:** dueño → Pro-2 Auditor (nunca la cuenta que escribió el código).
- **Prompt:**
  ```
  Eres el auditor adversarial de DaliGo. Revisa el diff {rama-o-rango} contra main buscando
  razones para RECHAZAR, no para aprobar. Verifica cada gate: tests verdes (composer test);
  public/build recompilado si hubo Blade/CSS/JS; compatibilidad MySQL 5.7 (VARCHAR(191) en
  índices, whereDate y no whereBetween sobre fechas casteadas, ningún dropIndex que deje una
  FK sin cobertura); lockForUpdate en todo guard leer-luego-crear que mute inventario o estados;
  permisos por ruta; reutilización de x-componentes en vez de markup inline; responsive
  375/768/1024 sin scroll horizontal. Contrasta contra la bitácora de CLAUDE.md: ¿reincide en
  un error ya documentado? Entrega una tabla gate → OK/FALLO/NO VERIFICABLE con evidencia, y
  cierra con VEREDICTO: APROBADO | APROBADO CON OBSERVACIONES | RECHAZADO.
  ```
- **Ejemplo relleno:** `{rama-o-rango}` = "feature/m15-notificaciones" (antes de P-M15-09).
- **Qué esperar:** tabla de gates con evidencia concreta (archivo/línea/comando) y veredicto en el mismo formato que ya usa el ciclo de delegación — apto para archivar como evidencia en `docs/qa/`.
- *Origen: adaptación propia sobre `review-your-changes-before` (la versión auto-revisión es R-30)*

### R-32 · Huecos de cobertura
- **Cuándo:** el Auditor sospecha (o confirmó) que un área quedó floja de tests.
- **Quién:** dueño → Pro-2 detecta, Forjador rellena.
- **Prompt:**
  ```
  Identifica los archivos de {área} con menor cobertura de tests (compara métodos públicos vs
  tests existentes en tests/Feature) y lista qué casos faltan, del más riesgoso al menos.
  Después escribe esos tests, córrelos y arregla lo que destapen.
  ```
- **Ejemplo relleno:** `{área}` = "app/Services/Bsale/ (los 4 syncs)".
- **Qué esperar:** primero el diagnóstico rankeado (revisable), después los tests — no al revés.
- *Origen: `fill-gaps-from-a`*

### R-33 · Resuelve el conflicto con main
- **Cuándo:** la rama del stream 2 quedó atrás de main y el merge/rebase trae conflictos.
- **Quién:** dueño → Max-2 (el dueño de la rama).
- **Prompt:**
  ```
  Trae main a esta rama (git fetch + merge origin/main), resuelve los conflictos y explícame
  QUÉ conservaste de cada lado y por qué, archivo por archivo. Reglas: public/build NUNCA se
  resuelve a mano — se regenera con npm run build; los archivos compartidos (navigation, routes,
  seeder de permisos) conservan AMBOS aportes; ante duda de negocio, para y pregunta. Termina
  con la suite completa verde.
  ```
- **Ejemplo relleno:** tal cual, en la rama `feature/m15-notificaciones`.
- **Qué esperar:** explicación lado-por-lado (no un "resuelto"), build regenerado, 372+ verdes.
- *Origen: `resolve-merge-conflicts`*

### R-34 · Commit con mensaje generado
- **Cuándo:** trabajo terminado, gates pasados, listo para registrar.
- **Quién:** dueño → Forjador.
- **Prompt:**
  ```
  Commitea estos cambios con un mensaje en español que siga el estilo del repo (tipo(área):
  resumen — mira git log para calibrar), cuerpo con viñetas de qué y por qué, y recuerda commitear
  también docs/RUTA-MAESTRA.md actualizada en el MISMO commit/push (regla del mismo push).
  ```
- **Ejemplo relleno:** tal cual.
- **Qué esperar:** mensaje tipo `feat(m15): dispatcher multi-canal con reintentos` + la RUTA-MAESTRA dentro del mismo push.
- *Origen: `commit-with-a-generated`*

---

## 5. Depurar e incidentes (HostGator: la evidencia del servidor llega vía IA-cPanel)

### R-40 · Investiga un error reportado
- **Cuándo:** la IA-QA (o un usuario) reportó un error en staging con evidencia archivada.
- **Quién:** dueño → Forjador del área.
- **Prompt:**
  ```
  QA reportó: {síntoma textual, copiado de la evidencia}. Reproduce el problema localmente
  (migrate:fresh --seed + los datos mínimos), encuentra la causa raíz, arréglala, y agrega el
  test de regresión que habría atrapado esto. Si el error calza con una entrada de la bitácora
  de CLAUDE.md, dímelo; si es nuevo, deja la entrada escrita antes del commit.
  ```
- **Ejemplo relleno:** `{síntoma}` = "al aprobar un reporte devuelto y reenviado, el kardex duplica los movimientos de consumo de preforma".
- **Qué esperar:** reproducción local ANTES del fix; el fix llega con test de regresión y entrada de bitácora si aplica.
- *Origen: `investigate-a-reported-error`*

### R-41 · El test que falla
- **Cuándo:** la suite se puso roja (local o en el workflow de Tests).
- **Quién:** dueño → Forjador.
- **Prompt:**
  ```
  El test {nombre} está fallando. Averigua por qué antes de tocar nada: ¿el test protege un
  comportamiento correcto y el código lo rompió, o el comportamiento cambió a propósito y el
  test quedó viejo? Dime tu diagnóstico en una línea, luego arregla el lado correcto. Prohibido
  "arreglar" debilitando la aserción.
  ```
- **Ejemplo relleno:** `{nombre}` = "ProduccionKardexTest::test_aprobar_es_idempotente".
- **Qué esperar:** el diagnóstico ANTES del fix (rompió-código vs test-viejo); jamás una aserción diluida para que pase.
- *Origen: `find-and-fix-a`*

### R-42 · Incidente en producción
- **Cuándo:** algo se rompió en producción/staging después de un deploy (500, pantalla en blanco, deploy rojo, dato que no cuadra).
- **Quién:** dueño → Max-1 (si hay que tocar código) o Pro-3 (si primero hay que entender).
- **Prompt:**
  ```
  Incidente en producción: {síntoma exacto, textual}. Empezó {cuándo / tras qué deploy}.
  Investiga sin tocar nada todavía: revisa git log de los últimos deploys, la bitácora de
  CLAUDE.md por síntomas parecidos, y dime qué evidencia falta del servidor. Para esa evidencia
  NO tienes acceso: redáctame el prompt de delegación con la plantilla
  docs/delegacion/plantillas/VERIFICACION-CPANEL.md pidiendo los valores textuales
  (p. ej. últimas 50 líneas de storage/logs/laravel.log). Con la evidencia de vuelta, propón la
  causa raíz y el fix como commit nuevo — nunca force-push a main — y deja la entrada de bitácora.
  ```
- **Ejemplo relleno:** `{síntoma}` = "POST /admin/produccion/asignar devuelve 500 en staging y el deploy #103 quedó rojo en ~11s; el #102 fue verde" (caso real: `dropUnique` antes del índice de reemplazo, error MySQL 1553).
- **Qué esperar:** hipótesis rankeadas + prompt de delegación listo para pegar en la IA-cPanel; el fix llega recién cuando hay evidencia, con test que lo cubra y entrada de bitácora.
- *Origen: `investigate-a-production-incident`, fusionado con el ciclo de 6 pasos de PROTOCOLO-DELEGACION §1*

---

## 6. Investigar con datos (Investigador Pro-3)

### R-50 · Analiza este archivo de datos
- **Cuándo:** hay un CSV/Excel/log del negocio que hay que entender (ventas, órdenes ML, catastros).
- **Quién:** dueño → Pro-3.
- **Prompt:**
  ```
  Lee {archivo} y resume los patrones clave: totales, distribución, valores raros o sucios, y
  las 3 conclusiones más útiles para el negocio. Formato: tabla resumen + hallazgos en viñetas.
  Distingue SIEMPRE dato verificado de estimación. No modifiques el archivo.
  ```
- **Ejemplo relleno:** `{archivo}` = "el export de las 991 órdenes de Mercado Libre pendientes de facturar (contexto dali/ORDENES PENDIENTES...xlsx)".
- **Qué esperar:** números con fuente (fila/columna), datos sucios señalados, conclusiones accionables — insumo para briefs de DECISIONES.md.
- *Origen: `analyze-a-data-file`*

### R-51 · Optimiza contra una métrica
- **Cuándo:** algo está lento y se puede medir (nunca optimizar "a ojo").
- **Quién:** dueño → Forjador.
- **Prompt:**
  ```
  {objetivo} está lento: hoy tarda {medición actual} y debería bajar de {meta}. Primero MIDE
  (query log / timestamps) y dime dónde se va el tiempo; después optimiza solo eso, y vuelve a
  medir. Prohibido optimizar sin medición antes/después. Ojo con soluciones que MySQL 5.7 no
  soporta (sin CTE ni window functions).
  ```
- **Ejemplo relleno:** `{objetivo}` = "el panel del jefe de producción con 6 meses de historia", `{medición actual}` = "~3s", `{meta}` = "500ms".
- **Qué esperar:** medición → diagnóstico → fix → re-medición, con los números en el commit.
- *Origen: `optimize-against-a-measurable`*

---

## 7. Dirigir y corregir a la IA (el dueño al volante)

### R-60 · Frena y reencuadra
- **Cuándo:** la cuenta va por un camino equivocado a mitad de tarea.
- **Quién:** dueño → la cuenta desviada.
- **Prompt:**
  ```
  Detente. Antes de seguir, explícame en 3 líneas qué estás intentando y por qué. Lo que yo
  necesito es {lo que realmente quiero}. Replantea tu enfoque a eso y dime el nuevo plan en
  2-3 pasos antes de ejecutar.
  ```
- **Ejemplo relleno:** `{lo que realmente quiero}` = "que el soplador vea SOLO su reporte de hoy, no un dashboard con gráficos".
- **Qué esperar:** freno inmediato + plan corto realineado; si la cuenta insiste en su idea, es señal de contexto contaminado (cerrar sesión y rearrancar con /arranque).
- *Origen: `course-correct`*

### R-61 · Acota el alcance
- **Cuándo:** la tarea está creciendo sola (scope creep) o la cuenta propone "aprovechar de" hacer más.
- **Quién:** dueño → la cuenta que se está desbordando.
- **Prompt:**
  ```
  Esto se está agrandando. Recorta al MÍNIMO que cumple {objetivo del paso}: lista qué dejas
  fuera y agrégalo como pasos nuevos propuestos para docs/RUTA-MAESTRA.md (no los hagas).
  Termina solo lo esencial de este paso.
  ```
- **Ejemplo relleno:** `{objetivo del paso}` = "P-M15-06: campanita con contador de no leídas — sin preferencias, sin sonidos, sin marcar-todas".
- **Qué esperar:** entrega mínima + el resto convertido en pasos `[ ]` propuestos, nunca trabajo silencioso extra.
- *Origen: `narrow-scope`*

### R-62 · Convierte mi corrección en regla
- **Cuándo:** tuviste que corregir lo mismo dos veces (a la misma cuenta o a distintas).
- **Quién:** dueño → la cuenta corregida (o Pro-4 Escriba).
- **Prompt:**
  ```
  Te corregí esto: {la corrección}. Para que no se repita en ninguna cuenta, escribe la regla
  en CLAUDE.md: si es un error resuelto va como entrada de bitácora (plantilla del propio
  archivo); si es una convención nueva va en la sección de convenciones. Redáctala con el
  ejemplo bueno y el malo.
  ```
- **Ejemplo relleno:** `{la corrección}` = "usaste verde/ámbar en badges; la paleta es SOLO naranjo/negro/gris/blanco con rojo destructivo".
- **Qué esperar:** la regla escrita en el lugar correcto de CLAUDE.md — desde ese push, las 6 cuentas la heredan al hacer pull.
- *Origen: `turn-a-correction-into`*

---

## 8. Cierre y memoria

### R-70 · Captura lo memorable antes de cerrar
- **Cuándo:** al final de una sesión con aprendizajes que exceden la checklist mecánica de cierre.
  (Complementa — NO reemplaza — la checklist §3 de PROTOCOLO-SESION.)
- **Quién:** dueño → la cuenta que cierra sesión.
- **Prompt:**
  ```
  Antes del cierre formal: resume qué hicimos esta sesión y dime qué merece quedar escrito y
  DÓNDE según su tipo — ¿error resuelto? → bitácora CLAUDE.md; ¿convención nueva? → CLAUDE.md
  convenciones; ¿decisión de negocio? → DECISIONES.md; ¿aprendizaje del proceso? → entrada de
  BITACORA-SESIONES. Propón el texto de cada una y escríbelas donde corresponde.
  ```
- **Ejemplo relleno:** tal cual.
- **Qué esperar:** cada aprendizaje ruteado a su documento canónico (no un resumen suelto que se pierde).
- *Origen: `capture-what-to-remember`*

### R-71 · Empaqueta la tarea repetida como skill
- **Cuándo:** un prompt de este recetario se usa una y otra vez, igual, por 2+ cuentas cada semana.
- **Quién:** dueño → Max-1 (las skills viven en `.claude/skills/` del repo, main).
- **Prompt:**
  ```
  El prompt {R-xx} lo estamos usando repetido y sin cambios. Crea la skill /{nombre} en
  .claude/skills/{nombre}/SKILL.md siguiendo el patrón de las existentes (/arranque, /cierre,
  /pre-merge): frontmatter name+description y un cuerpo de ≤15 líneas que SOLO referencia y
  ejecuta el documento canónico — nunca copies el texto del prompt (regla anti-drift).
  ```
- **Ejemplo relleno:** `{R-xx}` = "R-30 auto-revisión", `/{nombre}` = "/auto-revision".
- **Qué esperar:** skill delgada que apunta al recetario/protocolo; el texto sigue viviendo SOLO aquí.
- **Regla de graduación:** un prompt se vuelve skill únicamente cuando 2+ cuentas lo usan cada semana — así el propio sistema decide las próximas.
- *Origen: `turn-a-recurring-task`*

---

## 9. Índice rápido por rol

| Rol | Prompts que más usa |
|---|---|
| **Dueño (Mauricio)** | R-01, R-02 (entender) · R-60, R-61, R-62 (dirigir) · despacha todos los demás |
| **Forjadores (Max-1/Max-2)** | R-13 (plan) · R-20, R-21, R-22 (construir) · R-30, R-33, R-34 (pre-merge) · R-40, R-41, R-51 (arreglar) |
| **Auditor (Pro-2)** | R-31 (gates) · R-32 (cobertura) · R-12 (bordes, en revisión de specs) |
| **Investigador (Pro-3)** | R-01, R-02 (explicar) · R-50 (datos) · R-42 (primera fase de incidentes) |
| **Escriba (Pro-4)** | R-62, R-70 (memoria y reglas) |
| **Director (Pro-1)** | ninguno — verifica que los demás los usen; los veredictos de R-31 alimentan su ledger |
