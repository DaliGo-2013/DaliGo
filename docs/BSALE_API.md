# BSALE_API.md

> **Documento de referencia para integrar la API pública de Bsale (Chile) con DaliGo**
> **Fecha de acceso a la documentación:** 8 de junio de 2026
> **Fuente oficial:** https://docs.bsale.dev/ (API Chile) · landing devs: https://www.bsale.dev/
> **Sin credenciales:** todo proviene de la documentación pública para desarrolladores.
>
> **Leyenda:** ✅ **[CONFIRMADO]** = textual en docs · 🔎 **[INFERIDO]** = deducción razonada · ❓ **[NO ENCONTRADO]** = no aparece en la doc revisada.

---

## A. Fundamentos

**URL de la doc y versiones.** Doc técnica en https://docs.bsale.dev/ ; comunidad/landing en https://www.bsale.dev/ . Hay tres versiones por país: **Chile** (/first-steps), **Perú** (/PE/first-steps), **México** (/MX/first-steps). La vigente para nosotros es **API Chile**, versión **v1** (/v1/...), con algunos endpoints **v2** (ej. packs /v2/products/pack.json; los webhooks referencian recursos /v2/...). ✅ [CONFIRMADO] — https://docs.bsale.dev/first-steps · https://docs.bsale.dev/get-started

**Base URL de producción.** https://api.bsale.io/v1/ en todos los ejemplos; https://api.bsale.cl/v1/ aparece para archivos XML. URL base para recursos notificados por webhook: https://api.bsale.io . ✅ [CONFIRMADO] — https://docs.bsale.dev/get-started · https://docs.bsale.dev/webhooks

**Sandbox.** **Sí.** Se crea una cuenta en https://www.bsale.dev/ y se obtiene un access_token en entorno de prueba ("no te tomará más de 1 minuto"). ✅ [CONFIRMADO] — https://docs.bsale.dev/get-started · https://www.bsale.dev/

**Autenticación.** Header HTTP **access_token** en cada petición. Ejemplo textual: curl -i -H "access_token: tutokendeacceso" -X GET https://api.bsale.io/v1/clients.json . Token **único por usuario/empresa**. ✅ [CONFIRMADO] — https://docs.bsale.dev/get-started

Obtención en **producción**, dos vías: (1) solicitarlo por correo a ayuda@bsale.app ; (2) **OAuth 2.0** — redirección a https://oauth.bsale.io/login?app_id=...&redirect_uri=...&client_code=... → recibes code → POST https://oauth.bsale.io/gateway/oauth_response.json con {code, usrToken, appId} → respuesta con accessToken. Requiere solicitar app_id vía formulario. ✅ [CONFIRMADO] — https://docs.bsale.dev/oauth

> **Scopes y expiración del token:** no documentados. ❓ [NO ENCONTRADO]

**Límites (rate limit).** **3.000 requests / 300 segundos**; al excederlo → HTTP **429**. ✅ [CONFIRMADO] — https://docs.bsale.dev/faq

**Paginación.** limit (default **25**, **máx 50**) y offset (default 0). Sobre de respuesta: { "href", "count", "limit", "offset", "items": [...], "next": "...url..." }. ✅ [CONFIRMADO] — https://docs.bsale.dev/productos-y-servicios · https://docs.bsale.dev/clientes

**Formatos.** JSON con atributos en **camelCase**, respuestas en inglés. Fechas como **enteros Unix epoch** (ej. 1388545200 = 2014-01-01); en documentos "no se debe aplicar zona horaria". Parámetros expand (relaciones en una sola petición), fields (atributos específicos) y filtros por recurso. Decimales/moneda: montos como Float; moneda vía nodo coin (peso, UF, USD…). ✅ [CONFIRMADO] — https://docs.bsale.dev/get-started

**Códigos de error.** 400, 401, **402 (instancia bloqueada por no pago)**, 403, 404, 405, 429, 500, 502. Cada error trae código + mensaje. ✅ [CONFIRMADO] — https://docs.bsale.dev/faq

**Webhooks.** **Sí existen** (POST a tu URL). Topics: **document, product, variant, price, stock, documento pagado**, además de tienda en línea (producto web, colección, venta online) y otros (pagos, doc. de compra, RCOF, courier externo). Estructura: {cpnId, resource, resourceId, topic, action, send}. **Activación NO self-service:** se solicita por correo a ayuda@bsale.app indicando URL + RUT/cpnId. No hay eventos DELETE (desactivación se informa como PUT). ✅ [CONFIRMADO] — https://docs.bsale.dev/webhooks · https://docs.bsale.dev/productos-y-servicios/webhooks
> ⚠️ **Corregido en el Anexo A:** el panel de Devs SÍ permite **auto-configurar webhooks** (formulario con URL + topics + acciones), sin pedirlos por correo.

**SDKs.** **No hay SDK oficial.** Comunitarios en Packagist: ticketeradigital/bsale ("Bsale connector", ~3.304 instalaciones) y pviojo/bsale-api-php. Validar compatibilidad con Laravel 12 / PHP 8.3 antes de adoptar. ✅ [CONFIRMADO existencia] / 🔎 [INFERIDO: no oficiales] — https://packagist.org/?query=bsale

---

## B. Productos / Catálogo (PRIORIDAD)

**Modelo producto ↔ variante ↔ SKU.** Un **producto** (/v1/products) tiene **1 o más variantes** (/v1/variants). El **SKU vive en la VARIANTE**, campo **code**; el barCode también está en la variante. Una variante tiene un solo producto padre. Para vender se referencia la variante (variantId/code/barCode) en details. ✅ [CONFIRMADO] — https://docs.bsale.dev/productos-y-servicios · https://docs.bsale.dev/variantes

**Campos del producto:** id, name, description, classification (0=producto, 1=servicio, 3=pack/promoción), ledgerAccount, costCenter, allowDecimal, stockControl, printDetailPack, state (0=activo,1=inactivo), nodos product_type, product_taxes. ✅ [CONFIRMADO]

**Campos de la variante:** id, description (nombre variante), unlimitedStock, allowNegativeStock, state, barCode, code (SKU), imagestion* (legacy), serialNumber, prestashop*, nodos product, attribute_values, costs. ✅ [CONFIRMADO]

### 🔴 PESO y DIMENSIONES — premisa del negocio

**Premisa CONFIRMADA.** En la estructura documentada de **producto** y de **variante** **NO existen campos de peso ni de dimensiones** (alto/ancho/largo). ✅ [CONFIRMADO por ausencia] — https://docs.bsale.dev/productos-y-servicios · https://docs.bsale.dev/variantes

**¿Campos adicionales?** Existen **atributos personalizados a nivel de variante** (attribute_values), **pero atados al product_type**: primero se define el atributo en el tipo de producto (name, isMandatory, generateVariantName, hasOptions, options, state) y luego se asigna el valor por variante. Son **strings/opciones sin tipo numérico ni unidades**. 🔎 Técnicamente podrías guardar peso/dimensiones como atributos string, pero no es fiable como dato numérico → **mejor local**. ✅ [CONFIRMADO mecanismo] / 🔎 [INFERIDO recomendación] — https://docs.bsale.dev/tipos-de-productos-y-servicios

**Categorías y marcas.**
- **Categorías = product_types**: CRUD completo (GET/POST/PUT/DELETE), GET /v1/product_types/{id}/products.json, .../attributes.json. ✅ [CONFIRMADO] — https://docs.bsale.dev/tipos-de-productos-y-servicios
- **Marcas:** **no hay endpoint público documentado.** Aparece brandId en la respuesta del POST de pack (sugiere manejo interno), pero sin endpoint para listarlas/crearlas. ❓ [NO ENCONTRADO — a confirmar con Víctor]

**Escritura.** Lectura **y** escritura: productos POST/PUT/DELETE (virtual → state 1); variantes POST/PUT/DELETE (PUT acepta attribute_values). **Escribir peso/dimensiones directamente: NO** (no hay campos); solo vía atributo del product_type. ✅ [CONFIRMADO] / 🔎 [INFERIDO]

**Unicidad de SKU/code.** No se afirma textualmente, pero se filtra/referencia por code y hay límites de SKU por instancia (errores "Max sku exceeded"), lo que implica unicidad operativa. 🔎 [INFERIDO] + ✅ [CONFIRMADO límite] — https://docs.bsale.dev/faq

### Endpoints clave de catálogo

| Método + ruta | Auth | Params clave | Notas |
|---|---|---|---|
| GET /v1/products.json | access_token | limit≤50, offset, fields, expand, name, producttypeid, state | Lista productos |
| GET /v1/products/{id}.json | sí | expand=[product_type] | Un producto |
| GET /v1/products/{id}/variants.json | sí | — | Variantes del producto |
| GET /v1/variants.json | sí | code, barcode, productid, state, expand=[product] | SKU en code |
| GET /v1/variants/{id}.json | sí | expand | Una variante |
| POST /v1/products.json | sí | body: name, classification, productTypeId, stockControl | Crear producto |
| PUT /v1/variants/{id}.json | sí | body: description, attribute_values[] | Editar variante/atributos |
| GET /v1/product_types.json | sí | name, state | Categorías |

**Ejemplo real — listar variantes:**
```
GET https://api.bsale.io/v1/variants.json?fields=[description,barCode,code]&limit=2
Header: access_token: <token>
```
```json
{
  "count": 868, "limit": 2, "offset": 0,
  "items": [
    { "id": 1548, "description": "120 ML", "barCode": "1401291513",
      "code": "1401291513", "product": { "id": "416" } }
  ],
  "next": "https://api.bsale.io/v1/variants.json?limit=2&offset=2"
}
```
*(No aparece weight, height, width ni length.)*

---

## C. Listas de precios

Bsale gestiona **1 o más listas**, cada una con su **moneda** (coin). Se referencian con priceListId al emitir documentos. ✅ [CONFIRMADO] — https://docs.bsale.dev/listas-de-precio

- **Lectura:** GET /v1/price_lists.json → {id, name, description, state, coin, details} (filtros name, coinid, state). GET /v1/price_lists/{id}/details.json → por **variante**: variantValue (neto) y variantValueWithTaxes (con IVA); filtros variantid, code, barcode, expand=[variant]. ✅
- **Escritura:** **NO existe POST** ("las listas comparten el total de productos de Bsale"). Solo editar valores: PUT /v1/price_lists/{listId}/details/{detailId}.json con {variantValue, id}. ✅
- Múltiples listas por moneda/canal: **sí**. Neto y con IVA: **ambos**. Descuentos/rangos por lista: ❓ no documentado (descuentos se aplican por detalle al emitir documento).

```
GET https://api.bsale.io/v1/price_lists/1/details.json?code=12345&expand=[variant]
```
```json
{ "count": 7634, "limit": 25, "offset": 0,
  "items": [ { "id": 663, "variantValue": 4590, "variantValueWithTaxes": 5462,
               "variant": { "id": "388" } } ] }
```

---

## D. Clientes (secundario)

GET /v1/clients.json + CRUD (DELETE virtual → state 99). **RUT en el campo code**; búsqueda por RUT: ?code=12345678-9. Campos: firstName, lastName, code (RUT), phone, company (razón social), activity (giro), address, city, municipality, email (campo plano del cliente — verificado contra la API real; los `contacts` son contactos ADICIONALES), companyOrPerson (0=persona/1=empresa), maxCredit, hasCredit, nodos contacts, attributes, addresses. Extranjeros: isForeigner: 1. ✅ [CONFIRMADO] — https://docs.bsale.dev/clientes

| Método + ruta | Auth | Notas |
|---|---|---|
| GET /v1/clients.json?code=12345678-9 | sí | Búsqueda por RUT |
| POST /v1/clients.json | sí | Crear |
| PUT /v1/clients/{id}.json | sí | Actualizar |
| GET /v1/clients/{id}/addresses.json | sí | Direcciones adicionales |

---

## E. Stock / bodegas (secundario)

**Bodegas = "Sucursales/Oficinas" (offices)**, físicas o virtuales (isVirtual); CRUD, pero la **creación depende del plan**. **Stock por variante y bodega:** GET /v1/stocks.json → {quantity, quantityReserved, quantityAvailable, variant, office}; filtros officeid, variantid, code, barcode. **Reservas: sí** (quantityReserved = en borradores/pendientes de despacho). **Escritura:** no se edita stock directo; se usan **recepciones** (POST /v1/stocks/receptions.json, suma) y **consumos** (POST /v1/stocks/consumptions.json, resta). ✅ [CONFIRMADO] — https://docs.bsale.dev/stocks · https://docs.bsale.dev/sucursales

```
GET https://api.bsale.io/v1/stocks.json?code=629&officeid=1
```
```json
{ "items": [ { "quantity": 60.36, "quantityReserved": 0, "quantityAvailable": 60.36,
               "variant": { "id": "351" }, "office": { "id": "2" } } ] }
```

---

## F. Documentos / facturación (secundario)

**Emisión de DTE por API: SÍ.** POST /v1/documents.json. Envío: documentTypeId o codeSii, officeId, priceListId, emissionDate, expirationDate, declareSii (1=declarar SII), client/clientId, details[] (variantId/code/barCode, netUnitValue, quantity, taxId/taxes[], discount, comment), payments[], references[], dynamicAttributes[], salesId (id externo idempotente), dispatch (rebaja stock), sendEmail. ✅ [CONFIRMADO] — https://docs.bsale.dev/documentos

**El documento devuelto trae:** id, number (**folio**), totalAmount, netAmount, taxAmount, state, informedSii (0=correcto/1=enviado/2=rechazado), ted, urlXml, **PDF**: urlPdf, urlPublicView, urlPdfOriginal, token; nodos client, office, details, references, document_taxes, sellers. ✅

| Método + ruta | Auth | Notas |
|---|---|---|
| POST /v1/documents.json | sí | Emite DTE → devuelve folio + urlPdf + urlXml |
| GET /v1/documents/{id}.json | sí | Obtener documento/estado/PDF |
| GET /v1/documents.json?clientcode=...&emissiondaterange=[a,b] | sí | Búsqueda con filtros por fecha |

---

## G. Síntesis de integración

### Tabla de mapeo por entidad

**Producto** (products)
| Campo Bsale | Tipo | ¿Lo necesitamos? | Columna DaliGo |
|---|---|---|---|
| id | int | Sí | bsale_product_id |
| name | string | Sí | nombre |
| description | string | Sí | descripcion |
| classification | int (0/1/3) | Sí | tipo |
| product_type.id | int | Sí | categoria_bsale_id |
| state | bool | Sí | activo |
| stockControl | bool | Opcional | controla_stock |

**Variante** (variants) — *aquí vive el SKU y el enlace clave*
| Campo Bsale | Tipo | ¿Lo necesitamos? | Columna DaliGo |
|---|---|---|---|
| id | int | **Sí (enlace)** | bsale_id |
| code | string | **Sí (SKU)** | sku |
| barCode | string | Sí | barcode |
| description | string | Sí | nombre_variante |
| product.id | int | Sí | bsale_product_id (FK) |
| state | bool | Sí | activo |
| *(no existe)* | — | **Sí → LOCAL** | **peso_g** 🔴 |
| *(no existe)* | — | **Sí → LOCAL** | **alto_mm, ancho_mm, largo_mm** 🔴 |

**Categoría** (product_types): id→categoria_bsale_id · name→nombre · state→activo.
**Lista de precios** (price_lists/details): price_list.id→lista_precio_id · details.variantValue→precio_neto · variantValueWithTaxes→precio_con_iva · coin.id→moneda_id.
**Cliente** (clients): id→bsale_client_id · code→rut · company→razon_social · activity→giro · address/city/municipality/email/phone.
**Stock** (stocks): office.id→bodega_id · variant.id→bsale_id · quantity→stock_real · quantityAvailable→stock_disponible · quantityReserved→stock_reservado.

### Respuesta directa (catálogo + precios)
- **Por API (espejo lectura):** productos, variantes (SKU=code, barcode), categorías (product_types), listas de precios (neto e IVA), stock por bodega, clientes, documentos/DTE. ✅
- **LOCAL en DaliGo (no existe en Bsale):** **PESO y DIMENSIONES por SKU** 🔴, más metadatos propios (clasificaciones internas, validación de retiro). Enlace por bsale_id = **id de la variante** (no del producto, porque el SKU es por variante).
- **Premisa del negocio: CONFIRMADA.** Peso/dimensiones no existen en Bsale; los attribute_values son strings atados al product_type, no sustituto fiable → **viven local**.

### Estrategia de sync
**Espejo solo-lectura** para lo que "manda" Bsale (productos, variantes, precios, stock) + **columnas locales exclusivas** (peso/dimensiones) que **nunca** se escriben a Bsale. Escritura hacia Bsale solo en fases futuras (crear productos, emitir DTE).
Detección de cambios: **webhooks** (product, variant, price, stock, document; requieren activación por email + URL pública HTTPS) **o** **polling por cron** (catálogo: recorrido paginado periódico, pues no hay filtro "modified-since" genérico en productos/variantes; documentos sí tienen emissiondaterange). Idempotencia de DTE con salesId.

---

## H. Operacional
- **¿Plan pago?** Sandbox/token gratis; **operación condicionada al plan** (creación de sucursales según plan, **402 por no pago**, límites de SKU). ✅ — https://docs.bsale.dev/sucursales · https://docs.bsale.dev/faq
- **Soporte / doc:** Slack (bsaledev), tickets (ayuda.bsale.app), correo ayuda@bsale.app. Doc buena (ejemplos JSON reales, changelog); huecos en scopes, expiración, marcas, "modified-since". ✅
- **Uptime:** ❓ no publicado en la doc revisada.
- **Límites duros:** 3.000/300s; paginación máx 50/página (impacta full-sync grande); webhooks no self-service.

---

## Sección "Respuestas directas" (checklist Sí/No/Desconocido)

| Pregunta | Resp. | Cita |
|---|---|---|
| ¿Hay sandbox? | **Sí** | get-started |
| ¿Cómo se autentica? | **Header access_token** (único por empresa) | get-started |
| ¿Es de pago? | **Operación según plan** (402 no pago, sucursales/SKU según plan); sandbox gratis | sucursales, faq |
| ¿Guarda peso? | **No** | productos-y-servicios, variantes |
| ¿Guarda dimensiones? | **No** | productos-y-servicios, variantes |
| ¿Campos adicionales? | **Sí**, attribute_values (string, atados a product_type) | tipos-de-productos, variantes |
| ¿Se pueden ESCRIBIR productos? | **Sí** (POST/PUT/DELETE) | productos-y-servicios |
| ¿Marcas por API? | **No (no documentado)** | — |
| ¿Categorías por API? | **Sí** (product_types, CRUD) | tipos-de-productos |
| ¿Listas de precios? | **Lectura sí; escritura solo PUT de valores (sin POST)** | listas-de-precio |
| ¿Clientes? | **Sí** (CRUD, búsqueda por RUT en code) | clientes |
| ¿Stock por bodega? | **Sí** (lectura; escritura vía recepción/consumo; con reservas) | stocks, sucursales |
| ¿Emisión DTE + PDF por API? | **Sí** (POST /documents, devuelve folio, urlPdf, urlXml) | documentos |
| ¿Webhooks (eventos)? | **Sí**: document, product, variant, price, stock, doc. pagado (+ tienda online). Activación por email | webhooks, productos-y-servicios/webhooks |
| ¿Rate limits? | **3.000 req / 300 s (429)** | faq |
| ¿Paginación máx? | **limit máx 50** (default 25) | productos-y-servicios |
| ¿Scopes / expiración de token? | **Desconocido** | — |

---

## Huecos / lo que NO encontré (a resolver con Víctor — contacto Bsale)

1. **Marcas:** ¿existe endpoint para listar/crear marcas? (brandId aparece en packs pero sin endpoint público).
2. **Peso/dimensiones:** ¿hay algún campo nativo no documentado, o se recomienda oficialmente usar attribute_values? ¿Bsale los usa para courier/despacho?
3. **Token:** ¿expira el access_token? ¿hay scopes/permisos por recurso?
4. **"Modified-since" en catálogo:** ¿existe filtro por fecha de modificación en products/variants para polling incremental, o el único camino fiable es webhooks?
5. **Webhooks:** ¿reintentos ante fallo de mi endpoint? ¿firma/validación de origen? ¿latencia típica? ¿beta requieren solicitud aparte?
6. **Unicidad de code (SKU):** ¿la garantiza Bsale a nivel de instancia o puede haber duplicados?
7. **Plan:** ¿qué operaciones concretas bloquea cada plan? ¿límite exacto de SKU de nuestra instancia?
8. **Uptime / SLA** de api.bsale.io.
9. **OAuth:** tiempos de aprobación del app_id y si es imprescindible para nuestro caso single-tenant.

---

## Recomendación de integración para M02

Dado el stack (**Laravel 12 / PHP 8.3 / MySQL 5.7 / HostGator compartido, sin daemons**):

**Qué vive local vs por API.** El catálogo se **espeja desde Bsale** (productos, variantes con SKU=code, categorías, stock, precios) en tablas MySQL enlazadas por **bsale_id (id de variante)**. **Peso y dimensiones se crean como columnas locales** (peso_g, alto_mm, ancho_mm, largo_mm) que **DaliGo posee y Bsale nunca ve**. No intentar escribirlas en attribute_values (frágil, atado a product_type).

**Sync sin daemons.** En hosting compartido sin procesos persistentes, lo natural es **polling por cron** (Laravel scheduler vía cron de HostGator, ej. cada N minutos): recorrer variants/products/price_lists/details/stocks paginando a 50 y respetando 3.000 req/300 s. Los **webhooks son más eficientes** pero requieren activación por email y un endpoint HTTPS público que reciba POST (controller Laravel que solo guarda el resourceId y lo procesa luego) — viable y recomendable como mejora una vez estable, sobre todo para **stock y precios** (que cambian seguido). Enfoque pragmático: **cron desde el día 1**, **webhooks como optimización** cuando se justifique.

**Mantener el enlace bsale_id.** Clave única local = id de variante; guardar también bsale_product_id. En emisión futura de DTE, usar salesId para idempotencia.

**Alcance del Incremento 1.** Recomendación: **catálogo + precios juntos**. Razones: (a) los precios cuelgan de la variante (mismo enlace bsale_id), el costo incremental de traerlos es bajo; (b) un catálogo sin precio tiene poca utilidad operativa para un panel de gestión; (c) ambos son **solo-lectura**, de bajo riesgo. **Dejar para Incremento 2+**: stock por bodega, clientes, emisión de DTE y validación de retiro (ahí entran escritura y webhooks, mayor riesgo).

---

> **Nota DaliGo (estado al 2026-06-08):** el **Incremento 1 de M02 ya implementado** es un
> **catálogo local standalone** (tabla `productos` a nivel SKU con peso_kg/alto_cm/ancho_cm/largo_cm
> y `bsale_variant_id`/`bsale_product_id` nullable) cargado por UI + **CSV**, decidido así porque
> **aún no hay credenciales** de la API de Bsale. La **sincronización real** (espejo lectura
> productos/variantes/precios/stock por cron o webhooks, según este documento) y las **listas de
> precios** quedan para incrementos posteriores, una vez se obtenga el access_token (correo a
> ayuda@bsale.app u OAuth) y se valide con Víctor los huecos de arriba.

---

## Anexo A — Hallazgos empíricos contra la API real (2026-06-08)

> Exploración de **solo lectura** hecha con la cuenta **DEMO BSALE API CL (Cpn 18790)** del panel de
> Devs. Las **formas (shapes) son reales**; los **datos** (conteos, nombres de categorías, etc.) son de
> la cuenta DEMO de Bsale, **no** del catálogo real de DALI (ese se explorará con el token de la empresa
> de producción que se elija). Empresas del panel: DEMO (18790), **IMPORT Y EXPORTA DALI LTDA (26021,
> 76301506-8, Producción)**, **PLASTICOS DALI (102681, 76754504-5, Producción)**, y dos inactivas
> (DALI NORTE 49875, DAMIMED 48550).

### Correcciones a lo de arriba
- **Webhooks = SELF-SERVICE** (corrige la sección A). El panel de Devs tiene un **formulario**: campo
  *URL de destino* (+ toggle "Usa Headers"), checkboxes de topics (**Documento, Variante, Stock, Precio,
  Producto** pre-marcados; + Tienda en línea; + Pagos/RCOF/etc.), checkboxes de **acción: Crear /
  Actualizar**, y botón **AGREGAR HOOK**. NO se piden por correo. Hay listado de hooks existentes con
  eliminar por fila.

### Confirmaciones clave (datos reales)
- **Producto → MUCHAS variantes** (en DEMO: 41.285 productos vs 273.233 variantes ≈ 6,6/producto). →
  **valida** modelar el catálogo local a **nivel variante/SKU**; enlace real = **id de la variante**
  (`bsale_variant_id`), `bsale_product_id` agrupa.
- **NO existe `updatedAt`/`modifiedAt`/`createdAt`** en producto ni variante (lista completa de campos
  verificada). → **No hay sync incremental por fecha.** Estrategia: **carga inicial = barrido completo
  paginado** + **mantención = webhooks** (Variante/Producto/Precio/Stock, acción *Actualizar*).
- **SKU (`code`) = string libre**: numéricos cortos ("12"), EAN ("73884546240647"), alfanuméricos
  ("LC0037"), con guiones ("VMT-adulto"). ⚠️ **No está garantizada la unicidad** del `code` → en la
  sync, la **clave de upsert debe ser `bsale_variant_id`** (no el SKU). `barCode` puede ser un UUID.
- **Sin concepto de marca** (ni `brand` ni `brandId` en producto/variante) → marca vive local.
  Categoría = `product_type` (id + name).
- **Precio por variante**: `variantValue` (neto) + `variantValueWithTaxes` (con IVA). Listas: muchas
  (66 en DEMO, todas CLP en la muestra); **no hay correspondencia nativa "una lista = un canal"** → los
  canales serían convención de DaliGo.
- **Stock** = combinación variante × oficina (`quantity`, `quantityReserved`, `quantityAvailable`).
- **Oficinas/bodegas**: 68 en DEMO, **ninguna `isVirtual=1`** (pero el campo existe; el catastro real de
  DALI tiene ~25 virtuales según la biblia → confirmar contra la cuenta real).
- **Conteos**: el `count` de los listados (`/products.json`, `/variants.json`) **difiere** de
  `/count.json` (los listados filtran por `state` por defecto). Para totales/barridos, usar
  `/count.json` y ser explícito con el filtro `state`. Paginación máx **50**, rate limit **3.000/300s**
  → dimensionar el barrido inicial.

### Implicancia para el incremento de sincronización (futuro)
Receptor de **webhooks** (controller que guarda `resourceId` y procesa con el cron por minuto) para
mantención + **comando de barrido inicial** (paginado a 50, respetando rate limit) para la carga.
Upsert por `bsale_variant_id`. Token de la empresa de producción en `.env` (`BSALE_ACCESS_TOKEN`).
Pendiente de confirmar contra la cuenta REAL: tamaño del catálogo DALI, sus `product_types`, y los
huecos finos (unicidad de `code`, reintentos/firma de webhooks).
