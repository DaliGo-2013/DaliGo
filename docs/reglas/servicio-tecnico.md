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

La cotización industrial. El bloque ya es un partial reusable
(`emails/partials/_garantias-industrial.blade.php`), así que agregarlo es un `@include` —
pero no se hizo sin pedirlo.

> La **pantalla pública del QR de terreno** figuraba acá como el otro candidato y **dejó de
> existir el 25-08-2026** (ver §1bis): el cliente ya no pide visitas industriales. Al correo
> de la visita no le pasó nada — sigue llevando las cinco filas industriales.

### 1bis · LA VISITA INDUSTRIAL SALIÓ DE LA VISTA DEL CLIENTE (25-08-2026)

**Decide:** el gerente general · **Aplicado:** 25-08-2026

Pedido textual: *«que la coordinación de visita/revisión industrial la saques de la vista de
ingreso; estos los harán ahora los vendedores y serán autorizados por el jefe de ventas.
Déjalo solo para vistas de los vendedores y el jefe de ventas, pero siempre manteniendo el
aviso a los clientes»*.

**Qué se retiró.** El flujo público COMPLETO: la cuarta tarjeta del menú del QR, el
formulario, su pantalla «¡Listo!» y el endpoint del cartel de disponibilidad en vivo. No
alcanzaba con esconder la tarjeta: un link firmado guardado —el QR pegado en una máquina, un
correo viejo— habría seguido creando visitas **sin pasar por el vendedor ni por la
autorización del jefe**, o sea salteándose la regla nueva. Un link viejo ahora cae en la
página de error con marca DaliGo.

**Quién las crea ahora.** Nadie nuevo: el camino ya existía. `admin/agenda-terreno/crear`, con
permiso `agendar servicio terreno`, que tienen **vendedor, jefe de ventas y admin** — o sea
exactamente lo que el gerente pidió, sin tocar roles.

**El aviso al cliente no se tocó, y no por suerte.** Vive en el modelo
(`AgendaTrabajo::avisarAlCliente`) y sale por los DOS caminos internos —el vendedor que
agenda y el jefe de ventas que autoriza días después—, así que nunca dependió del formulario
público. Sigue igual el correo de la cita (agendada / movida / anulada) y el link firmado para
que el cliente confirme cuando el día no es el que había pedido.

#### Tres consecuencias que el pedido no nombra y hubo que resolver

**1. La visita técnica pasa a necesitar autorización.** Estaba excluida **a propósito** de
`TIPOS_QUE_AUTORIZA_JEFATURA`, y el comentario decía por qué: *«es la que pide el cliente por
el QR y el vendedor solo la coordina — no es un compromiso que el vendedor decida por su
cuenta»*. Sin formulario público esa razón desaparece: la fija un vendedor, igual que las
otras tres. El candado que afirmaba lo contrario quedó **invertido, no borrado**.

Y el jefe de ventas sigue exento —el motor exime a quien porta el rol aprobador—, con candado
propio: sin eso, el cambio le habría puesto a él una vuelta que termina en su propio
escritorio.

**2. La regla «el técnico va a terreno de lunes a viernes» se quedaba sin dónde aplicar.** Se
validaba SOLO en el formulario público; el camino interno nunca la tuvo, así que un vendedor
ya podía agendar un sábado. Se mudó a `AgendaTrabajoController::bloquearSiNoSeAtiende`, con el
mismo criterio y la misma excepción de admin que los días ya ocupados, y el mensaje ofrece el
**próximo día con disponibilidad** —lo que hacía el cartel en vivo— en vez de dejar al
vendedor tanteando. De paso empezó a cubrir los otros tres tipos, que nunca la tuvieron.

Se valida en las **puntas** del rango y no en cada día: un viaje que arranca viernes y termina
lunes atraviesa el fin de semana a propósito (el técnico se queda allá).

**3. Guardar una visita SIN fecha devolvía un 500.** El registro se creaba bien y el redirect
reventaba leyendo el año de una fecha que no existe: el vendedor veía una pantalla de error
habiendo guardado. No se notaba porque ese camino casi no se usaba —las visitas sin fecha
llegaban por el QR y este formulario se abría para ponerles la fecha—. Al retirar el QR, «lo
anoto y lo coordino cuando hable con el cliente» pasó a ser **la única** forma de dejar una
visita pendiente, así que se arregló acá. El aviso «hay algo por coordinar» que disparaba el
formulario público lo dispara ahora el interno en ese mismo caso: el destinatario no cambió.

#### La hora queda SIN validar, y es una decisión

La verificación **«la hora elegida está dentro del horario del día»** también vivía solo en el
formulario público. Hoy nada impide que un vendedor agende a las 19:00 un miércoles que cierra
16:30. Se planteó cerrarlo —es hermano del hueco de los días, que sí se cerró— y **el dueño
resolvió dejarlo abierto**: *«luego cuando hagamos pruebas con los vendedores que ellos digan
si se va a modificar a un rango de horas más extensas o no»* (26-08-2026).

El razonamiento importa para el que venga después: el `HORARIO` del modelo (L-M 08:00–17:30,
Mi-V 08:00–16:30) puede estar **más angosto que la realidad**, y validar contra él bloquearía
visitas que sí se hacen. Primero se observa cómo agendan los vendedores; después se decide si
el rango se ensancha, si se valida, o las dos cosas. **Falta un dato, no un candado.** Queda
anotado también en el encabezado de `HorarioVisitaTest`.

#### Los candados

De los ~70 que tocaban este flujo, los que probaban el **cálculo** (`disponibilidad`,
`horasDisponibles`, media jornada, próximo libre) bajaron un nivel y ahora consultan el
**modelo**: es el mismo criterio y sobrevive a que la pantalla que lo muestra cambie otra vez.
Los del **envío del cliente** se retiraron con su formulario, cada uno nombrado en el
encabezado de su archivo para que nadie los busque. Y hay nuevos para lo que nació: la puerta
cerrada (tarjeta ausente **y** rutas inexistentes), el rechazo del día no laborable con su
mensaje, la excepción del admin, y el guardado sin fecha.

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
| **Cotización** | Vista previa de lo que el cliente lee | Nada. Solo mirar: subtotal de repuestos, mano de obra, descuento y total con IVA |

La constancia de lo enviado (el historial de cotizaciones) y la tarjeta de **«listo
para retirar»** también viven en el parte, por el mismo motivo: estaban repetidas en
las dos pantallas. **Excepción con nombre: en garantía siguen en la pestaña
Cotización**, porque ahí el parte no las incluye —no hay cotización que enviar— y esa
pestaña es la única pantalla con el botón de avisar el retiro. Y como esas dos
acciones avisan al cliente, el redirect vuelve a donde está la tarjeta que acaba de
cambiar (`ServicioTecnicoController::pantallaDeConstancia`): si no, el usuario aterriza
en una pantalla que ya no muestra lo que hizo.

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

## 4. DOS DOMINIOS que no se mezclan

**La regla (dueño, 28-08-2026):** *«el ingreso por unidad y por lote se refiere a los
dispensadores, y lo de instalaciones, reparaciones, mantenciones y visita técnica es
referido a lo industrial — sopladora, lavadora, osmosis. Que estén las dos opciones
separadas para los vendedores y el jefe de ventas. Es importante que no esté mezclado
aunque los dos temas son de servicio técnico en general.»*

| | **Dispensadores** (taller) | **Industrial** (terreno) |
|---|---|---|
| Qué equipos | dispensadores, lavadoras de botellón | sopladora, lavadora, osmosis, llenadora |
| Quién atiende | Fernando (técnico de taller) | Carlos (técnico industrial) |
| Cómo se ingresa | por unidad (botón del Listado) y **por lote** | agenda de terreno + planilla de instalaciones |
| Qué se registra | una orden de servicio | visita técnica · mantención · reparación · instalación |
| Permisos de ventas | `manage servicio tecnico`, `crear lote servicio` | `agendar servicio terreno`, `gestionar instalaciones` |

**Ventas trabaja en los dos**, así que el vendedor y el jefe de ventas tienen las
**cuatro** puertas (candado: `RoleMatrixSeedTest::test_ventas_tiene_las_dos_puertas_…`,
que nombra en el fallo qué pantalla queda sin abrir). La única asimetría: el vendedor no
lleva `manage servicio tecnico` — no edita órdenes ni la etapa de reparación.

### El menú los separa en dos bloques

```
SERVICIO TÉCNICO
  Informe                 ← sirve a los dos, va arriba y SIN encabezado
  ── DISPENSADORES ──
  Listado
  Ingreso por lote
  ── INDUSTRIAL ──
  Agenda de terreno
  Instalaciones
```

El vocabulario **no es nuevo**: es el que ya usaba el Informe, que ofrece «Dispensadores»
e «Industrial» como dos pantallas desde que existe. Tres decisiones que conviene no
deshacer:

- **El reparto vive en `MenuPrincipal::agrupar()`**, no en el Blade: el menú es DATOS y esa
  es su fuente única. Un ítem nuevo declara su `grupo` y el resto sale solo.
- **Los ítems sin grupo van arriba y sin encabezado.** Debajo de uno se leerían como parte
  de ese dominio — justo la mezcla que esto evita.
- **El orden de los bloques sale de la primera aparición de cada grupo**, no de una lista
  fija: así respeta `priorizarPorRol()` sin saber de roles (al técnico industrial su bloque
  INDUSTRIAL le sube solo).

Un encabezado no puede quedar huérfano —el bloque nace de los ítems que sobrevivieron a la
poda por permiso— y un `grupo` que no esté en `MenuPrincipal::GRUPOS` **revienta** en vez de
dibujar un encabezado vacío.

---

## 4bis. Todo lo de Servicio Técnico le llega al jefe de ventas

**La regla (dueño, 28-08-2026):** *«a Héctor deben llegar todas las notificaciones de
servicio técnico, sean dispensadores o visitas técnicas, mantenciones o instalaciones»*.

### Las TRES puertas de entrada al taller avisan igual

Al taller se entra por tres lados, y hasta el 28-08 **solo uno avisaba**:

| Puerta | Quién la usa | ¿Avisaba antes? |
|---|---|---|
| QR del cliente (unidad y lote) | el cliente desde su celular | sí |
| **Mostrador** (`servicio-tecnico.store`) | quien atiende: técnico, vendedor | **no** |
| **Lote en ruta** (`servicio-tecnico.lote.store`) | el conductor que retira | **no** |

El aviso vive en el **modelo** (`OrdenServicio::notificarIngresoInterno()` y su gemelo de
`LoteServicio`), no en el controlador, justamente para que las tres lo llamen. Al agregar
una cuarta puerta, se llama desde ahí — la lista de destinatarios
(`ROLES_AVISO_INGRESO`) no se toca.

**Quien registra no se autonotifica** (`->reject($actor)`): avisarle de su propia acción es
ruido. Es el mismo criterio de `avisarACartera()`.

**El texto depende del ORIGEN, no es fijo.** La plantilla cierra con `{recepcion}`, que
resuelve `OrdenServicio::fraseDeRecepcion()`: por QR o ruta dice «Falta confirmar la
recepción» (la máquina llega después), y en el mostrador dice quién la recibió — ahí
`por_confirmar` es false por construcción y **no existe el botón de confirmar**. Si mañana
se suma una puerta, la frase se agrega ahí y no en la plantilla.

### Instalaciones también avisa

La planilla del técnico industrial no emitía **ninguna** notificación. Ahora
`instalacion.registrada` va a jefatura (`Instalacion::ROLES_AVISO`) al **registrar** — es
el único momento con los datos completos, porque la planilla no tiene estados: es un
registro de lo ya hecho. El aviso lleva los días trabajados y el vendedor, que son los dos
datos por los que jefatura pregunta después.

Los vendedores **no** reciben este aviso: el `vendedor` de la planilla es texto libre
copiado del Excel, no un usuario, así que no hay a quién dirigirlo.

### El vendedor registra los cuatro trabajos de terreno

> Los permisos de los dos dominios están en la tabla de §4; acá va solo el porqué.

*«El perfil de vendedor tiene que poder ingresar el registro de visita técnica, mantención,
reparación e instalación — el vendedor va a hacer estos ingresos.»* Es el otro lado de
§1bis: si la visita industrial salió de la vista del cliente, alguien de adentro la anota.

Tres de las cuatro ya las cubría `agendar servicio terreno` (son tipos de la agenda de
terreno). La que faltaba era la **planilla de instalaciones**: `gestionar instalaciones`
era del técnico industrial y de jefatura.

**Darle la pantalla no le da la decisión.** Toda cita que un vendedor fije sigue naciendo
ESPERANDO el visto bueno del jefe de ventas y sin ocupar la agenda del técnico (los cuatro
tipos están en `AgendaTrabajo::TIPOS_QUE_AUTORIZA_JEFATURA` desde el 25-08). Permiso y
autorización son cosas distintas y conviene no confundirlas al leer este apartado.

### Y se usa desde el celular

Los vendedores están en terreno. La planilla de instalaciones se verificó a **375 / 768 /
1280** y de ahí salieron dos correcciones que en pantalla grande no se ven: las dos
acciones de la fila ahora son el mismo control táctil (antes Eliminar medía 44×44 y Editar
39×20 — la destructiva era la fácil de acertar) y las líneas de datos truncan **solo desde
`sm:`** (en el celular el recorte se comía la comuna y el RUT). Detalle en la bitácora
[2026-08-28].

---

## 5. Historial de cambios de este apartado

- **20-08-2026** — nace el apartado. §2 (descuento de inventario al facturar y el hueco
  de la garantía) y §1 (la tabla de las cuatro garantías, con los plazos industriales
  nuevos y la advertencia de no mezclarlos con los del taller). Más tarde ese día, §3:
  el presupuesto se arma en una sola pantalla y la cotización queda de solo lectura.
- **28-08-2026** — §4bis: todas las notificaciones de Servicio Técnico le llegan al jefe de
  ventas (las tres puertas del taller, instalaciones) y el vendedor puede registrar los
  cuatro trabajos de terreno. El §4 anterior (historial) pasa a §5.
- **28-08-2026 (más tarde)** — §4: los DOS dominios (dispensadores / industrial) y la
  separación del menú en dos bloques, con las cuatro puertas de ventas en una tabla.
