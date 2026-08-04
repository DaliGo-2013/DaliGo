# Traslado de máquinas a reparar (sucursal → casa matriz)

> Decisión del dueño, 03-08-2026. Motivo textual: *"eliminar las excusas y
> justificaciones, todo transparente"*.

## El problema que resuelve

Abate Molina y Coquimbo **reciben** máquinas pero **no reparan**: la reparación
es en El Mirador (casa matriz). La ficha ya decía *"Se repara en El Mirador"*,
pero del viaje no quedaba registro: ni quién la entregó, ni quién la recibió, ni
cuándo. Una máquina recibida en Coquimbo el 3 y reparada el 20 tenía 17 días sin
ningún responsable — y ahí vivían las excusas.

## La regla

1. **Cadena de custodia obligatoria.** Toda máquina recibida en una sucursal que
   no repara viaja en un **traslado** con emisor y receptor nombrados.

2. **Dos permisos distintos, a propósito.** Que una misma persona pudiera cerrar
   las dos puntas anularía lo único que este registro aporta.
   - `despachar traslado servicio` → jefe de sucursal y los administrativos de
     esa sucursal (1-2 por sucursal).
   - `recibir traslado servicio` → técnico, jefe de bodega y jefe de ventas.

3. **No se repara lo que no llegó.** Una orden de sucursal sin recepción
   confirmada en la matriz **no puede pasar a la etapa de reparación**. Es lo que
   obliga a que el registro exista: sin este candado sería opcional y moriría en
   una semana. Cubre los dos casos: sigue en la sucursal (sin traslado) o va en
   camino (traslado sin confirmar).

4. **El conteo se congela al despachar.** `total_enviado` no se puede editar
   después: es la mitad que hace verificable una diferencia.

5. **Una diferencia es un hecho registrado, no una discusión.** Si el receptor
   confirma menos máquinas de las despachadas, queda como
   *"recibido con diferencias"* y sale un aviso a jefatura y a las dos sucursales
   **con los dos nombres y el folio de cada máquina que falta**.

6. **Un traslado = un origen.** Si se despachan máquinas de dos sucursales, se
   crean dos traslados: el responsable de una entrega es de una sola sucursal.

7. **Recibir cero máquinas es un resultado válido** (y el más grave). No se exige
   marcar al menos una: exigirlo obligaría a mentir para poder cerrar.

## Estados y avisos

| Momento | Estado de la máquina | Aviso |
|---|---|---|
| Recibida en sucursal | En sucursal, sin despachar | — (visible en el listado de traslados) |
| Despachada | En tránsito | `traslado.despachado` → al taller |
| Recibida completa | En el taller (se puede reparar) | `traslado.recibido` → a la sucursal que despachó |
| Recibida incompleta | Las que llegaron, en el taller; el resto sigue en tránsito | `traslado.diferencias` → jefatura + las dos sucursales |

El cliente ve el paso **"Tu equipo va en camino al taller"** en el seguimiento
por folio.

## Sobre las cuentas de sucursal

Al momento de la decisión **no había cuentas creadas** en Abate ni Coquimbo. Por
eso `emisor_id` es nullable y el **nombre se escribe siempre**: la
responsabilidad arranca por nombre (igual que los conductores) y el día que
existan las cuentas queda amarrada a una persona con clave, sin cambiar código.
El nombre se guarda incluso cuando hay cuenta: si el usuario se renombra o se da
de baja, el registro histórico no debe cambiar.

Los administrativos de sucursal reciben `despachar traslado servicio` desde la UI
de Roles cuando se creen sus cuentas — es un permiso aditivo, no requiere deploy.

## Órdenes anteriores al registro

La migración `2026_08_03_120100_sella_llegada_de_ordenes_previas_al_traslado`
sella como llegadas las órdenes de sucursal que ya existían, dejando
`traslado_id` en NULL — se distinguen de las que sí tienen cadena de custodia y
no se inventa un traslado que nunca ocurrió. Sin ese sello, la regla 3 habría
dejado bloqueada toda la operación viva en Abate y Coquimbo.

## Candados

`tests/Feature/Admin/TrasladoServicioTest.php` (19). Fijan las 7 reglas, los dos
permisos por separado, que el candado **no** bloquee la casa matriz ni las
órdenes previas, y que el aviso de diferencias nombre al emisor, al receptor y a
cada máquina faltante.
