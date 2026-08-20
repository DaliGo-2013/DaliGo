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

## 1. El descuento de inventario cuando se factura — PENDIENTE DE DECISIÓN

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

## 2. Historial de cambios de este apartado

- **20-08-2026** — nace el apartado con §1 (descuento de inventario al facturar y el
  hueco de la garantía), a pedido del dueño.
