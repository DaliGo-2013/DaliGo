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

### §5.3 Operación — mapa F0-OPERACIÓN (auditoría Max-1, 2026-08-19, sobre `32406f28`)

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
