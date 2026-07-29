# Carpeta para la reunión con Gerencia General — 29 de julio de 2026

**Tema:** avance en facturación electrónica desde DaliGo
**Preparado por:** Marcos Uribe (desarrollo DaliGo) · 28 de julio de 2026

> **Cómo usar esta carpeta.** No es para leer en la reunión: es para leerla **antes**, 10 minutos. La §1
> es lo único imprescindible. La §5 es un guion de 5 minutos por si querés seguirlo tal cual. La §8 son
> las preguntas que probablemente te hagan, con la respuesta ya pensada.
>
> El informe formal y detallado, con fuentes citadas, es **`docs/FACTURACION-ELECTRONICA.md`** — ese es
> para dejarle al gerente y al contador, no para presentar.

---

## 1. Si solo decís una cosa, que sea esta

> **«No construimos un sistema de facturación. Averiguamos qué hace falta, descartamos el camino caro y
> dejamos preparado el camino corto. Todavía no se emitió ningún documento, y el paso irreversible
> necesita tu firma.»**

Las tres ideas de esa frase, por separado:

1. **Bsale sigue siendo el que factura.** No se toca su certificación, ni sus folios, ni su conexión con
   el SII. DaliGo solo le va a pasar los datos que hoy alguien vuelve a tipear a mano.
2. **Descartamos construir el timbre electrónico propio**, y no por intuición: son 3 a 5 meses de
   trabajo contra 5 a 12 días integrando. Ese descarte, con los números, es el resultado más valioso de
   estos dos días.
3. **Documentos emitidos: cero.** El sistema hoy **no puede** emitir aunque alguien lo intente.

---

## 2. Qué se avanzó en dos días (en lenguaje de negocio)

| Qué se hizo | Para qué sirve |
|---|---|
| Investigación completa de la normativa del SII, en fuentes oficiales | Saber qué exige la ley, no lo que dice un blog |
| Se descartó construir el timbre electrónico, con los números al lado | Evita meter la empresa en 3-5 meses de desarrollo con riesgo legal |
| Se consultó formalmente a Bsale y respondieron por escrito | Tenemos su palabra documentada sobre cómo y cuándo se puede probar |
| El contador definió las 8 reglas contables y ya están en el sistema | El sistema factura como Contabilidad dice, no como el programador supone |
| Se construyó la traducción DaliGo → Bsale | Es el trabajo que hoy hace una persona tipeando dos veces |
| Se puso un candado que impide emitir por error | Un token equivocado ya no puede generar una factura real |
| Se creó el rol *Jefe de sucursal* con permiso para anular | Los 4 nombres que definió el contador son los únicos que pueden anular |
| Se documentó todo en la guía maestra del proyecto | Si mañana entra otra persona, no depende de mi memoria |

**Y un hallazgo que no era el objetivo:** revisando los precios se descubrió que el sistema tomaba el
precio de **una lista cualquiera** entre las 5 activas — una reparación de Mirador podía cotizarse con
precios de Coquimbo, sin que nada lo mostrara en pantalla. Ya está corregido y fijado en la lista
GENERAL. **Ese error estaba afectando cotizaciones reales hoy**, no en el futuro.

---

## 3. Los cuatro conceptos que conviene poder explicar

Si te preguntan, esto es todo lo que necesitás saber. No hace falta más.

**DTE (Documento Tributario Electrónico).** Es la boleta o factura en versión electrónica. Lo importante:
el documento legal es **el archivo XML**, no el PDF. El PDF es solo "la versión imprimible". El SII obliga
a guardar los XML por **6 años**.

**El timbre electrónico.** Es el sello que hace que el SII reconozca el documento como válido. Se genera
con un certificado digital y con los folios que el SII autoriza. **Hoy lo hace Bsale por nosotros**, y la
recomendación es que siga siendo así. Es la parte más difícil y más riesgosa de construir.

**Folio (y CAF).** Es el número del documento. El SII entrega los números por paquetes autorizados, y
esos paquetes **vencen a los 6 meses**. Un folio vencido es rechazado. Los administra Bsale.

**Nota de crédito.** Es la única forma de anular un documento electrónico: **no se borran**. Si sale mal
uno, se emite una nota de crédito que lo revierte. Por eso importa quién puede emitirlas.

---

## 4. Cómo explicar qué hicimos, si te lo preguntan simple

> «Hoy la venta se registra dos veces: una en DaliGo y otra a mano en Bsale para emitir la boleta.
> Estamos haciendo que DaliGo le pase esos datos a Bsale automáticamente. Bsale sigue emitiendo igual que
> siempre; lo que se elimina es el doble tipeo y los errores que eso trae.»

Y si te preguntan por qué tardó dos días si es "solo pasar datos":

> «Porque antes de programar había que saber tres cosas: qué exige el SII, qué permite Bsale y cómo
> quiere facturar Contabilidad. Las tres estaban sin responder. Si programábamos primero, había que
> rehacerlo.»

---

## 5. Guion sugerido, 5 minutos

**(1) Encuadre — 30 segundos.**
«Vengo a mostrar avances de facturación electrónica. Adelanto lo principal: **no emitimos ningún
documento y no vamos a emitir ninguno sin tu autorización por escrito.** Lo que hicimos fue averiguar y
preparar.»

**(2) La decisión importante — 1 minuto.**
«Lo primero que evaluamos fue si construir nuestro propio timbre electrónico. **Lo descartamos.** Son 3
a 5 meses de trabajo, más mantención permanente porque el SII cambió el formato cuatro veces en un año.
Integrarnos con Bsale, que ya está certificado y que ya pagamos, son 5 a 12 días.»

**(3) Las reglas contables — 1 minuto.**
«Antes de programar le hicimos 8 preguntas al contador: cómo se reparte el IVA, quién elige entre boleta
y factura, cómo se desglosa una reparación, quién puede anular. Las respondió todas y **ya están dentro
del sistema**. Eso significa que el sistema va a facturar como Contabilidad dice, no como yo suponga.»

**(4) Lo que falta y por qué — 1 minuto.**
«Falta una prueba real. Y acá hay algo que Bsale nos confirmó por escrito: **su ambiente de prueba no es
electrónico**, no llega al SII. O sea que **no existe una forma de ensayar de verdad sin emitir de
verdad**. Ellos mismos recomiendan hacer la primera emisión en producción con un documento de monto bajo
y anularlo con nota de crédito si hace falta.»

**(5) Lo que necesito de ti — 1 minuto.**
Las tres decisiones de la §7.

**(6) Un tema aparte — 30 segundos.**
El plazo del 1 de noviembre (§9). Plantearlo como lo que es: **un riesgo que ya existía y que esta
investigación destapó**.

---

## 6. Las 8 preguntas del contador (por si quiere verlas)

Sirven para mostrar que hubo método, no improvisación. Están completas en la §7 del informe.

| # | Pregunta | Respuesta | Estado |
|---|---|---|---|
| 1 | ¿Qué cifra debe cuadrar exacto, el total o el neto? | El total que paga el cliente | ✅ En el sistema |
| 2 | ¿Boleta o factura, quién decide? | Lo decide el cliente | ✅ En el sistema |
| 3 | ¿Cómo se desglosa una reparación? | Una línea por repuesto + una de mano de obra | ✅ En el sistema |
| 4 | ¿Se emite algo en garantía? | Hoy no; debería emitirse guía de despacho | ⏸ En espera (ver §9) |
| 5 | ¿Qué razón social factura? | Importadora y Exportadora Dali, solo esa | ✅ En el sistema |
| 6 | ¿Desde qué sucursal se emite? | Desde donde se repara | ✅ En el sistema |
| 7 | ¿El pago se registra al emitir? | Sí, en el momento | ✅ En el sistema |
| 8 | ¿Quién puede anular? | Gerente, jefe de ventas y jefes de sucursal | ✅ En el sistema |

---

## 7. Las tres decisiones que necesitás pedirle

**1. Autorización para la primera emisión real.** No es hoy, no es esta semana: es cuando esté todo
preparado. Pero conviene plantear ahora **qué condiciones va a exigir**. Las propuestas: monto bajo,
Contabilidad enterada, nota de crédito preparada de antemano y autorización por escrito.

**2. Que insista con Bsale por la vía comercial.** El canal técnico ya respondió lo que tenía. La fecha
de los campos nuevos de la guía de despacho (§9) hay que empujarla por el ejecutivo comercial. Ese
teléfono lo tiene Gerencia, no desarrollo.

**3. Asignar el rol de Jefe de sucursal.** El rol y el permiso ya existen en el sistema. Falta que
Gerencia decida asignárselo a Luis Figueroa y Gonzalo Martínez.

*(Y un dato menor que sale de la misma conversación: el cupo de documentos mensuales del plan de Bsale lo
derivaron al ejecutivo comercial. Si Gerencia lo quiere saber, es una llamada.)*

---

## 8. Preguntas que te pueden hacer, con la respuesta

**«¿Esto ya está funcionando?»**
No. Está construido el mecanismo, pero el sistema no puede emitir: falta cargar unos identificadores de
Bsale y falta tu autorización. Documentos emitidos: cero.

**«¿Cuánto falta?»**
Para poder emitir de prueba: días de trabajo. Para la primera emisión real: depende de tu autorización,
no del desarrollo. Y hay dos cosas que no dependen de nosotros: una credencial de prueba y dos respuestas
que le repreguntamos a Bsale.

**«¿Y si sale mal la primera factura?»**
Se anula con nota de crédito, que es el procedimiento normal y legal. Va a estar preparada de antemano.
Además el monto va a ser bajo, así que el impacto contable es mínimo.

**«¿Esto nos ata más a Bsale?»**
Al contrario. El sistema se diseñó para que cambiar de proveedor sea reemplazar **una sola pieza**. Hoy
si quisiéramos dejar Bsale habría que rehacer todo; después de esto, no.

**«¿Por qué no construimos nuestro propio sistema y dejamos de pagar Bsale?»**
Se evaluó y está en el informe con números: 3 a 5 meses solo para el timbre, más mantención permanente
porque el SII cambia el formato seguido. Y el timbre es la parte **más chica** de lo que hace Bsale —
también está el punto de venta, la caja, el inventario. Reemplazarlo es un proyecto aparte, no un
subproducto de este.

**«¿Hace falta hacer algún trámite en el SII?»**
No. La empresa **ya es emisor electrónico autorizado** a través de Bsale. La autorización es del RUT, no
del software. Cambiar de sistema interno no exige recertificarse.

**«¿Quién se hace responsable si el SII rechaza algo?»**
El documento lo emite Bsale y el rechazo queda registrado y visible en DaliGo con su motivo. La
responsabilidad tributaria es de la empresa, como siempre: no cambia porque cambie el software. Lo que sí
conviene definir es quién revisa los rechazos — eso está como pregunta abierta para Contabilidad.

**«¿Podemos probar sin riesgo?»**
Hasta cierto punto. Se puede probar todo el mecanismo sin emitir. Lo que **no** se puede es ver un
documento realmente timbrado sin que sea real: Bsale nos lo confirmó por escrito y su propia
recomendación es hacer la primera en producción con monto bajo.

---

## 9. El plazo del 1 de noviembre — cómo plantearlo

**No es una alarma, y conviene decirlo así.** Es un cambio normativo que afecta a Dali **aunque este
proyecto no se haga**.

A partir del **1 de noviembre de 2026** el SII va a exigir que las **guías de despacho** incluyan datos
del transporte: patente del vehículo y del remolque, RUT del transportista, nombre y cédula del chofer,
hora de salida, y dirección de origen y destino. Además, una guía por cada traslado y por cada vehículo.

**El problema:** hoy la integración de Bsale **no tiene dónde registrar esos datos**. Se les consultó
formalmente y respondieron que cumplirán "dentro de los plazos establecidos por el SII", **sin dar
fecha**, y que los campos nuevos los van a publicar en su documentación para que cada uno los implemente.

**Cómo decirlo:**

> «Esto lo encontramos investigando y no tiene que ver con nuestro proyecto: en noviembre el SII exige
> datos nuevos en las guías de despacho y hoy Bsale no tiene dónde ponerlos. Les preguntamos y
> respondieron que van a cumplir, pero sin fecha. Nos afecta igual, así que conviene que lo empujemos por
> el lado comercial.»

Dato que ayuda: **la guía impresa de Dali ya tiene esos campos en blanco** (nombre del conductor, RUT,
patente). Es exactamente esa información la que pasa a ser obligatoria.

---

## 9bis. «¿Y si DaliGo se cae?» — la pregunta que va a salir

Es la preocupación real de Gerencia y hay que tomarla en serio, porque la observación de fondo es
**correcta**: Bsale no se cae, y eso es un valor que hoy la empresa ya tiene.

### La respuesta corta, y es fuerte

> **«Si DaliGo se cae, se factura en Bsale como siempre. No se detiene la venta.»**

Con el diseño actual **a nadie se le quita nada**: Bsale sigue siendo el que emite, con su certificación y
sus folios. DaliGo solo le pasa los datos. La red de seguridad sigue puesta, y eso es a propósito.

### Y la parte honesta, para no prometer de más

DaliGo **hoy no está al nivel de disponibilidad de Bsale**, y no por el código: por dónde está alojado.

| Situación actual | Qué significa |
|---|---|
| Hosting **compartido** | El servidor se comparte con otros sitios; el consumo de un vecino afecta |
| Sin procesos permanentes | Las tareas automáticas corren cada 15 minutos, no al instante |
| Base de datos MySQL 5.7 | Versión antigua, fuera de soporte |
| **Nadie vigila si está arriba** | Existe una dirección de chequeo, pero no hay nada consultándola: una caída de fin de semana se descubre el lunes |
| Sin respaldos definidos ni probados | Un respaldo que nunca se restauró no es un respaldo |

**Esto está bien para lo que DaliGo es hoy** — un panel de gestión interno: si se cae 20 minutos, alguien
espera 20 minutos. **No está bien para un sistema que emite facturas**, porque ahí caerse significa no
poder vender.

### Lo que costaría llegar a ese nivel (ninguna es de programación)

Hosting dedicado · monitoreo con aviso · respaldos automáticos y probados · **plan de contingencia para el
mostrador** (qué hace la persona que tiene un cliente adelante y la pantalla no carga) · y custodia del
certificado digital y los folios, que pasa a ser responsabilidad legal de la empresa.

Bsale no se cae porque alguien paga por que no se caiga. Es un costo real, y hoy está incluido en lo que
la empresa ya paga.

### Cómo cerrarlo con el gerente

> «Hoy DaliGo no reemplaza a Bsale, lo alimenta. Y eso es a propósito: si algo falla, Bsale sigue estando.
> El día que se quiera reemplazar, lo que hay que discutir no es el software: es cuánto cuesta un servidor
> que no se caiga. Es un proyecto aparte, con presupuesto propio.»

Esto además **justifica el orden** que estamos siguiendo: primero alimentar a Bsale (reversible, con red),
y recién después de meses sin incidentes se puede *pensar* en reemplazarlo.

---

## 10. Lo que NO conviene prometer

Esto es para protegerte a vos en la reunión.

- **No prometas una fecha de la primera emisión real.** Depende de su autorización y de dos respuestas de
  Bsale.
- **No digas que "ya está listo para facturar".** Está listo el mecanismo, no la operación.
- **No prometas el aviso automático de folios.** Bsale respondió que no se pueden consultar por
  anticipado; quedó una repregunta y puede que ese control tenga que ser manual.
- **No te comprometas con la guía de despacho de garantía.** Es la que choca con el plazo de noviembre.
- **Si no sabés algo, decilo y anotalo.** Es mejor que improvisar: en este tema una respuesta inventada se
  transforma en un documento tributario mal emitido.
- **No digas que DaliGo va a ser tan estable como Bsale.** Hoy no lo es (§9bis) y eso se resuelve con
  infraestructura, no con código. Prometerlo es la forma más rápida de quedar mal en seis meses.

---

## 11. Una cosa más, por si sirve

Si en algún momento sentís que no dominás el detalle técnico, el encuadre honesto es este:

> «Mi trabajo en estos dos días no fue programar facturación. Fue **averiguar antes de programar**:
> preguntarle al SII lo que dice la norma, al contador cómo quiere facturar y a Bsale qué permite su
> sistema. Las tres respuestas cambiaron lo que había que construir. Si hubiéramos empezado por el
> código, hoy estaríamos rehaciéndolo.»

Eso es exactamente lo que pasó, y es la parte que más valor tiene.
