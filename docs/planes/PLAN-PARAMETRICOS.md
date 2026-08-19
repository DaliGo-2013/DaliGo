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
