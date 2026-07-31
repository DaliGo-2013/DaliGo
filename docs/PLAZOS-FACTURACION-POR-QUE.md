# Por qué cada plazo es ese plazo — facturación desde DaliGo

**Preparado por:** desarrollo DaliGo · 30 de julio de 2026
**Complementa:** `docs/FACTURACION-ELECTRONICA.md` (informe formal) y la carpeta de la reunión
del 29-jul (`docs/REUNION-GERENCIA-2026-07-29.md`).

> **Para qué existe este documento.** La hoja de niveles 1/2/3 entrega los plazos ("1 a 2 días",
> "1 semana", "4 a 6 meses") pero no dice **de qué está hecho** cada uno. Si alguien pregunta
> *"¿por qué una semana y no dos días?"*, esto es la respuesta, tarea por tarea.
>
> Cada plazo se abre en tres partes: **el trabajo** (qué hay que escribir), **la espera** (qué no
> depende de nosotros) y **el riesgo** (qué lo puede alargar). Todo lo que se afirma sobre el estado
> actual está referenciado a un archivo del proyecto, para que sea verificable y no una opinión.

---

## 0. Las dos reglas que explican todos los números

**Regla 1 — la unidad de medida.** "1 día" = **un día de una persona a tiempo parcial**, unas 5 a 6
horas efectivas. No es un día de calendario. Por eso 7 a 13 días de trabajo se convierten en 2 a 3
semanas de calendario, y con las esperas de terceros en 1 a 1,5 meses.

**Regla 2 — trabajo ≠ espera.** La mayor parte de la diferencia entre "2 a 3 semanas" y "1 a 1,5
meses" **no es trabajo**: es esperar una credencial, dos respuestas de Bsale y una autorización. Esa
distinción es la que conviene tener clara en la reunión, porque **acelerar el trabajo no acorta la
espera**.

**Y una tercera, que es la que la gente no ve.** En este módulo, cada tarea incluye sus pruebas
automáticas y su revisión adversarial (el gate R-31 del protocolo del proyecto). No es celo: en los
últimos lotes ese gate encontró errores reales que las pruebas normales no vieron — un botón
«Enviar» que no hacía nada, un 404 que dejaba al usuario sin salida, una confirmación que reventaba
con ciertos nombres de cliente (`CLAUDE.md`, bitácora de hallazgos). Saltarse esa revisión **en el
módulo que emite documentos tributarios** es exactamente donde menos conviene: acá un error no se
borra, se corrige con nota de crédito.

---

# NIVEL 1 — Facturar desde DaliGo, con Bsale detrás

## 1.1 · Leer de la cuenta de Bsale los identificadores — **1 a 2 días**

**Qué es.** Bsale no entiende "sucursal Mirador" ni "efectivo": entiende números internos suyos
(`officeId`, `paymentTypeId`, `documentTypeId`). Hay que ir a leerlos de la cuenta real y anotarlos.

**Estado hoy:** los tres mapas están **vacíos a propósito** en `config/dte.php:70-100`, y sin ellos
el sistema se niega a emitir nombrando la clave exacta que falta, en vez de adivinar.

**El trabajo (≈ 7 a 10 horas):**

| Paso | Tiempo | Por qué toma ese tiempo |
|---|---|---|
| Escribir el comando de lectura (`dte:emitir-prueba`) | 3-4 h | **No existe todavía.** `config/dte.php:15-17` lo nombra como el paso B6, pero en `app/Console/Commands/` solo están los seis comandos de sincronización. Hay que escribirlo: que lea los tres listados y los imprima en formato listo para pegar en la configuración |
| Elegir los 4 `documentTypeId` (factura 33, boleta 39, guía 52, nota de crédito 61) | 1 h | No es copiar un número: la empresa puede tener **más de un tipo de documento con el mismo código** (dos series de factura, una por sucursal). Bsale elegiría una y no hay forma de saber cuál (`config/dte.php:64-68`). Hay que mirar la lista real y decidir |
| Mapear las 3 sucursales a `officeId` | 1 h | **Los ids no coinciden entre el ambiente de prueba y producción** (`config/dte.php:82-83`), así que el mapeo se hace **dos veces**, una por ambiente |
| Mapear los medios de pago a `paymentTypeId` | 1 h | Un medio de pago sin mapear no puede fallar en silencio: la regla 7 de Contabilidad dice que el pago se registra al emitir, y un documento sin pago queda descuadrado en el cierre de caja |
| Pruebas que cierren el mapeo | 1-2 h | Que un mapeo faltante siga lanzando el error con nombre de clave, y no vuelva a quedar abierta la puerta de "emitir con un valor adivinado" |

**La espera (esto es lo que manda):** hace falta **una credencial de prueba etiquetada**. Sin token
no hay nada que leer. Y ojo con un detalle de seguridad que ya está puesto: una credencial **sin
etiquetar** se trata como producción (`config/dte.php:36-42`), o sea que hay que declarar
`BSALE_AMBIENTE=prueba` explícitamente. Es lo contrario de lo cómodo, y es a propósito.

**El riesgo:** ninguno grande. Es la tarea más predecible de la lista.

---

## 1.2 · Conectar el botón de emitir y el flujo completo — **1 semana**

**Esta es la que más se pregunta.** "Si el código de traducción ya está hecho, ¿por qué una semana?"
Porque lo que está hecho es **el traductor**, no **la pantalla ni los caminos que salen mal**.

**Lo que ya existe** (y por eso no son tres semanas):

- `app/Services/Bsale/BsaleEmisor.php` — 432 líneas: arma el mensaje que espera Bsale.
- `app/Services/Dte/EmisionDte.php` — 147 líneas: emite y registra, con el candado de idempotencia.
- `app/Services/Dte/DocumentoDesdeOrdenServicio.php` — 358 líneas: convierte una reparación en
  documento, con el desglose que pidió Contabilidad.
- Dos pantallas: `admin/dte/index` y `admin/dte/estado`.

**Lo que NO existe:** la ruta de emisión. En `routes/web.php:263-265` hay exactamente dos rutas del
módulo — `dte.index` y `dte.estado` — y ninguna emite. El botón no está desconectado: **no está**.

**El trabajo (≈ 5 días):**

| Paso | Tiempo | Por qué |
|---|---|---|
| Ruta + acción de emitir | 0,5 día | Es la pieza chica, y es la única que la frase "conectar el botón" describe bien |
| Formulario de emisión | 1 día | Tiene que preguntar **tres cosas obligatorias** y validarlas: boleta o factura (regla 2: lo decide el cliente), medio de pago (regla 7: se registra en el momento) y sucursal donde se reparó (regla 6). Y si es **factura**, exigir RUT, giro y dirección del cliente — sin eso el documento no es válido, y hoy ese flujo no pide esos datos |
| Conectar la previsualización | 0,5 día | `BsaleEmisor::previsualizar()` ya existe y **no está conectado a ninguna pantalla**. Es la pieza que permite ver el documento exacto que se enviaría **sin emitir nada**. Es lo que hace la etapa de pruebas posible, así que no es un lujo |
| Los cuatro caminos que salen mal | 1 día | Caja cerrada en Bsale, emisión bloqueada por el candado, mapeo faltante, error de red. Son **cuatro mensajes distintos**, cada uno con su "qué hacer ahora". La regla del módulo (`DteController.php:26-29`) es que nada en pantalla finja funcionar: un botón que parece emitir y no emite es peor que no tenerlo |
| Permisos en las rutas nuevas | 0,5 día | El rol *Jefe de sucursal* y el permiso de anular ya se crearon el 28-jul. Falta atarlos a las rutas nuevas y probar que quien no tiene permiso recibe un aviso amable, no una pantalla de error |
| Pruebas | 1 día | El módulo ya tiene 7 archivos de prueba (`tests/Feature/Dte/`). Cada camino nuevo pide el suyo, y en particular el de **idempotencia**: que un doble clic no genere dos documentos con folio real. Ese candado es físico (índice único en la base de datos, `EmisionDte.php:18-32`) y hay que probar que sigue en pie |
| Gate R-31 + revisión en celular/tablet/escritorio | 0,5-1 día | Ver la tercera regla de la §0 |

**El riesgo que lo puede alargar:** **la caja**. Bsale respondió que para que el documento cuadre en
el cierre hay que asociar *"la sucursal, la caja y la forma de pago"*, pero **su propia
documentación de la API no expone ningún campo de caja** (informe §9 y §11, pregunta 2). Si la
repregunta obliga a usar otro camino, el formulario cambia. Es la razón por la que esta tarea se
estima en una semana y no en cuatro días.

---

## 1.3 · Verificar la nota de crédito contra la cuenta real — **1 a 2 días**

**Qué es.** La nota de crédito es la única forma de anular un documento electrónico. El código está
escrito (`BsaleEmisor.php:97-121`) — pero **nunca se ejerció contra Bsale**.

**Por qué eso importa, y está dicho en el propio archivo** (`BsaleEmisor.php:33-38`): el formato de
`documents.json` (emitir) **sí** está confirmado contra la API real; el de `returns.json` (anular)
sale **solo de la documentación pública**. Hasta que se pruebe, ese método es un borrador. Y es el
método que hay que usar justamente cuando algo salió mal, con el cliente adelante: no es el momento
de descubrir que el mensaje tenía otra forma.

**El trabajo (≈ 8 a 12 horas):**

| Paso | Tiempo | Por qué |
|---|---|---|
| Emitir en la cuenta de prueba, anular, comparar la respuesta real campo por campo | 4 h | Hoy el código cubre **dos formas posibles** de respuesta (`$respuesta['creditNote'] ?? $respuesta`) porque no se sabe cuál devuelve Bsale. Eso se resuelve mirando, no leyendo documentación |
| Confirmar el `type` | 2 h | `type 1` anula sin devolver stock; `type 0` devuelve stock además. Si está al revés, **cada anulación por error de emisión devuelve al inventario un repuesto que nunca salió de bodega** — y el descuadre lo descubre alguien haciendo un conteo, semanas después |
| Pantalla de anulación con motivo obligatorio y registro de quién anuló | 4 h | Contabilidad definió cuatro personas autorizadas y no pidió visto bueno previo, así que la trazabilidad **es** el control |
| Pruebas | 2-3 h | Incluido que nadie fuera de esas cuatro personas pueda anular, ni siquiera quien emitió |

**La espera:** la misma credencial de prueba de la tarea 1.1.

**El límite honesto:** el ambiente de prueba de Bsale **no es electrónico** (confirmado por escrito).
Esto verifica **la forma del mensaje**, no el efecto tributario. El efecto tributario se ve en la
primera anulación real, y por eso la nota de crédito va preparada de antemano en la tarea 1.5.

---

## 1.4 · Seguimiento automático del estado ante el SII — **2 a 3 días**

**Qué es.** Que el sistema vaya solo a preguntar si el SII aceptó cada documento, y avise cuando lo
rechaza. Un documento rechazado que nadie ve es una venta sin respaldo válido.

**Lo que ya está resuelto** (y por eso son días y no semanas): el vocabulario de estados está escrito
en un solo lugar (`EstadoSii.php`, 109 líneas), incluido el detalle más peligroso: la escala de Bsale
es **contraintuitiva** — `0` = aceptado, `1` = enviado, `2` = rechazado. Un `if` mal escrito
mostraría como pendiente algo que el SII ya aceptó, o al revés. Eso ya está encapsulado.

**El trabajo (≈ 15 a 21 horas):**

| Paso | Tiempo | Por qué |
|---|---|---|
| Comando programado que reconsulte los documentos "enviados" | 4-6 h | "Enviado" **no es un estado final**: hay que volver a preguntar hasta que el SII decida |
| Ubicarlo en el reloj del servidor | 2 h | El hosting es **compartido**: el cron de cPanel corre cada 15 minutos, así que una tarea que no caiga en :00/:15/:30/:45 **no corre nunca** (está documentado en `routes/console.php:14-16`). Y hay que meterla sin chocar con las cuatro sincronizaciones que ya ocupan esos espacios |
| Aviso cuando el SII rechaza | 4-6 h | Al que emitió **y** a los que pueden anular. Un rechazo silencioso es el peor caso de todo el módulo |
| Separar "error de red" de "rechazo del SII" | 2-4 h | Son dos cosas distintas y el sistema no puede confundirlas: en un rechazo hay que emitir nota de crédito; en un error de red **el documento no existe** y hay que reintentar. Confundirlos genera un documento duplicado o una venta sin documento |
| Pruebas, incluido el candado del `0 = aceptado` | 3 h | Es un error que se puede reintroducir sin darse cuenta |

**La espera:** ninguna externa. Esta tarea se puede hacer completa con lo que hay.

---

## 1.5 · Primera emisión real, con ceremonia — **1 día**

**Y acá el plazo engaña en el otro sentido:** el trabajo técnico son **minutos**. El día es de
**procedimiento**, y conviene explicar por qué existe.

**Por qué no hay ensayo posible.** Bsale confirmó por escrito que su ambiente de prueba es *"un
entorno no electrónico"*: los documentos que se emiten ahí **no se timbran ni llegan al SII**. Es
decir: **la primera vez que veremos un documento realmente timbrado será en producción.** No es una
decisión nuestra ni un atajo — la recomendación de hacer la primera emisión en producción con monto
bajo, y anularla con nota de crédito si hace falta, **es de Bsale**, por escrito.

**De qué está hecho el día:**

1. Encender el interruptor de emisión (`DTE_EMISION_HABILITADA`, que **arranca apagado** y representa
   la autorización de Gerencia) y verificar que la credencial de producción está declarada como tal
   en el servidor de producción. El candado exige **las dos condiciones** a la vez: un token de
   producción en un computador de trabajo no puede emitir (`config/dte.php:44-51`).
2. Monto bajo, Contabilidad avisada, **nota de crédito preparada de antemano**.
3. Emitir. Verificar que el documento llegó al SII.
4. Anotar folio, monto y resultado en el informe (§13 de `FACTURACION-ELECTRONICA.md`).
5. Si sale mal: anular, con el cliente esperando. **Ese es el motivo de la ceremonia.**

**La espera, y es la única que importa:** **autorización escrita de Gerencia.** Este día **se
agenda, no se programa**. Es el punto de no retorno del proyecto: un folio real que queda en los
registros del SII y que solo se deshace con nota de crédito.

---

## 1.6 · Por qué "2 a 3 semanas de trabajo" pero "1 a 1,5 meses de calendario"

Suma del trabajo efectivo: **7 a 13 días** de una persona a tiempo parcial ≈ **2 a 3 semanas**.

La diferencia hasta 1-1,5 meses son **tres esperas que no consumen trabajo**:

| Espera | De quién depende | Bloquea |
|---|---|---|
| Credencial de prueba etiquetada | Interno / Bsale | Tareas 1.1 y 1.3 |
| Dos repreguntas a Bsale: cómo se asocia **la caja**, y si se puede consultar el **stock de folios** | Bsale | Cierra el diseño de 1.2 y el control de folios |
| Autorización escrita de Gerencia | Gerencia | Tarea 1.5, y solo esa |

**La frase para la reunión:** «Poner más horas acorta las tres semanas de trabajo. No acorta la
espera. Y la espera más larga es una firma.»

**Y después:** 1 a 2 meses de uso real antes de poder decir que está probado en la operación. Eso no
es desarrollo — es tiempo de calendario con documentos reales pasando por el sistema. No se puede
comprimir con capacidad.

---

# NIVEL 2 — Que TODA la facturación salga de DaliGo

## 2.1 · Punto de venta y caja (módulo M06) — **4 a 6 meses**

**El número que ordena todo:** el nivel 1 cubre las **reparaciones**, que en volumen son la minoría.
El grueso es venta de mostrador: **~120 documentos diarios** (72 en Mirador, 22 en Abate, 28 en
Coquimbo — suman 122, redondeado a 120 en la hoja de niveles). El nivel 1 automatiza el flujo de menor volumen, y eso está bien — es el de menor riesgo
—, pero no hay que confundirlo con "ya está la facturación".

**Por qué 4 a 6 meses y no 4 a 6 semanas.** Porque un punto de venta no es una pantalla de emitir con
más botones. Es, como mínimo:

| Pieza | Por qué no es opcional |
|---|---|
| Búsqueda de catálogo a velocidad de mostrador | La persona tiene un cliente adelante. Si buscar un repuesto toma 20 segundos, el sistema no se usa: se vuelve a Bsale |
| Boleta en menos de un minuto, sin datos de cliente | Ya está especificado como `P-M05-06` en `docs/RUTA-MAESTRA.md`. Es el caso más frecuente de los 122 diarios |
| Caja: apertura, cierre, arqueo y **diferencias** | Es lo que hoy hace Bsale y nadie nota, hasta que falta. Un descuadre de caja es un problema de plata, no de software |
| Medios de pago, incluida la máquina de tarjeta | Hoy están **atadas a Bsale** (ver 3.4). Es la dependencia más física de todas |
| Devoluciones y cambios en mostrador | Flujo distinto al de anular una factura de reparación |
| Turnos y responsables por sucursal | Quién abrió la caja, quién vendió, quién cerró |
| Funcionar con la conexión caída | **Esta es la que cuesta.** El mostrador no puede detenerse porque el servidor no responda. Hoy DaliGo está en hosting compartido, sin nadie vigilando si está arriba (§9bis de la carpeta de la reunión) |
| Impresora térmica en tres sucursales | Es hardware, y el hardware se prueba en el lugar |
| Capacitación de la gente que hoy ya sabe usar Bsale | El sistema nuevo compite con uno que funciona y que el equipo domina |

**Y la parte que no es de plazo:** el módulo M06 está marcado **[STANDBY] "no construir"** en
`docs/RUTA-MAESTRA.md:307`, con la razón textual de Luis: *"lo que funciona no se toca"*. O sea que
acá **el plazo no es el obstáculo: la decisión lo es.** Los 4 a 6 meses recién empiezan a contar el
día que esa decisión cambie.

**Acá el servidor SÍ empieza a hacer falta:** con 122 documentos diarios pasando por DaliGo, si el
sistema se cae **no se atrasa un informe — se detiene la venta**. En el nivel 1 una caída significa
que alguien factura a mano en Bsale, como siempre. En el nivel 2 significa que nadie cobra.

---

## 2.2 · Guías de despacho — **2 a 3 semanas, BLOQUEADO**

**Las 2 a 3 semanas son el trabajo nuestro**, y son creíbles porque el mecanismo de emisión ya estaría
hecho por el nivel 1: es un tipo de documento más, con sus campos propios y su pantalla.

**El bloqueo es externo y tiene fecha.** Desde el **1 de noviembre de 2026** el SII exige en las guías
datos de transporte que hoy **la integración de Bsale no tiene dónde registrar**: patente del vehículo
y del remolque, RUT del transportista, nombre y cédula del chofer, hora de salida, origen y destino
efectivo. Bsale respondió que va a cumplir *"dentro de los plazos establecidos por el SII"*, **sin
fecha**, y que los campos nuevos los publicará en su documentación para que cada uno los implemente
(informe §6).

**Qué significa para el plazo:**

- Las 2 a 3 semanas **empiezan a contar el día que Bsale publique los campos**, no antes.
- Construirlo ahora sería **construirlo dos veces**.
- Si Bsale publica en octubre, quedan semanas para implementar contra una fecha legal. Ese es el
  riesgo real, y no se controla con capacidad: se controla **insistiendo por la vía comercial** ahora.
- Hay trabajo adicional que **sí** se puede adelantar y no depende de Bsale: capturar chofer, patente
  y horarios en el flujo de despacho de DaliGo. Hoy no se piden. Y alguien en el patio va a tener que
  escribirlos en cada traslado — eso es cambio de hábito, no software.

---

## 2.3 · Boleta de mostrador — **incluida en el POS**

No tiene plazo propio porque no es una tarea separable: la boleta de mostrador **es** el flujo
principal del punto de venta. Estimarla aparte daría un número engañosamente chico — la pantalla de
emitir boleta sin el resto del POS (caja, medios de pago, contingencia) no sirve para atender a un
cliente.

---

# NIVEL 3 — Dejar de pagarle a Bsale

## 3.1 · Por qué el nivel 3 son 12 a 18 meses: la dependencia que no se ve en pantalla

**El dato más importante de todo este documento, y es verificable en un minuto:** en todo el código de
DaliGo hay **exactamente dos escrituras** hacia Bsale — `documents.json` (emitir) y `returns.json`
(anular). Todo lo demás es **lectura**: catálogo, precios, stock, clientes y documentos entran desde
Bsale y DaliGo los copia (`app/Services/Bsale/`, cinco sincronizaciones, ninguna escribe).

**Dicho en una frase:** DaliGo es un **espejo**. Los precios, el catálogo, el stock y los clientes se
editan en Bsale. **Si Bsale desapareciera mañana, DaliGo no sabría el precio de sus propios
productos.** Esa es la dependencia real, y no se resuelve con facturación.

*(Nota que refuerza el punto: la API de Bsale **no permite crear** listas de precios — solo editar
valores, porque *"las listas comparten el total de productos de Bsale"*, `docs/BSALE_API.md`. O sea
que la dirección del flujo no es una decisión nuestra que podamos invertir con una configuración.)*

## 3.2 · Que DaliGo sea DUEÑO de los datos — **2 a 3 meses**

**No es servidor: es desarrollo, migración y cambio de hábito.** Las tres partes:

1. **Migración.** Traer catálogo, precios, stock y clientes con su historia, y cuadrarlos. Un error de
   migración de precios se descubre cotizando mal.
2. **Pantallas que hoy no existen.** Hoy nadie edita un precio en DaliGo porque no hay dónde. Hay que
   construir el mantenedor de catálogo, precios, stock y clientes, con permisos y trazabilidad.
3. **Cambio de hábito, y es la parte lenta.** Hay que decidir **dónde se editan los precios** de ahora
   en adelante, y que toda la empresa lo haga ahí. Mientras existan dos lugares posibles, los datos
   se desincronizan.

**Dato reciente que muestra por qué esto es serio:** el 28-jul se descubrió que el sistema tomaba el
precio de *"la primera lista activa que encontrara"* entre las 5 activas — una reparación de Mirador
podía cotizarse con precios de Coquimbo, **sin que nada lo mostrara en pantalla**. Ya está corregido y
fijado en la lista GENERAL. Ese error **estaba afectando cotizaciones reales**, y era un problema de
*espejo*, no de facturación.

## 3.3 · Un emisor de documentos alternativo — **2 a 4 semanas**

**Por qué es tan corto, y esta es una buena noticia que conviene decir:** el sistema ya se diseñó para
esto. La emisión pasa por una interfaz (`EmisorDte`) y `EmisionDte` **no sabe con quién habla**
(`EmisionDte.php:11-14`). Cambiar de proveedor es escribir una segunda implementación, no rehacer el
módulo.

Las 2 a 4 semanas son: la implementación nueva, sus pruebas, y **la parte que sí es nueva** — pasar a
custodiar el certificado digital y los folios (CAF), que hoy administra Bsale y que a partir de ahí
son responsabilidad legal de la empresa. Ese último punto es el que hay que discutir, no el plazo.

## 3.4 · Medios de pago — **1 a 2 meses + trámite con el proveedor**

Las máquinas de tarjeta **hoy están atadas a Bsale**. El mes o dos es trabajo nuestro; **el trámite
con el proveedor tiene su propio calendario y no lo controlamos** — es el mismo tipo de espera que la
credencial del nivel 1, pero con un tercero más lento. No es servidor.

## 3.5 · Los reportes que hoy usa Gerencia — **1 a 2 meses**

No es "hacer gráficos". Es reproducir, con datos propios, informes que hoy la Gerencia ya lee y en los
que confía — y que tienen que **cuadrar** con lo que mostraba Bsale, o nadie los va a usar. El tablero
de DaliGo va en ~35 % (`RUTA-MAESTRA.md`) y la parte de reportes **depende de que exista el ciclo de
la factura**: sin documentos propios no hay nada que reportar. No es servidor.

## 3.6 · Servidor e infraestructura — **2 a 4 semanas**

**Es el único punto de toda la lista del nivel 3 que es el servidor.** Y conviene decirlo así, porque
es la parte **más barata y la menos difícil** de las siete.

Qué incluye — y ninguna de estas cosas es programación:

- Hosting **dedicado** en vez de compartido (hoy el consumo de un vecino nos afecta).
- Migrar la base de datos, que está en **MySQL 5.7, fuera de soporte**.
- **Monitoreo con aviso.** Hoy existe una dirección de chequeo y **no hay nada consultándola**: una
  caída de fin de semana se descubre el lunes.
- Respaldos automáticos **y probados**. Un respaldo que nunca se restauró no es un respaldo.
- Procesos permanentes en vez del cron cada 15 minutos del hosting compartido (ver 1.4).

## 3.7 · Alguien que responda cuando se cae — **no tiene plazo**

**Es una persona, no una máquina, y por eso no lleva número.** Bsale no se cae porque alguien paga por
que no se caiga; hoy ese costo está incluido en lo que la empresa ya paga. Reemplazarlo significa
asumirlo, incluido **el plan de contingencia del mostrador**: qué hace la persona que tiene un cliente
adelante y la pantalla no carga.

## 3.8 · Por qué 12 a 18 meses y no la suma de las partes

Las partes suman menos. Los 12 a 18 meses salen de tres cosas:

1. **El orden es obligatorio.** El nivel 3 va **después** del nivel 2, que va después del nivel 1. No
   se puede dejar Bsale antes de tener punto de venta propio, y no se puede tener punto de venta antes
   de tener los datos propios.
2. **Una sola persona a tiempo parcial no paraleliza.** Con dos personas a tiempo completo los plazos
   se acortan aproximadamente a la mitad. **La variable es la capacidad, no la voluntad.**
3. **No es un proyecto, es un programa.** Necesita una decisión sobre **capacidad dedicada** antes que
   una fecha. Pedir una fecha sin decidir la capacidad es pedir un número inventado.

**Lo que conviene decir sobre el ahorro:** es real —del orden de **$300.000 al mes**— pero **se cobra
al final, no al principio**: hay que terminar los tres niveles antes de poder dejar de pagar. Y el
servidor, que es lo que hay que decidir ahora, es la parte más barata y la menos difícil de esa lista.

---

## Anexo — Tabla de una página, para llevar a la reunión

| Tarea | Plazo | De qué está hecho el plazo | Trabajo o espera |
|---|---|---|---|
| Leer identificadores de Bsale | 1-2 días | Escribir el comando que no existe + elegir 4 tipos de documento ambiguos + mapear 3 sucursales **dos veces** (los ids difieren entre ambientes) + pruebas | Espera: **credencial** |
| Botón de emitir y flujo completo | 1 semana | Ruta (0,5 d) + formulario con 3 validaciones obligatorias (1 d) + conectar previsualización (0,5 d) + **4 caminos de error** (1 d) + permisos (0,5 d) + pruebas (1 d) + gate (0,5-1 d) | Trabajo. Riesgo: **la caja** |
| Verificar nota de crédito | 1-2 días | El código existe pero **nunca se ejerció**: hay que emitir, anular y comparar la respuesta real. Si el `type` está al revés, devuelve stock que nunca salió | Espera: **credencial** |
| Seguimiento del estado ante el SII | 2-3 días | Comando de reconsulta + encajarlo en el cron de 15 min del hosting compartido + aviso de rechazo + separar error de red de rechazo del SII | Trabajo |
| Primera emisión real | 1 día | El trabajo son minutos. El día es procedimiento: **no existe ensayo posible** (el ambiente de prueba de Bsale no es electrónico) | Espera: **firma de Gerencia** |
| **Nivel 1 completo** | **2-3 semanas de trabajo / 1-1,5 meses de calendario** | La diferencia es espera, no trabajo | — |
| Punto de venta y caja | 4-6 meses | 9 piezas, incluida "funcionar con la conexión caída". **~120 documentos diarios**. Hoy marcado *"no construir"* | Decisión, antes que plazo |
| Guías de despacho | 2-3 semanas | El trabajo es corto; **empieza a contar cuando Bsale publique los campos del 1-nov-2026** | Espera: **Bsale, sin fecha** |
| Datos propios (dejar de ser espejo) | 2-3 meses | En todo el código hay **2 escrituras** a Bsale y 5 sincronizaciones de lectura. Migración + pantallas nuevas + **cambio de hábito** | Trabajo + hábito |
| Emisor alternativo | 2-4 semanas | Corto **porque el sistema ya se diseñó para cambiar esa pieza**. Lo nuevo es custodiar el certificado y los folios | Trabajo |
| Medios de pago | 1-2 meses + trámite | Las máquinas de tarjeta están atadas a Bsale | Espera: **proveedor** |
| Reportes de Gerencia | 1-2 meses | Tienen que **cuadrar** con los de Bsale o nadie los usa. Dependen de que exista el ciclo de la factura | Trabajo |
| **Servidor e infraestructura** | **2-4 semanas** | Hosting dedicado + migrar MySQL 5.7 + monitoreo + respaldos probados. **El único punto de la lista que es el servidor, y el más barato** | Trabajo |
| Alguien que responda | — | Es una persona | Decisión de la empresa |
