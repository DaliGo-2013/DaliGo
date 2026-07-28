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
| Código interno de preparación (sin uso todavía) | Ninguno |
| **Documentos emitidos: 0** | — |
| **Contacto con el SII: ninguno** | — |

Si Gerencia decide no avanzar, se elimina y no queda ningún efecto.

### Lo que necesitamos de ustedes

1. **Contabilidad:** responder las 8 preguntas de la §7. Tres son imprescindibles.
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

### Acción tomada

Ya se envió una consulta formal a Bsale preguntando **qué campos incorporará y en qué fecha**.
**Esta es la respuesta más importante que estamos esperando**, y conviene que Gerencia haga seguimiento
por la vía comercial si no llega pronto.

*(Nota: la factura y la guía impresas de Dali ya traen los campos "Nombre del conductor / RUT / Patente
del vehículo" en blanco — es exactamente esa información la que pasa a ser obligatoria.)*

---

## 7. Preguntas para Contabilidad

Estas respuestas definen cómo se programa el sistema. **Las tres primeras son imprescindibles**; sin
ellas cualquier cosa que se construya habría que rehacerla.

### 🔴 1. Precios y redondeo (la más importante)

DaliGo guarda los precios **con IVA incluido**, pero el documento tributario exige el precio **neto**
por línea con el IVA desglosado aparte. Al convertir aparecen diferencias de $1 por redondeo.

**¿Qué cifra debe cuadrar exacto: el TOTAL que paga el cliente, o el NETO de cada línea?**

### 🔴 2. Qué documento se emite en cada caso

- Reparación a una empresa con RUT → ¿factura?
- Reparación a una persona natural → ¿boleta?
- ¿Lo decide el sistema automáticamente según el cliente, o la persona que atiende?
- ¿Existen ventas exentas de IVA en servicio técnico?

### 🔴 3. Cómo se desglosa una reparación

Una reparación tiene mano de obra + repuestos.
- ¿Líneas separadas (una por repuesto + una de mano de obra) o una sola línea de "servicio de reparación"?
- Los repuestos: ¿con su código del catálogo o como texto libre?
- La mano de obra: ¿con qué código o glosa debe aparecer?

*(Recordar la Res. 36/2024: la descripción debe ser el producto real, sin abreviaturas ni códigos internos.)*

### 4. Equipos en garantía

Una reparación en garantía no se cobra. **¿Se emite algún documento tributario igual** (por ejemplo
una guía de despacho al devolver el equipo) **o no se emite nada?**

### 5. Qué razón social factura

Hay dos: **Importadora y Exportadora Dali** (76.301.506-8) y **Plásticos Dali** (76.754.504-5).
¿El servicio técnico se factura por cuál? *(Confirmar el segundo RUT.)*

### 6. Sucursal y lista de precios

- Si el equipo se recibe en Coquimbo o Abate Molina pero se repara en Mirador, **¿desde qué sucursal
  se emite?**
- ¿Qué lista de precios corresponde para servicio técnico?

### 7. Forma de pago

Al emitir, ¿se registra el pago en el momento (efectivo, transferencia, tarjeta) o el documento queda
a crédito y el pago se registra después?

### 8. Anulaciones

Un documento mal emitido se corrige con **nota de crédito** (los documentos electrónicos no se borran).
**¿Quién debe estar autorizado a emitirla? ¿Requiere visto bueno de Contabilidad?**

---

## 8. Plan por fases, con el punto de no retorno marcado

| Fase | Qué pasa | Reversible |
|---|---|---|
| **1. Preparación interna** ✅ *(hecho)* | Tabla y código base. Nada emite. | ✅ Total |
| **2. Traducción a Bsale** | El código que arma el documento en el formato de Bsale. | ✅ Total |
| **3. Pruebas en ambiente de prueba de Bsale** | Se emiten documentos de prueba. **No son electrónicos: no se timbran ni llegan al SII.** | ✅ Total |
| 🔴 **4. PRIMERA EMISIÓN REAL** | **PUNTO DE NO RETORNO.** Se crea un documento tributario verdadero, con folio real, que queda en los registros del SII. Solo se deshace con nota de crédito. | ❌ **No** |
| **5. Uso normal** | DaliGo emite en la operación diaria. | Parcial |
| **6. (Futuro, opcional)** | Evaluar reemplazar Bsale por un proveedor de solo-facturación. | — |

### 🔴 Condiciones propuestas para la Fase 4

Se propone que la primera emisión real **no ocurra** hasta que:

1. Contabilidad haya respondido las preguntas 1, 2 y 3.
2. Gerencia lo autorice **por escrito**.
3. Se haga con: **monto bajo**, en el **RUT menos crítico**, con **Contabilidad enterada** y con la
   **nota de crédito preparada** de antemano.
4. Quede registrada en este documento (fecha, folio, resultado).

**Nota:** el ambiente de prueba de Bsale **no emite documentos electrónicos** (su documentación dice
textualmente que tiene todas las características *"(no electrónicas)"*). Es decir: **la primera vez
que veremos un documento realmente timbrado será en producción.** Por eso la Fase 4 merece ceremonia.

---

## 9. Riesgos y cómo se controlan

| Riesgo | Control |
|---|---|
| **Emitir un documento por error o dos veces** | Cada documento lleva una clave única derivada de su origen; la base de datos **impide** físicamente crear dos para el mismo origen. Un doble clic no puede duplicar. |
| **Emitir contra producción creyendo que es prueba.** En Bsale el ambiente **lo define solo la credencial** (las direcciones son idénticas) | Bloqueo automático: el sistema no arranca con credenciales de producción fuera del servidor de producción. |
| **Quedarse sin folios** o usar folios vencidos (el SII rechaza) | Aviso automático preventivo por cantidad restante y por fecha de vencimiento. |
| **Documento rechazado por el SII** | Queda registrado y visible con su motivo; se corrige con nota de crédito. |
| **Perder el cierre de caja de Bsale** | Verificado: los documentos emitidos por integración **sí quedan asociados a la caja del día**. Falta confirmar con Bsale a qué caja/usuario se atribuyen. |
| **Depender de Bsale a futuro** | El sistema se diseñó para que cambiar de emisor sea reemplazar una pieza, sin rehacer el módulo. |
| **Cambio normativo del 1-nov-2026** | Consulta enviada a Bsale. **Pendiente.** |

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
| 1 | ¿Qué campos de transporte tendrá la integración y cuándo? (plazo 1-nov-2026) | Bsale | 🔴 **Enviada, esperando** |
| 2 | ¿A qué caja/usuario se atribuyen los documentos emitidos por integración? | Bsale | Enviada |
| 3 | ¿Los documentos por integración consumen el mismo cupo del plan? | Bsale | Enviada |
| 4 | ¿Se puede emitir un documento de prueba que sí llegue al SII? | Bsale | Enviada (la documentación sugiere que no) |
| 5 | Preguntas 1 a 8 de la §7 | Contabilidad | ⬜ Pendiente |
| 6 | Validar que el RCOF está derogado y que el RCV reemplaza los libros | Contabilidad | ⬜ Pendiente |
| 7 | Configuración de cierre de caja en Bsale: ¿"por emisor" o "por sucursal"? | Interno | ⬜ Pendiente |

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
| | Respuestas a las preguntas de la §7 | Contabilidad |
| | **Autorización de la Fase 4 (primera emisión real)** | Gerencia |
| | Registro de la primera emisión (folio, monto, resultado) | Desarrollo |

---

## 14. Tareas internas pendientes

- [ ] Corregir el criterio de aceptación de M05 en `RUTA-MAESTRA.md`: dice *"una cotización creada en
      staging termina como DTE real en el sandbox de Bsale"*, y eso es **inalcanzable** porque el
      ambiente de prueba de Bsale no emite documentos electrónicos.
- [ ] Cerrar la decisión **D-005** con las respuestas de Bsale.
- [ ] Revisar si el flujo de guías de despacho de DaliGo necesita capturar chofer, patente y horarios
      antes del 1-nov-2026.
