# PLAN-PARAMETRICOS · Cacería de hardcodes — lo que el dueño debería poder cambiar sin programador

> **Estado: VIGENTE (definido por el dueño, 2026-08-18)** — sucesor directo del
> PLAN-MENU-DENSIDAD (cerrado el mismo día, 47→32). Mismo ritmo por directriz raíz:
> «poco a poco, lento pero seguro, decisiones con calma». Módulo por módulo en el orden
> de la sidebar. Autor: Director, sobre el pedido textual del dueño.

## 0. El pedido en una frase

Buscar valores **hardcodeados que deberían ser paramétricos**, en orden por apartado —
primero Dashboard, después Comercial, y así por toda la app — con Max-1 ejecutando.

## 1. Los tres niveles (la casa ya los tiene — reusar, no inventar)

| Nivel | Mecanismo | Cuándo | Ejemplos vivos |
|---|---|---|---|
| **1 · Editable en caliente** | tabla `configuracion` + `Configuracion::get(clave, default)` + UI (`Admin/ConfiguracionController`) | parámetro de NEGOCIO que el dueño quiera mover sin deploy | umbrales de notificaciones, retención de auditoría, devoluciones |
| **2 · Config de despliegue** | `config/*.php` + env | decisión que NO debe moverse en caliente | `daligo.tz_negocio` (moverla desplazaría el día de negocio), `lista_precios_ventas`, `servicio_tecnico.dias_reparacion` por sucursal, `feriados.php` |
| **3 · Se queda hardcodeado** | código + comentario con el porqué | invariantes, doctrina, aritmética que no es parámetro | `aria-current="true"`, grid del tab-nav |

**El veredicto por hallazgo = elegir nivel.** La propuesta la hace el forjador en el
mapa; **la decisión es del dueño**, hallazgo por hallazgo (mismo esquema que los
veredictos del mapa F0 del menú). El criterio de daligo.php es la vara: si moverlo en
caliente puede romper la operación, es nivel 2, no 1.

## 2. Protocolo (heredado del PLAN-MENU-DENSIDAD, cerrado con cero rojos)

Por cada módulo, DOS fases:

**Fase A — Auditoría (SOLO DOCS, un dictado):**
Max-1 barre controllers + vistas + support/models del módulo buscando:
- **Números mágicos**: plazos, ventanas de días, umbrales, límites, tamaños de página,
  montos, porcentajes, capacidades.
- **Strings de negocio fijos**: nombres de empresa/sucursal/lista, correos, teléfonos,
  textos que cambian con el negocio (no labels de UI).
- **Listas que crecen**: sucursales, categorías, horarios, todo array literal que la
  operación pueda ampliar.
- **Duplicados**: el mismo valor repetido en N sitios (peor que hardcodeado: drifteable).

Entregable: **mapa en el anexo §5 de ESTE archivo** — tabla con: valor · dónde vive
(file:line) · qué controla en pantalla (en palabras del negocio, para que el dueño
decida sin leer código) · repeticiones · veredicto propuesto (nivel 1/2/3 con una línea
de porqué) · esfuerzo (S/M/L). **Cero código en fase A.**

**Fase B — Lotes (tras veredictos del dueño):**
- El dueño marca qué se parametriza y a qué nivel; el Director dicta lotes.
- Un lote = un merge = una doble llave; parte al buzón; verificación invariante del
  Director (suite completa local, cifra contrastada, CI verde, ancestría).
- **Regla de oro: parametrizar NO cambia comportamiento** — el valor actual queda como
  default; delta 0 tests salvo los candados nuevos del parámetro (candado mínimo: el
  default rinde idéntico + mover el parámetro mueve la pantalla — mutación de siempre).
- Nivel 1 exige además: la clave aparece en la UI de Configuración con label y ayuda en
  español del negocio, y validación de rango (un 0 o un negativo no puede romper la
  operación).

Nunca dos módulos abiertos a la vez. QA del dueño al cierre de cada módulo.

## 3. Orden de módulos (el de la sidebar, pedido del dueño)

1. **Dashboard** (F0-DASH — dictado v67, EN CURSO)
2. Comercial
3. Operación
4. Logística
5. Facturación
6. Administración
7. Mi producción
8. Mis entregas
9. Aprobaciones
10. Servicio Técnico (el más grande — al final de los operativos, con lo aprendido)
11. Plan del proyecto

Pantallas públicas (QR, visita industrial, devoluciones) se auditan CON el módulo dueño
de su flujo. Los módulos avanzan de a uno: auditoría → veredictos → lotes → QA → siguiente.

## 4. Registro

- Avance por módulo se anota aquí (§5 anexos) y en el bloque `E-PARAM` de RUTA-MAESTRA
  (se crea cuando el Dashboard tenga su primer avance real — así la página /plan lo
  muestra con pasos de verdad, no un mapa vacío).
- Partes al buzón como siempre; veredictos del dueño quedan escritos en el anexo del
  módulo.
- **UN SOLO FORJADOR (decisión del dueño 20-ago tarde, reemplaza el reparto del
  mismo día)**: TODO el PLAN-PARAMETRICOS es de **Max-1**, módulo a módulo en el
  orden de la sidebar como hasta ahora. Max-2 sigue en el frente de Mensajes
  (fase 2 del chat, alcance por definir con el dueño).
- **Espejo en Trello (pedido del dueño 19-ago)**: cada módulo tiene su tarjeta
  «Paramétricos · <Módulo>» en el tablero del dueño — el DIRECTOR la mueve por API:
  «Tareas DaliGo» (pendiente) → «En Curso DaliGo» (al dictar su auditoría) →
  «Terminadas DaliGo» (al cierre con QA, con los hallazgos como comentarios en
  lenguaje de negocio). Los forjadores no tocan Trello.

## 5. Anexos por módulo (los llena la fase A de cada uno)

### §5.1 Dashboard — SALDADO ✅ (QA del dueño 19-ago; 4 perillas + card viva en producción: `bcbfb00`, `0c2bcad`, `75cce08`)

Mapa F0-DASH (auditoría Max-1, 2026-08-18, sobre `a81b21d`):

Barridos completos: `DashboardController` (324 líneas), `DashboardColoresController`,
`AccesosDashboard`, `dashboard.blade.php`, `components/dashboard/acceso.blade.php` +
la aguas-arriba mínima que el pulso consume. Las 3 semillas del Director quedaron
confirmadas (#1, #2, #3). `DashboardColoresController` y `acceso.blade.php` no tienen
valores de negocio: validación estructural y UI pura.

**Resumen: 8 hallazgos — 4 propuestos nivel 1 · 0 nivel 2 · 4 nivel 3 (2 con duplicado
marcado) · 6 anotaciones cross-módulo.** Los veredictos son PROPUESTOS: decide el dueño.

| # | Valor | Dónde vive (file:line) | Qué controla EN PANTALLA | Repetido en | Veredicto propuesto | Esfuerzo |
|---|---|---|---|---|---|---|
| 1 | **7 días** (serie de producción) | `DashboardController.php:144` (`subDays(6)`, incluye hoy) | Cuántos días de producción muestran las mini-barras del Inicio | El texto «Últimos 7 días» (`dashboard.blade.php:100`) — si se mueve el valor sin el texto, el rótulo miente | **1** — puro alcance visual, moverlo en caliente no rompe nada; validación de rango (2-31) y el rótulo pasa a derivar del parámetro | S |
| 2 | **7 días anteriores** (referencia de merma) | `DashboardController.php:163` (`subDays(7)`; el borde «hasta ayer» es `:164`) | Contra cuántos días previos se compara la merma de hoy — el «prom. 7 días» junto a la merma | El texto «prom. 7 días» (`dashboard.blade.php:89`) | **1** — misma naturaleza que #1; es OTRA ventana aunque también diga 7 (parámetros separados) | S |
| 3 | **7 y 30 días** (cortes de antigüedad del taller) | `DashboardController.php:185-186`, buckets `:190-192` | Dónde parten los tramos 0-7 / 8-30 / 30+ de los equipos activos (la barra segmentada y el «N llevan 30+ días») | Los textos «llevan 30+ días» (`dashboard.blade.php:113`) y «0-7 días · 8-30 · 30+» (`:124`) — y la variable `$d7` la REUSA el flujo semanal (#4) | **1** — es la vara de «esto lleva mucho en el taller» y es del dueño; OJO al desacople: hoy mover `$d7` movería también la «última semana» de #4 | M |
| 4 | **7 días** («última semana» del flujo del taller) | `DashboardController.php:201-202` (reusa el `$d7` de `:185`) | Cuántos días cubre el «Última semana: entraron N · salieron N» del taller | El texto «Última semana» (`dashboard.blade.php:127`) + ACOPLADO por variable al corte 0-7 de #3 | **3** — una semana es una semana: mover el número dejaría el rótulo mintiendo. El acoplamiento con #3 sí se paga en su lote (variable propia) | S (el desacople) |
| 5 | **«Mirador, Coquimbo, Abate Molina, Buzeta»** | `AccesosDashboard.php:43` (desc de la card Sucursales) | El texto bajo la card Sucursales del Inicio nombra las sucursales una a una — si se abre o cierra una sucursal, el Inicio queda mintiendo hasta que un programador lo edite | La tabla `sucursales` de la BD (fuente viva del mismo dato) | **1** — string de negocio + lista que crece; derivar de la BD existente (sin clave nueva) o volverlo genérico. Decisión del dueño | S |
| 6 | **48 / 24 / 1 horas** (edad legible) | `DashboardController.php:312-318` | Cómo se redondea el «el más antiguo espera hace X» de las excepciones (días desde 48 h, «1 día» desde 24 h, horas desde 1 h) | — | **3** — convención de presentación temporal, no decisión de negocio; cambiarla no cambia ninguna acción | — |
| 7 | **Paleta de 8 colores** (personalización de cards) | `dashboard.blade.php:140-149` (`$paleta`, clases) **+** `AccesosDashboard.php:15` (`COLORES`, keys) | Qué colores puede elegir cada usuario para sus cards del Inicio | **Duplicado estructural en 2 sitios** — intencional y documentado: las clases DEBEN ser literales en un Blade (anti-purga Tailwind v4) y las keys las valida el controller | **3** — doctrina D-013 (paleta curada por el dueño) + restricción técnica del duplicado; agregar un 9º color seguirá siendo tocar 2 archivos, y el porqué está escrito en ambos | — |
| 8 | **Topes visuales de barras** (`min(100,…)`, `max(4,…)`, `max(1,…)`) | `dashboard.blade.php:86,96,117` + `DashboardController.php:154` | Que la barra de avance no desborde el 100 %, que un día con poca producción igual pinte una barrita visible (4 %) y que no haya división por cero | — | **3** — aritmética defensiva de presentación, sin significado de negocio | — |

**Anotaciones cross-módulo** (el hardcode vive aguas arriba o su dueño es otro módulo —
quedan para la auditoría de ese módulo, regla del dictado v67):

- **Servicio Técnico** — estados del taller como strings sueltos en las tarjetas del
  Inicio: `'entregado'` (`DashboardController.php:227`), `'recibido'`+`'cotizacion'`
  (`:240`, y `'recibido,cotizacion'` como query en `:241`), `'reparado'` (`:247`).
  **Duplican el catálogo de estados de `OrdenServicio`** — duplicado marcado aunque el
  veredicto probable sea 3 (claves de máquina): unificar a constantes ya paga solo.
- **Servicio Técnico** — qué cuenta como «equipo activo» del taller:
  `OrdenServicio::ESTADOS_PENDIENTES_TECNICO` (`OrdenServicio.php:868-871`). Lista que
  crece con el flujo de estados.
- **Servicio Técnico** — qué recepciones esperan confirmación:
  `OrdenServicio::FUENTES_POR_CONFIRMAR` (`:846-849`).
- **Administración (Notificaciones)** — la definición de notificación «caída terminal»
  (estado FALLIDA + sin reintento programado) está escrita como condición en
  `DashboardController.php:118-119`; el modelo de reintentos es del módulo de
  notificaciones.
- **Aprobaciones** — la excepción «Aprobaciones pendientes» espeja `Aprobacion::bandejaDe()`
  a propósito (el número = lo que se ve al hacer click); sin valores propios acá.
- **Operación (Producción)** — «reportes por aprobar» = `ProduccionReporte::pendientes()`
  (= estado ENVIADO, `ProduccionReporte.php:283-286`); definición del módulo dueño.

**VEREDICTOS DEL DUEÑO (2026-08-18, al Director):** los 4 nivel 1 APROBADOS (#1, #2,
#3, #5) y los 4 nivel 3 CONFIRMADOS como propuso el forjador (#4, #6, #7, #8). Fase B
del Dashboard en 3 lotes: **DASH-1** = #1+#2 (dos ventanas simples, dictado v68) →
**DASH-2** = #3 + desacople de #4 (cortes de antigüedad, esfuerzo M) → **DASH-3** = #5
(card Sucursales desde la BD). Un lote por dictado; el bloque `E-PARAM` de RUTA-MAESTRA
nace con el merge de DASH-1.

### §5.2 Comercial — SALDADO ✅ (QA del dueño 19-ago; COM-1 `8f40a96` + COM-2 `c594182` en producción)

Mapa F0-COMERCIAL (auditoría Max-1, 2026-08-19, sobre `1edbc8ec`):

Barridos completos: `ClienteController` (154 líneas) + `Cliente`, `ProductoController`
(564 — CRUD + import/export CSV + plantillas + clasificación interna) + `Producto`,
`ListaPrecioController` + `ListaPrecio` + `Precio`, y las 12 vistas del módulo
(clientes, productos, listas-precios, catalogo/_tabs). Las 3 semillas del Director
quedaron resueltas: la #1 creció (el `paginate(25)` es ×3, no ×2), la #2 y la #3
salieron limpias (ver declaraciones bajo la tabla).

**Resumen: 9 hallazgos — 2 propuestos nivel 1 · 0 nivel 2 · 7 nivel 3 (4 con duplicado
marcado) · 2 anotaciones cross.** Los veredictos son PROPUESTOS: decide el dueño.

| # | Valor | Dónde vive (file:line) | Qué controla EN PANTALLA | Repetido en | Veredicto propuesto | Esfuerzo |
|---|---|---|---|---|---|---|
| 1 | **Segmentos de cliente** (`mayorista`, `retail`, `recurrente`) | `Cliente::SEGMENTOS` (`Cliente.php:22`) | Las opciones del selector «Segmento» de la ficha del cliente y de su filtro en el listado — la clasificación comercial de la cartera | Fuente única bien hecha: filtro (`ClienteController:78`), validación (`:120`) y formularios (`:151`) leen la constante | **1** — lista que crece con el negocio: abrir un segmento nuevo (p. ej. «horeca») hoy es un deploy. OJO en la ayuda: AGREGAR es seguro; QUITAR deja clientes con un segmento que el filtro ya no ofrece | S/M |
| 2 | **«Repuestos industriales»** (categorías internas sugeridas) | `ProductoController::PRESETS_CATEGORIA_INTERNA` (`:48`) | Las categorías que el corrector masivo del catálogo SUGIERE aunque ningún producto las use todavía (datalist del filtro y de la corrección) | El placeholder «Ej. Repuestos industriales» (`productos/index.blade.php:123`) — el mismo string a mano | **1** — string de negocio + lista que crece: la próxima categoría curada del dueño no debería necesitar programador. El placeholder pasa a derivar | S |
| 3 | **25 por página** | `ClienteController:21`, `ProductoController:54`, `ListaPrecioController:42` | Cuántas filas muestran los listados de Clientes, Catálogo y el detalle de una lista de precios | **×3 en el módulo** (y es la convención de toda la app) | **3** — densidad de listado uniforme: una perilla fragmentaría la UX entre módulos y nadie pidió moverla. El DUPLICADO sí se marca: unificar a una constante compartida paga solo. Si el dueño la quiere perilla, que sea UNA global, no tres | S (unificar) |
| 4 | **50 errores mostrados** (resultado del import) | `productos/importar.blade.php:33,41,43` | Cuántas filas con error lista la pantalla tras importar un CSV (el resto se resume en «… y N más») | **×3 en la misma vista** (el slice, el if y la resta) | **3** — presentación defensiva (no inundar la pantalla); el duplicado se marca: una variable única en la vista | S (unificar) |
| 5 | **5 MB** (tope del CSV de import) | `ProductoController:143` (`max:5120`) | El tamaño máximo del archivo que acepta el import del catálogo | — | **3** — está acotado por los límites PHP del hosting compartido (`upload_max_filesize`): una perilla en caliente que prometa más de lo que la infra da sería inerte y confusa — la vara de daligo.php | — |
| 6 | **Lotes de 500** (streaming del export) | `ProductoController:314` y `:366` (`chunk(500)`) | Invisible en pantalla: cuántas filas carga en memoria por tanda el export y la plantilla de medidas | **×2** | **3** — aritmética de memoria, no negocio; duplicado marcado (constante única) | S (unificar) |
| 7 | **Topes de peso/medidas** (`9999999.999` / `99999999.99`) | `ProductoController:251-254` (import) y `:433-436` (formulario) | El máximo que aceptan peso y dimensiones de un producto antes de rechazar el dato | **×2** (las mismas 4 reglas en el import y en el form) | **3** — espejan el esquema (`decimal(10,3)/(10,2)`, comentado en el código: evitan el «Out of range» de MySQL); duplicado marcado (extraer las reglas a una constante compartida) | S (unificar) |
| 8 | **Tolerancias del import** (tokens `si/sí/true/verdadero/activo`…, extensiones `csv/txt`) | `ProductoController:554-559` y `:147` | Qué escrituras de «activo» entiende el CSV y qué extensiones de archivo se aceptan | — | **3** — whitelist de entrada: ampliarla es código con test (un token mal interpretado desactiva productos en silencio, el riesgo que el propio código documenta) | — |
| 9 | **Roles de cartera** (`vendedor`, `jefe_ventas`) | `ClienteController:142` | Qué usuarios aparecen como «Vendedor» asignable en la ficha del cliente | Única en Comercial (las `ROLES_AVISO_*` de ST/Agenda son OTRO concepto: destinatarios de avisos, no cartera) | **3** — estructura de permisos: un rol de ventas nuevo llega con código y seeder, no en caliente | — |

**Semillas #2 y #3 del dictado — declaradas limpias:**

- **`daligo.lista_precios_ventas` SIN desvíos en el módulo**: `Producto::precioVentaConIva()`
  (`Producto.php:126-152`) es la fuente única y respeta la clave; su fallback al
  «criterio antiguo» solo corre si la clave NO está configurada (entornos de prueba,
  documentado en el propio método). La pantalla de edición del producto muestra TODAS
  las listas espejadas a propósito — es espejo informativo de Bsale, no una elección
  de lista. Cero rincones eligiendo lista por su cuenta.
- **Comercial NO tiene cotizaciones propias** (grep cero en sus 7 PHP): la vigencia
  (`cotizacion_vigencia_dias`) es de ST, sin mezcla.

**Anotaciones cross** (para la auditoría de su módulo):

- **Infra Bsale (sin apartado propio en el orden — que el Director decida dónde cae)**:
  `ListaPrecio::COIN_CLP = 1` (`ListaPrecio.php:26`) — el id de la moneda CLP en Bsale,
  contrato del espejo. Bien tenido: constante única con 2 consumidores (los badges CLP
  de `listas-precios/index:20` y `show:13`).
- **Servicio Técnico**: `config('servicio_tecnico.categorias_equipo')` (consumida por
  `Producto::scopeEquipoTaller`, `Producto.php:88-115`) — nivel 2 YA parametrizado y de
  ST; el modelo de Comercial solo aloja el mecanismo (con normalización tolerante y
  lista-vacía-no-filtra ya documentadas).

**VEREDICTOS DEL DUEÑO (2026-08-19, al Director):** #1 y #2 (los dos nivel 1) APROBADOS → lote **COM-1** (claves JSON `clientes_segmentos` + `catalogo_categorias_sugeridas`, dictado v73); mini-lote de higiene de los 4 duplicados APROBADO → **COM-2** (v74); los 7 nivel 3 CONFIRMADOS con sus porqués.

Convenciones revisadas y sin fila a propósito: `max:191` (el `defaultStringLength` de
MySQL 5.7 utf8mb4, doctrina de la casa), `Precio::formatear` (formato chileno CLP,
convención de presentación) y los `orderBy` de los listados (alfabéticos, consistentes).

### §5.3 Operación — SALDADO ✅ (QA del dueño 20-ago «todo ok»; fase B completa en producción: OPE-1 `47513bc` + OPE-2 `576ad95` + OPE-3 `ab0a8d1`) — mapa F0-OPERACIÓN (auditoría Max-1, 2026-08-19, sobre `32406f28`)

Barridos completos: `ProduccionController` (838 líneas — panel, drill-downs, asignar,
aprobar, kardex, kaizen) + `ProduccionVivoController`, los 4 servicios de producción
(`Oee`, `CorteSic`, `Moldes`, `SemaforoPreformas`), los modelos del módulo (Reporte,
Registro, Asignación, Movimiento, Parada, Corte, Mejora, Maquina, Molde, Receta,
TipoBotellon, Bodega, Stock), los 4 controllers del hub E1 + `BodegaController` +
`ProduccionNotaController`, y las vistas. Con respeto de autor (M11 es de Max-2): los
porqués citados existen en el código y calzan.

**El hallazgo-marco: el módulo más denso es el MEJOR parametrizado del proyecto.** M11
se construyó ya con la doctrina de la casa (D-003, «hipótesis editables») — lo que en
Dashboard/Comercial fue cacería acá es mayormente CENSO de lo ya vivo:

**Ya parametrizado (nivel 1/BD+UI vivos — nada que hacer):** `produccion_minutos_turno`
(duración del turno, clave con clamp) · `produccion_turnos` (horarios por turno, clave
JSON con validación y fallback) · `produccion_umbral_proyeccion` (el % del semáforo SIC,
clave, default 85) · `umbral_ajuste_produccion_unidades` (el umbral del motor M14 para
ajustes del jefe, vía `ReglaAprobacion.umbral_config`) · `oee_target` (meta OEE POR
MÁQUINA, columna nullable) · `umbral_mantencion` y `cavidades` (POR MOLDE, columnas) ·
`ciclo_ideal_seg` (POR RECETA, BD+UI) · bodegas 100 % paramétricas (M04) · la meta del
semáforo de preformas = las asignadas del turno (dato vivo, cero umbral fijo).

**Resumen: 13 hallazgos — 3 propuestos nivel 1 · 1 nivel 2 · 9 nivel 3 (4 con
duplicado/adopción marcada) · 2 grupos cross.** Los veredictos son PROPUESTOS.

| # | Valor | Dónde vive (file:line) | Qué controla EN PANTALLA | Repetido en | Veredicto propuesto | Esfuerzo |
|---|---|---|---|---|---|---|
| 1 | **7 días (panel) / 30 días (informes)** | `ProduccionController.php:125` (`$ventana = 6`) · `:306` y `:351` (`rango($request, 29)`) | Cuántos días miran, al abrirse, el panel del jefe y los informes por máquina y por tipo | El `29` ×2 (máquina y tipo) | **1** — primo exacto del #1 del mapa Dashboard que el dueño YA aprobó (misma naturaleza: ventana de mirada); claves separadas panel/informes | M |
| 2 | **92 días** (tope del filtro de fechas) | `:138-139` (el valor ×2 contiguo) | Hasta dónde se puede estirar el rango de los informes (la tabla diaria se arma en PHP) | ×2 en el mismo statement | **3** — límite de render comentado en el código; el duplicado contiguo se unifica al pasar | S |
| 3 | **`%preforma%` / `%dañada%`** | `:448` (categoría LIKE) · `:472-473` (exclusión, closure compartida con la validación `:497` ✓) | Qué productos del catálogo aparecen como «preforma del turno» al asignar — y cuáles quedan fuera por dañados | Los literales viven una vez cada uno (el criterio ya es closure única) | **2** — primo exacto de `categorias_equipo` de ST (config de despliegue): moverlo en caliente puede vaciar el selector en medio de un turno; el fallback todos-los-activos ya degrada con gracia | S/M |
| 4 | **Turnos `dia` / `noche`** (los NOMBRES) | `ProduccionController.php:26` (`TURNOS`, Rule::in) + claves del default de `produccion_turnos` (`CorteSic.php:42`) | Qué turnos existen al asignar producción | Los HORARIOS ya son clave viva, pero los NOMBRES están en constante — **acoplamiento declarado**: agregar un turno «tarde» a la clave de horarios NO lo haría asignable | **3** — abrir un tercer turno es cambio de flujo (asignar, reportes, SIC, avisos), lote de código con tests, no perilla. El acoplamiento queda anotado para ese día | — |
| 5 | **`max:100000`** (tope anti-dedazo) | `:492` (asignar) y `:798-802` (ajustar, ×5) | El máximo que aceptan las cantidades del jefe antes de rechazar («revisa el número») | **×6 en el archivo** (+ los de Mi producción, cross) | **3** — guardia anti-typo comentada, no capacidad de negocio; el duplicado se marca: constante única al pasar (primo COM-2) | S (unificar) |
| 6 | **Racha crítica: 2 cortes** | `CorteSic.php:152` (`$racha >= 2`) | Cuándo el semáforo del panel vivo pasa de «en riesgo» (naranjo) a «crítico» (rojo): dos cortes seguidos bajo el umbral sin recuperarse | — | **3** — definición de escalamiento con su porqué escrito; el % del umbral SÍ es perilla y ya existe (`produccion_umbral_proyeccion`) | — |
| 7 | **60 minutos mínimos para proyectar** | `CorteSic.php:52` | Cuánto turno debe haber pasado antes de que el panel vivo proyecte la meta (con menos, la proyección lineal es ruido) | — | **3** — guardia estadística del motor, comentada (además evita la división por cero) | — |
| 8 | **20 segundos** (refresco del panel vivo) | `vivo.blade.php:122` (`20000`) | Cada cuánto se actualiza sola la pantalla del panel vivo | — | **3** — frecuencia técnica: más rápido es más carga al hosting compartido, no es decisión de negocio | — |
| 9 | **Motivos de parada** (7) **+ subconjunto planificado** (2) | `ProduccionParada.php:43` y `:55` | Qué motivos puede tocar el operario al registrar una parada de máquina — y cuáles NO descuentan la disponibilidad del OEE (mantención y cambio de molde son «planificadas») | Fuente única ✓ (validación, chips y clase derivan de las constantes) | **1 con OJO** — lista que crece del taller (molde COM-1: LISTAS_SIMPLES); exige el par planificados ⊆ motivos y el matiz de que la clase se PERSISTE al crear la parada (cambiar la lista solo afecta paradas futuras — el OEE histórico no se reescribe, verificado en `claseDe()`) | M |
| 10 | **Propósitos de bodega** (6) | `Bodega.php:31` (`PROPOSITOS`) | La clasificación de cada bodega del espejo (física, virtual, tránsito, insumos, taller, cerrada) que filtra listados y decide qué mira el semáforo de preformas | Fuente única ✓ | **3** — claves con lógica colgada (filtros, semáforo, wizard de baja): agregar o renombrar es código, no dato | — |
| 11 | **Rangos del hub** (cavidades hasta 64, umbral de molde hasta 100 M, ciclo ideal hasta 600 s, cantidades de receta hasta 1000) | `MoldeController.php:93-94`, `RecetaController.php:52-58` | Los topes que aceptan los formularios de Configuración de producción | — | **3** — espejan la física del proceso y el esquema; el DATO que importa ya es por-fila (BD+UI) | — |
| 12 | **`paginate(25)` ×2** | `ProduccionController.php:698` (kardex) · `BodegaController.php:51` | Filas por página del kardex y del inventario | La convención global (×15 en la app) | **3** — adopción de `Controller::POR_PAGINA` pendiente (el mecanismo quedó listo en COM-2) | S |
| 13 | **Procedencias `saco` / `caja`** | `ProduccionAsignacion.php:15` (`PROCEDENCIAS`) | En qué formato llegó la preforma del turno (selector del form de asignar) | Fuente única ✓ (selector + validación) | **1** — lista chica que crece con la logística real (¿granel?); molde COM-1 exacto | S |

**Semillas del dictado, respondidas:**

- **#1 (`pendientes()` y el censo de catálogos de estado)**: TODOS los catálogos de
  estado del módulo son claves de máquina con flujo colgado — estados de reporte
  (borrador/enviado/devuelto/aprobado), clases y orígenes de parada, estados de molde,
  decisiones de mejora (kaizen). **Ninguno es lista-que-crece**: nivel 3 en bloque, sin
  fila propia. Las listas que sí crecen son las de la tabla (#9, #13).
- **#2 (`categorias_equipo`)**: dueño ST, consumidor Operación vía
  `Producto::scopeEquipoTaller` — **sin drift** (un solo consumidor, normalización
  tolerante y lista-vacía-no-filtra ya documentadas). Nada que mudar.
- **#3 (umbrales M11)**: censados arriba — casi todos YA parametrizados (ver el
  hallazgo-marco); lo que queda fijo (#6, #7) es aritmética del motor con porqué.
- **#4 (residuales M04)**: cero encontrados — bodegas 100 % BD+UI, el semáforo de
  preformas deriva su meta de las asignadas del turno y su universo de los propósitos.

**Anotaciones cross** (para la auditoría de su módulo):

- **Mi producción** — los **45 días** del historial del operario
  (`ProduccionReporte.php:32`, constante fuente-única consumida por
  `MiProduccionController:69,109` y `mi-historial.blade.php`); los catálogos de motivos
  del soplador (`ProduccionRegistro::MOTIVOS_DEFECTO`,
  `ProduccionReporte::MOTIVOS_DIFERENCIA`, `::NOTAS_COMUNES` — candidatas naturales a
  LISTAS_SIMPLES cuando toque ese apartado); y sus `max:100000` de tandas. Las
  constantes viven en modelos de ESTE módulo pero la pantalla es del otro: se auditan
  allá, con la nota de que la fuente es compartida.
- **Servicio Técnico** — `categorias_equipo` (semilla #2: sin drift, solo constancia).

**VEREDICTOS DEL DUEÑO AL MAPA §5.3 (2026-08-19, al Director):** los 3 nivel 1
APROBADOS (#1 ventanas panel/informes, #9 motivos de parada con su subconjunto
planificado, #13 procedencias) + el nivel 2 APROBADO (#3 criterio de preforma a config
de despliegue) + los 9 nivel 3 CONFIRMADOS + mini-lote de higiene APROBADO. Fase B de
Operación en 3 lotes: **OPE-1** (ventanas, molde DASH-1, dictado v77) → **OPE-2** (las
dos listas: motivos con par planificados⊆motivos + procedencias, molde COM-1) →
**OPE-3** (config de preforma estilo categorias_equipo + higiene: max:100000 ×6 a
constante, 92 ×2, adopción de POR_PAGINA ×2).

**OPE-1 EN PRODUCCIÓN (2026-08-20, merge `47513bc`, doble llave):** las 3 ventanas
(panel 7 días rango 2-31; informes máquina/tipo 30 días rango 7-90) como claves del
grupo `produccion`, rótulos e info-tip derivando (rebote v77.1: el tip del panel
decía «últimos 7» en prosa — cazado por el Director, derivado con +3 aserciones).
Suite combinada con MSG-4: 2253/15.764 cero rojos. Quedan OPE-2 (dictado v78) y OPE-3.

**OPE-2 EN PRODUCCIÓN (2026-08-20, merge `576ad95`, doble llave):** 3 claves TIPO_JSON
(`produccion_motivos_parada` = los 7 vivos, `produccion_motivos_planificados` = 2,
`produccion_procedencias_preforma` = saco/caja, LISTAS_SIMPLES) + **`PARES_SUBCONJUNTO`
(4º hermano declarativo)**: hijo⊆madre validado en las dos direcciones con RECHAZO que
nombra a la otra clave (sin auto-arreglo — la lista gobierna el OEE). Candado
OEE-histórico-intacto en 2 escenarios (la clase se persiste; etiqueta del Pareto viva
declarada cosmética). Suite combinada con MSG-5: 2291/16.002 cero rojos. Queda OPE-3
(dictado v79 — cierra el módulo).

**OPE-3 EN PRODUCCIÓN (2026-08-20, merge `ab0a8d1`, doble llave) — FASE B DE
OPERACIÓN COMPLETA.** `config/produccion.php` nuevo (patrones %preforma%/%dañada%
con el porqué del nivel 2 en el archivo; la doble vuelta de la Ñ derivada;
selector y validación moviéndose juntos, candado con config() en runtime) +
higiene delta-cero (TOPE_CANTIDAD, MAX_DIAS_RANGO con candado de 93 filas,
POR_PAGINA ×2 adoptado). Suite 2303/16.048 (+4/+19 exacto, sin +24 — sin claves
de seeder, predicho). La mutación puso rojo un candado VIEJO: la constante es lo
que ese test ya vigilaba. **Los 4 hallazgos aprobados del mapa §5.3 forjados en
3 lotes. PENDIENTE: QA del dueño del módulo completo → card a Terminadas.**

### §5.4 Logística — mapa F0-LOGÍSTICA (auditoría Max-1, 2026-08-20, sobre `bea00037`)

Barridos completos por sub-bloques: **Despachos** (controller + `DespachoService` +
modelos + cola/QR/escaneo + monitor de bodega), **Hojas de ruta** (controller +
`HojaRutaService` + cadena R11 + vistas), **Cargas/Simulador** (los 5 servicios de
`Carga/`, el catálogo `camiones_simulacion`, cargas reales, link público),
**Vehículos M18 + Conductores** (flota, documentos, avisos, `FlotaExcel`) y
**Traslados por baja de bodega** (residual M04, deslindado de §5.3). Además la **PWA
del conductor** (`Entregas/EntregaConductorController` + `offline-queue.js`), que no
cuelga de Admin. Método: censo por sub-bloques en paralelo (fleet de 5 censadores)
sobre `bea00037` + **verificación propia con `sed -n` de cada `file:line` citado** y
veredictos con la vara de `daligo.php` — la garantía del mapa es la misma de los
tres F0 anteriores.

**El hallazgo-marco: la ESTRUCTURA está parametrizada; lo que falta son PANTALLAS y
lo que sobra son RÓTULOS GEMELOS.** El catálogo del simulador
(`camiones_simulacion`) tiene TODO por columna-por-fila (medidas, peso máximo,
pasillo, geometría y topes de ejes, silueta, activo) y el motor no tiene ni un
número de camión hardcodeado — pero los VALORES solo se editan en
`CamionesSimulacionSeeder` (deploy): el pedido de Trello del dueño («capacidad de
carga») se resuelve con **una pantalla CRUD sobre la tabla que ya existe**, no con
una parametrización. La flota M18 es el módulo mejor parametrizado-por-fila del
proyecto (fechas, capacidades, tipos de documento en BD con su UI). El lastre real
es una familia de **textos que repiten a mano el número que ya vive en el dato** —
incluido uno que miente HOY.

**Ya parametrizado (nada que hacer):** `camiones_simulacion` completo por fila
(estructura) · flota M18: capacidades/medidas/5 fechas de ley por columna + tipos de
documento nuevos en BD con UI (`vehiculo_documento_tipos`) · zonas, sucursales,
conductores (rol spatie + tabla `conductores`), flota activa y cobro-por-parada de
las hojas de ruta: todo BD por fila · factor real de aprovechamiento: calculado de
`cargas_reales` · plantillas de los correos (claves `notif_plantilla_*`) · vigencia
del link público del plan de carga: constante única con rótulo derivado
(`PlanCargaPublicoController::DIAS_VIGENCIA = 7`) · tope de apilado/estiba/pallet:
editables por el usuario en pantalla · `MAX_INTENTOS = 5` de la cola offline
(`offline-queue.js:32`): motor anti-bucle con porqué escrito, compartido con las
tandas de Mi producción (cross).

**Resumen: 14 hallazgos destacados — 4 propuestos nivel 1 · 0 nivel 2 · 10 nivel 3
(6 con duplicado/drift marcado) · 3 bloques nivel 3 en masa · 2 respuestas de
semilla que no son parametrización.** Los veredictos son PROPUESTOS.

| # | Valor | Dónde vive (file:line) | Qué controla EN PANTALLA | Repetido en | Veredicto propuesto | Esfuerzo |
|---|---|---|---|---|---|---|
| 1 | **30 días «Por vencer»** de la flota | `Vehiculo.php:29` (`DIAS_AVISO`) | Cuándo un documento del vehículo deja de estar «Al día» y pasa a badge naranjo — en el listado, la ficha, el Excel y los hitos del aviso diario | El «30» EN PROSA ×3: `vehiculos/index.blade.php:51` («Por vencer (30 días)»), `FlotaExcel.php:129`, descripción del comando | **1** — primo exacto de los cortes DASH-2 (misma naturaleza: franja de antigüedad); los 3 rótulos gemelos se DERIVAN en el mismo lote (doctrina DASH-2). La `DIAS_VENTANA_VENCIDO = 30` del comando (`VehiculosAvisarVencimientos.php:43`, cuánto hacia atrás se re-avisa un vencido) es OTRO concepto: clave hermana o nivel 3, a veredicto | M |
| 2 | **Métodos de cobro en puerta** (`efectivo`/`cheque`/`transbank`) | `EntregaConductorController.php:103` (y `:121`, el required condicional) | Qué opciones ve el conductor al cobrar en la entrega — la plata que rinde al volver | ×2 en el mismo archivo | **1** — lista-que-crece real (¿transferencia?); molde OPE-2 (LISTAS_SIMPLES). Ojo: NO confundir con `estado_cobro` de la parada (`pagado`/`cobrar_en_entrega`/`credito`), que es flujo (nivel 3) | S/M |
| 3 | **Relación del receptor** (`empresa`/`conserje`/`otro`) | `EntregaConductorController.php:102` | Quién recibió en la puerta (evidencia de la entrega) | Fuente única ✓ | **1** — lista chica que crece (¿familiar?, ¿vecino?); mismo molde, mismo lote que #2 | S |
| 4 | **12 tarjetas** del monitor de bodega | `DespachoController.php:178` (`limit(12)`) | Cuántas cargas muestra el TV colgado en bodega (cola «McDonald's»); el resto queda como «Se muestran las 12 más antiguas de N» | El rótulo YA deriva del count ✓ | **1** chico — densidad de pantalla que depende del TV del local, decisión del dueño | S |
| 5 | **100 folios** del selector | `DespachoController.php:71` · `HojaRutaController.php:61` | Cuántos documentos sin despachar se ofrecen al crear despacho/hoja: con 100+ pendientes el folio viejo «no está» y NO hay buscador | ×2 (mismos 100, pantallas distintas) | **3 con nota UX** — subir el tope no arregla el fondo (falta buscador, se anota como mejora aparte); el duplicado se unifica al pasar | S |
| 6 | **La vigencia del QR del despacho NO EXISTE** | `Despacho.php:139` (`signedRoute`, no `temporal`) | El QR pegado en la carga no caduca jamás; el único control temporal es el ESTADO (un QR ya retirado grita «doble retiro») | — | **Semilla respondida, no hallazgo**: hoy no hay perilla que mover. Introducir caducidad sería FUNCIÓN nueva (cambia comportamiento) — lote aparte si el dueño la pide | — |
| 7 | **90 %** del aviso «Al filo de la carga máxima» | `carga/index.blade.php:339` (`* 0.9`) | Cuándo la barra de peso del simulador se pone roja antes de pasarse | ×2 (`_numeros.blade.php:44`) | **3** — margen de advertencia del motor; el duplicado se unifica (constante o var compartida) | S |
| 8 | **3 cargas para «confiable» / historial 100** | `CargaRealController.php:37` (`MINIMO_PARA_PROMEDIAR`) · `:44` | Cuándo el factor real de una combinación camión+producto se declara promediable, y sobre cuántas cargas se promedia | Rótulo deriva ✓ | **3** — decisión estadística del motor con constante nombrada; el dueño ES el usuario y no la ha pedido | — |
| 9 | **Textos-que-mienten (familia)** — el gemelo en prosa del dato | «cada 15 minutos» (`bodegas/traslados/show.blade.php:57`) **HOY FALSO**: el sync corre `hourlyAt(45)` (`routes/console.php:38`) · «folio 1000» (`hojas-ruta/index.blade.php:67` vs `FOLIO_PISO=999`) · «15 MB» ×2 (`VehiculoController.php:390` vs `max:15360` real) · «llave N de 3» ×3 (`HojaRutaController.php:124…`) · medidas del pallet en prosa (`carga/index.blade.php:1331`) · los «30 días» de #1 | Lo que el usuario LEE vs lo que el sistema HACE | Multi-sitio | **Higiene fase B (prioridad)** — derivar cada rótulo del dato que describe; el «cada 15 minutos» es FIX inmediato (promesa activa falsa: la baja se cierra en ≤1 h, no ≤15 min) | S |
| 10 | **188** (recorte de textos de evidencia) | `DespachoService.php:151`, `:210`, `:357` (`Str::limit`) · `DespachoController.php:217` · `EntregaConductorController.php` ×2 (`max:188`) | Cuánto sobrevive del «qué quedó pendiente», del motivo de rechazo en puerta y de la evidencia del escaneo (con recorte SILENCIOSO en el service) | ×6 | **3** — constante única con su porqué (191 de BD − «…»); unificar al pasar | S |
| 11 | **`paginate(25)` ×2** | `DespachoController.php:52` · `HojaRutaController.php:36` | Filas por página de despachos y hojas de ruta | La convención global | **3** — adopción `Controller::POR_PAGINA` (molde COM-2/OPE-3) | S |
| 12 | **`[RETIRADO, EN_RUTA]` ×4** («ya salió de bodega») | `DespachoService.php:128`, `:199` · `Despacho.php:145`, `:177` | El corazón del anti-fraude: qué estados gritan «doble retiro», cuáles admiten cierre de entrega y qué cuenta como «en reparto» | ×4 | **3** — extraer a UN método/scope con nombre (`Despacho::yaSalioDeBodega()` o similar): hoy agregar un estado obliga a encontrar los 4 | S |
| 13 | **Correo por ROLES vs campanita por PERMISOS** (mismo evento) | `DespachoService.php:396` (`User::role([jefe_despacho, jefe_logistica, admin])`) vs `Notificacion.php:226` (`canAny([...])`) | Quién se entera de una entrega rechazada en puerta: el correo va a una lista de roles a mano y la campanita se resuelve por permisos | Inconsistencia interna | **3** — unificar por PERMISOS (la lección del técnico industrial, bitácora 14-08: un rol fuera de la lista es un aviso que no existe) | S |
| 14 | **Topes desalineados UI vs servidor** en cubicar | `carga/_cubicar.blade.php:147` (1.200 cm) vs `SimuladorCargaController.php:122` (1.500/300) · `_cubicar.blade.php:184` (9.999) vs `:111` (100.000) | El panel rechaza en el borde lo que el servidor aceptaría — drift real entre las dos puertas | ×2 pares | **3** — alinear derivando la UI del mismo número del servidor | S |

**Bloques nivel 3 en masa (claves de máquina con flujo — sin fila propia):** los
estados de `Despacho` (5), la cadena R11 completa de `HojaDeRuta` (estados +
transiciones + resultados/cobros de parada), los veredictos de `EscaneoDespacho`,
los estados de `BodegaTraslado`, y los catálogos de `Vehiculo`
(estados/tipos/combustibles/orden-de-prioridad del semáforo) — TODOS con lógica
colgada; agregar o renombrar es código. · La aritmética del motor de carga
(apilabilidad `SOPORTA_ENCIMA`, redondeos, factor ≤ 1.0, imán de 4 cm del acomodo,
siluetas por largo) — motor con porqués escritos. · Los formatos de los 3 Excel
(columnas/anchos/colores/nombres de archivo) y los `d-m-Y H:i` — presentación.

**Semillas del dictado, respondidas:**

- **#1 (capacidades/dimensiones del simulador)**: la estructura YA es por-vehículo
  (tabla `camiones_simulacion`, columnas completas incl. ejes y pasillo; el motor
  lee `CamionSimulacion::paraCalculo()` sin un solo número hardcodeado); los
  VALORES se editan solo por seeder (deploy) — con la historia del 204→200 escrita
  encima (bitácoras 07-08 y seeder). **El pedido de Trello se resuelve con una
  pantalla CRUD** (lote de función, no de parametrización), y las medidas «se miden,
  no se estiman» seguiría siendo la doctrina de esa pantalla. Aparte: la caja del
  vehículo REAL (M18) ya es editable en su ficha (`largo/ancho/alto_util_cm`).
- **#2 (PWA conductor, umbrales de cola)**: `MAX_INTENTOS = 5` — MOTOR (anti-bucle
  con servidor caído, porqué escrito, recargar fuerza otro intento); compartido con
  Mi producción. Nivel 3.
- **#3 (QR anti-fraude, ventanas de validez)**: no existen (fila #6) — el control
  es por estado, y la firma es permanente a propósito (el QR va IMPRESO en la
  carga). Caducidad = función nueva, no perilla.
- **#4 (`paginate(25)`)**: ×2, fila #11 (molde listo de OPE-3).
- **#5 (`max:` y topes)**: censados — el grueso son guardias anti-dedazo con
  sentido (`100000000` de pesos en el cobro de puerta, `100000` de unidades,
  15 MB de fotos); lo accionable son los DESALINEADOS (fila #14) y el `100000`
  ×N del simulador que se unifica si se toca ese archivo.

**Anotaciones cross** (para el anexo de su módulo):

- **Servicio Técnico** — `TrasladoServicio` es de ST (traslado de máquinas al
  taller; verificado: sus filas son `OrdenServicio`, permisos
  `despachar/recibir traslado servicio`). Pendientes anotados para su F0:
  `TrasladoServicioController.php:29` y `:32` (listas const
  `ROLES_AVISO_TALLER`/`ROLES_AVISO_DIFERENCIA` — la misma familia de la fila #13).
- **Mi producción** — `MAX_INTENTOS` de `offline-queue.js` es compartido (la cola
  de tandas): si algún día se toca, es UNA constante para los dos módulos.
- **Inventario/M04 (§5.3)** — sin doble conteo: acá solo entró el ciclo de baja/
  traslado; el umbral `0.0001` de «llegó stock nuevo» (`BajaDeBodegas.php:27`) es
  motor de comparación de decimales (nivel 3, porqué evidente).
