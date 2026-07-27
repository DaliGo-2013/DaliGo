# PLAN-M16-V1 · De accesos directos a herramienta de DECISIÓN — investigación + propuesta
> **Estado: PROPUESTA (fase 1, SIN código) — sellado contra el código el 2026-07-14 (main @ `9ea830c`)**
> **GATE: visto bueno de Mauricio sobre la opción elegida ANTES de la primera línea de código** (lección de v0: construir sin validar el propósito = retrabajo).

> **Contexto:** M16-v0 funciona técnicamente (QA del dueño en teléfono, 14-07) pero el veredicto fue
> *"el dashboard es un despropósito: solo son accesos directos a distintos apartados"*. Este plan
> investiga qué hace bueno a un dashboard (fuentes leídas de verdad, no de memoria), audita el v0
> card por card contra esos principios, inventaría QUÉ datos existen HOY (verificado archivo:línea)
> y propone 3 opciones de diseño con recomendación.

---

## 1. Principios de un buen dashboard (investigación, fuentes leídas con WebFetch)

Consolidados de Nielsen Norman Group, Stephen Few (Perceptual Edge), Wayne Eckerson (TDWI),
Lean Enterprise Institute y ProKanban. Parafraseados; título + URL de cada fuente.

| # | Principio | Qué dice (parafraseo) | Fuente |
|---|---|---|---|
| P1 | **Un dashboard NO es un menú** | Definición canónica de Few: la información MÁS importante para un objetivo, en UNA pantalla, monitoreable de un vistazo. La metáfora es el tablero del auto: monitorear y detectar lo que exige respuesta — no navegar ni explorar. Un home de accesos directos falla la definición: entrega navegación, no estado. | Few, *Dashboard Confusion Revisited* — perceptualedge.com/articles/visual_business_intelligence/dboard_confusion_revisited.pdf |
| P2 | **Cada bloque responde una pregunta explícita** | Antes de elegir el gráfico: ¿qué debe entender quien mira? El medio se deriva de la pregunta. Si un bloque no responde ninguna pregunta del que lo mira, se elimina. | NN/g (Moran), *Choosing Chart Types* — nngroup.com/articles/choosing-chart-types/ |
| P3 | **Número sin contexto = ruido (vanity metric)** | Un acumulado que solo crece ("2.517 entregados históricos") da ilusión de información. Toda cifra necesita 1-2 referencias: meta/asignado, periodo anterior, tasa o umbral — para que el vistazo produzca un juicio ("vamos atrasados"), no un dato. | NN/g (Harley), *Vanity Metrics* — nngroup.com/articles/vanity-metrics/ · Few, *Common Pitfalls in Dashboard Design* (error #2) — perceptualedge.com/articles/Whitepapers/Common_Pitfalls.pdf |
| P4 | **Medida directa, no dos cifras a restar** | Si la decisión usa la desviación, mostrar LA desviación ("92% de avance", "Δ −100"), no el real y la meta por separado obligando a calcular de cabeza. Precisión justa (redondear a la unidad de decisión). | Few, *Common Pitfalls* (errores #3-4) |
| P5 | **Tendencia > foto del momento** | La historia reciente es contexto casi siempre necesario: un "hoy" que se ve bien puede venir cayendo hace una semana. Resolver con series compactas (sparklines/mini-barras) junto al valor. | Few, *Rich Data, Poor Data* — perceptualedge.com/articles/Whitepapers/Rich_Data_Poor_Data.pdf |
| P6 | **Gestión por excepción (andon)** | Lo normal se ve quieto; solo lo desviado llama la mirada. El andon de planta muestra el estado de un área en una mirada y señala la anomalía para respuesta inmediata. Destacar todo = no destacar nada (Few, errores #9-10). | Lean Enterprise Institute, *Andon* — lean.org/lexicon-terms/andon/ · Eckerson, *Performance Dashboards* (TDWI) |
| P7 | **Capas monitor → análisis → detalle** | El home da la SEÑAL con destino; el porqué y el detalle viven en el drill-down (divulgación progresiva). Coincide con la regla ya aprendida en la bitácora del proyecto: toda alerta contadora necesita una superficie donde ver y actuar sus ítems. | Eckerson, *Performance Dashboards* — download.101com.com/pub/TDWI/Files/PerformanceDashboards.pdf · NN/g (Kaplan), *Complex Applications* — nngroup.com/articles/complex-application-design/ |
| P8 | **Rol-céntrico** | Operacional (línea, intra-día) ≠ táctico (jefe, diario/semanal) ≠ estratégico (dueño, resúmenes). Un dashboard genérico para todos falla para todos; se diseña por el rol que decide. | Eckerson, *Performance Dashboards* · Few (definición 2013, vía dataplusscience.com/DashboardDefinition.html) |
| P9 | **Aging de colas: la edad manda** | La edad del ítem VIVO es el indicador líder para intervenir a tiempo (el cycle time es retrospectivo). Un conteo esconde al ítem estancado, que es justo el que pide acción: "3 por aprobar" informa menos que "el más viejo espera hace 2 días". | ProKanban, *Kanban Pocket Guide* cap. 6 (métricas de flujo, Vacanti) — prokanban.org |
| P10 | **Percepción preatentiva: longitud y posición** | Barras y líneas se leen sin pensar; ángulo/área (tortas, gauges, 3D) se juzgan mal y roban espacio. Color para categoría, nunca como único canal. El bullet graph de Few codifica estado con INTENSIDADES de un solo matiz — exactamente compatible con la paleta brand/neutral de DaliGo. | NN/g (Laubheimer), *Dashboards: Preattentive* — nngroup.com/articles/dashboards-preattentive/ · Few, *Bullet Graph Design Spec* — perceptualedge.com/articles/misc/Bullet_Graph_Design_Spec.pdf |
| P11 | **Una pantalla / priorización brutal** | Fragmentar con scroll destruye la comparación (memoria de corto plazo ≈ 4 chunks). En móvil 375px: pocas señales densas > muchas tarjetas. Cero decoración/variedad gratuita (errores #6, #11-13). | Few, *Common Pitfalls* (error #1) |
| P12 | **Right-time + test de 5 segundos** | La frescura la fija la decisión, no la tecnología (la grilla `*/15` del hosting basta si se es honesto con "espejo ~1h" donde aplique). Validación final: en 5 segundos el que mira debe captar el estado; si no, el diseño falla. | Eckerson, *Performance Dashboards* · NN/g (Chan), *5-Second Usability Tests* |

## 2. Auditoría del v0 card por card (contra los principios)

Clasificación: **DECISIÓN** (responde pregunta + gatilla acción) / **NAVEGACIÓN** (solo lleva a otra pantalla) / **RUIDO** (ni lo uno ni lo otro).

| Card v0 | ¿Qué pregunta responde? | ¿Acción? | ¿Contexto? | Veredicto |
|---|---|---|---|---|
| Producido hoy | "¿cuánto llevamos?" | ver el día | ❌ sin meta visible (está repartida en otra card — viola P4) | decisión DÉBIL |
| Avance de hoy (%) | "¿vamos bien vs lo asignado?" | ídem | ⚠️ meta implícita pero separada del producido | decisión DÉBIL |
| Merma de hoy (%) | "¿mucha merma?" | — | ❌ sin referencia (¿3% es normal?) — viola P3 | decisión DÉBIL |
| Reportes por revisar | "¿qué espera mi aprobación?" | aprobar | ⚠️ sin edad (P9) | **DECISIÓN** (la mejor del v0) |
| Equipos en taller (215) | "¿cuánto hay adentro?" | — | ❌ sin antigüedad ni tendencia — 215 no dice si crece o baja | decisión DÉBIL |
| Cotización (espera cliente) | "¿quiénes esperan respuesta?" | gestionar | ⚠️ sin edad | **DECISIÓN** |
| Reparado | "¿qué está listo para entregar?" | entregar | ⚠️ | NAVEGACIÓN |
| Entregado (2.517) | ninguna — acumulado histórico eterno | — | ❌ vanity metric de libro (P3) | **RUIDO** |
| Sin solución | ninguna — acumulado histórico | — | ❌ | **RUIDO** |
| Recepciones por confirmar | "¿qué llegó y nadie recibió?" | confirmar | ⚠️ sin edad | **DECISIÓN** |
| Aprobaciones pendientes | "¿qué espera mi visto bueno?" | aprobar | ⚠️ sin espera ("hace cuánto") | **DECISIÓN** |
| Productos sin medidas (2.857) | backlog eterno sin dueño ni ritmo | nadie lo limpia hoy | ❌ | **RUIDO** en el home (vive mejor dentro del Catálogo) |
| Clientes (total) | ninguna | — | ❌ vanity | RUIDO / navegación |
| Usuarios (total) | ninguna | — | ❌ vanity | RUIDO / navegación |
| Notificaciones fallidas | "¿se cayó algún aviso?" | reintentar/revisar | ✅ excepción pura | **DECISIÓN** (solo debería aparecer si > 0) |

**Balance de las 15: 5 de decisión (4 de ellas sin edad/espera), 4 débiles, 1 de navegación y 5 de ruido (2 de esas, a lo sumo navegación)** → el veredicto
del dueño se confirma con los principios: el v0 informa poco y no jerarquiza nada. Lo rescatable:
las cards de EXCEPCIÓN (por revisar / por confirmar / pendientes / fallidas) son el germen correcto.

## 3. Inventario de datos disponibles HOY (verificado en el código, archivo:línea)

| Dato | Cómo (query agregada, 5.7-safe) | Evidencia | ¿Hoy? |
|---|---|---|---|
| Serie de producción por día (producido/merma/tasas/avance, ceros rellenados, hasta 92 días) | Ya calculada: `construirTendencia` + `reportesPorDia` (whereDate + groupBy) + `asignadasPorDia` | `ProduccionController.php:168/131/155` | ✅ (helpers `private` → hay que exponerlos) |
| Meta natural del día: asignadas | `SUM(asignadas)` de `produccion_asignaciones` del día — así lo hace el panel | `ProduccionController.php:74` | ✅ (no existe otra tabla de metas) |
| Fórmulas canónicas | `ProduccionReporte::armarResumen()` estático público (v0.1) | `ProduccionReporte.php:221` | ✅ |
| Alertas de producción (por aprobar / devueltos / atrasados hoy) | Ya computadas en el panel | `ProduccionController.php:44-54` | ✅ |
| Ranking sopladores / por máquina / por tipo | `desgloseSopladores` / `desgloseRegistros` (1 query c/u) | `ProduccionController.php:200/216` | ✅ |
| Latencia envío→aprobación de reportes | `enviado_at` / `revisado_at` (datetime) | `ProduccionReporte.php:84` | ✅ |
| **Aging de órdenes ST activas** | `fecha_ingreso` (date, indexada) + estados activos (`pendientesTecnico`); buckets 0-7/8-30/30+ días | migración `2026_06_15_120000:45`, `OrdenServicio.php:415` | ✅ |
| Flujo ST entradas/salidas por semana | Entradas por `fecha_ingreso`; salidas por `fecha_entrega` (nullable — histórico puede venir vacío; fallback estado terminal) | `OrdenServicio.php:170` | ✅ con salvedad |
| ST por confirmar + su espera | scope `porConfirmar()` + `created_at` | `OrdenServicio.php:393` | ✅ |
| ST ruta/categoría (nuevos de M12) | `GROUP BY ruta` / `categoria` — históricos en NULL, tolerar | migraciones `2026_07_14_120000:19` (ruta) y `2026_07_14_130000:19` (categoria) | ✅ |
| Espera de aprobaciones pendientes / tiempo de resolución | `created_at` (índice `[estado, created_at]`), `resuelta_at`, `escalada_at` | migración `2026_07_07_160000:56-61` | ✅ |
| Notificaciones fallidas TERMINALES | `estado='fallida' AND programada_para IS NULL` (índice existente) | `EnviarNotificacion.php:60` | ✅ |
| Espejo Bsale: stock por bodega, productos, clientes, precios | agregaciones directas; **NO existe stock mínimo/umbral** (grep: 0) → "stock crítico" requiere primero una definición de negocio | migraciones inventario | ✅ lectura (sync ~1h) |
| **Ventas por día / por zona / despachos** | `documentos_venta` (`emitido_at`, montos) + `zonas`/`despachos` — SOLO en `origin/feature/despachos-v1` (P-DSP de Max-2, sin mergear) | migraciones `2026_07_14_180000/210000` (rama) | ❌ **post-merge despachos** |

**Portabilidad de las queries de edad (SQLite en tests / MySQL 5.7 en prod):** `DATEDIFF`/`YEARWEEK`
no existen en SQLite → los límites de bucket se calculan **en PHP (Carbon)** y las queries usan
`whereDate` por rango (3 COUNTs, o 1 query agrupada por fecha y rollup en PHP). Cero window functions.

## 4. Opciones de diseño

### Opción A — «Pulso del día» (andon + excepciones + tendencia) — ⭐ RECOMENDADA

La pregunta de cada franja, en orden de jerarquía (P6/P7/P11):

```
┌─ 375px ──────────────────────────────────────┐
│ ① ¿DEBO ATENDER ALGO AHORA?  (solo si > 0)   │
│   ● 3 reportes por aprobar · el más viejo    │
│     espera hace 2 días            → cola     │
│   ● 2 recepciones sin confirmar (ayer) → ST  │
│   ● 1 aprobación espera hace 5 h  → bandeja  │
│   (todo al día → una línea quieta:           │
│    "Operación al día ✓" en neutral)          │
│ ② ¿CÓMO VIENE EL DÍA / LA SEMANA?            │
│   Producción HOY: ▓▓▓▓▓▓▓░░ 450/500 (90%)    │
│   merma 4% (prom. 7d: 5%)                    │
│   últimos 7 días: ▂▄▆▅▇▆█ (mini-barras CSS)  │
│   Taller: 215 activos — 12 llevan 30+ días   │
│   sem.: entraron 18 / salieron 15            │
│ ③ ZÓCALO: accesos directos compactos         │
│   (los actuales, como lista/chips, al final) │
└──────────────────────────────────────────────┘
```

- **①** es gestión por excepción con **aging** (P6+P9): cada línea = señal + edad + destino
  (P7 y la regla de bitácora "alerta con superficie donde actuar"). Se renderiza SOLO lo desviado.
- **②** son 2 bloques de contexto: producción como **medida directa** (una barra producido-vs-asignado
  estilo bullet + merma con su promedio 7d como referencia + mini-serie, P4/P5/P10) y taller con
  aging + flujo semanal. Los % se leen por longitud, no por gauge.
- **③** conserva TODO el v0 como navegación compacta (nada se bota — baja de jerarquía).
- **Roles** (P8): mismas franjas, filtradas por permiso como hoy — jefe_bodega ve producción+
  aprobaciones+confirmar; técnico ve taller; admin todo; el dueño ve el pulso completo.
- **Datos:** 100% disponibles hoy (§3). **Costo: M** (2 pasos de construcción + gate).

### Opción B — «Scorecard con contexto» (evolución mínima del v0)

Mantener la grilla de cards pero: (1) cada card sobreviviente gana su comparación directa
(producido → "450/500 · 90%"; merma → "4% · prom 5%"; colas → "+ el más viejo hace N días") y
micro-tendencia 7d donde aplique; (2) las 5 cards-ruido salen del home (Entregado histórico,
Sin solución, Clientes, Usuarios, Productos sin medidas → zócalo/al Catálogo); (3) periodo
explícito en cada label. **Costo: S/M.** Menos re-aprendizaje visual, pero conserva la
fragmentación de tarjetas (P11: en móvil sigue siendo una lista larga de números sueltos) y
no introduce la jerarquía por excepción (P6) — mejora los números, no el propósito.

### Opción C — «Home por rol» (Eckerson completo)

Composición DISTINTA por rol: operacional para técnico (taller aging + cola del día), táctico
para jefes (opción A), estratégico para el dueño (semana/mes + ventas por zona + margen).
Máximo alineamiento con P8, pero: **ventas aún no está en main** (post-merge despachos) → la
vista del dueño nacería coja; y duplica superficies a mantener. **Costo: L.**

### Recomendación de Max-1

**Opción A ahora**, tomando de B la técnica para los KPI del bloque ② (medida directa + referencia),
y dejando **C como evolución natural v1.1 post-merge de despachos** (la franja "ventas de la
semana por zona" para el dueño ya quedó inventariada: `documentos_venta.emitido_at` + montos +
`zonas` — entra como un bloque más de la estructura A sin rediseñar nada).
El test de aceptación es el de 5 segundos (P12): Mauricio abre el Inicio en su teléfono y en
5 segundos responde "¿está todo bien o qué tengo que ir a mirar?".

## 5. Restricciones duras (del dictado — verificadas viables)

- **Paleta 4 estricta:** excepciones = `brand` (relleno/peso); normal = `neutral` atenuado; rojo
  solo destructivo. El bullet graph de Few usa intensidades de UN matiz — se implementa con
  `brand-600/brand-200/neutral-200` sin colores nuevos.
- **Sin librerías de charts:** mini-barras CSS `style="width:%"` (patrón ya probado en el panel,
  bitácora 2026-07-01) + a lo sumo SVG inline liviano. El hosting no tiene Node; el bundle no se infla.
- **Mobile-first 375/768/1024**, sin scroll horizontal; franjas colapsables si crecen.
- **Queries agregadas sin N+1**, MySQL 5.7 (sin window functions; aging con rangos `whereDate`
  calculados en PHP — portable a SQLite de tests), strings indexados ≤191.
- Los helpers de agregación de `ProduccionController` (hoy `private`) se exponen vía service o
  estáticos del modelo (mismo camino que ya tomó `armarResumen` en v0.1) — no se duplican queries.

## 6. Pasos propuestos (si se aprueba la Opción A)

| Paso | Entregable | Talla |
|---|---|---|
| P-M16V1-01 | Capa de datos: exponer helpers de agregación + queries de excepciones con aging (portables SQLite/MySQL 5.7) + tests de conteo/edad | M |
| P-M16V1-02 | Vista: franja excepciones + pulso (producción/taller) + zócalo; responsive 3 anchos; build + grep | M |
| P-M16V1-03 | Gate R-31 + merge doble llave + **QA de 5 segundos con Mauricio en staging** (el criterio de aceptación del rediseño) | S |

v1.1 (fuera de alcance, post-merge despachos): franja «Ventas de la semana por zona» para el dueño.

## 7. GATE

**Nada de código hasta el visto bueno de Mauricio sobre la opción** (A recomendada; B si prefiere
cambio mínimo; C si prefiere esperar despachos y hacerlo de una vez). El Director valida este plan
y se lo presenta al dueño.
