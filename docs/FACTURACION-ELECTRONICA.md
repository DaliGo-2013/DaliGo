# Facturación electrónica desde DaliGo — informe para Gerencia y Contabilidad

**Fecha:** 28 de julio de 2026
**Preparado por:** Marcos Uribe (desarrollo DaliGo)
**Para:** Gerencia y Contabilidad de Sociedad Importadora y Exportadora Dali Ltda.
**Estado:** propuesta pendiente de aprobación. **Nada de esto está operando.**

---

## 1. Resumen en una página

### Qué se propone

Que **DaliGo emita los documentos tributarios pasándolos a Bsale automáticamente**, en lugar de que
una persona vuelva a tipear en Bsale una venta que ya está registrada en DaliGo.

### Qué NO se propone (esto es lo importante)

> **No se propone construir un sistema de facturación propio, ni el timbre electrónico, ni conversar
> directamente con el SII.**
>
> **Bsale sigue siendo el emisor:** conserva la certificación ante el SII, sigue timbrando los
> documentos, sigue enviándolos al SII y sigue custodiando los folios y el certificado digital.

Es automatizar el traspaso de datos hacia el sistema tributario que la empresa **ya paga**, no asumir
la responsabilidad tributaria.

### Estado actual del desarrollo

| Construido hasta hoy | Riesgo tributario |
|---|---|
| Una tabla vacía en la base de datos | Ninguno |
| Código interno de preparación y de traducción a Bsale (sin uso todavía) | Ninguno |
| **Documentos emitidos: 0** | — |
| **Contacto con el SII: ninguno** | — |

> El sistema **todavía no puede** emitir aunque alguien lo intente: los identificadores que Bsale usa
> para saber a qué sucursal y a qué medio de pago corresponde cada documento están **deliberadamente
> vacíos**, y sin ellos el código se detiene con un aviso en lugar de adivinar. Llenarlos es un paso
> aparte y explícito (el B6 del plan técnico).

Si Gerencia decide no avanzar, se elimina y no queda ningún efecto.

### Lo que necesitamos de ustedes

1. **Contabilidad:** ✅ **respondido el 28-jul-2026** — las 8 reglas contables están definidas (§7).
   Quedan **dos datos menores** por confirmar: la lista de precios de servicio técnico y el RUT del
   emisor.
2. **Gerencia:** aprobar (o no) el plan de la §8, y en particular autorizar por escrito **el único
   paso irreversible**, que está marcado en rojo.

### ⚠️ Y un hallazgo que requiere atención independiente de este proyecto

La investigación destapó que **el 1 de noviembre de 2026** el SII exigirá datos nuevos en las guías de
despacho, y **hoy Bsale no tiene dónde registrarlos**. Esto afecta a Dali **aunque este proyecto no se
haga**. Ver §6.

---

## 2. Glosario mínimo

| Término | Qué significa |
|---|---|
| **DTE** | Documento Tributario Electrónico: la factura, boleta, guía de despacho o nota de crédito en versión electrónica |
| **El documento legal es el XML** | Lo que tiene valor tributario es un archivo de datos que viaja al SII. El PDF que recibe el cliente es solo la "representación impresa" |
| **Timbre electrónico (TED)** | El sello de autenticidad de cada documento. Es el cuadrado de rayas que aparece impreso en la factura |
| **CAF / folios** | Archivo que el SII entrega con un rango de números de documento **y la llave secreta que timbra**. Vence a los 6 meses |
| **Certificado digital** | Firma electrónica, emitida a nombre de **una persona** (no de la empresa) |
| **Emisor autorizado** | El contribuyente (el RUT) que el SII habilitó para emitir DTE. **Dali ya lo es** |

---

## 3. Situación regulatoria de Dali

### ✅ La empresa ya es emisor electrónico autorizado

Dali ya emite documentos electrónicos a través de Bsale, por lo tanto **el trámite grande ante el SII
ya está hecho**. Dos datos que lo confirman:

- El SII **autoriza al RUT del contribuyente, no al software**. Textual del SII: *"el SII no certifica
  el software de estas empresas; solo publica esta nómina como mera información"*.
- **Bsale lo dice en su propio sitio comercial**, al vender el servicio de enrolamiento: *"el número de
  resolución obtenido se puede utilizar con cualquier software de terceros también. **Es una
  certificación para el RUT**"*.

**Consecuencia práctica:** cambiar la forma en que se emite (o incluso cambiar de proveedor a futuro)
**no requiere volver a certificarse ante el SII**.

### Lo que sí sigue siendo obligación permanente de la empresa

Independiente de qué software se use:

- Mantener el **certificado digital vigente** (lo renueva su titular cada 1 a 3 años).
- Gestionar los **folios (CAF)** por tipo de documento — y ojo: **vencen a los 6 meses** desde su
  autorización (Res. Ex. SII N°58/2017). Los folios no usados de un CAF vencido **deben anularse**, y
  el SII rechaza los documentos que los usen.
- **Conservar los documentos por 6 años** (Res. Ex. SII N°45/2003).
- **La responsabilidad tributaria es siempre del contribuyente**, nunca del proveedor de software.
  Ningún proveedor la asume: lo que se delega es el trabajo técnico, no la responsabilidad.

---

## 4. Por qué NO se recomienda construir el timbre electrónico propio

Se evaluó a fondo y se descartó. Los números:

| Camino | Esfuerzo | Riesgo |
|---|---|---|
| **Timbre propio** (DaliGo firma y timbra) | **45 a 70 días-persona ≈ 3 a 5 meses** | Alto |
| **Vía Bsale o un proveedor** | **5 a 12 días-persona** | Bajo |

Razones concretas del descarte:

1. **No entrega ninguna ventaja.** Al terminar, la empresa quedaría igual que hoy: emitiendo
   documentos válidos. No hay nada que hoy no se pueda hacer.
2. **Obliga a custodiar llaves criptográficas.** El archivo de folios (CAF) contiene la llave que
   timbra: quien la tenga puede emitir documentos válidos con el RUT de Dali. Nuestro servidor es un
   hosting compartido y el repositorio de código es público — no es el lugar para eso.
3. **Mantención normativa permanente.** En los últimos 12 meses el SII cambió las reglas **cuatro
   veces** (ver §5). Cada cambio habría que implementarlo y probarlo contra plazos legales.
4. **En PHP no hay herramientas viables.** La única biblioteca madura del país tiene una licencia
   (AGPL, sin alternativa comercial) que **obligaría a publicar el código de DaliGo**, y además no
   tiene ninguna versión estable compatible con la versión de PHP de nuestro servidor.

**La opción elegida es la conservadora.** La opción arriesgada habría sido construir el timbre.

---

## 5. Normativa vigente relevante (verificada en fuentes oficiales del SII)

| Norma | Qué establece | Vigencia |
|---|---|---|
| **Res. Ex. N°154/2025**, prorrogada por **Res. Ex. N°52/2026** | Nuevo formato de documentos (Anexo Técnico v2.5) y **datos obligatorios de transporte en guías de despacho** | **1 de noviembre de 2026** |
| **Res. Ex. N°36/2024** | La descripción del producto debe ir **sin abreviaturas ni códigos internos** | Vigente desde julio 2024 |
| **Res. Ex. N°207/2025** | **Elimina** la obligación de imprimir el timbre en las boletas (sigue obligatorio en el archivo XML) | 1 de enero de 2026 |
| **Res. Ex. N°53/2025** (deja sin efecto la N°12/2025) | La boleta se puede entregar **impresa o virtual** (correo, QR, foto) | Vigente |
| **Res. Ex. N°58/2017** | Los folios (CAF) **vencen a los 6 meses** | Vigente |

### Dos advertencias sobre la documentación del propio SII

Esto es relevante para Contabilidad, porque **el SII tiene documentos oficiales que se contradicen**:

1. **El "Reporte de Consumo de Folios" (RCOF / Resumen de Ventas Diarias) está DEROGADO** desde el
   1 de agosto de 2022 (Res. Ex. N°53/2022, textual: *"eliminándose de esta forma la obligación de
   generar y enviar este archivo al SII para todos los contribuyentes emisores de boletas
   electrónicas"*).
   **Pero** el PDF oficial del formato de boletas (versión 4.2, de **septiembre de 2025**) y una
   pregunta frecuente del SII actualizada en 2017 **siguen exigiéndolo**.

2. **Los libros de compra y venta fueron reemplazados** por el Registro de Compras y Ventas (RCV) en
   agosto de 2017 (Res. Ex. N°61/2017); el SII lo construye solo con los documentos que recibe.

**Regla que adoptamos:** manda la **Resolución**, no los PDF de formato ni las preguntas frecuentes.
Se sugiere que Contabilidad valide estos dos puntos.

---

## 6. ⚠️ Hallazgo urgente: plazo del 1 de noviembre de 2026

**Este punto es independiente de este proyecto y afecta a la empresa igual.**

A contar del **1 de noviembre de 2026**, las **guías de despacho** que amparan el traslado de
mercadería deberán incluir obligatoriamente:

- Patente del vehículo **y patente del carro o remolque**
- **RUT del transportista**
- **Nombre y cédula del chofer**
- **Fecha y hora de salida**, y fecha de llegada
- Dirección y comuna de origen **y de destino efectivo**

Además: **una guía por traslado y por vehículo** (no se puede reutilizar la misma para varios
traslados o días).

### El problema

Se revisó la documentación pública de Bsale y **su interfaz de integración no tiene hoy ningún campo
donde registrar esos datos**. Tampoco se encontró ninguna comunicación de Bsale (blog, novedades,
centro de ayuda) sobre este cambio normativo.

Como referencia: cuando en 2022 el SII cambió un anexo técnico, **Bsale sí lo anunció públicamente**.
El silencio actual, a tres meses del plazo, es llamativo.

### Respuesta de Bsale (28 de julio de 2026) — sin fecha

Se consultó formalmente. Bsale respondió, textualmente, que *"realizará las adecuaciones necesarias para
cumplir con la normativa vigente dentro de los plazos establecidos por el SII"* y que si el nuevo anexo
técnico requiere campos nuevos en la API, *"dichos cambios serán informados oportunamente a través de
nuestra documentación oficial para que puedan ser implementados por quienes integran sus sistemas"*.

**Qué significa, sin adornos:**

| Lo que preguntamos | Lo que respondieron |
|---|---|
| ¿Qué campos de transporte incorporará la integración? | No lo dicen |
| ¿En qué fecha? | No lo dicen |
| ¿Cómo nos vamos a enterar? | Publicándolo en su documentación. **Hay que ir a mirarla** |

No es una negativa: es un compromiso general de cumplir, sin fecha. Y la última frase traslada trabajo
a Dali: cuando Bsale publique los campos nuevos, **alguien tiene que implementarlos de este lado**, con
el plazo del 1 de noviembre ya encima.

**Consecuencias prácticas:**

1. **La guía de despacho de garantía (§7.4) no se construye todavía.** Sería construirla dos veces.
2. **Hay que vigilar la documentación de Bsale**, no esperar un aviso. Si publican en octubre, quedan
   semanas para implementar.
3. **Conviene que Gerencia insista por la vía comercial** pidiendo una fecha. Una consulta técnica ya se
   hizo y dio esto; el ejecutivo comercial es otro canal.

*(Nota: la factura y la guía impresas de Dali ya traen los campos "Nombre del conductor / RUT / Patente
del vehículo" en blanco — es exactamente esa información la que pasa a ser obligatoria.)*

---

## 7. Reglas contables — preguntas y respuestas

> **✅ RESPONDIDAS por Contabilidad el 28 de julio de 2026.** Estas respuestas son las que definen cómo
> se programa el sistema. Quedan registradas aquí para que, si más adelante algo se comporta distinto a
> lo esperado, se pueda revisar contra lo acordado.

### ✅ 1. Precios y redondeo (la más importante)

DaliGo guarda los precios **con IVA incluido**, pero el documento tributario exige el precio **neto**
por línea con el IVA desglosado aparte. Al convertir aparecen diferencias de $1 por redondeo.

**Pregunta:** ¿qué cifra debe cuadrar exacto, el TOTAL que paga el cliente o el NETO de cada línea?

> **Respuesta:** **el total que paga el cliente.**

**Qué implica en el sistema:** nada que rehacer — esa ya es la convención del código actual (el total
es el dato guardado; el neto se calcula como `total ÷ 1,19` y el IVA es la diferencia, de modo que
neto + IVA = total **exacto**, siempre). Lo único que se agrega es que, al partir la reparación en
varias líneas, **el ajuste de redondeo se carga a la línea de mano de obra**, para que la suma de las
líneas siga dando el total que el cliente pagó.

### ✅ 2. Qué documento se emite en cada caso

> **Respuesta:** **boleta o factura lo decide el cliente.** No hay ventas exentas de IVA en servicio
> técnico. La única excepción en la empresa es la **venta de artículos del activo fijo con más de 36
> meses** (que no es servicio técnico).

**Qué implica en el sistema:**
- Al emitir, la persona que atiende **elige** boleta o factura; el sistema no lo decide solo.
- Si elige **factura**, el sistema exige RUT, giro y dirección del cliente (el documento no es válido
  sin ellos). Si elige **boleta**, puede emitirse sin RUT del cliente.
- Todo va **afecto a IVA**. La factura exenta queda prevista en el código pero **fuera de alcance** de
  este proyecto (correspondería a la venta de activo fijo, que es otro flujo y otra área).

### ✅ 3. Cómo se desglosa una reparación

> **Respuesta:** **una línea por repuesto + una línea de mano de obra.** Los repuestos se facturan
> **con su código del catálogo**. La mano de obra **tiene un SKU**.

**Qué implica en el sistema:** es lo que la orden de servicio ya guarda (repuestos enlazados al
catálogo + un monto de mano de obra), así que el desglose sale directo, sin pedirle nada extra al
técnico. Por la **Res. 36/2024**, el código va en el campo de código y la **descripción visible** debe
ser el nombre real del repuesto, sin abreviaturas.

**✅ Los dos códigos ya están identificados** (verificado el 28-jul-2026):

| Línea del documento | Código | Cómo aparece |
|---|---|---|
| **Repuestos** | SKU del catálogo, serie `1070xxx` (categoría *AGUA REPUESTOS*) | Nombre real del repuesto: "ADAPTADOR LLAVES VENTILADOR", "BASE TB-36", "BOMBA DE ALTA PRESION 1T"… |
| **Mano de obra** | SKU **`9771001`** — *HORA SERVICIO TECNICO* (categoría *SERVICIOS*) | "Hora servicio técnico" |

El SKU de mano de obra **ya está en uso** dentro de DaliGo: es el que alimenta el valor hora al cotizar
una reparación (`config/servicio_tecnico.php`). O sea, el desglose que pide Contabilidad se puede armar
con lo que el sistema ya tiene, sin pedirle nada nuevo al técnico.

### ✅ 4. Equipos en garantía

**Pregunta:** una reparación en garantía no se cobra. ¿Se emite algún documento igual (por ejemplo una
guía de despacho al devolver el equipo) o no se emite nada?

> **Respuesta:** **hoy no se emite nada; en DaliGo deberá emitirse.**

**Qué implica en el sistema:** es un **alcance nuevo**, no previsto en el plan original: la devolución
de un equipo reparado en garantía debe ir amparada por una **guía de despacho por traslado que no
constituye venta** (sin cobro). Además de automatizar, esto **cierra un vacío que existe hoy**: el
traslado de mercadería debe ir documentado aunque no haya venta.

> 🔴 **Ojo, esta respuesta se cruza con la §6.** La guía de despacho es **justamente** el documento al
> que el SII le exige datos nuevos de transporte desde el **1 de noviembre de 2026**, y hoy la
> integración de Bsale **no tiene dónde registrarlos**. Es decir: este punto no se puede construir
> completo hasta que Bsale responda. **Se propone dejar la guía de garantía para después de esa
> respuesta**, y avanzar primero con boleta y factura, que no tienen ese problema.

### ✅ 5. Qué razón social factura

> **Respuesta:** **Importadora y Exportadora Dali, solo esa.**

**Qué implica en el sistema:** simplifica bastante — **un solo emisor**, una sola credencial, una sola
serie de folios. **Plásticos Dali queda fuera de alcance.** El RUT que se usará es **76.301.506-8**
(tomado del catastro de empresas de la cuenta Bsale registrado en `docs/BSALE_API.md`); **se pide a
Contabilidad confirmarlo de una vez**, porque va impreso en cada documento.

*(Consecuencia sobre el plan: la §8 proponía hacer la primera emisión real "en el RUT menos crítico".
Ya no existe un segundo RUT donde probar → esa condición se reemplaza por monto bajo + nota de crédito
preparada de antemano.)*

### ✅ 6. Sucursal y lista de precios

> **Respuesta:** se emite **desde donde se repara** (no desde donde se recibió el equipo).

**Qué implica en el sistema:** el documento se emite con la sucursal donde se hizo la reparación —
típicamente **Mirador**, aunque el equipo haya entrado por Coquimbo o Abate Molina. Esto importa porque
en Bsale los folios y la caja pueden estar organizados **por sucursal** (ver pregunta abierta 7).

**Sobre la lista de precios** (quedó sin responder por Contabilidad): **para la etapa de pruebas se usan
los precios ya espejados desde Bsale**, aunque no estén al día — sirven para probar el mecanismo, y se
actualizan solos cuando corre la sincronización. Decisión operativa del 28-jul-2026.

Dos precisiones que conviene dejar por escrito:

1. **El precio queda congelado en el documento.** Al emitir, el precio se copia al documento tributario;
   si después el precio de lista cambia, **los documentos ya emitidos no cambian** (como debe ser).
2. **La lista quedó fija en GENERAL** ✅ *(hecho el 28-jul-2026)*. La empresa tiene **5 listas activas
   con precios** (COQUIMBO-1, CSTS, EXTERIOR 1, EXTERIOR 2, GENERAL) y el sistema tomaba *"la primera
   activa que encontrara"* — o sea una cualquiera, sin que nada lo delatara en pantalla. El dueño eligió
   **GENERAL** porque el origen de las otras no está claro. ⚠️ Esto **ya afectaba las cotizaciones de
   hoy**, aun sin facturación electrónica: era un error de precios, no un pendiente del proyecto.
   Si un repuesto no tiene precio en GENERAL, el sistema ahora **no muestra precio** (se escribe a mano)
   en vez de sacarlo de una lista de origen incierto.

### ✅ 7. Forma de pago

> **Respuesta:** **se registra el pago en el momento.**

**Qué implica en el sistema:** el documento se emite junto con su pago, así que al emitir hay que
indicar **el medio de pago** (efectivo, transferencia, tarjeta). Consecuencia práctica: **la caja del
día en Bsale tiene que estar abierta**; si está cerrada, Bsale rechaza la emisión. El sistema mostrará
un aviso claro ("la caja del día está cerrada en Bsale") en lugar de un error técnico.

### ✅ 8. Anulaciones

**Pregunta:** un documento mal emitido se corrige con **nota de crédito** (los documentos electrónicos
no se borran). ¿Quién debe estar autorizado a emitirla?

> **Respuesta:** el gerente **Luis Lazcano**, el jefe de ventas **Héctor Martínez**, y los jefes de
> sucursal **Luis Figueroa** (Coquimbo) y **Gonzalo Martínez** (Abate Molina).

**Qué implica en el sistema:** ✅ **hecho el 28-jul-2026.** Se creó el permiso *«Emitir notas de crédito
(anular un documento)»* y el rol **Jefe de sucursal**, que no existía en DaliGo. Lo tienen el gerente, el
jefe de ventas y los jefes de sucursal; **nadie más puede anular**, ni siquiera quien emitió el
documento. Contabilidad **no pidió** su visto bueno previo, así que esas cuatro personas pueden emitir la
nota de crédito directamente, y queda registrado quién la emitió y por qué.

Falta lo administrativo, que es de Gerencia: **asignarle el rol a Luis Figueroa y Gonzalo Martínez** en
Administración → Usuarios.

---

## 8. Plan por fases, con el punto de no retorno marcado

| Fase | Qué pasa | Reversible |
|---|---|---|
| **1. Preparación interna** ✅ *(hecho)* | Tabla y código base. Nada emite. | ✅ Total |
| **2. Traducción a Bsale** ✅ *(hecho 28-jul-2026)* | El código que arma el documento en el formato de Bsale, con las 8 reglas de Contabilidad ya aplicadas. **Sigue sin emitir nada:** los identificadores de Bsale están vacíos a propósito y sin ellos el sistema se niega a emitir. | ✅ Total |
| **3. Pruebas en ambiente de prueba de Bsale** | Se emiten documentos de prueba. **No son electrónicos: no se timbran ni llegan al SII.** | ✅ Total |
| 🔴 **4. PRIMERA EMISIÓN REAL** | **PUNTO DE NO RETORNO.** Se crea un documento tributario verdadero, con folio real, que queda en los registros del SII. Solo se deshace con nota de crédito. | ❌ **No** |
| **5. Uso normal** | DaliGo emite en la operación diaria. | Parcial |
| **6. (Futuro, opcional)** | Evaluar reemplazar Bsale por un proveedor de solo-facturación. | — |

### 🔴 Condiciones propuestas para la Fase 4

Se propone que la primera emisión real **no ocurra** hasta que:

1. ✅ Contabilidad haya respondido las reglas contables. **Cumplido el 28-jul-2026** (§7).
2. Gerencia lo autorice **por escrito**.
3. Se haga con: **monto bajo**, con **Contabilidad enterada** y con la **nota de crédito preparada** de
   antemano. *(La versión anterior de esta condición decía "en el RUT menos crítico"; ya no aplica:
   Contabilidad definió que se factura solo por Importadora y Exportadora Dali, así que no hay una
   razón social secundaria donde ensayar.)*
4. Quede registrada en este documento (fecha, folio, resultado).

**Nota:** el ambiente de prueba de Bsale **no emite documentos electrónicos** (su documentación dice
textualmente que tiene todas las características *"(no electrónicas)"*). Es decir: **la primera vez
que veremos un documento realmente timbrado será en producción.** Por eso la Fase 4 merece ceremonia.

### ✅ Bsale confirmó esto por escrito, y recomienda exactamente este plan

En su respuesta del **28 de julio de 2026**, Bsale confirmó las dos cosas y además **recomendó el mismo
procedimiento** que se propone acá:

> *"El ambiente de pruebas de la API corresponde a un entorno no electrónico, por lo que los documentos
> emitidos en dicho ambiente no son timbrados ni enviados al SII."*
>
> *"Al no existir un ambiente de certificación con envío al SII, la recomendación es realizar la primera
> emisión directamente en producción utilizando un documento de bajo monto. En caso de ser necesario,
> posteriormente puede anularse mediante la correspondiente Nota de Crédito."*

Es decir: **monto bajo + nota de crédito lista** no es una cautela nuestra, es el procedimiento que
recomienda el proveedor que hoy emite los documentos de la empresa. Eso no elimina el riesgo de la Fase
4, pero sí significa que no estamos improvisando un atajo.

---

## 9. Riesgos y cómo se controlan

| Riesgo | Control |
|---|---|
| **Emitir un documento por error o dos veces** | Cada documento lleva una clave única derivada de su origen; la base de datos **impide** físicamente crear dos para el mismo origen. Un doble clic no puede duplicar. |
| **Emitir contra producción creyendo que es prueba.** En Bsale el ambiente **lo define solo la credencial** (las direcciones son idénticas) | ✅ **Resuelto (28-jul-2026).** Se exigen **dos** condiciones para emitir: un interruptor que arranca apagado (representa la autorización de Gerencia) **y** que una credencial declarada de producción esté corriendo en el servidor de producción. Un token de producción en un computador de trabajo **no puede emitir**. *(Corrige la versión anterior de esta fila, que decía "el sistema no arranca": bloquear el arranque impediría **leer** los datos de Bsale, que es la etapa de preparación previa a la primera emisión. Leer nunca está bloqueado; crear documentos sí.)* |
| **Quedarse sin folios** o usar folios vencidos (el SII rechaza) | ⚠️ **Control DEGRADADO.** Se había previsto un aviso automático por cantidad restante y vencimiento. Bsale respondió (28-jul) que **no existe método para reservar un folio ni para saber cuál será el siguiente**, así que ese aviso puede no ser construible. Queda una repregunta pendiente: consultar el *stock restante* es distinto de saber *el próximo folio*. Si no hay forma, el control pasa a ser **manual** (revisar folios en Bsale). |
| **Documento rechazado por el SII** | Queda registrado y visible con su motivo; se corrige con nota de crédito. |
| **Perder el cierre de caja de Bsale** | ⚠️ **Sin resolver.** Bsale respondió que para que el documento cuadre en el cierre hay que asociar *"la sucursal, la caja y la forma de pago"*. Sucursal y forma de pago sí se pueden asociar (ya está hecho), pero **su propia documentación de la API no expone ningún campo de caja** — solo sucursal. Es una contradicción entre su respuesta y su documentación, y hay que aclararla **antes** de la primera emisión: si el documento cae en una caja equivocada, el descuadre lo descubre alguien al cierre del día. |
| **Depender de Bsale a futuro** | El sistema se diseñó para que cambiar de emisor sea reemplazar una pieza, sin rehacer el módulo. |
| **Cambio normativo del 1-nov-2026** | Respondido sin fecha (ver §6). **Riesgo abierto:** hay que vigilar su documentación e insistir por la vía comercial. |

---

## 10. Costos (solo si a futuro se decide dejar Bsale)

Hoy el costo adicional es **cero**: la conexión con Bsale está incluida en el plan que la empresa ya
paga (los planes incluyen 1.000 o 2.000 documentos mensuales según el plan; los adicionales se cobran
en paquetes).

Si en el futuro se quisiera reemplazar Bsale por un servicio de solo-facturación, los valores públicos
a julio de 2026 eran:

| Proveedor | Costo | Observación |
|---|---|---|
| OpenFactura | $360.000/año + IVA | Emisión ilimitada. Se cobra **por razón social** (×2 en nuestro caso) |
| LioREN | ~0,4 UF/mes | El más económico |
| SimpleFactura | $30.000/mes + IVA | Hasta 2.000 documentos |

*(Valores de referencia, no cotizaciones. Cambian.)*

---

## 11. Preguntas abiertas

| # | Pregunta | A quién | Estado |
|---|---|---|---|
| 1 | ¿Qué campos de transporte tendrá la integración y cuándo? (plazo 1-nov-2026) | Bsale | 🔴 **Respondida SIN FECHA** (§6). Riesgo abierto: vigilar su documentación + insistir por la vía comercial |
| 2 | ¿A qué caja se atribuyen los documentos emitidos por integración? | Bsale | 🔴 **Respuesta contradictoria:** dicen que hay que asociar la caja, pero su API no tiene ese campo. **Repreguntar** |
| 3 | ¿Los documentos por integración consumen el mismo cupo del plan? | Bsale → **ejecutivo comercial** | Derivada al área comercial: depende del plan de cada razón social |
| 4 | ¿Se puede emitir un documento de prueba que sí llegue al SII? | Bsale | ✅ **NO.** Confirmado por escrito: el ambiente de pruebas es no electrónico |
| 4b | ¿Existe forma de consultar el **stock restante** de folios y el vencimiento del CAF (aunque no se pueda reservar)? | Bsale | ⬜ **Repreguntar** (afecta el control de la §9) |
| 5 | Reglas contables 1 a 8 de la §7 | Contabilidad | ✅ **Respondidas 28-jul-2026** |
| 6 | Validar que el RCOF está derogado y que el RCV reemplaza los libros | Contabilidad | ⬜ Pendiente |
| 7 | Configuración de cierre de caja en Bsale: ¿"por emisor" o "por sucursal"? | Interno | ⬜ Pendiente |
| 8 | ¿Cuál es el SKU de mano de obra? | Contabilidad / Ventas | ✅ **`9771001` — HORA SERVICIO TECNICO** (ya en uso en DaliGo) |
| 9 | ¿Qué lista de precios se usa al facturar servicio técnico? | Contabilidad / Ventas | 🟡 **Para pruebas: el espejo de Bsale (GENERAL).** La definitiva se confirma antes de la Fase 4 |
| 10 | Confirmar el RUT del emisor: **76.301.506-8** (Importadora y Exportadora Dali) | Contabilidad | ⬜ Pendiente |

---

## 12. Fuentes

Todo lo afirmado se verificó en fuentes primarias.

**SII — normativa**
- [Res. Ex. N°154/2025 — exigencias en facturas y guías de despacho](https://www.sii.cl/normativa_legislacion/resoluciones/2025/reso154.pdf)
- [Res. Ex. N°52/2026 — prórroga al 1-nov-2026](https://www.sii.cl/normativa_legislacion/resoluciones/2026/reso52.pdf)
- [Res. Ex. N°207/2025 — elimina impresión del timbre en boletas](https://www.sii.cl/normativa_legislacion/resoluciones/2025/reso207.pdf)
- [Res. Ex. N°53/2025 — entrega impresa o virtual de la boleta](https://www.sii.cl/normativa_legislacion/resoluciones/2025/reso53.pdf)
- [Res. Ex. N°53/2022 — **elimina** el Resumen de Ventas Diarias (ex RCOF)](https://www.sii.cl/normativa_legislacion/resoluciones/2022/reso53.pdf)
- [Res. Ex. N°61/2017 — crea el Registro de Compras y Ventas](https://www.sii.cl/documentos/resoluciones/2017/reso61.pdf)
- [Res. Ex. N°58/2017 — vigencia de 6 meses de los folios (CAF)](https://www.sii.cl/normativa_legislacion/resoluciones/2017/reso58.pdf)
- [Res. Ex. N°74/2020 — procedimiento de boleta electrónica](https://www.sii.cl/normativa_legislacion/resoluciones/2020/reso74.pdf)

**SII — técnico**
- [Formato de Documentos Tributarios Electrónicos v2.5 (feb-2026)](https://www.sii.cl/factura_electronica/factura_mercado/formato_dte_202602.pdf)
- [Instructivo técnico de factura electrónica (timbre y firma)](https://www.sii.cl/factura_electronica/factura_mercado/instructivo_emision.pdf)
- [Proceso de certificación (solo aplica a software propio)](https://www.sii.cl/factura_electronica/factura_mercado/proceso_certificacion.htm)
- [FAQ: cambiar de software no exige recertificarse](https://www.sii.cl/preguntas_frecuentes/factura_electronica/001_003_6485.htm)
- [Registro de Compras y Ventas](https://www.sii.cl/destacados/f29/registrocompraventas.htm)

**Bsale**
- [Documentación de integración](https://docs.bsale.dev/documentos/) · [Folios](https://docs.bsale.dev/tipos-de-documentos/) · [Notas de crédito](https://docs.bsale.dev/devoluciones/) · [Guías de despacho](https://docs.bsale.dev/documentos/despachos/)
- [Enrolamiento: "es una certificación para el RUT"](https://www.bsale.cl/product/enrolamiento-factura-electronica)
- [Planes y cupos de documentos](https://www.bsale.cl/sheet/precios)

---

## 13. Decisiones y aprobaciones

*(Completar a medida que se resuelvan.)*

| Fecha | Decisión | Quién |
|---|---|---|
| | Aprobar / rechazar el plan de la §8 | Gerencia |
| **28-jul-2026** | ✅ Reglas contables de la §7 definidas | Contabilidad |
| | Emitir guía de despacho al devolver equipos en garantía *(alcance nuevo, §7.4)* | Gerencia |
| | **Autorización de la Fase 4 (primera emisión real)** | Gerencia |
| | Registro de la primera emisión (folio, monto, resultado) | Desarrollo |

---

## 14. Tareas internas pendientes

- [ ] Corregir el criterio de aceptación de M05 en `RUTA-MAESTRA.md`: dice *"una cotización creada en
      staging termina como DTE real en el sandbox de Bsale"*, y eso es **inalcanzable** porque el
      ambiente de prueba de Bsale no emite documentos electrónicos.
- [ ] Cerrar la decisión **D-005** con las respuestas de Bsale.
- [ ] **Repreguntar a Bsale dos cosas concretas** (ver §11): cómo se asocia la **caja** si la API no
      tiene ese campo, y si existe forma de consultar el **stock restante** de folios / vencimiento del
      CAF. Las dos afectan controles de la §9.
- [ ] **Vigilar la documentación de Bsale** por los campos de transporte del 1-nov-2026: no van a avisar,
      lo publican y hay que ir a mirar.
- [ ] Revisar si el flujo de guías de despacho de DaliGo necesita capturar chofer, patente y horarios
      antes del 1-nov-2026. **Subió de prioridad:** la respuesta 4 de la §7 convierte la guía de
      despacho en alcance obligatorio (devolución de equipos en garantía), y es justamente el documento
      afectado por el plazo.
- [ ] Crear el rol **`jefe_sucursal`** y el permiso de emisión de notas de crédito (respuesta 8 de la §7).
- [x] ✅ **Lista de precios fija por configuración** (`daligo.lista_precios_ventas` = `GENERAL`) y una
      sola fuente (`Producto::precioVentaConIva()`) en lugar de la misma consulta copiada en 4 lugares.
      Commit `1fbf554`, 8 candados en `PrecioListaOficialTest`, suite 1033 verde.
- [ ] **Verificar que el SKU `9771001` tenga precio en GENERAL** (Comercial → Precios → GENERAL). Si no
      lo tiene, el valor hora queda vacío y la mano de obra vuelve a ser manual.
