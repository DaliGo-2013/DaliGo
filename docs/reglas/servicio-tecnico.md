# Servicio técnico — la puerta del módulo

> **Qué es esto:** el apartado donde se guarda todo lo referido a servicio técnico
> (pedido del dueño, 20-08-2026). Si buscás una regla, una decisión pendiente o el
> porqué de algo del taller o del terreno, empezá acá.
>
> El módulo tiene **dos mitades** que conviene no confundir:
> - **Taller (dispensadores).** La máquina entra a la sucursal, la repara Fernando.
>   El cliente declara garantía o reparación al ingresar y el técnico lo confirma
>   al recibirla (`OrdenServicio::FACTURACION`).
> - **Terreno (industrial).** El técnico va donde el cliente: plantas de osmosis,
>   llenadoras, lavadoras. La primera visita es de revisión y la segunda es el
>   trabajo (`AgendaTrabajo::TIPOS`).

---

## Reglas ya documentadas (cada una con su archivo)

| Tema | Archivo | En una línea |
|---|---|---|
| Plazo de entrega al cliente | [`plazo-de-reparacion.md`](plazo-de-reparacion.md) | Se le dice el **plazo en días hábiles**, nunca una fecha calculada. |
| Traslado de máquinas a reparar | [`traslado-maquinas-a-reparar.md`](traslado-maquinas-a-reparar.md) | Cómo viajan las máquinas de sucursal a casa matriz y qué se cuenta al recibirlas. |

Las reglas nuevas de servicio técnico van como archivo propio en `docs/reglas/` y
se suman a esta tabla, para que este documento siga siendo la puerta y no una
enciclopedia.

---

## 1. Las garantías: son CUATRO y no se mezclan

Decisión del dueño, 20-08-2026. Nacieron de un pedido concreto: *«esto es importante
para que todos los clientes sepan al momento de llevar a cabo un arreglo»*.

**Lo que más importa de este apartado es que son dos servicios distintos con plazos
distintos, y que juntarlos sería prometerle al cliente por escrito una cobertura que
el negocio no dio.** Al dictar los plazos industriales se le preguntó expresamente si
reemplazaban a los del taller; respondió que no.

### Taller (dispensadores) — Fernando

| Qué cubre | Plazo | Desde | Dónde vive |
|---|---|---|---|
| El **producto** | 6 meses | la compra | `OrdenServicio::GARANTIA_MESES` |
| La **reparación** | 3 meses | el día en que se repara | `OrdenServicio::GARANTIA_REPARACION_MESES` |

Los 6 meses del producto **no son solo informativos**: son el número que decide si un
ingreso al taller se cobra o entra en garantía. Tocarlos es tocar plata.

### Industrial / terreno — Carlos

| Qué cubre | Plazo | Desde |
|---|---|---|
| **Llenadora** nueva | 1 año | su instalación |
| **Lavadora** nueva | 6 meses | su instalación |
| **Planta de osmosis** nueva | 1 año | su instalación |
| La **reparación** | 1 mes | el día en que se repara |
| La **instalación** (el armado) | 1 mes | que queda funcionando |

La garantía del equipo nuevo corre **desde su instalación**, con las palabras del
dueño: *«todo por instalación, o sea la primera vez cuando se arma todo»*.

Los plazos industriales viven en `config/servicio_tecnico.php` →
`garantias_industrial`, y se leen con `App\Support\GarantiasIndustrial`. Están en
config y no escritos en las plantillas porque son política comercial: cambiarlos ahí
los cambia en todas las superficies que los muestren.

### Dónde los ve el cliente hoy

- **Correo de la visita de terreno** (`emails/terreno/aviso.blade.php`) — las cinco
  filas industriales, en las variantes «agendada» y «reprogramada». En una visita
  **anulada no van**: no va a haber trabajo, y prometer garantías a quien se le acaba
  de cancelar el servicio confunde en vez de informar.
- **Las tres cartas del taller** ya mencionaban sus propios plazos desde el 14-08.

Candados en `tests/Feature/GarantiasIndustrialTest.php`. El central verifica que las
dos tablas **no converjan**: mutando el plazo industrial a los 3 meses del taller, se
ponen rojos tres tests.

### Superficies donde todavía NO están (candidatos naturales)

La cotización industrial y la pantalla pública del QR de terreno. El bloque ya es un
partial reusable (`emails/partials/_garantias-industrial.blade.php`), así que
agregarlo es un `@include` — pero no se hizo sin pedirlo.

---

## 2. El descuento de inventario cuando se factura — PENDIENTE DE DECISIÓN

**Estado:** abierta · **Decide:** el dueño (con contabilidad) · **Anotada:** 20-08-2026
a pedido del dueño, para que no se pierda cuando DaliGo se independice de Bsale.

### La regla que el dueño fijó

> «Es primordial que cuando se facture se haga el descuento de la pieza por
> inventario» (20-08-2026), y **dentro de DaliGo** — no apoyado en Bsale, porque
> «esto se va a independizar a futuro».

### Cómo está hoy, y por qué no contradice esa regla

Hoy el inventario **lo manda Bsale**: las tablas `stocks` y `bodegas` de DaliGo son
un **espejo** que la sincronización sobreescribe, y por eso ningún módulo les
escribe (ver la regla en `CLAUDE.md`). Bsale descuenta el repuesto cuando se
factura, y el espejo lo refleja en la sincronización siguiente.

O sea: **la regla ya se cumple, pero la ejecuta Bsale.** Lo que falta construir es
esa misma ejecución del lado de DaliGo. El precedente de cómo hacerlo ya existe en
el proyecto: `ProduccionMovimiento` es un libro de movimientos **propio** que
registra consumo y producción sin tocar el espejo. El descuento por facturación
seguiría ese molde.

### El hueco que hay que decidir: lo que NO se factura

Si el disparador del descuento es la factura, **todo lo que no se factura no
descuenta**. Y hay un caso corriente que consume pieza y no genera cobro:

**La garantía.** Entra una máquina, el cliente declara garantía, el técnico lo
confirma. Se le cambia la placa eléctrica. Al cliente no se le cobra: no hay
documento. Con «descuenta al facturar», esa placa **sigue contada en el inventario
aunque ya no está en la bodega**.

No es un defecto de programación: es que el disparador elegido no cubre ese camino.
El mismo razonamiento aplica, más chico, a un trabajo de terreno marcado **«no
realizado»** en el que ya se consumió un repuesto (se cambió el filtro y faltó la
membrana).

### Lo que DaliGo YA tiene para resolverlo

Nada de esto hay que construir de nuevo:

- **Sabe si es garantía o reparación**: `OrdenServicio::FACTURACION`
  (`['garantia', 'reparacion']`), declarado por el cliente al ingresar y confirmado
  por el técnico, con su cálculo de vigencia de la garantía.
- **Sabe qué repuestos se usaron y con qué código**:
  - Taller → `orden_servicio_repuestos` (`nombre`, `sku`, `cantidad`,
    `precio_unitario`).
  - Terreno → `agenda_trabajo_repuestos` (`nombre`, `sku`, `cantidad`, **sin
    precio** a propósito: el técnico industrial no maneja precios).
- **Sabe emitir documentos**: `DteEmitido`, `DocumentoVenta` y
  `Services\Bsale\BsaleEmisor`.
- **Tiene el molde del libro propio**: `ProduccionMovimiento`.

### Las opciones, para cuando toque decidir

1. **Descontar al cerrar la orden en garantía** (no al facturar). El disparador es
   el cierre del trabajo, no el cobro. Simple, y refleja la realidad física.
2. **Emitir un documento interno sin valor** que dispare el mismo camino que una
   factura. Deja traza documental y un solo disparador para todo.
3. **Llevarlo aparte como merma de garantía.** No corrige el stock por sí solo,
   pero pone un número a lo que cuesta la garantía — que es información que hoy no
   existe en ninguna parte.

Las tres son compatibles con la regla del dueño; cambian **quién** dispara y **qué
queda registrado**. La 2 y la 3 no se excluyen.

### Antes de implementar cualquiera, hay que preguntar

- ¿El repuesto de garantía sale de la **misma bodega** que uno que se vende?
- ¿Contabilidad necesita un documento por esa salida, o alcanza el registro interno?
- ¿La reposición al proveedor por falla de fábrica se sigue en DaliGo o afuera?

---

## 3. El presupuesto del taller se arma en UNA pantalla

Decisión del dueño, 20-08-2026, después de hablarlo con el técnico: *«toda la
información en un solo apartado»*, *«que la cotización no tenga opción de
modificarse»*, *«el detalle de los repuestos se repite… sácalo, sino es como doble
información»*.

| Pantalla | Qué es | Qué se puede hacer |
|---|---|---|
| **Parte del técnico** | LA pantalla de la orden | Editar todo: etapa, trabajo, causa, repuestos **con precio**, descuento (solo jefatura), fechas — y **enviar** la cotización al cliente |
| **Cotización** | Vista previa de lo que el cliente lee | Nada. Solo mirar: subtotal de repuestos, mano de obra, descuento, total con IVA, y la constancia de lo enviado |

**Por qué está escrito acá y no solo en un commit:** antes había **dos formularios
escribiendo el mismo dinero**, y eso ya se cobró una vez — la regla del descuento
(«solo jefatura lo aplica») estaba copiada en los dos, y una copia se arregla y la otra
no. Hoy la única acción que escribe el presupuesto es `reparacion.guardar`; la pestaña
Cotización **no tiene PUT**. Si alguien vuelve a poner un formulario ahí, el candado
`CotizacionGuardarTest::test_la_pestana_de_cotizacion_es_solo_lectura` se pone rojo por
tres vías distintas.

Dos reglas que van con esto y son fáciles de romper sin darse cuenta:

- **El precio del repuesto se exige al ENVIAR, no al guardar.** El técnico anota el
  repuesto con la máquina delante y le busca el precio después; lo que no puede salir
  al cliente es un repuesto en $0, porque ahí se cobra de menos y nadie lo nota. Mismo
  criterio que la mano de obra sin tiempo estándar.
- **En garantía la pantalla no muestra dinero:** solo qué repuestos se usaron, igual
  que el correo que recibe el cliente. Garantía no se cobra, así que un «Costo total a
  pagar» ahí es una contradicción a la vista del técnico.

---

## 4. Historial de cambios de este apartado

- **20-08-2026** — nace el apartado. §2 (descuento de inventario al facturar y el hueco
  de la garantía) y §1 (la tabla de las cuatro garantías, con los plazos industriales
  nuevos y la advertencia de no mezclarlos con los del taller). Más tarde ese día, §3:
  el presupuesto se arma en una sola pantalla y la cotización queda de solo lectura.
