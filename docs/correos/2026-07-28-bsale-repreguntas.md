# Correo a Bsale — repreguntas (28 de julio de 2026)

> Respuesta a su correo del 28-jul-2026. Dos consultas puntuales: las dos citan la propia
> documentación de Bsale, así que son difíciles de responder en general.
> **Para copiar y pegar tal cual.**

---

**Asunto:** Re: Consultas integración API — dos puntos por aclarar (caja y folios)

Estimados,

Muchas gracias por la respuesta, nos fue muy útil. Especialmente la confirmación de que el ambiente de
pruebas no es electrónico y la recomendación de hacer la primera emisión en producción con un documento
de bajo monto: con eso ya podemos planificar.

Nos quedaron dos puntos por aclarar, los dos sobre detalles concretos de la API.

**1. Asociación de la caja al emitir**

En el punto 6 nos indican que, para que los documentos emitidos por API se reflejen correctamente en el
cierre de caja y en el cuadre de medios de pago, es necesario que al momento de la emisión se asocien
correctamente **la sucursal, la caja y la forma de pago**.

La sucursal (`officeId`) y la forma de pago (`payments[].paymentTypeId`) las estamos enviando sin
problema. Nuestra duda es la **caja**: revisando la documentación de `POST /v1/documents.json` en
https://docs.bsale.dev/documentos no encontramos ningún campo para indicarla.

Concretamente:

- ¿Existe un campo para asociar la caja al crear el documento? Si existe, ¿cómo se llama?
- Si no existe, ¿de dónde toma Bsale la caja? ¿Se deriva de la sucursal (`officeId`), del usuario dueño
  del token, o los documentos emitidos por API quedan en una caja por defecto?
- ¿Hay algo que debamos configurar en el panel para que queden en la caja correcta?

Lo preguntamos antes de emitir y no después: si un documento queda en una caja equivocada, el descuadre
aparece al cierre del día y es difícil de rastrear.

**2. Consulta del stock de folios**

En el punto 3 nos indican que no existe un método para reservar un folio ni para consultar cuál será el
siguiente folio antes de emitir. Eso lo entendemos, y no es lo que necesitamos.

Lo que queremos es distinto: **saber con anticipación si nos estamos quedando sin folios o si el CAF está
por vencer**, para avisarlo internamente antes de que se frene la emisión.

- ¿Existe algún endpoint para consultar, por tipo de documento, la **cantidad de folios disponibles** y
  la **fecha de vencimiento del CAF** vigente?
- Vimos referencias a `GET /v1/document_types/caf.json`. ¿Es ese el endpoint correcto? ¿Podrían
  confirmarnos qué campos devuelve?
- Si no hay forma por API, ¿hay alguna alerta o notificación que Bsale envíe cuando los folios se están
  agotando o cuando el CAF está próximo a vencer?

Nos importa porque desde la Res. Ex. SII N°58/2017 los CAF tienen vigencia de seis meses, y un folio
vencido es rechazado por el SII al momento de la recepción.

Quedamos atentos. Muchas gracias.

Saludos cordiales,

---

## Notas internas (NO enviar)

**Por qué estas dos y no más.** Las otras respuestas quedaron cerradas: el sandbox no es electrónico
(confirmado), la primera emisión va en producción con monto bajo (recomendado por ellos mismos), el folio
se asigna por tipo + sucursal (valida nuestro diseño), y el cupo de documentos del plan lo derivaron al
**ejecutivo comercial** — eso lo tiene que preguntar Gerencia por la vía comercial, no soporte técnico.

**Lo que NO se repregunta acá.** El plazo del 1-nov-2026 (campos de transporte de la guía de despacho)
no se vuelve a preguntar por este canal: ya respondieron que cumplirán "dentro de los plazos" sin dar
fecha. Insistir por soporte técnico va a dar la misma respuesta. Ese hay que empujarlo por el
**ejecutivo comercial**, y en paralelo vigilar su documentación oficial.

**Qué depende de la respuesta 1.** Nada bloqueante para seguir programando, pero sí bloqueante para la
**primera emisión real**: sin saber a qué caja va el documento, el cierre de caja de ese día puede
descuadrar.

**Qué depende de la respuesta 2.** El aviso preventivo de folios (paso B7). Si la respuesta es que no
hay forma por API, ese control pasa a ser **manual** y hay que decirlo en el informe en vez de dejarlo
prometido — ya está corregido en la §9.
