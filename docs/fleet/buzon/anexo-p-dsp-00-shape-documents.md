# Anexo — Salida ÍNTEGRA de la delegación P-DSP-00 (shape de documents.json)
> Recibida por el Director el 2026-07-14 vía IA-cPanel (VEREDICTO del operador: APROBADO,
> 1/1 pasos OK, cero cambios en el server). Valores REDACTADOS por el propio comando
> `bsale:explore` §7 — sin datos reales de clientes ni montos. Max-2: archivar en
> `docs/qa/INFRA/2026-07-14--INFRA--p-dsp-00-shape-documents.md` al cerrar P-DSP-00.

## Salida textual

```
== Documentos de venta (shape para DESPACHOS-v1 — valores redactados) ==
  total (envelope count): 675912  ·  en esta página: 3
  claves de la CABECERA del documento:
    href, id, emissionDate, expirationDate, generationDate, number, serialNumber, trackingNumber, totalAmount, netAmount, taxAmount, exemptAmount, notExemptAmount, exportTotalAmount, exportNetAmount, exportTaxAmount, exportExemptAmount, commissionRate, commissionNetAmount, commissionTaxAmount, commissionTotalAmount, percentageTaxWithheld, purchaseTaxAmount, purchaseTotalAmount, address, municipality, city, urlTimbre, urlPublicView, urlPdf, urlPublicViewOriginal, urlPdfOriginal, token, state, commercialState, cancellationStatus, cancellationDate, urlXml, ted, salesId, informedSii, responseMsgSii, document_type, office, user, coin, priceList, references, document_taxes, details, sellers, attributes, payments
  ✅ details PRESENTE en la respuesta GET. Claves de una LÍNEA de detalle:
    href, id, lineNumber, quantity, netUnitValue, netUnitValueRaw, totalUnitValue, netAmount, taxAmount, totalAmount, netDiscount, totalDiscount, variant, note, relatedDetailId, gratuity, discountId, discountPercentage
  tipos de la línea de detalle (redactado):
    href                : string(len=55)
    id                  : int
    lineNumber          : int
    quantity            : float
    netUnitValue        : int
    netUnitValueRaw     : float
    totalUnitValue      : int
    netAmount           : int
    taxAmount           : int
    totalAmount         : int
    netDiscount         : int
    totalDiscount       : int
    variant             : array[4 claves]
    note                : string(len=0)
    relatedDetailId     : int
    gratuity            : int
    discountId          : int
    discountPercentage  : float
  claves del nodo office: href, id, name, description, address, latitude, longitude, isVirtual, country, municipality, city, zipCode, email, costCenter, state, imagestionCellarId, store, defaultPriceList
  tipos de la CABECERA (redactado — sin valores reales):
    href                : string(len=42)
    id                  : int
    emissionDate        : int (¿epoch?)
    expirationDate      : int (¿epoch?)
    generationDate      : int (¿epoch?)
    number              : int
    serialNumber        : null
    trackingNumber      : null
    totalAmount         : int
    netAmount           : int
    taxAmount           : int
    exemptAmount        : int
    notExemptAmount     : int
    exportTotalAmount   : int
    exportNetAmount     : int
    exportTaxAmount     : int
    exportExemptAmount  : int
    commissionRate      : int
    commissionNetAmount : int
    commissionTaxAmount : int
    commissionTotalAmount : int
    percentageTaxWithheld : float
    purchaseTaxAmount   : int
    purchaseTotalAmount : int
    address             : null
    municipality        : null
    city                : null
    urlTimbre           : null
    urlPublicView       : string(len=52)
    urlPdf              : string(len=56)
    urlPublicViewOriginal : string(len=45)
    urlPdfOriginal      : string(len=49)
    token               : string(len=12)
    state               : int
    commercialState     : int
    cancellationStatus  : int
    cancellationDate    : null
    urlXml              : string(len=52)
    ted                 : null
    salesId             : null
    informedSii         : int
    responseMsgSii      : null
    document_type       : array[2 claves]
    office              : array[18 claves]
    user                : array[2 claves]
    coin                : array[2 claves]
    priceList           : array[2 claves]
    references          : array[5 claves]
    document_taxes      : array[1 claves]
    details             : array[5 claves]
    sellers             : array[1 claves]
    attributes          : array[1 claves]
    payments            : array[1 claves]

Listo. Exploración de solo lectura completada.
```

## Notas del operador
- El comando no imprime enumeración de tipos de documento (Factura/Boleta/Guía);
  `document_type` viene solo como nodo `array[2 claves]` de la cabecera.
- La sesión de Terminal se desconectó y reconectó sola durante la ejecución, sin pérdida.
- Archivo temporal `/tmp/bsale_explore_out2.txt` usado para copiar la salida, eliminado.

## Reconciliación del Director contra PLAN-DESPACHOS-V1 §1.2 (los 4 hallazgos)
1. **`state` es INT, no string.** El plan asumía `state string(32)`. Corregir a entero
   (+ `commercialState` y `cancellationStatus` también int — candidatos útiles, ver 4).
2. **El nodo `client` NO vino en la respuesta** pese al `expand=[...client...]` (el explorador
   solo imprimió `office`). Hipótesis: los 3 docs de la página eran boletas sin cliente
   asociado. `cliente_id` nullable ya lo cubre; el sync debe tolerar ausencia y matchear solo
   cuando venga. Verificación barata durante P-DSP-01: probar contra un doc tipo factura.
3. **La línea de detalle NO trae `description`.** El plan tenía columna `descripcion` como
   fallback. El texto tiene que salir del nodo `variant` (array de 4 claves — confirmar si
   una es description/code) o derivarse del producto espejado vía `producto_id`; si `variant`
   solo trae `{href,id,...}`, evaluar dejar `descripcion` nullable poblada solo cuando el
   match a producto falle y el nodo lo permita.
4. **675.912 documentos totales** → backfill completo INVIABLE en hosting compartido. El sync
   DEBE operar por ventana `emissiondaterange` (ya previsto como mitigación del riesgo #2) y
   necesita una **fecha de arranque del espejo** (propuesta del Director: solo documentos
   desde el go-live de DESPACHOS-v1, configurable). Punto de decisión liviano para el dueño;
   default conservador si no responde: últimos 7 días al primer run.

Confirmaciones (sin cambio al plan): `details` presente (sobre anidado `{items}` — el
explorador lo leyó vía `['details']['items'][0]`) · fechas epoch confirmadas (cast
epoch→datetime como las 4 syncs) · montos en int (CLP) — `decimal(14,4)` del plan sirve ·
`office` trae `id` para el match contra `bodegas.bsale_office_id` · `document_type` nodo
`{href,id}` → guardar `bsale_document_type_id` desde el `id` anidado · claves nuevas útiles
para v1: `cancellationStatus`/`cancellationDate` (detección de anulación, mitiga riesgo #2),
`urlPublicView`, `token`. `sellers`/`payments`/`document_taxes` quedan fuera de v1.
